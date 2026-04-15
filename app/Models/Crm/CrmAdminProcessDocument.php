<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class CrmAdminProcessDocument extends Model
{
    protected $table = 'crm_admin_process_documents';

    protected $fillable = [
        'admin_process_id','step_id','uploaded_by_user_id','category',
        'original_name','disk_path','mime_type','size_bytes','is_client_visible','notes',
    ];

    protected $casts = [
        'is_client_visible' => 'boolean',
    ];

    public function process()
    {
        return $this->belongsTo(CrmAdminProcess::class, 'admin_process_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by_user_id');
    }

    public function step()
    {
        return $this->belongsTo(CrmAdminProcessStep::class, 'step_id');
    }

    public function categoryLabel(): string
    {
        return match($this->category) {
            'requerido_cliente'  => 'Req. Cliente',
            'produzido_interno'  => 'Produzido',
            'recebido_terceiro'  => 'Rec. Terceiro',
            'enviado_terceiro'   => 'Env. Terceiro',
            'geral'              => 'Geral',
            default              => ucfirst($this->category),
        };
    }

    public function sizeFmt(): string
    {
        if (!$this->size_bytes) return '';
        if ($this->size_bytes < 1024) return $this->size_bytes . ' B';
        if ($this->size_bytes < 1024 * 1024) return round($this->size_bytes / 1024) . ' KB';
        return round($this->size_bytes / (1024 * 1024), 1) . ' MB';
    }
}
