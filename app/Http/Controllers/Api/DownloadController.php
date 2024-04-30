<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Release;
use App\Licence;
use App\Download;
use App\DownloadLogs;
use App\User;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use DB;

class DownloadController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //dd($request->all());
        $releases = Release::where('show',1)->orderBy('release_date', 'DESC')->get();
        $latest = $releases->first();
        $auth_user = \Auth::user();
        $user_role = User::with('userRole')->find($auth_user->id)->role;
        if($request->get('downloadType') == 'archived'){
            if($request->get('type') == 'Disabled'){
                $licences = Licence::where("user_id",$auth_user->id)->where('enabled',1)->where('convert_to','<',0)->get();
                $downloads_count = Download::where('active',0)
                  ->where(function ($query) use ($latest,$request){
                      //dd($request->all());
                      $query->where('release_version','!=',$latest->version)
                            ->when($request->get('search') != "all" && $request->get('search') != null, function ($q) use ($request){
                                //return $q->where('release_version',$request->get('search'));
                                return $q->where('release_version','like','%'.$request->get('search').'%');
                            });
                  })
                  ->where('category',$request->get('type'))
                  ->orderBy('name')->get()->count();
                $downloads = Download::where('active',0)
                            ->where(function ($query) use ($latest,$request){
                                $query->where('release_version','!=',$latest->version)
                                    ->when($request->get('search') != "all" && $request->get('search') != null, function ($q) use ($request){
                                        //$q->where('release_version',$request->get('search'));
                                        $q->where('release_version','like','%'.$request->get('search').'%');
                                    });
                            })
                            ->where('category',$request->get('type'))
                            ->orderBy('name', 'asc')
                            ->paginate($request->get('paginate'));   
                            $result = [];
                                foreach ($downloads as $download){
                                    $licence_found = false;
                                    foreach ($licences as $licence) {
                                        $licenceReqs = explode(",", $download->licence_req);
                                        foreach ($licenceReqs as $licenceReq) {
                                            if (strpos($licence->licence_type, $licenceReq) !== false) {
                                                if ($licence->maintenance_expire > $download->release_date_show){
                                                    $licence_found = true;    
                                                }
                                            }
                                        }
                                    }
                                    
                                    if ($licence_found) {
                                        $result[] = $download;
                                    }
                                }                         
            }else{
                if($auth_user->role_id == 1){
                    $downloads = Download::where('active',1)
                      ->where(function ($query) use ($latest,$request){
                          //dd($request->all());
                          $query->where('release_version','!=',$latest->version)
                                ->when($request->get('search') != "all" && $request->get('search') != null, function ($q) use ($request){
                                    //return $q->where('release_version',$request->get('search'));
                                    return $q->where('release_version','like','%'.$request->get('search').'%');
                                });
                      })
                      ->where('category',$request->get('type'))
                      ->orderBy('name')
                      ->paginate($request->get('paginate'));
                      $downloads_count = Download::where('active',1)
                      ->where(function ($query) use ($latest,$request){
                          //dd($request->all());
                          $query->where('release_version','!=',$latest->version)
                                ->when($request->get('search') != "all" && $request->get('search') != null, function ($q) use ($request){
                                    //return $q->where('release_version',$request->get('search'));
                                    return $q->where('release_version','like','%'.$request->get('search').'%');
                                });
                      })
                      ->where('category',$request->get('type'))
                      ->orderBy('name')->get()->count();
                }else{
                    $licences = Licence::where("user_id",$auth_user->id)->where('enabled',1)->where('convert_to','<',0)->get();
                    $downloads = Download::where('active',1)
                    ->where(function ($query) use ($latest,$request){
                        $query->where('release_version','!=',$latest->version)
                            ->when($request->get('search') != "all" && $request->get('search') != null, function ($q) use ($request){
                                //$q->where('release_version',$request->get('search'));
                                $q->where('release_version','like','%'.$request->get('search').'%');
                            });
                    })
                    ->where('category',$request->get('type'))
                    ->orderBy('name')
                    ->paginate($request->get('paginate'));
                    $downloads_count = Download::where('active',1)
                    ->where(function ($query) use ($latest,$request){
                        $query->where('release_version','!=',$latest->version)
                            ->when($request->get('search') != "all" && $request->get('search') != null, function ($q) use ($request){
                                //$q->where('release_version',$request->get('search'));
                                $q->where('release_version','like','%'.$request->get('search').'%');
                            });
                    })
                    ->where('category',$request->get('type'))
                    ->orderBy('name')->get()->count();
                    $result = [];
                    foreach ($downloads as $download){
                        $licence_found = false;
                        foreach ($licences as $licence) {
                            $licenceReqs = explode(",", $download->licence_req);
                            foreach ($licenceReqs as $licenceReq) {
                                if (strpos($licence->licence_type, $licenceReq) !== false) {
                                    if ($licence->maintenance_expire > $download->release_date_show){
                                        $licence_found = true;    
                                    }
                                }
                            }
                        }
                        
                        if ($licence_found) {
                            $result[] = $download;
                        }
                    }
                }
            }
        }else if($request->get('downloadType') == 'latest'){
            //dd("here");
            if($request->get('type') == 'Disabled'){
                /*$downloads = Download::where('active',0)
                                    ->where(function ($query) use ($latest){
                                        $query->where('release_version',$latest->version)
                                            ->orWhere('release_version','all')
                                            ->orWhere('release_version','');
                                    })
                                    ->orderBy('name', 'asc')
                                    ->paginate($request->get('paginate'));*/
                $licences = Licence::where("user_id",$auth_user->id)->where('enabled',1)->where('convert_to','<',0)->get();                                    
                $downloads = Download::where('active',0)
                ->where(function ($query) use ($latest,$request){
                    $query->where('release_version','!=',$latest->version)
                        ->when($request->get('search') != "all" && $request->get('search') != null, function ($q) use ($request){
                            //$q->where('release_version',$request->get('search'));
                            $q->where('release_version','like','%'.$request->get('search').'%');
                        });
                })
                ->orderBy('name', 'asc')
                ->paginate($request->get('paginate'));
                $downloads_count = Download::where('active',0)
                ->where(function ($query) use ($latest,$request){
                    $query->where('release_version','!=',$latest->version)
                        ->when($request->get('search') != "all" && $request->get('search') != null, function ($q) use ($request){
                            //$q->where('release_version',$request->get('search'));
                            $q->where('release_version','like','%'.$request->get('search').'%');
                        });
                })
                ->orderBy('name', 'asc')->get()->count();
                $result = [];
                foreach ($downloads as $download){
                    $licence_found = false;
                    foreach ($licences as $licence) {
                        $licenceReqs = explode(",", $download->licence_req);
                        foreach ($licenceReqs as $licenceReq) {
                            if (strpos($licence->licence_type, $licenceReq) !== false) {
                                if ($licence->maintenance_expire > $download->release_date_show){
                                    $licence_found = true;    
                                }
                            }
                        }
                    }
                    
                    if ($licence_found) {
                        $result[] = $download;
                    }
                }                                    
            }else{
                if($auth_user->role_id == 1){
                    $downloads = Download::where('active',1)
                    ->where('category',$request->get('type'))
                    //->where('release_version',$latest->version)
                    ->where('release_version','like','%'.$latest->version.'%')
                    ->orderBy('name')
                    ->paginate($request->get('paginate'));
                    $downloads_count = Download::where('active',1)
                    ->where('category',$request->get('type'))
                    //->where('release_version',$latest->version)
                    ->where('release_version','like','%'.$latest->version.'%')
                    ->orderBy('name')->get()->count();
                }else{
                    $licences = Licence::where("user_id",$auth_user->id)->where('enabled',1)->where('convert_to','<',0)->get();
                    $downloads = Download::where('active',1)
                    ->where(function ($query) use ($latest,$request){
                        $query->where('release_version','like','%'.$latest->version.'%')
                            //->where('release_version',$latest->version)
                            ->orWhere('release_version','all')
                            ->orWhere('release_version','');
                    })
                    ->where('category',$request->get('type'))
                    ->orderBy('name')
                    ->paginate($request->get('paginate'));
                    $downloads_count = Download::where('active',1)
                    ->where(function ($query) use ($latest,$request){
                        $query->where('release_version','like','%'.$latest->version.'%')
                            //->where('release_version',$latest->version)
                            ->orWhere('release_version','all')
                            ->orWhere('release_version','');
                    })
                    ->where('category',$request->get('type'))
                    ->orderBy('name')
                    ->get()->count();               
                    $result = [];
                    foreach ($downloads as $download){
                        $licence_found = false;
                        foreach ($licences as $licence) {
                            $licenceReqs = explode(",", $download->licence_req);
                            foreach ($licenceReqs as $licenceReq) {
                                if (strpos($licence->licence_type, $licenceReq) !== false) {
                                    if ($licence->maintenance_expire > $download->release_date_show){
                                        $licence_found = true;   
                                    }
                                }
                            }
                        }
                        if ($licence_found) {
                            $result[] = $download;
                        }
                    }
                }
            }
        }
        if($auth_user->role_id != 1){
            $access = collect($result)->pluck('id');
            $downloads = $downloads->map(function($download) use($access,$request){
                $permission = $access->contains($download->id);
                return collect([
                    'id' => $download->id,
                    'name' => $download->name,
                    'description' => $download->description,
                    'release_date_show' => $download->release_date_show,
                    'active' => $download->active,
                    'dropbox' => $download->dropbox,
                    'download_access' => $permission
                ]);
            });
            $total = count($downloads);
            $perPage = $request->get('paginate'); // How many items do you want to display.
            $currentPage = $request->get('page');
            $downloads = new LengthAwarePaginator($downloads, $total, $perPage, $currentPage);
        }
        $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','data'=> ['current_page' => $downloads->currentPage(),'per_page' => $downloads->perPage(),'total' => $downloads->total(),'downloads' => $downloads->values(),'releases'=>$releases,'user_role' => $user_role,'downloads_count'=>$downloads_count]];
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
        $versionArr = [];
        $licenceArr = [];
        if($request->has('release_version')){
            $release_version = json_decode($data['release_version']);
            if(is_array($release_version) && count($release_version)>0){
                foreach($release_version as $key => $value){
                    $versionArr[] = $value->version;
                }
                $versionImp = implode("|", $versionArr);    
            }else{
                $versionImp = $release_version->version;
            }
        }

        /*if($request->has('licences')){
            if(strpos(",", $data['licences']) == false){
                $licenceArr = explode(",", $data['licences']);    
            }else{
                $licenceArr = $data['licences'];
            }
            $auth_user = \Auth::user();
            $authId = $auth_user->id;   
        }*/ 

        $release_version = "";
        if($request->get('downloadId') != "undefined"){
            $download = download::find($request->get('downloadId'));
            //$release_version = $download->release_version;
        }else{
            $download = new Download();
        }
        if($request->get('downloadId') == "undefined"){
            $validator = Validator::make($request->all(), [
                'name' => 'required|unique:josgt_downloads,name',
                'category' => 'required',
                'licences' => 'required',
                'dropbox' => 'required|unique:josgt_downloads,dropbox',
                'description' => 'required',
                'release_date' => 'required',
                'release_date_show' => 'required',
                'release_version' => 'required'
            ]);
        }else{
            $validator = Validator::make($request->all(), [
                'name' => 'required|unique:josgt_downloads,name,'.$download->id.',id',
                'licences' => 'required',
                'dropbox' => 'required|unique:josgt_downloads,dropbox,'.$download->id.',id',
                'description' => 'required',
                'release_date' => 'required',
                'release_date_show' => 'required',
            ]);
        }

        if ($validator->fails())
        {
            $errors = $validator->errors()->all();
            return response(['message'=>$errors[0]], 422);
        }

        \DB::beginTransaction();
        try {
            $download->name = $request->get('name');
            $download->category = $request->get('category');
            $download->licence_req = $request->get('licences');
            $download->dropbox = $request->get('dropbox');
            $download->description = $request->get('description');
            //$download->release_date = Carbon::createFromFormat('D M d Y H:i:s e+',$request->release_date)->format('Y-m-d');
            //$download->release_date_show = Carbon::createFromFormat('D M d Y H:i:s e+',$request->release_date_show)->format('Y-m-d');
            $download->release_date = Carbon::createFromFormat('D M d Y H:i:s e+',$request->release_date)->format('Y-m-d H:i:s');
            $download->release_date_show = Carbon::createFromFormat('D M d Y H:i:s e+',$request->release_date_show)->format('Y-m-d H:i:s');
            //$download->release_version = $request->get('release_version');
            /*if($request->has('release_version')){
                $download->release_version = $request->get('release_version');
            }else{
                $download->release_version = $release_version;
            }*/
            $download->release_version = $versionImp;
            //$download->version_id = $request->get('version_id');
            $download->active = $request->get('active');
            $download->save();
            
            \DB::commit();
            if($request->get('downloadId') != "undefined"){
                $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Download updated successfully']; 
            }else{
                $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Download added successfully']; 
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
        $download = Download::find($id);
        if($download){
            $licences =  explode(',', $download->licence_req);
            $release_date = Carbon::createFromFormat('Y-m-d H:i:s',$download->release_date)->format('m/d/Y H:i:s');
            $release_date_show = Carbon::createFromFormat('Y-m-d H:i:s',$download->release_date_show)->format('m/d/Y H:i:s');
            $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','data'=> ['download' => $download,'licences' => $licences,'release_date' => $release_date,'release_date_show' => $release_date_show]];
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
    public function destroy($id)
    {
        $download = Download::find($id);
        if($download){
            $download->delete();
            $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Download deleted successfully.']; 
            return response($response, 200);
        }
        else{
            $response = ['error'=>'error','status' => 'false','message' => 'Download not found.'];   
            return response($response, 422);
        }
    }

    public function changeStaus(Request $request){
        $download = Download::find($request->get('download_id'));
        if($download){
         $download->active = $request->get('status');
         $download->save();
         $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Download status changed successfully.']; 
         return response($response, 200);
        } 
        else{
         $response = ['error'=>'error','status' => 'false','message' => 'Download not found.'];   
         return response($response, 422);
        }
    }

    public function downloadLog(Request $request){
        $data = $request->all();
        $user_id = $data['user_id'];
        $download_id = $data['download_id'];
        $version = $data['version'];
        $auth_user = \Auth::user();
        $id = $auth_user->id;
        $downloadLogs = new DownloadLogs();
        $downloadLogs->user_id = $user_id;
        $downloadLogs->download_id = $download_id;
        $downloadLogs->version = $version;
        $downloadLogs->download_date = now();
        $downloadLogs->save();
        $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Download logs added successfully.']; 
        return response($response, 200);
    }

    public function downloadLogList(Request $request){
        $id = $request->get('id');
        $downloadLogs = DownloadLogs::with('user')
                ->when($request->has('search') && $request->get('search') != '',function($q) use ($request){
                            /*$q->whereHas('user',function($query) use ($request){
                                //$query->where('user_id',$request->get('id'));
                                $query->orWhere('username','LIKE','%'.$request->get('search').'%');
                                //$query->where('user_id',$request->get('id'));
                                
                            });*/
                            //$q->where('user_id',$request->get('id'));
                            $q->orWhere('version','LIKE','%'.$request->get('search').'%');
                            //$q->orWhere('download_date','LIKE','%'.$request->get('search').'%');
                            //$q->orWhere('user_id',$request->get('id'));
                    })
                ->when($request->has('order') && $request->get('order') != '' && $request->has('direction') && $request->get('direction') != '',function($q) use ($request){
                    $q->orderBy($request->get('order'),$request->get('direction'));
                })
                ->where('user_id',$id)  
                ->orderBy('created_at','DESC')
                ->paginate($request->get('paginate'));
                //->toSql();
                //dd($downloadLogs);
        //dd($downloadLogs);
        /*$downloadLogs = DownloadLogs::with('user')
                ->when($request->has('search') && $request->get('search') != '',function($q) use ($request){
                            $q->whereHas('user',function($query) use ($request){
                                $query->orWhere('username','LIKE','%'.$request->get('search').'%');
                                //$query->where('user_id',$request->get('id'));
                                //$query->where('user_id',$request->get('id'));
                            });
                            $q->orWhere('version','LIKE','%'.$request->get('search').'%');
                            $q->orWhere('download_date','LIKE','%'.$request->get('search').'%');
                            //$q->where('user_id',$request->get('id'));
                    })
                ->when($request->has('order') && $request->get('order') != '' && $request->has('direction') && $request->get('direction') != '',function($q) use ($request){
                    $q->orderByRaw($request->get('order'),$request->get('direction'));
                })
                ->where('user_id',$id)  
                ->paginate($request->get('paginate'));*/
                //->toSql();
        //dd($downloadLogs);
        
        $users_detail = $downloadLogs->map(function($download){
            return collect([
                'id' => $download->id,
                'user_id' => $download->user->username,
                'download_id' => $download->download_id,
                'version' => $download->version,
                'download_date' => $download->download_date
            ]);
        });
    
        $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','data'=> ['current_page' => $downloadLogs->currentPage(),'per_page' => $downloadLogs->perPage(),'total' => $downloadLogs->total(),'downloads' => $users_detail]];
        return response($response, 200);

    }
}
