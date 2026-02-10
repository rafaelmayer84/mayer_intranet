@extends('layouts.app')
@section('content')
<div class="container">
    <h1>Clientes</h1>
    <table class="table">
        <thead><tr><th>Nome</th><th>Tipo</th><th>Email</th></tr></thead>
        <tbody>
            @foreach($clientes as $c)
            <tr><td><a href="{{ route('clientes.detalhes', $c->id) }}">{{ $c->nome }}</a></td><td>{{ $c->tipo }}</td><td>{{ $c->email }}</td></tr>
            @endforeach
        </tbody>
    </table>
    {{ $clientes->links() }}
</div>
@endsection
