<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\User;
use App\Employee;
use App\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Events\PushNotiMaintaince;

class NotificationsController extends Controller
{
    public function __construct(){
        
    }
    public function getNotifications(Request $request) {
        $data = $request->all();
        $notications = Notification::where("re_notify","!=",2)->where("user_id",$data['userId'])->orderBy('id','desc')->get();
        $dataArr = array();
        foreach($notications as $key => $value){
            $dataArr[$key]['id'] = $value['id'];
            $dataArr[$key]['title'] = $value['title'] != NULL ? $value['title'] : "Advise";
            $dataArr[$key]['description'] = $value['advise'];
            $dataArr[$key]['read'] = $value['re_notify'];
        }
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['data' => array($dataArr)]];
        return response($response, 200);
    }   

    public function changeStatus(Request $request){
        $notification = Notification::find($request->get('id'));
        if ($notification) {
            $notification->re_notify = 1;
            $notification->save();
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Notification status changed successfully.'];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Notification not found.'];
            return response($response, 422);
        }
    }

    public function destroy(Request $request){
        $notification = Notification::find($request->get('id'));
        if ($notification) {
            $notification->re_notify = 2;
            $notification->save();
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Notification deleted successfully.'];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Notification not found.'];
            return response($response, 422);
        }
    }
}