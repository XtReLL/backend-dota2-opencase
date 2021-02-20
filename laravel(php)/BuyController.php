<?php

namespace App\Http\Controllers;

use App\Game;
use App\Item;
use App\User;
use App\Config;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Cases;

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

class BuyController extends Controller
{

    public function getNewPrices()
    {
        ini_set('max_execution_time', 900);

        $url = 'https://api.steamapi.io/market/prices/570?key=' . env('HEXA_ONE_API_KEY', '75ba055db430b101fddf60239a88214c');
        $items = json_decode(self::httpGet($url));

        foreach ($items as $name => $item) {
            $price = round($item * 75);
            Item::where('market_name', $name)->update([
                'price' => $price
            ]);
        }
        return 'ok';
    }

    public function withdrawItem(Request $r)
    {
        $explode = explode("??", $r->bsh);
        $bsh = $explode[0];
        $token = $explode[1];

        $user = User::where('remember_token', $token)->first();
        if (!$user) return response()->json(['success' => false, 'message' => 'Ошибка доступа']);
        if ($user->banned) {
            return response()->json([
                'success' => false,
                'message' => 'Ваш профиль был заблокирован, обратитесь к администратору.'
            ]);
        }

        $game = Game::where('id', \Crypt::decrypt($bsh))->whereAnd('userid', $user->id)->where('buy', 0)->where('send', 0)->first();
        if (!$game) return response()->json(['success' => false, 'message' => 'Произошла ошибка!']);

        $case = Cases::find($game->case);

        if ($case->id == 87 || $case->freecase === 1) return response()->json(['success' => false, 'message' => 'Эту вещь нельзя вывести']);

        if (\Cache::has('send-item.' . $user->id)) return response()->json(['success' => false, 'message' => 'Дождитесь результата прошлого вывода!']);
        \Cache::put('send-item.' . $user->id, '', 0.2);

        $item = Item::find($game->item_id);

        if (!$item) return response()->json(['success' => false, 'message' => 'Данного предмета нет в базе!']);

        $checkInventory = $this->checkInventory($item);

        if ($checkInventory['status']) {
            $game->update([
                'send' => 2
            ]);
            $returnValue = [
                'assetID' => $checkInventory['assetID'],
                'trade_link' => $user->trade_link,
                'userID' => $user->id,
                'gameID' => $game->id,
                'market_name' => $item->market_name,
                'steamid' => $user->steamid
            ];
            $this->redis->publish('newWithdraw', json_encode($returnValue));
            return response()->json(['success' => true, 'message' => 'Предмет будет отправлен в течении 5-минут!', 'id' => $game->id]);
        } else {
            $buyItem = $this->buyItem($item);

            if ($buyItem['status']) {
                $game->update([
                    'send' => 1,
                    'idtm' => $buyItem['id']
                ]);
                return response()->json(['success' => true, 'message' => 'Предмет будет отправлен в течении 4-х часов!', 'id' => $game->id]);
            } else {
                return response()->json(['success' => false, 'message' => 'Произошла ошибка, попробуйте позже!', 'type' => $buyItem['type']]);
            }
        }
    }

    public function checkInventory($item)
    {
        if (!\Cache::has('inventory_bot')) {
            $url = 'https://steamcommunity.com/inventory/' . env('STEAM_ID_BOT_INVENTORY') . '/570/2?l=english&count=5000';
            $json = self::httpGet($url, 'STEAM_COMMUNITY');
            if (is_null($json)) {
                \Log::error('Unable to create curl in checkInventory()');
                return ['status' => false];
            }

            $inventory = BuyController::convertInventoryToOldStyle($json);
            \Cache::put('inventory_bot', $inventory, 1);
        } else {
            $inventory = \Cache::get('inventory_bot');
        }

        if ($inventory['success']) {
            foreach ($inventory['rgInventory'] as $info) {
                $item1 = $inventory['rgDescriptions'][$info['classid'] . '_' . $info['instanceid']];
                if ($item1['tradable'] == 0) {
                    continue;
                }
                if ($item1['market_hash_name'] == $item->market_hash_name) {
                    return ['assetID' => $info['id'], 'status' => true];
                }
            }
        }
        return ['status' => false];
    }

