<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Employee;
use App\User;
use App\Release;
use App\Download;
use App\Licence;
use App\Discord;
use App\Role;
use App\LicenceCcuHistory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Auth;
use DB;
use Carbon\Carbon;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //dd($request->get('status') == 0);
        $auth_user = \Auth::user();
        $userId = $auth_user->id;
        /*$users = User::with('licences','discord','userRole')
                        ->where('role_id','!=',1)
                        //->where('role_id','!=',3)
                        ->when($request->has('search') && $request->get('search') != '',function($q) use ($request){
                                    $q->where(function ($query) use ($request){
                                        return $query->where('email','LIKE','%'.$request->get('search').'%')
                                                    ->orWhere('username','LIKE','%'.$request->get('search').'%');
                                    });
                            })
                        ->when($request->get('role') != 'all',function($q1) use ($request){
                            return $q1->where('role_id',$request->get('role'));
                        })
                        ->when($request->get('status') == 1 || $request->get('status') == 0,function($q2) use ($request){
                            //$status = $request->get('status') == 'active' ? 0 : 1;
                            return $q2->where('block',$request->get('status'));
                        })
                        ->orderBy('id','DESC')
                        ->paginate($request->get('paginate'));*/

        $employees = Employee::select('*')->with('user')
                        //->where('owner_id',$userId)
                        ->when($request->has('search') && $request->get('search') != '',function($q) use ($request){
                                    $q->where(function ($query) use ($request){
                                        return $query->where('email','LIKE','%'.$request->get('search').'%')
                                                    ->orWhere('username','LIKE','%'.$request->get('search').'%');
                                    });
                            })
                        ->when($auth_user->role_id == 3,function($q2) use ($request,$userId){
                            return $q2->where('owner_id',$userId);
                        })
                        ->when($auth_user->role_id != 1,function($q2) use ($request,$userId){
                            return $q2->where('owner_id',$userId);
                        })
                        ->when($request->get('status') == 1 || $request->get('status') == 0,function($q2) use ($request){
                            return $q2->where('block',$request->get('status'));
                        })
                        ->orderBy('id','DESC')
                        ->paginate($request->get('paginate'));                
                     
        $employee_detail = $employees->map(function($employee){
            return collect([
                'id' => $employee->id,
                'owner_id' => $employee->owner_id,
                'username' => $employee->username,
                'email' => $employee->email,
                'owner_name' => ($employee->user ? $employee->user['name'] : ''),
                'registered_since' => $employee->registerDate,
                'block' => $employee->block
            ]);
        });
        
        $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','data'=> ['current_page' => $employees->currentPage(),'per_page' => $employees->perPage(),'total' => $employees->total(),'employees' => $employee_detail]];
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
        if($request->get('userId') != "undefined"){
            $employee = Employee::find($request->get('userId'));
        }else{
            $employee = new Employee();
        }
        if($request->get('userId') && $request->get('userId') != "undefined"){
                $validator = Validator::make($request->all(), [
                    'username' => 'required|unique:josgt_employees,username,'.$employee->id.',id',
                    'name' => 'required',
                    'email' => 'required|email|unique:josgt_employees,email,'.$employee->id,
                    //'email' => 'required|unique:josgt_users,email,'.$employee->id.',id'
            ]);
        }else{
            $validator = Validator::make($request->all(), [
                'username' => 'required|unique:josgt_employees,username',
                'name' => 'required',
                'email' => 'required|unique:josgt_employees,email',
                'password' => 'required|min:6',
                'password_confirmation' => 'required_with:password|same:password|min:6'
            ]);
        }
        if ($validator->fails())
        {
            $errors = $validator->errors()->all();
            return response(['message'=>$errors[0]], 422);
        }

        $password = Str::random(10);
        \DB::beginTransaction();
        try {
            $employee->owner_id = $employee->owner_id ? $employee->owner_id : $request->get('loggedin_id');
            $employee->username = $request->get('username');
            $employee->name = $request->get('name');
            $employee->email = $request->get('email');
            $employee->password = bcrypt($request->get('password'));
            $employee->save();

            \DB::commit();
            if($request->get('userId') != "undefined"){
                $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Employee updated successfully']; 
            }else{
                $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Employee added successfully']; 
            }
            return response($response, 200); 
        }catch (\Exception $e) {
            dd($e);
            \DB::rollback();
            $response = ["message" =>'Something went wrong,please try again later.','error' => $e];
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
        //$user = User::with('discord','licences')->find($id);
        $employee = Employee::find($id);
        if($employee){
            $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','data'=> ['employee' => $employee]];
            return response($response, 200);
        }else{
            $response = ['error'=>'error','status' => 'false','message' => 'User not found.'];   
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
    public function destroy($id)
    {
        $employee = Employee::find($id);
        if($employee){
            $employee->delete();
            $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Employee deleted successfully.']; 
            return response($response, 200);
        }
        else{
            $response = ['error'=>'error','status' => 'false','message' => 'Employee not found.']; 
            return response($response, 422);
        }
    }

    public function changeEmployeePassword(Request $request){
        $data = $request->all();
        $employee = Employee::where('owner_id', $request->get('loggedin_id'))->update(['password' => bcrypt($request->password)]);
        $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Password Updated Successfully'];
        return response($response, 200);
    }

    public function getAllCounts(Request $request){
        $role_id = $request->get('role_id');
        $auth = Auth::user();
        $releases = Release::where('show',1)->orderBy('id', 'DESC')->get();
        $latest = $releases->first();
        $countArr = [];
        if($role_id == 1){
            $accountsCounts = User::where('role_id',3)->get()->count();
            $coustomersActiveCounts = User::where('role_id',3)->where('block',0)->get()->count();
            $usersCounts = User::whereIn('role_id',[2])->get()->count();
            $employeesAdminsCounts = User::whereIn('role_id',[2])->where('block',0)->get()->count();
            $releasesCounts = Release::get()->count();
            $downloadsCounts = Download::get()->count();
            $activeCounts = Download::where('active',1)->get()->count();
        }else{
            $accountsCounts = User::where('role_id',3)->get()->count();
            $coustomersActiveCounts = User::where('role_id',3)->where('block',0)->get()->count();
            $usersCounts = User::whereIn('role_id',[2])->where('id','<>',$auth->id)->get()->count();
            $employeesAdminsCounts = User::whereIn('role_id',[2])->where('id','<>',$auth->id)->where('block',0)->get()->count();
            $releasesCounts = Release::get()->count();
            $licences = Licence::where("user_id",$auth->id)->where('enabled',1)->where('convert_to','<',0)->get();
            $result = Download::get();
            //$activeCounts = Download::where('active',1)->get()->count();
            $downloads = [];
            $activeDownloads = [];
            foreach ($result as $download){
                $licence_found = false;
                foreach ($licences as $licence) {
                    $licenceReqs = explode(",", $download->licence_req);
                    foreach ($licenceReqs as $licenceReq) {
                        if (strpos($licence->licence_type, $licenceReq) !== false) {
                            if ($licence->maintenance_expire > $download->release_date){
                                $licence_found = true;    
                            }
                        }
                    }
                }
                
                if ($licence_found) {
                    $downloads[] = $download;

                    if($download->active == 1){
                        $activeDownloads[] = $download;
                    }
                }
            }
            $downloadsCounts = count($downloads);
            $activeCounts = count($activeDownloads);
        }
        $countArr['accounts_count'] = $accountsCounts;
        $countArr['customers_active_count'] = $coustomersActiveCounts;
        $countArr['employees_admins_count'] = $employeesAdminsCounts;
        $countArr['users_count'] = $usersCounts;
        $countArr['releases_count'] = $releasesCounts;
        $countArr['releases_latest_version'] = $latest->version;
        $countArr['downloads_enabled_count'] = $downloadsCounts;
        $countArr['downloads_disabled_count'] = $activeCounts;
        return $countArr;
        $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','data'=> ['counts' => $countArr, 'role_id' => $role_id]];
        return response($response, 200);
    }

    public function changeStatus(Request $request){
       $employee = Employee::find($request->get('user_id'));
       if($employee){
        $employee->block = $request->get('status');
        $employee->save();
        $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Employee status changed successfully.']; 
        return response($response, 200);
       } 
       else{
        $response = ['error'=>'error','status' => 'false','message' => 'Employee not found.'];
        return response($response, 422);
       }
    }

    public function getChartData(Request $request){
        $filter = $request->get('filter');
        $licId = $request->get('licId');
        $licIdArr = explode(',', $licId);
        if($filter){
            $filter = $request->get('filter');
        }else{
            $filter = 0;
        }
        $auth = Auth::user();
        $role_id = $auth->role_id;
        $rows = array();
        $table = array();
        $table['cols'] = array(
            array(
                'label' => 'Date Time', 
                'type' => 'datetime'
            )
        );
        if($role_id != 1){
            if(isset($licId)){
                $result = DB::table('josgt_licences')->where('enabled',1)->where('convert_to','<',0)->whereIn('id',$licIdArr)->get();   
            }else{
                $result = DB::table('josgt_licences')->where("user_id", $auth->id)->where('enabled',1)->where('convert_to','<',0)->get();
            }
            //$result = DB::table('josgt_licences')->where("user_id", $auth->id)->where('enabled',1)->where('convert_to','<',0)->get();
        }else{
            $result = DB::table('josgt_licences')->where("user_id", $auth->id)->where('enabled',1)->where('convert_to','<',0)->get();
            //$result = DB::table('josgt_licences')->where("user_id", $auth->id)->where('enabled',1)->where('convert_to','<',0)->get();
        }

        $chartData = [];
        $chartOptions = [];
        $multipleChartOptions = [];
        $data_from = $request->get('data_from');
        $data_to = $request->get('data_to');

        $startDate = Carbon::createFromFormat('Y-m-d', '2021-06-01');
        $endDate = Carbon::createFromFormat('Y-m-d', '2021-06-30');

        switch ($filter) {
            case 0:
                $dtf  = \Carbon\Carbon::now('UTC');
                $dto  = \Carbon\Carbon::now('UTC')->subHour(-7);
                $time =  $dtf->format('Y-m-d H:i:s');
                $timeto = $dto->format('Y-m-d H:i:s');   
                break; 
            case 1:
                $dtf  = \Carbon\Carbon::now('UTC');
                $dto  = \Carbon\Carbon::now('UTC')->subHour(-24);
                $time =  $dtf->format('Y-m-d H:i:s');
                $timeto = $dto->format('Y-m-d H:i:s');
                break;
            case 2:
                $dtf  = \Carbon\Carbon::now('UTC');
                $dto  = \Carbon\Carbon::now('UTC')->subDays(-7);
                $time =  $dtf->format('Y-m-d H:i:s');
                $timeto = $dto->format('Y-m-d H:i:s');
                break;
            case 3:
                //$dtf = \Carbon\Carbon::now('UTC')->startOfMonth()->subMonth();
                //$dto = \Carbon\Carbon::now('UTC')->endOfMonth()->subMonth();
                $dtf =  \Carbon\Carbon::now('UTC')->startOfMonth()->subMonth();
                $dto =  \Carbon\Carbon::now('UTC')->subMonth()->endOfMonth();
                $time =  $dtf->format('Y-m-d H:i:s');
                $timeto = $dto->format('Y-m-d H:i:s');
                break;
            case 4:
                $dtf = \Carbon\Carbon::now('UTC')->subYear()->startOfMonth();
                $dto = \Carbon\Carbon::now('UTC')->endOfMonth();
                $time =  $dtf->format('Y-m-d H:i:s');
                $timeto = $dto->format('Y-m-d H:i:s');
                break;  
            case 5:
                $data_from = strtotime(str_replace("GMT+0530 (India Standard Time)", "", $data_from));
                $time = date('Y-m-d H:i:s',$data_from);
                $data_to = strtotime(str_replace("GMT+0530 (India Standard Time)", "", $data_to));
                $timeto = date('Y-m-d H:i:s',$data_to);
                break;
        } 
        

        /*$chartData = DB::table('josgt_licences_ccu_history')
            ->where('josgt_licences_ccu_history.licence_id', $licId)->whereBetween('josgt_licences_ccu_history.time',[$time,$timeto])->get();*/
        /*if($filter >= 0){
            $chartDataArr = DB::table('josgt_licences_ccu_history')
            ->where('josgt_licences_ccu_history.licence_id', $licId)->whereBetween('josgt_licences_ccu_history.time',[$time,$timeto])->get();
            $length = 6;
            for($i=0;$i<$length;$i++){
                $chartData['data'][] = $chartDataArr[$i]['ccu'] ? $chartDataArr[$i]['ccu'] : 0;
                //array_push($chartData['data'], $chartDataArr[$i]['ccu'] ? $chartDataArr[$i]['ccu'] : 0);
            } 
            $chartData['time'] = $time;
            $chartData['timeto'] = $timeto;
        }else{
            foreach ($result as $key => $value) {
                $chartData['series'][] = $this->getSingleChart($value->id,$filter,$data_from,$data_to);
                $chartData['xaxis']['categories'] = $chartData['series'][0]['xaxis']['categories'];

                # start chart logic
                array_push($chartOptions, (object)[
                        'series' => array("name"=>$value->licence_key,"data"=>$chartData['series'][$key]['data']),
                        'chart' => (object)["height"=>350,"type"=>"line","zoom"=>["enabled"=>false]],
                        'dataLabels' => (object)["enabled" => false],
                        'stroke' => (object)["curve"=>"straight"],
                        'title' => (object)["text"=>"Product Trends by Month","align"=>"left"],
                        'grid' => (object)["row"=>(object)["colors"=>["#f3f3f3", "transparent"]],"opacity"=>0.5],
                        'xaxis' => (object)["categories"=>$chartData['series'][$key]['xaxis']['categories']],
                        'licence_id' => array("licence_id"=>$value->id)
                ]);
                $chartData['chartOptions'][] = $chartOptions[$key];

                $series = $chartData['chartOptions'][$key]->series;
                array_push($multipleChartOptions, $series);
                $chartData['multipleChartOptions'][] = $multipleChartOptions[$key];
                
                # end chart logic
            }    
        }*/    
        foreach ($result as $key => $value) {
            $chartData['series'][] = $this->getSingleChart($value->id,$filter,$data_from,$data_to);
            $chartData['xaxis']['categories'] = $chartData['series'][0]['xaxis']['categories'];

            # start chart logic
            array_push($chartOptions, (object)[
                    'series' => array("name"=>$value->licence_key,"data"=>$chartData['series'][$key]['data']),
                    'chart' => (object)["height"=>350,"type"=>"line","zoom"=>["enabled"=>false]],
                    'dataLabels' => (object)["enabled" => false],
                    'stroke' => (object)["curve"=>"straight"],
                    'title' => (object)["text"=>"Product Trends by Month","align"=>"left"],
                    'grid' => (object)["row"=>(object)["colors"=>["#f3f3f3", "transparent"]],"opacity"=>0.5],
                    'xaxis' => (object)["categories"=>$chartData['series'][$key]['xaxis']['categories']],
                    'licence_id' => array("licence_id"=>$value->id),
                    'authId' => $auth->id
            ]);
            $chartData['chartOptions'][] = $chartOptions[$key];

            $series = $chartData['chartOptions'][$key]->series;
            array_push($multipleChartOptions, $series);
            $chartData['multipleChartOptions'][] = $multipleChartOptions[$key];
            
            # end chart logic
        }
        //$xaxis = $chartData['chartOptions'][0]->xaxis;
        //array_push($chartData['xaxis'], $xaxis);
        $response = ['error'=>new \stdClass(),'status' => 'true','data'=> $chartData];
        //$response = ['error'=>new \stdClass(),'status' => 'true','data'=> $chartData]; 
        return response($response, 200);
    }

    public function getSingleChart($lid,$ttype,$data_from,$data_to){
        
        $auth = Auth::user();

        $licId = (is_numeric($lid) ? (int)$lid : 0);
        $ttype = (is_numeric($ttype) ? (int)$ttype : 0);

        $dtf  = \Carbon\Carbon::now('UTC');
        $dto  = \Carbon\Carbon::now('UTC');

        $date_from = $data_from;
        $date_to = $data_to;

        /*$dtf  = new \DateTime('now', new \DateTimeZone('UTC'));
        $dto  = new \DateTime('now', new \DateTimeZone('UTC'));*/
        
        $time1 = "CONCAT(lpad(cast(DATE_FORMAT(time,'%Y-%m-%d-%T') as char(19)),13,'0'),'-00-00')";
        $time2 = "lpad(cast(DATE_FORMAT(time,'%Y-%m-%d %T') as char(19)),13,'0')";
        switch ($ttype) {
           case 0:
                $add = 30;
                $dtf->add(new \DateInterval('PT7H'));
                $time1 = "CONCAT(lpad(cast(DATE_FORMAT(time,'%Y-%m-%d-%T') as char(19)),13,'0'),'-00-00')";
                $time2 = "lpad(cast(DATE_FORMAT(time,'%Y-%m-%d %T') as char(19)),13,'0')";
                break;
           case 1:
                $add = 60;
                //$dtf->sub(new \DateInterval('P1D'));
                $dtf->add("1 day");
                $time1 = "CONCAT(lpad(cast(DATE_FORMAT(time,'%Y-%m-%d-%T') as char(19)),13,'0'),'-00-00')";
                $time2 = "lpad(cast(DATE_FORMAT(time,'%Y-%m-%d %T') as char(19)),13,'0')";
                break;
           case 2:
                $add = 7;
                $dtf->add(new \DateInterval('P7D'));
                $time1 = "CONCAT(lpad(cast(DATE_FORMAT(time,'%Y-%m-%d-%T') as char(19)),11,'0'),'00-00-00')";
                $time2 = "lpad(cast(DATE_FORMAT(time,'%Y-%m-%d %T') as char(19)),11,'0')";
                break;
           case 3:
                $add = 31;
                $dtf->add(new \DateInterval('P1M'));
                $time1 = "CONCAT(lpad(cast(DATE_FORMAT(time,'%Y-%m-%d-%T') as char(19)),11,'0'),'00-00-00')";
                $time2 = "lpad(cast(DATE_FORMAT(time,'%Y-%m-%d %T') as char(19)),11,'0')";
                break;
           case 4:
                $add = 12;
                $dtf->add(new \DateInterval('P1Y'));
                $time1 = "CONCAT(lpad(cast(DATE_FORMAT(time,'%Y-%m-%d-%T') as char(19)),11,'0'),'00-00-00')";
                $time2 = "lpad(cast(DATE_FORMAT(time,'%Y-%m-%d %T') as char(19)),11,'0')";
                break;
           case 5:
                $add = "custom";
                $data_from = str_replace(" GMT+0530 (India Standard Time)", "", $data_from);
                $data_from = date('Y-m-d g:i:s',strtotime($data_from));
                $data_to = str_replace(" GMT+0530 (India Standard Time)", "", $data_to);
                $data_to = date('Y-m-d g:i:s',strtotime($data_to));

                $dtf->sub(new \DateInterval('PT7H'));
                $dtf = (isset($data_from)? new \DateTime($data_from, new \DateTimeZone('UTC')) : $dtf);
                $dto = (isset($data_to)? new \DateTime($data_to, new \DateTimeZone('UTC')) :  $dto);
                if($dto<$dtf){
                 $dtf = new \DateTime($dto->format('Y-m-d H:i:s'), new \DateTimeZone('UTC'));
                 $dtf->add(new \DateInterval('PT7H'));
                }
                
                $timediff = $dtf->diff($dto);
                $plus = $timediff->format('%R');
                $numdays = (int)$timediff->format('%a');
                $diffMonths = (int)$timediff->format('%m months');

                $time1 = "CONCAT(lpad(cast(DATE_FORMAT(time,'%Y-%m-%d-%T') as char(19)),13,'0'),'-00-00')";
                $time2 = "lpad(cast(DATE_FORMAT(time,'%Y-%m-%d %T') as char(19)),13,'0')";
                if($numdays>6){
                    $time1 = "CONCAT(lpad(cast(DATE_FORMAT(time,'%Y-%m-%d-%T') as char(19)),11,'0'),'00-00-00')";
                    $time2 = "lpad(cast(DATE_FORMAT(time,'%Y-%m-%d %T') as char(19)),11,'0')";
                }
                break;
        } 
        $time =  $dtf->format('Y-m-d H:i:s');
        $timeto = $dto->format('Y-m-d H:i:s');
        //$time =  "2021-11-04 10:10:10";
        //$timeto = "2021-11-05 10:10:10";
        $role_id = Auth::user()->role_id;
        if($role_id != 1){
            //$lic = Licence::where('user_id',  $auth->id)->where("id",$licId)->first();
            $lic = Licence::where("id",$licId)->first();
        }else{
            $lic = Licence::where("id",$licId)->first();
        }
        //$lic->id = 4647;
        $licProxyIds = DB::select("SELECT DISTINCT proxy_id FROM `josgt_licences_ccu_history` WHERE `licence_id` = ".$lic->id." AND time > '".$time."'  AND time < '".$timeto."'");        
        
        $sql = "SELECT licence_id, ". $time1." as time ";
        foreach ($licProxyIds as $key => $proxy_id){
            $table['cols'][] =  array(
                'label' => $lic->licence_key.' server '.$proxy_id->proxy_id, 
                'type' => 'number'
            ); 
            $sql .=", avg(case when proxy_id = ".$proxy_id->proxy_id." then ccu else @  end) as 'proxy_".$proxy_id->proxy_id."'";
            $sql .=", max(case when proxy_id = ".$proxy_id->proxy_id." then ccu else @  end) as 'max_proxy_".$proxy_id->proxy_id."'";
        }

        /*$sql .=  ", sum(ccu) as sumccu ".
                ", sum(ccu) as avgsumccu "
             . " from josgt_licences_ccu_history where licence_id = ".$lic->id ." AND time between '".$time."' AND '".$timeto."' "
             ." GROUP by ".$time2;*/

        $sql .=  ", sum(ccu) as sumccu ".
                ", sum(ccu) as avgsumccu "
             . " from josgt_licences_ccu_history where licence_id = ".$lic->id ." AND time between '".$time."' AND '".$timeto."' "
             ." GROUP by ".$time2;     

        /*$sql .=  ", sum(ccu) as sumccu ".
                ", sum(ccu) as avgsumccu "
             . " from josgt_licences_ccu_history where licence_id = ".$lic->id ." AND time > '".$time."'  AND time < '".$timeto."' "
             ." GROUP by ".$time2;*/

        switch ($ttype) {
            case 0:
                $dtf  = \Carbon\Carbon::now('IST');
                $dto  = \Carbon\Carbon::now('IST')->subHour(-7);
                $time =  $dtf->format('Y-m-d H:i:s');
                $timeto = $dto->format('Y-m-d H:i:s');   
                break; 
            case 1:
                $dtf  = \Carbon\Carbon::now('IST')->addHour(3.50);
                $dto  = \Carbon\Carbon::now('IST')->subDay();
                $time =  $dtf->format('Y-m-d H:i:s');
                $timeto = $dto->format('Y-m-d H:i:s');
                break;
            case 2:
                $dtf  = \Carbon\Carbon::now('IST')->subDay(7);
                $dto  = \Carbon\Carbon::now('IST');
                $time =  $dtf->format('Y-m-d H:i:s');
                $timeto = $dto->format('Y-m-d H:i:s');
                break;
            case 3:
                //$dtf = \Carbon\Carbon::now('IST')->subDays(31);
                //$dto = \Carbon\Carbon::now('IST');
                $dtf =  \Carbon\Carbon::now('IST')->startOfMonth()->subMonth();
                $dto =  \Carbon\Carbon::now('IST')->subMonth()->endOfMonth();
                $time =  $dtf->format('Y-m-d H:i:s');
                $timeto = $dto->format('Y-m-d H:i:s');  
                /*$dtf  = \Carbon\Carbon::now('IST')->subDay(31);
                $dto  = \Carbon\Carbon::now('IST');
                $time =  $dtf->format('Y-m-d H:i:s');
                $timeto = $dto->format('Y-m-d H:i:s');*/
                break;
            case 4:
                $dtf = \Carbon\Carbon::now('IST');
                $dto = \Carbon\Carbon::now('IST')->subYear()->endOfMonth();
                $timeto =  $dtf->format('Y-m-d H:i:s');
                $time = $dto->format('Y-m-d H:i:s');
                break;  
            case 5:
                $timeF = \Carbon\Carbon::parse($date_from)->format('Y-m-d');
                $timetoF = \Carbon\Carbon::parse($date_to)->format('Y-m-d');

                $time = \Carbon\Carbon::parse($date_from)->format('Y-m-d H:i:s');
                $timeto = \Carbon\Carbon::parse($date_to)->format('Y-m-d H:i:s');

                $year1 = date('Y', strtotime($timetoF));
                $year2 = date('Y', strtotime($timeF));

                $month1 = date('m', strtotime($timetoF));
                $month2 = date('m', strtotime($timeF));

                $diffMonths = abs((($year2 - $year1) * 12) + ($month2 - $month1));
                $diff_in_days = Carbon::parse( $time )->diffInDays( $timeto );
                $diff_in_hours = Carbon::parse( $time )->diffInHours( $timeto );
                //$diffMonths = (strtotime($timetoF) - strtotime($timeF)) / (60 * 60 * 24);
                //\Log::info(print_r([$diff], true));
                break;
        }     
        if($ttype <= 4){
            $query = "SELECT licence_id, ". $time1." as time ";
            $query .=  ", sum(ccu) as sumccu ".
                    ", sum(ccu) as avgsumccu "
                 . " from josgt_licences_ccu_history where licence_id = ".$lic->id ." AND time > '".$time."'  AND time < '".$timeto."' ";     
        }else{
            $query = "SELECT licence_id, ". $time1." as time ";
            $query .=  ", sum(ccu) as sumccu ".
                    ", sum(ccu) as avgsumccu "
                 . " from josgt_licences_ccu_history where licence_id = ".$lic->id ." AND time between '".$time."' AND '".$timeto."' ";     
        }
        
        //\Log::info(print_r($query, true));     
        $licencesCcuCount = DB::select($query);
        //\Log::info(print_r($licencesCcuCount, true));  
             

        //\Log::info(print_r($sql, true));           
        $licencesCcu = DB::select($sql);

        if(count($licencesCcu)==0){
             $table['cols'][] =  array(
                'label' => $lic->licence_key, 
                'type' => 'number'
            ); 
             
            $sub_array = array();
            $sub_array[] =  array(
                "v" => 'Date('.$dtf->format('Y,m,d,H,m,s').')'
            );
             $sub_array[] =  array(
                "v" => 0
            );
             $rows[] =  array(
                "c" => $sub_array
            );
            $sub_array = array();
            $sub_array[] =  array(
                "v" => 'Date('.$dto->format('Y,m,d,H,m,s').')'
            );
            $sub_array[] =  array(
                "v" => 0
            );
            $rows[] =  array(
                "c" => $sub_array
            );
            //$table['rows'] = $rows;
            $table[$lic->licence_key]['rows'][] = $rows;
            //echo json_encode($table, JSON_PRETTY_PRINT);
            //die();
        }
        
        $table['cols'][] =  array(
            'label' => ' SUM ', 
            'type' => 'number'
        );
        $table['cols'][] =  array(
            'label' => ' AVG ', 
            'type' => 'number'
        );
        $ii=0;
        $ccuSum = 0;
        //\Log::info(print_r($licencesCcu, true));
        foreach ($licencesCcu as $k => $ccuh){
            //$dt = explode("-", $ccuh['time']);
            $dt = explode("-", $ccuh->time);
            $sub_array = array();
            $sub_array[] =  array(
                "v" => 'Date('.$dt[0].','.$dt[1].','.$dt[2].','.$dt[3].','.$dt[4].','.$dt[5].')'
            );
            $sum = 0;   

            foreach ($licProxyIds as $proxy_id){
                $pstr = 'proxy_'.$proxy_id->proxy_id;
                $mstr = 'max_proxy_'.$proxy_id->proxy_id;
                $sub_array[] =  array(
                    "v" => $ccuh->$pstr
                );
                
                $s = (is_numeric($ccuh->$mstr) ? (int)$ccuh->$mstr : 0);
                $sum += $s;
            }
            $sub_array[] =  array(
                "v" => $sum
            );
            $sub_array[] =  array(
                "v" => $ccuh->avgsumccu
            );
            $ccuSum = $ccuSum+$ccuh->sumccu;
            $rows[] =  array(
                "c" => $sub_array,
                "sum" => $ccuSum
            );
        }      

        //$table['rows'] = $rows;
        $table[$lic->licence_key]['rows'][] = $rows;
        //\Log::info(print_r(end($rows->sum), true));
        /*if(isset($lic->id)){
            $query = "SELECT licence_id, ". $time1." as time ";
            $query .=  ", sum(ccu) as sumccu ".
                    ", sum(ccu) as avgsumccu "
                 . " from josgt_licences_ccu_history where licence_id = ".$lic->id ." AND time between '".$time."' AND '".$timeto."' ";
            $licencesCcuCount = DB::select($query);
            
            foreach($licencesCcuCount as $key => $licencesCcuCountData){
                $chartDataArr = ['name'=>$key,'data'=>[@$licencesCcuCountData[$key]['sumccu']]];
            }
            \Log::info(print_r($chartDataArr, true));    
            
        }*/
        
        //\Log::info(print_r($table, true));   
        $chartData = [];
        if($licencesCcuCount){
            foreach($licencesCcuCount as $key => $value){
                $chartData = ['name'=>$lic->licence_key,'data'=>[$value->sumccu]];
            }    
        }
        
        /*foreach($table as $key => $value){
            
            if($key != 'cols'){
                unset($value['rows'][0][0]['c'][0]);
                $summ = 0;
                //$chartData = ['name'=>$key,'data'=>[$value['rows'][0][0]['c'][1]['v']]];
                if(count($value['rows'][0][0]['c']) > 0){
                    for($i=0;$i<count($value['rows'][0][0]['c']);$i++){
                        //$summ = $value['rows'][0][0]['c'][$i+1]['v'];
                        if($licencesCcuCount[$key]['sumccu'] > 0){
                            $summ = $licencesCcuCount[$key]['sumccu'];    
                        }else{
                            $summ = 1200;
                        }
                        
                        //\Log::info(print_r(last($value['rows'][0][$i]['sum']), true));   
                    }
                    $chartData = ['name'=>$key,'data'=>[$summ]];    
                }
                
            }
        }*/
        //\Log::info(print_r($chartData, true)); 
        $to = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $dto->format('Y-m-d H:i:s'));
        $from = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $dtf->format('Y-m-d H:i:s'));
        if($add == 30 || $add == 60){
            $diff_in_hours = $to->diffInHours($from)*2;
            for($i=0;$i<$diff_in_hours;$i++){
                $chartData['xaxis']['categories'][] = $from->addMinutes($add)->format('H:i');
            }    
            if($add == 30){
                $length = 7;    
                for($i=0;$i<$length;$i++){
                    array_push($chartData['data'], 0);
                }    
            }elseif($add == 60){
                $length = 23;
                for($i=0;$i<$length;$i++){
                    array_push($chartData['data'], 0);
                }
            }
        }else if($add == 7){
            $diff_in_days = $to->diffInDays($from);
            for($i=0;$i<$diff_in_days;$i++){
                $chartData['xaxis']['categories'][] = $from->addDays()->format('Y-m-d');
            }   
            $length = 6;
            for($i=0;$i<$length;$i++){
                array_push($chartData['data'], 0);
            }   
            sort($chartData['xaxis']['categories']);
        }else if($add == 31){
            $diff_in_days = $to->diffInDays($from);
            for($i=0;$i<=$diff_in_days;$i++){
                //$chartData['xaxis']['categories'][] = $from->addDays($i)->format('Y-m-d');
                /*$dtf  = \Carbon\Carbon::now('IST');
                $dto  = \Carbon\Carbon::now('IST')->subDays($i+1);
                $time =  $dtf->format('Y-m-d H:i:s');
                $timeto = $dto->format('Y-m-d H:i:s');
                $chartData['xaxis']['categories'][] = $time;
                $chartData['xaxis']['categories'][] = $timeto;
                */
                $dto  = \Carbon\Carbon::parse($time)->addDays($i);
                $timeto = $dto->format('Y-m-d');   
                $chartData['xaxis']['categories'][] = $timeto; 
            }
            $length = $diff_in_days;
            for($i=0;$i<$length;$i++){
                array_push($chartData['data'], 0);
            }   
            sort($chartData['xaxis']['categories']);
        }else if($add == 12){
            $diff_in_days = 12;
            $chartData['xaxis']['categories'][] = $from->format('Y-m-d');
            for($i=0;$i<$diff_in_days;$i++){
                $chartData['xaxis']['categories'][] = ($i == 0) ? $from->format('Y-m-d') : $from->subMonth()->format('Y-m-d');
            }
            $length = 12;
            for($i=0;$i<$length;$i++){
                array_push($chartData['data'], 0);
            }   
            sort($chartData['xaxis']['categories']);
        }else if($add == "custom"){
            $j = 1;
            if($diffMonths >= 1){
                $length = $diffMonths-$j;
                for($i=0;$i<$diffMonths;$i++){
                    /*$dtf  = \Carbon\Carbon::now('IST')->subDays($i+1);
                    $dto  = \Carbon\Carbon::now('IST')->addDays($i+1);
                    $time =  $dtf->format('Y-m-d H:i:s');
                    $timeto = $dto->format('Y-m-d H:i:s');*/
                    $time = \Carbon\Carbon::parse($date_from)->format('Y-m-d H:i:s');
                    $timeto = \Carbon\Carbon::parse($date_to)->format('Y-m-d H:i:s');
                    $chartData['xaxis']['categories'][] = $time;
                    $chartData['xaxis']['categories'][] = $timeto;
                }    
                for($i=0;$i<$diffMonths;$i++){
                    array_push($chartData['data'], 0);
                }
                sort($chartData['xaxis']['categories']);
            }else{
                if($diff_in_days > 1){
                    for($i=0;$i<=$diff_in_days;$i++){
                        $dto  = \Carbon\Carbon::parse($date_from)->addDays($i);
                        $timeto = $dto->format('Y-m-d H:i:s');
                        //$chartData['xaxis']['categories'][] = $time;
                       $chartData['xaxis']['categories'][] = $timeto;
                    }   
                    $length = $diff_in_days;
                    for($i=0;$i<$length;$i++){
                        array_push($chartData['data'], 0);
                    }   
                    sort($chartData['xaxis']['categories']);       
                }else{
                    $diff_in_hours = Carbon::parse( $time )->diffInHours( $timeto );
                    for($i=0;$i<$diff_in_hours;$i++){
                        $time = \Carbon\Carbon::parse($date_from)->format('Y-m-d H:i:s');
                        $timeto = \Carbon\Carbon::parse($date_to)->format('Y-m-d H:i:s');
                        $chartData['xaxis']['categories'][] = $time;
                        $chartData['xaxis']['categories'][] = $timeto;
                    }   
                    for($i=0;$i<$diff_in_hours;$i++){
                        array_push($chartData['data'], 0);
                    }  
                }
            }
        }
        $chartData['licid'][] = $lic->id;
        $chartData['time'] = @$time;
        $chartData['timeto'] = @$timeto;
        $chartData['diffMonths'] = @$diffMonths;
        $chartData['diff_in_days'] = @$diff_in_days;
        $chartData['diff_in_hours'] = @$diff_in_hours;
        
        return $chartData;
    }

    public function userFilter(){
        $roles = Role::where('id','!=',1)
                     ->get();
        $response = ['error'=>new \stdClass(),'status' => 'true','data'=> ['roles' => $roles]]; 
        return response($response, 200);
    }

    public function changeTheme(Request $request){
        $data = $request->all();
        $userId = $data['user_id'];
        $theme = $data['theme'];
        $user = User::find($userId);
        $user->theme = $theme;
        $user->save();        
        $response = ['error'=>new \stdClass(),'status' => 'true','data'=> ['user_id' => $userId,'theme' => $theme]]; 
        return response($response, 200);
    }

    public function changeScheme(Request $request){
        $data = $request->all();
        $userId = $data['user_id'];
        $scheme = $data['scheme'];
        $user = User::find($userId);
        $user->scheme = $scheme;
        $user->save();        
        $response = ['error'=>new \stdClass(),'status' => 'true','data'=> ['user_id' => $userId,'scheme' => $scheme]]; 
        return response($response, 200);
    }
}
