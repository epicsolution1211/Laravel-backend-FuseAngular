<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use DB;
use Auth;
use App\Products;
use App\ProductDetails;
use App\ProductConfigurations;
use Xsolla\SDK\API\XsollaClient;
use Xsolla\SDK\API\PaymentUI\TokenRequest;
use Xsolla\SDK\Webhook\WebhookServer;
use Xsolla\SDK\Webhook\Message\Message;

class LicencesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $data = $request->all();
        $auth_user = Auth::user();
        $type = $data['type'];
        if($type == 'subscription'){
            $licences = DB::table('josgt_eshop_product_configuration')
            ->select('josgt_eshop_product_configuration.*','josgt_eshop_product_configuration.product_name as pd_product_name')
            ->where('josgt_eshop_product_configuration.licence_flag', 0)
            ->paginate($request->get('paginate'));
            /*$licences = DB::table('josgt_eshop_products')
            ->select('josgt_eshop_products.*','josgt_eshop_productdetails.*','josgt_eshop_product_configuration.*')
            ->join('josgt_eshop_productdetails', 'josgt_eshop_productdetails.product_id', '=', 'josgt_eshop_products.id')
            ->join('josgt_eshop_product_configuration', 'josgt_eshop_product_configuration.product_id', '=', 'josgt_eshop_products.id')
            ->where('josgt_eshop_productdetails.language', 'en-US')
            ->where('josgt_eshop_product_configuration.licence_flag', 0)
            ->paginate($request->get('paginate'));*/
        }
        if($type == 'permanent'){
            $licences = DB::table('josgt_eshop_product_configuration')
            ->select('josgt_eshop_product_configuration.*','josgt_eshop_product_configuration.product_name as pd_product_name')
            ->where('josgt_eshop_product_configuration.licence_flag', 1)
            ->paginate($request->get('paginate'));
            /*$licences = DB::table('josgt_eshop_products')
            ->select('josgt_eshop_products.*','josgt_eshop_productdetails.*','josgt_eshop_product_configuration.*','josgt_eshop_productdetails.product_name as pd_product_name')
            ->join('josgt_eshop_productdetails', 'josgt_eshop_productdetails.product_id', '=', 'josgt_eshop_products.id')
            ->join('josgt_eshop_product_configuration', 'josgt_eshop_product_configuration.product_id', '=', 'josgt_eshop_products.id')
            ->where('josgt_eshop_productdetails.language', 'en-US')
            ->where('josgt_eshop_product_configuration.licence_flag', 1)
            ->paginate($request->get('paginate'));*/
        }
        if($type == "maintenance"){
            $licences = DB::table('josgt_eshop_product_configuration')
            ->select('josgt_eshop_product_configuration.*','josgt_eshop_product_configuration.product_name as pd_product_name')
            ->where('josgt_eshop_product_configuration.licence_flag', 2)
            ->paginate($request->get('paginate'));
        }   
        /*if($auth_user->role_id != 1){
            $access = collect($licences)->pluck('id');
            $licences = $licences->map(function($licence) use($access,$request){
                $permission = $access->contains($licence->id);
                return collect([
                    'id' => $licence->id,
                    'product_name' => $licence->product_name,
                    'product_alias' => $licence->product_alias,
                    'published' => $licence->published
                ]);
            });
            $total = count($licences);
            $perPage = $request->get('paginate'); // How many items do you want to display.
            $currentPage = $request->get('page');
            $licences = new LengthAwarePaginator($licences, $total, $perPage, $currentPage);
        }*/
        $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','data'=> ['current_page' => $licences->currentPage(),'per_page' => $licences->perPage(),'total' => $licences->total(),'licences' => $licences->values()]];
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

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = $request->all();
        $action = "";
        $products = Products::where('id',$request->get('product_name'))->first();
        if($products){
            $productName = str_replace('_', ' ', $products->product_sku);  
            $productId = $products->id;  
        }else{
            $productName = $request->get("product_name");
            $productId = NULL;
        }
        $productAlias = Str::slug($productName);
        $xsollaClient = XsollaClient::factory(array(
        'merchant_id' => \Config::get('app.merchant_id'),
        'api_key' => \Config::get('app.api_key')));
        $project_id = \Config::get('app.project_id');
        if($request->get('configurationId') != "undefined" && $request->get('configurationId') != ""){
            /*$validator = Validator::make($request->all(), [
                'product_name' => 'required|string|max:255|unique:josgt_eshop_product_configuration,product_name'.$request->get('product_id'),
            ]);
            if ($validator->fails())
            {
                $errors = $validator->errors()->all();
                return response(['message'=>$errors[0]], 422);
            }*/
            $action = "update";
            $product = DB::table('josgt_eshop_products')
            ->select('josgt_eshop_products.*','josgt_eshop_productdetails.*','josgt_eshop_product_configuration.*')
            ->join('josgt_eshop_productdetails', 'josgt_eshop_productdetails.product_id', '=', 'josgt_eshop_products.id')
            ->join('josgt_eshop_product_configuration', 'josgt_eshop_product_configuration.product_id', '=', 'josgt_eshop_products.id')
            //->where('josgt_eshop_productdetails.product_alias', 'atavism-2018-op-standard-subscription')
            ->where('josgt_eshop_products.id', $request->get('product_id'))
            ->first();
            $type = $request->get('type');
        }else{
            /*$validator = Validator::make($request->all(), [
                'product_name' => 'required|string|max:255|unique:josgt_eshop_product_configuration,product_name',
            ]);
            if ($validator->fails())
            {
                $errors = $validator->errors()->all();
                return response(['message'=>$errors[0]], 422);
            }*/
            $action = "save";
            $product = new Products();
            $productDetails = new ProductDetails();
            $productConfigurations = new ProductConfigurations();
            $type = $request->get('type');
        }
        try {
            if($action == 'update'){
                $productConfigurations = ProductConfigurations::find($request->get('configurationId'));
                $xsollaClient = XsollaClient::factory(array(
                'merchant_id' => \Config::get('app.merchant_id'),
                'api_key' => \Config::get('app.api_key')));
                if($productConfigurations->plan_id){
                    if($productConfigurations->licence_flag == 0){
                        $responseUpdateSubscriptionPlan = $xsollaClient->UpdateSubscriptionPlan(array(
                            'project_id' => $productConfigurations->project_id,
                            'plan_id' => $productConfigurations->plan_id,
                            'request' => array (
                                'external_id' => $productConfigurations->external_id,
                                'name' => array (
                                    'en' => $request->get('product_name'),
                                ),
                                'description' => array (
                                    'en' => ($request->get('product_desc') ? $request->get('product_desc') : "NULL"),
                                ),
                                'charge' => array (
                                    'period' => array (
                                         'value' => $request->get('maintenance'),
                                         'type' => 'day',
                                    ),
                                    'amount' => $request->get('from_price'),
                                    'currency' => 'USD',
                                ),
                                'group_id' => $productConfigurations->group_id
                            )
                        ));  
                    }else if($productConfigurations->licence_flag == 1){
                        /*$responseUpdateSubscriptionPlan = $xsollaClient->UpdateSubscriptionPlan(array(
                            'project_id' => $productConfigurations->project_id,
                            'plan_id' => $productConfigurations->plan_id,
                            'request' => array (
                                'external_id' => $productConfigurations->external_id,
                                'name' => array (
                                    'en' => @$productName,
                                ),
                                'description' => array (
                                    'en' => "lifetime plan",
                                ),
                                'charge' => array (
                                    'period' => array (
                                         'value' => 0,
                                         'type' => 'lifetime',
                                    ),
                                    'amount' => @$request->get('product_price'),
                                    'currency' => 'USD',
                                ),
                            )
                        ));*/
                    }
                }
                $productConfigurations = ProductConfigurations::where('id',$request->get('configurationId'))->update(['type'=>$type,'product_id'=>$productId,'product_name'=>$productName,'from_price'=>@$request->get('from_price'),'to_price'=>@$request->get('to_price'),'product_desc'=>@$request->get('product_desc'), 'concurrent_connections'=>$request->get('ccu'),'maintenance'=>$request->get('maintenance'),'multiserver'=>$request->get('multiserver'),'licence_type'=>$request->get('licence_type'),'trial_period_days'=>$request->get('trial_period_days')]);
            }else{
                if($request->get('licence_flag') == 0){
                    $external_id = $request->get('external_id');
                    $params = array(
                        'project_id' => $project_id,
                        'request' => array (
                        'charge'=> [
                        'amount'=> @$request->get('from_price'),
                        'currency'=> 'USD',
                            'period'=> [
                            'type'=> 'day',
                                'value'=> @$request->get('maintenance')
                            ]
                            ],
                            'description'=> [
                                'en'=> ($request->get('product_desc') ? $request->get('product_desc') : "Licence description")
                            ],
                            'name'=>[
                                'en'=> @$productName
                            ],
                            'status'=>[
                                'value'=> 'active'
                            ],
                            'trial'=> [
                                'type'=> 'day',
                                'value'=> @$request->get('maintenance')
                            ],
                            'group_id'=> @$request->get('licence_type')
                        )
                    );
                    $response = $xsollaClient->CreateSubscriptionPlan($params);
                    $plan_id = $response['plan_id'];
                    $external_id = $response['external_id'];
                }else{
                    /*$params = array(
                        'project_id' => $project_id,
                        'request' => array (
                        'charge'=> [
                            'amount'=> @$request->get('product_price'),
                            'currency'=> 'USD',
                                'period'=> [
                                    'type'=> 'lifetime',
                                    'value'=> 0
                                ]
                            ],
                            'description'=> [
                                'en'=> "Lifetime product"
                            ],
                            'name'=>[
                                'en'=> @$productName
                            ],
                            'status'=>[
                                'value'=> 'active'
                            ],
                            'trial'=> [
                                'type'=> 'day',
                                'value'=> 1
                            ],
                        )
                    );
                    $response = $xsollaClient->CreateSubscriptionPlan($params);
                    $plan_id = $response['plan_id'];
                    $external_id = $response['external_id'];*/
                }
                $productConfigurations->product_id = @$productId;
                $productConfigurations->licence_flag = $request->get('licence_flag');
                $productConfigurations->type = @$type;
                $productConfigurations->product_name = @$productName;
                $productConfigurations->from_price = @$request->get('from_price');
                $productConfigurations->to_price =  @$request->get('to_price');
                //$productConfigurations->product_price = @$request->get('product_price');
                $productConfigurations->product_desc = @$request->get('product_desc');
                $productConfigurations->concurrent_connections = @$request->get('ccu');
                $productConfigurations->maintenance = @$request->get('maintenance');
                $productConfigurations->multiserver = @$request->get('multiserver');
                $productConfigurations->licence_type = @$request->get('licence_type');
                $productConfigurations->trial_period_days = @$request->get('trial_period_days');
                $productConfigurations->plan_id = @$plan_id;
                $productConfigurations->external_id = @$external_id;
                $productConfigurations->project_id = @$project_id;
                $productConfigurations->save();
            }
            //\DB::commit();
            if($request->get('configurationId') != "undefined"){
                $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'License updated successfully','data'=>$data];
            }else{
                $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'License added successfully'];
            }
            return response($response, 200);
        }catch (\Exception $e) {
            //print_r($e);
            //\DB::rollback();
            $response = ["message" =>'Something went wrong,please try again later.','error' => $e->getMessage()];
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

    public function getAllProducts(){
        $productConfigurations = DB::table('josgt_eshop_product_configuration')->select('product_id')->where('product_id','<>',NULL)->get()->toArray();
        $productIdArr = array();
        foreach($productConfigurations as $key => $value){
            $productIdArr[] = $value->product_id;
        }
        $products = DB::table('josgt_eshop_products')->select('id','product_sku')->where('published',1)->whereNotIn('id',$productIdArr)->get();
        $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','data'=> ['products' => $products]];
            return response($response, 200);
    }

    public function getAllProductsEdit($configurationId){
        $licences = DB::table('josgt_eshop_product_configuration')->find($configurationId);
        if($licences->product_id == NULL){
            $products = DB::table('josgt_eshop_product_configuration')->find($configurationId);
        }else{
            $products = DB::table('josgt_eshop_products')->where('id',$licences->product_id)->get();    
        }
        $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','data'=> ['products' => $products,'configurationId'=>$configurationId]];
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
        $licences = DB::table('josgt_eshop_product_configuration')->find($id);
        $products = DB::table('josgt_eshop_products')->select('id','product_sku')->where('published',1)->where('id',$licences->product_id)->get();
        /*$licences = DB::table('josgt_eshop_products')
            ->select('josgt_eshop_products.*','josgt_eshop_productdetails.*','josgt_eshop_product_configuration.*')
            ->join('josgt_eshop_productdetails', 'josgt_eshop_productdetails.product_id', '=', 'josgt_eshop_products.id')
            ->join('josgt_eshop_product_configuration', 'josgt_eshop_product_configuration.product_id', '=', 'josgt_eshop_products.id')
            ->where('josgt_eshop_products.id', $id)
            ->first();*/
        if($licences){
            $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','data'=> ['licences' => $licences,'products'=>$products]];
            return response($response, 200);
        }else{
            $response = ['error'=>'error','status' => 'false','message' => 'Download not found.'];
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

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($configurationId)
    {
        $product = DB::table('josgt_eshop_product_configuration')->where('id', $configurationId)->first();
        if($product){
            if($product->type == NULL){
                $xsollaClient = XsollaClient::factory(array(
                'merchant_id' => \Config::get('app.merchant_id'),
                'api_key' => \Config::get('app.api_key')));
                $project_id = \Config::get('app.project_id');
                $plan_id = $product->plan_id;
                $params = array("project_id"=>$project_id,"plan_id"=>$plan_id);    
                $deleteProduct = $xsollaClient->DeleteSubscriptionPlan($params);    
            }
            $productConfigurations = DB::table('josgt_eshop_product_configuration')->where('id', $configurationId)->delete();
            /*$productDetails = DB::table('josgt_eshop_productdetails')->where('product_id', $licenceId)->delete();
            $product = DB::table('josgt_eshop_products')->where('id', $licenceId)->delete();*/
            $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Licence deleted successfully.'];
            return response($response, 200);
        }
        else{
            $response = ['error'=>'error','status' => 'false','message' => 'Licence not found.'];
            return response($response, 422);
        }
    }

    public function changeLicenceSubscriptionStatus(Request $request){
        $data = $request->all();
        $licence = ProductConfigurations::find($request->get('licence_id'));
        if($licence){
         $licence->published = $request->get('status');
         $licence->save();
         $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Licence status changed successfully.'];
         return response($response, 200);
        }
        else{
         $response = ['error'=>'error','status' => 'false','message' => 'Licence not found.'];
         return response($response, 422);
        }
    }
}
