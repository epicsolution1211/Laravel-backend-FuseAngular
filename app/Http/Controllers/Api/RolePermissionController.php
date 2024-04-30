<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\RolePermission;

class RolePermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $permissions = RolePermission::all();
        $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','data'=> ['permissions' => $permissions]];
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
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $permissions = RolePermission::where('role_id',$id)->get();
        $permission_array = $permissions->pluck('feature.name');
        $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','data'=> ['permissions' => $permission_array]];
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
        //
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
        //
    }

    public function permissionsByUser($id){
        $permissions = RolePermission::with('feature')->where('role_id',$id)->get();
        $feature = array();
        foreach ($permissions as $key => $value) {
            $feature[] = $value['feature']['name'];
        }
        $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','data'=> ['permissions' => $permissions,'feature'=>$feature]];
        return response($response, 200);
    }
}
