@extends('layout')

@section('title', 'Edit Configuration')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">{{ $config ? 'Edit' : 'Add' }} Configuration</h1>
</div>

<form action="/config/save" method="POST">
    <div class="mb-3">
        <label for="id" class="form-label">ID</label>
        <input type="text" class="form-control" id="id" name="id" value="{{ $config ? $config->getId() : '' }}" {{ $config ? 'readonly' : '' }} required>
    </div>
    <div class="mb-3">
        <label for="json_content" class="form-label">Data (JSON)</label>
        <textarea class="form-control" id="json_content" name="json_content" rows="15" required>{{ $dataJson }}</textarea>
    </div>
    <div class="mb-3">
        <button type="submit" class="btn btn-primary">Save changes</button>
        <a href="/config" class="btn btn-secondary">Cancel</a>
    </div>
</form>
@endsection
