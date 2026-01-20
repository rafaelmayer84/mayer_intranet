<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiMonthlyTarget extends Model
    {
        protected $fillable = ['year', 'month', 'kpi_key', 'target_value'];
    }
