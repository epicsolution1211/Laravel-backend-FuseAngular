<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PromotionUsers extends Model
{
    protected $table="promotion_users";

    protected $fillable = [
        'users_id', 'role_id', 'promotions_id', 'flag', 'is_notify'];

    public $timestamps = true;

    public function Promotions(){
        return $this->belongsTo('App\Promotion','promotions_id');
    }
}
	