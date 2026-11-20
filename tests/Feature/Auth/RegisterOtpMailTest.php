<?php

use App\Mail\RegisterOtpMail;

test('the registration code email tells members to check their spam folder', function () {
    $mail = new RegisterOtpMail('123456');

    $mail->assertSeeInHtml('123456');
    $mail->assertSeeInHtml('spam or junk folder');
    $mail->assertSeeInText('spam or junk folder');
});
