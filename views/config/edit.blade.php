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

            <div class="mb-4">
                <label class="form-label d-block">Bot Characteristics</label>
                <div id="bot_characteristics_container">
                    @forelse($botChars as $char)
                    <div class="input-group mb-2">
                        <input type="text" class="form-control" name="bot_characteristics[]" value="{{ $char }}">
                        <button class="btn btn-outline-danger remove-item" type="button">Remove</button>
                    </div>
                    @empty
                    <div class="input-group mb-2">
                        <input type="text" class="form-control" name="bot_characteristics[]" value="">
                        <button class="btn btn-outline-danger remove-item" type="button">Remove</button>
                    </div>
                    @endforelse
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary add-item" data-container="bot_characteristics_container" data-name="bot_characteristics[]">Add Item</button>
            </div>

            <div class="mb-4">
                <label class="form-label d-block">Human Characteristics</label>
                <div id="human_characteristics_container">
                    @forelse($humanChars as $char)
                    <div class="input-group mb-2">
                        <input type="text" class="form-control" name="human_characteristics[]" value="{{ $char }}">
                        <button class="btn btn-outline-danger remove-item" type="button">Remove</button>
                    </div>
                    @empty
                    <div class="input-group mb-2">
                        <input type="text" class="form-control" name="human_characteristics[]" value="">
                        <button class="btn btn-outline-danger remove-item" type="button">Remove</button>
                    </div>
                    @endforelse
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary add-item" data-container="human_characteristics_container" data-name="human_characteristics[]">Add Item</button>
            </div>

            <div class="mb-4">
                <label class="form-label d-block">Requests</label>
                <div id="requests_container">
                    @forelse($requests as $req)
                    <div class="input-group mb-2">
                        <input type="text" class="form-control" name="requests[]" value="{{ $req }}">
                        <button class="btn btn-outline-danger remove-item" type="button">Remove</button>
                    </div>
                    @empty
                    <div class="input-group mb-2">
                        <input type="text" class="form-control" name="requests[]" value="">
                        <button class="btn btn-outline-danger remove-item" type="button">Remove</button>
                    </div>
                    @endforelse
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary add-item" data-container="requests_container" data-name="requests[]">Add Item</button>
            </div>

            <div class="mb-3">
                <label for="line_target" class="form-label">LINE Target</label>
                <input type="text" class="form-control" id="line_target" name="line_target" value="{{ $lineTarget }}">
            </div>

            <button type="submit" class="btn btn-primary">Save Config</button>
        </form>
    </div>
</div>

<div class="mt-4">
    <a href="{{ $basePath }}/config" class="btn btn-secondary">Back to List</a>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.add-item').forEach(btn => {
            btn.addEventListener('click', () => {
                const containerId = btn.getAttribute('data-container');
                const name = btn.getAttribute('data-name');
                const container = document.getElementById(containerId);
                const div = document.createElement('div');
                div.className = 'input-group mb-2';
                div.innerHTML = `
                    <input type="text" class="form-control" name="${name}" value="">
                    <button class="btn btn-outline-danger remove-item" type="button">Remove</button>
                `;
                container.appendChild(div);
            });
        });

        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-item')) {
                const container = e.target.closest('.input-group').parentElement;
                if (container.querySelectorAll('.input-group').length > 1) {
                    e.target.closest('.input-group').remove();
                } else {
                    e.target.closest('.input-group').querySelector('input').value = '';
                }
            }
        });
    });
</script>
@endsection
