<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AdvisorSettings extends Model
{
    protected $table="advisor_settings";

    protected $fillable = [
        'user_id', 'advise_name', 'advise_slug', 'advise_description', 'advise_status'
    ];

    public $timestamps = true;
}
	