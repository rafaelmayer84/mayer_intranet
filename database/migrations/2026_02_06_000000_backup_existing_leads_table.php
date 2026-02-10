<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('leads')) {
            $total = DB::table('leads')->count();
            $inicio = now();
            DB::table('integration_logs')->insert(['sistema' => 'Central de Leads', 'tipo' => 'migracao', 'fonte' => 'manual', 'status' => 'em_andamento', 'mensagem' => 'Backup tabela leads', 'detalhes' => json_encode(['total' => $total]), 'registros_processados' => $total, 'inicio' => $inicio, 'created_at' => now(), 'updated_at' => now()]);
            try {
                Schema::rename('leads', 'leads_legacy');
                $fim = now();
                DB::table('integration_logs')->insert(['sistema' => 'Central de Leads', 'tipo' => 'migracao', 'fonte' => 'manual', 'status' => 'sucesso', 'mensagem' => 'Backup concluído', 'detalhes' => json_encode(['total' => $total]), 'registros_processados' => $total, 'inicio' => $inicio, 'fim' => $fim, 'duracao_segundos' => $fim->diffInSeconds($inicio), 'created_at' => now(), 'updated_at' => now()]);
            } catch (\Exception $e) {
                $fim = now();
                DB::table('integration_logs')->insert(['sistema' => 'Central de Leads', 'tipo' => 'migracao', 'fonte' => 'manual', 'status' => 'erro', 'mensagem' => 'Erro backup', 'mensagem_erro' => $e->getMessage(), 'detalhes' => json_encode(['erro' => $e->getMessage()]), 'registros_processados' => $total, 'registros_erro' => 1, 'inicio' => $inicio, 'fim' => $fim, 'duracao_segundos' => $fim->diffInSeconds($inicio), 'created_at' => now(), 'updated_at' => now()]);
                throw $e;
            }
        } else {
            DB::table('integration_logs')->insert(['sistema' => 'Central de Leads', 'tipo' => 'migracao', 'fonte' => 'manual', 'status' => 'ignorado', 'mensagem' => 'Backup não necessário', 'detalhes' => json_encode(['motivo' => 'tabela inexistente']), 'registros_processados' => 0, 'inicio' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
    }
    public function down(): void {
        if (Schema::hasTable('leads_legacy') && !Schema::hasTable('leads')) {
            $inicio = now();
            DB::table('integration_logs')->insert(['sistema' => 'Central de Leads', 'tipo' => 'rollback', 'fonte' => 'manual', 'status' => 'processando', 'mensagem' => 'Restauração', 'detalhes' => json_encode(['acao' => 'restaurar']), 'inicio' => $inicio, 'created_at' => now(), 'updated_at' => now()]);
            Schema::rename('leads_legacy', 'leads');
            $fim = now();
            DB::table('integration_logs')->insert(['sistema' => 'Central de Leads', 'tipo' => 'rollback', 'fonte' => 'manual', 'status' => 'sucesso', 'mensagem' => 'Restaurado', 'detalhes' => json_encode(['acao' => 'restaurado']), 'inicio' => $inicio, 'fim' => $fim, 'duracao_segundos' => $fim->diffInSeconds($inicio), 'created_at' => now(), 'updated_at' => now()]);
        }
    }
};
