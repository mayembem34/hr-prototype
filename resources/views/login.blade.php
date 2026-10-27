@extends('layout')
@section('content')
<section><h1>Sign in</h1><form method="post" action="{{ route('login') }}">@csrf<label>Email<input type="email" name="email" value="{{ old('email') }}" required autocomplete="username"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><button>Sign in</button></form></section>
@endsection
