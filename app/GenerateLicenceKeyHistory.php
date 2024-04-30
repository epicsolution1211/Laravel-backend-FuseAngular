<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class GenerateLicenceKeyHistory extends Model
{
    protected $table="generate_licence_key_history";

    protected $fillable = [
        'user_id', 'licence_id', 'licence_key_new', 'licence_key_old', 'requested_date', 'verification_date', 'verification_code'
    ];

    public $timestamps = true;
}
	