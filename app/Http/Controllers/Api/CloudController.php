<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\User;
use App\Employee;
use App\Licence;
use App\OrderProduct;
use App\Release;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CloudController extends Controller
{
    private $_nn_api_url, $_nn_api_id, $_nn_api_key, $_nn_invoice_url, $_nn_vapi_key, $_nn_vapi_pass, $_nn_vapi_url;
    public function __construct()
    {
        $this->_nn_api_url = 'https://development.my.ksweb.net/includes/api.php';
        $this->_nn_api_id = 'JNnOj7JlHavKxRcNOASMwpqBeQZptowP';
        $this->_nn_api_key = 'XUJqwa53r9TXzkO0SPJ7P0ZUJQyhss08';
        $this->_nn_invoice_url = 'https://development.my.ksweb.net/viewinvoice.php?id=';
        $this->_nn_vapi_key =  'vlxvjsuklbuaxycmwrawqut5426vdbpl';
        $this->_nn_vapi_pass = 'qyghlvnekfxbvthbmwptixlze1xqikbw';
        $this->_nn_vapi_url = 'cloud.northnetworking.com';
    }
    public function signinCloud(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255',
            'password' => 'required',
        ]);
        if ($validator->fails()) {
            return response(['errors' => $validator->errors()->all()], 422);
        }
        $isEmail = $data['username'];
        // Load user by email address
        if ($isEmail) {
            error_log("APANEL Server login data = " . print_r($data, true));
            $post = array(
                'username' => $this->_nn_api_id,
                'action' => 'ValidateLogin',
                'password' => $this->_nn_api_key,
                'email' => $data['username'],
                'password2' => $data['password'],
                'responsetype' => 'json',
            );

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->_nn_api_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $data1 = curl_exec($ch);
            curl_close($ch);

            error_log("APANEL Server Validate New API Login response = " . print_r($data1, true));

            $return = json_decode($data1);

            if ($return->result == "success") {
                error_log("APANEL Server Validate login result = success ");
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $this->_nn_api_url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_USERPWD, "dev:U9versal!");
                curl_setopt(
                    $ch,
                    CURLOPT_POSTFIELDS,
                    http_build_query(
                        array(
                            'action' => 'GetUsers',
                            'username' => $this->_nn_api_id,
                            'password' => $this->_nn_api_key,
                            'search' => $post['email'],
                            'responsetype' => 'json',
                        )
                    )
                );
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $response = curl_exec($ch);
                curl_close($ch);
                error_log("APANEL Server Validate GetUsers response = " . print_r($response, true));
                $nnusers = json_decode($response);
                $nnucount = $nnusers->totalresults;
                $startnumber = $nnusers->startnumber;
                $nnClintId = 0;
                for ($i = $startnumber; $i < $nnucount + $startnumber; $i++) {
                    $nnuserid  = $nnusers->users[$i]->id;
                    error_log("APANEL Server Validate GetUsers nn user id = " . $nnuserid);
                    if ($nnuserid = $data['userId']) {
                        $nnClintId = $nnusers->users[$i]->clients[0]->id;
                    }
                }
                error_log("APANEL Server Validate nnClintId response = " . $nnClintId);
                if ($nnClintId > 0) {
                    if ($data['rememberme']) {
                        DB::table('josgt_northnetworking_login')->insert(
                            ['user_id' => $data['userId'], 'nn_user_id' => $nnClintId]
                        );
                    }
                    $server = $this->getServers($data['userId'], $nnClintId);
                    $server['nn_user_id'] = $nnClintId;
                    if (count($server)) {
                        return response($server, 200);
                    }
                    //$this->_app->response->headers->set('Location', "/servers/admin");
                } else {
                    $response = ['error' => ['code' => 404, "message" => "ACCOUNT_USER_OR_PASS_INVALID"], 'status' => 'false', 'data' => new \stdClass()];
                    return response($response, 422);
                }
            } else {
                $response = ['error' => ['code' => 404, "message" => "ACCOUNT_USER_OR_PASS_INVALID"], 'status' => 'false', 'data' => new \stdClass()];
                return response($response, 422);
            }
        } else {
            $response = ['error' => ['code' => 404, "message" => "ACCOUNT_USER_OR_EMAIL_INVALID"], 'status' => 'false', 'data' => new \stdClass()];
            return response($response, 422);
        }
        return response($response, 200);
    }

    public function signoutCloud(Request $request)
    {
        $data = $request->all();
        $nId = $data['nn_user_id'];
        $count = DB::table('josgt_northnetworking_login')->where("user_id", $nId)->count();
        if ($count > 0) {
            $result = DB::table('josgt_northnetworking_login')->where("user_id", $nId)->delete();
        }
        $response = ['error' => ['code' => 200, "message" => "North Networking account was disconnected from apanel"], 'status' => 'true', 'data' => $nId];
        return response($response, 200);
    }

    public function serverRestart(Request $request)
    {
        //require_once('/usr/local/virtualizor/sdk/admin.php');
        $data = $request->all();
        $validator = Validator::make($request->all(), [
            'vpsid' => 'required',
        ]);
        if ($validator->fails()) {
            return response(['errors' => $validator->errors()->all()], 422);
        }
        $vpsid = $data['vpsid'];
        $admin = new Virtualizor_Admin_API($this->_nn_vapi_url, $this->_nn_vapi_key, $this->_nn_vapi_pass);
        $output = $admin->restart($vpsid);
        $response = ['error' => ['code' => 200, "message" => "APANEL Server restart vps " . $vpsid . " response"], 'status' => 'true', 'data' => $output];
        return response($response, 422);
    }

    public function serverStop(Request $request)
    {
        //require_once('/usr/local/virtualizor/sdk/admin.php');
        $data = $request->all();
        $validator = Validator::make($request->all(), [
            'vpsid' => 'required',
        ]);
        if ($validator->fails()) {
            return response(['errors' => $validator->errors()->all()], 422);
        }
        $vpsid = $data['vpsid'];
        $key =  'vlxvjsuklbuaxycmwrawqut5426vdbpl';
        $pass = 'qyghlvnekfxbvthbmwptixlze1xqikbw';
        $ip = 'cloud.northnetworking.com';

        $admin = new Virtualizor_Admin_API($ip, $key, $pass);

        $vid = 1175;

        $output = $admin->stop($vid);
        /*$admin = new Virtualizor_Admin_API($this->_nn_vapi_url, $this->_nn_vapi_key, $this->_nn_vapi_pass);
        $output = $admin->stop($vpsid);*/
        $response = ['error' => ['code' => 200, "message" => "APANEL Server stop vps " . $vpsid . " response"], 'status' => 'true', 'data' => $output];
        return response($response, 200);
    }

    public function serverCreate($userId)
    {
        $post = array(
            'action' => 'GetProducts',
            'username' => $this->_nn_api_id,
            'password' => $this->_nn_api_key,
            'pid' => '1,10,11,12,13,14,15,16,17,18',
            'responsetype' => 'json',
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_nn_api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $data = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($data, true);
        error_log("APANEL Server Create getProducts response = " . print_r($response, true));

        // return response()->json([$response,true]);

        $keys = array_keys($response['products']);
        $count = count($keys);
        $plans = array();
        $vpslistorder = array('1' => 1, '10' => 2, '11' => 3, '12' => 4, '13' => 5, '14' => 6, '15' => 7, '16' => 8, '17' => 9, '18' => 10);

        for ($i = 0; $i < $count; $i++) {
            $desc = $response['products']['product'][$i]['description'];
            $decs2 = explode("<br>", $desc);
            $decs2 = array_slice($decs2, 0, -1);
            $desc3 = implode(", ", $decs2);
            $obj = (object) array(
                'name' => $response['products']['product'][$i]['name'] . " - " . $desc3 . " $" . $response['products']['product'][$i]['pricing']['USD']['monthly'] . " monthly ",
                'id' => $response['products']['product'][$i]['pid']
            );
            $plans[$vpslistorder[$response['products']['product'][$i]['pid']] - 1] = $obj;
        }
        $maintanace = self::getMaintenanceExpire2($userId);
        $result = Release::where('show', 1)->where('release_date', "<", $maintanace)->orderBy('release_date', 'desc')->get();
        $releases = [];
        foreach ($result as $release) {
            $releases[$release->version] = $release;
        }
        ksort($plans);
        $now = new \DateTime('NOW', new \DateTimeZone('UTC'));

        $licences = self::getLicences($userId);
        $licences_list = [];
        foreach ($licences as $licence) {
            $date = new \DateTime($licence->maintenance_expire, new \DateTimeZone('UTC'));
            if (($date > $now && strpos($licence->licence_type, 'LEASE') !== false) || strpos($licence->licence_type, 'PREM') !== false) {
                $result = Release::where('show', 1)->where('release_date', "<", $date)->orderBy('release_date', 'desc')->get();
                $releases = [];
                foreach ($result as $release) {
                    $releases[$release->version] = $release;
                }
                $licence->releases = $releases;
                $licences_list[$licence->id] = $licence;
            }
        }
        $response = [
            'error' =>
            ['code' => 200, "message" => "", 'status' => 'true', 'data' => ['licences' => $licences_list, 'plans' => $plans, 'versions' => $releases]]
        ];
        return response($response, 200);
    }

    public function serverCreatePost(Request $request)
    {
        $obraz = "ubuntu-18.04-AtaX1-x86_64.img";
        $data = $request->all();
        $release = Release::where('show', 1)->where('version', (int)$data["version"])->first();
        $licence_count = Licence::where('id', (int)$data["licence_id"])->where('user_id', $data['userId'])->count();
        if ($licence_count < 1) {
            $response = ['error' => ['code' => 404, "message" => "Licence not valid"], 'status' => 'false', 'data' => new \stdClass()];
            return response($response, 422);
        } else {
            $licence = Licence::where('id', (int)$data["licence_id"])->where('user_id', $data['userId'])->first();
            // $hostnameId     = "295";
            // $hostnameId_cat    = "78";
            // $atamail           = "297";
            // $atamail_cat       = "80";
            // $atalicense        = "298";
            // $atalicense_cat    = "81";
            // $ataversion        = "299";
            // $ataversion_cat    = "82";
            // $atatype           = "300";
            // $atatype_cat       = "83";
            $region = "EU";
      $atamail = "3";
      $atalicense = "4";
      $ataversion = "5";
      $atatype = "6";
      $serverId = 1;
            if ($data["server"] == 1) {
                $serverId = 1;
                $region = "EU";
                $atamail =   "3";
                $atalicense =   "4";
                $ataversion =   "5";
                $atatype =   "6";
            } else if ($data["server"] == 10) {
                $serverId = 10;
                $region = "EU";
                $atamail =   "16";
                $atalicense =   "17";
                $ataversion =   "18";
                $atatype =   "19";
            } else if ($data["server"] == 11) {
                $serverId = 11;
                $region = "EU";
                $atamail =   "26";
                $atalicense =   "27";
                $ataversion =   "28";
                $atatype =   "29";
            } else if ($data["server"] == 12) {
                $serverId = 12;
                $region = "EU";
                $atamail =   "36";
                $atalicense =   "37";
                $ataversion =   "38";
                $atatype =   "39";
            } else if ($data["server"] == 13) {
                $serverId = 13;
                $region = "EU";
                $atamail =   "46";
                $atalicense =   "47";
                $ataversion =   "48";
                $atatype =   "49";
            }
            //US Cloud
            else if ($data["server"] == 14) {
                $serverId = 14;
                $region = "US";
                $atamail =   "56";
                $atalicense =   "57";
                $ataversion =   "58";
                $atatype =   "59";
            } else if ($data["server"] == 15) {
                $serverId = 15;
                $region = "US";
                $atamail =   "66";
                $atalicense =   "67";
                $ataversion =   "68";
                $atatype =   "69";
            } else if ($data["server"] == 16) {
                $serverId = 16;
                $region = "US";
                $atamail =   "76";
                $atalicense =   "77";
                $ataversion =   "78";
                $atatype =   "79";
            } else if ($data["server"] == 17) {
                $serverId = 17;
                $region = "US";
                $atamail =   "86";
                $atalicense =   "87";
                $ataversion =   "88";
                $atatype =   "89";
            } else if ($data["server"] == 18) {
                $serverId = 18;
                $region = "US";
                $atamail =   "96";
                $atalicense =   "97";
                $ataversion =   "98";
                $atatype =   "99";
            }


            $customfields = array(
              base64_encode(serialize(
                array(
                  $atamail => $request->email,
                  $atalicense => $licence->licence_key,
                  $ataversion => $release->version,
                  $atatype => $data["installation_type"]
                )
              ))
              //ataemail - 707
              //atalicense - 708
              //ataversion - 732
              //atatype - 733

              //	,
              //	base64_encode(serialize(array("704" => $licence->licence_key)))
            );

            if ($data["server"] == 104 || $data["server"] == 106) {
              $region = "US";
            }
            $option  = array(
              base64_encode(serialize(array("bpid" => "none"))),
              base64_encode(serialize(array("Region" => $region))),
              base64_encode(serialize(array("Operating System" => $obraz)))
            );
            $post = array(
                'action' => 'AddOrder',
                // See https://developers.whmcs.com/api/authentication
                'username' => $this->_nn_api_id,
                'password' => $this->_nn_api_key,
                'clientid' => $data['nn_user_id'],
                'pid' => array($serverId),
                'billingcycle' => array('monthly'),
                'responsetype' => 'json',
                'paymentmethod' => 'paypalcheckout',
                'configoptions' => $option,
                'customfields' => $customfields,
                'promocode' => '6WSWEP6XZ4',
                'hostname' => array($data["hostname"]),
              );
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->_nn_api_url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $rdata = curl_exec($ch);
            curl_close($ch);

            $OrderDraftData = json_decode($rdata, true);
            if($OrderDraftData['result'] === 'success'){

                $server = $this->getServers($data['userId']);
                $response = ['error' => ['code' => 200, "message" => "Server created successfully"], 'status' => 'true', 'data' => $OrderDraftData['invoiceid'], 'server' => $server];
                return response($response, 200);
            }else{
                $response = ['error' => ['code' => 404, "message" => "Can't create order"], 'status' => 'false', 'data' => $rdata];
                return response($response, 422);
            }
        }
    }

    public function getMaintenanceExpire2($userId)
    {
        $licences = self::getLicences($userId);
        $date = new \DateTime("2018-03-27T19:40:00+00:00", new \DateTimeZone('UTC'));
        foreach ($licences as $licence) {
            $date3 = new \DateTime($licence->maintenance_expire, new \DateTimeZone('UTC'));
            if ($date3 > $date) {
                $date = $date3;
            }
        }
        return $date;
    }

    public function getLicences($userId)
    {
        // First check if any new licences need to be added
        self::checkOrders($userId);
        self::getLicenceIds($userId);
        // Return the array of group objects
        $result = Licence::find($this->_licences);
        $data_rel = DB::table('josgt_releases')->select()->where('show', 1)->orderBy('id', 'desc')->first();
        $date_rel = new \DateTime($data_rel->release_date, new \DateTimeZone('UTC'));

        $licences = [];
        foreach ($result as $licence) {
            $date = new \DateTime($licence->maintenance_expire, new \DateTimeZone('UTC'));
            $date2 = new \DateTime($date->format('Y-m-d H:m:s'), new \DateTimeZone('UTC'));
            $now = new \DateTime('NOW', new \DateTimeZone('UTC'));
            $diffDate = $now->diff($date2);
            $licence->diff = $diffDate->format('%a');
            $diffRelDate = $date->diff($date_rel);
            $licence->diffRel = $diffRelDate->format('%a');
            $licence->latestRel = $data_rel->version;
            $licence->latestRelDate =  $date_rel->format('Y-m-d H:m:s');
            $licences[$licence->id] = $licence;
        }
        return $licences;
    }

    /**
     * Checks the users orders and order id's to see if any orders have not yet been converted into licences
     *
     */
    public function checkOrders($userId)
    {
        // Loop through each order
        $orders = self::getOrders($userId);
        $licence_multi = Licence::where('user_id', $userId)->where('licence_type', 'like', 'PREM5')->where('enabled', 1)->first();
        if ($licence_multi == null) {
            error_log("APANEL checkOrders licence_multi= is null");
        }
        //error_log("APANEL checkOrders $licence_multi="+print_r($licence_multi,true));

        foreach ($orders as $order) {
            // If order status = 4 (complete), get order_product details
            if ($orders->order_status_id == 4) {
                $order_products = $this->getOrderProducts($orders->id);
                // Loop through order product and check if there is an entry in the licence table to match
                foreach ($order_products as $order_product) {
                    if (Licence::getLicenceType($order_product->product_id) != "") {
                        $licence_count = self::getLicenceCountForOrderProduct($orders->id, $order_product->product_id);
                        while ($licence_count < $order_product->quantity) {
                            // If no match, create entry in licence table
                            error_log("APANEL Order Checking Id=" . $order->id . " ProdId=" . $order_product->product_id);
                            if ($licence_multi != null) {
                                error_log("APANEL Order Checking licence_multi is not null");
                                $this->extendLicence($licence_multi, $order->id, $order_product->product_id, $order->created_date);
                            } else {
                                error_log("APANEL Order Checking licence_multi is null");
                                $this->generateLicenceKey($order->id, $order_product->product_id, $order->created_date);
                            }
                            if ($licence_multi == null) {
                                $licence_multi = Licence::where('user_id',  $this->id)->where('licence_type', 'like', 'PREM5')->where('enabled', 1)->first();
                            }
                            $licence_count++;
                        }
                    } else if (Maintenance::getDaysCount($order_product->product_id) > 0) {
                        error_log("APANEL Order Checking Id=" . $order->id . " ProdId=" . $order_product->product_id . " Maintenance");
                        $licence_count = $this->getMaintenanceCountForOrderProduct($order->id, $order_product->product_id);
                        while ($licence_count < $order_product->quantity) {
                            // If no match, create entry in licence table
                            $this->generateMaintenanceKey($order->id, $order_product->product_id);
                            $licence_count++;
                        }
                    }
                }
            }
        }
        if ($licence_multi != null) {
            error_log("APANEL checkOrders licence_multi= is not null");
        } else {
            error_log("APANEL checkOrders licence_multi= is null");
        }
    }

    public function getLicenceIds($userId)
    {
        // Fetch from database, if not set
        if (!isset($this->_licences)) {
            $result = DB::table("josgt_licences")->select("id")->where("user_id", $userId)->where('enabled', 1)->where('convert_to', '<', 0)->get();

            $this->_licences = [];
            foreach ($result as $licence) {
                $this->_licences[] = $licence->id;
            }
        }

        return $this->_licences;
    }

    public function getOrders($userId)
    {
        self::getOrderIds($userId);
        $orders = [];
        foreach($this->_orders as $order_id){
            $order = DB::table('josgt_eshop_orders')->find($order_id);
            array_push($orders, $order);
        }
        /*echo "<pre>"; print_r($result); exit;
        foreach ($result as $order){
            $orders[$result->id][] = $order;
        }*/

        return $orders;
    }

    public function getOrderIds($userId)
    {
        if (!isset($this->_orders)) {
            $result = DB::table("josgt_eshop_orders")->select("id")->where("customer_id", $userId)->get();
            $this->_orders = [];
            foreach ($result as $key => $order) {
                $this->_orders[] = $order->id;
            }
        }

        return $this->_orders;
    }

    public function getOrderProducts($order_id)
    {
        $order_product_ids = self::getOrderProductIds($order_id);

        // Return the array of group objects
        $result = OrderProduct::find($order_product_ids);

        $order_products = [];
        foreach ($result as $product) {
            $order_products[$product->id] = $product;
        }
        return $order_products;
    }

    public function getOrderProductIds($order_id)
    {
        $result = DB::table('josgt_eshop_orderproducts')->select("id")->where("order_id", $order_id)->get();

        $order_product_ids = [];
        foreach ($result as $order) {
            $order_product_ids[] = $order->id;
        }

        return $order_product_ids;
    }

    public function getLicenceCountForOrderProduct($order_id, $product_id)
    {
        $result = DB::table('josgt_licences')->select("id")->where("order_id", $order_id)->where("product_id", $product_id)->get();
        return count($result);
    }

    public function serverStart(Request $request)
    {
        $data = $request->all();
        $vid = $data['vpsid'];
        $admin = new Virtualizor_Admin_API($this->_nn_vapi_url, $this->_nn_vapi_key, $this->_nn_vapi_pass);
        $output = $admin->start($vid);
        $response = ['error' => ['code' => 200, "message" => "APANEL Server start vps " . $vid . " response"], 'status' => 'true', 'data' => $output];
        return response($response, 200);
    }

    public function refreshAccessToken(Request $request)
    {
        $user = \Auth::user();
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['token' => $user->api_token, 'user' => $user]];
        return response($response, 200);
    }

    public function changePassword(Request $request)
    {
        $data = $request->all();
        $userData = User::find($request->get('userId'));
        // update password
        $userData->password = bcrypt($request->password);
        $userData->save();

        //$employee = Employee::where('owner_id', $request->get('loggedin_id'))->update(['password' => bcrypt($request->password)]);

        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Password Updated Successfully'];
        return response($response, 200);
    }

    public function facebookLogin(Request $request)
    {
        $user = User::with('userRole', 'userRole.permissions', 'userRole.permissions.feature')->where('email', $request->email)->first();
        if ($user) {
            if ($user->block == 1) {
                $response = ['error' => ['code' => 404, 'message' => 'This account has been disabled. Please contact us for more information.'], 'status' => 'false', "message" => "This account has been disabled. Please contact us for more information.", 'data' => new \stdClass()];
                return response($response, 422);
            }

            $date = Carbon::now();
            $token = $user->createToken('Laravel Password Grant Client')->accessToken;
            $user->api_token = $token;
            $user->lastvisitDate = $date->toDateTimeString();
            $user->save();
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['token' => $token, 'user' => $user]];
            return response($response, 200);
        } else {
            $response = ["message" => 'Your Facebook Account is not connected to a local account. Plase register on https://www.atavismonline.com first.'];
            return response($response, 422);
        }
    }

    public function getServers($userId = NULL, $nn_user_id = NULL)
    {
        //$nn_user_id = DB::table('josgt_northnetworking_login')->where('user_id',$userId)->first();
        $post = array(
            'username' => $this->_nn_api_id,
            'password' => $this->_nn_api_key,
            'action' => 'GetClientsProducts',
            'clientid' => $nn_user_id,
            'responsetype' => 'json',
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->_nn_api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $data = curl_exec($ch);
        curl_close($ch);
        $return = json_decode($data, true);
        $servers = [];
        $vpsids = array();

        if ($return['result'] === 'success') {
            if ($return['products'] !== "") {
                $vpsids= array();
                foreach ($return['products']['product'] as $product) {
                    error_log("APANEL Server product response = " . print_r(json_encode($product), true));
                    //$product->customfields->customfield
                    $vpsid = 0;
                    $mysqlPass = "";

                    if ($product['status'] == "Active") {
                        foreach($product['customfields']['customfield'] as $customfield){
                            error_log("APANEL Server custom field ".$customfield['name']." value ".$customfield['value']);
                            if($customfield['name'] == "vpsid"){
                                $vpsid = $customfield['value'];
                            }
                            if($customfield['name'] == "MySQL Password"){
                                $mysqlPass = $customfield['value'];
                            }
                        }
                    }
                    if($vpsid>0){
                        $vpsids[]= $vpsid;
                    }
                    error_log("APANEL Server vpsid ".$vpsid." myPass ".$mysqlPass);
                    $product['vpsid'] = $vpsid;
                    $product['mysqlpass'] = $mysqlPass;

                    if($product['status'] =="Pending"){
                        $ch1 = curl_init();
                        curl_setopt($ch1, CURLOPT_URL, $this->_nn_api_url);
                        curl_setopt($ch1, CURLOPT_POST, 1);
                        curl_setopt($ch1, CURLOPT_POSTFIELDS,
                            http_build_query(
                                array(
                                    'action' => 'GetOrders',
                                    'username' => $this->_nn_api_id,
                                    'password' => $this->_nn_api_key,
                                    'id'=> $product['orderid'],
                                    'responsetype' => 'json',
                                    )
                                )
                        );
                        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
                        $orderresponse = curl_exec($ch1);
                        curl_close($ch1);
                        error_log("APANEL Server GetOrders response = ".print_r($orderresponse, true));

                        $orderResponseData = json_decode($orderresponse);
                        foreach($orderResponseData->orders->order as $order){
                            error_log("APANEL Server order invoice = ".$order->id);
                            if($product['orderid'] == $order->id){
                                $product['invoiceid'] = $order->invoiceid;
                                $product['invoiceUrl'] = $this->_nn_invoice_url.$order->invoiceid;
                                error_log("APANEL Server set invoide = ".$order->id);

                            }
                        }
                    }
                    //$product->vpsstatus = $status;
                    $servers[$product['id']]=$product;
                }
                error_log("APANEL Server vpsids ".json_encode($vpsids));
                if (count($vpsids) > 0) {
                    $admin = new Virtualizor_Admin_API($this->_nn_vapi_url, $this->_nn_vapi_key, $this->_nn_vapi_pass);
                    $output = $admin->status($vpsids);
                    if ($output != null) {
                        foreach ($output as $key => $value) {
                            if (is_array($value)) {
                                foreach ($servers as $server) {
                                    if ($server['vpsid'] == $key) {
                                        $server['vpsstatus'] = $value['status'];
                                        $server['used_bandwidth'] = $value['used_bandwidth'];
                                        $server['bandwidth'] = $value['bandwidth'];
                                    }
                                }
                            }
                        }
                    } else {
                    }
                }
            }
            // $count = count($return['accounts']);
            // for ($i = 0; $i < $count; $i++) {
            //     $admin = new Virtualizor_Admin_API($this->_nn_vapi_url, $this->_nn_vapi_key, $this->_nn_vapi_pass);

            //     $post = array(
            //         'api_id' => $this->_nn_api_id,
            //         'api_key' => $this->_nn_api_key,
            //         'call' => 'getAccountDetails',
            //         'id' => $return['accounts'][$i]['id'],
            //     );
            //     $ch = curl_init();
            //     curl_setopt($ch, CURLOPT_URL, $this->_nn_api_url);
            //     curl_setopt($ch, CURLOPT_POST, 1);
            //     curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            //     curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            //     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            //     curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            //     $acdata = curl_exec($ch);
            //     curl_close($ch);
            //     $acreturn = json_decode($acdata, true);
            //     $server = new \stdClass;
            //     $server->servername = $acreturn['details']['category_name'];
            //     $server->name = $acreturn['details']['product_name'];
            //     if (isset($acreturn['details']['vpsip'])) {
            //         $server->dedicatedip = $acreturn['details']['vpsip'];
            //     } else {
            //         $server->dedicatedip = "";
            //     }
            //     $server->domain = $acreturn['details']['domain'];
            //     $server->invoiceid = -1;
            //     $server->mysqlpass = "";
            //     if ($acreturn['details']['status'] == "Pending") {

            //         $post = array(
            //             'api_id' => $this->_nn_api_id,
            //             'api_key' => $this->_nn_api_key,
            //             'call' => 'getOrderDetails',
            //             'id' => $acreturn['details']['order_id']
            //         );
            //         $ch = curl_init();
            //         curl_setopt($ch, CURLOPT_URL, $this->_nn_api_url);
            //         curl_setopt($ch, CURLOPT_POST, 1);
            //         curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            //         curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            //         curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            //         curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            //         curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            //         $orderdata = curl_exec($ch);
            //         curl_close($ch);
            //         error_log("APANEL Server GetOrders response = " . print_r($orderdata, true));
            //         $orderResponseData = json_decode($orderdata, true);
            //         if ($orderResponseData['success']) {
            //             $invoiceid = $orderResponseData['details']['invoice_id'];
            //             error_log("APANEL Server set invoide = " . $invoiceid);
            //             $server->invoiceid = $invoiceid;
            //             $server->invoiceUrl = $this->_nn_invoice_url . $invoiceid;
            //         }
            //     }

            //     if (is_array($acreturn['details']['custom'])) {
            //         $custom_keys = array_keys($acreturn['details']['custom']);
            //         for ($k = 0; $k < count($custom_keys); $k++) {
            //             if ($acreturn['details']['custom'][$custom_keys[$k]]['name'] == 'MySQL Password') {
            //                 $mysqlPasKey =  array_keys($acreturn['details']['custom'][$custom_keys[$k]]['data']);
            //                 $server->mysqlpass = $acreturn['details']['custom'][$custom_keys[$k]]['data'][$mysqlPasKey[0]];
            //             }
            //         }
            //     } else {
            //         $server->mysqlpass = "";
            //     }
            //     $vpsid = -1;
            //     if (count($acreturn['details']['extra_details']) > 0) {
            //         if (isset($acreturn['details']['extra_details']['option6'])) {
            //             if ($acreturn['details']['extra_details']['option6'] != "") {
            //                 $vpsid = $acreturn['details']['extra_details']['option6'];
            //             }
            //         }
            //     }
            //     if ($vpsid > 0) {
            //         $vpsids[] = $vpsid;
            //         if ($server->dedicatedip == "") {
            //             $p = array();
            //             $p['vpsid'] = $vpsid;
            //             $adminoutput = $admin->listvs(0, 0, $p);
            //             if (isset($adminoutput[$vpsid]) && isset($adminoutput[$vpsid]['ips'])) {
            //                 if (is_array($adminoutput[$vpsid]['ips'])) {
            //                     $vpsadmin_keys = array_keys($adminoutput[$vpsid]['ips']);
            //                     $server->dedicatedip = $adminoutput[$vpsid]['ips'][$vpsadmin_keys[0]];
            //                 }
            //             }
            //         }
            //     }
            //     $server->status = 0;
            //     $server->vpsstatus = "";
            //     $server->vpsid = $vpsid;
            //     $server->bandwidth = -1;
            //     $server->used_bandwidth = -1;

            //     $servers[] = $server;
            // }

            // if (count($vpsids) > 0) {
            //     $admin = new Virtualizor_Admin_API($this->_nn_vapi_url, $this->_nn_vapi_key, $this->_nn_vapi_pass);
            //     $output = $admin->status($vpsids);
            //     if ($output != null) {
            //         foreach ($output as $key => $value) {
            //             if (is_array($value)) {
            //                 foreach ($servers as $server) {
            //                     if ($server->vpsid == $key) {
            //                         $server->vpsstatus = $value['status'];
            //                         $server->used_bandwidth = $value['used_bandwidth'];
            //                         $server->bandwidth = $value['bandwidth'];
            //                     }
            //                 }
            //             }
            //         }
            //     } else {
            //     }
            // }
        } else {
            error_log("APANEL Server GetClientsServers getClientAccounts not get Accounts (Services/Servers)");
        }

        error_log("APANEL Server end servers = " . print_r($servers, true));
        return $servers;
    }
}

