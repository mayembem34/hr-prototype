@extends('layout')
@section('content')
<p><a href="{{ route('training.index',$organization) }}">Training</a> / Assessment results</p><h1>{{ $courseRow->title }} — {{ $e->first_name }} {{ $e->last_name }}</h1><section><ul>@forelse($attempts as $attempt)<li>{{ $attempt->created_at }} · {{ $attempt->correct_count }}/{{ $attempt->question_count }} ({{ $attempt->score_percent }}%) · {{ $attempt->passed ? 'Passed' : 'Not passed' }}</li>@empty<li>No attempts yet.</li>@endforelse</ul></section>
@endsection
