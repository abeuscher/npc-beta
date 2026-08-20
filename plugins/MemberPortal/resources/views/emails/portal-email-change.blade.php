<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Confirm your new email address</title>
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
            <p>You requested to change your email address to <strong>{{ $newEmail }}</strong>. Click the button below to confirm this change.</p>
            <p>
              <a href="{{ $confirmUrl }}" style="display:inline-block;padding:12px 24px;background:#1a56db;color:#ffffff;text-decoration:none;border-radius:4px;font-size:15px;margin:16px 0;">Confirm new email address</a>
            </p>
            <p>This link expires in 60 minutes. Your current email address will remain active until you confirm the change.</p>
            <p>If you did not request this change, you can safely ignore this email.</p>
            <p>If the button doesn't work, copy and paste this URL into your browser:</p>
            <p style="word-break:break-all;font-size:13px;color:#555555;">{{ $confirmUrl }}</p>
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