class Virtualizor_Admin_API
{

    var $key = '';
    var $pass = '';
    var $ip = '';
    var $port = 4085;
    var $protocol = 'https';
    var $error = array();

    /**
     * Contructor
     *
     * @author       Pulkit Gupta
     * @param        string $ip IP of the NODE
     * @param        string $key The API KEY of your NODE
     * @param        string $pass The API Password of your NODE
     * @param        int $port (Optional) The port to connect to. Port 4085 is the default. 4084 is non-SSL
     * @return       NULL
     */
    public function __construct()
    {
        $this->_nn_api_url = 'https://sandbox.bill.northnetworking.com/admin/api.php';
        $this->_nn_api_id = '1435316b2692211e912a';
        $this->_nn_api_key = 'cac032dfd75342875c71';
        $this->_nn_invoice_url = 'https://sandbox.bill.northnetworking.com/clientarea/invoice/&id=';
        $this->_nn_vapi_key =  'vlxvjsuklbuaxycmwrawqut5426vdbpl';
        $this->_nn_vapi_pass = 'qyghlvnekfxbvthbmwptixlze1xqikbw';
        $this->_nn_vapi_url = 'cloud.northnetworking.com';
    }


    function Virtualizor_Admin_API($ip, $key, $pass, $port = 4085)
    {
        $this->key = $key;
        $this->pass = $pass;
        $this->ip = $ip;
        $this->port = $port;
        if ($port != 4085) {
            $this->protocol = 'http';
        }
    }

