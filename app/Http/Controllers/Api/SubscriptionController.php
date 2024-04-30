<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Release;
use App\Licence;
use App\Download;
use App\DownloadLogs;
use App\User;
use App\LicenceOrder;
use App\LicenceXsollaProduct;
use App\SubscriptionCancelReason;
use App\CancelReason;
use Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use DB;
use Illuminate\Support\Facades\Log;
use Xsolla\SDK\API\XsollaClient;
use Xsolla\SDK\API\PaymentUI\TokenRequest;

class SubscriptionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success'];
        return response($response, 200);
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

    /*public function addArchive(Request $request){
      $data = $request->all();
      $SubscriptionCancelReason = SubscriptionCancelReason::find($data['reason_id']);
      $auth = Auth::user();
      $authId = $auth->id;
      $addSubscriptionReasonArchive = new SubscriptionCancelReason();
      $addSubscriptionReasonArchive->user_id = $authId;
      $addSubscriptionReasonArchive->archive_id = $data['reason_id'];
      $addSubscriptionReasonArchive->licence_id = $SubscriptionCancelReason->licence_id;
      $addSubscriptionReasonArchive->flag = 1;
      $addSubscriptionReasonArchive->reason = $data['reason'];
      $addSubscriptionReasonArchive->save();
      $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success'];
      return response($response, 200);
    }*/

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = $request->all();
        $auth = \Auth::user();
        $data = $request->all();
        $licence_id = $data['licence_id'];
        $licence = Licence::where('id', $data['licence_id'])->first();

        $xsollaClient = XsollaClient::factory(array(
            'merchant_id' => 43963,
            'api_key' => 'dc8y4BVLBNAZH4g0'
        ));

        $prod = "";
        $prize = 39;
        $planId = 'AtLease20CCU';
        $group = "AtLeasLic";
        $connections = 100;
        $licence_type = 'LEASE20';
        if (strpos($licence->licence_type, 'LEASE') !== false) {
            if ($licence->licence_type == "Lease20") {
                $planId = 'AtLease20CCU';
                $xSollaPlanId = 233201;
                $prize = 29;
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
                $group = "AtLeasLic";
                $prize = 78;
                $connections = 5000;
            }
            $responseProductList = $xsollaClient->ListSubscriptionProducts(array(
                'project_id' => 158935
            ));
            $product_id = "";
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
                $product =   new LicenceXsollaProduct([
                    "id" => $product_id,
                    "name" => $licence->licence_key,
                    "group_id" => $group,
                    'project' => 158935,
                ]);
                $product->save();
            }

            $dd = new \DateTime('NOW', new \DateTimeZone('UTC'));
            //$data = $dd->modify('+180 day');
            $token  = self::generateOrderToken();
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

            Log::alert($order);
            $tokenRequest = new TokenRequest(158935, $auth->email);
            $tokenRequest->setUserEmail($auth->email)
                ->setExternalPaymentId($token)
                ->setUserName($auth->username);
            // ->setPurchase(is_int($prize) ? $prize : floatval($prize), "USD");
            $tokenRequest->setSandboxMode(true);

            $dataRequest = $tokenRequest->toArray();
            $dataRequest['purchase']['description']['value'] = 'Select Atavism subscription plan' . $licence->licence_key;
            $dataRequest['settings']['return_url'] = 'https://devapanel.atavismonline.com/licences';
            $dataRequest['purchase']['subscription']['product_id'] = "" . $product_id;
            $dataRequest['purchase']['subscription']['available_plans'] = array('zoFvotBq', 'CQ6GNlxL');
            $xsollaClient = XsollaClient::factory(array(
                'merchant_id' => 43963,
                'api_key' => 'dc8y4BVLBNAZH4g0'
            ));
            $parsedResponse = $xsollaClient->CreatePaymentUIToken(array('request' => $dataRequest));
            $xsollaToken = $parsedResponse['token'];

            $paymentRedirectUrl = "https://sandbox-secure.xsolla.com/paystation3/desktop/subscription/?access_token=" . $xsollaToken;
            // $paymentRedirectUrl = "https://sandbox-secure.xsolla.com/paystation4/?token=" . $xsollaToken;
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['licence' => $licence, 'xsollapayment' => $paymentRedirectUrl]];
            return response($response, 200);
        }
    }

    public function addArchive(Request $request)
    {
        $data = $request->all();
        $auth = Auth::user();
        $authId = $auth->id;
        $SubscriptionCancelReasonId = CancelReason::find($data['reason_id']);
        $SubscriptionCancelReasonId->published = 0;
        $SubscriptionCancelReasonId->save();
        $addSubscriptionReasonArchive = new CancelReason();
        $addSubscriptionReasonArchive->user_id = $authId;
        $addSubscriptionReasonArchive->archive_id = $SubscriptionCancelReasonId->archive_id;
        $addSubscriptionReasonArchive->licence_id = "";
        $addSubscriptionReasonArchive->flag = 1;
        $addSubscriptionReasonArchive->published = 1;
        $addSubscriptionReasonArchive->reason = $data['reason'];
        $addSubscriptionReasonArchive->save();
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success'];
        return response($response, 200);
    }

    public function getSubscriptionCancelReasons(Request $request)
    {
        $data = $request->all();
        $auth = Auth::user();
        /*if($auth->role_id == 1){
      $SubscriptionCancelReason = SubscriptionCancelReason::select('*')
          ->where("flag",0)
          ->orderBy("id","DESC")
          ->paginate($request->get('paginate'));

          $SubscriptionCancelReasonDetails = $SubscriptionCancelReason->map(function($SubscriptionCancelReasons){
          return collect([
            'id' => $SubscriptionCancelReasons->id,
            'reason' => $SubscriptionCancelReasons->reason
          ]);
      });
    }else if($data['historyId'] != ""){
      $SubscriptionCancelReason = SubscriptionCancelReason::select('*')
          ->where("flag",1)
          ->where("archive_id",$data['historyId'])
          ->orderBy("id","DESC")
          ->paginate($request->get('paginate'));

          $SubscriptionCancelReasonDetails = $SubscriptionCancelReason->map(function($SubscriptionCancelReasons){
          return collect([
            'id' => $SubscriptionCancelReasons->id,
            'reason' => $SubscriptionCancelReasons->reason
          ]);
      });
    }else{
      $SubscriptionCancelReason = SubscriptionCancelReason::select('*')
          ->where("flag",0)
          ->where("user_id",$auth->id)
          ->orderBy("id","DESC")
          ->paginate($request->get('paginate'));

          $SubscriptionCancelReasonDetails = $SubscriptionCancelReason->map(function($SubscriptionCancelReasons){
          return collect([
            'id' => $SubscriptionCancelReasons->id,
            'reason' => $SubscriptionCancelReasons->reason
          ]);
      });
    }*/
        if ($data['historyId'] != "") {
            $SubscriptionCancelReasonId = CancelReason::find($data['historyId']);
            $SubscriptionCancelReason = CancelReason::select("*")
                ->where("published", 0)
                ->where("archive_id", $SubscriptionCancelReasonId->archive_id)
                ->orderBy("id", "DESC")
                ->paginate($request->get('paginate'));

            $SubscriptionCancelReasonDetails = $SubscriptionCancelReason->map(function ($SubscriptionCancelReasons) {
                return collect([
                    'id' => $SubscriptionCancelReasons->id,
                    'reason' => $SubscriptionCancelReasons->reason
                ]);
            });
        } else {
            $SubscriptionCancelReason = CancelReason::select('*')
                ->where("published", 1)
                ->orderBy("id", "DESC")
                ->paginate($request->get('paginate'));

            $SubscriptionCancelReasonDetails = $SubscriptionCancelReason->map(function ($SubscriptionCancelReasons) {
                return collect([
                    'id' => $SubscriptionCancelReasons->id,
                    'reason' => $SubscriptionCancelReasons->reason,
                    'archive_id' => $SubscriptionCancelReasons->archive_id
                ]);
            });
        }
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['current_page' => $SubscriptionCancelReason->currentPage(), 'per_page' => $SubscriptionCancelReason->perPage(), 'total' => $SubscriptionCancelReason->total(), 'subscriptionCancelReasons' => $SubscriptionCancelReasonDetails]];
        return response($response, 200);
    }

    public function getSubscriptionCancelReasonsByUser(Request $request)
    {
        $data = $request->all();
        $auth = Auth::user();
        $role_id = $auth->role_id;
        $authId = $auth->id;
        if ($role_id == 3) {
            $SubscriptionCancelReasonCount = SubscriptionCancelReason::join('josgt_licences', 'subscription_cancel_reasons.licence_id', '=', 'josgt_licences.id')
                ->select("subscription_cancel_reasons.id as subscription_cancel_reasons_id", "subscription_cancel_reasons.licence_id", "subscription_cancel_reasons.user_id", "subscription_cancel_reasons.reason", "subscription_cancel_reasons.archive_id", "josgt_licences.id", "josgt_licences.licence_key", "josgt_licences.licence_key", "subscription_cancel_reasons.reason", DB::raw('count(*) as total_count'), "subscription_cancel_reasons.reason_text")
                ->where("subscription_cancel_reasons.role_id", $role_id)
                ->where("subscription_cancel_reasons.user_id", $authId)
                ->groupBy("subscription_cancel_reasons.reason")
                ->groupBy("subscription_cancel_reasons.reason_text")
                //->orderBy("subscription_cancel_reasons.archive_id","ASC")
                ->orderBy("subscription_cancel_reasons.id", "DESC")
                ->get()->count();
            $SubscriptionCancelReason = SubscriptionCancelReason::join('josgt_licences', 'subscription_cancel_reasons.licence_id', '=', 'josgt_licences.id')
                ->select("subscription_cancel_reasons.id as subscription_cancel_reasons_id", "subscription_cancel_reasons.licence_id", "subscription_cancel_reasons.user_id", "subscription_cancel_reasons.reason", "subscription_cancel_reasons.archive_id", "josgt_licences.id", "josgt_licences.licence_key", "josgt_licences.licence_key", "subscription_cancel_reasons.reason", DB::raw('count(*) as total_count'), "subscription_cancel_reasons.reason_text")
                ->where("subscription_cancel_reasons.role_id", $role_id)
                ->where("subscription_cancel_reasons.user_id", $authId)
                ->groupBy("subscription_cancel_reasons.reason")
                ->groupBy("subscription_cancel_reasons.reason_text")
                //->orderBy("subscription_cancel_reasons.archive_id","ASC")
                ->orderBy("subscription_cancel_reasons.id", "DESC")
                ->paginate($request->get('paginate'));

            $SubscriptionCancelReasonDetails = $SubscriptionCancelReason->map(function ($SubscriptionCancelReasons) {
                return collect([
                    'id' => $SubscriptionCancelReasons->subscription_cancel_reasons_id,
                    'reason' => ($SubscriptionCancelReasons->reason_text ? $SubscriptionCancelReasons->reason_text : $SubscriptionCancelReasons->reason),
                    'archive_id' => $SubscriptionCancelReasons->archive_id,
                    'licence_key' => $SubscriptionCancelReasons->licence_key,
                    'total_count' => $SubscriptionCancelReasons->total_count
                ]);
            });
        } else if ($role_id == 2) {
            $SubscriptionCancelReasonCount = SubscriptionCancelReason::join('josgt_licences', 'subscription_cancel_reasons.licence_id', '=', 'josgt_licences.id')
                ->select("subscription_cancel_reasons.id as subscription_cancel_reasons_id", "subscription_cancel_reasons.licence_id", "subscription_cancel_reasons.user_id", "subscription_cancel_reasons.reason", "subscription_cancel_reasons.archive_id", "josgt_licences.id", "josgt_licences.licence_key", "josgt_licences.licence_key", "subscription_cancel_reasons.reason", DB::raw('count(*) as total_count'), "subscription_cancel_reasons.reason_text", "subscription_cancel_reasons.role_id")
                //->whereIn("subscription_cancel_reasons.role_id",[3,2])
                ->groupBy("subscription_cancel_reasons.reason")
                ->groupBy("subscription_cancel_reasons.reason_text")
                //->orderBy("subscription_cancel_reasons.archive_id","ASC")
                ->orderBy("subscription_cancel_reasons.id", "DESC")
                ->get()->count();
            $SubscriptionCancelReason = SubscriptionCancelReason::join('josgt_licences', 'subscription_cancel_reasons.licence_id', '=', 'josgt_licences.id')
                ->select("subscription_cancel_reasons.id as subscription_cancel_reasons_id", "subscription_cancel_reasons.licence_id", "subscription_cancel_reasons.user_id", "subscription_cancel_reasons.reason", "subscription_cancel_reasons.archive_id", "josgt_licences.id", "josgt_licences.licence_key", "josgt_licences.licence_key", "subscription_cancel_reasons.reason", DB::raw('count(*) as total_count'), "subscription_cancel_reasons.reason_text", "subscription_cancel_reasons.role_id")
                //->whereIn("subscription_cancel_reasons.role_id",[3,2])
                ->groupBy("subscription_cancel_reasons.reason")
                ->groupBy("subscription_cancel_reasons.reason_text")
                //->orderBy("subscription_cancel_reasons.archive_id","ASC")
                ->orderBy("subscription_cancel_reasons.id", "DESC")
                ->paginate($request->get('paginate'));

            $SubscriptionCancelReasonDetails = $SubscriptionCancelReason->map(function ($SubscriptionCancelReasons) {
                return collect([
                    'id' => $SubscriptionCancelReasons->subscription_cancel_reasons_id,
                    'reason' => ($SubscriptionCancelReasons->reason_text ? $SubscriptionCancelReasons->reason_text : $SubscriptionCancelReasons->reason),
                    'archive_id' => $SubscriptionCancelReasons->archive_id,
                    'licence_key' => $SubscriptionCancelReasons->licence_key,
                    'total_count' => $SubscriptionCancelReasons->total_count
                ]);
            });
        } else {
            $SubscriptionCancelReasonCount = SubscriptionCancelReason::join('josgt_licences', 'subscription_cancel_reasons.licence_id', '=', 'josgt_licences.id')
                ->select("subscription_cancel_reasons.id as subscription_cancel_reasons_id", "subscription_cancel_reasons.licence_id", "subscription_cancel_reasons.user_id", "subscription_cancel_reasons.reason", "subscription_cancel_reasons.archive_id", "josgt_licences.id", "josgt_licences.licence_key", "josgt_licences.licence_key", "subscription_cancel_reasons.reason", DB::raw('count(*) as total_count'), "subscription_cancel_reasons.reason_text")
                ->groupBy("subscription_cancel_reasons.reason")
                ->groupBy("subscription_cancel_reasons.reason_text")
                //->orderBy("subscription_cancel_reasons.archive_id","ASC")
                ->orderBy("subscription_cancel_reasons.id", "DESC")
                ->get()->count();
            $SubscriptionCancelReason = SubscriptionCancelReason::join('josgt_licences', 'subscription_cancel_reasons.licence_id', '=', 'josgt_licences.id')
                ->select("subscription_cancel_reasons.id as subscription_cancel_reasons_id", "subscription_cancel_reasons.licence_id", "subscription_cancel_reasons.user_id", "subscription_cancel_reasons.reason", "subscription_cancel_reasons.archive_id", "josgt_licences.id", "josgt_licences.licence_key", "josgt_licences.licence_key", "subscription_cancel_reasons.reason", DB::raw('count(*) as total_count'), "subscription_cancel_reasons.reason_text")
                ->groupBy("subscription_cancel_reasons.reason")
                ->groupBy("subscription_cancel_reasons.reason_text")
                //->orderBy("subscription_cancel_reasons.archive_id","ASC")
                ->orderBy("subscription_cancel_reasons.id", "DESC")
                ->paginate($request->get('paginate'));

            $SubscriptionCancelReasonDetails = $SubscriptionCancelReason->map(function ($SubscriptionCancelReasons) {
                return collect([
                    'id' => $SubscriptionCancelReasons->subscription_cancel_reasons_id,
                    'reason' => ($SubscriptionCancelReasons->reason_text ? $SubscriptionCancelReasons->reason_text : $SubscriptionCancelReasons->reason),
                    'archive_id' => $SubscriptionCancelReasons->archive_id,
                    'licence_key' => $SubscriptionCancelReasons->licence_key,
                    'total_count' => $SubscriptionCancelReasons->total_count
                ]);
            });
        }
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['current_page' => $SubscriptionCancelReason->currentPage(), 'per_page' => $SubscriptionCancelReason->perPage(), 'total' => $SubscriptionCancelReason->total(), 'subscriptionCancelReasons' => $SubscriptionCancelReasonDetails, 'subscriptionCancelReasonsCount' => $SubscriptionCancelReasonCount]];
        return response($response, 200);
    }

    public function getFeedbackByReasons(Request $request)
    {
        $data = $request->all();
        $auth = Auth::user();
        $id = $data['id'];
        $type = $data['type'];
        $userId = $data['userId'];
        $roleId = $auth->role_id;
        $from = $data['from'];
        $to = $data['to'];
        $fromArr = explode("GMT", $from);
        $data_from_0 = strtotime($fromArr[0]);
        $data_from = date("Y-m-d H:i:s", $data_from_0);
        $toArr = explode("GMT", $to);
        $to_from_0 = strtotime($toArr[0]);
        $data_to = date("Y-m-d H:i:s", $to_from_0);
        $SubscriptionCancelReasonCount = SubscriptionCancelReason::join('josgt_licences', 'subscription_cancel_reasons.licence_id', '=', 'josgt_licences.id')
            ->join('josgt_users', 'subscription_cancel_reasons.user_id', '=', 'josgt_users.id')
            ->select("subscription_cancel_reasons.id as subscription_cancel_reasons_id", "subscription_cancel_reasons.licence_id", "subscription_cancel_reasons.licence_id", "subscription_cancel_reasons.user_id", "subscription_cancel_reasons.reason_text", "subscription_cancel_reasons.reason", "subscription_cancel_reasons.archive_id", "josgt_licences.id", "josgt_licences.licence_key", "josgt_licences.licence_key", "subscription_cancel_reasons.reason", "josgt_users.username")
            ->when(isset($type) && $type == 'multiple', function ($q2) use ($id, $request, $userId) {
                $subscriptionCancelReasonText = SubscriptionCancelReason::find($id);
                //$q2->where("subscription_cancel_reasons.user_id",$userId);
                $q2->where("subscription_cancel_reasons.archive_id", $request->get('archieve_id'));
                if ($subscriptionCancelReasonText->reason == "Other" && $userId != "") {
                    $q2->where("subscription_cancel_reasons.reason", "LIKE", "%" . "Other" . "%");
                } else if ($subscriptionCancelReasonText->reason == "Other" && $userId = "") {
                    $q2->where("subscription_cancel_reasons.reason_text", "LIKE", "%" . $subscriptionCancelReasonText->reason_text . "%");
                } else {
                    $q2->where("subscription_cancel_reasons.reason", "LIKE", "%" . $subscriptionCancelReasonText->reason . "%");
                }
                return $q2;
            })
            ->when(isset($type) && $type == 'single', function ($q2) use ($id, $request) {
                $subscriptionCancelReasonText = SubscriptionCancelReason::find($id);
                //$q2->where("subscription_cancel_reasons.user_id",$userId);
                $q2->where("subscription_cancel_reasons.id", $id);
                $q2->where("subscription_cancel_reasons.archive_id", $request->get('archieve_id'));
                if ($subscriptionCancelReasonText->reason == "Other") {
                    $q2->where("subscription_cancel_reasons.reason_text", "LIKE", "%" . $subscriptionCancelReasonText->reason_text . "%");
                } else {
                    $q2->where("subscription_cancel_reasons.reason", "LIKE", "%" . $subscriptionCancelReasonText->reason . "%");
                }
                return $q2;
            })
            ->when(isset($userId) && $roleId == 3, function ($q2) use ($userId) {
                return $q2->where("subscription_cancel_reasons.user_id", $userId);
            })
            ->when(isset($userId) && $userId != "" && $roleId != 2, function ($q2) use ($userId) {
                return $q2->where("subscription_cancel_reasons.user_id", $userId);
            })
            ->when(isset($from) && isset($to), function ($q2) use ($request) {
                $from = $request->get('from');
                $to = $request->get('to');
                $fromArr = explode("GMT", $from);
                $data_from_0 = strtotime($fromArr[0]);
                //$data_from = date("Y-m-d H:i:s",$data_from_0);
                $data_from = date("Y-m-d", $data_from_0);
                $toArr = explode("GMT", $to);
                $to_from_0 = strtotime($toArr[0]);
                //$data_to = date("Y-m-d H:i:s",$to_from_0);
                $data_to = date("Y-m-d", $to_from_0);
                $q2->where("subscription_cancel_reasons.created_at", ">", $data_from);
                $q2->where("subscription_cancel_reasons.created_at", "<", $data_to);
                return $q2;
            })
            //->where("subscription_cancel_reasons.id",$id)
            ->where("subscription_cancel_reasons.archive_id", $data['archieve_id'])
            ->orderBy("subscription_cancel_reasons.archive_id", "ASC")
            ->get()->count();
        $SubscriptionCancelReason = SubscriptionCancelReason::join('josgt_licences', 'subscription_cancel_reasons.licence_id', '=', 'josgt_licences.id')
            ->join('josgt_users', 'subscription_cancel_reasons.user_id', '=', 'josgt_users.id')
            ->select("subscription_cancel_reasons.id as subscription_cancel_reasons_id", "subscription_cancel_reasons.licence_id", "subscription_cancel_reasons.licence_id", "subscription_cancel_reasons.user_id", "subscription_cancel_reasons.reason_text", "subscription_cancel_reasons.reason", "subscription_cancel_reasons.archive_id", "josgt_licences.id", "josgt_licences.licence_key", "josgt_licences.licence_key", "subscription_cancel_reasons.reason", "josgt_users.username")
            ->when(isset($type) && $type == 'multiple', function ($q2) use ($id, $request, $userId) {
                $subscriptionCancelReasonText = SubscriptionCancelReason::find($id);
                //$q2->where("subscription_cancel_reasons.user_id",$userId);
                $q2->where("subscription_cancel_reasons.archive_id", $request->get('archieve_id'));
                if ($subscriptionCancelReasonText->reason == "Other" && $userId != "") {
                    $q2->where("subscription_cancel_reasons.reason", "LIKE", "%" . "Other" . "%");
                } else if ($subscriptionCancelReasonText->reason == "Other" && $userId = "") {
                    $q2->where("subscription_cancel_reasons.reason_text", "LIKE", "%" . $subscriptionCancelReasonText->reason_text . "%");
                } else {
                    $q2->where("subscription_cancel_reasons.reason", "LIKE", "%" . $subscriptionCancelReasonText->reason . "%");
                }
                return $q2;
            })
            ->when(isset($type) && $type == 'single', function ($q2) use ($id, $request) {
                $subscriptionCancelReasonText = SubscriptionCancelReason::find($id);
                //$q2->where("subscription_cancel_reasons.user_id",$userId);
                $q2->where("subscription_cancel_reasons.id", $id);
                $q2->where("subscription_cancel_reasons.archive_id", $request->get('archieve_id'));
                if ($subscriptionCancelReasonText->reason == "Other") {
                    $q2->where("subscription_cancel_reasons.reason_text", "LIKE", "%" . $subscriptionCancelReasonText->reason_text . "%");
                } else {
                    $q2->where("subscription_cancel_reasons.reason", "LIKE", "%" . $subscriptionCancelReasonText->reason . "%");
                }
                return $q2;
            })
            ->when(isset($userId) && $roleId == 3, function ($q2) use ($userId) {
                return $q2->where("subscription_cancel_reasons.user_id", $userId);
            })
            ->when(isset($userId) && $userId != "" && $roleId == 2, function ($q2) use ($userId) {
                return $q2->where("subscription_cancel_reasons.user_id", $userId);
            })
            ->when(isset($from) && isset($to), function ($q2) use ($request) {
                $from = $request->get('from');
                $to = $request->get('to');
                $fromArr = explode("GMT", $from);
                $data_from_0 = strtotime($fromArr[0]);
                //$data_from = date("Y-m-d H:i:s",$data_from_0);
                $data_from = date("Y-m-d H:i:s", $data_from_0);
                $toArr = explode("GMT", $to);
                $to_from_0 = strtotime($toArr[0]);
                //$data_to = date("Y-m-d H:i:s",$to_from_0);
                $data_to = date("Y-m-d H:i:s", $to_from_0);
                $q2->where("subscription_cancel_reasons.created_at", ">", $data_from);
                $q2->where("subscription_cancel_reasons.created_at", "<", $data_to);
                return $q2;
            })
            //->where("subscription_cancel_reasons.id",$id)
            ->where("subscription_cancel_reasons.archive_id", $data['archieve_id'])
            ->orderBy("subscription_cancel_reasons.archive_id", "ASC")
            ->paginate($request->get('paginate'));
        $SubscriptionCancelReasonDetails = $SubscriptionCancelReason->map(function ($SubscriptionCancelReasons) {
            return collect([
                'id' => $SubscriptionCancelReasons->subscription_cancel_reasons_id,
                'reason' => ($SubscriptionCancelReasons->reason_text ? $SubscriptionCancelReasons->reason_text : $SubscriptionCancelReasons->reason),
                'archive_id' => $SubscriptionCancelReasons->archive_id,
                'licence_key' => $SubscriptionCancelReasons->licence_key,
                'username' => $SubscriptionCancelReasons->username,
            ]);
        });
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['current_page' => $SubscriptionCancelReason->currentPage(), 'per_page' => $SubscriptionCancelReason->perPage(), 'total' => $SubscriptionCancelReason->total(), 'subscriptionCancelReasons' => $SubscriptionCancelReasonDetails, 'subscriptionCancelReasonsCount' => $SubscriptionCancelReasonCount, 'roleId' => $roleId]];
        return response($response, 200);
    }

    public function getSubscriptionCancelReasonByUserData(Request $request)
    {
        $data = $request->all();
        $userId = $data['users'];
        $from = $data['from'];
        $to = $data['to'];
        $SubscriptionCancelReasonCount = SubscriptionCancelReason::join('josgt_licences', 'subscription_cancel_reasons.licence_id', '=', 'josgt_licences.id')
            ->select("subscription_cancel_reasons.id as subscription_cancel_reasons_id", "subscription_cancel_reasons.licence_id", "subscription_cancel_reasons.user_id", "subscription_cancel_reasons.reason_text", "subscription_cancel_reasons.reason", "subscription_cancel_reasons.archive_id", "josgt_licences.id", "josgt_licences.licence_key", "josgt_licences.licence_key", "subscription_cancel_reasons.reason", DB::raw('count(*) as total_count'))
            ->when(isset($data['users']) && $data['users'] != "undefined", function ($q2) use ($request) {
                $q2->groupBy("subscription_cancel_reasons.reason");
                $q2->groupBy("subscription_cancel_reasons.reason_text");
                return $q2->where("subscription_cancel_reasons.user_id", $request
                    ->get('users'));
            })
            ->when(isset($from) && isset($to), function ($q2) use ($request) {
                $from = $request->get('from');
                $to = $request->get('to');
                $fromArr = explode("GMT", $from);
                $data_from_0 = strtotime($fromArr[0]);
                //$data_from = date("Y-m-d H:i:s",$data_from_0);
                $data_from = date("Y-m-d H:i:s", $data_from_0);
                $toArr = explode("GMT", $to);
                $to_from_0 = strtotime($toArr[0]);
                //$data_to = date("Y-m-d H:i:s",$to_from_0);
                $data_to = date("Y-m-d H:i:s", $to_from_0);
                $q2->groupBy("subscription_cancel_reasons.reason");
                $q2->groupBy("subscription_cancel_reasons.reason_text");
                $q2->where("subscription_cancel_reasons.created_at", ">", $data_from);
                $q2->where("subscription_cancel_reasons.created_at", "<", $data_to);
                return $q2;
            })
            ->groupBy("subscription_cancel_reasons.reason")
            ->groupBy("subscription_cancel_reasons.reason_text")
            ->orderBy("subscription_cancel_reasons.archive_id", "ASC")
            ->get()->count();
        $SubscriptionCancelReason = SubscriptionCancelReason::join('josgt_licences', 'subscription_cancel_reasons.licence_id', '=', 'josgt_licences.id')
            ->select("subscription_cancel_reasons.id as subscription_cancel_reasons_id", "subscription_cancel_reasons.licence_id", "subscription_cancel_reasons.user_id", "subscription_cancel_reasons.reason_text", "subscription_cancel_reasons.reason", "subscription_cancel_reasons.archive_id", "josgt_licences.id", "josgt_licences.licence_key", "josgt_licences.licence_key", "subscription_cancel_reasons.reason", DB::raw('count(*) as total_count'))
            ->when(isset($data['users']) && $data['users'] != "undefined", function ($q2) use ($request) {
                $q2->groupBy("subscription_cancel_reasons.reason");
                $q2->groupBy("subscription_cancel_reasons.reason_text");
                return $q2->where("subscription_cancel_reasons.user_id", $request
                    ->get('users'));
            })
            ->when(isset($from) && isset($to), function ($q2) use ($request) {
                $from = $request->get('from');
                $to = $request->get('to');
                $fromArr = explode("GMT", $from);
                $data_from_0 = strtotime($fromArr[0]);
                //$data_from = date("Y-m-d H:i:s",$data_from_0);
                $data_from = date("Y-m-d H:i:s", $data_from_0);
                $toArr = explode("GMT", $to);
                $to_from_0 = strtotime($toArr[0]);
                //$data_to = date("Y-m-d H:i:s",$to_from_0);
                $data_to = date("Y-m-d H:i:s", $to_from_0);
                $q2->groupBy("subscription_cancel_reasons.reason");
                $q2->groupBy("subscription_cancel_reasons.reason_text");
                $q2->where("subscription_cancel_reasons.created_at", ">", $data_from);
                $q2->where("subscription_cancel_reasons.created_at", "<", $data_to);
                return $q2;
            })
            ->groupBy("subscription_cancel_reasons.reason")
            ->groupBy("subscription_cancel_reasons.reason_text")
            ->orderBy("subscription_cancel_reasons.archive_id", "ASC")
            ->paginate($request->get('paginate'));
        $SubscriptionCancelReasonDetails = $SubscriptionCancelReason->map(function ($SubscriptionCancelReasons) {
            return collect([
                'id' => $SubscriptionCancelReasons->subscription_cancel_reasons_id,
                'reason' => ($SubscriptionCancelReasons->reason_text ? $SubscriptionCancelReasons->reason_text : $SubscriptionCancelReasons->reason),
                'archive_id' => $SubscriptionCancelReasons->archive_id,
                'licence_key' => $SubscriptionCancelReasons->licence_key,
                'total_count' => $SubscriptionCancelReasons->total_count
            ]);
        });
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['current_page' => $SubscriptionCancelReason->currentPage(), 'per_page' => $SubscriptionCancelReason->perPage(), 'total' => $SubscriptionCancelReason->total(), 'subscriptionCancelReasons' => $SubscriptionCancelReasonDetails, 'subscriptionCancelReasonsCount' => $SubscriptionCancelReasonCount]];
        return response($response, 200);
    }

    public static function generateOrderToken($gen = null)
    {
        do {
            $gen = md5(uniqid(mt_rand(), false));
        } while (LicenceOrder::where('order_id', $gen)->first());
        return $gen;
    }
}
