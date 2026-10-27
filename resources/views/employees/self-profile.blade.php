@extends('layout')
@section('content')
<p><a href="{{ route('home') }}">Organizations</a> / {{ $organization->name }}</p><h1>My employee profile</h1><p>Complete your details for {{ $organization->name }}. HR manages your employee number, unit, reporting manager and employment status.</p>
<section><form method="post" action="{{ route('self-profile.save', $organization) }}">@csrf
<div class="row"><label>First name<input name="first_name" value="{{ old('first_name', $employee?->first_name) }}" required></label><label>Last name<input name="last_name" value="{{ old('last_name', $employee?->last_name) }}" required></label></div>
<div class="row"><label>Contact email<input type="email" name="email" value="{{ old('email', $employee?->email ?? auth()->user()->email) }}"></label><label>Phone<input name="phone" maxlength="80" value="{{ old('phone', $employee?->phone) }}"></label><label>Date of birth<input type="date" name="date_of_birth" value="{{ old('date_of_birth', $employee?->date_of_birth?->format('Y-m-d')) }}"></label></div>
<label>Address<input name="address" maxlength="500" value="{{ old('address', $employee?->address) }}"></label>
<h2>Emergency contact</h2><div class="row"><label>Contact name<input name="emergency_contact_name" value="{{ old('emergency_contact_name', $employee?->emergency_contact_name) }}"></label><label>Contact phone<input name="emergency_contact_phone" maxlength="80" value="{{ old('emergency_contact_phone', $employee?->emergency_contact_phone) }}"></label></div><button>Save profile</button></form></section>
@if($employee)<section><h2>Employment details</h2><p>Employee number: {{ $employee->employee_number }}</p><p>Job title: {{ $employee->job_title ?: 'Not set' }}</p><p>Employment type: {{ $employee->employment_type ?: 'Not set' }}</p><p>Start date: {{ $employee->start_date?->format('Y-m-d') ?: 'Not set' }}</p></section>@endif
@endsection
