const config = require('./config');
const log = require('./log')('app');

const fs = require('fs');
const options = {
  key: fs.readFileSync(config.cert.private, 'utf8'), // путь к ключу
  cert: fs.readFileSync(config.cert.public, 'utf8') // путь к сертификату
};

const app = require('express')();
const server = require('https').createServer(options, app);
const io = require('socket.io')(server);

const redisFactory = require('./redisFactory');
const redisClient = redisFactory.buildDefault(log, 'app client');

let online = 100;

const updateOnline = () => {
  io.emit('online', online);
};

io.on('connection', (socket) => {

  online++;
  updateOnline();

  socket.on('ping', (pong) => {
    log.debug(pong);
  });

  socket.on('disconnect', () => {
    online--;
    updateOnline();
  });
});

redisClient.subscribe('updateLiveDrop');
redisClient.subscribe('notify');
redisClient.on('message', (channel, message) => {
  message = JSON.parse(message);
  if (channel === 'updateLiveDrop') {
    setTimeout(() => {
      io.emit('updateLiveDrop', 1);
    }, 10000);
  }
  if (channel === 'notify') {
    io.emit('notify', message);
  }
});

server.listen(8080);
