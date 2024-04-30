<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use Notifiable;
    use HasApiTokens;

    protected $table="josgt_users";

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    //protected $appends = ['user_licence_id'];

    public function getroleAttribute()
    {
        $role = Role::find($this->role_id)->role;
        return $role;
    }

    public function userRole()
    {
        return $this->hasOne('App\Role','id','role_id');
    }

    public function licences(){
        return $this->hasMany('App\Licence','user_id','id');
    }

    public function discord()
    {
        return $this->hasMany('App\Discord','user_id','id');
    }

    public function downloadLogs(){
        return $this->hasMany(DownloadLogs::class);
    }

    public function employee()
    {
        return $this->hasMany('App\Employee','owner_id','id');
    }

    public function maintenance()
    {
        return $this->hasMany('App\Maintenance','user_id');
    }

    // public function userLicenceIds(){
    //     $licences = Licence::select("id")->where("user_id", $this->id)->where('enabled',1)->where('convert_to','<',0)->get();
    //     return $licences;
    // }
}
