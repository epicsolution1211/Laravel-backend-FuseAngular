<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
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
use App\Notification;
use App\AdvisorSettings;
use DB;
use Carbon\Carbon;
use App\Events\PushNotiMaintaince;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Contract\Database;
use GuzzleHttp\Client;

class AdvisorCron extends Command implements ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'advisor:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(Request $request)
    {
        //$deleteNotifications = Notification::truncate();
        /*$data = ["description" => "you are advised to extend your maintenance plan in '912' days","id" => "1","read" => false, "title" => "Advise"];
        $actionData = array("userId" => 5141, "data" => json_encode($data));
        event(new PushNotiMaintaince($actionData));
        exit("done");*/
        $maintanance_for_voting = Maintenance::select(
            'user_id',
            'number_days',
            'assaign_date'
        )
            ->where('licence_id', '!=', 0)
            ->get();
        foreach ($maintanance_for_voting as $value) {
            $assign_date = Carbon::parse($value->assaign_date);
            $expire_date = $assign_date->addDays($value->number_days);
            if (now()->lt($expire_date) && $assign_date->diffInDays(now()) >= 30 && now()->gte(now()->endOfMonth())) {
                $votingConfig = VotingPointsConfiguration::where('user_id', $value->user_id)->first();
                if ($votingConfig) {
                    $votingConfig->voting_points += 1;
                    $votingConfig->save();
                } else {
                    VotingPointsConfiguration::create([
                        'user_id' => $value->user_id,
                        'voting_points' => 1
                    ]);
                }
            }
        }
        $advisorSettings = self::checkAdvisorSettingsStatus();
        $dataArr = [];
        foreach ($advisorSettings as $key => $value) {
            $advise_description = $advisorSettings[$key]['advise_description'];
            $advise_mail_status = $advisorSettings[$key]['advise_mail_status'];
            $advise_message_status = $advisorSettings[$key]['advise_message_status'];
            $advise_days = $advisorSettings[$key]['advise_days'];
            if ($advisorSettings[$key]['advise_slug'] == 'Unity-Asset-Store-Register') {
                $users = User::select("*")
                    ->doesntHave("licences")
                    ->get();
                if ($advise_message_status) {
                    foreach ($users as $user) {
                        $notification = new Notification();
                        $notification->user_id = $user->id;
                        $notification->advise = $advise_description;
                        $notification->re_notify = 0;
                        $notification->action = "notify";
                        $notification->save();
                        $data = ["description" => $notification->advise, "id" => $notification->id, "read" => $notification->re_notify, "title" => "Advise"];
                        $actionData = array("userId" => $user->id, "data" => json_encode($data));
                        event(new PushNotiMaintaince($actionData));
                        /*$factory = (new Factory)
                        ->withServiceAccount('public/refund-19eb1-firebase-adminsdk-2wwiy-4cb2656929.json')
                        ->withDatabaseUri('https://refund-19eb1.firebaseio.com/');
                        $database = app('firebase.database');
                        $database->getReference('users/'.$user->id)->push($notification);*/
                    }
                }
                if ($advise_mail_status) {
                    foreach ($users as $user) {
                        $usersDetails = User::select('email', 'username')->where('id', $user->id)->first();
                        $params = json_encode(array());
                        $curlUrl = "https://atavismonline.com/api/acy_api.php?action=send&nid=77&email=" . $usersDetails->email . "&params=" . $params;
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $curlUrl);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        $response = curl_exec($ch);
                        curl_close($ch);
                    }
                }
            }
            if ($advisorSettings[$key]['advise_slug'] == 'Old-Licence-Convert') {
                $result = Licence::where('convert_to', '-1')->get();
                if ($advise_message_status) {
                    foreach ($result as $licence) {
                        $notification = new Notification();
                        $notification->user_id = $licence->user_id;
                        $notification->advise = str_replace(["%%licence_key%%"], [$licence->licence_key], $advise_description);
                        $notification->re_notify = 0;
                        $notification->action = "notify";
                        $notification->save();
                        $data = ["description" => $notification->advise, "id" => $notification->id, "read" => $notification->re_notify, "title" => "Advise"];
                        $actionData = array("userId" => $licence->user_id, "data" => json_encode($data));
                        event(new PushNotiMaintaince($actionData));
                        /*$factory = (new Factory)
                        ->withServiceAccount('public/refund-19eb1-firebase-adminsdk-2wwiy-4cb2656929.json')
                        ->withDatabaseUri('https://refund-19eb1.firebaseio.com/');
                        $database = app('firebase.database');
                        $database->getReference('users/'.$licence->user_id)->push($notification);*/
                    }
                }
                if ($advise_mail_status) {
                    $usersDetails = User::select('email', 'username')->where('id', $licence->user_id)->first();
                    $params = json_encode(array("licence_key" => $licence->licence_key, "days" => $licence->diff_days, "year" => $date->format("Y")));
                    $curlUrl = "https://atavismonline.com/api/acy_api.php?action=send&nid=75&email=" . $usersDetails->email . "&params=" . $params;
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $curlUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    $response = curl_exec($ch);
                    curl_close($ch);
                }
            }
            if ($advisorSettings[$key]['advise_slug'] == 'Purchase-Maintenance-Plan') {
                $maintenances = DB::table('josgt_maintenances')
                    ->select('josgt_licences.*', 'josgt_maintenances.*')
                    ->leftjoin('josgt_licences', 'josgt_maintenances.order_id', '=', 'josgt_licences.order_id')
                    ->where(['josgt_maintenances.licence_id' => '0'])
                    ->get();
                if ($advise_message_status) {
                    foreach ($maintenances as $maintenance) {
                        $notification = new Notification();
                        $notification->user_id = $maintenance->user_id;
                        $notification->advise = str_replace(["%%licence_key%%"], [$maintenance->licences_maintenance_key], $advise_description);
                        $notification->re_notify = 0;
                        $notification->action = "notify";
                        $notification->save();
                        $data = ["description" => $notification->advise, "id" => $notification->id, "read" => $notification->re_notify, "title" => "Advise"];
                        $actionData = array("userId" => $maintenance->user_id, "data" => json_encode($data));
                        event(new PushNotiMaintaince($actionData));
                        /*$factory = (new Factory)
                        ->withServiceAccount('public/refund-19eb1-firebase-adminsdk-2wwiy-4cb2656929.json')
                        ->withDatabaseUri('https://refund-19eb1.firebaseio.com/');
                        $database = app('firebase.database');
                        $database->getReference('users/'.$maintenance->user_id)->push($notification);*/
                    }
                }
                if ($advise_mail_status) {
                    foreach ($maintenances as $maintenance) {
                        $usersDetails = User::select('email', 'username')->where('id', $maintenance->user_id)->first();
                        $params = json_encode(array("licence_key" => $maintenance->licences_maintenance_key));
                        $curlUrl = "https://atavismonline.com/api/acy_api.php?action=send&nid=76&email=" . $usersDetails->email . "&params=" . $params;
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $curlUrl);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        $response = curl_exec($ch);
                        curl_close($ch);
                    }
                }
            }
            if ($advisorSettings[$key]['advise_slug'] == 'Maintenance-Discount') {
                $result = Licence::all();
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
                    $licence->diff_days = $licence->diff;
                    if ($licence->mmmDate > $licence->nnnDate) {
                        if ($licence->diff < 14) {
                        } else {
                            if ($advise_days >= $licence->diff_days) {
                                //$licence->bgcolor = "#99FF99";
                                if ($advise_message_status) {
                                    $notification = new Notification();
                                    $notification->user_id = $licence->user_id;
                                    $notification->advise = str_replace(["%%days%%", "%%licence_key%%", "%%year%%"], [$licence->diff_days, $licence->licence_key, $date->format("Y")], $advise_description);
                                    $notification->re_notify = 0;
                                    $notification->action = "feedback";
                                    $notification->save();
                                    $data = ["description" => $notification->advise, "id" => $notification->id, "read" => $notification->re_notify, "title" => "Advise"];
                                    $actionData = array("userId" => $licence->user_id, "data" => json_encode($data));
                                    event(new PushNotiMaintaince($actionData));
                                    /*$factory = (new Factory)
                                    ->withServiceAccount('public/refund-19eb1-firebase-adminsdk-2wwiy-4cb2656929.json')
                                    ->withDatabaseUri('https://refund-19eb1.firebaseio.com/');
                                    $database = app('firebase.database');
                                    $database->getReference('users/'.$licence->user_id)->push($notification);*/
                                }
                                if ($advise_mail_status) {
                                    $usersDetails = User::select('email', 'username')->where('id', $licence->user_id)->first();
                                    $params = json_encode(array("licence_key" => $licence->licence_key, "days" => $licence->diff_days, "year" => $date->format("Y")));
                                    $curlUrl = "https://atavismonline.com/api/acy_api.php?action=send&nid=74&email=" . $usersDetails->email . "&params=" . $params;
                                    $ch = curl_init();
                                    curl_setopt($ch, CURLOPT_URL, $curlUrl);
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                                    $response = curl_exec($ch);
                                    curl_close($ch);
                                }
                            }
                        }
                    }
                }
            }
            if ($advisorSettings[$key]['advise_slug'] == 'Licence-Server-Setup-Help') {
                $licences = Licence::where(now()->subDays(5), 'server_keepalive')->get();
                foreach ($licences as $licence) {
                    $usersDetails = User::select('email', 'username')->where('id', $licence->user_id)->first();
                    if ($usersDetails) {
                        if ($advise_message_status) {
                            $notification = new Notification();
                            $notification->user_id = $licence->user_id;
                            $notification->advise = str_replace(["%%licence_key%%"], [$licence->licence_key], $advise_description);
                            $notification->re_notify = 0;
                            $notification->action = "notify";
                            $notification->save();
                            $data = ["description" => $notification->advise, "id" => $notification->id, "read" => $notification->re_notify, "title" => "Advise"];
                            $actionData = array("userId" => $licence->user_id, "data" => json_encode($data));
                            event(new PushNotiMaintaince($actionData));
                        }

                        if ($advise_mail_status) {
                            $params = json_encode(array("licence_key" => $licence->licence_key));
                            $curlUrl = "https://atavismonline.com/api/acy_api.php?action=send&nid=78&email=" . $usersDetails->email . "&params=" . $params;
                            \Log::info(print_r($curlUrl, true));
                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $curlUrl);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                            $response = curl_exec($ch);
                            curl_close($ch);
                        }
                    }
                }
            }
            if ($advisorSettings[$key]['advise_slug'] == 'CCU-Usage-Close') {
                if ($advise_message_status) {
                    $licences = Licence::all();
                    foreach ($licences as $licence) {
                        if ($licence->connections <= 10 && $licence->licence_key != "") {
                            $usersDetails = User::select('email', 'username')->where('id', $licence->user_id)->first();
                            if (@$usersDetails) {
                                $notification = new Notification();
                                $notification->user_id = $licence->user_id;
                                $notification->advise = str_replace(["%%licence_key%%"], [$licence->licence_key], $advise_description);
                                $notification->re_notify = 0;
                                $notification->action = "notify";
                                $notification->save();
                                $data = ["description" => $notification->advise, "id" => $notification->id, "read" => $notification->re_notify, "title" => "Advise"];
                                $actionData = array("userId" => $licence->user_id, "data" => json_encode($data));
                                event(new PushNotiMaintaince($actionData));
                                /*$factory = (new Factory)
                                ->withServiceAccount('public/refund-19eb1-firebase-adminsdk-2wwiy-4cb2656929.json')
                                ->withDatabaseUri('https://refund-19eb1.firebaseio.com/');
                                $database = app('firebase.database');
                                $database->getReference('users/'.$licence->user_id)->push($notification);*/
                            }
                        }
                    }
                }
                if ($advise_mail_status) {
                    $licences = Licence::all();
                    foreach ($licences as $licence) {
                        if ($licence->connections <= 10 && $licence->licence_key != "") {
                            $usersDetails = User::select('email', 'username')->where('id', $licence->user_id)->first();
                            if (@$usersDetails) {
                                $params = json_encode(array("licence_key" => $licence->licence_key));
                                $curlUrl = "https://atavismonline.com/api/acy_api.php?action=send&nid=78&email=" . $usersDetails->email . "&params=" . $params;
                                \Log::info(print_r($curlUrl, true));
                            }
                        }
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $curlUrl);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        $response = curl_exec($ch);
                        curl_close($ch);
                    }
                }
            }
        }
    }

    public static function checkAdvisorSettingsStatus()
    {
        $AdvisorSettings = "";
        $AdvisorSettings = AdvisorSettings::select('advise_slug', 'advise_description', 'advise_days', 'advise_mail_status', 'advise_message_status')->where('advise_message_status', 1)->orWhere('advise_mail_status', 1)->where('users_id', 1)->get();
        return $AdvisorSettings;
    }

    public static function checkAdvisorSettings($user_id, Request $request)
    {
        $dataArr = array();
        //$advisorSettings = AdvisorSettings::where('users_id',$user_id)->get()->toArray();
        $advisorSettings = AdvisorSettings::where('users_id', 1)->get()->toArray();
        foreach ($advisorSettings as $key => $value) {
            $dataArr[$value['users_id']]['user_id'] = $advisorSettings[$key]['users_id'];
            $dataArr[$value['users_id']]['advise_slug'] = $advisorSettings[$key]['advise_slug'];
            $dataArr[$value['users_id']]['advise_description'] = $advisorSettings[$key]['advise_description'];
            $dataArr[$value['users_id']]['advise_mail_status'] = $advisorSettings[$key]['advise_mail_status'];
            foreach ($dataArr as $k => $v) {
                if ($dataArr[$k]['advise_slug'] == 'Product-Installation-And-Usability' && $dataArr[$k]['advise_mail_status']) {
                    $ProductInstallationAndUsability = self::ProductInstallationAndUsability($dataArr);
                }
                if ($dataArr[$k]['advise_slug'] == 'Maintenance-Discount' && $dataArr[$k]['advise_mail_status']) {
                    $licences = self::getMaintenances($request);
                    \Log::info(print_r($licences, true));
                    //$MaintenanceDiscount = self::MaintenanceDiscount($dataArr);
                }
            }
        }
        return $dataArr;
    }

    public static function ProductInstallationAndUsability($data)
    {
        $users = User::all();
        foreach ($users as $k => $v) {
            $usersDetails = User::find($v['id']);
            $curlUrl = "https://atavismonline.com/api/acy_api.php?action=send&nid=69&email=" . $usersDetails->email;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $curlUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $response = curl_exec($ch);
            curl_close($ch);
        }
    }

    public static function MaintenanceDiscount($data)
    {
        //\Log::info(print_r($data, true));
        //$database->getReference('users')->set(null);
        $users = User::all();
        foreach ($users as $k => $v) {
            //$users = User::find($v['user_id']);
            $userData = self::getLicences($v);
            $advise_description = $data['1']['advise_description'];
        }
    }

    public static function getMaintenances($request)
    {
        // First check if any new licences need to be added
        $maintenances = self::getMaintenanceIds($request);
        // Return the array of group objects
        $result = Maintenance::find($maintenances);


        $maintenancesArr = [];
        foreach ($result as $maintenance) {
            $licence = Licence::find($maintenance->licence_id);
            $maintenancesArr[] = $maintenance;
        }
        //\Log::info(print_r($maintenancesArr, true));
        return $maintenancesArr;
    }

    public static function getMaintenanceIds($request)
    {
        // Fetch from database, if not set
        //if (!isset($request['maintenances'])) {
        $result = DB::table('josgt_maintenances')->select("id")->where("user_id", $request['userId'])->get();


        $maintenances = [];
        foreach ($result as $licence) {
            $maintenances[] = $licence->id;
        }
        //}
        return $maintenances;
    }

    public static function getLicences($request)
    {
        // First check if any new licences need to be added
        //self::checkOrders($request);

        //self::getLicenceIds($request);

        // Return the array of group objects
        $result = Licence::find($request);

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
            $licence->diff_days = $licence->diff;
            if ($licence->mmmDate > $licence->nnnDate) {
                if ($licence->diff < 14) {
                    $licence->bgcolor = "#FFFF99";
                } else {
                    $licence->bgcolor = "#99FF99";
                }
            } else {
                $licence->bgcolor = "#FF9999";
            }
            $licences[] = $licence;
        }
        return $licences;
    }



    /**
     * Checks the users orders and order id's to see if any orders have not yet been converted into licences
     *
     */
    public static function checkOrders($request)
    {
        // Loop through each order
        $orders = self::getOrders($request);
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
                    if (Licence::getLicenceType((int)$order_product->product_id) != "") {
                        $licence_count = $this->getLicenceCountForOrderProduct($order->id, $order_product->product_id);
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

    public static function getLicenceIds($request)
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



    public static function getOrders($request)
    {
        //self::getOrderIds($request);
        $result = Order::find($request['userId']);

        $orders = [];
        foreach ($result as $order) {
            $orders[$order->id] = $order;
        }
        return $orders;
    }

    public static function getOrderIds($request)
    {
        // Fetch from database, if not set
        if (!isset($request)) {
            //$result = DB::table('josgt_eshop_orders')->select("id")->where("customer_id", $request->get('userId'))->get()->toArray();
            $result = DB::table('josgt_eshop_orders')->select("id")->where("customer_id", $request->userId)->get();
            $orders = [];
            foreach ($result as $order) {
                $orders[] = $order->id;
            }
        }
        return $orders;
    }
}
