@extends('layout')
@section('content')
<p><a href="{{ route('chat.index',$organization) }}">Staff chat</a> / Direct messages</p><h1>{{ $member->name }}</h1><section><p>This conversation is visible to you and {{ $member->name }}.</p>
@if($canReceive && auth()->user()->hasPermission('chat.direct.send',$organization))<form method="post" action="{{ route('chat.direct.send',[$organization,$member->id]) }}">@csrf<label>Message<textarea name="body" maxlength="5000" required></textarea></label><button>Send</button></form>@elseif(!$canReceive)<p>This member cannot currently receive messages.</p>@endif
@forelse($messages as $message)<article><p><strong>{{ $message->sender_name }}</strong> · {{ $message->created_at }}</p><p style="white-space:pre-wrap">{{ $message->body }}</p></article>@empty<p>No messages yet.</p>@endforelse {{ $messages->links() }}</section>
@endsection
