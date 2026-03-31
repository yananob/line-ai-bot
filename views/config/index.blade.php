@extends('layout')

@section('title', 'Bot Configuration List')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Bot Configurations</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="/config/edit" class="btn btn-sm btn-outline-primary">Add New Bot</a>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-sm">
        <thead>
            <tr>
                <th scope="col">Bot ID</th>
                <th scope="col">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($botIds as $botId)
            <tr>
                <td>{{ $botId }}</td>
                <td>
                    <a href="/config/edit?bot_id={{ $botId }}" class="btn btn-xs btn-primary">Edit Config & Triggers</a>
                    <form action="/config/delete" method="POST" style="display:inline-block;" onsubmit="return confirm('Delete Bot {{ $botId }}?');">
                        <input type="hidden" name="bot_id" value="{{ $botId }}">
                        <button type="submit" class="btn btn-xs btn-danger">Delete</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
