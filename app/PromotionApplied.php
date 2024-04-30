<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PromotionApplied extends Model
{
    protected $table="promotion_applied";

    protected $fillable = [
        'users_id', 'price', 'discount_price', 'coupon_code'];

    public $timestamps = true;

    public function Promotions(){
        return $this->belongsTo('App\Promotion','promotions_id');
    }
}
	