    /**
     * Dumps a variable
     *
     * @author       Pulkit Gupta
     * @param        array $re The Array or any other variable.
     * @return       NULL
     */
    function r($re)
    {
        echo '<pre>';
        print_r($re);
        echo '</pre>';
    }

    /**
     * Unserializes a string
     *
     * @author       Pulkit Gupta
     * @param        string $str The serialized string
     * @return       array The unserialized array on success OR false on failure
     */
    function _unserialize($str)
    {

        $var = @unserialize($str);
        if (empty($var)) {
            $str = preg_replace('!s:(\d+):"(.*?)";!se', "'s:'._strlen('$2').':\"$2\";'", $str);

            $var = @unserialize($str);
        }

        //If it is still empty false
        if (empty($var)) {

            return false;
        } else {

            return $var;
        }
    }

    /**
     * Make an API Key
     *
     * @author       Pulkit Gupta
     * @param        string $key An 8 bit random string
     * @param        string $pass The API Password of your NODE
     * @return       string The new APIKEY which will be used to query
     */
    function make_apikey($key, $pass)
    {
        $pass = "qyghlvnekfxbvthbmwptixlze1xqikbw";
        return $key . md5($pass . $key);
    }

    /**
     * Generates a random string for the given length
     *
     * @author       Pulkit Gupta
     * @param        int $length The length of the random string to be generated
     * @return       string The generated random string
     */
    function generateRandStr($length)
    {
        $randstr = "";
        for ($i = 0; $i < $length; $i++) {
            $randnum = mt_rand(0, 61);
            if ($randnum < 10) {
                $randstr .= chr($randnum + 48);
            } elseif ($randnum < 36) {
                $randstr .= chr($randnum + 55);
            } else {
                $randstr .= chr($randnum + 61);
            }
        }
        return strtolower($randstr);
    }

