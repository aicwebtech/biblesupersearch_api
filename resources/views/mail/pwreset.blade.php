<div style='width: 800px; margin: 0 auto'>
    Bible SuperSearch API<br />
    <br />
    Hello {{ $User->name }} ({{ $User->username }}),<br />
    <br />
    You are receiving this email because we received a password reset request for your account.<br />
    <br />
    <a href='{{$url}}'>Reset Password</a><br />
    <br />
    If you did not request a password reset, no further action is required.<br />
    <br />
    Regards,<br />
    Bible SuperSearch API<br /><br /><br />

    <hr />
    <small>
        If you’re having trouble clicking the "Reset Password" button, please copy and paste the URL below into your web browser:
        <a href='{{$url}}'>{{$url}}</a>
    </small>
</div>


