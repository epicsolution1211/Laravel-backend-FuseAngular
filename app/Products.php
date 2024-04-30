<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Products extends Model
{
	protected $primaryKey = 'id';

    protected $table="josgt_eshop_products";

    protected $fillable = [
        'manufacturer_id', 'product_price'
    ];

    public $timestamps = false;

    public function scopeSubscription($query) {
        return $query->whereProductAlias('atavism-2018-op-standard-subscription');
    }
    
    public function ProductDetails() {
        return $this->belongsTo('App\ProductDetails','id','product_id');
    }
}
