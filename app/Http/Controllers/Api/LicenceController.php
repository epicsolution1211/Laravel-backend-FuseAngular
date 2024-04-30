<?php

namespace App\Http\Controllers\Api;

//require __DIR__ . '/../../../../xsolla/xsolla-autoloader.php';

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Licence;
use App\User;
use App\Release;
use App\Order;
use App\LicenceSubscription;
use App\LicenceXsollaProduct;
use App\LicenceOrder;
use App\Maintenance;
use App\SubscriptionCancelReason;
use App\CancelReason;
use App\GenerateLicenceKeyHistory;
use App\OrderProduct;
use App\PromotionUsers;
use App\PromotionApplied;
use App\ProductConfigurations;
use App\Products;
use DB;
use Auth;
use Xsolla\SDK\API\XsollaClient;
use Xsolla\SDK\API\PaymentUI\TokenRequest;
use Xsolla\SDK\Webhook\WebhookServer;
use Xsolla\SDK\Webhook\Message\Message;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;


class LicenceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $data = $request->all();
        $id = $data['userId'];
        $user = User::find($id);
        // $role = $user->userRole;
        // dd($role);
        $result = LicenceSubscription::where('user_id', $id)->get();

        $subscriptions = [];

        $resultSubscription = LicenceSubscription::where('user_id', $id)->where('status', 1)->get();

        $date_next_charge = [];

        foreach ($resultSubscription as $subscription) {
            if ($subscription->status == 1) {
                $prod = LicenceXsollaProduct::where('id', $subscription->xsolla_product_id)->first();
                $date_next_charge[$prod->name] = $subscription->date_next_charge;
            }
        }

        $releases = Release::where('show', 1)->orderBy('release_date', 'asc')->get();
        foreach ($result as $subscription) {
            if ($subscription->status == 1) {
                Log::alert($subscription->subscription_id);
                $order = LicenceOrder::where('subscription_id', $subscription->subscription_id)->first();
                //$subscriptions[$subscription->id] = $order->licence_key;
                $subscriptions[] = $order->licence_key;
            }
        }

        $licences = $this->getLicences($request);
        $maintenances = $this->getMaintenances($request);

        /*if($user->role_id == 1){
            $licences = Licence::paginate($request->get('paginate'));
        }else{
            $licences = Licence::where('user_id',$id)->paginate($request->get('paginate'));
          }*/
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['licences' => $licences, 'date_next_charge' => $date_next_charge, 'maintenances' => $maintenances, 'subscriptions' => $subscriptions, 'user' => $user]];
        return response($response, 200);
        /*$all_licences = $licencesRecords->map(function($licencesRecords){
            return collect([
                'licences' => $licencesRecords,
                'subscriptions'=> $subscriptions,
                'date_next_charge'=> $date_next_charge,
                'maintenances' => $this->getMaintenances($request),
                'today' => $date->format('Y-m-d H:i:s')
            ]);
          });*/
        /*$all_licences = $licences->map(function($licence){
            return collect([
                'id' => $licence->id,
                'licence_key' => $licence->licence_key,
                'maintenance_expire' => $licence->maintenance_expire,
                'server_keepalive' => $licence->server_keepalive,
                'buy_date' => $licence->buy_date,
                'activationDate' => $licence->activationDate,
                'server_address' => $licence->server_address
            ]);
          });*/
        if ($licences) {
            //$response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','data'=> ['licences' => $licences,'current_page' => $licences->currentPage(),'per_page' => $licences->perPage(),'total' => $licences->total(),'user' => $user]];
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['licences' => $licences, 'user' => $user]];
            return response($response, 200);
        } else {
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Licences not found.', 'data' => ['licences' => $licences, 'user' => $user]];
            return response($response, 200);
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $maintenanceArr = array();
        $licence = Licence::find($id);
        $order = LicenceOrder::where('licence_id', $licence->id)->where('licences_order_type', "like", "Subscription")->first();
        $licence->next_pay = @$order->date_next_charge;
        $auth = \Auth::user();
        $search = date('Y-m-d H:i:s');
        $promotion = PromotionUsers::leftJoin('promotions', function ($join) {
            $join->on('promotion_users.user_id', '=', 'promotions.user_id');
        })
            ->where('promotion_users.user_id', $auth->id)
            ->where('promotions.promotion_status', 1)
            ->whereDate('promotions.start_date', '<=', $search)
            ->whereDate('promotions.end_date', '>=', $search)
            ->get()->count();
        $maintenance = self::getMaintenancesForLicence($id);
        foreach ($maintenance as $key => $value) {
            if ($value['maintenance_type'] == 'AtXMain180') {
                $maintenanceArr[$key]['id'] = $value['id'];
                $maintenanceArr[$key]['maintenance_type'] = "Atavism On-Premises " . $value['number_days'] . " " . $value['licences_maintenance_key'];
            }
            if ($value['maintenance_type'] == 'AtXMain365') {
                $maintenanceArr[$key]['id'] = $value['id'];
                $maintenanceArr[$key]['maintenance_type'] = "Atavism On-Premises " . $value['number_days'] . " " . $value['licences_maintenance_key'];
            }
            if ($value['maintenance_type'] == 'AtStdMain30') {
                $maintenanceArr[$key]['id'] = $value['id'];
                $maintenanceArr[$key]['maintenance_type'] = "Atavism On-Premises Standard " . $value['number_days'] . " " . $value['licences_maintenance_key'];
            }
            if ($value['maintenance_type'] == 'AtStdMain90') {
                $maintenanceArr[$key]['id'] = $value['id'];
                $maintenanceArr[$key]['maintenance_type'] = "Atavism On-Premises Standard " . $value['number_days'] . " " . $value['licences_maintenance_key'];
            }
            if ($value['maintenance_type'] == 'AtStdMain180') {
                $maintenanceArr[$key]['id'] = $value['id'];
                $maintenanceArr[$key]['maintenance_type'] = "Atavism On-Premises Standard " . $value['number_days'] . " " . $value['licences_maintenance_key'];
            }
            if ($value['maintenance_type'] == 'AtStdMain365') {
                $maintenanceArr[$key]['id'] = $value['id'];
                $maintenanceArr[$key]['maintenance_type'] = "Atavism On-Premises Standard " . $value['number_days'] . " " . $value['licences_maintenance_key'];
            }
            if ($value['maintenance_type'] == 'AtAdvMain30') {
                $maintenanceArr[$key]['id'] = $value['id'];
                $maintenanceArr[$key]['maintenance_type'] = "Atavism On-Premises Advanced " . $value['number_days'] . " " . $value['licences_maintenance_key'];
            }
            if ($value['maintenance_type'] == 'AtAdvMain90') {
                $maintenanceArr[$key]['id'] = $value['id'];
                $maintenanceArr[$key]['maintenance_type'] = "Atavism On-Premises Advanced " . $value['number_days'] . "" . $value['licences_maintenance_key'];
            }
            if ($value['maintenance_type'] == 'AtAdvMain180') {
                $maintenanceArr[$key]['id'] = $value['id'];
                $maintenanceArr[$key]['maintenance_type'] = "Atavism On-Premises Advanced " . $value['number_days'] . " " . $value['licences_maintenance_key'];
            }
            if ($value['maintenance_type'] == 'AtAdvMain365') {
                $maintenanceArr[$key]['id'] = $value['id'];
                $maintenanceArr[$key]['maintenance_type'] = "Atavism On-Premises Advanced " . $value['number_days'] . " " . $value['licences_maintenance_key'];
            }
            if ($value['maintenance_type'] == 'AtProMain30') {
                $maintenanceArr[$key]['id'] = $value['id'];
                $maintenanceArr[$key]['maintenance_type'] = "Atavism On-Premises Professional " . $value['number_days'] . " " . $value['licences_maintenance_key'];
            }
            if ($value['maintenance_type'] == 'AtProMain90') {
                $maintenanceArr[$key]['id'] = $value['id'];
                $maintenanceArr[$key]['maintenance_type'] = "Atavism On-Premises Professional " . $value['number_days'] . " " . $value['licences_maintenance_key'];
            }
            if ($value['maintenance_type'] == 'AtProMain180') {
                $maintenanceArr[$key]['id'] = $value['id'];
                $maintenanceArr[$key]['maintenance_type'] = "Atavism On-Premises Professional " . $value['number_days'] . " " . $value['licences_maintenance_key'];
            }
            if ($value['maintenance_type'] == 'AtProMain365') {
                $maintenanceArr[$key]['id'] = $value['id'];
                $maintenanceArr[$key]['maintenance_type'] = "Atavism On-Premises Professional " . $value['number_days'] . " " . $value['licences_maintenance_key'];
            }
            if ($value['maintenance_type'] == 'AtUltMain30') {
                $maintenanceArr[$key]['id'] = $value['id'];
                $maintenanceArr[$key]['maintenance_type'] = "Atavism On-Premises Ultra " . $value['number_days'] . " " . $value['licences_maintenance_key'];
            }
            if ($value['maintenance_type'] == 'AtProMain90') {
                $maintenanceArr[$key]['id'] = $value['id'];
                $maintenanceArr[$key]['maintenance_type'] = "Atavism On-Premises Ultra " . $value['number_days'] . " " . $value['licences_maintenance_key'];
            }
            if ($value['maintenance_type'] == 'AtProMain180') {
                $maintenanceArr['id'] = $value['id'];
                $maintenanceArr[$key]['maintenance_type'] = "Atavism On-Premises Ultra " . $value['number_days'] . " " . $value['licences_maintenance_key'];
            }
            if ($value['maintenance_type'] == 'AtProMain365') {
                $maintenanceArr[$key]['id'] = $value['id'];
                $maintenanceArr[$key]['maintenance_type'] = "Atavism On-Premises Ultra " . $value['number_days'] . " " . $value['licences_maintenance_key'];
            }
        }
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['licence' => $licence, 'maintenance' => $maintenanceArr, 'promotion' => true, 'promotion' => $promotion, 'search' => $search, 'authId' => $auth->id]];
        return response($response, 200);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function getChangeSubscriptionData($licenceId)
    {
        $auth = Auth::user();
        $licence = Licence::where('id', $licenceId)->first();

        $result = LicenceSubscription::where('user_id', $auth->id)->where('status', 1)->get();
        $subscription_id = 0;
        $date_next_charge = "";

        foreach ($result as $subscription) {
            if ($subscription->status == 1) {
                $prod = LicenceXsollaProduct::where('id', $subscription->xsolla_product_id)->first();

                if ($prod->name == $licence->licence_key) {
                    $subscription_id = $subscription->subscription_id;
                    $date_next_charge = $subscription->date_next_charge;
                }
            }
        }
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['licence' => $licence, 'subscription_id' => $subscription_id, 'next_pay' => $date_next_charge]];
        return response($response, 200);
    }

    public function changeSubscription(Request $request)
    {
        $data = $request->all();
        $auth = Auth::user();
        $subscription_id = $data['subscription_id'];
        $licence_id = $data['licence_id'];
        $licence = Licence::where('id', $licence_id)->first();

        $xsollaClient = XsollaClient::factory(array(
            'merchant_id' => 43963,
            'api_key' => 'dc8y4BVLBNAZH4g0'
        ));

        $prod = "";
        $prize = 39;
        $planId = 'LEASE20';
        $group = "AtLeasLic";
        $connections = 100;
        $licence_type = 'LEASE20';
        if (strpos($licence->licence_type, 'LEASE') !== false) {
            if ($licence->licence_type == "Lease20") {
                $planId = 'AtLease20CCU';
                $xSollaPlanId = 233201;
                $prize = 39;
                $group = "AtLeasLic";
                $connections = 100;
                $licence_type = 'LEASE20';
            } else if ($licence->licence_type == "LEASE100") {
                $planId = 'AtLease100CCU';
                $xSollaPlanId = 233202;
                $prize = 51;
                $group = "AtLeasLic";
                $connections = 100;
                $licence_type = 'LEASE100';
            } else if ($licence->licence_type == "LEASE250") {
                $planId = 'AtLease250CCU';
                $xSollaPlanId = 233203;
                $prize = 63;
                $group = "AtLeasLic";
                $connections = 250;
                $licence_type = 'LEASE250';
            } else if ($licence->licence_type == "LEASE500") {
                $planId = 'AtLease500CCU';
                $xSollaPlanId = 233204;
                $prize = 33;
                $group = "AtLeasLic";
                $connections = 500;
                $licence_type = 'LEASE500';
            } else if ($licence->licence_type == "LEASE750") {
                $planId = 'AtLease750CCU';
                $xSollaPlanId = 233205;
                $prize = 120;
                $group = "AtLeasLic";
                $connections = 750;
                $licence_type = 'LEASE750';
            } else if ($licence->licence_type == "LEASE1000") {
                $planId = 'AtLease1000CCU';
                $xSollaPlanId = 233206;
                $prize = 38;
                $group = "AtLeasLic";
                $connections = 1000;
                $licence_type = 'LEASE1000';
            } else if ($licence->licence_type == "LEASE1500") {
                $planId = 'AtLease1500CCU';
                $xSollaPlanId = 233207;
                $prize = 225;
                $group = "AtLeasLic";
                $connections = 1500;
                $licence_type = 'LEASE1500';
            } else if ($licence->licence_type == "LEASE2000") {
                $planId = 'AtLease2000CCU';
                $xSollaPlanId = 233208;
                $prize = 48;
                $group = "AtLeasLic";
                $connections = 2000;
                $licence_type = 'LEASE2000';
            } else if ($licence->licence_type == "LEASE3000") {
                $planId = 'AtLease3000CCU';
                $xSollaPlanId = 233210;
                $prize = 58;
                $group = "AtLeasLic";
                $connections = 3000;
                $licence_type = 'LEASE3000';
            } else if ($licence->licence_type == "LEASE4000") {
                $planId = 'AtLease4000CCU';
                $xSollaPlanId = 233211;
                $prize = 128;
                $group = "AtLeasLic";
                $connections = 4000;
            } else if ($licence->licence_type == "LEASE5000") {
                $planId = 'AtLease5000CCU';
                $xSollaPlanId = 233212;
                $prize = 78;
                $group = "AtLeasLic";
                $connections = 5000;
            }

            $responseProductList = $xsollaClient->ListSubscriptionProducts(array(
                'project_id' => 158935
            ));
            $product_id = "";
            foreach ($responseProductList as $product) {
                if ($product['name'] == $licence->licence_key  && $product['group_id'] == $group) {
                    $product_id = $product['id'];
                }
            }
            if ($product_id == "") {
                $responseProduct = $xsollaClient->CreateSubscriptionProduct(array(
                    'project_id' => 158935,
                    'request' => array(
                        'name' => $licence->licence_key,
                        'group_id' => $group
                    ),
                ));
                $product_id = $responseProduct['product_id'];

                $product =   new LicenceXsollaProduct([
                    "id" => $product_id,
                    "name" => $licence->licence_key,
                    "group_id" => $group,
                    'project' => 158935,
                ]);
                $product->save();
            }


            $dd = new \DateTime('NOW', new \DateTimeZone('UTC'));
            $token  = LicenceController::generateOrderToken();
            $order = new LicenceOrder();
            \DB::beginTransaction();
            $order->user_id = $auth->id;
            $order->order_id = $token;
            $order->licence_id = $licence_id;
            $order->licences_order_type = "Subscription";
            $order->prize = $prize;
            $order->create_date = $dd->format('Y-m-d H:i:s');
            $order->status = 8;
            $order->product_id = $product_id;
            $order->plan_id = $planId;
            $order->extend_days = 30;
            $order->licence_key = $licence->licence_key;
            $order->connections = $connections;
            $order->new_licence_type = $licence_type;
            $order->save();
            \DB::commit();

            $tokenRequest = new TokenRequest(158935, $auth->email);
            $tokenRequest->setUserEmail($auth->email)
                ->setExternalPaymentId($token)
                ->setUserName($auth->username);
            $tokenRequest->setSandboxMode(true);

            $dataRequest = $tokenRequest->toArray();
            $dataRequest['settings']['ui']['desktop']['header']['visible_logo'] = true;
            $dataRequest['settings']['ui']['desktop']['header']['visible_name'] = true;
            $dataRequest['settings']['ui']['desktop']['header']['close_button'] = true;
            $dataRequest['settings']['return_url'] = 'https://devapanel.atavismonline.com/licences';
            $dataRequest['settings']['ui']['desktop']['subscription_list']['description'] = 'Select Atavism subscription plan';
            $dataRequest['purchase']['subscription']['product_id'] = "" . $product_id;
            $dataRequest['purchase']['subscription']['available_plans'] = array('zoFvotBq', 'CQ6GNlxL');
            //$dataRequest['purchase']['subscription']['available_plans'] = array ('AtLease20CCU',/*'AtLease100CCU','AtLease250CCU',*/'AtLease500CCU',/*'AtLease750CCU',*/'AtLease1000CCU',/*'AtLease1500CCU',*/'AtLease2000CCU','AtLease3000CCU','AtLease5000CCU','AtLease4000CCU');
            $dataRequest['settings']['ui']['size'] = "small";
            $dataRequest['settings']['language'] = "en";
            $parsedResponse = $xsollaClient->CreatePaymentUIToken(array('request' => $dataRequest));

            $xsollaToken = $parsedResponse['token'];
            $paymentRedirectUrl = "https://sandbox-secure.xsolla.com/paystation3/?access_token=" . $xsollaToken;
        }
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['data' => $data, 'xsollapayment' => $paymentRedirectUrl]];
        return response($response, 200);
    }

    public function convertAll(Request $request)
    {
        $data = $request->all();
        $auth = Auth::user();
        $licences = Licence::where('user_id',  $auth->id)->where('licence_type', 'like', '%PREM%')->where('convert_to', '<', 0)->get();
        $date = new \DateTime("2005-01-01", new \DateTimeZone('UTC'));
        $datenow = new \DateTime("now", new \DateTimeZone('UTC'));
        $count = 0;
        $prem5 = NULL;
        foreach ($licences as $lic) {
            if ($lic->licence_type == "PREM1") {
                $count +=  1500;
            } else if ($lic->licence_type == "PREM2") {
                $count +=  15000;
            } else if ($lic->licence_type == "PREM3") {
                $count +=  1000;
            } else if ($lic->licence_type == "PREM4") {
                $count +=  5000;
            } else if ($lic->licence_type == "PREM5") {
                $prem5 = $lic;
            }
            $date2 = new \DateTime($lic->maintenance_expire, new \DateTimeZone('UTC'));
            if ($date2 > $date) {
                $date = $date2;
            }
        }
        if ($count > 0) {
            if ($prem5 == null) {
                $licence_key = $this->generateLicenceKeys("X");
                $new_licence = new Licence();
                \DB::beginTransaction();
                $new_licence->user_id = $auth->id;
                $new_licence->order_id = -1;
                $new_licence->source = "Dragonsan Studios";
                $new_licence->product_id = -1;
                $new_licence->licence_key = $licence_key;
                $new_licence->licence_type = "PREM5";
                $new_licence->connections = $count;
                $new_licence->maintenance_expire = $date->format('Y-m-d H:i:s');
                $new_licence->buy_date = $datenow->format('Y-m-d H:i:s');
                $new_licence->multiserver = 1;
                $new_licence->save();
                foreach ($licences as $lic) {
                    $lic->convert_to = $new_licence->id;
                    $lic->enabled = 0;
                    $lic->save();
                }
                \DB::commit();
            } else {
                $date2 = new \DateTime($prem5->maintenance_expire, new \DateTimeZone('UTC'));
                if ($date2 > $date) {
                    $date = $date2;
                }
                $prem5->connections += $count;
                $prem5->maintenance_expire = $date;
                $prem5->save();
                foreach ($licences as $lic) {
                    if ($lic->id != $prem5->id) {
                        $lic->convert_to = $prem5->id;
                        $lic->enabled = 0;
                        $lic->save();
                    }
                }
            }
        }
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Convert All Permanent successfully'];
        return response($response, 200);
    }

    public function assignMaintenance(Request $request)
    {
        $data = $request->all();
        $maintenance_id = 0;
        $licence_id = 0;
        if (isset($data['licence_id'])) {
            $licence_id = (int)$data['licence_id'];
        }
        if (isset($data['maintenances'])) {
            $maintenance_id = (int)$data['maintenances'];
        }
        $date = new \DateTime();
        $licence = Licence::where('id', $licence_id)->first();
        $maintenance = Maintenance::where('id', $maintenance_id)->first();
        if ($maintenance->licence_id == 0) {
            $maintenance->assaign_date = $date;
            $maintenance->licence_id = $licence_id;
            $dd = new \DateTime($licence->maintenance_expire);
            if ($dd < $date) {
                $d1 = new \DateTime();
                if ($licence->licence_type == "PREM5") {
                    $d2 = new \DateTime();
                    $licence->maintenance_expire = $d2->modify('+' . $maintenance->number_days . ' day');
                } else if ($dd < $d1->modify('-180 day')) {
                    $d2 = new \DateTime();
                    if ($licence->licence_type == "PREM5") {
                        $licence->maintenance_expire = $d2->modify('+' . $maintenance->number_days . ' day');
                    } else {
                        $licence->maintenance_expire = $d2->modify('-180 day')->modify('+' . $maintenance->number_days . ' day');
                    }
                } else {
                    $licence->maintenance_expire = $dd->modify('+' . $maintenance->number_days . ' day');
                }
            } else {
                $licence->maintenance_expire = $dd->modify('+' . $maintenance->number_days . ' day');
            }
            $licence->save();
            $maintenance->save();
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Maintenance has been successfully assigned to Licence', 'data' => ['data' => $data]];
            return response($response, 200);
        }
    }

    public function extendMaintenance(Request $request)
    {
        $data = $request->all();
        $auth = Auth::user();
        $licence_id = $data['licence_id'];
        $maintenance = $data['maintenances'];
        $discountPrice = $data['actual_price'];
        $couponCode = $data['coupon_code'];

        $licence = Licence::where('id', $licence_id)->first();

        $prod = "";
        $period = 365;
        $prize = ($discountPrice != "" && $discountPrice != null) ? $discountPrice : 21;
        $backcover = 1;
        if ($maintenance == "AtMain30" && $licence->licence_type == "PREM5") {
            $prod =  'Extend Maintenance by 30 days for Licence ' . $licence->licence_key;
            $period = 30;
            $prize = ($discountPrice != "" && $discountPrice != null) ? $discountPrice : 15;
        } else if ($maintenance == "AtMain90"  && $licence->licence_type == "PREM5") {
            $prod =  'Extend Maintenance by 90 days for Licence ' . $licence->licence_key;
            $period = 90;
            $prize = $discountPrice ? $discountPrice : 45;
        } else if ($maintenance == "AtMain180" && $licence->licence_type == "PREM5") {
            $prod =  'Extend Maintenance by 180 days for Licence ' . $licence->licence_key;
            $period = 180;
            $prize = ($discountPrice != "" && $discountPrice != null) ? $discountPrice : 90;
        } else if ($maintenance == "AtMain365"  && $licence->licence_type == "PREM5") {
            $prod =  'Extend Maintenance by 365 days for Licence ' . $licence->licence_key;
            $period = 365;
            $prize = ($discountPrice != "" && $discountPrice != null) ? $discountPrice : 180;
        } else if ($maintenance == "AtAdvMain30" && $licence->licence_type == "PREM1") {
            $prod =  'Extend Maintenance by 30 days for Licence ' . $licence->licence_key;
            $period = 30;
            $prize = ($discountPrice != "" && $discountPrice != null) ? $discountPrice : 21;
        } else if ($maintenance == "AtAdvMain90"  && $licence->licence_type == "PREM1") {
            $prod =  'Extend Maintenance by 90 days for Licence ' . $licence->licence_key;
            $period = 90;
            $prize = ($discountPrice != "" && $discountPrice != null) ? $discountPrice : 63;
        } else if ($maintenance == "AtAdvMain180" && $licence->licence_type == "PREM1") {
            $prod =  'Extend Maintenance by 180 days for Licence ' . $licence->licence_key;
            $period = 180;
            $prize = ($discountPrice != "" && $discountPrice != null) ? $discountPrice : 125;
        } else if ($maintenance == "AtAdvMain365"  && $licence->licence_type == "PREM1") {
            $prod =  'Extend Maintenance by 365 days for Licence ' . $licence->licence_key;
            $period = 365;
            $prize = ($discountPrice != "" && $discountPrice != null) ? $discountPrice : 250;
        } else if ($maintenance == 'AtUltMain30' && $licence->licence_type == "PREM2") {
            $prod =  'Extend Maintenance by 30 days for Licence ' . $licence->licence_key;
            $period = 30;
            $prize = ($discountPrice != "" && $discountPrice != null) ? $discountPrice : 209;
        } else if ($maintenance == 'AtUltMain90' && $licence->licence_type == "PREM2") {
            $prod =  'Extend Maintenance by 90 days for Licence ' . $licence->licence_key;
            $period = 90;
            $prize = ($discountPrice != "" && $discountPrice != null) ? $discountPrice : 625;
        } else if ($maintenance == 'AtUltMain180' && $licence->licence_type == "PREM2") {
            $prod =  'Extend Maintenance by 180 days for Licence ' . $licence->licence_key;
            $period = 180;
            $prize = ($discountPrice != "" && $discountPrice != null) ? $discountPrice : 1250;
        } else if ($maintenance == 'AtUltMain365' && $licence->licence_type == "PREM2") {
            $prod =  'Extend Maintenance by 365 days for Licence ' . $licence->licence_key;
            $period = 365;
            $prize = ($discountPrice != "" && $discountPrice != null) ? $discountPrice : 2500;
        } else if ($maintenance == 'AtStdMain30' && $licence->licence_type == "PREM3") {
            $prod =  'Extend Maintenance by 30 days for Licence ' . $licence->licence_key;
            $period = 30;
            $prize = ($discountPrice != "" && $discountPrice != null) ? $discountPrice : 11;
        } else if ($maintenance == 'AtStdMain90' && $licence->licence_type == "PREM3") {
            $prod =  'Extend Maintenance by 90 days for Licence ' . $licence->licence_key;
            $period = 90;
            $prize = ($discountPrice != "" && $discountPrice != null) ? $discountPrice : 32;
        } else if ($maintenance == 'AtStdMain180' && $licence->licence_type == "PREM3") {
            $prod =  'Extend Maintenance by 180 days for Licence ' . $licence->licence_key;
            $period = 180;
            $prize = ($discountPrice != "" && $discountPrice != null) ? $discountPrice : 63;
        } else if ($maintenance == 'AtStdMain365' && $licence->licence_type == "PREM3") {
            $prod =  'Extend Maintenance by 365 days for Licence ' . $licence->licence_key;
            $period = 365;
            $prize = ($discountPrice != "" && $discountPrice != null) ? $discountPrice : 125;
        } else if ($maintenance == 'AtProMain30' && $licence->licence_type == "PREM4") {
            $prod =  'Extend Maintenance by 30 days for Licence ' . $licence->licence_key;
            $period = 30;
            $prize = ($discountPrice != "" && $discountPrice != null) ? $discountPrice : 105;
        } else if ($maintenance == 'AtProMain90' && $licence->licence_type == "PREM4") {
            $prod =  'Extend Maintenance by 90 days for Licence ' . $licence->licence_key;
            $period = 90;
            $prize = ($discountPrice != "" && $discountPrice != null) ? $discountPrice : 313;
        } else if ($maintenance ==  'AtProMain180' && $licence->licence_type == "PREM4") {
            $prod =  'Extend Maintenance by 180 days for Licence ' . $licence->licence_key;
            $period = 180;
            $prize = ($discountPrice != "" && $discountPrice != null) ? $discountPrice : 625;
        } else if ($maintenance == 'AtProMain365' && $licence->licence_type == "PREM4") {
            $prod =  'Extend Maintenance by 365 days for Licence ' . $licence->licence_key;
            $period = 365;
            $prize = ($discountPrice != "" && $discountPrice != null) ? $discountPrice : 1250;
        } else if ($maintenance ==  'AtXMain180' && $licence->licence_type == "PREM5") {
            $prod =  'Extend Maintenance by 180 days for Licence ' . $licence->licence_key;
            $period = 180;
            $prize =  ($discountPrice != "" && $discountPrice != null) ? $discountPrice : 90;
            $backcover = 0;
        } else if ($maintenance == 'AtXMain365' && $licence->licence_type == "PREM5") {
            $prod =  'Extend Maintenance by 365 days for Licence ' . $licence->licence_key;
            $period = 365;
            $prize = ($discountPrice != "" && $discountPrice != null) ? $discountPrice : 144;
            $backcover = 0;
        }

        $dd = new \DateTime();
        $token  = LicenceController::generateOrderToken();

        \DB::beginTransaction();
        $order = new LicenceOrder();
        $order->user_id = $auth->id;
        $order->order_id = $token;
        $order->licence_id = $licence->id;
        $order->licences_order_type = "Extend";
        $order->prize = $prize;
        $order->create_date = $dd->format('Y-m-d H:i:s');
        $order->status = 8;
        $order->extend_days = $period;
        $order->licence_key = $licence->licence_key;
        $order->backcover = $backcover;
        $order->save();
        if ($maintenance != $prize) {
            $promotionApplied = new PromotionApplied();
            $promotionApplied->user_id = $auth->id;
            $promotionApplied->price = $prize;
            $promotionApplied->discount_price = $prize;
            $promotionApplied->coupon_code = $couponCode;
            $promotionApplied->save();
        }
        \DB::commit();

        $tokenRequest = new TokenRequest(158935, $auth->email);
        $tokenRequest->setUserEmail($auth->email)
            ->setExternalPaymentId($token)
            ->setUserName($auth->username)
            ->setPurchase(is_int($prize) ? $prize : floatval($prize), "USD");
        $tokenRequest->setSandboxMode(true);

        $dataRequest = $tokenRequest->toArray();
        $dataRequest['purchase']['description']['value'] = 'Extend maintenance for ' . $licence->licence_key;
        //$dataRequest['purchase']['description']['value'] = "$prod";
        $dataRequest['settings']['return_url'] = 'https://devapanel.atavismonline.com/licences';
        $xsollaClient = XsollaClient::factory(array(
            'merchant_id' => 43963,
            'api_key' => 'dc8y4BVLBNAZH4g0'
        ));
        $parsedResponse = $xsollaClient->CreatePaymentUIToken(array('request' => $dataRequest));
        $xsollaToken = $parsedResponse['token'];
        //$paymentRedirectUrl = "https://sandbox-secure.xsolla.com/paystation3/desktop/list/?access_token=" . $xsollaToken;
        $paymentRedirectUrl = "https://sandbox-secure.xsolla.com/paystation4/?token=" . $xsollaToken;
        //return $xsollaToken;
        //$response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','data'=> ['licence' => $licence,'token' => $xsollaToken]];
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['xsollapayment' => $paymentRedirectUrl]];
        return response($response, 200);
    }

    public function subscriptionMaintenance(Request $request)
    {
        $auth = Auth::user();
        $licence_id = $request->get('licence_id');
        $licence = Licence::where('id', $licence_id)->first();
        $xsollaClient = XsollaClient::factory(array(
            'merchant_id' => 43963,
            'api_key' => 'dc8y4BVLBNAZH4g0'
        ));
        $prod = "";
        $prize = 209;
        $planId = '0aIHhwnd';
        $group = "AtUltLic";
        if ($licence->licence_type == "PREM1") {
            $planId = 'YwdbH4Nw';
            $xSollaPlanId = 227547;
            $prize = 21;
            $group = "AtAdvLic";
        } else if ($licence->licence_type == "PREM2") {
            $planId = '0aIHhwnd';
            $xSollaPlanId = 227549;
            $prize = 209;
            $group = "AtUltLic";
        } else if ($licence->licence_type == "PREM3") {
            $planId = 'gC7aHyA3';
            $xSollaPlanId = 227546;
            $prize = 11;
            $group = "AtStdLic";
        } else if ($licence->licence_type == "PREM4") {
            $planId = 'hR7JOyhh';
            $xSollaPlanId = 227548;
            $prize = 105;
            $group = "AtProLic";
        } else if ($licence->licence_type == "PREM5") {
            $planId = 'hR7JOyhh';
            $xSollaPlanId = 227548;
            $prize = 15;
            $group = "AtProLic";
        }
        $responseProductList = $xsollaClient->ListSubscriptionProducts(array(
            'project_id' => 158935
        ));
        foreach ($responseProductList as $product) {
            if ($product['name'] == $licence->licence_key) {
                $product_id = $product['id'];
            }
        }
        if ($product_id == "") {
            $responseProduct = $xsollaClient->CreateSubscriptionProduct(array(
                'project_id' => 158935,
                'request' => array(
                    'name' => $licence->licence_key,
                    'group_id' => $group
                ),
            ));
            $product_id = $responseProduct['product_id'];
        }
        $dd = new \DateTime();
        $token  = self::generateOrderToken();
        /*$order = new LicenceOrder();
      \DB::beginTransaction();
      $order->user_id = $auth->id;
      $order->order_id = $token;
      $order->licence_id = $licence->id;
      $order->licences_order_type = "Subscription";
      $order->prize = $prize;
      $order->create_date = $dd->format('Y-m-d H:i:s');
      $order->status = 8;
      $order->product_id = $product_id;
      $order->plan_id = $planId;
      $order->licence_key = $licence->licence_key;
      $order->save();
      \DB::commit();
      $curl = curl_init();

      curl_setopt_array($curl, array(
        CURLOPT_URL => "https://store.xsolla.com/api/v2/project/44056/payment",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => "{\"purchase\":{\"checkout\":{\"amount\":".$prize.",\"currency\":\"USD\"},\"description\":{\"value\":\"".$licence->licence_key."\"}}}",
        CURLOPT_HTTPHEADER => array(
          "authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJleHAiOjE5NjIyMzQwNDgsImlzcyI6Imh0dHBzOi8vbG9naW4ueHNvbGxhLmNvbSIsImlhdCI6MTU2MjE0NzY0OCwidXNlcm5hbWUiOiJ4c29sbGEiLCJ4c29sbGFfbG9naW5fYWNjZXNzX2tleSI6IjA2SWF2ZHpDeEVHbm5aMTlpLUc5TmMxVWFfTWFZOXhTR3ZEVEY4OFE3RnMiLCJzdWIiOiJkMzQyZGFkMi05ZDU5LTExZTktYTM4NC00MjAxMGFhODAwM2YiLCJlbWFpbCI6InN1cHBvcnRAeHNvbGxhLmNvbSIsInR5cGUiOiJ4c29sbGFfbG9naW4iLCJ4c29sbGFfbG9naW5fcHJvamVjdF9pZCI6ImU2ZGZhYWM2LTc4YTgtMTFlOS05MjQ0LTQyMDEwYWE4MDAwNCIsInB1Ymxpc2hlcl9pZCI6MTU5MjR9.GCrW42OguZbLZTaoixCZgAeNLGH2xCeJHxl8u8Xn2aI",
          "content-type: application/json"
        ),
      ));

      $response = curl_exec($curl);
      $err = curl_error($curl);

      curl_close($curl);

      if ($err) {
        return $err;
      } else {
        return $response;
      }*/
        $tokenRequest = new TokenRequest(28914, $auth->email);
        $tokenRequest->setUserEmail($auth->email)
            ->setExternalPaymentId($token)
            ->setUserName($auth->username);
        $tokenRequest->setSandboxMode(true);
        $dataRequest = $tokenRequest->toArray();
        $dataRequest['purchase']['subscription']['plan_id'] = $planId;
        $dataRequest['purchase']['subscription']['product_id'] = "" . $product_id;
        $dataRequest['purchase']['description']['value'] = 'Monthly Subscription for licence ' . $licence->licence_key;
        $dataRequest['settings']['ui']['size'] = "small";
        $dataRequest['settings']['language'] = "en";
        $parsedResponse = $xsollaClient->CreatePaymentUIToken(array('request' => $dataRequest));
        $xsollaToken = $parsedResponse['token'];
        $paymentRedirectUrl = "https://sandbox-secure.xsolla.com/paystation4/?token=" . $xsollaToken;
        //$paymentRedirectUrl = "https://sandbox-secure.xsolla.com/paystation3/desktop/payment/?access_token=1mon8SeLTqcyVfxsNiFsk1sqrQsdatPO";
        //$response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','data'=> ['licence' => $licence,'token' => $xsollaToken]];
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['xsollapayment' => $paymentRedirectUrl]];
        return response($response, 200);
    }

    public function updateLicence(Request $request)
    {
        $licence_id = $request->get('licence_id');
        $licences = Licence::where('id', $licence_id)->get();

        foreach ($licences as $licence) {
            $licence->assigned_to = $request->get('email');
            $licence->save();
        }
        $message = "Licence has been successfully assigned to " . $request->get('email');
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => $message];
        return response($response, 200);
    }

    public function getCancelSubscription(Request $request)
    {
        /*$data = $request->all();
    $licence_id = $data[0];
    $subscription_id = 0;
    $order = LicenceOrder::where('licence_id', $licence_id)->where('licences_order_type',"like" ,"Subscription")->first();
    $subscription_id = $order->subscription_id;
    $date_next_charge = $order->date_next_charge;
    $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','data'=> ['licence' => $order, 'subscription_id' => $subscription_id, 'next_pay' => $date_next_charge]];*/
        $auth = Auth::user();
        $data = $request->all();
        $licenceId = $data[0];
        $licence = Licence::find($licenceId);
        $result = LicenceSubscription::where('user_id', $auth->id)->where('status', 1)->where('licence_key', $licence->licence_key)->get();
        $subscription_id = 0;
        $date_next_charge = "";

        foreach ($result as $subscription) {
            if ($subscription->status == 1) {
                $prod = LicenceXsollaProduct::where('id', $subscription->xsolla_product_id)->first();
                if ($prod->name == $licence->licence_key) {
                    $subscription_id = $subscription->subscription_id;
                    $date_next_charge = $subscription->date_next_charge;
                }
            }
        }
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['licence' => $licence, 'subscription_id' => $subscription_id, 'next_pay' => $date_next_charge]];
        return response($response, 200);
    }

    public function cancelSubscription(Request $request)
    {
        $auth = Auth::user();
        $role_id = $auth->role_id;
        $data = $request->all();
        $licence_id = $data['licence_id'];
        $subscription_id = $data['subscription_id'];
        $reason = $data['reason'];
        $licence = Licence::where('id', $licence_id)->first();
        $subscriptionCancelReason = CancelReason::where("archive_id", $reason)->where("published", 1)->get();
        $reason_text = "";
        if ($reason == 1) {
            $archive_id = $subscriptionCancelReason[0]->archive_id;
            $reason = $subscriptionCancelReason[0]->reason;
            //$reason = "It’s too hard to set it up.";
        } else if ($reason == 2) {
            $archive_id = $subscriptionCancelReason[0]->archive_id;
            $reason = $subscriptionCancelReason[0]->reason;
            //$reason = "I couldn’t receive any support and resolve my issue.";
        } else if ($reason == 4) {
            $archive_id = $subscriptionCancelReason[0]->archive_id;
            $reason = $subscriptionCancelReason[0]->reason;
            //$reason = "Atavism didn’t meet my expectations.";
        } else if ($reason == 5) {
            $archive_id = $subscriptionCancelReason[0]->archive_id;
            $reason = $subscriptionCancelReason[0]->reason;
            //$reason = "Other";
            $reason_text = $data['reason_text'];
        }

        //$order = LicenceOrder::where('licence_id', $licence_id)->where('licences_order_type',"like" ,"Subscription")->first();
        //$subscription_id = $data['subscription_id'];
        //$subscription_id = $order->subscription_id;
        \DB::beginTransaction();
        $subscriptionCancelReason = new SubscriptionCancelReason();
        $subscriptionCancelReason->user_id = $auth->id;
        $subscriptionCancelReason->archive_id = $archive_id;
        $subscriptionCancelReason->role_id = $role_id;
        $subscriptionCancelReason->licence_id = $licence_id;
        $subscriptionCancelReason->reason = $reason;
        $subscriptionCancelReason->reason_text = $reason_text;
        $subscriptionCancelReason->save();
        \DB::commit();
        $xsollaClient = XsollaClient::factory(array(
            'merchant_id' => 43963,
            'api_key' => 'dc8y4BVLBNAZH4g0'
        ));

        $response = $xsollaClient->UpdateSubscription(array(
            'project_id' => 158935,
            'user_id' => $auth->email,
            'subscription_id' => (int)$subscription_id,
            'request' => array(
                'status' => 'canceled',
                'cancel_subscription_payment' => false,
            )
        ));
        //$message = "Subscription canceled successfully";
        $message = "The subscription cancellation for " . $licence->licence_key . " has been ordered. It may take a few minutes";
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => $message];
        return response($response, 200);
    }

    public function addUnityLicence(Request $request)
    {
        $auth = Auth::user();
        $data = $request->all();
        $invoice = $data['invoice_id'];
        //$invoice = "010001499222";
        $invoice = trim($invoice);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.assetstore.unity3d.com/publisher/v1/invoice/verify.json?key=AQ2cZBO23zlNrhkMkhI3airKybA&invoice=' . $invoice);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);
        $responseData = json_decode($response);
        $invoiceNumbersArray = array($invoice);
        $invoiceNumbersInfo = $responseData->invoices;
        $validID = false;
        $licence_count = 0;
        if (isset($invoiceNumbersInfo)) {
            foreach ($invoiceNumbersInfo as $value) {
                $assetName = $value->package;
                $assetQuantity = $value->quantity;
                $assetCost = doubleval($value->price_exvat);
                $refunded =  $value->refunded;
                $productConfigurations = ProductConfigurations::where('product_name', 'LIKE', '%' . $assetName . '%')->where('from_price', '<', $assetCost)->where('to_price', '>', $assetCost)->first();
                if ($productConfigurations) {
                    $minPrice = $productConfigurations->from_price;
                    $maxPrice = $productConfigurations->to_price;
                    $productName = @$productConfigurations->product_name;
                    $maintenanceDays = @$productConfigurations->maintenance;
                    $ccu = ($productConfigurations->concurrent_connections ? $productConfigurations->concurrent_connections : 10);
                    $multiverver = ($productConfigurations->multiverver ? $productConfigurations->multiverver : 1);
                    $licence_type = @$productConfigurations->licence_type;
                } else {
                    $message = "That is not a valid Unity Invoice " . $invoice . " ID for the Atavism package!";
                    $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => $message, 'data' => $data];
                    return response($response, 422);
                }

                if (($assetName == $productName && $refunded == 'No')) {
                    $validID = true;
                    $SQL = "licence_key = '" . $invoice . "'";
                    if (ctype_digit($invoice)) {
                        $SQL = "licence_key = '" . $invoice . "' OR licence_key = 'IN" . $invoice . "'";
                        $licence_count = Licence::where('licence_key', '=', $invoice)->orWhere('licence_key', '=', 'IN' . $invoice)->orwhere('invoice', '=', $invoice)->orWhere('invoice', '=', 'IN' . $invoice)->orWhere('invoice', '=', $value->invoice)->count();
                    } else if ("IN" == substr($invoice, 0, 2) &&  strrpos($invoice, "-") === false) {
                        $SQL = "licence_key = '" . $invoice . "' OR licence_key = '" . substr($invoice, 2) . "'";
                        $licence_count = Licence::where('licence_key', '=', $invoice)->orWhere('licence_key', '=', substr($invoice, 2))->orwhere('invoice', '=', $invoice)->orWhere('invoice', '=', substr($invoice, 2))->orWhere('invoice', '=', $value->invoice)->count();
                    } else {
                        $licence_count = Licence::where('licence_key', '=', $invoice)->orwhere('invoice', '=', $invoice)->count();
                    }

                    if ($licence_count == 0) {
                        $created_date = new \DateTime($value->date, new \DateTimeZone('UTC'));
                        $dd = new \DateTime($value->date);
                        //$data = $dd->modify('+180 day');
                        $data = $dd->modify('+' . $maintenanceDays . ' day');
                        $licences = Licence::where('user_id',  $auth->id)->where('licence_type', 'like', 'PREM5')->where('enabled', 1)->first();
                        for ($i = 0; $i < $value->quantity; $i++) {
                            if ($licences == null) {
                                \DB::beginTransaction();
                                $new_licence = new Licence();
                                $new_licence->user_id = $auth->id;
                                $new_licence->order_id = -1;
                                $new_licence->source = "Unity";
                                $new_licence->product_id = -1;
                                $new_licence->licence_key = $this->generateLicenceKeys("U");
                                $new_licence->invoice = $value->invoice;
                                $new_licence->connections = @$ccu;
                                $new_licence->maintenance_expire = $data->format('Y-m-d H:i:s');
                                $new_licence->buy_date = $created_date->format('Y-m-d H:i:s');
                                $new_licence->multiserver = $multiverver;
                                $new_licence->save();
                                \DB::commit();
                            } else {
                                $dd = $created_date;
                                $dd2 = new \DateTime($licences->maintenance_expire, new \DateTimeZone('UTC'));
                                if ($dd2 > $dd) {
                                    $dd = $dd2;
                                }
                                //$data = $dd->modify('+180 day');
                                $data = $dd->modify('+' . $maintenanceDays . ' day');
                                $licences->maintenance_expire  = $data->format('Y-m-d H:m:s');
                                $licences->connections += @$ccu;
                                $licences->save();
                                \DB::beginTransaction();
                                $new_licence = new Licence();
                                $new_licence->user_id = $auth->id;
                                $new_licence->order_id = -1;
                                $new_licence->source = "Unity";
                                $new_licence->product_id = -1;
                                $new_licence->licence_key = 'ExtendCCU-' . $value->invoice;
                                $new_licence->licence_type = $licence_type;
                                $new_licence->connections = @$ccu;
                                $new_licence->invoice = $value->invoice;
                                $new_licence->maintenance_expire = $created_date->format('Y-m-d H:i:s');
                                $new_licence->buy_date = $created_date->format('Y-m-d H:i:s');
                                $new_licence->multiserver = $multiverver;
                                $new_licence->enabled = 0;
                                $new_licence->convert_to = $licences->id;
                                $new_licence->save();
                                \DB::commit();
                            }
                            if ($licences == null) {
                                $licences = Licence::where('user_id',  $auth->id)->where('licence_type', 'like', 'PREM5')->where('enabled', 1)->first();
                            }
                        }
                    }
                } else if (($assetName == $productName) || $licence) {
                    $validID = true;
                    $SQL = "licence_key = '" . $invoice . "'";
                    if (ctype_digit($invoice)) {
                        $SQL = "licence_key = '" . $invoice . "' OR licence_key = 'IN" . $invoice . "'";
                        $licence_count = Licence::where('licence_key', '=', $invoice)->orWhere('licence_key', '=', 'IN' . $invoice)->orwhere('invoice', '=', $invoice)->orWhere('invoice', '=', 'IN' . $invoice)->orWhere('invoice', '=', $value->invoice)->count();
                    } else if ("IN" == substr($invoice, 0, 2) &&  strrpos($invoice, "-") === false) {
                        $SQL = "licence_key = '" . $invoice . "' OR licence_key = '" . substr($invoice, 2) . "'";
                        $licence_count = Licence::where('licence_key', '=', $invoice)->orWhere('licence_key', '=', substr($invoice, 2))->orwhere('invoice', '=', $invoice)->orWhere('invoice', '=', substr($invoice, 2))->orWhere('invoice', '=', $value->invoice)->count();
                    } else {
                        $licence_count = Licence::where('licence_key', '=', $invoice)->orWhere('invoice', '=', $invoice)->count();
                    }
                    if ($licence_count == 0) {
                        if ($assetCost > $maxPrice) {
                            $licences = Licence::where('user_id',  $auth->id)->where('licence_type', 'like', 'PREM5')->where('enabled', 1)->first();
                            //$created_date = new \DateTime(date("Y-m-d H:i:s",$value->GetPurchaseDate()), new \DateTimeZone('UTC'));
                            $created_date = new \DateTime($value->date, new \DateTimeZone('UTC'));
                            //$dd = new \DateTime(date("Y-m-d H:i:s",$value->GetPurchaseDate()));
                            $dd = new \DateTime($value->date);
                            //$data = $dd->modify('+180 day');
                            $data = $dd->modify('+' . $maintenanceDays . ' day');
                            //  for ($i=0;$i<$value->GetQuantity();$i++){
                            for ($i = 0; $i < $value->quantity; $i++) {
                                if ($licences == null) {
                                    \DB::beginTransaction();
                                    $new_licence = new Licence();
                                    $new_licence->user_id = $auth->id;
                                    $new_licence->order_id = -1;
                                    $new_licence->source = "Unity";
                                    $new_licence->product_id = -1;
                                    $new_licence->licence_key = $this->generateLicenceKeys("U");
                                    $new_licence->invoice = $value->invoice;
                                    $new_licence->licence_type = $licence_type;
                                    $new_licence->connections = @$ccu;
                                    $new_licence->maintenance_expire = $data->format('Y-m-d H:i:s');
                                    $new_licence->buy_date = $created_date->format('Y-m-d H:i:s');
                                    $new_licence->multiserver = $multiverver;
                                    $new_licence->save();
                                    \DB::commit();
                                    $message = "Your Atavism Licence has been added!";
                                    //$ms->addMessageTranslated("success", "Your Atavism Licence has been added!", $post);
                                    //return;
                                    $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => $message, 'data' => $data];
                                    return response($response, 200);
                                } else {
                                    $dd = $created_date; //new \DateTime($created_date, new \DateTimeZone('UTC'));
                                    $dd2 = new \DateTime($licences->maintenance_expire, new \DateTimeZone('UTC'));
                                    if ($dd2 > $dd) {
                                        $dd = $dd2;
                                    }
                                    //$data = $dd->modify('+180 day');
                                    $data = $dd->modify('+' . $maintenanceDays . ' day');
                                    $licences->maintenance_expire  = $data->format('Y-m-d H:m:s');
                                    $licences->connections += $ccu;
                                    $licences->save();
                                    \DB::beginTransaction();
                                    $new_licence = new Licence();
                                    $new_licence->user_id = $auth->id;
                                    $new_licence->order_id = -1;
                                    $new_licence->source = "Unity";
                                    $new_licence->product_id = -1;
                                    $new_licence->licence_key = $this->generateLicenceKeys("U");
                                    $new_licence->licence_type = $licence_type;
                                    $new_licence->invoice = $value->invoice;
                                    $new_licence->connections = @$ccu;
                                    $new_licence->maintenance_expire = $data->format('Y-m-d H:i:s');
                                    $new_licence->buy_date = $created_date->format('Y-m-d H:i:s');
                                    $new_licence->multiserver = $multiverver;
                                    $new_licence->enabled = 0;
                                    $new_licence->convert_to = $licences->id;
                                    $new_licence->save();
                                    \DB::commit();
                                    $message = "The amount of ccu has been increased for your license!";
                                    //$ms->addMessageTranslated("success", "The amount of ccu has been increased for your license!", $post);
                                    //return;
                                    $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => $message, 'data' => $data];
                                    return response($response, 200);
                                }
                            }
                        } else if ($assetCost > $minPrice && $assetCost < $maxPrice) {
                            $licences = Licence::where('user_id',  $auth->id)->where('licence_type', 'like', 'PREM5')->where('enabled', 1)->first();
                            $created_date = new \DateTime($value->date, new \DateTimeZone('UTC'));
                            $dd = new \DateTime($value->date);
                            //$data = $dd->modify('+180 day');
                            $data = $dd->modify('+' . $maintenanceDays . ' day');
                            for ($i = 0; $i < $value->quantity; $i++) {
                                if ($licences == null) {
                                    $message = "NotFound Multiserver Licence";
                                    $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => $message, 'data' => $data];
                                    return response($response, 200);
                                    //$ms->addMessageTranslated("danger", "NotFound Multiserver Licence", $post);
                                } else {
                                    $dd = $created_date; //new \DateTime($created_date, new \DateTimeZone('UTC'));
                                    $dd2 = new \DateTime($licences->maintenance_expire, new \DateTimeZone('UTC'));
                                    if ($dd2 > $dd) {
                                        $dd = $dd2;
                                    }
                                    //$data = $dd->modify('+180 day');
                                    $data = $dd->modify('+' . $maintenanceDays . ' day');
                                    $licences->maintenance_expire = $data->format('Y-m-d H:m:s');
                                    $licences->save();
                                    \DB::beginTransaction();
                                    $new_licence = new Licence();
                                    $new_licence->user_id = $auth->id;
                                    $new_licence->order_id = -1;
                                    $new_licence->source = "Unity";
                                    $new_licence->product_id = -1;
                                    $new_licence->licence_key = 'ExtendMNG-' . $value->invoice;
                                    $new_licence->licence_type = $licence_type;
                                    $new_licence->invoice = $value->invoice;
                                    $new_licence->connections = @$ccu;
                                    $new_licence->maintenance_expire = $data->format('Y-m-d H:i:s');
                                    $new_licence->buy_date = $created_date->format('Y-m-d H:i:s');
                                    $new_licence->multiserver = $multiverver;
                                    $new_licence->enabled = 0;
                                    $new_licence->convert_to = $licences->id;
                                    $new_licence->save();
                                    \DB::commit();
                                    $message = "Maintanance to Your Atavism Licence has extended!";
                                    $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => $message, 'data' => $data];
                                    return response($response, 200);
                                    //$ms->addMessageTranslated("success", "Maintanance to Your Atavism Licence has extended!", $post);
                                }
                            }
                            return;
                        } else {
                            $message = "The free update cannot be registered! Please contact support via support@atavismonline.com or on the Discord";
                            //$ms->addMessageTranslated("danger", "The free update cannot be registered! Please contact support via support@atavismonline.com or on the Discord", $post);
                            //return;
                            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => $message, 'data' => $data];
                            return response($response, 422);
                        }
                    }
                }
            }
        }
        // Insert Licence
        if ($validID) {
            if ($licence_count == 0) {
                $message = "Your Atavism Licence has been added!";
                $statusCode = 200;
                //$ms->addMessageTranslated("success", "Your Atavism Licence has been added!", $post);
            } else {
                $message = "This Unity Invoice ID is already assigned!";
                $statusCode = 422;
                //$ms->addMessageTranslated("danger", "This Unity Invoice ID is already assigned!", $post);
            }
        } else {
            $message = "That is not a valid Unity Invoice " . $invoice . " ID for the Atavism package!";
            $statusCode = 422;
            //$ms->addMessageTranslated("danger", "That is not a valid Unity Invoice ID for the Atavism package!", $post);
        }
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => $message, 'data' => $data];
        return response($response, $statusCode);
    }

    public function licenceType()
    {
        $licence_types = config('constants.LICENCES');
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['licence_types' => $licence_types]];
        return response($response, 200);
    }

    public function getLicences(Request $request)
    {
        // First check if any new licences need to be added
        $this->checkOrders($request);

        $this->getLicenceIds($request);

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
            $licence->mmmDate =  strtotime($licence->maintenance_expire);
            $licence->nnnDate = strtotime(Carbon::now());
            if ($licence->mmmDate > $licence->nnnDate) {
                if ($licence->diff < 14) {
                    $licence->bgcolor = "#FFFF99";
                } else {
                    $licence->bgcolor = "#99FF99";
                }
            } else {
                $licence->bgcolor = "#FF9999";
            }
            //$licences[$licence->id] = $licence;
            $licences[] = $licence;
        }
        return $licences;
    }

    /**
     * Checks the users orders and order id's to see if any orders have not yet been converted into licences
     *
     */
    public function checkOrders(Request $request)
    {
        // Loop through each order
        $orders = $this->getOrders($request);

        $licence_multi = Licence::where('user_id',  $request->get('userId'))->where('licence_type', 'like', 'PREM5')->where('enabled', 1)->first();
        if ($licence_multi == null) {
            error_log("APANEL checkOrders licence_multi= is null");
        }
        //error_log("APANEL checkOrders $licence_multi="+print_r($licence_multi,true));

        foreach ($orders as $order) {
            // If order status = 4 (complete), get order_product details
            if ($order->order_status_id == 4) {
                $order_products = $this->getOrderProducts($order->id);

                // Loop through order product and check if there is an entry in the licence table to match
                foreach ($order_products as $order_product) {

                    if (Licence::getLicenceType((int)$order_product->product_id) != "" && !Maintenance::getDaysCount($order_product->product_id) > 0) {
                        $licence_count = $this->getLicenceCountForOrderProduct($order->id, $order_product->product_id);
                        while ($licence_count < $order_product->quantity) {
                            // If no match, create entry in licence table
                            if ($licence_multi != null) {
                                $this->extendLicence($licence_multi, $order->id, $order_product->product_id, $order->created_date);
                            } else {
                                $this->generateLicenceKey($order->id, $order_product->product_id, $order->created_date);
                            }
                            if ($licence_multi == null) {
                                $licence_multi = Licence::where('user_id',  $this->id)->where('licence_type', 'like', 'PREM5')->where('enabled', 1)->first();
                            }
                            $licence_count++;
                        }
                    } else if (Maintenance::getDaysCount($order_product->product_id) > 0) {
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

    public function getOrders(Request $request)
    {
        $orders = $this->getOrderIds($request);
        /*if(count($orders) > 0){
            $result = Order::find($orders);
            Log::info("result111",[$result]);
        }else{
            $result = Order::find($orders);
            Log::info("orders222",[$orders]);
            $orders = [];
            foreach ($result as $order) {
                $orders[$order->id] = $order;
            }
        }*/
        return $orders;
    }

    public function getOrderIds(Request $request)
    {
        $result = DB::table('josgt_eshop_orders')->where("customer_id", $request->get('userId'))->get();
        return $result;
        // Fetch from database, if not set
        /*if (!isset($request)) {
            $result = DB::table('josgt_eshop_orders')->select("id")->where("customer_id", $request->get('userId'))->get();
            $orders = [];
            foreach ($result as $order) {
                $orders[] = $order->id;
            }
        }*/
        //return $orders;
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

    public function getMaintenanceCountForOrderProduct($order_id, $product_id)
    {
        $result = DB::table('josgt_maintenances')->select("id")->where("order_id", $order_id)->where("product_id", $product_id)->get();
        return count($result);
    }

    public function getLicenceIds(Request $request)
    {
        // Fetch from database, if not set
        if (!isset($this->_licences)) {
            $result = DB::table('josgt_licences')->select("id")->where("user_id", $request->get('userId'))->where('enabled', 1)->where('convert_to', '<', 0)->get();

            $this->_licences = [];
            foreach ($result as $licence) {
                $this->_licences[] = $licence->id;
            }
        }
        return $this->_licences;
    }

    public function getMaintenances(Request $request)
    {
        // First check if any new licences need to be added
        $this->getMaintenanceIds($request);
        // Return the array of group objects
        $result = Maintenance::find($this->_maintenances);
        $maintenances = [];
        foreach ($result as $maintenance) {
            $licence = Licence::find($maintenance->licence_id);
            $josgtProduct = Products::find($maintenance['product_id']);
            $maintenances[] = $maintenance;
            $productIdArr[] = doubleval($josgtProduct['product_price']) . '=>' . doubleval($josgtProduct['product_price']);
            $productConfigurations = ProductConfigurations::where('product_name', $maintenance['maintenance_type'])->where('from_price', '<', doubleval($josgtProduct['product_price']))->where('to_price', '>', doubleval($josgtProduct['product_price']))->get();
            if ($maintenance['licence_id'] == 0 && $productConfigurations != "" && $maintenance['days_applied'] == NULL) {
                $numberOfDays = Maintenance::getDaysCount($maintenance['product_id']);
                $updateMaintenance = Maintenance::find($maintenance['id']);
                $updateMaintenance->number_days = (@$productConfigurations[0]['maintenance'] > '0' ? $productConfigurations[0]['maintenance'] : $numberOfDays);
                $updateMaintenance->days_applied = 1;
                $updateMaintenance->save();
                $maintenance['updateMaintenance'] = $updateMaintenance;
            }
            $maintenance['assigned_licence_key'] = @$licence['licence_key'];
        }
        return $maintenances;
    }

    public function getMaintenanceIds(Request $request)
    {
        // Fetch from database, if not set
        if (!isset($this->_maintenances)) {
            $result = DB::table('josgt_maintenances')->select("id")->where("user_id", $request->get('userId'))->get();

            $this->_maintenances = [];
            foreach ($result as $licence) {
                $this->_maintenances[] = $licence->id;
            }
        }
        return $this->_maintenances;
    }

    public function getMaintenancesForLicence($licence_id)
    {
        $auth = Auth::user();
        $licences = Licence::where('id', $licence_id)->first();
        $result = Maintenance::where("user_id", $licences->user_id)
            ->where("licence_type", $licences->licence_type)
            ->where("licence_id", "0")
            ->get();
        $maintenances = [];
        foreach ($result as $maintenance) {
            $maintenances[] = $maintenance;
        }
        return $maintenances;
    }

    public static function generateOrderToken($gen = null)
    {
        do {
            $gen = md5(uniqid(mt_rand(), false));
        } while (LicenceOrder::where('order_id', $gen)->first());
        return $gen;
    }

    public function generateLicenceKey($order_id, $product_id, $created_date)
    {
        $LicenceType = Licence::getLicenceType((int)$product_id);
        if ($LicenceType == "CCU") {
            return;
        }
        $multiverver = 0;
        $licence_key = '';

        if ($LicenceType == "PREM5") {
            $multiverver = 1;
            $licence_key = 'X';
        }
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ123456789';
        for ($i = 0; $i < 4; $i++) {
            $licence_key .= $characters[rand(0, strlen($characters) - 1)];
        }
        $licence_key .= "-";
        for ($i = 0; $i < 4; $i++) {
            $licence_key .= $characters[rand(0, strlen($characters) - 1)];
        }
        $licence_key .= "-";
        for ($i = 0; $i < 4; $i++) {
            $licence_key .= $characters[rand(0, strlen($characters) - 1)];
        }
        $dd = new \DateTime($created_date, new \DateTimeZone('UTC'));
        $buydate = new \DateTime($created_date, new \DateTimeZone('UTC'));
        if ($LicenceType == "LEASE20") {
            $data = $dd->modify('+30 day');
        } else if ($LicenceType == "TRIAL") {
            $data = $dd->modify('+14 day');
        } else {
            $data = $dd->modify('+180 day');
        }
        $licenceConnection = Licence::getConnectionCount((int)$product_id);
        $new_licence = new Licence();
        $new_licence->user_id = Auth::user()->id;
        $new_licence->order_id = $order_id;
        $new_licence->source = "Dragonsan Studios";
        $new_licence->product_id = $product_id;
        $new_licence->licence_key = $licence_key;
        $new_licence->licence_type = $LicenceType;
        $new_licence->connections = $licenceConnection;
        $new_licence->maintenance_expire = $buydate->format('Y-m-d H:m:s');
        $new_licence->buy_date = $buydate->format('Y-m-d H:m:s');
        $new_licence->multiserver = $multiverver;
        $new_licence->save();
        /*$new_licence = new Licence([
            "user_id" => $this->id,
            "order_id" => $order_id,
            "source" => "Dragonsan Studios",
            "product_id" => $product_id,
            "licence_key" => $licence_key,
            "licence_type" => $LicenceType,
            "connections" => $licenceConnection,
            "maintenance_expire" => $data->format('Y-m-d H:m:s') ,
            "buy_date" => $buydate->format('Y-m-d H:m:s'),
            "multiserver" => $multiverver,
        ]);
        $new_licence->save();*/
    }
    public function generateLicenceKeys($unity)
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ123456789';
        $licence_key = $unity;
        for ($i = 0; $i < 4; $i++) {
            $licence_key .= $characters[rand(0, strlen($characters) - 1)];
        }
        $licence_key .= "-";
        for ($i = 0; $i < 4; $i++) {
            $licence_key .= $characters[rand(0, strlen($characters) - 1)];
        }
        $licence_key .= "-";
        for ($i = 0; $i < 4; $i++) {
            $licence_key .= $characters[rand(0, strlen($characters) - 1)];
        }
        $licence_count = Licence::where('licence_key', '=', $licence_key)->count();

        if ($licence_count > 0) {
            $licence_key = generateLicenceKey();
        }
        return  $licence_key;
    }

    public function generateLicenceKeyMaintenace($order_id, $product_id, $created_date)
    {
        $LicenceType = Licence::getLicenceType((int)$product_id);
        if ($LicenceType == "CCU") {
            return;
        }
        $multiverver = 0;
        $licence_key = '';

        if ($LicenceType == "PREM5") {
            $multiverver = 1;
            $licence_key = 'X';
        }
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ123456789';
        for ($i = 0; $i < 4; $i++) {
            $licence_key .= $characters[rand(0, strlen($characters) - 1)];
        }
        $licence_key .= "-";
        for ($i = 0; $i < 4; $i++) {
            $licence_key .= $characters[rand(0, strlen($characters) - 1)];
        }
        $licence_key .= "-";
        for ($i = 0; $i < 4; $i++) {
            $licence_key .= $characters[rand(0, strlen($characters) - 1)];
        }
        $dd = new \DateTime($created_date, new \DateTimeZone('UTC'));
        $buydate = new \DateTime($created_date, new \DateTimeZone('UTC'));


        if ($LicenceType == "LEASE20") {
            $data = $dd->modify('+30 day');
        } else if ($LicenceType == "TRIAL") {
            $data = $dd->modify('+14 day');
        } else {
            $data = $dd->modify('+180 day');
        }
        $licenceConnection = Licence::getConnectionCount((int)$product_id);
        $new_licence = new Licence();
        $new_licence->user_id = Auth::user()->id;
        $new_licence->order_id = $order_id;
        $new_licence->source = "Dragonsan Studios";
        $new_licence->product_id = $product_id;
        $new_licence->licence_key = $licence_key;
        $new_licence->licence_type = $licenceType;
        $new_licence->connections = $licenceConnection;
        $new_licence->maintenance_expire = $buydate->format('Y-m-d H:m:s');
        $new_licence->buy_date = $buydate->format('Y-m-d H:m:s');
        $new_licence->multiserver = $multiverver;
        $new_licence->save();
        /*$new_licence = new Licence([
            "user_id" => $this->id,
            "order_id" => $order_id,
            "source" => "Dragonsan Studios",
            "product_id" => $product_id,
            "licence_key" => $licence_key,
            "licence_type" => $LicenceType,
            "connections" => $licenceConnection,
            "maintenance_expire" => $data->format('Y-m-d H:m:s') ,
            "buy_date" => $buydate->format('Y-m-d H:m:s'),
            "multiserver" => $multiverver,
        ]);
        $new_licence->save();*/
    }

    public function generateMaintenanceKey($order_id, $product_id)
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ123456789';
        $licence_key = 'AMP-';
        for ($i = 0; $i < 4; $i++) {
            $licence_key .= $characters[rand(0, strlen($characters) - 1)];
        }
        $licence_key .= "-";
        for ($i = 0; $i < 4; $i++) {
            $licence_key .= $characters[rand(0, strlen($characters) - 1)];
        }
        $licence_key .= "-";
        for ($i = 0; $i < 4; $i++) {
            $licence_key .= $characters[rand(0, strlen($characters) - 1)];
        }
        $maintenance = new Maintenance();
        $maintenance->user_id = Auth::user()->id;
        $maintenance->order_id = $order_id;
        $maintenance->source = 'Dragonsan Studios';
        $maintenance->product_id = $product_id;
        $maintenance->licences_maintenance_key = $licence_key;
        $maintenance->licence_type = Maintenance::getLicenceType($product_id);
        $maintenance->maintenance_type = Maintenance::getMaintenanceType($product_id);
        $maintenance->number_days = Maintenance::getDaysCount($product_id);
        $maintenance->save();
        /*$new_licence = new Maintenance([
            "user_id" => 5141,
            "order_id" => $order_id,
            "source" => "Dragonsan Studios",
            "product_id" => $product_id,
            "licences_maintenance_key" => $licence_key,
            "licence_type" => Maintenance::getLicenceType($product_id),
            "maintenance_type" => Maintenance::getMaintenanceType($product_id),
            "number_days" => Maintenance::getDaysCount($product_id)
        ]);
        $new_licence->save();*/
    }

    public function extendLicence($licence, $order_id, $product_id, $created_date)
    {
        $licenceType = Licence::getLicenceType((int)$product_id);
        $licenceConnection = Licence::getConnectionCount((int)$product_id);
        if ($licenceType == "CCU") {
            $licence->connections += $licenceConnection;
            $licence->save();
            $buydate = new \DateTime($created_date, new \DateTimeZone('UTC'));
            $new_licence = new Licence();
            $new_licence->user_id = Auth::user()->id;
            $new_licence->order_id = $order_id;
            $new_licence->source = "Dragonsan Studios";
            $new_licence->product_id = $product_id;
            $new_licence->licence_key = 'ExtendCCU-' . $order_id;
            $new_licence->licence_type = $licenceType;
            $new_licence->connections = $licenceConnection;
            $new_licence->maintenance_expire = $buydate->format('Y-m-d H:m:s');
            $new_licence->buy_date = $buydate->format('Y-m-d H:m:s');
            $new_licence->multiserver = 1;
            $new_licence->enabled = 0;
            $new_licence->convert_to = $licence->id;
            $new_licence->enabled = 0;
            $new_licence->save();
            /*$new_licence = new Licence([
                "user_id" => $this->id,
                "order_id" => $order_id,
                "source" => "Dragonsan Studios",
                "product_id" => $product_id,
                "licence_key" => 'ExtendCCU-'.$order_id,
                "licence_type" => $licenceType,
                "connections" => $licenceConnection,
                "maintenance_expire" => $buydate->format('Y-m-d H:m:s') ,
                "buy_date" => $buydate->format('Y-m-d H:m:s'),
                "multiserver" => 1,
                "enabled" => 0,
                "convert_to"=>$licence->id,
            ]);
            $new_licence->enabled=0;
            $new_licence->save();*/
        } else if ($licenceType == "PREM5") {
            $productConfigurations = ProductConfigurations::where('product_id', $product_id)->where('published', 1)->first();
            $dd = new \DateTime($created_date, new \DateTimeZone('UTC'));
            $dd2 = new \DateTime($licence->maintenance_expire, new \DateTimeZone('UTC'));
            if ($dd2 > $dd) {
                $dd = $dd2;
            }
            if ($productConfigurations) {
                $data = $dd->modify('+' . $productConfigurations->maintenance . ' day');
                $ccu = $productConfigurations->concurrent_connections;
            } else {
                $data = $dd->modify('+180 day');
                $ccu = $licenceConnection;
            }
            $licence->maintenance_expire  = $data->format('Y-m-d H:m:s');
            $licence->connections += $ccu;
            $licence->save();
            $buydate = new \DateTime($created_date, new \DateTimeZone('UTC'));
            $new_licence = new Licence();
            $new_licence->user_id = Auth::user()->id;
            $new_licence->order_id = $order_id;
            $new_licence->source = "Dragonsan Studios";
            $new_licence->product_id = $product_id;
            $new_licence->licence_key = 'ExtendCCU-' . $order_id;
            $new_licence->licence_type = $licenceType;
            $new_licence->connections = $ccu;
            $new_licence->maintenance_expire = $buydate->format('Y-m-d H:m:s');
            $new_licence->buy_date = $buydate->format('Y-m-d H:m:s');
            $new_licence->multiserver = 1;
            $new_licence->enabled = 0;
            $new_licence->convert_to = $licence->id;
            $new_licence->enabled = 0;
            $new_licence->save();
            /*$new_licence = new Licence([
                "user_id" => $this->id,
                "order_id" => $order_id,
                "source" => "Dragonsan Studios",
                "product_id" => $product_id,
                "licence_key" => 'ExtendCCU-'.$order_id,
                "licence_type" => $licenceType,
                "connections" => $licenceConnection,
                "maintenance_expire" => $buydate->format('Y-m-d H:m:s') ,
                "buy_date" => $buydate->format('Y-m-d H:m:s'),
                "multiserver" => 1,
                "enabled" => 0,
                "convert_to"=>$licence->id,
            ]);
            $new_licence->enabled=0;
            $new_licence->save();*/
        }
    }

    public function regenerateLicence(Request $request)
    {
        $data = $request->all();
        $licence_id = $data['licence_id'];
        $auth = Auth::user();
        $user_id = $auth->id;
        $licence = Licence::where('id', $licence_id)->first();
        $licence_key_old = $licence->licence_key;
        $requested_date = now();
        $verification_code = md5(uniqid(rand(), true));
        $usersDetails = User::select('email', 'username')->where('id', $licence->user_id)->first();
        $generateLicenceKeyHistory = new GenerateLicenceKeyHistory();
        $generateLicenceKeyHistory->user_id = $user_id;
        $generateLicenceKeyHistory->licence_id = $licence_id;
        $generateLicenceKeyHistory->licence_key_old = $licence_key_old;
        $generateLicenceKeyHistory->requested_date = $requested_date;
        $generateLicenceKeyHistory->verification_code = $verification_code;
        $generateLicenceKeyHistory->save();
        $params = json_encode(array("licence_key" => $licence->licence_key, "verification_code" => $verification_code));
        $curlUrl = "https://atavismonline.com/api/acy_api.php?action=send&nid=80&email=" . $usersDetails->email . "&params=" . $params;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $curlUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['licences' => $generateLicenceKeyHistory]];
        return response($response, 200);
    }

    public function verifyRegenerateLicence(Request $request)
    {
        $data = $request->all();
        $user_id = $data['user_id'];
        $licence_id = $data['licence_id'];
        $verification_code = $data['verification_code'];
        $licence = Licence::where('id', $licence_id)->where('enabled', 1)->first();
        if ($licence) {
            $verificationCodeExists = GenerateLicenceKeyHistory::where("user_id", $user_id)->where("licence_id", $licence_id)->where("verification_code", $verification_code)->where('verification_date', NULL)->count();
            if ($verificationCodeExists) {
                $verificationCodeHistory = GenerateLicenceKeyHistory::where("user_id", $user_id)->where("licence_id", $licence_id)->where("verification_code", $verification_code)->first();
                $generateLicenceKey = $this->generateLicenceKeys("U");
                $licences = Licence::where('id', $licence_id)->update(['licence_key' => $generateLicenceKey]);
                $generateLicenceKeyHistoryId = $verificationCodeHistory->id;
                $updateLicenceKey = GenerateLicenceKeyHistory::where("id", $generateLicenceKeyHistoryId)->update(['licence_key_new' => $generateLicenceKey, 'verification_date' => now()]);
                $message = "Your licence key regenerate successfully";
                $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => $message];
                return response($response, 200);
            } else {
                $message = "Verification code doesn't match in our system";
                $response = ['error' => new \stdClass(), 'status' => 'false', 'message' => $message];
                return response($response, 200);
            }
        } else {
            $message = "Licence does not exists";
            $response = ['error' => new \stdClass(), 'status' => 'false', 'message' => $message];
            return response($response, 200);
        }
    }

    public function getAllRegenerateLicence(Request $request)
    {
        $data = $request->all();
        $licence_id = $data['licence_id'];
        $getAllRegenerateLicence = GenerateLicenceKeyHistory::where('licence_id', $licence_id)->orderBy('id', 'DESC')->get()->toArray();
        if (count($getAllRegenerateLicence) > 0) {
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['getAllRegenerateLicence' => $getAllRegenerateLicence]];
        } else {
            $message = "Licence doesn't exists";
            $response = ['error' => new \stdClass(), 'status' => 'false', 'message' => $message];
        }
        return response($response, 200);
    }
}
