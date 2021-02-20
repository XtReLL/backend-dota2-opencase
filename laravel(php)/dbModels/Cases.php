<?php
/**
 * Created by PhpStorm.
 * User: ToXaHo
 * Date: 01.08.2018
 * Time: 20:22
 */

namespace App;


use Illuminate\Database\Eloquent\Model;

class Cases extends Model
{

    protected $table = 'cases';

    protected $fillable = [
        'status', 'name', 'slug', 'price', 'images', 'nomer', 'group_id', 'images_hover', 'oldprice', 'value', 'value_text', 'user_count', 'users_need', 'bank', 'freecase'
    ];

    public function category()
    {
        return $this->belongsTo('App\Group', 'group_id');
    }

    public function getUrlAttribute() {
        return empty($this->slug) ? '/game/' . $this->id : '/case/' . $this->slug;
    }
}