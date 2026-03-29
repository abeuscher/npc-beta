<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Email</title>
<style>
  body { margin: 0; padding: 0; background: #f4f4f4; font-family: Arial, sans-serif; }
  .wrapper { max-width: 600px; margin: 0 auto; background: #ffffff; }
  .header { background: {{ $template->header_color ?: '#1a56db' }}; padding: 24px 32px; text-align: center; }
  .header img { max-height: 60px; display: block; margin: 0 auto 12px; }
  .header h1 { color: #ffffff; font-size: 20px; margin: 0; }
  .body { padding: 32px; color: #333333; font-size: 15px; line-height: 1.6; }
  .footer { background: #f4f4f4; padding: 20px 32px; font-size: 12px; color: #888888; border-top: 1px solid #e0e0e0; }
  .footer p { margin: 4px 0; }
</style>
</head>
<body>
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
  <tr>
    <td align="center" style="padding: 24px 0;">
      <table class="wrapper" width="600" cellpadding="0" cellspacing="0" role="presentation">

        {{-- Header --}}
        <tr>
          <td class="header" style="background: {{ $template->header_color ?: '#1a56db' }}; padding: 24px 32px; text-align: center;">
            @if ($headerImageUrl = $template->getHeaderImageUrl())
              <img src="{{ $headerImageUrl }}"
                   alt="{{ $template->header_text ?: '' }}"
                   style="max-height: 60px; display: block; margin: 0 auto 12px;">
            @endif
            @if ($template->header_text)
              <h1 style="color: #ffffff; font-size: 20px; margin: 0;">{{ $template->header_text }}</h1>
            @endif
          </td>
        </tr>

        {{-- Body --}}
        <tr>
          <td class="body" style="padding: 32px; color: #333333; font-size: 15px; line-height: 1.6; background: #ffffff;">
            {!! $body !!}
          </td>
        </tr>

        {{-- Footer --}}
        <tr>
          <td class="footer" style="background: #f4f4f4; padding: 20px 32px; font-size: 12px; color: #888888; border-top: 1px solid #e0e0e0;">
            @if ($template->footer_sender_name)
              <p style="margin: 4px 0;">{{ $template->footer_sender_name }}</p>
            @endif
            @if ($template->footer_reply_to)
              <p style="margin: 4px 0;">Reply to: {{ $template->footer_reply_to }}</p>
            @endif
            @if ($template->footer_address)
              <p style="margin: 4px 0; white-space: pre-line;">{{ $template->footer_address }}</p>
            @endif
            @if ($template->footer_reason)
              <p style="margin: 12px 0 4px;">{{ $template->footer_reason }}</p>
            @endif
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>
</body>
</html>
