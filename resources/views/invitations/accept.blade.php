@extends('layout')
@section('content')
<section><h1>Set up your account</h1><form method="post" action="{{ route('invitations.accept',$token) }}">@csrf<label>Password (at least 12 characters)<input type="password" name="password" minlength="12" autocomplete="new-password" required></label><label>Confirm password<input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></label><button>Set password</button></form></section>
@endsection
