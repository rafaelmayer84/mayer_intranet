<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class NexoClienteValidacao extends Model
{
    protected $table = 'nexo_clientes_validacao';
    protected $fillable = ['telefone','cliente_id','cpf_cnpj','numero_processo','nome_mae','cidade_nascimento','cidade_primeiro_processo','ano_inicio_processo','valor_causa','tipo_acao','tentativas_falhas','bloqueado_ate'];
    protected $casts = ['tentativas_falhas'=>'integer','bloqueado_ate'=>'datetime','valor_causa'=>'decimal:2'];

    public function estaBloqueado(): bool {
        return $this->bloqueado_ate && $this->bloqueado_ate->isFuture();
    }
    public function bloquear(int $minutos = 30): void {
        $this->bloqueado_ate = now()->addMinutes($minutos);
        $this->save();
    }
    public function desbloquear(): void {
        $this->bloqueado_ate = null;
        $this->tentativas_falhas = 0;
        $this->save();
    }
    public function incrementarTentativa(): void {
        $this->tentativas_falhas++;
        if($this->tentativas_falhas >= 3) $this->bloquear(30);
        $this->save();
    }
    public function resetarTentativas(): void {
        $this->tentativas_falhas = 0;
        $this->save();
    }
    public function getCpfCnpjMascaradoAttribute(): string {
        if(!$this->cpf_cnpj) return '';
        $v = preg_replace('/\D/','',$this->cpf_cnpj);
        return strlen($v)===11 ? '***.' . substr($v,3,3) . '.' . substr($v,6,3) . '-**' : '**.***.'. substr($v,5,3) . '/' . substr($v,8,4) . '-**';
    }
    public function logs() {
        return $this->hasMany(NexoAutomationLog::class,'telefone','telefone');
    }
}
