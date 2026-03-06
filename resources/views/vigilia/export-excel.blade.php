@php
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=vigilia_compromissos_" . date('Y-m-d') . ".xls");
@endphp
<html><head><meta charset="utf-8"></head><body>
<table border="1">
    <thead>
        <tr>
            <th>Status</th>
            <th>Responsável</th>
            <th>Tipo Atividade</th>
            <th>Processo</th>
            <th>Data</th>
            <th>Data Conclusão</th>
            <th>Prazo Fatal</th>
            <th>Cruzamento</th>
            <th>Dias Gap</th>
            <th>Último Andamento</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $r)
        <tr>
            <td>{{ $r->status }}</td>
            <td>{{ $r->responsavel_nome }}</td>
            <td>{{ $r->tipo_atividade }}</td>
            <td>{{ $r->processo_pasta }}</td>
            <td>{{ $r->data_hora ? \Carbon\Carbon::parse($r->data_hora)->format('d/m/Y') : '' }}</td>
            <td>{{ $r->data_conclusao ? \Carbon\Carbon::parse($r->data_conclusao)->format('d/m/Y') : '' }}</td>
            <td>{{ $r->data_prazo_fatal }}</td>
            <td>{{ $r->status_cruzamento }}</td>
            <td>{{ $r->dias_gap }}</td>
            <td>{{ $r->data_ultimo_andamento }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
</body></html>