    /**
     * Makes an API request to the server to do a particular task
     *
     * @author       Pulkit Gupta
     * @param        string $path The action you want to do
     * @param        array $post An array of DATA that should be posted
     * @param        array $cookies An array FOR SENDING COOKIES
     * @return       array The unserialized array on success OR false on failure
     */
    function call($path, $data = array(), $post = array(), $cookies = array())
    {

        $key = $this->generateRandStr(8);
        $apikey = $this->make_apikey($key, $this->pass);

        //$url = ($this->protocol).'://'.$this->ip.':'. $this->port .'/'. $path;
        $url = ($this->protocol) . '://' . $this->_nn_vapi_url . ':' . $this->port . '/' . $path;
        $url .= (strstr($url, '?') ? '' : '?');
        $url .= '&api=serialize&apikey=' . rawurlencode($apikey);
        // Pass some data if there
        if (!empty($data)) {
            $url .= '&apidata=' . rawurlencode(base64_encode(serialize($data)));
        }
        // Set the curl parameters.
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);

        // Time OUT
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

        // Turn off the server and peer verification (TrustManager Concept).
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

        // UserAgent
        curl_setopt($ch, CURLOPT_USERAGENT, 'Softaculous');

        // Cookies
        if (!empty($cookies)) {
            curl_setopt($ch, CURLOPT_COOKIESESSION, true);
            curl_setopt($ch, CURLOPT_COOKIE, http_build_query($cookies, '', '; '));
        }

        if (!empty($post)) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // Get response from the server.
        $resp = curl_exec($ch);
        curl_close($ch);
        // The following line is a method to test
        //if(preg_match('/sync/is', $url)) echo $resp;

        if (empty($resp)) {
            return false;
        }

        $r = @unserialize($resp);

