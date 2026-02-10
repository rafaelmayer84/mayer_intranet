<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('leads_legacy') || !Schema::hasTable('leads')) {
            DB::table('integration_logs')->insert(['sistema' => 'Central de Leads', 'tipo' => 'migracao', 'fonte' => 'manual', 'status' => 'ignorado', 'mensagem' => 'Transferência não executada', 'detalhes' => json_encode(['motivo' => 'tabelas inexistentes']), 'registros_processados' => 0, 'inicio' => now(), 'created_at' => now(), 'updated_at' => now()]);
            return;
        }
        $totalLegacy = DB::table('leads_legacy')->count();
        $inicio = now();
        DB::table('integration_logs')->insert(['sistema' => 'Central de Leads', 'tipo' => 'migracao', 'fonte' => 'manual', 'status' => 'processando', 'mensagem' => 'Transferência iniciada', 'detalhes' => json_encode(['total' => $totalLegacy]), 'registros_processados' => $totalLegacy, 'inicio' => $inicio, 'created_at' => now(), 'updated_at' => now()]);
        try {
            $legacyLeads = DB::table('leads_legacy')->get();
            $migrated = 0; $skipped = 0; $errors = 0;
            foreach ($legacyLeads as $legacy) {
                try {
                    if (DB::table('leads')->where('telefone', $legacy->telefone)->exists()) { $skipped++; continue; }
                    $metadata = ['origem' => 'leads_legacy', 'legacy_id' => $legacy->id, 'legacy_email' => $legacy->email ?? null, 'legacy_origem' => $legacy->origem ?? null, 'legacy_responsavel_id' => $legacy->responsavel_id ?? null, 'legacy_motivo_perda' => $legacy->motivo_perda ?? null, 'legacy_data_conversao' => $legacy->data_conversao ?? null, 'migrated_at' => now()->toDateTimeString()];
                    $status = 'novo';
                    if (!empty($legacy->data_conversao)) { $status = 'convertido'; } elseif (!empty($legacy->motivo_perda)) { $status = 'descartado'; }
                    DB::table('leads')->insert(['nome' => $legacy->nome ?? 'Nome não informado', 'telefone' => $legacy->telefone ?? 'Não informado', 'contact_id' => null, 'area_interesse' => 'Migrado do sistema anterior', 'cidade' => null, 'resumo_demanda' => 'Lead migrado do sistema anterior. Consultar tabela leads_legacy (ID: ' . $legacy->id . ') para informações completas.', 'palavras_chave' => 'lead, migrado, legacy', 'intencao_contratar' => 'não', 'gclid' => null, 'status' => $status, 'espocrm_id' => null, 'erro_processamento' => null, 'metadata' => json_encode($metadata), 'data_entrada' => $legacy->created_at ?? now(), 'created_at' => $legacy->created_at ?? now(), 'updated_at' => $legacy->updated_at ?? now()]);
                    $migrated++;
                } catch (\Exception $e) { $errors++; DB::table('integration_logs')->insert(['sistema' => 'Central de Leads', 'tipo' => 'migracao', 'fonte' => 'manual', 'status' => 'erro', 'mensagem' => 'Erro registro individual', 'mensagem_erro' => $e->getMessage(), 'detalhes' => json_encode(['legacy_id' => $legacy->id, 'erro' => $e->getMessage()]), 'registros_erro' => 1, 'created_at' => now(), 'updated_at' => now()]); }
            }
            $fim = now();
            DB::table('integration_logs')->insert(['sistema' => 'Central de Leads', 'tipo' => 'migracao', 'fonte' => 'manual', 'status' => 'sucesso', 'mensagem' => 'Transferência concluída', 'detalhes' => json_encode(['total' => $totalLegacy, 'migrados' => $migrated, 'duplicados' => $skipped, 'erros' => $errors]), 'registros_processados' => $totalLegacy, 'registros_criados' => $migrated, 'registros_ignorados' => $skipped, 'registros_erro' => $errors, 'inicio' => $inicio, 'fim' => $fim, 'duracao_segundos' => $fim->diffInSeconds($inicio), 'created_at' => now(), 'updated_at' => now()]);
        } catch (\Exception $e) {
            $fim = now();
            DB::table('integration_logs')->insert(['sistema' => 'Central de Leads', 'tipo' => 'migracao', 'fonte' => 'manual', 'status' => 'erro', 'mensagem' => 'Erro transferência', 'mensagem_erro' => $e->getMessage(), 'detalhes' => json_encode(['erro' => $e->getMessage()]), 'inicio' => $inicio, 'fim' => $fim, 'duracao_segundos' => $fim->diffInSeconds($inicio), 'created_at' => now(), 'updated_at' => now()]);
            throw $e;
        }
    }
    public function down(): void {
        if (!Schema::hasTable('leads')) { return; }
        try {
            $inicio = now();
            $migratedCount = DB::table('leads')->where('area_interesse', 'Migrado do sistema anterior')->count();
            DB::table('integration_logs')->insert(['sistema' => 'Central de Leads', 'tipo' => 'rollback', 'fonte' => 'manual', 'status' => 'processando', 'mensagem' => 'Remoção registros', 'detalhes' => json_encode(['total' => $migratedCount]), 'registros_processados' => $migratedCount, 'inicio' => $inicio, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('leads')->where('area_interesse', 'Migrado do sistema anterior')->delete();
            $fim = now();
            DB::table('integration_logs')->insert(['sistema' => 'Central de Leads', 'tipo' => 'rollback', 'fonte' => 'manual', 'status' => 'sucesso', 'mensagem' => 'Remoção concluída', 'detalhes' => json_encode(['total' => $migratedCount]), 'registros_processados' => $migratedCount, 'inicio' => $inicio, 'fim' => $fim, 'duracao_segundos' => $fim->diffInSeconds($inicio), 'created_at' => now(), 'updated_at' => now()]);
        } catch (\Exception $e) {
            DB::table('integration_logs')->insert(['sistema' => 'Central de Leads', 'tipo' => 'rollback', 'fonte' => 'manual', 'status' => 'erro', 'mensagem' => 'Erro remoção', 'mensagem_erro' => $e->getMessage(), 'detalhes' => json_encode(['erro' => $e->getMessage()]), 'fim' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
    }
};
