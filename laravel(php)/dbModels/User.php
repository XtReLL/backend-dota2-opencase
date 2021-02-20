<?php

namespace App;

use App\Http\Controllers\Controller;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'username', 'steamid', 'avatar', 'partner', 'money', 'candy_coins', 'trade_link', 'accessToken', 'is_admin', 'support', 'banned', 'banchat', 'youtuber', 'prize', 'descr', 'free_spin', 'free_time', 'twist', 'now_twist', 'jackpot', 'my_ref', 'use_ref', 'lvl', 'profit_ref', 'newID'
    ];

    public function updateUsername()
    {
        if (empty($this->steamid)) {
            return 'Пустой steamid пользователя';
        }

        $steam_api_key = env('STEAM_API_KEY');
        if (empty($steam_api_key)) {
            return 'Не удалось обновить ваш ник профиля т.к. отсутствует Steam API Key. Сообщите администратору об ошибке.';
        }

        /*
        if (!($curl = curl_init())) {
            return 'Не удалось создать запрос';
        }*/

        $url = "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/?key={$steam_api_key}&steamids={$this->steamid}";
        $out = Controller::httpGet($url, 'STEAM_COMMUNITY');

        if (is_null($out)) {
            return 'Не удалось обновить ваш ник профиля т.к. не удалось создать HTTP запрос';
        }

        /*
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        $out = curl_exec($curl);
        curl_close($curl);*/

        if (empty($out)) {
            return 'Пустой ответ от Steam во время получения вашего ника профиля.';
        }

        $json = json_decode($out, true);
        if (empty($players = $json['response']['players']) && is_array($players) && count($players) > 0) {
            return 'Не удалось проверить ник вашего профиля. Сообщите администратору об ошибке.';
        }

        $username = $players[0]['personaname'];
        $this->username = $username;
        $this->update([
            'username' => $username
        ]);

        return false;
    }

    public function getTradeLinkShortcutAttribute() {
        if (empty($this->trade_link)) {
            return '';
        }

        parse_str(parse_url($this->trade_link, PHP_URL_QUERY), $query);
        if (empty($query['partner'] || !is_numeric($query['partner']) ||
            empty($query['token']))) {
            return '';
        }

        $partner = intval($query['partner']);
        if ($partner <= 0) {
            return '';
        }

        return "{$partner}, {$query['token']}";
    }
}
