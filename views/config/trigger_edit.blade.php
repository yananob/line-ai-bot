@extends('layout')

@section('title', ($triggerId ? 'Edit' : 'Add') . ' Trigger for ' . $botId)

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">{{ $triggerId ? 'Edit Trigger: ' . $triggerId : 'Add New Trigger' }} (Bot: {{ $botId }})</h1>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form action="{{ $basePath }}/config/trigger/save" method="POST">
            <input type="hidden" name="bot_id" value="{{ $botId }}">
            <div class="mb-3">
                <label for="trigger_id" class="form-label">Trigger ID</label>
                <input type="text" class="form-control" id="trigger_id" name="trigger_id" value="{{ $triggerId ?: '' }}" {{ $triggerId ? 'readonly' : '' }} placeholder="Leave empty for auto-generation">
            </div>

            <div class="mb-3">
                <label for="event" class="form-label">Event Type</label>
                <select class="form-select" id="event" name="event" required>
                    <option value="timer" {{ ($trigger['event'] ?? '') === 'timer' ? 'selected' : '' }}>Timer</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="date" class="form-label">Date (e.g., today, tomorrow, or YYYY-MM-DD)</label>
                <input type="text" class="form-control" id="date" name="date" value="{{ $trigger['date'] ?? '' }}" placeholder="today" required>
            </div>

            <div class="mb-3">
                <label for="time" class="form-label">Time (e.g., HH:MM, or now +X mins)</label>
                <input type="text" class="form-control" id="time" name="time" value="{{ $trigger['time'] ?? '' }}" placeholder="08:00" required>
            </div>

            <div class="mb-3">
                <label for="request" class="form-label">Request (Instruction for AI)</label>
                <textarea class="form-control" id="request" name="request" rows="5" required>{{ $trigger['request'] ?? '' }}</textarea>
            </div>

            <button type="submit" class="btn btn-primary">Save Trigger</button>
            <a href="{{ $basePath }}/config/triggers?bot_id={{ $botId }}" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>
@endsection
