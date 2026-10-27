@extends('layout')
@section('content')
<p><a href="{{ route('home') }}">Home</a> / {{ $organization->name }} / Notifications</p><h1>Daily HR notifications</h1><section>@forelse($notifications as $notification)<article><p>{{ $notification->created_at }} · {{ $notification->message }} @if(!$notification->read_at)<strong>Unread</strong>@endif</p>@if(!$notification->read_at && auth()->user()->hasPermission('daily.notifications.read'))<form method="post" action="{{ route('daily.notifications.read',[$organization,$notification->id]) }}">@csrf<button>Mark read</button></form>@endif</article>@empty<p>No notifications.</p>@endforelse</section>
@endsection
