@extends('layout')
@section('content')
<p><a href="{{ route('lifecycle.self',$organization) }}">My checklists</a></p><h1>Lifecycle notifications</h1><section>@forelse($notifications as $notification)<p>{{ $notification->created_at }} · {{ $notification->message }} · {{ $notification->read_at ? 'Read' : 'Unread' }}</p>@if(!$notification->read_at && auth()->user()->hasPermission('lifecycle.self.notifications.update'))<form method="post" action="{{ route('lifecycle.notification.read',[$organization,$notification->id]) }}">@csrf<button>Mark read</button></form>@endif @empty<p>No notifications.</p>@endforelse</section>
@endsection
