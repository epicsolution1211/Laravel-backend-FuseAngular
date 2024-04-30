<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Maintenance extends Model
{
    protected $table="josgt_maintenances";

    public $timestamps = false;

    public function maintenance_product()
    {
        return $this->hasMany('App\ProductConfigurations','maintenance');
    }

    public static function getMaintenanceType($product_id) {
      // TODO: get data from database? 51-66
      if ($product_id == 51) {
          return "AtStdMain30";
      } else if ($product_id == 52) {
          return "AtStdMain90";
      }else if ($product_id == 53) {
          return "AtStdMain180";
      } else if ($product_id == 54) {
          return "AtStdMain365";
      } else if ($product_id == 55) {
          return "AtAdvMain30";
      } else if ($product_id == 56) {
          return "AtAdvMain90";
      } else if ($product_id == 57) {
          return "AtAdvMain180";
      } else if ($product_id == 58) {
          return "AtAdvMain365";
      } else if ($product_id == 59) {
          return "AtProMain30";
      } else if ($product_id == 60) {
          return "AtProMain90";
      } else if ($product_id == 61) {
          return "AtProMain180";
      } else if ($product_id == 62) {
          return "AtProMain365";
      } else if ($product_id == 63) {
          return "AtUltMain30";
      } else if ($product_id == 64) {
          return "AtUltMain90";
      } else if ($product_id == 65) {
          return "AtUltMain180";
      } else if ($product_id == 66) {
          return "AtUltMain365";
      } else if ($product_id == 138) {
          return "AtXMain180";
      } else if ($product_id == 137) {
          return "AtXMain365";
      }
      return "";  
    }
    
     public static function getLicenceType($product_id) {
      // TODO: get data from database?
       if ($product_id == 51||$product_id == 52||$product_id == 53||$product_id == 54) {
          return "PREM3";
      } else if ($product_id == 55||$product_id == 56||$product_id == 57||$product_id == 58) {
          return "PREM1";
      } else if ($product_id == 59||$product_id == 60||$product_id == 61||$product_id == 62) {
          return "PREM4";
      } else if ($product_id == 63 || $product_id == 64 || $product_id == 65 || $product_id == 66) {
          return "PREM2";
      }  else if ($product_id == 137 || $product_id == 138 ) {
          return "PREM5";
      } 
      return "";  
    }
    
    
    public static function getDaysCount($product_id) {
      // TODO: get data from database?
      if ($product_id == 51 || $product_id == 55 || $product_id == 59 || $product_id == 63) {
          return 30;
      } else if ($product_id == 52 || $product_id == 56 || $product_id == 60 || $product_id == 64) {
          return 90;
      } else if ($product_id == 53 || $product_id == 57 || $product_id == 61 || $product_id == 65 || $product_id == 138) {
          return 180;
      } else if ($product_id == 54 || $product_id == 58 || $product_id == 62 || $product_id == 66 || $product_id == 137) {
          return 365;
      } 
      return 0;
    }
}
