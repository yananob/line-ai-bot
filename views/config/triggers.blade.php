@extends('layout')

@section('title', 'Triggers for ' . $botId)

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Triggers for: {{ $botId }}</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="{{ $basePath }}/config/trigger/edit?bot_id={{ $botId }}" class="btn btn-sm btn-outline-success">Add New Trigger</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead>
                    <tr>
                        <th scope="col">Trigger ID</th>
                        <th scope="col">Event</th>
                        <th scope="col">Schedule</th>
                        <th scope="col">Request</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($triggers as $tid => $tdata)
                    <tr>
                        <td>{{ $tid }}</td>
                        <td>{{ $tdata['event'] ?? '' }}</td>
                        <td>{{ $tdata['date'] ?? '' }} {{ $tdata['time'] ?? '' }}</td>
                        <td><code>{{ $tdata['request'] ?? '' }}</code></td>
                        <td>
                            <a href="{{ $basePath }}/config/trigger/edit?bot_id={{ $botId }}&trigger_id={{ $tid }}" class="btn btn-xs btn-primary">Edit</a>
                            <form action="{{ $basePath }}/config/trigger/delete" method="POST" style="display:inline-block;" onsubmit="return confirm('Delete Trigger?');">
                                <input type="hidden" name="bot_id" value="{{ $botId }}">
                                <input type="hidden" name="trigger_id" value="{{ $tid }}">
                                <button type="submit" class="btn btn-xs btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-4">
    <a href="{{ $basePath }}/config/edit?bot_id={{ $botId }}" class="btn btn-secondary">Back to Config</a>
    <a href="{{ $basePath }}/config" class="btn btn-outline-secondary">Back to List</a>
</div>
@endsection
