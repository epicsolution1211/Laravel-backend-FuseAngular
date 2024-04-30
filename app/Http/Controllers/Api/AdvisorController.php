<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\AdvisorSettings;

class AdvisorController extends Controller
{
    public function advisorSettings($user_id) {
        $advisorSettingsArr = AdvisorSettings::where('users_id',$user_id)->get()->toArray();
        if($advisorSettingsArr){
            foreach($advisorSettingsArr as $key => $value){
                $advise_variables = \Config::get('constants.ADVISE_SETTINGS');
                $advisorSettings['user_id'] = $user_id;
                $advisorSettings['advisor_settings'][] = ["id"=>$value['id'],"advise_name"=>$value['advise_name'],"advise_slug"=>$value['advise_slug'],"advise_description"=>$value['advise_description'],"advise_days"=>$value['advise_days'],"advise_message_status"=>$value["advise_message_status"],"advise_mail_status"=>$value["advise_mail_status"],"advise_variables"=>$advise_variables[$key]['advise_variables']];
            }
        }else{
            $advisorSettings['advisor_settings'] = \Config::get('constants.ADVISE_SETTINGS');
            $advisorSettings['user_id'] = $user_id;
        }
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['data' => array($advisorSettings)]];
        return response($response, 200);
    }   

    public function advisorSettingsSave(Request $request){
        $data = $request->all();
        $user_id = $data['user_id'];
        $advisorSettings = $data['advisor_settings'];
        foreach($advisorSettings as $key => $value){
            $exists = AdvisorSettings::where('users_id',$user_id)->where('id',$value['id'])->count();
            if($exists){
                $advisorSettings = AdvisorSettings::where('id',$value['id'])->update(["advise_name"=>$value['advise_name'],"advise_slug"=>$value['advise_slug'],"advise_description"=>$value['advise_description'],"advise_days"=>$value['advise_days'],"advise_message_status"=>$value['advise_message_status'],"advise_mail_status"=>$value['advise_mail_status']]);
            }else{
                $advisorSettings = New AdvisorSettings();
                $advisorSettings->users_id = $user_id;
                $advisorSettings->advise_name = $value['advise_name'];
                $advisorSettings->advise_slug = $value['advise_slug'];
                $advisorSettings->advise_description = $value['advise_description'];
                $advisorSettings->advise_days = $value['advise_days'];
                $advisorSettings->advise_message_status = $value['advise_message_status'];
                $advisorSettings->advise_mail_status = $value['advise_status'];
                $advisorSettings->save();
            }
        }
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['data' => array($data)]];
        return response($response, 200);
    }
}