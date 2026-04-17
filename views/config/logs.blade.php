@extends('layout')

@section('title', 'Chat Logs - ' . ($botName ?: $botId))

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Chat Logs: {{ $botName ?: $botId }}</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="{{ $basePath }}/config" class="btn btn-sm btn-outline-secondary">Back to List</a>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-sm">
        <thead>
            <tr>
                <th scope="col" style="width: 150px;">Time</th>
                <th scope="col" style="width: 100px;">Speaker</th>
                <th scope="col">Content</th>
            </tr>
        </thead>
        <tbody>
            @foreach($logs as $log)
            <tr>
                <td>{{ $log['created_at'] }}</td>
                <td>
                    <span class="badge {{ $log['speaker'] === 'bot' ? 'bg-primary' : 'bg-secondary' }}">
                        {{ $log['speaker'] }}
                    </span>
                </td>
                <td style="white-space: pre-wrap;">{{ $log['content'] }}</td>
            </tr>
            @endforeach
            @if(empty($logs))
            <tr>
                <td colspan="3" class="text-center">No logs found.</td>
            </tr>
            @endif
        </tbody>
    </table>
</div>

<nav aria-label="Page navigation">
    <ul class="pagination justify-content-center">
        @if($page > 1)
        <li class="page-item">
            <a class="page-link" href="{{ $basePath }}/config/logs?bot_id={{ $botId }}&page={{ $page - 1 }}">Previous</a>
        </li>
        @else
        <li class="page-item disabled">
            <span class="page-link">Previous</span>
        </li>
        @endif

        <li class="page-item disabled">
            <span class="page-link">Page {{ $page }}</span>
        </li>

        @if($hasMore)
        <li class="page-item">
            <a class="page-link" href="{{ $basePath }}/config/logs?bot_id={{ $botId }}&page={{ $page + 1 }}">Next</a>
        </li>
        @else
        <li class="page-item disabled">
            <span class="page-link">Next</span>
        </li>
        @endif
    </ul>
</nav>
@endsection
