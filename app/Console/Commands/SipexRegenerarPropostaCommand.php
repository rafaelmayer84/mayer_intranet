<?php

namespace App\Console\Commands;

use App\Models\PricingProposal;
use App\Services\ProposalClaudeService;
use Illuminate\Console\Command;

class SipexRegenerarPropostaCommand extends Command
{
    protected $signature = 'sipex:regenerar {id : ID da proposta} {--dry-run : Simula sem gravar}';
    protected $description = 'Regenera texto da proposta cliente via Claude (para propostas que falharam)';

    public function handle()
    {
        $id = $this->argument('id');
        $dryRun = $this->option('dry-run');

        $proposta = PricingProposal::find($id);

        if (!$proposta) {
            $this->error("Proposta #{$id} não encontrada.");
            return 1;
        }

        if (!$proposta->proposta_escolhida) {
            $this->error("Proposta #{$id} não tem proposta_escolhida definida. Fluxo incompleto.");
            return 1;
        }

        $this->info("Proposta #{$id}: {$proposta->nome_proponente}");
        $this->info("Área: {$proposta->area_direito}");
        $this->info("Escolhida: {$proposta->proposta_escolhida}");

        $escolhida = $proposta->proposta_escolhida;
        $propostaData = $proposta->{'proposta_' . $escolhida} ?? [];

        if (empty($propostaData)) {
            $this->error("Dados da proposta {$escolhida} estão vazios.");
            return 1;
        }

        $this->info("Valor: R$ " . number_format($propostaData['valor_honorarios'] ?? 0, 2, ',', '.'));

        if ($dryRun) {
            $this->warn("[DRY-RUN] Simulação — não vai chamar a API.");
            return 0;
        }

        $this->info("Gerando texto via Claude...");

        $service = app(ProposalClaudeService::class);

        // Etapa 1: Sugerir configuração
        $config = $service->sugerirConfiguracao($proposta);

        if (isset($config['_fallback']) && $config['_fallback']) {
            $this->warn("Usando config fallback: " . ($config['_erro'] ?? 'erro desconhecido'));
        }

        // Garantir valores mínimos
        $config['valor_honorarios'] = $config['valor_honorarios'] ?? $propostaData['valor_honorarios'] ?? 0;
        $config['parcelas'] = $config['parcelas'] ?? $propostaData['parcelas_sugeridas'] ?? 1;
        $config['tipo_cobranca'] = $config['tipo_cobranca'] ?? $propostaData['tipo_cobranca'] ?? 'fixo';
        $config['escopo'] = $config['escopo'] ?? '1ª instância até sentença';
        $config['vigencia_dias'] = $config['vigencia_dias'] ?? 15;
        $config['despesas_selecionadas'] = $config['despesas_sugeridas'] ?? ['Custas judiciais'];
        $config['incluir_exito'] = $config['incluir_exito'] ?? false;
        $config['incluir_tabela_horas'] = $config['incluir_tabela_horas'] ?? false;

        $this->info("Config: valor=" . $config['valor_honorarios'] . ", parcelas=" . $config['parcelas']);

        // Etapa 2: Gerar proposta
        $resultado = $service->gerarProposta($proposta, $config);

        if (isset($resultado['error'])) {
            $this->error("Erro na geração: " . $resultado['error']);
            return 1;
        }

        if (empty($resultado['saudacao'])) {
            $this->error("Resposta inválida — sem campo saudacao.");
            return 1;
        }

        // Salvar
        $proposta->texto_proposta_cliente = json_encode($resultado, JSON_UNESCAPED_UNICODE);
        $proposta->save();

        $this->info("✅ Proposta #{$id} regenerada com sucesso!");
        $this->info("Campos gerados: " . implode(', ', array_keys($resultado)));

        return 0;
    }
}
