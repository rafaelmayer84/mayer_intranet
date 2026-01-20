@extends('layouts.app')

@section('title', 'Quadro de Avisos')

@section('content')
<div class="max-w-3xl">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h1 class="text-2xl font-bold text-gray-900">Quadro de Avisos</h1>
        <p class="text-gray-600 mt-2">Parece que o módulo foi instalado, mas o banco ainda não recebeu as tabelas necessárias.</p>

        <div class="mt-4 bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg p-4 text-sm">
            Rode as migrations do Laravel no servidor e recarregue a página.
        </div>

        <div class="mt-4 text-sm text-gray-600">
            Se você está no ambiente de produção, normalmente é:
            <pre class="mt-2 bg-gray-900 text-gray-100 rounded-lg p-4 overflow-x-auto"><code>php artisan migrate --force</code></pre>
        </div>
    </div>
</div>
@endsection
