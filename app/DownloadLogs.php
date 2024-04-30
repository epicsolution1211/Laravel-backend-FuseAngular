<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DownloadLogs extends Model
{
	protected $primaryKey = 'id';

    protected $table="download_logs";
    
    public $timestamps = true;

    public function user()
    {
        //return $this->hasOne('App\User','id','user_id');
        return $this->belongsTo(User::class);
    }
}
