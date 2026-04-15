<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class CrmAdminProcessChecklist extends Model
{
    protected $table = 'crm_admin_process_checklist';

    protected $fillable = [
        'admin_process_id','nome','descricao','status',
        'document_id','requested_at','received_at','dispensed_reason',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'received_at'  => 'datetime',
    ];

    public function process()
    {
        return $this->belongsTo(CrmAdminProcess::class, 'admin_process_id');
    }

    public function document()
    {
        return $this->belongsTo(CrmAdminProcessDocument::class, 'document_id');
    }
}
