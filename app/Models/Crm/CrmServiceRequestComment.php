<?php
namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class CrmServiceRequestComment extends Model
{
    protected $table = 'crm_service_request_comments';

    protected $fillable = ['service_request_id', 'user_id', 'body', 'is_internal'];

    protected $casts = ['is_internal' => 'boolean'];

    public function serviceRequest()
    {
        return $this->belongsTo(CrmServiceRequest::class, 'service_request_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
