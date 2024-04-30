<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomRequirement extends Model
{
    protected $table = "custom_requirements";

    protected $fillable = [
        'title',
        'budget',
        'description',
        'deadline',
        'user_id'
    ];
}
