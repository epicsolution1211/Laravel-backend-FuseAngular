<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AcymailingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //include '/var/www/html/php72/atavism/atavismonline.com/includes/defines.php';
        //include '/var/www/html/php72/atavism/atavismonline.com/includes/framework.php';
        define('_JEXEC', 1);
        define('JPATH_BASE', '/var/www/html/atavismonline.com');

        require_once JPATH_BASE . '/includes/defines.php';
        require_once JPATH_BASE . '/includes/framework.php';
        try {
            $japp = \JFactory::getApplication('site');
            //This code will not only delete a list but all elements related to that list (it won't delete users or newsletters, but only the fact a Newsletter is attached to that list or a subscriber subscribed to that list)
            if (!file_exists(JPATH_BASE."/administrator/components/com_acymailing/helpers/helper.php")) {
                echo 'This code can not work without the AcyMailing Component';
                return false;
            }
            $helper = include(JPATH_BASE."/administrator/components/com_acymailing/helpers/helper.php");
            $memberid = '10293'; //ID of the Joomla User or user e-mail (this code supposes that the user is already inserted in AcyMailing!)
            $mailid = '73'; //ID of the Newsletter you want to add in the queue
            $senddate = time(); //When do you want the e-mail to be sent to this user? you should specify a timestamp here (time() is the current time)
            $userClass = acymailing_get('class.subscriber');
            $subid = $userClass->subid($memberid); //this function returns the ID of the user stored in the AcyMailing table from a Joomla User ID or an e-mail address 
            if(empty($subid)){
                $db= \JFactory::getDBO();
                $db->setQuery('INSERT IGNORE INTO #__acymailing_queue (`subid`,`mailid`,`senddate`,`priority`) VALUES ('.$db->Quote($memberid).','.$db->Quote($mailid).','.$db->Quote($senddate).',1)');
                $db->query();
            }

            /*$listClass = acymailing_get('class.list');
            $allLists = $listClass->getLists();
            $allLists = json_encode($allLists);*/
            /*$mailer = acymailing_get('helper.mailer');

            $mailer->report = true; //set it to true or false if you want Acy to display a confirmation message or not (message successfully sent to...)
            $mailer->trackEmail = true; //set it to true or false if you want Acy to track the message or not (it will be inserted in the statistics table)
            $mailer->autoAddUser = false; //set it to true if you want Acy to automatically create the user if it does not exist in AcyMailing
            $mailer->addParam('var1','Value of the variable 1'); //Acy will automatically replace the tag {var1} by the value specified in the second parameter... you can use this function several times to replace tags in your message.
            $mailer->sendOne(73,'tester123456@mailinator.com'); //The first parameter is the ID of the Newsletter you want to send or its namekey*/
        } catch (Exception $e) {
            echo 'Caught exception: ', $e->getMessage(), "\n";
        }
        $response = ['error'=>new \stdClass(),'status' => 'true','message' => 'Success'];
        return response($response, 200);
    }
}
