@extends('layouts.app')

@section('content')
<div class="container mt-5">
    <h1>Metas Mensais - KPIs</h1>
    
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.metas-kpi-mensais.store') }}" class="card p-4">
        @csrf
        
        <div class="row mb-3">
            <div class="col-md-3">
                <label>Ano</label>
                <input type="text" name="year" class="form-control" value="{{ $year }}" required>
            </div>
            <div class="col-md-3">
                <label>Mês</label>
                <input type="text" name="month" class="form-control" value="{{ $month }}" required>
            </div>
        </div>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>KPI</th>
                    <th>Chave</th>
                    <th>Meta</th>
                </tr>
            </thead>
            <tbody>
                @foreach($kpiList as $kpi)
                <tr>
                    <td>{{ $kpi['label'] }}</td>
                    <td><small>{{ $kpi['key'] }}</small></td>
                    <td>
                        <input type="text" name="metas[{{ $kpi['key'] }}]" class="form-control" 
                               value="{{ $metas->get($kpi['key'])?->target_value ?? '' }}">
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <button type="submit" class="btn btn-primary">Salvar Metas</button>
    </form>
</div>
@endsection
