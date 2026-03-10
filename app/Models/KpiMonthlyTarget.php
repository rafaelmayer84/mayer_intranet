<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiMonthlyTarget extends Model
    {
        protected $fillable = ['modulo', 'kpi_key', 'descricao', 'ano', 'mes', 'meta_valor', 'unidade', 'tipo_meta', 'created_by'];
    }
