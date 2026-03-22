<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify your email address</title>
<style>
  body { margin: 0; padding: 0; background: #f4f4f4; font-family: Arial, sans-serif; }
  .wrapper { max-width: 600px; margin: 0 auto; background: #ffffff; }
  .body { padding: 32px; color: #333333; font-size: 15px; line-height: 1.6; }
  .footer { background: #f4f4f4; padding: 20px 32px; font-size: 12px; color: #888888; border-top: 1px solid #e0e0e0; }
  .btn { display: inline-block; padding: 12px 24px; background: #1a56db; color: #ffffff; text-decoration: none; border-radius: 4px; font-size: 15px; margin: 16px 0; }
</style>
</head>
<body>
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
  <tr>
    <td align="center" style="padding: 24px 0;">
      <table class="wrapper" width="600" cellpadding="0" cellspacing="0" role="presentation">
        <tr>
          <td class="body">
            <p>Hi {{ $account->contact->first_name ?? $account->email }},</p>
            <p>Thanks for creating an account. Please verify your email address by clicking the button below.</p>
            <p>
              <a href="{{ $verificationUrl }}" class="btn">Verify email address</a>
            </p>
            <p>This link expires in 60 minutes. If you did not create an account, you can safely ignore this email.</p>
            <p>If the button doesn't work, copy and paste this URL into your browser:</p>
            <p style="word-break:break-all;font-size:13px;color:#555555;">{{ $verificationUrl }}</p>
          </td>
        </tr>
        <tr>
          <td class="footer">
            <p>{{ config('app.name') }}</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
