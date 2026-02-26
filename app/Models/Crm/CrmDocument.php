<?php
namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CrmDocument extends Model
{
    protected $table = 'crm_documents';

    protected $fillable = [
        'account_id', 'uploaded_by_user_id', 'category',
        'original_name', 'normalized_name', 'disk_path',
        'mime_type', 'size_bytes', 'notes',
    ];

    public function account()
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by_user_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->disk_path);
    }

    /**
     * Categorias disponíveis para documentos
     */
    public static function categorias(): array
    {
        return [
            'procuracao'    => 'Procuração',
            'contrato'      => 'Contrato de Honorários',
            'documento_id'  => 'Documento de Identificação',
            'comprovante'   => 'Comprovante de Residência',
            'peticao'       => 'Petição',
            'sentenca'      => 'Sentença / Decisão',
            'certidao'      => 'Certidão',
            'notificacao'   => 'Notificação',
            'correspondencia' => 'Correspondência',
            'financeiro'    => 'Documento Financeiro',
            'geral'         => 'Outros',
        ];
    }

    /**
     * Normalizar nome de arquivo: slug + data + extensão
     */
    public static function normalizarNome(string $originalName, string $category, int $accountId): string
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) ?: 'pdf';
        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $slug = \Illuminate\Support\Str::slug($base, '_');
        if (strlen($slug) > 80) $slug = substr($slug, 0, 80);
        $date = now()->format('Ymd_His');
        return "{$category}_{$slug}_{$date}.{$ext}";
    }
}
