@extends('layout')

@section('title', 'Edit Bot Configuration')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">{{ $botId ? 'Edit Bot: ' . $botId : 'Add New Bot' }}</h1>
</div>

<div class="card mb-4">
    <div class="card-header">Main Config</div>
    <div class="card-body">
        <form action="{{ $basePath }}/config/save" method="POST">
            <div class="mb-3">
                <label for="bot_id" class="form-label">Bot ID</label>
                <input type="text" class="form-control" id="bot_id" name="bot_id" value="{{ $botId ?: '' }}" {{ $botId ? 'readonly' : '' }} required>
            </div>

            <div class="mb-3">
                <label for="bot_characteristics" class="form-label">Bot Characteristics (one per line)</label>
                <textarea class="form-control" id="bot_characteristics" name="bot_characteristics" rows="5">{{ $botChars }}</textarea>
            </div>

            <div class="mb-3">
                <label for="human_characteristics" class="form-label">Human Characteristics (one per line)</label>
                <textarea class="form-control" id="human_characteristics" name="human_characteristics" rows="5">{{ $humanChars }}</textarea>
            </div>

            <div class="mb-3">
                <label for="requests" class="form-label">Requests (one per line)</label>
                <textarea class="form-control" id="requests" name="requests" rows="5">{{ $requests }}</textarea>
            </div>

            <div class="mb-3">
                <label for="line_target" class="form-label">LINE Target</label>
                <input type="text" class="form-control" id="line_target" name="line_target" value="{{ $lineTarget }}">
            </div>

            <button type="submit" class="btn btn-primary">Save Config</button>
            @if($botId)
            <a href="{{ $basePath }}/config/triggers?bot_id={{ $botId }}" class="btn btn-outline-info">Manage Triggers</a>
            @endif
        </form>
    </div>
</div>

<div class="mt-4">
    <a href="{{ $basePath }}/config" class="btn btn-secondary">Back to List</a>
</div>
@endsection
