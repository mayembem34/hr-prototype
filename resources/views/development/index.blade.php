@extends('layout')
@section('content')
<p><a href="{{ route('organizations.show',$organization) }}">{{ $organization->name }}</a> / Development / {{ $label }}</p>
<h1>{{ $label }}</h1>
<section><h2>Records</h2>
@forelse($records as $record)
@php($employee = \App\Models\Employee::where('organization_id',$organization->id)->find($record->employee_id))
<article><h3>{{ $record->title }} — {{ $record->first_name }} {{ $record->last_name }}</h3>
<p>{{ str_replace('_',' ',ucfirst($record->status)) }} · Start: {{ $record->start_date ?: 'Not set' }} · Due / expiry: {{ $record->due_date ?: 'Not set' }} · Completed: {{ $record->completed_date ?: 'Not set' }}</p>
<p>{{ $record->details }}</p><p>Provider / issuer: {{ $record->provider ?: 'Not set' }} · Reference: {{ $record->reference ?: 'Not set' }}</p>
@if($employee && app(\App\Services\EmployeeAccess::class)->allows(auth()->user(),$organization,"development.$area.update",$employee))
<details><summary>Edit record</summary><form method="post" action="{{ route('development.update',[$organization,$area,$record->id]) }}">@csrf
@include('development.fields',['record'=>$record])<button>Save</button></form></details>@endif</article>
@empty<p>No records in your scope.</p>@endforelse</section>
@if(auth()->user()->hasPermission("development.$area.create",$organization))<section><h2>Add a record</h2><form method="post" action="{{ route('development.store',[$organization,$area]) }}">@csrf
<label>Employee<select name="employee_id" required><option value="">Select an employee</option>@foreach($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->first_name }} {{ $employee->last_name }} ({{ $employee->employee_number }})</option>@endforeach</select></label>
@include('development.fields',['record'=>null])<button>Add record</button></form></section>@endif
@endsection
