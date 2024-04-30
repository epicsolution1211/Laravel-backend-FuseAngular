<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CancelReason extends Model
{
	protected $primaryKey = 'id';

    protected $table="cancel_reasons";
    
    public $timestamps = true;
}