        if (empty($r)) {
            return false;
        }
        return $r;
    }

    /**
     * Create a VPS
     *
     * @author       Pulkit Gupta
     * @param        string $path The action you want to do
     * @param        array $post An array of DATA that should be posted
     * @param        array $cookies An array FOR SENDING COOKIES
     * @return       array The unserialized array on success OR false on failure
     */
    function addippool($post)
    {
        $post['addippool'] = 1;
        $path = 'index.php?act=addippool';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function addips($post)
    {
        $post['submitip'] = 1;
        $path = 'index.php?act=addips';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function addiso($post)
    {
        $path = 'index.php?act=addiso';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function deleteiso($post)
    {
        $path = 'index.php?act=iso';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function addplan($post)
    {
        $post['addplan'] = 1;
        $path = 'index.php?act=addplan';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function mediagroups($page = 1, $reslen = 50, $post = array())
    {
        if (empty($post)) {
            $path = 'index.php?act=mediagroups';
            $ret = $this->call($path, array(), $post);
        } else {
            $path = 'index.php?act=mediagroups&mgid=' . $post['mgid'] . '&mg_name=' . $post['mg_name'] . '&page=' . $page . '&reslen=' . $reslen;
            $ret = $this->call($path, array(), $post);
        }
        return $ret;
    }

    function addserver($post)
    {
        $post['addserver'] = 1;
        $path = 'index.php?act=addserver';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function servergroups($post = 0)
    {
        $path = 'index.php?act=servergroups';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function addtemplate($post)
    {
        $post['addtemplate'] = 1;
        $path = 'index.php?act=addtemplate';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function adduser($post = 0)
    {
        $path = 'index.php?act=adduser';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    /**
     * Create a VPS
     *
     * @author       Pulkit Gupta
     * @param        array $post An array of DATA that should be posted
     * @param        array $cookies An array FOR SENDING COOKIES
     * @return       array The unserialized array on success OR false on failure
     */
    function addvs($post, $cookies = '')
    {
        $path = 'index.php?act=addvs';
        $post = $this->clean_post($post);
        $ret = $this->call($path, '', $post, $cookies);
        return array(
            'title' => $ret['title'],
            'error' => @empty($ret['error']) ? array() : $ret['error'],
            'vs_info' => $ret['newvs'],
            'globals' => $ret['globals']
        );
    }

    /**
     * Create a VPS (V2 Method)
     *
     * @author       Pulkit Gupta
     * @param        array $post An array of DATA that should be posted
     * @param        array $cookies An array FOR SENDING COOKIES
     * @return       array The unserialized array on success OR false on failure
     */
    function addvs_v2($post, $cookies = '')
    {
        $path = 'index.php?act=addvs';
        $post['addvps'] = 1;
        $post['node_select'] = 1;
        $ret = $this->call($path, '', $post, $cookies);
        return array(
            'title' => $ret['title'],
            'error' => @empty($ret['error']) ? array() : $ret['error'],
            'vs_info' => $ret['newvs'],
            'globals' => $ret['globals'],
            'done' => $ret['done']
        );
    }

    function addiprange($post)
    {
        $path = 'index.php?act=addiprange';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function editiprange($post)
    {
        $path = 'index.php?act=editiprange&ipid=' . $post['ipid'];
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function iprange($page = 1, $reslen = 50, $post = null)
    {
        if (empty($post)) {
            $path = 'index.php?act=ipranges&page=' . $page . '&reslen=' . $reslen;
            $ret = $this->call($path, array(), $post);
        } elseif (isset($post['delete'])) {
            $path = 'index.php?act=ipranges';
            $ret = $this->call($path, array(), $post);
        } else {
            $path = 'index.php?act=ipranges&ipsearch=' . $post['ipsearch'] . '&ippoolsearch=' . $post['ippoolsearch'] . '&lockedsearch=' . $post['lockedsearch'] . '&page=' . $page . '&reslen=' . $reslen;
            $ret = $this->call($path, array(), $post);
        }
        return $ret;
    }

    function addsg($post)
    {
        $post['addsg'] = 1;
        $path = 'index.php?act=addsg';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function editsg($post)
    {
        $post['editsg'] = 1;
        $path = 'index.php?act=editsg&sgid=' . $post['sgid'];
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function deletesg($post)
    {
        $path = 'index.php?act=servergroups';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function listbackupplans($page = 1, $reslen = 50, $post = array())
    {
        $path = 'index.php?act=backup_plans&page=' . $page . '&reslen=' . $reslen;
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function addbackupplan($post = array())
    {
        $post['addbackup_plan'] = 1;
        $path = 'index.php?act=addbackup_plan';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function editbackupplan($post = array())
    {
        $post['editbackup_plan'] = 1;
        $path = 'index.php?act=editbackup_plan';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function deletebackupplan($post)
    {
        $path = 'index.php?act=backup_plans';
        $ret = $this->call($path, array(), $post);
        unset($ret['backup_plans']);
        return $ret;
    }

    function backupservers($page = 1, $reslen = 50, $post = array())
    {
        $path = 'index.php?act=backupservers&page=' . $page . '&reslen=' . $reslen;
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function deletebackupservers($post)
    {
        $path = 'index.php?act=backupservers';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function testbackupservers($post)
    {
        $path = 'index.php?act=backupservers';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function addbackupserver($post)
    {
        $post['addbackupserver'] = 1;
        $path = 'index.php?act=addbackupserver';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function editbackupserver($post)
    {
        $post['editbackupserver'] = 1;
        $path = 'index.php?act=editbackupserver&id=' . $post['id'];
        $ret = $this->call($path, array(), $post);

        return $ret;
    }

    function addstorage($post)
    {
        $post['addstorage'] = 1;
        $path = 'index.php?act=addstorage';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function storages($post = array(), $page = 1, $reslen = 50)
    {
        $path = 'index.php?act=storage&page=' . $page . '&reslen=' . $reslen;
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function editstorage($post)
    {
        $post['editstorage'] = 1;
        $path = 'index.php?act=editstorage&stid=' . $post['stid'];
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function orhpaneddisks($post = array())
    {
        $path = 'index.php?act=orphaneddisks';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function adddnsplan($post)
    {
        $post['adddnsplan'] = 1;
        $path = 'index.php?act=adddnsplan';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function listdnsplans($page = 1, $reslen = 50, $post = array())
    {
        if (!isset($post['planname'])) {
            $path = 'index.php?act=dnsplans';
            $ret = $this->call($path, array(), $post);
        } else {
            $path = 'index.php?act=dnsplans&planname=' . $post['planname'] . '&page=' . $page . '&reslen=' . $reslen;
            $ret = $this->call($path, array(), $post);
        }
        return $ret;
    }

    function edit_dnsplans($post = array())
    {
        $post['editdnsplan'] = 1;
        $path = 'index.php?act=editdnsplan&dnsplid=' . $post['dnsplid'];
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function delete_dnsplans($post)
    {
        $path = 'index.php?act=dnsplans';
        $ret = $this->call($path, array(), $post);

        return $ret;
    }

    function add_admin_acl($post)
    {
        $path = 'index.php?act=add_admin_acl';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function admin_acl($post = array())
    {
        if (empty($post)) {
            $path = 'index.php?act=admin_acl';
            $ret = $this->call($path);
        } else {
            $path = 'index.php?act=admin_acl';
            $ret = $this->call($path, array(), $post);
        }
        return $ret;
    }

    function edit_admin_acl($post = array())
    {
        $path = 'index.php?act=edit_admin_acl&aclid=' . $post['aclid'];
        $ret = $this->call($path, array(), $post);
        return $ret;
    }


    function addmg($post)
    {
        $post['addmg'] = 1;
        $path = 'index.php?act=addmg';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function editmg($post)
    {
        $post['editmg'] = 1;
        $path = 'index.php?act=editmg&mgid=' . $post['mgid'];
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function delete_mg($post)
    {
        $path = 'index.php?act=mediagroups&delete=' . $post['delete'];
        $ret = $this->call($path);
        return $ret;
    }

    function add_distro($post)
    {
        $post['add_distro'] = 1;
        $path = 'index.php?act=add_distro';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function edit_distro($post)
    {
        $post['add_distro'] = 1;
        $path = 'index.php?act=add_distro&edit=' . $post['edit'];
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function list_distros($post = 0)
    {
        if (empty($post)) {
            $path = 'index.php?act=list_distros';
            $ret = $this->call($path, array(), $post);
        } else {
            $path = 'index.php?act=list_distros&delete=' . $post['delete'];
            $ret = $this->call($path);
        }
        return $ret;
    }

    function list_euiso($page = 1, $reslen = 50, $post = array())
    {
        $path = 'index.php?act=euiso&page=' . $page . '&reslen=' . $reslen;
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function delete_euiso($post)
    {
        $path = 'index.php?act=euiso';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function list_recipes($page = 1, $reslen = 50, $post = array())
    {
        if (!isset($post['rid'])) {
            $path = 'index.php?act=recipes&page=' . $page . '&reslen=' . $reslen;
            $ret = $this->call($path, array(), $post);
        } else {
            $path = 'index.php?act=recipes&rid=' . $post['rid'] . '&rname=' . $post['rname'] . '&page=' . $page . '&reslen=' . $reslen;
            $ret = $this->call($path, array(), $post);
        }
        return $ret;
    }

    function add_recipes($post)
    {
        $post['addrecipe'] = 1;
        $path = 'index.php?act=addrecipe';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function editrecipe($post)
    {
        $post['editrecipe'] = 1;
        $path = 'index.php?act=editrecipe&rid=' . $post['rid'];
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    // The recipe function deletes activates and deactivates a recipes
    function recipes($post)
    {
        $path = 'index.php?act=recipes';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function tasks($page = 1, $reslen = 50, $post = array())
    {
        if (empty($post)) {
            $path = 'index.php?act=tasks';
            //$ret = $this->call($path);
        } elseif (isset($post['showlogs'])) {
            $path = 'index.php?act=tasks';
        } else {
            $path = 'index.php?act=tasks&actid=' . $post['actid'] . '&vpsid=' . $post['vpsid'] . '&username=' . $post['username'] . '&action=' . $post['action'] . '&status=' . $post['status'] . '&order=' . $post['order'] . '&page=' . $page . '&reslen=' . $reslen;
        }
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function addpdns($post)
    {
        $post['addpdns'] = 1;
        $path = 'index.php?act=addpdns';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function adminindex()
    {
        $path = 'index.php?act=adminindex';
        $res = $this->call($path);
        return $res;
    }

    function apidoings()
    {
    }

    function backup($post)
    {
        $path = 'index.php?act=backup';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function bandwidth($post = array())
    {
        if (empty($post)) {
            $path = 'index.php?act=bandwidth';
            $ret = $this->call($path);
        } else {
            $path = 'index.php?act=bandwidth&show=' . $post['show'];
            $ret = $this->call($path, array(), $post);
        }
        return $ret;
    }

    /**
     * Cleaning the POST variables
     *
     * @author       Pulkit Gupta
     * @param        array $post An array of DATA that should be posted
     * @param        array $cookies An array FOR SENDING COOKIES
     * @return       array The unserialized array on success OR false on failure
     */
    function clean_post(&$post, $edit = 0)
    {
        $post['serid'] = !isset($post['serid']) ? 0 : (int)$post['serid'];
        $post['uid'] = !isset($post['uid']) ? 0 : (int)$post['uid'];
        $post['plid'] = !isset($post['plid']) ? 0 : (int)$post['plid'];
        $post['osid'] = !isset($post['osid']) ? 0 : (int)$post['osid'];
        $post['iso'] = !isset($post['iso']) ? 0 : (int)$post['iso'];
        $post['space'] = !isset($post['space']) ? 10 : $post['space'];
        $post['ram'] = !isset($post['ram']) ? 512 : (int)$post['ram'];
        $post['swapram'] = !isset($post['swapram']) ? 1024 : (int)$post['swapram'];
        $post['bandwidth'] = !isset($post['bandwidth']) ? 0 : (int)$post['bandwidth'];
        $post['network_speed'] = !isset($post['network_speed']) ? 0 : (int)$post['network_speed'];
        $post['cpu'] = !isset($post['cpu']) ? 1000 : (int)$post['cpu'];
        $post['cores'] = !isset($post['cores']) ? 4 : (int)$post['cores'];
        $post['cpu_percent'] = !isset($post['cpu_percent']) ? 100 : (int)$post['cpu_percent'];
        $post['vnc'] = !isset($post['vnc']) ? 1 : (int)$post['vnc'];
        $post['vncpass'] = !isset($post['vncpass']) ? 'test' : $post['vncpass'];
        $post['sec_iso'] = !isset($post['sec_iso']) ? 0 : $post['sec_iso'];
        $post['kvm_cache'] = !isset($post['kvm_cache']) ? 0 : $post['kvm_cache'];
        $post['io_mode'] = !isset($post['io_mode']) ? 0 : $post['io_mode'];
        $post['vnc_keymap'] = !isset($post['vnc_keymap']) ? 'en-us' : $post['vnc_keymap'];
        $post['nic_type'] =  !isset($post['nic_type']) ? 'default' : $post['nic_type'];
        $post['osreinstall_limit'] = !isset($post['osreinstall_limit']) ? 0 : (int)$post['osreinstall_limit'];
        $post['mgs'] = !isset($post['mgs']) ? 0 : $post['mgs'];
        $post['tuntap'] = !isset($post['tuntap']) ? 0 : $post['tuntap'];
        $post['virtio'] = !isset($post['virtio']) ? 0 : $post['virtio'];
        if (isset($post['hvm'])) {
            $post['hvm'] = $post['hvm'];
        }
        $post['noemail'] = !isset($post['noemail']) ? 0 : $post['noemail'];
        $post['boot'] = !isset($post['boot']) ? 'dca' : $post['boot'];
        $post['band_suspend'] = !isset($post['band_suspend']) ? 0 : $post['band_suspend'];
        $post['vif_type'] = !isset($post['vif_type']) ? 'netfront' : $post['vif_type'];
        if ($edit == 0) {
            $post['addvps'] = !isset($post['addvps']) ? 1 : (int)$post['addvps'];
        } else {
            $post['editvps'] = !isset($post['editvps']) ? 1 : $post['editvps'];
            $post['acpi'] = !isset($post['acpi']) ? 1 : $post['acpi'];
            $post['apic'] = !isset($post['apic']) ? 1 : $post['apic'];
            $post['pae'] = !isset($post['pae']) ? 1 : $post['pae'];
            $post['dns'] = !isset($post['dns']) ? array('4.2.2.1', '4.2.2.2') : $post['dns'];
            $post['editvps'] = !isset($post['editvps']) ? 1 : (int)$post['editvps'];
        }

        return $post;
    }

    function cluster()
    {
    }

    function config($post = array())
    {
        $path = 'index.php?act=config';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function config_slave($post = array())
    {
        $path = 'index.php?act=config_slave';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    /**
     * Get CPU usage details
     *
     * @author       Pulkit Gupta
     * @param
     * @return       array The unserialised array is returned on success or
     *               empty array is returned on failure
     */
    function cpu($serverid = 0)
    {
        $path = 'index.php?act=manageserver&changeserid=' . $serverid;
        $ret = $this->call($path);
        return $ret['usage']['cpu'];
    }

    function serverloads($post = array())
    {
        $path = 'index.php?act=serverloads';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function createssl($post)
    {
        $path = 'index.php?act=createssl';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function letsencrypt($post)
    {
        $path = 'index.php?act=letsencrypt';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function createtemplate($post)
    {
        $path = 'index.php?act=createtemplate';
        $post['createtemp'] = 1;
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function server_stats($post)
    {
        $path = 'index.php?act=server_stats' . (!empty($post['serid']) ? '&changeserid=' . (int)$post['serid'] : '');
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function vps_stats($post)
    {
        $path = 'index.php?act=vps_stats' . (!empty($post['serid']) ? '&changeserid=' . (int)$post['serid'] : '');
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function databackup($post)
    {
        $path = 'index.php?act=databackup';
        $ret = $this->call($path, array(), $post);

        return $ret;
    }

    function listdbbackfiles()
    {
        $path = 'index.php?act=databackup';
        $ret = $this->call($path);
        return $ret;
    }

    function createvpsbackup($post)
    {
        $path = 'index.php?act=editbackup_plan';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function vps_backup_list($post)
    {

        $path = 'index.php?act=vpsrestore&op=get_vps&vpsid=' . $post['vpsid'];
        if (!empty($post['serid'])) {
            $path .= '&changeserid=' . $post['serid'];
        }
        $res = $this->call($path, array(), $post);
        return $res;
    }

    function vpsrestore($post)
    {
        $post['restore'] = 1;
        $path = 'index.php?act=vpsrestore';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function deletevpsbackup($post)
    {
        $path = 'index.php?act=vpsrestore';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function pdns($page, $reslen, $post = array())
    {
        if (empty($post)) {
            $path = 'index.php?act=pdns&page=' . $page . '&reslen=' . $reslen;
            $ret = $this->call($path, array(), $post);
        } elseif (isset($post['test'])) {
            $path = 'index.php?act=pdns&test=' . $post['test'];
            $ret = $this->call($path);
        } elseif (isset($post['delete'])) {
            $path = 'index.php?act=pdns';
            $ret = $this->call($path, array(), $post);
        } else {
            $path = 'index.php?act=pdns&pdns_name=' . $post['pdns_name'] . '&pdns_ipaddress=' . $post['pdns_ipaddress'] . '&page=' . $page . '&reslen=' . $reslen;
            $ret = $this->call($path, array(), $post);
        }
        return $ret;
    }

    function rdns($post = array())
    {
        $path = 'index.php?act=rdns';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function domains($page = 1, $reslen = 50, $post = array())
    {
        if (!isset($post['del'])) {
            $path = 'index.php?act=domains&pdnsid=' . $post['pdnsid'] . '&page=' . $page . '&reslen=' . $reslen;
            $ret = $this->call($path);
        } else {
            $path = 'index.php?act=domains&pdnsid=' . $post['pdnsid'] . '&page=' . $page . '&reslen=' . $reslen;
            $ret = $this->call($path, array(), $post);
        }
        return $ret;
    }

    function delete_dnsrecords($post = array())
    {
        $path = 'index.php?act=dnsrecords&pdnsid=' . $post['pdnsid'];
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function dnsrecords($page = 1, $reslen = 50, $post = array())
    {
        if (!isset($post['del'])) {
            $path = 'index.php?act=dnsrecords&pdnsid=' . $post['pdnsid'] . '&domain_id=' . $post['domain_id'] . '&page=' . $page . '&reslen=' . $reslen;
            $ret = $this->call($path);
        } else {
            $path = 'index.php?act=dnsrecords&pdnsid=' . $post['pdnsid'] . '&domain_id=' . $post['domain_id'];
            $ret = $this->call($path, array(), $post);
        }
        return $ret;
    }

    function search_dnsrecords($page = 1, $reslen = 50, $post = array())
    {
        $path = 'index.php?act=dnsrecords&pdnsid=' . $post['pdnsid'] . '&domain_id=' . $post['domain_id'] . '&dns_name=' . $post['dns_name'] . '&dns_domain=' . $post['dns_domain'] . '&record_type=' . $post['record_type'] . '&page=' . $page . '&reslen=' . $reslen;
        $ret = $this->call($path, array(), $post);

        return $ret;
    }

    function add_dnsrecord($post = array())
    {
        $post['add_dnsrecord'] = 1;
        $path = 'index.php?act=add_dnsrecord&pdnsid=' . $post['pdnsid'];
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function edit_dnsrecord($post = array())
    {
        $post['add_dnsrecord'] = 1;
        $path = 'index.php?act=add_dnsrecord&pdnsid=' . $post['pdnsid'] . '&edit=' . $post['edit'];
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function editpdns($post = array())
    {
        $post['editpdns'] = 1;
        $path = 'index.php?act=editpdns&pdnsid=' . $post['pdnsid'];
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function defaultvsconf($post)
    {
        $path = 'index.php?act=defaultvsconf';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    /**
     * Delete a VPS
     *
     * @author       Pulkit Gupta
     * @param        array $post An array of DATA that should be posted
     * @return       boolean 1 on success OR 0 on failure
     */
    function delete_vs($vid)
    {
        $path = 'index.php?act=vs&delete=' . (int)$vid;
        $res = $this->call($path);
        return $res;
    }

    /**
     * Get Disk usage details
     *
     * @author       Pulkit Gupta
     * @param
     * @return       array The unserialised array is returned on success or
     *               empty array is returned on failure
     */
    function disk($serverid = 0)
    {
        $path = 'index.php?act=manageserver&changeserid=' . $serverid;
        $ret = $this->call($path);
        return $ret['usage']['disk'];
    }

    function webuzo($post = array())
    {
        $post['webuzo'] = 1;
        $path = 'index.php?act=webuzo';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function webuzo_scripts()
    {
        $path = 'index.php?act=webuzo';
        $ret = $this->call($path);
        return $ret;
    }

    function editemailtemp($post)
    {
        $path = 'index.php?act=editemailtemp&temp=' . $post['temp'];
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function resetemailtemp($post)
    {
        $path = 'index.php?act=editemailtemp&temp=' . $post['temp'] . '&reset=' . $post['reset'];
        $ret = $this->call($path);
        return $ret;
    }

    function billingsettings($post = array())
    {
        $post['editsettings'] = 1;
        $path = 'index.php?act=billing';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function resourcepricing($post = array())
    {
        $post['editsettings'] = 1;
        $path = 'index.php?act=resource_pricing';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function addinvoice($post = array())
    {
        $post['addinvoice'] = 1;
        $path = 'index.php?act=addinvoice';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function editinvoice($post = array())
    {
        $post['editinvoice'] = 1;
        $path = 'index.php?act=editinvoice&invoid=' . $post['invoid'];
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function listinvoice($page = 1, $reslen = 50, $post = array())
    {
        $path = 'index.php?act=invoices&page=' . $page . '&reslen=' . $reslen;
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function deleteinvoice($post = array())
    {
        $path = 'index.php?act=invoices';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function addtransaction($post = array())
    {
        $post['addtransaction'] = 1;
        $path = 'index.php?act=addtransaction';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function edittransaction($post = array())
    {
        $post['edittransaction'] = 1;
        $path = 'index.php?act=edittransaction&trid=' . $post['trid'];
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function listtransaction($page = 1, $reslen = 50, $post = array())
    {
        $path = 'index.php?act=transactions&page=' . $page . '&reslen=' . $reslen;
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function deletetransactions($post = array())
    {
        $path = 'index.php?act=transactions';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function editippool($post)
    {
        $post['editippool'] = 1;
        $path = 'index.php?act=editippool&ippid=' . $post['ippid'];
        $res = $this->call($path, array(), $post);
        return $res;
    }

    function deleteippool($ippid)
    {
        $path = 'index.php?act=ippool';
        $ret = $this->call($path, array(), $ippid);
        return $ret;
    }

    function editips($post)
    {
        $path = 'index.php?act=editips';
        $res = $this->call($path, array(), $post);
        return $res;
    }

    function delete_ips($post)
    {
        $path = 'index.php?act=ips';
        $res = $this->call($path, array(), $post);
        return $res;
    }

    function editplan($post)
    {
        $post['editplan'] = 1;
        $path = 'index.php?act=editplan&plid=' . $post['plid'];
        $res = $this->call($path, array(), $post);
        return $res;
    }

    function editserver($post)
    {
        $post['editserver'] = 1;
        $path = 'index.php?act=editserver&serid=' . $post['serid'];
        $res = $this->call($path, array(), $post);
        return $res;
    }

    function edittemplate($post)
    {
        $path = 'index.php?act=edittemplate';
        $res = $this->call($path, array(), $post);
        return $res;
    }

    function edituser($post)
    {
        $path = 'index.php?act=edituser&uid=' . $post['uid'];
        $res = $this->call($path, array(), $post);
        return $res;
    }

    /**
     * Create a VPS
     *
     * @author       Pulkit Gupta
     * @param        array $post An array of DATA that should be posted
     * @return       array The unserialized array on success OR false on failure
     */
    function editvs($post, $cookies = array())
    {
        $path = 'index.php?act=editvs&vpsid=' . $post['vpsid'];
        //$post = $this->clean_post($post, 1);
        $ret = $this->call($path, '', $post, $cookies);
        return array(
            'title' => $ret['title'],
            'done' => $ret['done'],
            'error' => @empty($ret['error']) ? array() : $ret['error'],
            'vs_info' => $ret['vps']
        );
    }

    function managevps($post)
    {
        $post['theme_edit'] = 1;
        $post['editvps'] = 1;
        $path = 'index.php?act=managevps&vpsid=' . $post['vpsid'];
        $ret = $this->call($path, array(), $post);
        return array(
            'title' => $ret['title'],
            'done' => $ret['done'],
            'error' => @empty($ret['error']) ? array() : $ret['error'],
            'vs_info' => $ret['vps']
        );
    }

    function emailconfig($post)
    {
        $path = 'index.php?act=emailconfig';
        $res = $this->call($path, array(), $post);
        return $res;
    }

    function emailtemp($post = array())
    {
        $path = 'index.php?act=emailtemp';
        $res = $this->call($path, array(), $post);
        return $res;
    }

    function filemanager($post)
    {
        $path = 'index.php?act=filemanager';
        $res = $this->call($path, '', $post);
        return $res;
    }

    function firewall($post)
    {
        $path = 'index.php?act=firewall';
        $res = $this->call($path, array(), $post);
        return $res;
    }

    function giveos()
    {
    }

    function health()
    {
    }

    function hostname($post)
    {
        $path = 'index.php?act=hostname';
        $res = $this->call($path, '', $post);
        return $res;
    }

    function import($page, $reslen, $post)
    {
        $path = 'index.php?act=import';
        $res = $this->call($path, array(), $post);
        return $res;
    }

    function ippool($page = 1, $reslen = 50, $post = array())
    {
        if (empty($post)) {
            $path = 'index.php?act=ippool&page=' . $page . '&reslen=' . $reslen;
            $res = $this->call($path);
        } else {
            $path = 'index.php?act=ippool&poolname=' . $post['poolname'] . '&poolgateway=' . $post['poolgateway'] . '&netmask=' . $post['netmask'] . '&nameserver=' . $post['nameserver'] . '&page=' . $page . '&reslen=' . $reslen;
            $res = $this->call($path);
        }
        return $res;
    }

    /**
     * Get list of IPs
     *
     * @author       Pulkit Gupta
     * @param
     * @return       array The unserialised array on success.
     */
    function ips($page = 1, $reslen = 50, $post = null)
    {
        if (empty($post)) {
            $path = 'index.php?act=ips&page=' . $page . '&reslen=' . $reslen;
            $ret = $this->call($path);
        } else {
            $path = 'index.php?act=ips&ipsearch=' . $post['ipsearch'] . '&ippoolsearch=' . $post['ippoolsearch'] . '&macsearch=' . $post['macsearch'] . '&vps_search=' . $post['vps_search'] . '&servers_search=' . $post['servers_search'] . '&lockedsearch=' . $post['lockedsearch'] . '&page=' . $page . '&reslen=' . $reslen;
            $ret = $this->call($path);
        }
        return $ret;
    }

    function iso()
    {
        $path = 'index.php?act=iso';
        $ret = $this->call($path);
        return $ret;
    }

    function kernelconf($post = 0)
    {
        $path = 'index.php?act=kernelconf';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function license()
    {
        $path = 'index.php?act=license';
        $ret = $this->call($path);
        return $ret;
    }

    /**
     * List VPS
     *
     * @author       Pulkit Gupta
     * @param        int page number, if not specified then only 50 records are returned.
     * @return       array The unserialized array on success OR false on failure
     *
     */
    function listvs($page = 1, $reslen = 50, $search = array())
    {

        if (empty($search)) {
            $path = 'index.php?act=vs&page=' . $page . '&reslen=' . $reslen;
        } else {
            $path = 'index.php?act=vs&vpsid=' . $search['vpsid'] . '&vpsname=' . $search['vpsname'] . '&vpsip=' . $search['vpsip'] . '&vpshostname=' . $search['vpshostname'] . '&vsstatus=' . $search['vsstatus'] . '&vstype=' . $search['vstype'] . '&user=' . $search['user'] . '&serid=' . $search['serid'] . '&search=' . $search['search'];
        }

        $result = $this->call($path);
        $ret = $result['vs'];
        return $ret;
    }

    function login()
    {
    }

    function loginlogs($page = 1, $reslen = 50, $post = array())
    {
        if (empty($post)) {
            $path = 'index.php?act=loginlogs&page=' . $page . '&reslen=' . $reslen;
            $ret = $this->call($path);
        } else {
            $path = 'index.php?act=loginlogs&username=' . $post['username'] . '&ip=' . $post['ip'] . '&page=' . $page . '&reslen=' . $reslen;
            $ret = $this->call($path, array(), $post);
        }
        return $ret;
    }

    function logs($page = 1, $reslen = 50, $post = array())
    {
        if (empty($post)) {
            $path = 'index.php?act=logs&page=' . $page . '&reslen=' . $reslen;
            $ret = $this->call($path);
        } else {
            $path = 'index.php?act=logs&id=' . $post['id'] . '&email=' . $post['email'] . '&page=' . $page . '&reslen=' . $reslen;
            $ret = $this->call($path, array(), $post);
        }
        return $ret;
    }

    function maintenance($post)
    {
        $path = 'index.php?act=maintenance';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function makeslave()
    {
    }

    function os($post = array())
    {
        if (empty($post)) {
            $path = 'index.php?act=os';
        } else {
            $path = 'index.php?act=os&getos=' . $post['osids'][0];
        }
        $result = $this->call($path, array(), $post);
        return $result;
    }

    function ostemplates($page = 1, $reslen = 50)
    {
        $path = 'index.php?act=ostemplates&page=' . $page . '&reslen=' . $reslen;
        $result = $this->call($path);
        // $ret['title'] = $result['title'];
        // $ret['ostemplates'] = $result['ostemplates'];
        return $result;
    }

    function delostemplates($post = array())
    {
        $path = 'index.php?act=ostemplates&delete=' . $post['delete'];
        $result = $this->call($path);
        $ret['title'] = $result['title'];
        $ret['done'] = $result['done'];
        $ret['ostemplates'] = $result['ostemplates'];
        return $ret;
    }

    function performance()
    {
        $path = 'index.php?act=performance';
        $result = $this->call($path);
        return $result;
    }

    function phpmyadmin()
    {
    }

    function plans($page = 1, $reslen = 50, $search = array())
    {
        if (empty($search)) {
            $path = 'index.php?act=plans&page=' . $page . '&reslen=' . $reslen;
            $ret = $this->call($path);
        } else {
            $path = 'index.php?act=plans&planname=' . $search['planname'] . '&ptype=' . $search['ptype'] . '&page=' . $page . '&reslen=' . $reslen;
            $ret = $this->call($path);
        }
        return $ret;
    }

    function sort_plans($page = 1, $reslen = 50, $sort = array())
    {
        $path = 'index.php?act=plans&sortcolumn=' . $sort['sortcolumn'] . '&sortby=' . $sort['sortby'] . '&page=' . $page . '&reslen=' . $reslen;
        $ret = $this->call($path);
        return $ret;
    }

    function delete_plans($post)
    {
        $path = 'index.php?act=plans&delete=' . $post['delete'];
        $ret = $this->call($path);
        return $ret;
    }

    function list_user_plans($post = array(), $page = 1, $reslen = 50)
    {
        $path = 'index.php?act=user_plans&page=' . $page . '&reslen=' . $reslen;
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function add_user_plans($post = array())
    {
        $post['adduser_plans'] = 1;
        $path = 'index.php?act=adduser_plans';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function edit_user_plans($post)
    {
        $post['edituser_plans'] = 1;
        $path = 'index.php?act=edituser_plans&uplid=' . $post['uplid'];
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    function delete_user_plans($post = array())
    {
        $path = 'index.php?act=user_plans';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    /**
     * POWER OFF a Virtual Server
     *
     * @author       Pulkit Gupta
     * @param        int $vid The VMs ID
     * @return       bool TRUE on success or FALSE on failure
     */
    function poweroff($vid)
    {
        // Make the Request
        $res = $this->call('index.php?act=vs&action=poweroff&serid=0&vpsid=' . (int)$vid);
        return $res;
    }

    function processes($post = array())
    {

        $path = 'index.php?act=processes';
        $ret = $this->call($path, array(), $post);
        return $ret;
    }

    /**
     * Get RAM details
     *
     * @author       Pulkit Gupta
     * @param
     * @return       array The unserialised array is returned on success or
     *               empty array is returned on failure
     */
    function ram($serverid = 0)
    {
        $path = 'index.php?act=manageserver&changeserid=' . $serverid;
        $ret = $this->call($path);
        return $ret['usage']['ram'];
    }

    /**
     * Rebuild a VPS
     *
     * @author       Pulkit Gupta
     * @param        array $post An array of DATA that should be posted
     * @return       array The unserialized array on success OR false on failure
     */
    function rebuild($post)
    {
        $post['reos'] = 1;
        $path = 'index.php?act=rebuild' . (!empty($post['serid']) ? '&changeserid=' . (int)$post['serid'] : '');
        return $this->call($path, '', $post);
    }

    /**
     * RESTART a Virtual Server
     *
     * @author       Pulkit Gupta
     * @param        int $vid The VMs ID
     * @return       bool TRUE on success or FALSE on failure
     */
    function restart($vid)
    {
        // Make the Request
        $res = $this->call('index.php?act=vs&action=restart&serid=0&vpsid=' . (int)$vid);
        return $res;
    }

    function restartservices($post)
    {
        $post['do'] = 1;
        $path = 'index.php?act=restartservices&service=' . $post['service'] . '&do=' . $post['do'];
        $res = $this->call($path, array(), $post);
        return $res;
    }

    /**
     * Current server information
     *
     * @author       Pulkit Gupta
     * @param
     * @return       array The unserialized array on success OR false on failure
     */
    function serverinfo($serid = 0)
    {

        $path = 'index.php?act=serverinfo';
        if (!empty($serid)) {
            $path .= '&changeserid=' . $serid;
        }
        $result = $this->call($path);

        $ret = array();
        $ret['title'] = $result['title'];
        $ret['info']['masterkey'] = $result['info']['masterkey'];
        $ret['info']['path'] = $result['info']['path'];
        $ret['info']['key'] = $result['info']['key'];
        $ret['info']['pass'] = $result['info']['pass'];
        $ret['info']['kernel'] = $result['info']['kernel'];
        $ret['info']['num_vs'] = $result['info']['num_vs'];
        $ret['info']['version'] = $result['info']['version'];
        $ret['info']['patch'] = $result['info']['patch'];

        return $ret;
    }

    /**
     * List Servers
     *
     * @author       Pulkit Gupta
     * @param
     * @return       array The unserialized array on success OR false on failure
     */
    function servers($search = array(), $del_serid = 0)
    {
        if ($del_serid == 0) {
            $path = 'index.php?act=servers';
            if (!empty($search)) {
                $path .= '&servername=' . $search['servername'] . '&serverip=' . $search['serverip'] . '&ptype=' . $search['ptype'] . '&search=Search';
            }
        } else {
            $path = 'index.php?act=servers&delete=' . $del_serid;
        }
        return $this->call($path);
    }

    function server_force_delete($del_serid = 0)
    {
        if ($del_serid == 0) {
            $path = 'index.php?act=servers';
        } else {
            $path = 'index.php?act=servers&force=' . $del_serid;
        }
        return $this->call($path);
    }

    function listservers()
    {
        $path = 'index.php?act=servers';
        return $this->call($path);
    }

    function services($post = array())
    {
        $path = 'index.php?act=services';
        $res = $this->call($path, array(), $post);
        return $res;
    }

    function ssh()
    {
        /*  $path = 'index.php?act=ssh';
        $res = $this->call($path);
        return $res;*/
    }

    function ssl($post = 0)
    {
        $path = 'index.php?act=ssl';
        $res = $this->call($path, array(), $post);
        return $res;
    }

    function sslcert()
    {
        /*  $path = 'index.php?act=sslcert';
        $res = $this->call($path);
        return $res;*/
    }

    /**
     * START a Virtual Server
     *
     * @author       Pulkit Gupta
     * @param        int $vid The VMs ID
     * @return       bool TRUE on success or FALSE on failure
     */
    function start($vid)
    {

        $res = $this->call('index.php?act=vs&action=start&serid=0&vpsid=' . (int)$vid);
        return $res;
    }

    /**
     * STOP a Virtual Server
     *
     * @author       Pulkit Gupta
     * @param        int $vid The VMs ID
     * @return       bool TRUE on success or FALSE on failure
     */
    function stop($vid)
    {
        /*$ip = 'cloud.northnetworking.com';
        $pass = '1435316b2692211e912a';
        $key = 'cac032dfd75342875c71';*/
        //$key =  'vlxvjsuklbuaxycmwrawqut5426vdbpl';
        //$pass = 'qyghlvnekfxbvthbmwptixlze1xqikbw';
        /*$admin = new Virtualizor_Admin_API($ip, $key, $pass);

        $vid = 1175;

        $output = $admin->stop($vid);
        print_r(json_encode($output));exit;*/
        // Make the Request
        $res = $this->call('index.php?act=vs&action=stop&serid=0&vpsid=' . (int)$vid);
        return $res;
    }

    /**
     * Gives status of a Virtual Server
     *
     * @author       Pulkit Gupta
     * @param        Array $vids array of IDs of VMs
     * @return       Array Contains the status info of the VMs
     */
    function status($vids)
    {
        // Make the Request
        $res = $this->call('index.php?act=vs&vs_status=' . implode(',', $vids));
        return @$res['status'];
    }

    /**
     * Suspends a VM of a Virtual Server
     *
     * @author       Pulkit Gupta
     * @param        int $vid The VMs ID
     * @return       int 1 if the VM is ON, 0 if its OFF
     */
    function suspend($vid)
    {
        $path = 'index.php?act=vs&suspend=' . (int)$vid;
        $res = $this->call($path);
        return $res;
    }

    /**
     * Unsuspends a VM of a Virtual Server
     *
     * @author       Pulkit Gupta
     * @param        int $vid The VMs ID
     * @return       int 1 if the VM is ON, 0 if its OFF
     */
    function unsuspend($vid)
    {
        $path = 'index.php?act=vs&unsuspend=' . (int)$vid;
        $res = $this->call($path);
        return $res;
    }

    function suspend_net($vid)
    {
        $path = 'index.php?act=vs&suspend_net=' . $vid;
        $res = $this->call($path);
        return $res;
    }

    function unsuspend_net($vid)
    {
        $path = 'index.php?act=vs&unsuspend_net=' . $vid;
        $res = $this->call($path);
        return $res;
    }

    function tools()
    {
    }

    function ubc($post)
    {
        $path = 'index.php?act=ubc';
        $res = $this->call($path, array(), $post);
        return $res;
    }

    function updates($post)
    {
        $path = 'index.php?act=updates';
        $res = $this->call($path, array(), $post);
        return $res;
    }

    function userlogs($page = 1, $reslen = 50, $post = array())
    {
        if (empty($post)) {
            $path = 'index.php?act=userlogs&page=' . $page . '&reslen=' . $reslen;
            $res = $this->call($path);
        } else {
            $path = 'index.php?act=userlogs&vpsid=' . $post['vpsid'] . '&email=' . $post['email'] . '&page=' . $page . '&reslen=' . $reslen;
            $res = $this->call($path, array(), $post);
        }
        return $res;
    }

    function iplogs($page = 1, $reslen = 50, $post = array())
    {
        if (empty($post)) {
            $path = 'index.php?act=iplogs&page=' . $page . '&reslen=' . $reslen;
            $res = $this->call($path);
        } else {
            $path = 'index.php?act=iplogs&vpsid=' . $post['vpsid'] . '&ip=' . $post['ip'] . '&page=' . $page . '&reslen=' . $reslen;
            $res = $this->call($path, array(), $post);
        }
        return $res;
    }

    function deleteiplogs($post)
    {
        if (!empty($post)) {
            $path = 'index.php?act=iplogs';
            $res = $this->call($path, array(), $post);
        }
        return $res;
    }

    function users($page = 1, $reslen = 50, $post = array())
    {
        if (empty($post)) {
            $path = 'index.php?act=users&page=' . $page . '&reslen=' . $reslen;
            $res = $this->call($path, array(), $post);
        } else {
            $path = 'index.php?act=users&uid=' . $post['uid'] . '&email=' . $post['email'] . '&type=' . $post['type'] . '&page=' . $page . '&reslen=' . $reslen;
            $res = $this->call($path, array(), $post);
        }
        return $res;
    }

    function delete_users($del_userid)
    {
        $path = 'index.php?act=users';
        $res = $this->call($path, array(), $del_userid);
        return $res;
    }

    function vnc($post)
    {
        $path = 'index.php?act=vnc&novnc=' . $post['novnc'];
        $res = $this->call($path, array(), $post);
        return $res;
    }

    function vs($page = 1, $reslen = 50)
    {
        $path = 'index.php?act=vs&page=' . $page . '&reslen=' . $reslen;
        $res = $this->call($path);
        return $res;
    }

    function vsbandwidth()
    {
        $path = 'index.php?act=vsbandwidth';
        $res = $this->call($path);
        return $res;
    }

    function vscpu()
    {
        $path = 'index.php?act=vscpu';
        $res = $this->call($path);
        return $res;
    }

    function vsram()
    {
        $path = 'index.php?act=vsram';
        $res = $this->call($path);
        return $res;
    }

    function clonevps($post)
    {
        $path = 'index.php?act=clone';
        $post['migrate'] = 1;
        $post['migrate_but'] = 1;
        $res = $this->call($path, array(), $post);
        return $res;
    }

    function migrate($post)
    {
        $path = 'index.php?act=migrate';
        $res = $this->call($path, array(), $post);
        return $res;
    }

    function haproxy($post)
    {
        $path = 'index.php?act=haproxy';
        $res = $this->call($path, array(), $post);
        return $res;
    }

    function listhaproxy($search = array(), $page = 1, $reslen = 50)
    {

        if (empty($search)) {
            $path = 'index.php?act=haproxy&page=' . $page . '&reslen=' . $reslen;
        } else {
            $path = 'index.php?act=haproxy&s_id=' . $search['s_id'] . '&s_serid=' . (empty($search['s_serid']) ? '-1' : $search['s_serid']) . '&s_vpsid=' . $search['s_vpsid'] . '&s_protocol=' . (empty($search['s_protocol']) ? '-1' : $search['s_protocol']) . '&s_src_hostname=' . $search['s_src_hostname'] . '&s_src_port=' . $search['s_src_port'] . '&s_dest_ip=' . $search['s_dest_ip'] . '&s_dest_port=' . $search['s_dest_port'] . '&haproxysearch=' . $search['haproxysearch'];
        }

        $result = $this->call($path);
        $ret = $result['haproxydata'];
        return $ret;
    }
} // Class Ends
