@extends('layouts.app')
@section('content')
<div class="w-full">
    <h1 class="text-xl font-bold mb-4">Clientes</h1>
    <div class="overflow-x-auto bg-white rounded-lg shadow">
        <table class="table w-full">
            <thead><tr><th>Nome</th><th>Tipo</th><th>Email</th></tr></thead>
            <tbody>
                @foreach($clientes as $c)
                <tr><td><a href="{{ route('clientes.detalhes', $c->id) }}">{{ $c->nome }}</a></td><td>{{ $c->tipo }}</td><td>{{ $c->email }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $clientes->links() }}
</div>
@endsection
