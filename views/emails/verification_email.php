<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{appName}} Email Verification</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
        .header { background-color: #f2f2f2; padding: 10px; text-align: center; }
        .content { padding: 20px 0; }
        .button { display: inline-block; padding: 10px 20px; margin: 20px 0; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; }
        .footer { text-align: center; font-size: 0.8em; color: #777; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Email Verification for {{appName}}</h2>
        </div>
        <div class="content">
            <p>Hello {{userName}},</p>
            <p>Thank you for registering with {{appName}}. Please click the button below to verify your email address:</p>
            <p style="text-align: center;"><a href="{{verificationLink}}" class="button">Verify Email Address</a></p>
            <p>If you have trouble clicking the button, copy and paste the URL below into your web browser:</p>
            <p><a href="{{verificationLink}}">{{verificationLink}}</a></p>
            <p>If you did not create an account, no further action is required.</p>
            <p>Regards,<br>{{appName}} Team</p>
        </div>
        <div class="footer">
            <p>&copy; {{currentYear}} {{appName}}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
