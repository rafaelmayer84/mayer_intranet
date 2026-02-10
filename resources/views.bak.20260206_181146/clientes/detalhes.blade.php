@extends('layouts.app')
@section('content')
<div class="container">
    <h1>{{ $cliente->nome }}</h1>
    <p><strong>Email:</strong> {{ $cliente->email }}</p>
    <p><strong>Telefone:</strong> {{ $cliente->telefone }}</p>
    <h3>Oportunidades</h3>
    @foreach($cliente->oportunidades as $op)
        <div class="card mb-2"><div class="card-body"><h5>{{ $op->nome }}</h5><p>R$ {{ number_format($op->valor, 2, ',', '.') }}</p></div></div>
    @endforeach
</div>
@endsection
