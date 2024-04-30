@component('mail::message')
<h1>Reset Password</h1>
<h4>A password reset request has been initiated for your account at your account.</h4>
<br/>
Please click on the below button to To reset your password
<br/>
@component('mail::button', ['url' =>env('FRONTEND_URL').'reset-password?token='.$token])
Reset Password
@endcomponent

<p>Thank You,<br/>
Team Atavism 
</p>
@endcomponent