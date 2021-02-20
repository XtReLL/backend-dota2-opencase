<?php
/**
 * Created by PhpStorm.
 * User: ToXaHo
 * Date: 02.08.2018
 * Time: 19:34
 */

namespace App;


use Illuminate\Database\Eloquent\Model;

class Game extends Model
{

    protected $table = 'game';

    protected $fillable = [
        'price', 'status', 'userid', 'case', 'ticket', 'item_id', 'buy', 'send', 'ip', 'error', 'hash', 'idtm'
    ];

    public function item()
    {
        return $this->belongsTo('App\Item');
    }

    public function cases()
    {
        return $this->belongsTo('App\Cases', 'case');
    }

}