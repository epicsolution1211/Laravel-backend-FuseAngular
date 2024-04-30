<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductDetails extends Model
{
    protected $table="josgt_eshop_productdetails";

    public $timestamps = false;

    protected $fillable = [
        'product_id', 'product_name', 'product_desc'
    ];
    
    public function products() {
        return $this->hasMany('App\Products');
    }
}