    public function buyItem($item)
    {
        $config = Config::find(1);
        $name = str_replace(array(
            "Unusual ",
            "Inscribed ",
            "Elder ",
            "Heroic ",
            "Genuine ",
            "Autographed ",
            "Cursed ",
            "Exalted ",
            "Frozen ",
            "Corrupted ",
            "Auspicious ",
            "Infused "), "", $item->market_name);
        $name = rawurlencode($name);
        $url = 'https://market.dota2.net/api/SearchItemByName/' . $name . '/?key=' . $config->keycsgotm;
        $search = self::httpGet($url, 'MARKET_DOTA2'); // file_get_contents($url, true);

        $search_result = json_decode($search, true);
        if (isset($search_result) && $search_result['success'] && count($search_result['list']) > 0) {
            $itemMarket = $search_result['list'][0];
            $url = 'https://market.dota2.net/api/Buy/' . $itemMarket['i_classid'] . '_' . $itemMarket['i_instanceid'] . '/' . $itemMarket['price'] . '/?key=' . $config->keycsgotm;
            $search = self::httpGet($url, 'MARKET_DOTA2');

            $buy = json_decode($search, true);
            if ($buy['result'] == 'ok') {
                return ['status' => true, 'id' => $buy['id']];
            } else {
                \Log::warning("[Buy Market]: Unable to Buy:\r\n" . $search);
                return ['status' => false, 'type' => 'buy'];
            }
        } else {
            \Log::warning("[Buy Market]: Unable to SearchItemByName:\r\n" . $search);
            return ['status' => false, 'type' => 'search'];
        }
    }

    public function withdrawStatus(Request $r)
    {
        $game = Game::find($r->gameID);
        if (!$game) return response()->json(['success' => false]);
        $game->update([
            'status' => $r->status
        ]);
        return response()->json(['success' => true]);
    }

    public function setOfferID(Request $r)
    {
        $game = Game::find($r->gameID);
        if (!$game) return response()->json(['success' => false]);
        $game->update([
            'hash' => $r->offerID
        ]);
        return response()->json(['success' => true]);
    }

    public function withdrawStatusOffer(Request $r)
    {
        $game = Game::where('hash', $r->offerID)->first();
        if (!$game) return response()->json(['success' => false]);
        $game->update([
            'send' => $r->status
        ]);
        return response()->json(['success' => true]);
    }

    public function pushNewItems(Request $r)
    {
        $items = $r->items;
        foreach ($items as $item) {
            $itemBD = Item::where('market_name', $item['market_name'])->first();
            if (!$itemBD) $itemBD = Item::where('market_name', $item['market_hash_name'])->first();
            if (!$itemBD) continue;
            $game = Game::where('item_id', $itemBD->id)->where('send', 1)->orderBy('id', 'desc')->first();
            if (!$game) continue;

            $user = User::find($game->userid);
            if (!$user) continue;

            $returnValue = [
                'assetID' => $item['assetID'],
                'trade_link' => $user->trade_link,
                'userID' => $user->id,
                'gameID' => $game->id,
                'market_name' => $itemBD->market_name,
                'steamid' => $user->steamid
            ];
            $game->update([
                'send' => 2
            ]);
            $this->redis->publish('newWithdraw', json_encode($returnValue));
        }
        return response()->json(['success' => true]);
    }

    public function checkStatus(Request $r)
    {
        $config = Config::find(1);
        $url = 'https://market.dota2.net/api/Trades/?key=' . $config->keycsgotm;
        $link = self::httpGet($url, 'MARKET_DOTA2');
        $trades = json_decode($link, true);

        if ($trades) {
            foreach ($trades as $trade) {
                if ($trade['ui_status'] == 4) {
                    $url = 'https://market.dota2.net/api/ItemRequest/out/' . $trade['ui_bid'] . '/?key=' . $config->keycsgotm;
                    $link = self::httpGet($url, 'MARKET_DOTA2');
                    $send = json_decode($link, true);
                    print_r($send);
                }
            }
        }
        return response()->json(['success' => true]);
    }

    public function redirectToBotInventory()
    {
        return redirect('https://steamcommunity.com/profiles/' . env('STEAM_ID_BOT_INVENTORY'));
    }

    public function checkBuyItems(Request $r)
    {
        $games = Game::where('send', 1)->get();
        foreach ($games as $game) {
            $time = Carbon::parse($game->updated_at);
            if (Carbon::now()->timestamp >= $time->timestamp + 18000) {
                $game->update([
                    'send' => 0
                ]);
            }
        }
        return response()->json(['success' => true]);
    }

    public static function convertInventoryToOldStyle($d)
    {
        $d = json_decode($d, true);
        $res = [];

        if (empty($d)) return false;
        if ($d['success'] && $d['total_inventory_count'] > 0) {
            $res['success'] = true;

            $pos = 1;
            foreach ($d['assets'] as $asset) {
                $asset['id'] = $asset['assetid'];
                unset($asset['assetid']);
                unset($asset['appid']);
                unset($asset['contextid']);

                $asset['pos'] = $pos;
                $pos++;
                $res['rgInventory'][$asset['id']] = $asset;
            }

            $res['rgCurrency'] = array();
            foreach ($d['descriptions'] as $desc) {
                $desc['icon_drag_url'] = '';

                $id = $desc['classid'] . "_" . $desc['instanceid'];
                $res['rgDescriptions'][$id] = $desc;
            }


        } else
            $res['success'] = false;

        return $res;
    }
}
