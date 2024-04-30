<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductConfigurations extends Model
{
    protected $table="josgt_eshop_product_configuration";

    protected $fillable = [
        'product_id', 'licence_flag', 'type', 'product_name', 'product_price', 'product_desc', 'concurrent_connections', 'trial_period_days', 'maintenance', 'multiserver' , 'licence_type', 'external_id', 'prodject_id', 'plan_id', 'group_id'
    ];

    public $timestamps = true;
}
	