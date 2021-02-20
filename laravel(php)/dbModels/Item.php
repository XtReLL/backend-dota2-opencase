<?php
/**
 * Created by PhpStorm.
 * User: ToXaHo
 * Date: 01.08.2018
 * Time: 21:15
 */

namespace App;


use Illuminate\Database\Eloquent\Model;

class Item extends Model
{

    protected $fillable = [
        'case_id', 'classid_new', 'instanceid', 'market_hash_name', 'classid', 'type', 'price', 'market_name', 'status', 'counts'
    ];

}