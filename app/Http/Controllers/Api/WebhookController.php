<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Xsolla\SDK\API\XsollaClient;
use Xsolla\SDK\API\PaymentUI\TokenRequest;
use Xsolla\SDK\Webhook\WebhookServer;
use Xsolla\SDK\Exception\Webhook\XsollaWebhookException;
use Xsolla\SDK\Webhook\WebhookRequest;
use Xsolla\SDK\Webhook\Message\Message;
use Xsolla\SDK\Webhook\WebhookAuthenticator;
use Xsolla\SDK\Webhook\WebhookResponse;
use Xsolla\SDK\Exception\Webhook\InvalidUserException;

class WebhookController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    /*public function __construct(Request $request){
        $httpHeaders = array();//TODO fetch HTTP request headers from $_SERVER or apache_request_headers()
        $httpRequestBody = file_get_contents('php://input');
        $clientIPv4 = $_SERVER['REMOTE_ADDR'];
        $request = new WebhookRequest($httpHeaders, $httpRequestBody, $clientIPv4);    
        $webhookAuthenticator = new WebhookAuthenticator('161611');
        $webhookAuthenticator->authenticate($request, $authenticateClientIp = true); // throws Xsolla\SDK\Exception\Webhook\XsollaWebhookException
    }*/
    
    public function index(Request $request)
    {
        $callback = function (Message $message) {
            switch ($message->getNotificationType()) {
                case Message::USER_VALIDATION:
                    /** @var Xsolla\SDK\Webhook\Message\UserValidationMessage $message */
                    // TODO if user not found, you should throw Xsolla\SDK\Exception\Webhook\InvalidUserException
                    break;
                case Message::PAYMENT:
                    /** @var Xsolla\SDK\Webhook\Message\PaymentMessage $message */
                    // TODO if the payment delivery fails for some reason, you should throw Xsolla\SDK\Exception\Webhook\XsollaWebhookException
                    $userArray = $message->getUser();
                    $paymentArray = $message->getTransaction();
                    $paymentId = $message->getPaymentId();
                    $externalPaymentId = $message->getExternalPaymentId();
                    $paymentDetailsArray = $message->getPaymentDetails();
                    $customParametersArray = $message->getCustomParameters();
                    $isDryRun = $message->isDryRun();
                    $messageArray = $message->toArray();
                    break;
                case Message::REFUND:
                    /** @var Xsolla\SDK\Webhook\Message\RefundMessage $message */
                    // TODO if you cannot handle the refund, you should throw Xsolla\SDK\Exception\Webhook\XsollaWebhookException
                    break;
                default:
                    throw new XsollaWebhookException('Notification type not implemented');
            }
        };

        $webhookServer = WebhookServer::create($callback, '161611');
        $webhookServer->start($webhookRequest = null, $authenticateClientIp = false);
        $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success','callback' => $callback,'webhookServer'=>$webhookServer];
        return response($response, 200);
    }
}
