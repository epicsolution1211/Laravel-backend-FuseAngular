<?php

use App\AdvisorSettings;
use App\Events\PushNotiMaintaince;
use App\Notification;
use Illuminate\Http\Request;
use App\User;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });
Route::post('/login', 'Auth\ApiAuthController@login')->name('login');
Route::post('/reset-password-request', 'Api\PasswordResetRequestController@sendPasswordResetEmail');
Route::post('/change-password', 'Api\ChangePasswordController@passwordResetProcess');
Route::post('/check-password', 'Auth\ApiAuthController@checkPassword');
Route::post('/update-password', 'Auth\ApiAuthController@changePassword');
Route::post('/facebook-login', 'Auth\ApiAuthController@facebookLogin');
Route::post('/refresh-access-token', 'Auth\ApiAuthController@refreshAccessToken')->middleware('auth:api');
Route::get('/webhook', 'Api\WebhookController@index');
Route::get('/acymailing', 'Api\AcymailingController@index');

//Routes for cloud
Route::post('/signin-cloud', 'Api\CloudController@signinCloud')->name('signin-cloud');
Route::post('/signout-cloud', 'Api\CloudController@signoutCloud')->name('signout-cloud');
Route::post('/server-restart', 'Api\CloudController@serverRestart')->name('server-restart');
Route::post('/server-stop', 'Api\CloudController@serverStop')->name('server-stop');
Route::get('/server-create/{userId}', 'Api\CloudController@serverCreate')->name('server-create');
Route::post('/server-create-post', 'Api\CloudController@serverCreatePost')->name('server-create-post');
Route::post('/server-start', 'Api\CloudController@serverStart')->name('server-start');

Route::group(['namespace' => 'Api', 'prefix' => 'zoho/ticket/webhook'], function () {
    Route::post('add-comment', 'ZohoController@TicketCommentHookHandle');
    Route::post('update', 'ZohoController@TicketStatusChangeHookHandle');
});
//Route::get('/addUnityLicence', 'Api\LicenceController@addUnityLicence');

