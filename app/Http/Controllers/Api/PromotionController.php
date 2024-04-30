<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Promotion;
use App\PromotionUsers;
use App\PromotionApplied;
use App\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Auth;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Contract\Database;
use App\Events\PushNotiMaintaince;
use App\Notification;

class PromotionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $auth_user = \Auth::user();
        //$users = User::with('licences','discord')
        $promotions = Promotion::select('promotions.*')
            ->when($request->has('search') && $request->get('search') != '', function ($q) use ($request) {
                $q->where(function ($query) use ($request) {
                    return $query->where('josgt_users.email', 'LIKE', '%' . $request->get('search') . '%')
                        ->orWhere('josgt_users.username', 'LIKE', '%' . $request->get('search') . '%')
                        ->orWhere('josgt_licences.licence_key', 'LIKE', '%' . $request->get('search') . '%')
                        ->orWhere('josgt_discord.name', 'LIKE', '%' . $request->get('search') . '%')
                        ->orWhere('josgt_users.registerDate', 'LIKE', '%' . $request->get('search') . '%')
                        ->orWhere('josgt_users.lastvisitDate', 'LIKE', '%' . $request->get('search') . '%');
                });
            })
            ->orderBy('promotions.id', 'DESC')
            ->paginate($request->get('paginate'));

        $promotion = $promotions->map(function ($promotion) {
            $promotion_type = $promotion->promotion_type;
            if ($promotion_type == 0) {
                $promotion_type_name = "Fixed";
            } elseif ($promotion_type == 1) {
                $promotion_type_name = "Percentage";
            } else {
                $promotion_type_name = "Coupon Code";
            }
            return collect([
                'id' => $promotion->id,
                'user_id' => $promotion->user_id,
                'role_id' => $promotion->role_id,
                'promotion_type' => $promotion_type_name,
                'promotion_name' => $promotion->promotion_name,
                'promotion_slug' => $promotion->promotion_slug,
                'price' => $promotion->price,
                'percentage' => $promotion->percentage,
                'promotion_status' => $promotion->promotion_status,
                'start_date' => $promotion->start_date,
                'end_date' => $promotion->end_date
            ]);
        });

        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['current_page' => $promotions->currentPage(), 'per_page' => $promotions->perPage(), 'total' => $promotions->total(), 'promotions' => $promotion]];
        return response($response, 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function assignPromotion(Request $request)
    {
        $auth = Auth::user();
        $data = $request->all();
        $promotion_id = $data['promotion_id'];
        $users = $data['users'];
        $usersArr = explode(',', $users);
        $notifyFlag = explode(",",$data['notify_flag']); // 0 for notification and 1 for email
        $promotionUsers = PromotionUsers::where('promotions_id', $promotion_id)->delete();
        $usersDetails = User::select('email')->whereIn('id', $usersArr)->get();
        foreach ($usersArr as $key => $value) {
            //print_r($value);
            if($value != ""){
                $promotionUsers = new PromotionUsers();
                $promotionUsers->user_id = $usersArr[$key];
                $promotionUsers->role_id = $auth->role_id;
                $promotionUsers->promotions_id = $promotion_id;
                $promotionUsers->save();
                /////////////////////////////////
                $user_id = $usersArr[$key];
                $notifyFlagMessage = 0;
                $notifyFlagNotification = 0;
                if(@$notifyFlag[0] == 0){
                    $notifyFlagMessage = 1;
                    $notifyFlagNotification = 1;
                }
                if(@$notifyFlag[0] == 1){
                    $notifyFlagNotification = 1;
                    $notifyFlagMessage = 1;
                }
                if(@$notifyFlag[0] == 0 && @$notifyFlag[1] == 1){
                    $notifyFlagNotification = 1;
                    $notifyFlagMessage = 1;
                }

                $promotion = Promotion::where('id', $promotionUsers->promotions_id)->first();
                if($notifyFlagMessage){

                    $message = "Atavism gives you a ".$promotion->promotion_slug." with ".($promotion->price == 0 ? "" : $promotion->price."$")." ".($promotion->percentage == 0.00 ? "" : $promotion->percentage."%");
                    $data = ["description" => $message, "id" => $user_id,"read" => false, "title" => "Assign Promotion"];
                    $actionData = array("userId" => $user_id, "data" => json_encode($data));
                    event(new PushNotiMaintaince($actionData));
                    $notification = new Notification();
                    $notification->user_id = $user_id;
                    $notification->advise = $message;
                    $notification->re_notify = 0;
                    $notification->action = "notify";
                    $notification->save();
                }
                $usersDetail = User::select('email', 'username')->where('id', $user_id)->first();
                if($notifyFlagNotification){
                    $params = json_encode(array("user"=>$usersDetail->username,"promotion"=>$promotion->promotion_slug,"price"=>($promotion->price == 0 ? "" : $promotion->price."$"),"percentage"=>($promotion->percentage == 0.00 ? "" : $promotion->percentage."%")));
                    //$usersDetail->email = "alpesh.koyani90@gmail.com";
                    $ch = curl_init();
                    $encoded_message = urlencode($params);
                    $string1 = "https://atavismonline.com/api/acy_api.php?action=send&nid=73&email=" . $usersDetail->email . "&params=".$encoded_message;
                    curl_setopt($ch, CURLOPT_URL, $string1);
                    curl_exec($ch);
                    curl_close($ch);
                }
                //$email = $usersDetails[$key]['email'];
            }    
        }

        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['usersArr' => $usersArr, 'usersDetails' => $usersDetails]];
        return response($response, 200);
    }

    public function assignUserPromotion(Request $request)
    {
        $auth = Auth::user();
        $data = $request->all();
        $user_id = $data['user_id'];
        $user_id = explode(',', $user_id);
        $promotions = $data['promotions'];
        $promotionsArr = explode(',', $promotions);
        $notifyFlag = explode(",",$data['notify_flag']); // 0 for notification and 1 for email

        foreach ($user_id as $user_id) {

            $allPromotions = Promotion::select('id')->where('promotion_status', 0)->get()->pluck('id')->toArray();

            $existsPromotions = PromotionUsers::select('promotions_id')->whereIn('promotions_id',$promotionsArr)->where('user_id', $user_id)->get()->pluck('promotions_id')->toArray();
            
            $usersDetails = User::select('email', 'username')->where('id', $user_id)->first();

            $tempPromotions = array();
            foreach ($promotionsArr as $key => $promotion) {
                if (!in_array($promotion, $existsPromotions)) {
                    $tempPromotions[] = $promotion;
                }
            }
            if (!$existsPromotions) {                
                foreach ($promotionsArr as $key => $value) {
                    $promotionUsers = new PromotionUsers();
                    $promotionUsers->user_id = $user_id;
                    $promotionUsers->role_id = $auth->role_id;
                    //$promotionUsers->role_id = 2;
                    $promotionUsers->promotions_id = $promotionsArr[$key];
                    $promotionUsers->flag = 1;
                    $promotionUsers->is_invite = 1;
                    $promotionUsers->save();
                    
                    $notifyFlagMessage = 0;
                    $notifyFlagNotification = 0;
                    if(@$notifyFlag[0] == 0){
                        $notifyFlagMessage = 1;
                        $notifyFlagNotification = 1;
                    }
                    if(@$notifyFlag[0] == 1){
                        $notifyFlagNotification = 1;
                        $notifyFlagMessage = 1;
                    }
                    if(@$notifyFlag[0] == 0 && @$notifyFlag[1] == 1){
                        $notifyFlagNotification = 1;
                        $notifyFlagMessage = 1;
                    }

                    $promotion = Promotion::where('id', $promotionUsers->promotions_id)->first();
                    if($notifyFlagMessage){

                        $message = "Atavism gives you a ".$promotion->promotion_slug." with ".($promotion->price == 0 ? "" : $promotion->price."$")." ".($promotion->percentage == 0.00 ? "" : $promotion->percentage."%");
                        $data = ["description" => $message, "id" => $user_id,"read" => false, "title" => "Assign Promotion"];
                        $actionData = array("userId" => $user_id, "data" => json_encode($data));
                        event(new PushNotiMaintaince($actionData));
                        $notification = new Notification();
                        $notification->user_id = $user_id;
                        $notification->advise = $message;
                        $notification->re_notify = 0;
                        $notification->action = "notify";
                        $notification->save();
                        /*$message = "This Atavism Promotion give you ".$promotion->promotion_name." with ".($promotion->price == 0 ? "" : $promotion->price."$")." ".($promotion->percentage == 0.00 ? "" : $promotion->percentage."%");
                        $actionData = array("userId" => $user_id, "message" => $message);
                        event(new PushNotiMaintaince($actionData));*/
                    }
                    if($notifyFlagNotification){
                        $params = json_encode(array("user"=>$usersDetails->username,"promotion"=>$promotion->promotion_slug,"price"=>($promotion->price == 0 ? "" : $promotion->price."$"),"percentage"=>($promotion->percentage == 0.00 ? "" : $promotion->percentage."%")));
                        //$usersDetails->email = "mitesh.c@wingstechsolutions.com";
                        $ch = curl_init();
                        $encoded_message = urlencode($params);
                        $string1 = "https://atavismonline.com/api/acy_api.php?action=send&nid=73&email=" . $usersDetails->email . "&params=".$encoded_message;
                        // set URL and other appropriate options
                         curl_setopt($ch, CURLOPT_URL, $string1);
                        // grab URL and pass it to the browser
                        curl_exec($ch);
                        //close cURL resource, and free up system resources
                        curl_close($ch);
                        //return $notifyFlagNotification;
                    }
                }
            } elseif ($promotionsArr) {
                foreach ($tempPromotions as $key => $value) {
                    $promotionUsers = new PromotionUsers();
                    $promotionUsers->user_id = $user_id;
                    $promotionUsers->role_id = $auth->role_id;
                    //$promotionUsers->role_id = 2;
                    $promotionUsers->promotions_id = $tempPromotions[$key];
                    $promotionUsers->flag = 1;
                    $promotionUsers->is_invite = 1;
                    $promotionUsers->save();

                    $notifyFlagMessage = 0;
                    $notifyFlagNotification = 0;
                    if(@$notifyFlag[0] == 0){
                        $notifyFlagMessage = 1;
                        $notifyFlagNotification = 1;
                    }
                    if(@$notifyFlag[0] == 1){
                        $notifyFlagNotification = 1;
                        $notifyFlagMessage = 1;
                    }
                    if(@$notifyFlag[0] == 0 && @$notifyFlag[1] == 1){
                        $notifyFlagNotification = 1;
                        $notifyFlagMessage = 1;
                    }

                    $promotion = Promotion::where('id', $promotionUsers->promotions_id)->first();
                    if($notifyFlagMessage){
                        $message = "Atavism gives you a ".$promotion->promotion_slug." with ".($promotion->price == 0 ? "" : $promotion->price."$")." ".($promotion->percentage == 0.00 ? "" : $promotion->percentage."%");
                        $data = ["description" => $message, "id" => $user_id,"read" => false, "title" => "Assign Promotion"];
                        $actionData = array("userId" => $user_id, "data" => json_encode($data));
                        event(new PushNotiMaintaince($actionData));
                        $notification = new Notification();
                        $notification->user_id = $user_id;
                        $notification->advise = $message;
                        $notification->re_notify = 0;
                        $notification->action = "notify";
                        $notification->save();
                        /*$message = $feedback;
                        /*$message = "This Atavism Promotion give you ".$promotion->promotion_name." with ".($promotion->price == 0 ? "" : $promotion->price."$")." ".($promotion->percentage == 0.00 ? "" : $promotion->percentage."%");
                        $actionData = array("userId" => $user_id, "message" => $message);
                        event(new PushNotiMaintaince($actionData));*/
                    }
                    if($notifyFlagNotification){
                        $params = json_encode(array("user"=>$usersDetails->username,"promotion"=>$promotion->promotion_slug,"price"=>($promotion->price == 0 ? "" : $promotion->price."$"),"percentage"=>($promotion->percentage == 0.00 ? "" : $promotion->percentage."%")));
                        //$usersDetails->email = "mitesh.c@wingstechsolutions.com";
                        $ch = curl_init();
                        $encoded_message = urlencode($params);
                        $string1 = "https://atavismonline.com/api/acy_api.php?action=send&nid=73&email=" . $usersDetails->email . "&params=".$encoded_message;
                        // set URL and other appropriate options
                         curl_setopt($ch, CURLOPT_URL, $string1);
                        // grab URL and pass it to the browser
                        curl_exec($ch);
                        //close cURL resource, and free up system resources
                        curl_close($ch);
                        //return $notifyFlagNotification;
                    }
                }

                $arrayIntersect = array_intersect($allPromotions, $promotionsArr);
                if (count($arrayIntersect)) {
                    $promotionIntersect = PromotionUsers::whereIn('promotions_id', $arrayIntersect)->update(['flag' => 1]);
                }
                $arrayDiff = array_diff($allPromotions, $promotionsArr);
                if (count($arrayDiff)) {
                    $promotionDiff = PromotionUsers::whereIn('promotions_id', $arrayDiff)->update(['flag' => 0]);
                }
            } else {
                $arrayIntersect = array_intersect($allPromotions, $promotionsArr);
                if (count($arrayIntersect)) {
                    $promotionIntersect = PromotionUsers::whereIn('promotions_id', $arrayIntersect)->update(['flag' => 1]);
                }
                $arrayDiff = array_diff($allPromotions, $promotionsArr);
                if (count($arrayDiff)) {
                    $promotionDiff = PromotionUsers::whereIn('promotions_id', $arrayDiff)->update(['flag' => 0]);
                }
            }
        }
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['promotionsArr' => $promotionsArr]];
        return response($response, 200);    
    }

    public function getPromotionById(Request $request, $promotion_id)
    {
        $promotionUsers = PromotionUsers::where('promotions_id', $promotion_id)->get();
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['promotionUsers' => $promotionUsers]];
        return response($response, 200);
    }

    public function getUserPromotionById(Request $request, $user_id)
    {
        $promotionUsers = PromotionUsers::with('Promotions')->where('user_id', $user_id)->get();
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['promotionUsers' => $promotionUsers]];
        return response($response, 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $auth = Auth::user();
        if ($request->get('promotion_id') != "") {
            $promotion = Promotion::find($request->get('promotion_id'));
        } else {
            $promotion = new Promotion();
        }
        if ($request->get('promotion_id') != "") {
            $validator = Validator::make($request->all(), [
                'promotion_name' => 'required|unique:promotions,promotion_name,' . $promotion->id . ',id',
            ]);
        } else {
            $validator = Validator::make($request->all(), [
                'promotion_name' => 'required|unique:promotions,promotion_name',
            ]);
        }
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response(['message' => $errors[0]], 422);
        }

        \DB::beginTransaction();
        try {
            $promotion->user_id = $auth->id;
            $promotion->role_id = $auth->role_id;
            $promotion->promotion_type = $request->get('promotion_type');
            $promotion->promotion_name = $request->get('promotion_name');
            $promotion->promotion_slug = \Str::slug($request->get('promotion_name'));
            if($request->get('promotion_type') == 0){
                $promotion->percentage = 0.00;
                $promotion->price = $request->get('price') ? $request->get('price') : 0;
            }else{
                $promotion->price = 0;
                $promotion->percentage = $request->get('percentage') ? $request->get('percentage') : 0;
            }
            $promotion->promotion_status = $request->get('promotion_status');
            $promotion->start_date = $request->get('start_date');
            $promotion->end_date = $request->get('end_date');
            $promotion->save();

            \DB::commit();
            if ($request->get('promotion_id') != "") {
                $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Promotion updated successfully'];
            } else {
                $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Promotion added successfully'];
            }
            return response($response, 200);
        } catch (\Exception $e) {
            \DB::rollback();
            $response = ["message" => 'Something went wrong,please try again later.', 'error' => $e];
            return response($response, 422);
        }
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $promotion = Promotion::find($id);
        if ($promotion) {
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['promotion' => $promotion]];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Promotion not found.'];
            return response($response, 422);
        }
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

    public function changeStatus(Request $request)
    {
        $promotion = Promotion::find($request->get('promotion_id'));
        if ($promotion) {
            $promotion->promotion_status = $request->get('status');
            $promotion->save();
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Promotion status changed successfully.'];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Promotion not found.'];
            return response($response, 422);
        }
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($promotion_id)
    {
        $promotion = Promotion::find($promotion_id);
        if ($promotion) {
            $promotionUsers = PromotionUsers::where('promotions_id', $promotion_id)->delete();
            $promotion->delete();
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Promotion deleted successfully.'];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Promotion not found.'];
            return response($response, 422);
        }
    }

    public function getAllPromotions(Request $request){
        $currentDate = strtotime(now());
        $promotions = Promotion::select('promotions.*')
            ->when($request->has('search') && $request->get('search') != '', function ($q) use ($request) {
                $q->where(function ($query) use ($request) {
                    return $query->where('josgt_users.email', 'LIKE', '%' . $request->get('search') . '%')
                        ->orWhere('josgt_users.username', 'LIKE', '%' . $request->get('search') . '%')
                        ->orWhere('josgt_licences.licence_key', 'LIKE', '%' . $request->get('search') . '%')
                        ->orWhere('josgt_discord.name', 'LIKE', '%' . $request->get('search') . '%')
                        ->orWhere('josgt_users.registerDate', 'LIKE', '%' . $request->get('search') . '%')
                        ->orWhere('josgt_users.lastvisitDate', 'LIKE', '%' . $request->get('search') . '%');
                });
            })
            //->whereRaw('(now() between start_date and end_date)')
            ->where('promotion_status',1)
            ->orderBy('promotions.id', 'DESC')
            ->paginate($request->get('paginate'));

        $promotion = $promotions->map(function ($promotion) {
            $promotion_type = $promotion->promotion_type;
            if ($promotion_type == 0) {
                $promotion_type_name = "Fixed";
            } elseif ($promotion_type == 1) {
                $promotion_type_name = "Percentage";
            } else {
                $promotion_type_name = "Coupon Code";
            }
            return collect([
                'id' => $promotion->id,
                'user_id' => $promotion->user_id,
                'role_id' => $promotion->role_id,
                'promotion_type' => $promotion_type_name,
                'promotion_name' => $promotion->promotion_name,
                'promotion_slug' => $promotion->promotion_slug,
                'price' => $promotion->price,
                'percentage' => $promotion->percentage,
                'promotion_status' => $promotion->promotion_status,
                'start_date' => $promotion->start_date,
                'end_date' => $promotion->end_date
            ]);
        });

        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['current_page' => $promotions->currentPage(), 'per_page' => $promotions->perPage(), 'total' => $promotions->total(), 'promotions' => $promotion]];
        return response($response, 200);
    }

    public function getAllPromotionsLists(Request $request){
        $currentDate = strtotime(now());
        $promotions = Promotion::select('promotions.*')
            ->whereRaw('(now() between start_date and end_date)')
            ->where('promotion_status',1)
            ->orderBy('promotions.id', 'DESC')->get();

        $promotion = $promotions->map(function ($promotion) {
            return collect([
                'id' => $promotion->id,
                'user_id' => $promotion->user_id,
                'role_id' => $promotion->role_id,
                'promotion_name' => $promotion->promotion_name,
                'promotion_slug' => $promotion->promotion_slug,
                'price' => $promotion->price,
                'percentage' => $promotion->percentage,
                'promotion_status' => $promotion->promotion_status,
                'start_date' => $promotion->start_date,
                'end_date' => $promotion->end_date
            ]);
        });
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['promotions' => $promotion]];
        return response($response, 200);
    }

    public function verifyCoupon(Request $request){
        $data = $request->all();
        $auth = \Auth::user();
        $couponCode = $data['coupon_code'];
        $price = $data['price'];
        $userId = $auth->id;
        
        $promotionApplied = PromotionApplied::where('user_id',$userId)
        ->where('coupon_code',$couponCode)
        ->first();

        if($promotionApplied){
            $response = ['error' => 'error', 'status' => 'true', 'message' => 'Coupon code already applied.','actual_price' => $price];
            return response($response, 200);
        }

        $promotion = \DB::table('promotion_users')
        ->select('promotions.promotion_slug','promotions.promotion_type','promotions.price','promotions.percentage')
        ->leftJoin('promotions', function($join) {
            $join->on('promotion_users.promotions_id', '=', 'promotions.id');
        })
        ->where(['promotion_users.user_id' => $userId,'promotions.promotion_slug' => $couponCode])
        ->first();
        
        $promotionType = "";
        if($promotion){
            $promotionType = $promotion->promotion_type;    
        }

        if($promotionType == 0){
            $promotionDiscountPrice = @$promotion->price;
            $actualPriceWithDiscount = $price - $promotionDiscountPrice;
        }else{
            $promotionDiscountPercentage = floatval($promotion->percentage);
            $actualPriceWithDiscount = $price - ($price * $promotionDiscountPercentage / 100);
        }
        if($price >= $actualPriceWithDiscount && $promotion != null){
            $response = ['success' => 'success', 'status' => 'true', 'message' => 'Coupon code applied successfully.','actual_price' => $actualPriceWithDiscount,'original_price' => intval($price)];
            return response($response, 200);
        }else{
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Invalid coupon code.','userId'=>$userId,'promotion'=>$promotion];
            return response($response, 422);
        }
    }
}
