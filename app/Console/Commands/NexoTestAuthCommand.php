<?php

namespace App\Console\Commands;

use App\Models\NexoAuthAttempt;
use App\Services\NexoConsultaService;
use Illuminate\Console\Command;

class NexoTestAuthCommand extends Command
{
    protected $signature = 'nexo:test-auth {telefone} {--cpf=}';
    protected $description = 'Smoke test do ciclo completo de autenticação NEXO para um cliente real';

    public function handle(NexoConsultaService $svc): int
    {
        $telefone = $this->argument('telefone');
        $cpf      = $this->option('cpf') ?: null;

        $this->info("━━━ NEXO AUTH SMOKE TEST ━━━");
        $this->line("  Telefone : {$telefone}");
        if ($cpf) $this->line("  CPF      : {$cpf}");
        $this->newLine();

        $passed = 0;
        $failed = 0;

        // ── Passo 1: identificarCliente ───────────────────────────────────────
        $id = $svc->identificarCliente($telefone, $cpf);

        if ($id['encontrado'] !== 'sim') {
            $this->line("  <fg=red>✗</> identificarCliente: cliente NÃO encontrado");
            $this->line("    <fg=yellow>→ Verifique se o telefone/CPF existe na tabela clientes</>");
            return Command::FAILURE;
        }

        if ($id['bloqueado'] === 'sim') {
            $this->line("  <fg=red>✗</> identificarCliente: cliente BLOQUEADO");
            $this->line("    <fg=yellow>→ Execute: php artisan nexo:test-auth {$telefone} após 30min ou limpe nexo_auth_attempts</>");
            return Command::FAILURE;
        }

        $this->line("  <fg=green>✓</> identificarCliente: encontrado=sim, nome={$id['nome']}");
        $passed++;

        // ── Passo 2: gerarPerguntasAuth ───────────────────────────────────────
        $perguntas = $svc->gerarPerguntasAuth($telefone, $cpf);

        if (isset($perguntas['erro'])) {
            $this->line("  <fg=red>✗</> gerarPerguntasAuth: {$perguntas['erro']}");
            $failed++;
            $this->printSummary($passed, $failed);
            return Command::FAILURE;
        }

        $sessionToken = $perguntas['session_token'] ?? null;
        $temPin       = $perguntas['tem_pin'] ?? 'nao';
        $qtd          = $temPin === 'sim' ? 2 : 4;

        $this->line("  <fg=green>✓</> gerarPerguntasAuth: {$qtd} perguntas, tem_pin={$temPin}, session_token=" . ($sessionToken ? 'OK' : 'AUSENTE'));
        $passed++;

        // Buscar o cliente para resolver respostas corretas (usa mesma lógica do service)
        $telefoneNorm = preg_replace('/\D/', '', $telefone);
        if (strlen($telefoneNorm) <= 11) $telefoneNorm = '55' . $telefoneNorm;

        $cliente = $svc->buscarClientePorTelefone($telefoneNorm);
        if (!$cliente && $cpf) {
            $cliente = \Illuminate\Support\Facades\DB::table('clientes')
                ->where('cpf_cnpj', preg_replace('/\D/', '', $cpf))
                ->first();
        }

        // Mostrar perguntas e respostas corretas
        $respostas = [
            'session_token' => $sessionToken ?? '',
            'pin_valor'     => '',
        ];

        for ($n = 1; $n <= $qtd; $n++) {
            $campo   = $perguntas["pergunta{$n}_campo"] ?? '';
            $texto   = $perguntas["pergunta{$n}_texto"] ?? '';
            $opcaoA  = $perguntas["pergunta{$n}_opcao_a"] ?? '';
            $opcaoB  = $perguntas["pergunta{$n}_opcao_b"] ?? '';
            $opcaoC  = $perguntas["pergunta{$n}_opcao_c"] ?? '';

            $correta = $cliente ? $svc->resolverRespostaCorreta($cliente, $campo) : '?';

            // Identificar qual opção é a correta
            $opcaoCorreta = '?';
            foreach (['A' => $opcaoA, 'B' => $opcaoB, 'C' => $opcaoC] as $letra => $val) {
                if (strtolower(trim($val)) === strtolower(trim($correta))) {
                    $opcaoCorreta = $letra;
                    break;
                }
            }

            $this->line("    P{$n} [{$campo}]: {$texto}");
            $this->line("       A={$opcaoA}  B={$opcaoB}  C={$opcaoC}");
            $this->line("       <fg=cyan>→ correta: {$correta} [opção {$opcaoCorreta}]</>");

            $respostas["pergunta{$n}_campo"] = $campo;
            $respostas["pergunta{$n}_valor"] = $correta;
        }

        // ── Passo 3: validarAuth com respostas corretas ───────────────────────
        $resultado = $svc->validarAuth($telefone, $respostas, $cpf);

        if ($resultado['valido'] === 'sim') {
            $this->line("  <fg=green>✓</> validarAuth (corretas): valido=sim, sessão 30min ativa");
            $passed++;
        } else {
            $this->line("  <fg=red>✗</> validarAuth (corretas): valido=nao, bloqueado={$resultado['bloqueado']}, restantes={$resultado['tentativas_restantes']}");
            $this->line("    <fg=yellow>→ Verifique os logs: tail -100 storage/logs/laravel.log | grep NEXO-AUTH</>");
            $failed++;
        }

        // ── Passo 4: validarAuth com resposta errada ──────────────────────────
        // Gerar novo session_token para este sub-teste
        $perguntas2 = $svc->gerarPerguntasAuth($telefone, $cpf);
        $respostasErradas = [
            'session_token'   => $perguntas2['session_token'] ?? '',
            'pin_valor'       => '',
            'pergunta1_campo' => $perguntas2['pergunta1_campo'] ?? '',
            'pergunta1_valor' => '__resposta_errada_intencional__',
            'pergunta2_campo' => $perguntas2['pergunta2_campo'] ?? '',
            'pergunta2_valor' => '__resposta_errada_intencional__',
            'pergunta3_campo' => $perguntas2['pergunta3_campo'] ?? '',
            'pergunta3_valor' => '__resposta_errada_intencional__',
            'pergunta4_campo' => $perguntas2['pergunta4_campo'] ?? '',
            'pergunta4_valor' => '__resposta_errada_intencional__',
        ];

        $resultadoErrado = $svc->validarAuth($telefone, $respostasErradas, $cpf);

        if ($resultadoErrado['valido'] === 'nao') {
            $restantes = $resultadoErrado['tentativas_restantes'];
            $bloqueado = $resultadoErrado['bloqueado'];
            $this->line("  <fg=green>✓</> validarAuth (erradas): valido=nao, tentativas_restantes={$restantes}, bloqueado={$bloqueado}");
            $passed++;
        } else {
            $this->line("  <fg=red>✗</> validarAuth (erradas): esperava valido=nao mas obteve sim — BRECHA DE SEGURANÇA");
            $failed++;
        }

        // ── Cleanup: zerar tentativas para não poluir prod ───────────────────
        $telNorm = preg_replace('/\D/', '', $telefone);
        if (strlen($telNorm) <= 11) $telNorm = '55' . $telNorm;

        NexoAuthAttempt::where('telefone', $telNorm)->update([
            'tentativas'   => 0,
            'bloqueado'    => false,
            'bloqueado_ate' => null,
        ]);
        $this->line("  <fg=gray>↺ tentativas resetadas (cleanup)</>");;

        $this->printSummary($passed, $failed);

        return $failed === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function printSummary(int $passed, int $failed): void
    {
        $this->newLine();
        $total = $passed + $failed;
        if ($failed === 0) {
            $this->info("  [PASS] {$passed}/{$total} verificações OK");
        } else {
            $this->error("  [FAIL] {$failed} falha(s) em {$total} verificações");
        }
    }
}
