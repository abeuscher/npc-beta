@extends('layouts.public')

@section('content')
<article style="max-width:480px;margin:3rem auto;">
    {!! \App\Models\SiteSetting::get('system_page_content_email_verify', '<h1>Verify your email</h1>') !!}

    <p>We sent a verification link to <strong>{{ auth('portal')->user()->email }}</strong>. Please check your inbox and click the link to activate your account.</p>

    <p>If you don't see the email, check your spam folder.</p>

    <form method="POST" action="{{ route('portal.logout') }}" style="margin-top:2rem;">
        @csrf
        <button type="submit" class="secondary outline">Log out</button>
    </form>
</article>
@endsection
