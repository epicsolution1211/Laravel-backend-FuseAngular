<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ReqOffer extends Model
{
    protected $table="req_offers";

    protected $fillable = [
        'custom_requirement_id',
        'amount',
        'deadline',
        'description'
    ];
}
