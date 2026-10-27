@extends('layout')
@section('content')
<p><a href="{{ route('organizations.show',$organization) }}">{{ $organization->name }}</a> / Holidays</p><h1>Organization holidays</h1><p>Configured dates are excluded from new leave requests. Add each date for the relevant year. Earlier requests retain their recorded counts.</p>
<section><h2>Holiday dates</h2>@forelse($holidays as $holiday)
@if(auth()->user()->hasPermission('leave.holidays.update',$organization))<form class="row" method="post" action="{{ route('holidays.update',[$organization,$holiday]) }}">@csrf<label>Name<input name="name" value="{{ $holiday->name }}" required></label><label>Date<input type="date" name="date" value="{{ $holiday->date }}" required></label><button>Save</button></form>@else<p>{{ $holiday->date }} — {{ $holiday->name }}</p>@endif
@if(auth()->user()->hasPermission('leave.holidays.delete',$organization))<form method="post" action="{{ route('holidays.delete',[$organization,$holiday]) }}" onsubmit="return confirm('Delete this holiday?')">@csrf @method('DELETE')<button>Delete</button></form>@endif
@empty<p>No holidays configured.</p>@endforelse</section>
@if(auth()->user()->hasPermission('leave.holidays.create',$organization))<section><h2>Add a holiday</h2><form method="post" action="{{ route('holidays.store',$organization) }}">@csrf<label>Name<input name="name" required></label><label>Date<input type="date" name="date" required></label><button>Add holiday</button></form></section>@endif
@endsection
