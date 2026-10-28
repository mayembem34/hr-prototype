@extends('layout')
@section('title','Sign in · HR Management')
@section('body-class','login-page')
@section('content')
<div class="login-shell">
<aside class="login-intro" aria-label="HR Management">
<div class="login-brand"><span class="login-mark">HR</span><span>HR Management</span></div>
<div class="login-intro-copy"><span class="login-eyebrow">YOUR PEOPLE. ONE WORKSPACE.</span><h2>A better day<br>starts here.</h2><p>Stay connected to your team, manage your workday and keep your development moving.</p></div>
<div class="login-intro-footer"><span class="login-dot"></span> People · Operations · Development</div>
</aside>
<section class="login-form-panel" aria-labelledby="login-heading">
<div class="login-form-heading"><p class="login-eyebrow">WELCOME BACK</p><h1 id="login-heading">Sign in to your workspace</h1><p>Use the account provided by your organization.</p></div>
<form method="post" action="{{ route('login') }}">
@csrf
<label for="login-email">Email address</label>
<input id="login-email" type="email" name="email" value="{{ old('email') }}" placeholder="you@organization.com" required autocomplete="username" autofocus @error('email') aria-invalid="true" @enderror>
<label for="login-password">Password</label>
<div class="login-password-field"><input id="login-password" type="password" name="password" required autocomplete="current-password" @error('password') aria-invalid="true" @enderror><button class="password-toggle" id="password-toggle" type="button" aria-controls="login-password" aria-pressed="false" hidden>Show</button></div>
<button class="login-submit" type="submit">Sign in <span aria-hidden="true">→</span></button>
</form>
<p class="login-help">New here or having trouble signing in?<br>Contact your organization administrator for account access.</p>
</section></div>
<script>(function(){const input=document.getElementById('login-password'),button=document.getElementById('password-toggle');button.hidden=false;button.addEventListener('click',()=>{const visible=input.type==='password';input.type=visible?'text':'password';button.textContent=visible?'Hide':'Show';button.setAttribute('aria-pressed',String(visible));});})();</script>
@endsection
