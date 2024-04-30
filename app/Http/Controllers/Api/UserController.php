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
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Auth;
use DB;

class UserController extends Controller
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

        $users = Employee::select('*')
                        ->when($request->has('search') && $request->get('search') != '',function($q) use ($request){
                                    $q->where(function ($query) use ($request){
                                        return $query->where('email','LIKE','%'.$request->get('search').'%')
                                                    ->orWhere('username','LIKE','%'.$request->get('search').'%');
                                    });
                            })
                        ->when($auth_user->role_id == 3,function($q2) use ($request,$userId){
                            return $q2->where('owner_id',$userId);
                        })
                        ->when($request->get('status') == 1 || $request->get('status') == 0,function($q2) use ($request){
                            return $q2->where('block',$request->get('status'));
                        })
                        ->orderBy('id','DESC')
                        ->paginate($request->get('paginate'));                
                     
        $users_detail = $users->map(function($user){
            return collect([
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'registered_since' => $user->registerDate,
                'block' => $user->block
            ]);
        });
        
        $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','data'=> ['current_page' => $users->currentPage(),'per_page' => $users->perPage(),'total' => $users->total(),'users' => $users_detail]];
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
            $user = Employee::find($request->get('userId'));
        }else{
            $user = new Employee();
        }
        $data = $request->all();

        if($request->get('userId') != "undefined"){
                $validator = Validator::make($request->all(), [
                    'username' => 'required|unique:josgt_users,username,'.$user->id.',id',
                    'name' => 'required',
                    'email' => 'required|unique:josgt_users,email,'.$user->id.',id'
            ]);
        }else{
            $validator = Validator::make($request->all(), [
                'username' => 'required|unique:josgt_users,username',
                'name' => 'required',
                'email' => 'required|unique:josgt_users,email'
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
            $user->owner_id = $request->get('loggedin_id');
            $user->username = $request->get('username');
            $user->name = $request->get('name');
            $user->email = $request->get('email');
            $user->password = bcrypt($request->get('password'));
            $user->save();

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
        $user = Employee::find($id);
        if($user){
            $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','data'=> ['user' => $user]];
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
        $user = Employee::find($id);
        if($user){
            $user->delete();
            $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Employee deleted successfully.']; 
            return response($response, 200);
        }
        else{
            $response = ['error'=>'error','status' => 'false','message' => 'Employee not found.']; 
            return response($response, 422);
        }
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
            $usersCounts = User::whereIn('role_id',[30,2])->get()->count();
            $employeesAdminsCounts = User::whereIn('role_id',[30,2])->where('block',0)->get()->count();
            $releasesCounts = Release::get()->count();
            $downloadsCounts = Download::get()->count();
            $activeCounts = Download::where('active',1)->get()->count();
        }else{
            $accountsCounts = User::where('role_id',3)->get()->count();
            $coustomersActiveCounts = User::where('role_id',3)->where('block',0)->get()->count();
            $usersCounts = User::whereIn('role_id',[30,2])->get()->count();
            $employeesAdminsCounts = User::whereIn('role_id',[30,2])->where('block',0)->get()->count();
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

    public function changeStaus(Request $request){
       $user = Employee::find($request->get('user_id'));
       if($user){
        $user->block = $request->get('status');
        $user->save();
        $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Employee status changed successfully.']; 
        return response($response, 200);
       } 
       else{
        $response = ['error'=>'error','status' => 'false','message' => 'Employee not found.'];
        return response($response, 422);
       }
    }

    public function getChartData(Request $request){
        $role_id = $request->get('role_id');

        $dataArr = [];
        $registerDateArr = [];
        $customers_joined_data_arr = [];
        $customers_active_data_arr = [];
        if($role_id == 1){
            $customersJoined = User::select(DB::raw('count(id) as `data`'), DB::raw("DATE_FORMAT(registerDate, '%d %b %Y') register_date"),  DB::raw('YEAR(registerDate) year, MONTH(registerDate) month'))
                ->groupBy('month','year')
                ->get();
            $customersActive = User::select(DB::raw('count(id) as `data`'), DB::raw("DATE_FORMAT(lastvisitDate, '%d %b %Y') last_visit_date"),  DB::raw('YEAR(lastvisitDate) year, MONTH(lastvisitDate) month'))
                ->groupBy('month','year')
                ->get();       
        }else{
            $customersJoined = User::select(DB::raw('count(id) as `data`'), DB::raw("DATE_FORMAT(registerDate, '%d %b %Y') register_date"),  DB::raw('YEAR(registerDate) year, MONTH(registerDate) month'))
                ->groupBy('month','year')
                ->get();
            $customersActive = User::select(DB::raw('count(id) as `data`'), DB::raw("DATE_FORMAT(lastvisitDate, '%d %b %Y') last_visit_date"),  DB::raw('YEAR(lastvisitDate) year, MONTH(lastvisitDate) month'))
                ->groupBy('month','year')
                ->get();
        }

        foreach($customersJoined as $key => $value){
            if($value->register_date != null){
                array_push($registerDateArr, $value->register_date);    
            }
            array_push($customers_joined_data_arr, $value->data);
        }

        foreach($customersActive as $key => $value){
            array_push($customers_active_data_arr, $value->data);
        }

        $registerDateArr = implode('","',$registerDateArr);
        $customers_joined_data_arr = implode('","',$customers_joined_data_arr); 
        $customers_active_data_arr = implode('","',$customers_active_data_arr); 
        //$dataArr['customers_joined'] = $customersJoined;
        //$dataArr['last_visite_date'] = $customersActive;
        $dataArr['registerDateArr'] = $registerDateArr;
        $dataArr['customers_joined_data_arr'] = $customers_joined_data_arr;
        $dataArr['customers_active_data_arr'] = $customers_active_data_arr;

        $response = ['error'=>new \stdClass(),'status' => 'true','data'=> [$dataArr, 'role_id' => $role_id]]; 
        return response($response, 200);
    }

    public function userFilter(){
        $roles = Role::where('id','!=',1)
                     //->where('id','!=',3)
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
