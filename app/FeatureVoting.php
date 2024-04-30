<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FeatureVoting extends Model
{
    //
    public function features()
    {
        return $this->hasMany('App\FeatureRequests','id','feature_id');
    }

    public function users() {
        return $this->hasOne('App\User','id','user_id');
    }
}
