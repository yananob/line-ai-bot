@extends('layout')

@section('title', 'Configuration List')

@section('content')
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Configurations</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="/config/edit" class="btn btn-sm btn-outline-primary">Add New</a>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-sm">
        <thead>
            <tr>
                <th scope="col">ID</th>
                <th scope="col">Data (JSON)</th>
                <th scope="col">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($configs as $config)
            <tr>
                <td>{{ $config->getId() }}</td>
                <td><code>{{ json_encode($config->getData(), JSON_UNESCAPED_UNICODE) }}</code></td>
                <td>
                    <a href="/config/edit?id={{ $config->getId() }}" class="btn btn-xs btn-primary">Edit</a>
                    <form action="/config/delete" method="POST" style="display:inline-block;" onsubmit="return confirm('Delete?');">
                        <input type="hidden" name="id" value="{{ $config->getId() }}">
                        <button type="submit" class="btn btn-xs btn-danger">Delete</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
