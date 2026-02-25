<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class CrmDistributionProposal extends Model
{
    protected $fillable = ['status', 'assignments', 'summary', 'ai_reasoning', 'created_by', 'approved_by', 'applied_at'];
    protected $casts = ['assignments' => 'array', 'summary' => 'array', 'applied_at' => 'datetime'];

    public function creator() { return $this->belongsTo(\App\Models\User::class, 'created_by'); }
}
