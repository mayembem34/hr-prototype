<label>Title<input name="title" value="{{ $record->title ?? '' }}" maxlength="255" required></label>
<label>Details<textarea name="details" maxlength="10000">{{ $record->details ?? '' }}</textarea></label>
<label>Status<select name="status">@foreach(['planned'=>'Planned','in_progress'=>'In progress','completed'=>'Completed','cancelled'=>'Cancelled'] as $value=>$text)<option value="{{ $value }}" @selected(($record->status ?? 'planned')===$value)>{{ $text }}</option>@endforeach</select></label>
<label>Start date<input type="date" name="start_date" value="{{ $record->start_date ?? '' }}"></label>
<label>Due / expiry date<input type="date" name="due_date" value="{{ $record->due_date ?? '' }}"></label>
<label>Completed date<input type="date" name="completed_date" value="{{ $record->completed_date ?? '' }}"></label>
<label>Provider / issuer<input name="provider" value="{{ $record->provider ?? '' }}" maxlength="255"></label>
<label>Reference<input name="reference" value="{{ $record->reference ?? '' }}" maxlength="255"></label>
