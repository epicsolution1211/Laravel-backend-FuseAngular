<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class VotingPointsConfiguration extends Model
{
    public function voteUser()
    {
        //return $this->hasOne('App\User','id','user_id');
        return $this->belongsTo(User::class,'user_id','id');
    }
}
