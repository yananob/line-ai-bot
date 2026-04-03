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
                <label for="json_content" class="form-label">Config (JSON)</label>
                <textarea class="form-control" id="json_content" name="json_content" rows="10" required>{{ $dataJson }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary">Save Config</button>
        </form>
    </div>
</div>

@if($botId)
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Triggers</span>
        <button type="button" class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#triggerModal">Add Trigger</button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead>
                    <tr>
                        <th scope="col">Trigger ID</th>
                        <th scope="col">Data (JSON)</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($triggers as $tid => $tdata)
                    <tr>
                        <td>{{ $tid }}</td>
                        <td><code>{{ json_encode($tdata, JSON_UNESCAPED_UNICODE) }}</code></td>
                        <td>
                            <button type="button" class="btn btn-xs btn-primary edit-trigger"
                                data-id="{{ $tid }}"
                                data-json="{{ json_encode($tdata, JSON_UNESCAPED_UNICODE) }}"
                                data-bs-toggle="modal" data-bs-target="#triggerModal">Edit</button>
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

<!-- Modal -->
<div class="modal fade" id="triggerModal" tabindex="-1" aria-labelledby="triggerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ $basePath }}/config/trigger/save" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="triggerModalLabel">Trigger Editor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="bot_id" value="{{ $botId }}">
                    <div class="mb-3">
                        <label for="trigger_id" class="form-label">Trigger ID</label>
                        <input type="text" class="form-control" id="trigger_id" name="trigger_id" placeholder="leave empty to auto-generate">
                    </div>
                    <div class="mb-3">
                        <label for="trigger_json" class="form-label">Trigger Data (JSON)</label>
                        <textarea class="form-control" id="trigger_json" name="trigger_json" rows="5" required>{"event": "timer", "date": "", "time": "", "request": ""}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Trigger</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const triggerModal = document.getElementById('triggerModal');
        const triggerIdInput = document.getElementById('trigger_id');
        const triggerJsonInput = document.getElementById('trigger_json');

        document.querySelectorAll('.edit-trigger').forEach(button => {
            button.addEventListener('click', () => {
                triggerIdInput.value = button.getAttribute('data-id');
                triggerIdInput.setAttribute('readonly', 'true');
                triggerJsonInput.value = button.getAttribute('data-json');
            });
        });

        triggerModal.addEventListener('hidden.bs.modal', () => {
            triggerIdInput.value = '';
            triggerIdInput.removeAttribute('readonly');
            triggerJsonInput.value = '{"event": "timer", "date": "", "time": "", "request": ""}';
        });
    });
</script>
@endif

<div class="mt-4">
    <a href="{{ $basePath }}/config" class="btn btn-secondary">Back to List</a>
</div>
@endsection
