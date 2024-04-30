<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\CustomRequirement;
use App\ReqOffer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Xsolla\SDK\API\XsollaClient;
use Xsolla\SDK\API\PaymentUI\TokenRequest;

class CustomRequirementController extends Controller
{

    function index(Request $request)
    {
        $auth_user = Auth::user();
        $role_id = $auth_user->role_id;
        $userId = $auth_user->id;

        $custom_requirements = CustomRequirement::select(
            'custom_requirements.id',
            'custom_requirements.title',
            'custom_requirements.budget',
            'custom_requirements.description',
            DB::raw('date_format(custom_requirements.deadline, "%a %b %d %Y") as deadline'),
            'josgt_users.id as user_id',
            'josgt_users.username',
        )
            ->join('josgt_users', 'josgt_users.id', 'custom_requirements.user_id')
            ->when($role_id == 3, function ($q2) use ($userId) {
                return $q2->where('user_id', $userId);
            })
            ->orderBy('id', 'DESC')
            ->paginate($request->get('paginate'));

            $transformed_custom_requirements = $custom_requirements->getCollection()->map(function($item){
                $item->hasOffer = ReqOffer::where('custom_requirement_id',$item->id)->first() ? true : false;
                return $item;
            });


        if (count($custom_requirements) > 0) {
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['current_page' => $custom_requirements->currentPage(), 'per_page' => $custom_requirements->perPage(), 'total' => $custom_requirements->total(), 'custom_requirements' => $transformed_custom_requirements]];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Requirements not found.'];
            return response($response, 422);
        }
    }

    function store(Request $request)
    {
        $payloads = $request->only('title', 'budget', 'deadline', 'description','user_id');

        $validator = Validator::make($payloads, [
            'title' => 'required|string|max:225',
            'budget' => 'required|numeric',
            'deadline' => 'required|string',
            'description' => 'required|string|max:225',
            'user_id' => 'required|integer'
        ], [
            'title.required' => 'Title is required',
            'title.string' => 'Invalid Title',
            'title.max' => 'Title should be less then 226 characters',
            'budget.required' => 'Budget is required',
            'budget.numeric' => 'Invalid Budget',
            'deadline.required' => 'Deadline is required',
            'deadline.string' => 'Invalid Deadline',
            'description.required' => 'Description is required',
            'description.string' => 'Invalid Description',
            'description.max' => 'Description should be less then 226 characters',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response()->json(['message' => $errors[0]], 422);
        }

        CustomRequirement::create($payloads);

        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Requirement created successfully'];
        return response()->json($response);
    }

    function update(Request $request) {
        $payloads = $request->only('title', 'budget', 'deadline', 'description','user_id');

        $validator = Validator::make($payloads, [
            'title' => 'required|string|max:225',
            'budget' => 'required|numeric',
            'deadline' => 'required|string',
            'description' => 'required|string|max:225',
            'user_id' => 'required|integer'
        ], [
            'title.required' => 'Title is required',
            'title.string' => 'Invalid Title',
            'title.max' => 'Title should be less then 226 characters',
            'budget.required' => 'Budget is required',
            'budget.numeric' => 'Invalid Budget',
            'deadline.required' => 'Deadline is required',
            'deadline.string' => 'Invalid Deadline',
            'description.required' => 'Description is required',
            'description.string' => 'Invalid Description',
            'description.max' => 'Description should be less then 226 characters',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response()->json(['message' => $errors[0]], 422);
        }

        $custom_req = CustomRequirement::find($request->id);

        if(!$custom_req) {
            return response()->json(['message' => 'Not Found'],404);
        }

        $custom_req->update($payloads);

        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Requirement updated successfully'];
        return response()->json($response);
    }

    function createOffer (Request $request) {
        $payloads = $request->only('custom_requirement_id','amount','deadline','description');

        $validator = Validator::make($payloads,[
            'custom_requirement_id' => 'required|integer',
            'amount' => 'required|numeric',
            'deadline' => 'required|string',
            'description' => 'required|string|max:225',
        ],[
            'amount.required' => 'Amount is required',
            'amount.numeric' => 'Invalid Amount',
            'deadline.required' => 'Deadline is required',
            'deadline.string' => 'Invalid Deadline',
            'description.required' => 'Description is required',
            'description.string' => 'Invalid Description',
            'description.max' => 'Description should be less then 226 characters',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response()->json(['message' => $errors[0]], 422);
        }

        ReqOffer::create($payloads);

        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Offer created successfully'];
        return response()->json($response);
    }

    function showOffer ($id) {
        $req_offer = ReqOffer::select(
            'req_offers.id',
            'custom_requirements.title',
            'req_offers.amount',
            'req_offers.description',
            DB::raw('date_format(req_offers.deadline, "%a %b %d %Y") as deadline'),
            'accepted',
        )
            ->join('custom_requirements','custom_requirements.id','req_offers.custom_requirement_id')
            ->where('custom_requirement_id',$id)
            ->first();
        if ($req_offer) {
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => $req_offer];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Offer not found.'];
            return response($response, 404);
        }
    }

    function acceptOffer(Request $request){

        $user = $request->user();
        $xsollaClient = XsollaClient::factory(array(
            'merchant_id' => 43963,
            'api_key' => 'dc8y4BVLBNAZH4g0'
        ));
            // $tokenRequest = new TokenRequest(158935, $user->email);
            // $tokenRequest->setUserEmail($user->email)
            //     ->setExternalPaymentId(md5(uniqid(mt_rand(), false)))
            //     ->setUserName($user->username);
            // $tokenRequest->setSandboxMode(true);

            // $dataRequest = $tokenRequest->toArray();
            // $dataRequest['settings']['ui']['desktop']['header']['visible_logo'] = true;
            // $dataRequest['settings']['ui']['desktop']['header']['visible_name'] = true;
            // $dataRequest['settings']['ui']['desktop']['header']['close_button'] = true;
            // $dataRequest['settings']['return_url'] = 'https://devapanel.atavismonline.com/custom-requirements';
            // $dataRequest['settings']['ui']['desktop']['subscription_list']['description'] = 'You Custom Requirement Payment';
            // $dataRequest['settings']['ui']['size'] = "small";
            // $dataRequest['settings']['language'] = "en";

            // $parsedResponse = $xsollaClient->CreatePaymentUIToken(array('request' => $dataRequest));

            // $responseProduct = $xsollaClient->CreateSubscriptionProduct(array(
            //     'project_id' => 158935,
            //     'request' => array(
            //         'name' => $request->title,
            //         'group_id' => 'Requirement'
            //     ),
            // ));
            // $product_id = $responseProduct['product_id'];
            $tokenRequest = new TokenRequest(158935, $user->email);
            $tokenRequest->setUserEmail($user->email)
                ->setExternalPaymentId(md5(uniqid(mt_rand(), false)))
                ->setUserName($user->username)
                ->setPurchase(is_int($request->amount) ? $request->amount : floatval($request->amount), "USD");;
            $tokenRequest->setSandboxMode(true);
            $dataRequest = $tokenRequest->toArray();
        $dataRequest['purchase']['description']['value'] = $request->title;
            $dataRequest['settings']['language'] = "en";
        $dataRequest['settings']['return_url'] = 'https://devapanel.atavismonline.com/custom-requirements';
            $parsedResponse = $xsollaClient->CreatePaymentUIToken(array('request' => $dataRequest));
            $paymentRedirectUrl = "https://sandbox-secure.xsolla.com/paystation4/?token=" . $parsedResponse['token'];

            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data'=> $paymentRedirectUrl];
            return response($response, 200);
    }
}
