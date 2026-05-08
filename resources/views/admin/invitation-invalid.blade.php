<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invalid invitation &mdash; {{ config('app.name') }}</title>
    @vite('resources/scss/admin/invitation.scss')
</head>
<body>
    <div class="card card--centered">
        <h1>This invitation is no longer valid.</h1>
        <p>The link may have expired or already been used. Please contact your administrator to request a new invitation.</p>
    </div>
</body>
</html>
