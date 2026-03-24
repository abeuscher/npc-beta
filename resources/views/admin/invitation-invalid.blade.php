<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invalid invitation &mdash; {{ config('app.name') }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
            font-size: 0.9375rem;
            background: #f1f5f9;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .card {
            background: #fff;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.12), 0 1px 2px rgba(0,0,0,.08);
            padding: 2.25rem 2rem;
            width: 100%;
            max-width: 24rem;
            text-align: center;
        }
        h1 { font-size: 1.25rem; font-weight: 600; margin-bottom: 0.75rem; }
        p { color: #64748b; font-size: 0.9375rem; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="card">
        <h1>This invitation is no longer valid.</h1>
        <p>The link may have expired or already been used. Please contact your administrator to request a new invitation.</p>
    </div>
</body>
</html>
