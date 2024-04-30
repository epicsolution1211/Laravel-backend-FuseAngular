<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Role;
use App\User;
use Illuminate\Support\Facades\Validator;
use App\RolePermission;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $roles = Role::with('permissions','permissions.feature')->where('id','!=',1)->get();
        //$permissions = $roles->permissions;
        $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','data'=> ['roles' => $roles]];
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

    public function store(Request $request)
    {
        if($request->get('roleId') == null || $request->get('roleId') == "undefined"){
            $validator = Validator::make($request->all(), [
                'role' => 'required|unique:roles,role',
            ]);
        }else{
            $validator = Validator::make($request->all(), [
                'role' => [
                    'required',
                    Rule::unique('roles','role')->ignore('roleId')
                ],
            ]);
        }
        if ($validator->fails())
        {
            $errors = $validator->errors()->all();
            return response(['message'=>$errors], 422);
        }

        if($request->get('roleId') == null || $request->get('roleId') == "undefined"){
            $data = $request->all();

            $role = new Role();
            $role->role = $request->get('role');
            $role->save();

            $read = json_decode($request->get('readpermissions'), true);
            $readlength = count($read);
            
            $write = json_decode($request->get('writepermissions'), true);
            $writelength = count($write);

            $update = json_decode($request->get('updatepermissions'), true);
            $updatelength = count($update);

            $delete = json_decode($request->get('deletepermissions'), true);
            $deletelength = count($delete);

            $maxlength = max($readlength,$writelength,$updatelength,$deletelength);
            
            $read = collect($read);
            $read_permissions = $read->map(function($read){
                return [
                    'feature_id' => $read,
                    'permission' => 'read'
                ];
            });

            $write = collect($write);
            $write_permissions = $write->map(function($write){
                return [
                    'feature_id' => $write,
                    'permission' => 'write'
                ];
            });

            $read_write_permissions = $read_permissions->merge($write_permissions);
            $update = collect($update);
            $update_permissions = $update->map(function($update){
                return [
                    'feature_id' => $update,
                    'permission' => 'update'
                ];
            });

            $delete = collect($delete);
            $delete_permissions = $delete->map(function($delete){
                return [
                    'feature_id' => $delete,
                    'permission' => 'delete'
                ];
            });
            $update_delete_permissions = $update_permissions->merge($delete_permissions);
            $all_permissions = $read_write_permissions->merge($update_delete_permissions);
            $all_permissions = $all_permissions->groupBy('feature_id');
            $permissions_arr = $all_permissions->map(function($permission){
                $permission = $permission->toArray();
                return array_column($permission,'permission');
            });
            $permission_collection = collect($permissions_arr);
            foreach($permission_collection as $key => $val){
                $role_permission = new RolePermission();
                $role_permission->role_id = $role->id; 
                $role_permission->feature_id =  $key;
                $role_permission->permissions = json_encode($val);
                $role_permission->save();
            }
            $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'New role & permissions added successfully',"data"=>json_encode($data)]; 
            return response($response, 200); 
        }
        else{
            $role = Role::find($request->get('roleId'));
            $role->role = $request->get('role');
            $role->save();

            $role->permissions()->delete();
            $permissions = $request->get('permissions');
            $decodePermissions = json_decode($permissions);
            foreach ($decodePermissions as $key => $value) {
                if(count($value->permissions)){
                    $role_permission = new RolePermission();
                    $role_permission->role_id = $role->id; 
                    $role_permission->feature_id =  $value->id;
                    $role_permission->permissions = json_encode($value->permissions);
                    $role_permission->save();    
                }
            }
            //delete previous permissions
            /*$role->permissions()->delete();

            $read = json_decode($request->get('readpermissions'), true);
            $readlength = count($read);
            
            $write = json_decode($request->get('writepermissions'), true);
            $writelength = count($write);

            $update = json_decode($request->get('updatepermissions'), true);
            $updatelength = count($update);

            $delete = json_decode($request->get('deletepermissions'), true);
            $deletelength = count($delete);

            $maxlength = max($readlength,$writelength,$updatelength,$deletelength);
            
            $read = collect($read);
            $read_permissions = $read->map(function($read){
                return [
                    'feature_id' => $read,
                    'permission' => 'read'
                ];
            });

            $write = collect($write);
            $write_permissions = $write->map(function($write){
                return [
                    'feature_id' => $write,
                    'permission' => 'write'
                ];
            });

            $read_write_permissions = $read_permissions->merge($write_permissions);
            $update = collect($update);
            $update_permissions = $update->map(function($update){
                return [
                    'feature_id' => $update,
                    'permission' => 'update'
                ];
            });

            $delete = collect($delete);
            $delete_permissions = $delete->map(function($delete){
                return [
                    'feature_id' => $delete,
                    'permission' => 'delete'
                ];
            });
            $update_delete_permissions = $update_permissions->merge($delete_permissions);
            $all_permissions = $read_write_permissions->merge($update_delete_permissions);
            $all_permissions = $all_permissions->groupBy('feature_id');
            $permissions_arr = $all_permissions->map(function($permission){
                $permission = $permission->toArray();
                return array_column($permission,'permission');
            });
            $permission_collection = collect($permissions_arr);
            foreach($permission_collection as $key => $val){
                $role_permission = new RolePermission();
                $role_permission->role_id = $role->id; 
                $role_permission->feature_id =  $key;
                $role_permission->permissions = json_encode($val);
                $role_permission->save();
            }*/
            $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Role & permissions updated successfully']; 
            return response($response, 200); 
        }
             
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    // public function store(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'role' => 'required',
    //     ]);
    //     if ($validator->fails())
    //     {
    //         return response(['errors'=>$validator->errors()->all()], 422);
    //     }

    //     if($request->get('roleId') == null || $request->get('roleId') == "undefined"){
    //         $role = new Role();
    //         $role->role = $request->get('role');
    //         $role->save();

    //         $read = json_decode($request->get('readpermissions'), true);
    //         $readlength = count($read);
            
    //         $write = json_decode($request->get('writepermissions'), true);
    //         $writelength = count($write);

    //         $update = json_decode($request->get('updatepermissions'), true);
    //         $updatelength = count($update);

    //         $delete = json_decode($request->get('deletepermissions'), true);
    //         $deletelength = count($delete);

    //         $maxlength = max($readlength,$writelength,$updatelength,$deletelength);
            
    //         $read = collect($read);
    //         $read_permissions = $read->map(function($read){
    //             return [
    //                 'feature_id' => $read,
    //                 'permission' => 'read'
    //             ];
    //         });

    //         $write = collect($write);
    //         $write_permissions = $write->map(function($write){
    //             return [
    //                 'feature_id' => $write,
    //                 'permission' => 'write'
    //             ];
    //         });

    //         $read_write_permissions = $read_permissions->merge($write_permissions);
    //         $update = collect($update);
    //         $update_permissions = $update->map(function($update){
    //             return [
    //                 'feature_id' => $update,
    //                 'permission' => 'update'
    //             ];
    //         });

    //         $delete = collect($delete);
    //         $delete_permissions = $delete->map(function($delete){
    //             return [
    //                 'feature_id' => $delete,
    //                 'permission' => 'delete'
    //             ];
    //         });
    //         $update_delete_permissions = $update_permissions->merge($delete_permissions);
    //         $all_permissions = $read_write_permissions->merge($update_delete_permissions);
    //         $all_permissions = $all_permissions->groupBy('feature_id');
    //         $permissions_arr = $all_permissions->map(function($permission){
    //             $permission = $permission->toArray();
    //             return array_column($permission,'permission');
    //         });
    //         $permission_collection = collect($permissions_arr);
    //         foreach($permission_collection as $key => $val){
    //             $role_permission = new RolePermission();
    //             $role_permission->role_id = $role->id; 
    //             $role_permission->feature_id =  $key;
    //             $role_permission->permissions = json_encode($val);
    //             $role_permission->save();
    //         }
    //         $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'New role & permissions added successfully']; 
    //         return response($response, 200); 
    //     }
    //     else{
    //         $role = Role::find($request->get('roleId'));
    //         $role->role = $request->get('role');
    //         $role->save();

    //         //delete previous permissions
    //         $role->permissions()->delete();

    //         $read = json_decode($request->get('readpermissions'), true);
    //         $readlength = count($read);
            
    //         $write = json_decode($request->get('writepermissions'), true);
    //         $writelength = count($write);

    //         $update = json_decode($request->get('updatepermissions'), true);
    //         $updatelength = count($update);

    //         $delete = json_decode($request->get('deletepermissions'), true);
    //         $deletelength = count($delete);

    //         $maxlength = max($readlength,$writelength,$updatelength,$deletelength);
            
    //         $read = collect($read);
    //         $read_permissions = $read->map(function($read){
    //             return [
    //                 'feature_id' => $read,
    //                 'permission' => 'read'
    //             ];
    //         });

    //         $write = collect($write);
    //         $write_permissions = $write->map(function($write){
    //             return [
    //                 'feature_id' => $write,
    //                 'permission' => 'write'
    //             ];
    //         });

    //         $read_write_permissions = $read_permissions->merge($write_permissions);
    //         $update = collect($update);
    //         $update_permissions = $update->map(function($update){
    //             return [
    //                 'feature_id' => $update,
    //                 'permission' => 'update'
    //             ];
    //         });

    //         $delete = collect($delete);
    //         $delete_permissions = $delete->map(function($delete){
    //             return [
    //                 'feature_id' => $delete,
    //                 'permission' => 'delete'
    //             ];
    //         });
    //         $update_delete_permissions = $update_permissions->merge($delete_permissions);
    //         $all_permissions = $read_write_permissions->merge($update_delete_permissions);
    //         $all_permissions = $all_permissions->groupBy('feature_id');
    //         $permissions_arr = $all_permissions->map(function($permission){
    //             $permission = $permission->toArray();
    //             return array_column($permission,'permission');
    //         });
    //         $permission_collection = collect($permissions_arr);
    //         foreach($permission_collection as $key => $val){
    //             $role_permission = new RolePermission();
    //             $role_permission->role_id = $role->id; 
    //             $role_permission->feature_id =  $key;
    //             $role_permission->permissions = json_encode($val);
    //             $role_permission->save();
    //         }
    //         $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Role & permissions updated successfully']; 
    //         return response($response, 200); 
    //     }
             
    // }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $role = Role::with('permissions','permissions.feature')->where('id',$id)->first();
        if($role){
            $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','data'=> ['role' => $role]];
            return response($response, 200);
        }else{
            $response = ['error'=>'error','status' => 'false','message' => 'Role not found.'];   
            return response($response, 422);
        }
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
        $role = Role::find($id);
        $checkUserRole = User::where('role_id',$id)->count();
        $response = ['error'=>'error','status' => 'false','message' => $checkUserRole];   
        if($role && $checkUserRole == 0){
            $role_permissions = RolePermission::where('role_id',$id)->delete();
            $role->delete();
            $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Role deleted successfully.']; 
            return response($response, 200);
        }else if($checkUserRole){
            $response = ['error'=>'error','status' => 'false','message' => 'Role cannot be deleted, there are users present of this role'];   
            return response($response, 422);
        }else{
            $response = ['error'=>'error','status' => 'false','message' => 'Role not found.'];   
            return response($response, 422);
        }
    }
}
