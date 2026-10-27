@extends('layout')
@section('content')
<p><a href="{{ route('home') }}">Home</a> / {{ $organization->name }} / My attendance</p><h1>My attendance</h1>
<section><p>Times are displayed in UTC.</p>
@if(auth()->user()->hasPermission('attendance.self.clock_in'))<form method="post" action="{{ route('attendance.clock-in',$organization) }}">@csrf<button>Clock in</button></form>@endif
@if(auth()->user()->hasPermission('attendance.self.clock_out'))<form method="post" action="{{ route('attendance.clock-out',$organization) }}">@csrf<button>Clock out</button></form>@endif</section>
<section><h2>Attendance history</h2><ul>@forelse($entries as $entry)<li>{{ $entry->clock_in }} — {{ $entry->clock_out ?? 'Still clocked in' }}</li>@empty<li>No entries yet.</li>@endforelse</ul></section>
@endsection
