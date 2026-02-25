<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class CrmOwnerProfile extends Model
{
    protected $fillable = ['user_id', 'max_accounts', 'priority_weight', 'specialties', 'description', 'active'];
    protected $casts = ['specialties' => 'array', 'active' => 'boolean'];

    public function user() { return $this->belongsTo(\App\Models\User::class); }

    public function scopeActive($q) { return $q->where('active', true); }

    public function currentCount(): int
    {
        return CrmAccount::where('owner_user_id', $this->user_id)->where('lifecycle', 'ativo')->count();
    }
}
