@extends('layouts.public')

@section('content')
<article style="max-width:480px;margin:3rem auto;">
    <h1>Check your email</h1>

    <p>If an account with that email exists, we've sent a reset link. Please check your inbox.</p>

    <p><a href="{{ route('portal.login') }}">Back to log in</a></p>
</article>
@endsection
