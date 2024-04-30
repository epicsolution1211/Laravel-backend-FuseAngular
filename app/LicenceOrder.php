<?php
        
namespace App;

use Illuminate\Database\Eloquent\Model;

class LicenceOrder extends Model {

    protected $table="josgt_licence_order";

    protected $fillable = [
        'user_id'
    ];

    public $timestamps = false;
}