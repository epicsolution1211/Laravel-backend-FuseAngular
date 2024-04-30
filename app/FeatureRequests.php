<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FeatureRequests extends Model
{
    //
    public function userUsername()
    {
        //return $this->hasOne('App\User','id','user_id');
        return $this->belongsTo(User::class,'users_id','id');
    }
    public function poolFeatures()
    {
        return $this->belongsTo('App\FeatureRequests');
    }
}
