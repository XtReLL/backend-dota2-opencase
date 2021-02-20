const config = require('./config');
const log = require('./log')('bot');

const requestify = require('requestify');

const redisFactory = require('./redisFactory');
const redisClient = redisFactory.buildDefault(log, 'client');
const redisPub = redisFactory.buildDefault(log, 'pub');

redisClient.subscribe('newWithdraw');
redisClient.on('message', (channel, message) => {
  if (channel === 'newWithdraw') {
    const data = JSON.parse(message);
    log.info(`Пришел запрос на вывод от пользователя ${data.steamid} на вещь ${data.market_name}`);
    withdrawItem(data);
  }
});

const SteamCommunity = require('steamcommunity');
const SteamTotp = require('steam-totp');
const TradeOfferManager = require('steam-tradeoffer-manager');
const SteamUser = require('steam-user');

let client = new SteamUser();
let steam = new SteamCommunity();
let manager = new TradeOfferManager({
  "domain": config.nameSite, // For steam api key. It was example.com
  "language": "en",
  "pollInterval": 5000,
  "cancelTime": 86400000
});

let logOnOptions = {
  "accountName": config.bot.username,
  "password": config.bot.password,
  "twoFactorCode": SteamTotp.getAuthCode(config.bot.shared_secret, 0)
};

client.logOn(logOnOptions);

client.on('loggedOn', () => {
  log.info(`Бот авторизовался`);
});

client.on('webSession', (sessionID, cookies) => {
  manager.setCookies(cookies, function (err) {
    if (err) {
      log.error(`Ошибка получения cookie: ${err}`);
      process.exit(1);
      return;
    }

    log.info(`APIKey получен: ${manager.apiKey}`);
  });

  steam.setCookies(cookies);
  steam.startConfirmationChecker(10000, config.bot.identity_secret);
});

steam.on('sessionExpired', (err) => {
  log.error('Сессия устарела, переавторизуемся.');
  client.webLogOn();
});

manager.on('sentOfferChanged', (offer, oldState) => {
  if (offer.state === TradeOfferManager.ETradeOfferState.Declined) {
    redisPub.publish('notify', JSON.stringify({
      steamid: offer.partner.getSteamID64(),
      message: `Вы отменили обмен. Попробуйте вывести снова!`,
      success: false
    }));
    withdrawStatusOffer(offer.id, 0);
  }
});

manager.on('receivedOfferChanged', (offer, oldState) => {
  if (offer.state === TradeOfferManager.ETradeOfferState.Accepted) {
    offer.getReceivedItems((err, items) => {
      if (!err) {
        let newItems = [];
        for (let i = 0; i < items.length; i++) {
          newItems.push({
            assetID: items[i].assetid,
            market_hash_name: items[i].market_hash_name,
            market_name: items[i].market_name
          });
          pushNewItems(newItems);
        }
        log.info(`---> Обмен #${offer.id} принят, вещи переданы серверу!`);
      }
    });
  }
});

manager.on('newOffer', (offer) => {
  log.info(`Пришел новый обмен #${offer.id} от ${offer.partner.getSteamID64()}`);
  if (offer.itemsToGive.length === 0) {
    offer.itemsToReceive.forEach((item) => {
      log.info(`--> Предмет ${item.market_name}`);
    });
    offer.accept((err) => {
      if (err) {
        log.info(`---> Ошибка принятия обмена #${offer.id} ${err.message}`);
      }
    });
  }
});

const withdrawItem = (data) => {
  const item = {
    assetid: data.assetID,
    appid: 570,
    contextid: 2,
    amount: 1
  };
  let offer = manager.createOffer(data.trade_link);
  offer.addMyItem(item);
  offer.setMessage(`Ваш выигрыш с сайта ${config.nameSite}`);
  offer.send((err, status) => {
    if (err) {
      log.error(`--> Ошибка отправки обмена пользователю ${data.steamid} на вещь ${data.market_name}. ${err}`);
      redisPub.publish('notify', JSON.stringify({
        steamid: data.steamid,
        message: `Предмет ${data.market_name} не смог отправится, попробуйте снова!`,
        success: false
      }));
      withdrawStatus(data.gameID, 0);
      return;
    }
    if (status === 'pending') {
      steam.acceptConfirmationForObject(config.bot.identity_secret, offer.id, (err) => {
        redisPub.publish('notify', JSON.stringify({
          steamid: data.steamid,
          message: `Предмет ${data.market_name} отправлен!`,
          success: true
        }));
        log.info(`--> Обмен #${offer.id} отправлен пользователю ${data.steamid}. Вещь ${data.market_name}`);
        setOfferID(offer.id, data.gameID);
      });
    } else {
      redisPub.publish('notify', JSON.stringify({
        steamid: data.steamid,
        message: `Предмет ${data.market_name} отправлен!`,
        success: true
      }));
      log.info(`--> Обмен #${offer.id} отправлен пользователю ${data.steamid}. Вещь ${data.market_name}`);
      setOfferID(offer.id, data.gameID);
    }
  });
};

const withdrawStatus = (gameID, status) => {
  requestify.post(`${config.domainServer}/api/withdrawStatus`, {
    gameID: gameID,
    status: status
  }, (response) => {
    const data = JSON.parse(response.body);
  }, (error) => {
    log.error(error);
  });
};

const withdrawStatusOffer = (offerID, status) => {
  requestify.post(`${config.domainServer}/api/withdrawStatusOffer`, {
    offerID: offerID,
    status: status
  }, (response) => {
    const data = JSON.parse(response.body);
  }, (error) => {
    log.error(error);
  });
};

const setOfferID = (offerID, gameID) => {
  requestify.post(`${config.domainServer}/api/setOfferID`, {
    offerID: offerID,
    gameID: gameID
  }, (response) => {
    const data = JSON.parse(response.body);
  }, (error) => {
    log.error(error);
  });
};

const pushNewItems = (items) => {
  requestify.post(`${config.domainServer}/api/pushNewItems`, {
    items: items
  }, (response) => {
    const data = JSON.parse(response.body);
  }, (error) => {
    log.error(error);
  });
};

const checkStatus = () => {
  requestify.post(`${config.domainServer}/api/checkStatus`, {}, (response) => {
    const data = JSON.parse(response.body);
  }, (error) => {
    log.error(error);
  });
};

const checkBuyItems = () => {
  requestify.post(`${config.domainServer}/api/checkBuyItems`, {}, (response) => {
    const data = JSON.parse(response.body);
  }, (error) => {
    log.error(error);
  });
};

setInterval(() => {
  checkStatus();
  checkBuyItems();
}, 60000);

setInterval(() => {
  client.webLogOn();
}, 21600000);