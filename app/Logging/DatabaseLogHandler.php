<?php

namespace App\Logging;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Monolog\Level;

class DatabaseLogHandler extends AbstractProcessingHandler
{
    public function __construct()
    {
        parent::__construct(Level::Error, true);
    }

    protected function write(LogRecord $record): void
    {
        try {
            $context = $record->context;
            $exception = $context['exception'] ?? null;

            $file = null;
            $line = null;
            $trace = null;

            if ($exception instanceof \Throwable) {
                $file = $exception->getFile();
                $line = $exception->getLine();
                $trace = mb_substr($exception->getTraceAsString(), 0, 5000);
                unset($context['exception']);
            }

            $module = $this->detectModule($file, $context);

            $user = null;
            try { $user = Auth::user(); } catch (\Throwable $e) {}

            $url = null;
            try {
                $url = Request::fullUrl();
                if ($url === '') $url = null;
            } catch (\Throwable $e) {}

            DB::table('system_error_logs')->insert([
                'level'        => strtolower($record->level->name),
                'message'      => mb_substr($record->message, 0, 5000),
                'module'       => $module,
                'context_json' => !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) : null,
                'trace'        => $trace,
                'file'         => $file ? mb_substr($file, 0, 500) : null,
                'line'         => $line,
                'url'          => $url ? mb_substr($url, 0, 500) : null,
                'user_id'      => $user?->id,
                'user_name'    => $user?->name,
                'ip_address'   => Request::ip() ?? null,
                'created_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            // Handler NUNCA pode causar loop infinito
        }
    }

    private function detectModule(?string $file, array $context): ?string
    {
        if (!$file) return null;

        $map = [
            'Evidentia'  => 'evidentia',
            'Justus'     => 'justus',
            'Nexo'       => 'nexo',
            'Crm'        => 'crm',
            'Gdp'        => 'gdp',
            'Vigilia'    => 'vigilia',
            'Sisrh'      => 'sisrh',
            'DataJuri'   => 'datajuri',
            'SendPulse'  => 'nexo',
            'Financeiro' => 'financeiro',
            'Reports'    => 'relatorios',
            'BscInsight' => 'bsc',
        ];

        foreach ($map as $keyword => $module) {
            if (stripos($file, $keyword) !== false) {
                return $module;
            }
        }

        return 'sistema';
    }
}
