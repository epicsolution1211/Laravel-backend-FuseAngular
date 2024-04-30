<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\User;
use App\FeatureRequests;
use App\PoolFeatureComment;
use App\Pool;
use App\PoolFeature;
use App\FeatureVoting;
use App\Notification;
use App\VotingPointsConfiguration;
use App\Events\PushNotiMaintaince;
use Xsolla\SDK\API\XsollaClient;
use Xsolla\SDK\API\PaymentUI\TokenRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use DB;
use Carbon\Carbon;
use DateTime;

class PoolController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $auth_user = Auth::user();
        $role_id = $auth_user->role_id;
        $userId = $auth_user->id;
        $poolFeatures = FeatureRequests::select('*')
            ->with(['userUsername'])
            ->when($role_id == 3, function ($q2) use ($userId) {
                return $q2->where('users_id', $userId);
            })
            ->orderBy('id', 'DESC')
            ->paginate($request->get('paginate'));

        $poolFeature = $poolFeatures->map(function ($features) {
            return collect([
                'id' => $features->id,
                'username' => $features->userUsername->username,
                'title' => $features->title,
                'description' => $features->description,
                'status' => $features->status,
                'feature_status' => $features->feature_status
            ]);
        });

        if (count($poolFeatures) > 0) {
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['current_page' => $poolFeatures->currentPage(), 'per_page' => $poolFeatures->perPage(), 'total' => $poolFeatures->total(), 'features' => $poolFeature]];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Feature not found.'];
            return response($response, 422);
        }
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
        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'description' => 'required',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response(['message' => $errors[0]], 422);
        }
        \DB::beginTransaction();
        try {
            $feature = new FeatureRequests();
            $feature->users_id = @$request->get('users_id');
            $feature->title = $request->get('title');
            $feature->description = $request->get('description');
            $feature->feature_status = 0;
            $feature->save();
            \DB::commit();
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Feature request added successfully'];
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
        $pool = Pool::find($id);
        if ($pool) {
            $poolFeatureIds = PoolFeature::select(['feature_id'])->where('pool_id', $id)->get();
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['pool' => $pool, 'poolFeatureIds' => $poolFeatureIds]];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Pool not found.'];
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
    public function update(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($request->all(), [
            'title' => 'required',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response(['message' => $errors[0]], 422);
        }
        $id = $request->get('id');
        $featureIds = $request->get('feature_ids');
        $pool = Pool::find($id);
        if ($pool) {
            if ($pool->status) {
                $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Pool is already activated so, you cannot update.'];
                return response($response, 422);
            } else {
                $deletePoolFeature = PoolFeature::where("pool_id", $id)->delete();
                $pool->title = $request->get('title');
                if ($request->get('duration') > 0) {
                    $pool->duration = @$request->get('duration');
                    $pool->start_datetime = "";
                    $pool->end_datetime = "";
                } else {
                    $start_datetime = strtotime($request->get('from_date'));
                    $end_datetime = strtotime($request->get('to_date'));
                    $start_date = new DateTime($request->get('from_date'));
                    $since_start = $start_date->diff(new DateTime($request->get('to_date')));
                    $minutes = $since_start->days * 24 * 60;
                    $minutes += $since_start->h * 60;
                    $duration += $since_start->i;
                    $pool->duration = $duration;
                    $pool->start_datetime = strtotime($request->get('from_date'));
                    $pool->end_datetime = strtotime($request->get('to_date'));
                }
                if (count($featureIds)) {
                    $i = 0;
                    for ($i; $i < count($featureIds); $i++) {
                        $poolFeature = new PoolFeature();
                        $poolFeature->pool_id = $pool->id;
                        $poolFeature->feature_id = $featureIds[$i];
                        $poolFeature->status = 0;
                        $poolFeature->save();
                    }
                }
                $pool->duration_type = @$request->get('duration_type');
                $pool->save();
                $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['pool' => $pool]];
                return response($response, 200);
            }
        } else {
            $response = ['error' => new \stdClass(), 'status' => 'false', 'message' => 'Pool not found.'];
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
        $pool = Pool::find($id);
        if ($pool) {
            if ($pool->status) {
                $response = ['error' => new \stdClass(), 'status' => 'false', 'message' => 'Pool is already activated so, you cannot delete.'];
                return response($response, 422);
            } else {
                $pool->delete();
                $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Pool deleted successfully.'];
                return response($response, 200);
            }
        } else {
            $response = ['error' => new \stdClass(), 'status' => 'false', 'message' => 'Pool not found.'];
            return response($response, 422);
        }
    }

    public function editFeature($id)
    {
        $featureRequest = FeatureRequests::find($id);
        if ($featureRequest) {
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['feature_request' => $featureRequest]];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Feature not found.'];
            return response($response, 422);
        }
    }

    public function updateFeature(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'title' => 'required',
            'description' => 'required',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response(['message' => $errors], 422);
        }
        $id = $request->get('id');
        $featureRequest = FeatureRequests::find($id);
        if ($featureRequest) {
            if ($featureRequest->feature_status > 0 || $featureRequest->status != NULL) {
                $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Feature is already activated so, you cannot update.'];
                return response($response, 422);
            } else {
                $featureRequest->title = $request->get('title');
                $featureRequest->description = $request->get('description');
                $featureRequest->save();
                $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['feature_request' => $featureRequest]];
                return response($response, 200);
            }
        } else {
            $response = ['error' => new \stdClass(), 'status' => 'false', 'message' => 'Feature request not found.'];
            return response($response, 422);
        }
    }

    public function destroyFeature($id)
    {
        $featureRequest = FeatureRequests::find($id);
        if ($featureRequest) {
            if ($featureRequest->feature_status > 0 || $featureRequest->status != NULL) {
                $response = ['error' => new \stdClass(), 'status' => 'false', 'message' => 'Feature is already activated so, you cannot delete.'];
                return response($response, 422);
            } else {
                $featureRequest->delete();
                $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Feature deleted successfully.'];
                return response($response, 200);
            }
        } else {
            $response = ['error' => new \stdClass(), 'status' => 'false', 'message' => 'Feature not found.'];
            return response($response, 422);
        }
    }

    public function commentFeature(Request $request)
    {
        $auth_user = \Auth::user();
        $role_id = $auth_user['role_id'];
        $data = $request->all();
        $validator = Validator::make($request->all(), [
            'comment' => 'required',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response(['message' => $errors[0]], 422);
        }
        \DB::beginTransaction();
        try {
            $featureComment = new PoolFeatureComment();
            $featureComment->pool_features_id = $request->get('pool_features_id');
            $featureComment->comment_by = $request->get('users_id');
            $featureComment->comment = $request->get('comment');
            $featureComment->save();

            // start push notification
            if ($role_id == 3) {
                $users = User::whereIn('role_id', [1, 2])->get();
                foreach ($users as $key => $user) {
                    \DB::beginTransaction();
                    $notification = new Notification();
                    $notification->title = "Feature comment";
                    $notification->user_id = $user['id'];
                    $notification->advise = $request->get('comment');
                    $notification->re_notify = 0;
                    $notification->action = "notify";
                    $notification->save();
                    \DB::commit();
                    $data = ["description" => $notification->advise, "id" => $notification->id, "read" => $notification->re_notify, "title" => "Feature comment"];
                    $actionData = array("userId" => $user['id'], "data" => json_encode($data));
                    event(new PushNotiMaintaince($actionData));
                }
            } else {
                $featureRequests = FeatureRequests::find($request->get('pool_features_id'));
                $notification = new Notification();
                $notification->title = "Feature comment";
                $notification->user_id = $featureRequests->users_id;
                $notification->advise = $request->get('comment');
                $notification->re_notify = 0;
                $notification->action = "notify";
                $notification->save();
                \DB::commit();
                $data = ["description" => $notification->advise, "id" => $notification->id, "read" => $notification->re_notify, "title" => "Feature comment"];
                $actionData = array("userId" => $featureRequests->users_id, "data" => json_encode($data));
                event(new PushNotiMaintaince($actionData));
                // end push notification
            }
            \DB::commit();
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Feature comment added successfully', 'data' => ['auth_user' => $auth_user]];
            return response($response, 200);
        } catch (\Exception $e) {
            dd($e);
            \DB::rollback();
            $response = ["message" => 'Something went wrong,please try again later.', 'error' => $e];
            return response($response, 422);
        }
    }

    public function getCommentFeature(Request $request)
    {
        $auth_user = \Auth::user();
        $data = $request->all();
        $featureId = $data['id'];
        $featureRequest = FeatureRequests::find($featureId);
        $feature = PoolFeatureComment::with(['commentUser'])->leftJoin('pool_features', 'pool_features.id', '=', 'pool_feature_comments.pool_features_id')->where('pool_feature_comments.pool_features_id', $featureId)->orderBy('pool_feature_comments.created_at', 'DESC')
            ->get(['pool_feature_comments.*']);
        if (count($feature) > 0) {
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['feature' => $feature, 'feature_request' => $featureRequest, 'auth_user' => $auth_user]];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Feature not found.'];
            return response($response, 422);
        }
    }

    public function getApprovedFeatureRequests(Request $request)
    {
        $auth_user = \Auth::user();
        $role_id = $auth_user->role_id;
        $userId = $auth_user->id;
        $poolFeatures = FeatureRequests::select('*')
            ->with(['userUsername'])
            /*->when($role_id == 3,function($q2) use ($request,$userId){
                            return $q2->where('users_id',$userId);
                        })*/
            ->where('feature_status', 1)
            ->orderBy('id', 'DESC')
            ->paginate($request->get('paginate'));

        $poolFeature = $poolFeatures->map(function ($features) {
            return collect([
                'id' => $features->id,
                'users_id' => $features->users_id,
                'username' => $features->userUsername->username,
                'title' => $features->title,
                'description' => $features->description,
                'status' => $features->status
            ]);
        });
        if (count($poolFeatures) > 0) {
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['current_page' => $poolFeatures->currentPage(), 'per_page' => $poolFeatures->perPage(), 'total' => $poolFeatures->total(), 'features' => $poolFeature]];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Feature not found.'];
            return response($response, 422);
        }
    }



    public function changeStatus(Request $request)
    {
        $auth_user = \Auth::user();
        $role_id = $auth_user->role_id;
        $feature = FeatureRequests::find($request->get('id'));
        if ($feature) {
            $feature->status = $request->get('status');
            $feature->feature_status = $request->get('status');
            $feature->save();
            if ($request->get('status') == 1) {
                $message = "Your feature request is approved by admin";
            } else {
                $message = "Your feature request is rejected by admin";
            }
            $featureRequests = FeatureRequests::find($request->get('id'));
            $notification = new Notification();
            $notification->user_id = $featureRequests->users_id;
            $notification->advise = $message;
            $notification->re_notify = 0;
            $notification->action = "notify";
            $notification->save();
            $data = ["description" => $notification->advise, "id" => $notification->id, "read" => $notification->re_notify, "title" => "Feature status updated"];
            $actionData = array("userId" => $featureRequests->users_id, "data" => json_encode($data));
            event(new PushNotiMaintaince($actionData));
            // end push notification
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Feature status changed successfully.'];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Feature not found.'];
            return response($response, 422);
        }
    }

    public function rejectFeatureRequestsComment(Request $request)
    {
        $auth_user = \Auth::user();
        $role_id = $auth_user->role_id;
        $data = $request->all();
        $featureId = $data['id'];
        $featureComment = FeatureRequests::find($featureId);
        $featureComment->comments = $data['comment'];
        $featureComment->feature_status = 2;
        $featureComment->status = 0;
        $featureComment->save();
        $message = "Your feature request is rejected by admin";
        $featureRequests = FeatureRequests::find($request->get('id'));
        $notification = new Notification();
        $notification->user_id = $featureRequests->users_id;
        $notification->advise = $message;
        $notification->re_notify = 0;
        $notification->action = "notify";
        $notification->save();
        $data = ["description" => $notification->advise, "id" => $notification->id, "read" => $notification->re_notify, "title" => "Feature is rejected"];
        $actionData = array("userId" => $featureRequests->users_id, "data" => json_encode($data));
        event(new PushNotiMaintaince($actionData));
        if ($featureComment) {
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Feature request rejected successfully.'];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Feature comment not found.'];
            return response($response, 422);
        }
    }

    public function poolList(Request $request)
    {
        $auth_user = \Auth::user();
        $roleId = $auth_user->role_id;
        $userId = $auth_user->id;
        $poolId = $request->get('pool_id');
        $pools = Pool::select('*')
            ->with('features', 'requests', 'votes')
            ->orderBy('id', 'DESC')
            ->paginate($request->get('paginate'));
        if ($roleId == 1 && $roleId == 2) {
            $totalUserVotesCount = FeatureVoting::where('pool_id', $poolId)->sum('votes');
        } else {
            $totalUserVotesCount = FeatureVoting::where('user_id', $userId)->where('pool_id', $poolId)->sum('votes');
        }
        $pool = $pools->map(function ($pools, $userId) {
            $sumOfVotes = FeatureVoting::where("pool_id", $pools->id)->sum('votes');
            $totalFeature = PoolFeature::where("pool_id", $pools->id)->count();
            $votes = ($sumOfVotes * 100 / $totalFeature) / 100;
            $featureArr = array();
            foreach ($pools->features as $key => $value) {
                $featureArr[] = FeatureRequests::find($value['feature_id']);
            }
            $totalVotesCount = FeatureVoting::where("pool_id", $pools->id)->sum('votes');
            return collect([
                'id' => $pools->id,
                'title' => $pools->title,
                'poolFeatures' => $pools->features,
                'featureRequests' => $featureArr,
                'duration' => $pools->duration,
                'status' => $pools->status,
                'start_datetime' => $pools->start_datetime,
                'end_datetime' => $pools->end_datetime,
                'votes' => number_format($votes, 2) . "%",
                'total_votes_count' => $totalVotesCount,
            ]);
        });

        if (count($pool) > 0) {
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['current_page' => $pools->currentPage(), 'per_page' => $pools->perPage(), 'total' => $pools->total(), 'pools' => $pool, 'total_user_votes_count' => $totalUserVotesCount]];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Pool not found.'];
            return response($response, 422);
        }
    }

    public function createPool(Request $request)
    {
        $data = $request->all();
        $title = $data['title'];
        $duration = $data['duration'];
        $featureIds = $data['feature_ids'];
        $validator = Validator::make($request->all(), [
            'title' => 'required|unique:pools',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response(['message' => $errors], 422);
        }
        $pool = new Pool();
        $pool->title = $title;
        if ($request->get('duration') > 0) {
            $pool->duration = @$request->get('duration');
            $pool->start_datetime = "";
            $pool->end_datetime = "";
        } else {
            $start_datetime = strtotime($request->get('from_date'));
            $end_datetime = strtotime($request->get('to_date'));
            $start_date = new DateTime($request->get('from_date'));
            $since_start = $start_date->diff(new DateTime($request->get('to_date')));
            $minutes = $since_start->days * 24 * 60;
            $minutes += $since_start->h * 60;
            $duration += $since_start->i;
            $pool->duration = $duration;
            $pool->start_datetime = strtotime($request->get('from_date'));
            $pool->end_datetime = strtotime($request->get('to_date'));
        }
        $pool->duration_type = @$request->get('duration_type');
        $pool->status = 0;
        $pool->save();
        if (count($featureIds)) {
            $i = 0;
            for ($i; $i < count($featureIds); $i++) {
                $poolFeature = new PoolFeature();
                $poolFeature->pool_id = $pool->id;
                $poolFeature->feature_id = $featureIds[$i];
                $poolFeature->status = 0;
                $poolFeature->save();
            }
        }
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Pool created successfully.'];
        return response($response, 200);
    }

    public function activePool(Request $request)
    {
        $pool = Pool::find($request->get('id'));
        if ($pool) {
            $pool->status = $request->get('status');
            $pool->save();
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Pool status changed successfully.'];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Pool not found.'];
            return response($response, 422);
        }
    }

    public function startPool(Request $request)
    {
        $pool = Pool::find($request->get('id'));
        if ($pool) {
            if (!$pool->status) {
                $response = ['error' => 'error', 'status' => 'false', 'message' => 'Please do activate your pool then it will start.'];
                return response($response, 422);
            }
            if ($pool->start_datetime != "" && $pool->end_datetime != "") {
                $response = ['error' => 'error', 'status' => 'false', 'message' => 'Pool already started.'];
                return response($response, 422);
            }
            $duration = $pool->duration;
            $currentDateTime = Carbon::now();
            $endDateTime = Carbon::now()->addMinutes($duration);
            $startDateTime = $currentDateTime;
            $pool->start_datetime = strtotime($startDateTime);
            $pool->end_datetime = strtotime($endDateTime);
            $pool->save();
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Pool started successfully.'];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Pool not found.'];
            return response($response, 422);
        }
    }

    public function voteFeature(Request $request)
    {
        $data = $request->all();

        $poolId = $data['pool_id'];
        $userId = $data['user_id'];
        $featureId = $data['feature_id'];
        $vote = $data['vote'];
        $currentDateTime = strtotime(Carbon::now());
        $accessToGiveVote = VotingPointsConfiguration::where('user_id', $userId)->where('voting_points', '>', '0')->first();
        $pool = Pool::find($poolId);
        if ($pool->end_datetime < $currentDateTime) {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'You can not give vote this feature because feature duration is an over.'];
            return response($response, 422);
        }
        $validator = Validator::make($request->all(), [
            'pool_id' => 'required',
            'user_id' => 'required',
            'feature_id' => 'required',
            'vote' => 'required',
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response(['message' => $errors], 422);
        }
        if (!$accessToGiveVote) {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'You can not give vote this feature because vote limit is an over.'];
            return response($response, 422);
        }
        $featureVoting = new FeatureVoting();
        $featureVoting->pool_id = $poolId;
        $featureVoting->user_id = $userId;
        $featureVoting->feature_id = $featureId;
        $featureVoting->votes = $vote;
        $featureVoting->save();
        $accessToGiveVote->voting_points = $accessToGiveVote->voting_points - 1;
        $accessToGiveVote->save();
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Feature votes successfully.'];
        return response($response, 200);
    }

    public function getVotesPoolFeatures(Request $request)
    {
        $data = $request->all();
        $poolId = $data['pool_id'];
        $pool = Pool::find($poolId);
        $pool_title = "";
        if ($pool) {
            $pool_title = $pool->title;
        }
        $featureVotings = FeatureVoting::select('*', DB::raw("SUM(votes) as votesCount"))
            ->with('features')
            ->where('pool_id', $poolId)
            ->orderBy('id', 'DESC')
            ->groupBy('feature_id')
            ->paginate($request->get('paginate'));
        $featureVoting = $featureVotings->map(function ($featureVoting, $voteCount) {
            $votePercentage = $featureVoting['votesCount'] * 1000 / 100;
            $votePercentage = number_format($votePercentage, 0) . "%";
            return collect([
                'title' => @$featureVoting->features[0]['title'],
                'description' => @$featureVoting->features[0]['description'],
                'votesCount' => $featureVoting['votesCount'],
                'votesPercentage' => $votePercentage,
                'pool_id' => $featureVoting['pool_id'],
                'feature_id' => $featureVoting['feature_id']
            ]);
        });

        if (count($featureVoting) > 0) {
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['current_page' => $featureVotings->currentPage(), 'per_page' => $featureVotings->perPage(), 'total' => $featureVotings->total(), 'pool_features' => $featureVoting, 'pool_title' => $pool_title]];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Pool not found.'];
            return response($response, 422);
        }
    }

    public function getVotesPoolFeaturesUsers(Request $request)
    {
        $data = $request->all();
        $poolId = $data['pool_id'];
        $featureId = $data['feature_id'];
        $featureVotings = FeatureVoting::select('*', DB::raw("SUM(votes) as votesCount"))->with('users')->where('pool_id', $poolId)
            ->where('feature_id', $featureId)
            ->orderBy('id', 'DESC')
            ->groupBy('user_id')
            ->paginate($request->get('paginate'));

        $featureVoting = $featureVotings->map(function ($featureVoting, $voteCount) {
            return collect([
                'username' => $featureVoting->users->username,
                'votesCount' => $featureVoting->votesCount,
            ]);
        });

        if (count($featureVoting) > 0) {
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['current_page' => $featureVotings->currentPage(), 'per_page' => $featureVotings->perPage(), 'total' => $featureVotings->total(), 'pool_features' => $featureVoting]];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Pool not found.'];
            return response($response, 422);
        }
    }

    public function poolRelease(Request $request)
    {
        $data = $request->all();
        $userId = $data['user_id'];
        $poolId = $data['pool_id'];
        $featureIds = $data['feature_id'];
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'pool_id' => 'required',
            'feature_id' => 'required|array'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response(['message' => $errors], 422);
        }
        $featureAlreadyReleased = PoolFeature::where('pool_id', $poolId)->where('selected', '<>', NULL)->count();
        if ($featureAlreadyReleased) {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Pool already released.'];
            return response($response, 422);
        }
        foreach ($featureIds as $key => $featureId) {
            $poolFeature = PoolFeature::where('pool_id', $poolId)->where('feature_id', $featureId)->first();
            $poolFeature->selected = $featureId;
            $poolFeature->selected_datetime = strtotime(now());
            $poolFeature->save();
        }
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Feature release created successfully.'];
        return response($response, 200);
    }

    public function voteList(Request $request)
    {
        $votingPointsConfigurations = VotingPointsConfiguration::select('*')
            ->with('voteUser')
            ->orderBy('id', 'DESC')
            ->paginate($request->get('paginate') ? $request->get('paginate') : 7);
        $votingPointsConfiguration = $votingPointsConfigurations->map(function ($votingPointsConfiguration) {
            return collect([
                'id' => $votingPointsConfiguration->id,
                'user_id' => $votingPointsConfiguration->user_id,
                'username' => $votingPointsConfiguration->voteUser->username,
                'voting_points' => $votingPointsConfiguration->voting_points,
                'per_month_purchase_vote' => $votingPointsConfiguration->per_month_purchase_vote,
                'vote_purchase_price' => $votingPointsConfiguration->vote_purchase_price,
                'purchase_status' => $votingPointsConfiguration->purchase_status,
                'status' => $votingPointsConfiguration->status,
            ]);
        });

        if (count($votingPointsConfigurations) > 0) {
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['current_page' => $votingPointsConfigurations->currentPage(), 'per_page' => $votingPointsConfigurations->perPage(), 'total' => $votingPointsConfigurations->total(), 'votingPointsConfiguration' => $votingPointsConfiguration]];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Pool not found.'];
            return response($response, 422);
        }
    }
    public function createVote(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|numeric|unique:voting_points_configurations,user_id',
            'voting_points' => 'required|numeric',
            'per_month_purchase_vote' => 'required|numeric',
            'vote_purchase_price' => 'required|numeric',
            'purchase_status' => 'required|numeric',
            'status' => 'required|numeric'
        ]);
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response(['message' => $errors], 422);
        }
        $votingPointsConfiguration = new VotingPointsConfiguration();
        $votingPointsConfiguration->user_id = $data['user_id'];
        $votingPointsConfiguration->voting_points = $data['voting_points'];
        $votingPointsConfiguration->per_month_purchase_vote = $data['per_month_purchase_vote'];
        $votingPointsConfiguration->vote_purchase_price = $data['vote_purchase_price'];
        $votingPointsConfiguration->purchase_status = $data['purchase_status'];
        $votingPointsConfiguration->status = $data['status'];
        $votingPointsConfiguration->save();
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Vote configuration created successfully.'];
        return response($response, 200);
    }

    public function VoteByUserId($user_id)
    {
        try {
            $votingPointsConfiguration = VotingPointsConfiguration::select('voting_points')->where('user_id', $user_id)->first();
            if ($votingPointsConfiguration) {
                $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['voting_points_configuration' => $votingPointsConfiguration]];
                return response($response, 200);
            } else {
                $response = ['error' => 'error', 'status' => 'false', 'message' => 'Vote configuration not found.'];
                return response($response, 422);
            }
        } catch (\Throwable $th) {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Something went wrong.'];
            return response($response, 422);
        }
    }

    public function editVote($id)
    {
        $votingPointsConfiguration = VotingPointsConfiguration::with('voteUser')->find($id);
        if ($votingPointsConfiguration) {
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['voting_points_configuration' => $votingPointsConfiguration]];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Vote configuration not found.'];
            return response($response, 422);
        }
    }

    public function updateVote(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|numeric',
            'voting_points' => 'required|numeric',
            'per_month_purchase_vote' => 'required|numeric',
            'vote_purchase_price' => 'required|numeric',
            'purchase_status' => 'required|numeric',
            'status' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            return response(['message' => $errors], 422);
        }
        $id = $request->get('id');
        $votingPointsConfiguration = VotingPointsConfiguration::find($id);
        if ($votingPointsConfiguration) {
            $votingPointsConfiguration->user_id = $data['user_id'];
            $votingPointsConfiguration->voting_points = $data['voting_points'];
            $votingPointsConfiguration->per_month_purchase_vote = $data['per_month_purchase_vote'];
            $votingPointsConfiguration->vote_purchase_price = $data['vote_purchase_price'];
            $votingPointsConfiguration->purchase_status = $data['purchase_status'];
            $votingPointsConfiguration->status = $data['status'];
            $votingPointsConfiguration->save();
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => ['voting_points_configuration' => $votingPointsConfiguration]];
            return response($response, 200);
        } else {
            $response = ['error' => new \stdClass(), 'status' => 'false', 'message' => 'Vote configuration not found.'];
            return response($response, 422);
        }
    }

    public function destroyVote($id)
    {
        $votingPointsConfiguration = VotingPointsConfiguration::find($id);
        if ($votingPointsConfiguration) {
            $votingPointsConfiguration->delete();
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Vote configuration deleted successfully.'];
            return response($response, 200);
        } else {
            $response = ['error' => new \stdClass(), 'status' => 'false', 'message' => 'Vote configuration not found.'];
            return response($response, 422);
        }
    }

    public function changePurchaseStatus(Request $request)
    {
        $votingPointsConfiguration = VotingPointsConfiguration::find($request->get('id'));
        if ($votingPointsConfiguration) {
            $votingPointsConfiguration->purchase_status = $request->get('status');
            $votingPointsConfiguration->save();
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Vote purchase status changed successfully.'];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Vote configuration not found.'];
            return response($response, 422);
        }
    }

    public function changeVoteStatus(Request $request)
    {
        $votingPointsConfiguration = VotingPointsConfiguration::find($request->get('id'));
        if ($votingPointsConfiguration) {
            $votingPointsConfiguration->status = $request->get('status');
            $votingPointsConfiguration->save();
            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Vote status changed successfully.'];
            return response($response, 200);
        } else {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Vote configuration not found.'];
            return response($response, 422);
        }
    }

    public function purchaseVote(Request $request)
    {
        $data = $request->all();
        $prize = 2;
        $userId = $data['user_id'];
        $product_id = 163709;
        $token  = LicenceController::generateOrderToken();
        $xsollaClient = XsollaClient::factory(array(
            'merchant_id' => 43963,
            'api_key' => 'dc8y4BVLBNAZH4g0'
        ));
        $tokenRequest = new TokenRequest(158935, 'aaa@mailinator.com');
        $tokenRequest->setUserEmail('aaa@mailinator.com')
            ->setExternalPaymentId($token)
            ->setUserName('atulb');
        $tokenRequest->setSandboxMode(true);

        $dataRequest = $tokenRequest->toArray();
        $dataRequest['settings']['ui']['desktop']['header']['visible_logo'] = true;
        $dataRequest['settings']['ui']['desktop']['header']['visible_name'] = true;
        $dataRequest['settings']['ui']['desktop']['header']['close_button'] = true;
        $dataRequest['settings']['return_url'] = 'https://devapanel.atavismonline.com/#/customers';

        $dataRequest['settings']['ui']['size'] = "small";
        $dataRequest['settings']['language'] = "en";
        $parsedResponse = $xsollaClient->CreatePaymentUIToken(array('request' => $dataRequest));

        $xsollaToken = $parsedResponse['token'];
        $paymentRedirectUrl = "https://sandbox-secure.xsolla.com/paystation3/?access_token=" . $xsollaToken;
        echo $paymentRedirectUrl;
        exit;
    }
}