//Route::group(['namespace' => 'Api','middleware' => ['auth:api','checkRole']], function () {
Route::group(['namespace' => 'Api', 'middleware' => 'auth:api'], function () {
    Route::get('/roles', 'RoleController@index');
    Route::get('/role/{id}', 'RoleController@show');
    Route::post('/add-role', 'RoleController@store');
    Route::delete('/delete-role/{id}', 'RoleController@destroy');
    Route::get('/permissions/{id}', 'RolePermissionController@show');
    Route::get('/permissionsByUser/{id}', 'RolePermissionController@permissionsByUser');
    Route::get('/features', 'FeatureController@index');
    Route::post('/employee', 'EmployeeController@index');
    Route::post('/add-employee', 'EmployeeController@store');
    Route::get('/employee/{id}', 'EmployeeController@edit');
    Route::get('/employee-filter', 'EmployeeController@userFilter');
    Route::post('/employee-status', 'EmployeeController@changeStatus');
    Route::delete('/delete-employee/{id}', 'EmployeeController@destroy');
    Route::post('/update-employee-password', 'EmployeeController@changeEmployeePassword');
    Route::get('/languages', 'LanguageController@index');
    //Route::get('/user-licences/{id}', 'LicenceController@index');
    Route::post('/employee-licences', 'LicenceController@index');
    Route::get('/licence/{id}', 'LicenceController@show');
    Route::post('/assignMaintenance', 'LicenceController@assignMaintenance');
    Route::post('/extendMaintenance', 'LicenceController@extendMaintenance');
    Route::post('/subscriptionMaintenance', 'LicenceController@subscriptionMaintenance');
    Route::post('/updateLicence', 'LicenceController@updateLicence');
    Route::post('/addUnityLicence', 'LicenceController@addUnityLicence');

    Route::post('/convertAll', 'LicenceController@convertAll');
    Route::post('/getCancelSubscription', 'LicenceController@getCancelSubscription');
    Route::post('/cancelSubscription', 'LicenceController@cancelSubscription');
    Route::post('/downloads', 'DownloadController@index');
    Route::post('/versions', 'VersionController@index');
    Route::post('/add-version', 'VersionController@store');
    Route::delete('/delete-version/{id}', 'VersionController@destroy');
    Route::get('/version/{id}', 'VersionController@edit');
    Route::get('/all-versions', 'VersionController@show');
    Route::post('/version-status', 'VersionController@changeStatus');
    Route::post('/all-count', 'EmployeeController@getAllCounts');
    Route::post('/get-chart-data', 'EmployeeController@getChartData');
    Route::get('/licence-types', 'LicenceController@licenceType');
    Route::post('/regenerate-licence', 'LicenceController@regenerateLicence')->name('regenerate-licence');

    Route::post('/add-download', 'DownloadController@store');
    Route::post('/download-log', 'DownloadController@downloadLog');
    Route::post('/download-log-list', 'DownloadController@downloadLogList');
    Route::post('/status-download', 'DownloadController@changeStaus');
    Route::delete('/delete-download/{id}', 'DownloadController@destroy');
    Route::get('/download/{id}', 'DownloadController@edit');
    Route::post('/customers', 'CustomerController@index');
    Route::get('/customers/list', 'CustomerController@getCustomersList');
    Route::post('/getAllUsers', 'CustomerController@getAllUsers');
    Route::get('/getAllUsersList', 'CustomerController@getAllUsersList');
    Route::get('/customers/{id}', 'CustomerController@edit');
    Route::post('/customer-status', 'CustomerController@changeStatus');
    Route::post('/add-customer', 'CustomerController@store');
    Route::post('/customer-notify', 'CustomerController@notify')->name('customer-notify');
    Route::post('/get-all-regenerate-licence', 'LicenceController@getAllRegenerateLicence')->name('get-all-regenerate-licence');
    Route::post('/verify-regenerate-licence', 'LicenceController@verifyRegenerateLicence')->name('verify-regenerate-licence');
    Route::post('/theme-change', 'EmployeeController@changeTheme');
    Route::post('/scheme-change', 'EmployeeController@changeScheme');
    Route::delete('/delete-customer/{id}', 'CustomerController@destroy');
    Route::post('/add-subscription', 'SubscriptionController@store');
    Route::post('/getSubscriptionCancelReasons', 'SubscriptionController@getSubscriptionCancelReasons');
    Route::post('/getSubscriptionCancelReasonsByUser', 'SubscriptionController@getSubscriptionCancelReasonsByUser');
    Route::post('/getFeedbackByReasons', 'SubscriptionController@getFeedbackByReasons');
    Route::post('/getSubscriptionCancelReasonByUserData', 'SubscriptionController@getSubscriptionCancelReasonByUserData');
    Route::post('/addArchive', 'SubscriptionController@addArchive');
    Route::post('/change-subscription', 'LicenceController@changeSubscription');
    Route::get('/getChangeSubscriptionData/{id}', 'LicenceController@getChangeSubscriptionData');
    Route::post('/licences', 'LicencesController@index');
    Route::get('/licences/{id}', 'LicencesController@edit');
    Route::post('/add-licence-subscription', 'LicencesController@store');
    Route::delete('/delete-licence-subscription/{id}', 'LicencesController@destroy');
    Route::post('/change-licence-subscription-status', 'LicencesController@changeLicenceSubscriptionStatus');
    Route::get('/get-all-products', 'LicencesController@getAllProducts');
    Route::get('/get-all-products-edit/{id}', 'LicencesController@getAllProductsEdit');

    //promotion
    Route::post('/promotions', 'PromotionController@index');
    Route::get('/promotions/{id}', 'PromotionController@edit');
    Route::post('/promotion-status', 'PromotionController@changeStatus');
    Route::post('/add-promotion', 'PromotionController@store');
    Route::delete('/delete-promotion/{id}', 'PromotionController@destroy');
    Route::post('/assign-promotion', 'PromotionController@assignPromotion');
    Route::post('/assign-user-promotion', 'PromotionController@assignUserPromotion');
    Route::get('/getPromotionById/{id}', 'PromotionController@getPromotionById');
    Route::get('/getUserPromotionById/{id}', 'PromotionController@getUserPromotionById');
    Route::get('/getAllPromotions', 'PromotionController@getAllPromotions');
    Route::get('/getAllPromotionsLists', 'PromotionController@getAllPromotionsLists');
    Route::post('/verifyCoupon', 'PromotionController@verifyCoupon');

    //Notifications
    Route::post('/getNotifications', 'NotificationsController@getNotifications');
    Route::post('/notification-status', 'NotificationsController@changeStatus');
    Route::post('/notification-remove', 'NotificationsController@destroy');


    //Routes for advisor settings
    Route::get('/advisor-settings/{user_id}', 'AdvisorController@advisorSettings')->name('advisor-settings');
    Route::post('/advisor-settings-save', 'AdvisorController@advisorSettingsSave')->name('advisor-settings-save');

    //Routes for pool
    Route::post('/get-pool-request', 'PoolController@index')->name('get-features');
    Route::post('/add-feature', 'PoolController@store')->name('add-feature');
    Route::post('/comment-feature', 'PoolController@commentFeature');
    Route::post('/get-comment-feature', 'PoolController@getCommentFeature');
    Route::post('/feature-status', 'PoolController@changeStatus');
    Route::post('/get-approved-pool-feature', 'PoolController@getApprovedFeatureRequests');
    Route::post('/reject-pool-feature-comment', 'PoolController@rejectFeatureRequestsComment');

    Route::post('/pool-list', 'PoolController@poolList');
    Route::post('/create-pool', 'PoolController@createPool');
    Route::post('/active-pool', 'PoolController@activePool');
    Route::post('/start-pool', 'PoolController@startPool');
    Route::post('/vote-feature', 'PoolController@voteFeature');
    Route::post('/get-votes-pool-features', 'PoolController@getVotesPoolFeatures');
    Route::post('/get-votes-pool-feature-voters', 'PoolController@getVotesPoolFeaturesUsers');
    Route::post('/pool-release', 'PoolController@poolRelease');


    Route::get('/edit-feature/{id}', 'PoolController@editFeature');
    Route::post('/update-feature', 'PoolController@updateFeature');
    Route::delete('/delete-feature/{id}', 'PoolController@destroyFeature');
    Route::get('/edit-pool/{id}', 'PoolController@edit');
    Route::post('/update-pool', 'PoolController@update');
    Route::delete('/delete-pool/{id}', 'PoolController@destroy');
    Route::post('/get-votes-pool-features-users', 'PoolController@getVotesPoolFeaturesUsers');

    ## integration pending
    Route::get('/vote-list', 'PoolController@voteList');
    Route::post('/create-vote', 'PoolController@createVote');
    Route::get('/edit-vote/{id}', 'PoolController@editVote');
    Route::post('/update-vote', 'PoolController@updateVote');
    Route::delete('/delete-vote/{id}', 'PoolController@destroyVote');
    Route::post('/purchase-status', 'PoolController@changePurchaseStatus');
    Route::post('/vote-status', 'PoolController@changeVoteStatus');
    Route::get('user-votes/{user_id}', 'PoolController@VoteByUserId');
    Route::post('/purchase-vote', 'PoolController@purchaseVote');

    Route::prefix('custom-requirements')
        ->group(function () {
            Route::get('', 'CustomRequirementController@index');
            Route::post('', 'CustomRequirementController@store');
            Route::put('', 'CustomRequirementController@update');
            Route::prefix('offer')->group(function () {
                Route::get('/{id}', 'CustomRequirementController@showOffer');
                Route::post('', 'CustomRequirementController@createOffer');
                Route::post('payment', 'CustomRequirementController@acceptOffer');
            });
        });

    // Zoho Api
    Route::prefix('zoho')
        ->group(function () {
            Route::get('create-refresh-token/{code}', 'ZohoController@createRefreshToken');
            Route::prefix('ticket')->group(function () {
                Route::get('/{token}', 'ZohoController@getTickets');
                Route::get('show/{token}/{id}', 'ZohoController@getTicketById');
                Route::post('', 'ZohoController@createTicket');
                Route::prefix('comment')->group(function () {
                    Route::get('/{token}/{id}', 'ZohoController@getTicketComments');
                    Route::post('', 'ZohoController@addTicketComment');
                });
            });
        });
});
