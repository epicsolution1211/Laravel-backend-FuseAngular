<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Licence extends Model
{
    protected $table="josgt_licences";

    public $timestamps = false;

    public static function getLicenceType($product_id) {
      // TODO: get data from database?
      if ($product_id == 1) {
          return "CLOUD1";
      } else if ($product_id == 2) {
          return "PREM1";
      } else if ($product_id == 3) {
          return "CLOUD3";
      } else if ($product_id == 4) {
          return "CLOUD2";
      } else if ($product_id == 5) {
          return "CLOUD4";
      } else if ($product_id == 6) {
          return "PREM2";
      } else if ($product_id == 7) {
          return "EDITOR";
      } else if ($product_id == 8) {
          return "AGIS";
      } else if ($product_id == 9) {
          return "ATVOXEL";
      } else if ($product_id == 11) {
          return "ARCFALL";
      }else if ($product_id == 69) {
          return "PREM3";
      } else if ($product_id == 68) {
          return "PREM4";
      } else if ($product_id == 133) {
          return "TRIAL";
      } else if ($product_id == 134) {
          return "PREM5";
      } else if ($product_id == 135) {
          return "CCU";
      }/*else if ($product_id == 123) {
          return "LEASE100";
      }*/
      error_log("APANEL getLicenceType product_id unknown  >".$product_id."<");
    
      return "";  
    }

    public static function getConnectionCount($product_id) {
      // TODO: get data from database?
      if ($product_id == 1) {
          return 10;
      } else if ($product_id == 2) {
          return 500;
      } else if ($product_id == 3) {
          return 500;
      } else if ($product_id == 4) {
          return 50;
      } else if ($product_id == 5) {
          return 1000;
      } else if ($product_id == 6) {
          return 100000;
      } else if ($product_id == 7) {
          return 0;
      } else if ($product_id == 8) {
          return 0;
      } else if ($product_id == 9) {
          return 0;
      } else if ($product_id == 11) {
          return 0;
      } else if ($product_id == 69) {
          return 100;
      } else if ($product_id == 68) {
          return 3000;
      } else if ($product_id == 123) {
          return 100;
      } else if ($product_id == 133) {
          return 100;
      } else if ($product_id == 134) { 
          return 1000;
      } else if ($product_id == 135) {
          return 1000;
      }
      return 0;
    }
}
