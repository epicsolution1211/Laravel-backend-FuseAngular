<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = "notifications";

    protected $fillable = [
        'user_id', 'advise', 'title', 're_notify', 'action'
    ];

    public $timestamps = true;
}
