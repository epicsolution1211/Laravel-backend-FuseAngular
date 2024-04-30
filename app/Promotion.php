<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    protected $table="promotions";

    protected $fillable = [
        'user_id', 'role_id', 'promotion_type', 'promotion_name', 'promotion_slug','price','percentage', 'promotion_status','start_date','end_date'];

    public $timestamps = true;
}
