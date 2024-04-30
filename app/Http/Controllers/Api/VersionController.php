<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Release;
use App\Download;

class VersionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $versions = Release::orderBy('id','DESC')->paginate($request->get('paginate'));
        $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','data'=> ['current_page' => $versions->currentPage(),'per_page' => $versions->perPage(),'total' => $versions->total(),'versions' => $versions->values()]];
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
        if($request->get('releaseId') != "undefined"){
            $release = Release::find($request->get('releaseId'));
        }else{
            $release = new Release();
        }

        if($request->get('releaseId') != "undefined"){
            $validator = Validator::make($request->all(), [
                'name' => 'required|unique:josgt_releases,name,'.$release->id.',id',
                'version' => 'required|unique:josgt_releases,version,'.$release->id.',id',
                'release_date' => 'required'
            ]);
        }else{
            $validator = Validator::make($request->all(), [
                'name' => 'required|unique:josgt_releases,name',
                'version' => 'required|unique:josgt_releases,version',
                'release_date' => 'required'
            ]);
        }

        if ($validator->fails())
        {
            $errors = $validator->errors()->all();
            return response(['message'=>$errors[0]], 422);
        }

        \DB::beginTransaction();
        try {
                $release->name = $request->get('name');
                $release->version = $request->get('version');
                $release->release_date = Carbon::createFromFormat('D M d Y H:i:s e+',$request->release_date)->format('Y-m-d H:i:s');
                $release->server_key = ($request->get('server_key') ? $request->get('server_key') : "");
                $release->hash = '';
                $release->server_salt = '';
                $release->save();

                \DB::commit();
                if($request->get('releaseId') != "undefined"){
                    $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Release updated successfully']; 
                }else{
                    $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Release added successfully']; 
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
    public function show()
    {
        $versions = Release::where('show',1)->orderBy('id','DESC')->get();
        $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','data'=> ['versions' => $versions]];
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
        $release = Release::find($id);
        if($release){
            $release_date = Carbon::createFromFormat('Y-m-d H:i:s',$release->release_date)->format('Y-m-d H:i:s');
            $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','data'=> ['release' => $release,'release_date' => $release_date]];
            return response($response, 200);
        }else{
            $response = ['error'=>'error','status' => 'false','message' => 'Release not found.'];   
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
        $release = Release::find($id);
        if($release){
            Download::where('release_version',$release->version)->delete();
            $release->delete();
            $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Release & its downloads deleted successfully.']; 
            return response($response, 200);
        }
        else{
            $response = ['error'=>'error','status' => 'false','message' => 'Release not found.'];   
            return response($response, 422);
        }
    }

    public function changeStatus(Request $request){
       $release = Release::find($request->get('id'));
       if($release){
        $release->show = $request->get('status');
        $release->save();
        $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Release status changed successfully.']; 
        return response($response, 200);
       } 
       else{
        $response = ['error'=>'error','status' => 'false','message' => 'Release not found.'];
        return response($response, 422);
       }
    }
}
