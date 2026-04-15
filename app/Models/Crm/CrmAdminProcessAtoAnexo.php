<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class CrmAdminProcessAtoAnexo extends Model
{
    protected $table = 'crm_admin_process_ato_anexos';

    protected $fillable = [
        'ato_id','uploaded_by_user_id','original_name','disk_path','mime_type','size_bytes',
    ];

    public function ato()
    {
        return $this->belongsTo(CrmAdminProcessAto::class, 'ato_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by_user_id');
    }

    public function url(): string
    {
        return route('secure-storage', $this->disk_path);
    }

    public function sizeFmt(): string
    {
        if (!$this->size_bytes) return '';
        if ($this->size_bytes < 1024) return $this->size_bytes . ' B';
        if ($this->size_bytes < 1048576) return round($this->size_bytes / 1024) . ' KB';
        return round($this->size_bytes / 1048576, 1) . ' MB';
    }

    public function iconByMime(): string
    {
        if (str_contains($this->mime_type ?? '', 'pdf')) return 'file-text';
        if (str_contains($this->mime_type ?? '', 'image')) return 'image';
        if (str_contains($this->mime_type ?? '', 'word') || str_contains($this->mime_type ?? '', 'document')) return 'file';
        return 'paperclip';
    }
}
