@extends('layout')
@section('content')
<p><a href="{{ route('training.mine',$organization) }}">My training</a> / Assessment</p><h1>{{ $courseRow->title }} assessment</h1><section><p>Pass mark: {{ $assessment->pass_percent }}%. You may retry until you pass.</p>
@if(in_array($a->status,['assigned','in_progress']) && auth()->user()->hasPermission('training.self.assessment.submit'))<form method="post" action="{{ route('assessment.submit',[$organization,$a->id]) }}">@csrf @foreach($questions as $question)<fieldset><legend>{{ $question->prompt }}</legend>@foreach(json_decode($question->options,true) as $i=>$option)<label><input type="radio" name="answers[{{ $question->id }}]" value="{{ $i }}" required> {{ $option }}</label>@endforeach</fieldset>@endforeach<button>Submit assessment</button></form>@endif</section>
<section><h2>My results</h2><ul>@forelse($attempts as $attempt)<li>{{ $attempt->created_at }} · {{ $attempt->correct_count }}/{{ $attempt->question_count }} ({{ $attempt->score_percent }}%) · {{ $attempt->passed ? 'Passed' : 'Not passed' }}</li>@empty<li>No attempts yet.</li>@endforelse</ul></section>
@endsection
