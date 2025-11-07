<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{appName}} Password Reset</title>
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
            <h2>Password Reset Request for {{appName}}</h2>
        </div>
        <div class="content">
            <p>Hello {{userName}},</p>
            <p>You are receiving this email because we received a password reset request for your account.</p>
            <p>Please click the button below to reset your password:</p>
            <p style="text-align: center;"><a href="{{resetLink}}" class="button">Reset Password</a></p>
            <p>This password reset link will expire in {{resetLinkExpiry}} hours.</p>
            <p>If you did not request a password reset, no further action is required.</p>
            <p>Regards,<br>{{appName}} Team</p>
        </div>
        <div class="footer">
            <p>&copy; {{currentYear}} {{appName}}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
