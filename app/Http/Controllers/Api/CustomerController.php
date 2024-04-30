<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\User;
use App\Employee;
use App\Release;
use App\Download;
use App\Licence;
use App\Discord;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Auth;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Contract\Database;
use App\Events\PushNotiMaintaince;
use Carbon\Carbon;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // return response()->json(['data' => [$request->discordName, $request->input('discordName')]]);
        $auth_user = \Auth::user();
        $loginFrom = \Carbon\Carbon::parse($request->loginFrom);
        $loginTo = \Carbon\Carbon::parse($request->loginTo);
        $serverFrom = \Carbon\Carbon::parse($request->serverFrom);
        $serverTo = \Carbon\Carbon::parse($request->serverTo);

        //$users = User::with('licences','discord')

        $users = User::leftJoin('josgt_licences', 'josgt_users.id', '=', 'josgt_licences.user_id')
            ->leftJoin('josgt_discord', 'josgt_users.id', '=', 'josgt_discord.user_id')
            ->leftJoin('josgt_maintenances', 'josgt_users.id', '=', 'josgt_maintenances.user_id')
            ->leftJoin('josgt_licence_subscription', 'josgt_users.id', '=', 'josgt_licence_subscription.user_id')
            ->leftJoin('josgt_eshop_product_configuration', 'josgt_eshop_product_configuration.maintenance', '=', 'josgt_maintenances.id')
            ->select('josgt_users.*', 'josgt_licences.licence_key', 'josgt_discord.name')
            ->where('role_id', '<>', 1)
            ->where('josgt_users.id', '<>', $auth_user->id)
            ->when($request->has('search') && $request->get('search') != '', function ($q) use ($request) {
                $q->where(function ($query) use ($request) {
                    return $query->where('josgt_users.email', 'LIKE', '%' . $request->get('search') . '%')
                        ->orWhere('josgt_users.username', 'LIKE', '%' . $request->get('search') . '%')
                        ->orWhere('josgt_licences.licence_key', 'LIKE', '%' . $request->get('search') . '%')
                        ->orWhere('josgt_discord.name', 'LIKE', '%' . $request->get('search') . '%')
                        ->orWhere('josgt_users.registerDate', 'LIKE', '%' . $request->get('search') . '%')
                        ->orWhere('josgt_users.lastvisitDate', 'LIKE', '%' . $request->get('search') . '%');
                });
            })
            ->when($request->get('discordName') && $request->get('discordName') != null, function ($q2) use ($request) {
                // dd(true);
                return $q2->where('josgt_discord.name', 'like', "%$request->discordName%");
            })
            ->when($request->get('userName') && $request->get('userName') != null, function ($q2) use ($request) {
                return $q2->where('josgt_users.name', 'like', "%$request->userName%");
            })
            ->when($request->get('licenceKey') && $request->get('licenceKey') != null, function ($q2) use ($request) {
                return $q2->where('josgt_licences.licence_key', 'like', "%$request->licenceKey%");
            })
            ->when($request->get('licenceType') && $request->get('licenceType') != null && $request->get('licenceType') != 'All', function ($q2) use ($request) {
                return $q2->where('josgt_licences.licence_type', 'like', "%$request->licenceType%");
            })
            ->when($request->get('loginFrom') && $request->get('loginFrom') != null, function ($q2) use ($loginFrom, $loginTo) {
                return $q2->whereBetween('josgt_users.lastvisitDate', [$loginTo, $loginFrom]);
            })
            ->when($request->get('serverFrom') && $request->get('serverFrom') != null, function ($q2) use ($serverFrom, $serverTo) {
                return $q2->whereBetween('josgt_users.registerDate', [$serverTo, $serverFrom]);
            })
            ->when($request->has('maintenance') && $request->get('maintenance') !== null, function ($q2) use ($request) {
                $value = $request->input('maintenance');
                if ($value == 0) {
                    return $q2->where('josgt_licences.convert_to', '=', -1);
                } else {
                    return $q2->where('josgt_licences.convert_to', '!=', -1);
                }
                //return $q2->where('josgt_eshop_product_configuration.licence_flag', '=', 2)->where('josgt_eshop_product_configuration.published', '=', $value);
            })
            ->when($request->has('subscription') && $request->get('subscription') !== null, function ($q2) use ($request) {
                $value = $request->input('subscription');
                if ($value) {
                    return $q2->where('josgt_licence_subscription.status', '=', 1);
                } else {
                    return $q2->where('josgt_licence_subscription.status', '=', 0);
                }
                /*$value = $request->input('subscription') == 1 ? 1 : 0;
                return $q2->where('josgt_eshop_product_configuration.licence_flag', '=', 0)
                    ->where('josgt_eshop_product_configuration.published', '=', $value);*/
            })
            ->when($request->get('role'), function ($q2) use ($request) {
                return $q2->where('josgt_users.role_id', $request->get('role'));
            })
            ->when($request->get('status') == 1 && $request->get('status') !== null, function ($q2) use ($request) {
                return $q2->where('josgt_users.block', $request->get('status'));
            })
            ->when($request->get('status') == 0 && $request->get('status') !== null, function ($q2) use ($request) {
                return $q2->where('josgt_users.block', $request->get('status'));
            })
            ->orderBy('josgt_users.id', 'DESC')
            ->groupBy('josgt_users.id')
            //->toSql();
            ->paginate($request->get('paginate'));
        //var_dump($users);


        $users_detail = $users->map(function ($user) {
            $user_licences = [];
            foreach ($user->licences as $licence) {
                array_push($user_licences,$licence);
            }

            return collect([
                'id' => $user->id,
                'username' => $user->username,
                'role' => $user->userRole('role'),
                'name' => $user->name,
                'title' => $user->title,
                'email' => $user->email,
                'block' => $user->block,
                'licences' => $user_licences,
                'discord' => $user->discord,
                'registered_since' => $user->registerDate,
                'last_login' => $user->lastvisitDate
            ]);
        });

        $discordName = DB::table('josgt_discord')->select('name')->orderBy('id', 'desc')->get();
        $userName = DB::table('josgt_users')->select('name')->where('josgt_users.id', '<>', $auth_user->id)->orderBy('id', 'desc')->get();
        $licenceKey = DB::table('josgt_licences')->select('licence_key')->orderBy('id', 'desc')->get();
        $licenceType = DB::table('josgt_licences')->select('licence_type')->orderBy('id', 'desc')->get();

        $response = [
            'error' => new \stdClass(),
            'status' => 'true', 'message' => 'Success',
            'discordData' => $discordName,
            'userName' => $userName,
            'licenceKey' => $licenceKey,
            'licenceType' => $licenceType,
            'data' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'users' => $users_detail
            ]
        ];
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
        if ($request->get('userId') != "undefined") {
            $user = User::find($request->get('userId'));
        } else {
            $user = new User();
        }
        $api_token_reset = false;
        if ($user->role_id != $request->get('role')) {
            $api_token_reset = true;
        }
        if ($request->get('userId') != "undefined") {

            $validator = Validator::make($request->all(), [
                'username' => 'required|unique:josgt_users,username,' . $user->id . ',id',
                'name' => 'required',
                'displayname' => 'required',
                'email' => 'required|unique:josgt_users,email,' . $user->id . ',id',
                'title' => 'required',
                'locale' => 'required',
                'role' => 'required'
            ]);
        } else {
            $validator = Validator::make($request->all(), [
                'username' => 'required|unique:josgt_users,username',
                'name' => 'required',
                'displayname' => 'required',
                'email' => 'required|unique:josgt_users,email',
                'title' => 'required',
                'locale' => 'required',
                'role' => 'required'
            ]);
        }
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response(['message' => $errors[0]], 422);
        }

        $password = Str::random(10);
        \DB::beginTransaction();
        try {
            $user->username = $request->get('username');
            $user->name = $request->get('name');
            //$user->password = Hash::make($password);
            $user->display_name = $request->get('displayname');
            $user->email = $request->get('email');
            $user->role_id = $request->get('role');
            $user->title = $request->get('title');
            $user->locale = $request->get('locale');
            if ($api_token_reset) {
                $user->api_token = '';
            }
            $user->save();

            $discord = $request->get('discord');
            $explodeDiscord = explode(",", $request->get('discord'));
            $data = $request->all();
            if ($data['discord'] != NULL) {
                $discord = Discord::where('user_id', $user->id)->delete();
                for ($i = 0; $i < count($explodeDiscord); $i++) {
                    $discord = new Discord();
                    $discord->user_id = $user->id;
                    $discord->name = $explodeDiscord[$i];
                    $discord->save();
                }
            } else {
                $discord = Discord::where('user_id', $user->id)->delete();
            }

            /*$discord = Discord::where('user_id', $user->id)->first();
            if ($discord) {
                $discord->name = $request->get('discord');
                $discord->save();
            } else {
                $discord = new Discord();
                $discord->user_id = $user->id;
                $discord->name = $request->get('discord');
                $discord->save();
            }*/

            if ($request->get('userId') == "undefined") {
                \Mail::send(
                    'Email.register_mail',
                    array(
                        'email' => $user->email,
                        'password' =>  $password,
                        'name' => $user->name,
                    ),
                    function ($message) use ($request) {
                        $message->from('support@atavism.com', 'Atavism');
                        $message->to($request->get('email'))->subject('Welcome to Atavism Panel! Your account has been activated');
                    }
                );
            }

            \DB::commit();
            if ($request->get('userId') != "undefined") {
                $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'User updated successfully'];
            } else {
                $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'User added successfully'];
            }
            return response($response, 200);
        } catch (\Exception $e) {
            dd($e);
            \DB::rollback();
            $response = ["message" => 'Something went wrong,please try again later.', 'error' => $e];
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
        $user = User::with('discord', 'licences')->find($id);
        //$user->licences[0]['bgcolor'] = "#99FF99";

        $data_rel = DB::table('josgt_releases')->select()->where('show', 1)->orderBy('id', 'desc')->first();
        $date_rel = new \DateTime($data_rel->release_date, new \DateTimeZone('UTC'));
        $result = $user->licences;
        foreach ($result as $key => $licence) {
            $date = new \DateTime($licence->maintenance_expire, new \DateTimeZone('UTC'));
            $date2 = new \DateTime($date->format('Y-m-d H:m:s'), new \DateTimeZone('UTC'));
            $now = new \DateTime('NOW', new \DateTimeZone('UTC'));
            $diffDate = $now->diff($date2);
            $licence->diff = $diffDate->format('%a');
            $diffRelDate = $date->diff($date_rel);
            $licence->diffRel = $diffRelDate->format('%a');
            $licence->latestRel = $data_rel->version;
            $licence->latestRelDate =  $date_rel->format('Y-m-d H:m:s');
            $licence->mmmDate =  strtotime($licence->maintenance_expire);
            $licence->nnnDate = strtotime(Carbon::now());
            if ($licence->mmmDate > $licence->nnnDate) {
                if ($licence->diff < 14) {
                    $user->licences[$key]['bgcolor'] = "#FFFF99";
                } else {
                    $user->licences[$key]['bgcolor'] = "#99FF99";
                }
            } else {
                $user->licences[$key]['bgcolor'] = "#FF9999";
            }
        }

        $implodeString = $user->discord->pluck('name')->join(',');
        unset($user['discord']);
        $user['discord'] = $implodeString;
        if ($user) {
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['user' => $user]];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'User not found.'];
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

    public function changeStatus(Request $request)
    {
        $user = User::find($request->get('user_id'));
        if ($user) {
            $user->block = $request->get('status');
            $user->save();
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'User status changed successfully.'];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'User not found.'];
            return response($response, 422);
        }
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $user = User::find($id);
        if ($user) {
            if ($user->discord()) {
                $user->discord()->delete();
            }
            if ($user->employee()) {
                $user->employee()->delete();
            }
            $user->delete();
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Customer deleted successfully.'];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'User not found.'];
            return response($response, 422);
        }
    }

    public function getAllUsers(Request $request)
    {
        $users = User::select('josgt_users.*')
            ->when($request->has('search') && $request->get('search') != '', function ($q) use ($request) {
                $q->where(function ($query) use ($request) {
                    return $query->where('josgt_users.email', 'LIKE', '%' . $request->get('search') . '%')
                        ->orWhere('josgt_users.username', 'LIKE', '%' . $request->get('search') . '%')
                        ->orWhere('josgt_licences.licence_key', 'LIKE', '%' . $request->get('search') . '%')
                        ->orWhere('josgt_discord.name', 'LIKE', '%' . $request->get('search') . '%')
                        ->orWhere('josgt_users.registerDate', 'LIKE', '%' . $request->get('search') . '%')
                        ->orWhere('josgt_users.lastvisitDate', 'LIKE', '%' . $request->get('search') . '%');
                });
            })
            ->orderBy('josgt_users.id', 'DESC')
            ->groupBy('josgt_users.id')
            ->get();
        //->paginate($request->get('paginate'));

        $users_detail = $users->map(function ($user) {
            $user_licences = [];
            foreach ($user->licences as $licence) {
                if ($licence->licence_type != 'PREM5' || $licence->convert_to < 0) {
                    $user_licences[] = @$licence->licence_key;
                }
            }

            return collect([
                'id' => $user->id,
                'username' => $user->username,
                'role' => $user->userRole('role'),
                'name' => $user->name,
                'title' => $user->title,
                'email' => $user->email,
                'block' => $user->block,
                'licences' => $user_licences,
                'discord' => $user->discord,
                'registered_since' => $user->registerDate,
                'last_login' => $user->lastvisitDate
            ]);
        });

        //$response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','data'=> ['current_page' => $users->currentPage(),'per_page' => $users->perPage(),'total' => $users->total(),'users' => $users_detail]];
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['users' => $users_detail]];
        return response($response, 200);
    }

    public function getAllUsersList(Request $request)
    {
        $users = User::select('josgt_users.*')->orderBy('id', 'desc')->get();

        $users_detail = $users->map(function ($user) {
            return collect([
                'id' => $user->id,
                'username' => $user->name
            ]);
        });
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['users' => $users_detail]];
        return response($response, 200);
    }

    function getCustomersList(Request $request)
    {
        $customers = User::select(
            'id',
            'username'
        )
            ->where('role_id', 3)
            ->when($request->has('search'), function ($q) use ($request) {
                return $q->where('username', 'like', "%$request->search%");
            })
            ->take(10)
            ->get();
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['customers' => $customers]];
        return response($response, 200);
    }

    public function notify(Request $request)
    {
        $data = $request->all();
        $userIdArr = explode(",", $data['user_id']);
        $notifyFlag = explode(",", $data['notify_flag']); // 0 for notification and 1 for email
        $feedback = $data['feedback'];
        foreach ($userIdArr as $key => $value) {
            $users = User::where('id', $value)->first();
            $notications = [];
            $notifyFlagMessage = 0;
            $notifyFlagNotification = 0;
            if (@$notifyFlag[0] == 0) {
                $notifyFlagMessage = 1;
                $notifyFlagNotification = 1;
            }
            if (@$notifyFlag[0] == 1) {
                $notifyFlagNotification = 1;
                $notifyFlagMessage = 1;
            }
            if (@$notifyFlag[0] == 0 && @$notifyFlag[1] == 1) {
                $notifyFlagNotification = 1;
                $notifyFlagMessage = 1;
            }
            if ($notifyFlagMessage) {
                $data = ["description" => $feedback, "id" => $value, "read" => false, "title" => "Notify"];
                $actionData = array("userId" => $value, "data" => json_encode($data));
                event(new PushNotiMaintaince($actionData));
                /*$message = $feedback;
                $actionData = array("userId" => $value, "message" => $message);
                event(new PushNotiMaintaince($actionData));*/
                /*$notications['user_id'] = $value;
                $notications['feedback'] = $feedback;
                $actionData = array("userId" => $notications['user_id'], "message" => json_encode($notications['feedback']));
                event(new PushNotiMaintaince($actionData));*/
            }
            if ($notifyFlagNotification) {
                $params = json_encode(array("message" => $feedback));
                $curlUrl = "https://atavismonline.com/api/acy_api.php?action=send&nid=79&email=" . $users->email . "&params=" . $params;
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $curlUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $response = curl_exec($ch);
                curl_close($ch);
                //\Log::info(print_r($curlUrl, true));
            }
        }
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['users' => $userIdArr]];
        return response($response, 200);
    }
}
