<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Pool extends Model
{
    //
    public function features()
    {
        return $this->hasMany('App\PoolFeature');
    }
    public function requests()
    {
        //return $this->hasMany('App\FeatureRequests','id');
        return $this->hasMany('App\FeatureRequests','id');  
    }
    public function votes()
    {
        return $this->hasMany('App\FeatureVoting','pool_id');  
    }
}
