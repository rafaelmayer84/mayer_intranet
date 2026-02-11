<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiricConsulta extends Model
{
    protected $table = 'siric_consultas';

    protected $fillable = [
        'cpf_cnpj', 'nome', 'telefone', 'email',
        'rg', 'data_nascimento', 'estado_civil', 'nacionalidade', 'profissao',
        'endereco_rua', 'endereco_numero', 'endereco_complemento',
        'endereco_bairro', 'endereco_cidade', 'endereco_uf', 'endereco_cep',
        'renda_declarada', 'fonte_renda', 'empresa_empregador', 'tempo_emprego',
        'despesas_mensais', 'outras_rendas', 'descricao_outras_rendas',
        'patrimonio_estimado', 'descricao_patrimonio',
        'possui_imovel', 'possui_veiculo', 'valor_imovel', 'valor_veiculo',
        'referencia1_nome', 'referencia1_telefone', 'referencia1_relacao',
        'referencia2_nome', 'referencia2_telefone', 'referencia2_relacao',
        'valor_total', 'parcelas_desejadas', 'finalidade', 'data_primeiro_vencimento',
        'observacoes', 'autorizou_consultas_externas',
        'snapshot_interno', 'actions_ia',
        'rating', 'score', 'comprometimento_max',
        'parcelas_max_sugeridas', 'recomendacao',
        'motivos_ia', 'dados_faltantes_ia',
        'decisao_humana', 'nota_decisao', 'decisao_user_id',
        'status', 'cliente_id', 'user_id',
    ];

    protected $casts = [
        'valor_total'                   => 'decimal:2',
        'renda_declarada'               => 'decimal:2',
        // comprometimento_max: cast manual no accessor
        'autorizou_consultas_externas'  => 'boolean',
        'snapshot_interno'              => 'array',
        'actions_ia'                    => 'array',
        'motivos_ia'                    => 'array',
        'dados_faltantes_ia'            => 'array',
    ];

    // ── Relacionamentos ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function decisaoUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decisao_user_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function evidencias(): HasMany
    {
        return $this->hasMany(SiricEvidencia::class, 'consulta_id');
    }

    public function relatorios(): HasMany
    {
        return $this->hasMany(SiricRelatorio::class, 'consulta_id');
    }

    // ── Helpers ──

    /**
     * Retorna CPF/CNPJ formatado para exibição.
     */
    public function getCpfCnpjFormatadoAttribute(): string
    {
        $doc = preg_replace('/\D/', '', $this->cpf_cnpj);
        if (strlen($doc) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $doc);
        }
        if (strlen($doc) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $doc);
        }
        return $this->cpf_cnpj;
    }

    /**
     * Badge de cor do rating.
     */
    public function getRatingCorAttribute(): string
    {
        return match ($this->rating) {
            'A' => 'green',
            'B' => 'blue',
            'C' => 'yellow',
            'D' => 'orange',
            'E' => 'red',
            default => 'gray',
        };
    }

    /**
     * Badge de cor do status.
     */
    public function getStatusCorAttribute(): string
    {
        return match ($this->status) {
            'rascunho' => 'gray',
            'coletado'  => 'blue',
            'analisado' => 'yellow',
            'decidido'  => 'green',
            default     => 'gray',
        };
    }
}
