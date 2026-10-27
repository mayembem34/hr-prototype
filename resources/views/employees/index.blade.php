@extends('layout')
@section('content')
<p><a href="{{ route('organizations.show', $organization) }}">{{ $organization->name }}</a> / Employees</p><h1>Employees</h1>
<section><h2>Employee records</h2>@forelse($employees as $employee)<p><a href="{{ route('employees.show', [$organization, $employee]) }}">{{ $employee->first_name }} {{ $employee->last_name }}</a> — {{ $employee->employee_number }} @if($employee->job_title) · {{ $employee->job_title }} @endif @if(auth()->user()->hasPermission('units.view', $organization) && $employee->unit) · {{ $employee->unit->name }} @endif · {{ str_replace('_', ' ', ucfirst($employee->status)) }}</p>@empty<p>No employees yet.</p>@endforelse {{ $employees->links() }}</section>
@if(auth()->user()->hasPermission('employees.create', $organization))<section><h2>Add employee</h2><form method="post" action="{{ route('employees.store', $organization) }}">@csrf @include('employees.form') <button>Create employee</button></form></section>@endif
@endsection
