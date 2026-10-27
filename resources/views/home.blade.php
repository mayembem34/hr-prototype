@extends('layout')
@section('content')
<h1>Organizations</h1><p>Choose an organization to manage its foundation.</p><section><ul>@forelse($organizations as $organization)<li><a href="{{ route('organizations.show', $organization) }}">{{ $organization->name }}</a></li>@empty<li>No organizations available.</li>@endforelse</ul></section>
@if(auth()->user()->hasPermission('organizations.create'))<section><h2>Create an organization</h2><form method="post" action="{{ route('organizations.create') }}">@csrf<label>Name<input name="name" maxlength="255" required></label><button>Create organization</button></form></section>@endif
@endsection
