<?php

namespace App\Http\Controllers\Api;

use App\Events\PushNotiMaintaince;
use App\Http\Controllers\Controller;
use App\Notification;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ZohoController extends Controller
{
    private $client_id = '1000.ALDXEUT875RBMOKIS057TEBOU4G12H';
    private $client_secret = 'd058e53a19c0e2ca6b9eb6c3caf5a5d1299b609e62';
    private $redirect_url = 'https://devapanel.atavismonline.com/tickets';
    private $org_id = "20094770084";

    function createRefreshToken($code)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://accounts.zoho.eu/oauth/v2/token?code=$code&grant_type=authorization_code&client_id=$this->client_id&client_secret=$this->client_secret&redirect_uri=$this->redirect_url",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
        ));

        $res = curl_exec($curl);
        $error = false;
        if (curl_errno($curl)) {
            $error = true;
        }

        curl_close($curl);

        $res = json_decode($res);

        if ($error || property_exists($res, 'error')) {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Something went wrong!'];
            return response($response, 500);
        }


        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => $res->refresh_token];
        return response($response, 200);
    }

    private function getNewAccessToken($refreshToken)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://accounts.zoho.eu/oauth/v2/token?grant_type=refresh_token&redirect_uri=$this->redirect_url&scope=Desk.basic.READ%2CDesk.tickets.READ%2CDesk.tickets.CREATE%2CDesk.tickets.UPDATE&client_secret=$this->client_secret&client_id=$this->client_id&refresh_token=$refreshToken",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
        ));

        $res = curl_exec($curl);
        $error = false;

        if (curl_errno($curl)) {
            $error = true;
        }

        curl_close($curl);

        $res = json_decode($res);

        return $res->access_token;
    }

    function uploadTicketAttachment($token, $id, $file)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://desk.zoho.eu/api/v1/tickets/$id/attachments",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array('file' => new \CURLFile($file->path(), $file->getMimeType(), $file->getClientOriginalName())),
            CURLOPT_HTTPHEADER => array(
                "orgId: $this->org_id",
                "Authorization: Zoho-oauthtoken $token",
            ),
        ));

        curl_exec($curl);

        curl_close($curl);
    }

    function createTicket(Request $request)
    {
        $payloads = $request->only(
            'subject',
            'email',
            'description',
            'file',
            'allow_access_to_server',
            'licence',
            'server_ip'
        );

        $curl = curl_init();

        $payloadsArr = filter_var($payloads['allow_access_to_server'], FILTER_VALIDATE_BOOLEAN) ? [
            'contact' => ['email' => $payloads['email']],
            'subject' => $payloads['subject'],
            'departmentId' => '159401000000007061',
            'description' => $payloads['description'],
            'channel' => 'Email',
            'cf' => [
                'cf_allow_access_to_server' => $payloads['allow_access_to_server'],
                'cf_licence' => $payloads['licence'],
                'cf_server_ip' => $payloads['server_ip']
            ]
        ] : [
            'contact' => ['email' => $payloads['email']],
            'subject' => $payloads['subject'],
            'departmentId' => '159401000000007061',
            'description' => $payloads['description'],
            'channel' => 'Email',
            'cf' => [
                'cf_allow_access_to_server' => $payloads['allow_access_to_server'],
            ]
        ];

        $token = $this->getNewAccessToken($request->token);

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://desk.zoho.eu/api/v1/tickets',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($payloadsArr),
            CURLOPT_HTTPHEADER => array(
                "orgId: $this->org_id",
                "Authorization: Zoho-oauthtoken $token",
                'Content-Type: application/json',
            ),
        ));

        $res = curl_exec($curl);
        $error = false;

        if (curl_errno($curl)) {
            $error = true;
        }

        curl_close($curl);

        $res = json_decode($res);

        if ($error || property_exists($res, 'error')) {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Something went wrong!'];
            return response($response, 500);
        }


        if ($request->hasFile('file')) {
            $this->uploadTicketAttachment($token, $res->id, $request->file('file'));
        }
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => $res];
        return response($response, 200);
    }

    function getTickets($token)
    {
        $token = $this->getNewAccessToken($token);
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://desk.zoho.eu/api/v1/associatedTickets',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                "orgId: $this->org_id",
                "Authorization: Zoho-oauthtoken $token",
            ),
        ));

        $res = curl_exec($curl);
        $error = false;

        if (curl_errno($curl)) {
            $error = true;
        }

        curl_close($curl);

        $res = json_decode($res);
        if ($res) {
            if ($error || property_exists($res, 'error')) {
                $response = ['error' => 'error', 'status' => 'false', 'message' => 'Something went wrong!'];
                return response($response, 500);
            }

            $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => $res->data];
            return response($response, 200);
        }
        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => []];
        return response($response, 200);
    }

    function getTicketById($token, $id)
    {
        $token = $this->getNewAccessToken($token);
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://desk.zoho.eu/api/v1/tickets/$id",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                "orgId: $this->org_id",
                "Authorization: Zoho-oauthtoken $token"
            ),
        ));

        $res = curl_exec($curl);

        $error = false;

        if (curl_errno($curl)) {
            $error = true;
        }

        curl_close($curl);

        $res = json_decode($res);

        if ($error || property_exists($res, 'error')) {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Something went wrong!'];
            return response($response, 500);
        }

        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => $res];
        return response($response, 200);
    }

    function addTicketComment(Request $request)
    {
        $token = $this->getNewAccessToken($request->token);

        $payloads = [
            'isPublic' => true,
            "contentType" => "html",
            "content" => $request->comment
        ];
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://desk.zoho.eu/api/v1/tickets/$request->ticket_id/comments",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($payloads),
            CURLOPT_HTTPHEADER => array(
                "orgId: $this->org_id",
                "Authorization: Zoho-oauthtoken $token"
            ),
        ));

        $res = curl_exec($curl);

        $error = false;

        if (curl_errno($curl)) {
            $error = true;
        }

        curl_close($curl);

        $res = json_decode($res);

        if ($error || property_exists($res, 'error')) {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Something went wrong!'];
            return response($response, 500);
        }

        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => $res];
        return response($response, 200);
    }

    function getTicketComments($token, $id)
    {
        $token = $this->getNewAccessToken($token);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://desk.zoho.eu/api/v1/tickets/$id/comments",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                "orgId: $this->org_id",
                "Authorization: Zoho-oauthtoken $token"
            ),
        ));

        $res = curl_exec($curl);

        $error = false;

        if (curl_errno($curl)) {
            $error = true;
        }

        curl_close($curl);

        $res = json_decode($res);

        if ($error || property_exists($res, 'error')) {
            $response = ['error' => 'error', 'status' => 'false', 'message' => 'Something went wrong!'];
            return response($response, 500);
        }

        $response = ['error' => new \stdClass(), 'status' => 'true', 'message' => 'Success', 'data' => $res->data];
        return response($response, 200);
    }

    function TicketCommentHookHandle(Request $request)
    {
        $commenter = $request->all()[0]['payload']['commenter']['name'];
        $user = User::whereEmail('office@atavismonline.com')->first();
        $notification = Notification::create([
            'user_id' => $user->id,
            'title' => 'New Comment',
            'advise' => "$commenter commented on your ticket 'test ticket'",
            're_notify' => 0,
            'action' => 'notify'
        ]);
        $data = [
            "id" => $notification->id,
            "title" => "New Comment",
            "read" => $notification->re_notify,
            "description" => "$commenter commented on your ticket 'test ticket'",
        ];
        $actionData = array("userId" => $user->id, "data" => json_encode($data));
        event(new PushNotiMaintaince($actionData));
    }

    function TicketStatusChangeHookHandle(Request $request)
    {
        $user = User::whereEmail('office@atavismonline.com')->first();
        $status = $request->all()[0]['payload']['status'];
        $notification = Notification::create([
            'user_id' => $user->id,
            'title' => "Ticket $status",
            'advise' => "Your Ticket has been $status",
            're_notify' => 0,
            'action' => 'notify'
        ]);
        $data = [
            "id" => $notification->id,
            "title" => "Ticket $status",
            "read" => $notification->re_notify,
            "description" => "Your Ticket has been $status",
        ];
        $actionData = array("userId" => $user->id, "data" => json_encode($data));
        event(new PushNotiMaintaince($actionData));
    }
}
