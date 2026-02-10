<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\WaConversation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NexoClienteResolverService
{
    public function resolve(WaConversation $conversation): ?Cliente
    {
        if ($conversation->linked_cliente_id) {
            return Cliente::find($conversation->linked_cliente_id);
        }

        $cliente = null;
        $metodo  = null;

        if ($conversation->phone) {
            $cliente = $this->buscarPorTelefone($conversation->phone);
            if ($cliente) $metodo = 'telefone';
        }

        if (!$cliente && $conversation->linked_lead_id) {
            $lead = $conversation->lead;
            if ($lead && $lead->email) {
                $cliente = $this->buscarPorEmail($lead->email);
                if ($cliente) $metodo = 'email_via_lead';
            }
        }

        if (!$cliente && $conversation->name && mb_strlen(trim($conversation->name)) >= 5) {
            $cliente = $this->buscarPorNome($conversation->name);
            if ($cliente) $metodo = 'nome_aproximado';
        }

        if ($cliente) {
            try {
                $conversation->update(['linked_cliente_id' => $cliente->id]);
                Log::info('NexoClienteResolver: vinculo criado', [
                    'conversation_id' => $conversation->id,
                    'cliente_id'      => $cliente->id,
                    'metodo'          => $metodo,
                ]);
            } catch (\Throwable $e) {
                Log::warning('NexoClienteResolver: falha ao persistir vinculo', [
                    'conversation_id' => $conversation->id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        return $cliente;
    }

    private function buscarPorTelefone(string $phone): ?Cliente
    {
        $digits = preg_replace('/\D/', '', $phone);
        $candidates = Cliente::where('telefone', $digits)->get();

        if ($candidates->isEmpty() && str_starts_with($digits, '55')) {
            $semDdi = substr($digits, 2);
            $candidates = Cliente::where('telefone', $semDdi)
                ->orWhere('telefone', $digits)
                ->get();
        }

        if ($candidates->isEmpty() && !str_starts_with($digits, '55')) {
            $comDdi = '55' . $digits;
            $candidates = Cliente::where('telefone', $comDdi)
                ->orWhere('telefone', $digits)
                ->get();
        }

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    private function buscarPorEmail(string $email): ?Cliente
    {
        $email = mb_strtolower(trim($email));
        if (empty($email)) return null;
        $candidates = Cliente::whereRaw('LOWER(email) = ?', [$email])->get();
        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    private function buscarPorNome(string $nome): ?Cliente
    {
        $nome = trim($nome);
        if (mb_strlen($nome) < 5) return null;
        $candidates = Cliente::whereRaw('LOWER(nome) = ?', [mb_strtolower($nome)])->get();
        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    public function contextoCliente(Cliente $cliente): array
    {
        $contexto = [
            'id'          => $cliente->id,
            'datajuri_id' => $cliente->datajuri_id,
            'cartao'      => [],
            'processos'   => [],
            'financeiro'  => [],
            'contratos'   => [],
        ];

        try {
            $documento = $cliente->documento ?? $cliente->cpf ?? $cliente->cnpj ?? null;
            $docMascarado = $documento ? $this->mascararDocumento($documento, $cliente->tipo ?? 'PF') : null;
            $contexto['cartao'] = [
                'nome'          => $cliente->nome,
                'tipo_pessoa'   => $cliente->tipo ?? 'PF',
                'documento'     => $docMascarado,
                'email'         => $cliente->email,
                'telefone'      => $cliente->telefone,
                'endereco'      => $cliente->endereco,
                'datajuri_id'   => $cliente->datajuri_id,
                'is_cliente'    => $cliente->is_cliente ?? true,
                'status_pessoa' => $cliente->status_pessoa,
            ];
        } catch (\Throwable $e) {
            $contexto['cartao']['_error'] = $e->getMessage();
        }

        try {
            $processos = DB::table('processos')
                ->where('cliente_id', $cliente->id)
                ->where('status', 'Ativo')
                ->orderByDesc('updated_at')
                ->limit(10)
                ->get(['id', 'pasta', 'numero', 'status', 'tipo_acao', 'assunto', 'natureza', 'proprietario_nome', 'posicao_cliente', 'adverso_nome', 'updated_at']);

            $contexto['processos'] = $processos->map(function ($p) {
                return [
                    'id'                 => $p->id,
                    'numero'             => $p->pasta ?: $p->numero,
                    'status'             => $p->status,
                    'tipo_acao'          => $p->tipo_acao ?? null,
                    'assunto'            => $p->assunto ? mb_substr($p->assunto, 0, 80) : null,
                    'natureza'           => $p->natureza,
                    'responsavel'        => $p->proprietario_nome,
                    'posicao_cliente'    => $p->posicao_cliente ?? null,
                    'adverso'            => $p->adverso_nome ?? null,
                    'ultima_atualizacao' => $p->updated_at,
                ];
            })->toArray();
        } catch (\Throwable $e) {
            $contexto['processos'] = ['_error' => $e->getMessage()];
        }

        try {
            $contas = DB::table('contas_receber')
                ->where(function ($q) use ($cliente) {
                    // FK sólida quando disponível
                    if ($cliente->datajuri_id) {
                        $q->where('pessoa_datajuri_id', $cliente->datajuri_id)
                          ->orWhere('cliente_datajuri_id', $cliente->datajuri_id);
                    }
                    // Fallback por nome (retrocompatibilidade)
                    $q->orWhere('cliente', $cliente->nome);
                })
                ->whereNotIn('status', ['Concluido', 'Concluído', 'Excluido', 'Excluído', 'Pago'])
                ->get(['id', 'valor', 'data_vencimento', 'data_pagamento', 'status', 'descricao']);

            $totalAberto = $contas->sum('valor');
            $hoje = now()->format('Y-m-d');
            $vencidas = $contas->filter(fn($c) => $c->data_vencimento && $c->data_vencimento < $hoje);
            $totalVencido = $vencidas->sum('valor');
            $proximoVenc = $contas
                ->filter(fn($c) => $c->data_vencimento && $c->data_vencimento >= $hoje)
                ->sortBy('data_vencimento')
                ->first();

            $contexto['financeiro'] = [
                'total_aberto'       => round($totalAberto, 2),
                'total_vencido'      => round($totalVencido, 2),
                'qtd_titulos'        => $contas->count(),
                'qtd_vencidos'       => $vencidas->count(),
                'proximo_vencimento' => $proximoVenc->data_vencimento ?? null,
                'proximo_valor'      => $proximoVenc ? round($proximoVenc->valor, 2) : null,
            ];
        } catch (\Throwable $e) {
            $contexto['financeiro'] = ['_error' => $e->getMessage()];
        }

        try {
            $contratos = DB::table('contratos')
                ->where('contratante_id_datajuri', $cliente->datajuri_id)
                ->orderByDesc('data_assinatura')
                ->limit(5)
                ->get(['id', 'numero', 'valor', 'data_assinatura', 'proprietario_nome']);

            $contexto['contratos'] = $contratos->map(function ($c) {
                return [
                    'id'              => $c->id,
                    'numero'          => $c->numero,
                    'valor'           => $c->valor ? round($c->valor, 2) : null,
                    'data_assinatura' => $c->data_assinatura,
                    'responsavel'     => $c->proprietario_nome,
                ];
            })->toArray();
        } catch (\Throwable $e) {
            $contexto['contratos'] = ['_error' => $e->getMessage()];
        }

        return $contexto;
    }

    private function mascararDocumento(?string $doc, string $tipo): ?string
    {
        if (!$doc) return null;
        $digits = preg_replace('/\D/', '', $doc);
        if (strlen($digits) === 11) {
            return substr($digits, 0, 3) . '.***.***-' . substr($digits, 9, 2);
        }
        if (strlen($digits) === 14) {
            return substr($digits, 0, 2) . '.***.***/****-' . substr($digits, 12, 2);
        }
        $len = strlen($digits);
        if ($len > 4) {
            return substr($digits, 0, 2) . str_repeat('*', $len - 4) . substr($digits, -2);
        }
        return $doc;
    }

    public function gerarResumo(Cliente $cliente, array $contexto): string
    {
        $linhas = [];
        $linhas[] = '=== RESUMO DO CLIENTE ===';
        $linhas[] = "Nome: {$cliente->nome}";
        $linhas[] = "Tipo: " . ($cliente->tipo ?? 'PF');
        if ($contexto['cartao']['documento'] ?? null) {
            $linhas[] = "Documento: {$contexto['cartao']['documento']}";
        }
        if ($cliente->email) $linhas[] = "E-mail: {$cliente->email}";
        if ($cliente->telefone) $linhas[] = "Telefone: {$cliente->telefone}";
        if ($cliente->datajuri_id) $linhas[] = "DataJuri ID: {$cliente->datajuri_id}";

        $processos = $contexto['processos'] ?? [];
        if (is_array($processos) && !isset($processos['_error']) && count($processos) > 0) {
            $linhas[] = '';
            $linhas[] = '--- PROCESSOS ATIVOS ---';
            foreach ($processos as $p) {
                $linhas[] = "- {$p['numero']} | {$p['status']} | Resp: " . ($p['responsavel'] ?? 'N/A');
            }
        }

        $fin = $contexto['financeiro'] ?? [];
        if (is_array($fin) && !isset($fin['_error'])) {
            $linhas[] = '';
            $linhas[] = '--- FINANCEIRO ---';
            $linhas[] = "Total em aberto: R$ " . number_format($fin['total_aberto'] ?? 0, 2, ',', '.');
            $linhas[] = "Vencidos: R$ " . number_format($fin['total_vencido'] ?? 0, 2, ',', '.');
            if ($fin['proximo_vencimento'] ?? null) {
                $linhas[] = "Proximo venc.: {$fin['proximo_vencimento']} (R$ " . number_format($fin['proximo_valor'] ?? 0, 2, ',', '.') . ")";
            }
        }

        $contratos = $contexto['contratos'] ?? [];
        if (is_array($contratos) && !isset($contratos['_error']) && count($contratos) > 0) {
            $linhas[] = '';
            $linhas[] = '--- CONTRATOS ---';
            foreach ($contratos as $c) {
                $valor = $c['valor'] ? 'R$ ' . number_format($c['valor'], 2, ',', '.') : 'S/V';
                $linhas[] = "- {$c['numero']} | {$valor} | {$c['data_assinatura']}";
            }
        }

        return implode("\n", $linhas);
    }
}
