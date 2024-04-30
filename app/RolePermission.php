<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    public function feature()
    {
        return $this->hasOne('App\Feature','id','feature_id');
    }
}
