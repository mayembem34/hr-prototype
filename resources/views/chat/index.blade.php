@extends('layout')
@section('content')
<p><a href="{{ route('home') }}">Home</a> / {{ $organization->name }} / Staff chat</p><h1>Staff chat</h1>
@if($general)<section><h2>Organization conversation</h2><p>Visible to members with organization chat viewing permission.</p>@if(auth()->user()->hasPermission('chat.general.send',$organization))<form method="post" action="{{ route('chat.general.send',$organization) }}">@csrf<label>Message<textarea name="body" maxlength="5000" required></textarea></label><button>Send</button></form>@endif
@forelse($messages as $message)<article><p><strong>{{ $message->sender_name }}</strong> · {{ $message->created_at }}</p><p style="white-space:pre-wrap">{{ $message->body }}</p></article>@empty<p>No messages yet.</p>@endforelse {{ $messages->links() }}</section>@endif
@if($direct)<section><h2>Direct messages</h2><ul>@forelse($members as $member)<li><a href="{{ route('chat.direct',[$organization,$member->id]) }}">{{ $member->name }}</a></li>@empty<li>No other members with direct chat access.</li>@endforelse</ul></section>@endif
@endsection
