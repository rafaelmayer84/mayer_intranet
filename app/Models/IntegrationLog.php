<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
class IntegrationLog extends Model
{
    protected $table = 'integration_logs';
    public $timestamps = false;
    protected $fillable = [
        'sync_id',
        'tipo',
        'fonte',
        'status',
        'registros_processados',
        'registros_criados',
        'registros_atualizados',
        'registros_ignorados',
        'erros',
        'duracao_segundos',
        'inicio',
        'fim',
        'created_at',
        'updated_at',
    ];
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'inicio' => 'datetime',
        'fim' => 'datetime',
    ];
    /**
     * Accessor para converter status "concluido" em "success" para exibição
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                // Normalizar o status para exibição
                $status = strtolower(trim($value ?? ''));
                
                // Mapear valores do banco para valores de exibição
                $statusMap = [
                    'concluido' => 'success',
                    'concluída' => 'success',
                    'completed' => 'success',
                    'success' => 'success',
                    'erro' => 'failed',
                    'error' => 'failed',
                    'failed' => 'failed',
                    'pendente' => 'pending',
                    'pending' => 'pending',
                ];
                
                return $statusMap[$status] ?? $status;
            },
        );
    }
    /**
     * Mutator para salvar o status original no banco
     */
    protected function setStatusAttribute($value)
    {
        // Normalizar antes de salvar
        $this->attributes['status'] = strtolower(trim($value ?? ''));
    }
    /**
     * Método helper para verificar se a sincronização foi bem-sucedida
     */
    public function isSuccessful(): bool
    {
        $status = strtolower(trim($this->attributes['status'] ?? ''));
        return in_array($status, ['concluido', 'concluída', 'completed', 'success']);
    }
    /**
     * Método helper para verificar se a sincronização falhou
     */
    public function isFailed(): bool
    {
        $status = strtolower(trim($this->attributes['status'] ?? ''));
        return in_array($status, ['erro', 'error', 'failed']);
    }
    /**
     * Método helper para verificar se a sincronização está pendente
     */
    public function isPending(): bool
    {
        $status = strtolower(trim($this->attributes['status'] ?? ''));
        return in_array($status, ['pendente', 'pending']);
    }
}
