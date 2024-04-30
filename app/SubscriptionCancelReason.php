<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SubscriptionCancelReason extends Model
{
	protected $primaryKey = 'id';

    protected $table="subscription_cancel_reasons";
    
    public $timestamps = true;
}
