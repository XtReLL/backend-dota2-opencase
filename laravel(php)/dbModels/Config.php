<?php
/**
 * Created by PhpStorm.
 * User: ToXaHo
 * Date: 02.08.2018
 * Time: 19:17
 */

namespace App;


use Illuminate\Database\Eloquent\Model;

class Config extends Model
{

    protected $table = 'config';

    protected $fillable = [
        'random', 'countitems', 'keycsgotm', 'our_from', 'our_to', 'bank_from', 'bank_to', 'user_from', 'user_to', 'bank'
    ];

}