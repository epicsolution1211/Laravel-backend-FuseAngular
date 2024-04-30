<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PoolFeatureComment extends Model
{
    //
    public function commentUser()
    {
        //return $this->hasOne('App\User','id','user_id');
        return $this->belongsTo(User::class,'comment_by','id');
    }
}
