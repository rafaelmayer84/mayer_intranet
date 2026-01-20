<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

class DiagController extends Controller
{
    /**
     * Página simples com links úteis de diagnóstico.
     * Restrita a usuários autenticados (middleware na rota).
     */
    public function index()
    {
        $user = Auth::user();

        $body = ''
            . '<h1>Diagnóstico — Intranet</h1>'
            . '<p style="color:#555">Use estes links para descobrir a causa exata do Erro 500 sem depender de SSH.</p>'
            . '<div class="cards">'
            . $this->card('Status geral', '/_diag/status', 'Ambiente, versões, storage/logs, etc.')
            . $this->card('Banco de dados', '/_diag/db', 'Testa conexão e verifica se as tabelas do Quadro de Avisos existem.')
            . $this->card('Rotas relevantes', '/_diag/routes', 'Lista as rotas de login/avisos/diag carregadas no runtime.')
            . $this->card('Tail do laravel.log', '/_diag/log', 'Mostra as últimas linhas do log do Laravel (com máscara de dados).')
            . '</div>'
            . '<hr>'
            . '<p><strong>Usuário autenticado:</strong> ' . e(($user->name ?? '—') . ' (#' . ($user->id ?? '—') . ')') . '</p>'
            . '<p><strong>Agora:</strong> ' . e(now()->format('d/m/Y H:i:s')) . '</p>';

        return response($this->wrapHtml('Diagnóstico', $body));
    }

    public function status()
    {
        $logFile = $this->latestLaravelLogFile();
        $logExists = $logFile ? File::exists($logFile) : false;

        $rows = [
            ['APP_ENV', config('app.env')],
            ['APP_DEBUG', config('app.debug') ? 'true' : 'false'],
            ['Laravel', app()->version()],
            ['PHP', PHP_VERSION],
            ['URL', config('app.url')],
            ['Timezone', config('app.timezone')],
            ['Storage writable', is_writable(storage_path()) ? 'yes' : 'no'],
            ['Log file', $logFile ? basename($logFile) : '—'],
            ['Log exists', $logExists ? 'yes' : 'no'],
            ['Log size', ($logExists && $logFile) ? number_format(File::size($logFile) / 1024, 1) . ' KB' : '—'],
        ];

        $body = '<h1>Status</h1>' . $this->table($rows)
            . '<p><a href="/_diag">← voltar</a></p>';

        return response($this->wrapHtml('Status', $body));
    }

    public function db()
    {
        $expectedTables = ['categorias_avisos', 'avisos', 'avisos_lidos'];

        $body = '<h1>Banco de Dados</h1>';

        try {
            DB::connection()->getPdo();

            $missing = [];
            $counts = [];

            foreach ($expectedTables as $t) {
                $exists = $this->tableExists($t);
                if (!$exists) {
                    $missing[] = $t;
                } else {
                    try {
                        $counts[$t] = (int) DB::table($t)->count();
                    } catch (Throwable $e) {
                        $counts[$t] = 'erro ao contar: ' . $e->getMessage();
                    }
                }
            }

            $rows = [
                ['Conexão', config('database.default')],
                ['Database', $this->maskDbName(config('database.connections.' . config('database.default') . '.database'))],
                ['Host', $this->maskHost(config('database.connections.' . config('database.default') . '.host'))],
            ];

            $body .= $this->table($rows);

            if (!empty($missing)) {
                $body .= '<div class="warn"><strong>Faltando tabela(s):</strong> ' . e(implode(', ', $missing)) . '<br>'
                    . 'A correção mais comum é executar: <code>php artisan migrate</code>'
                    . '</div>';
            } else {
                $body .= '<div class="ok"><strong>Tabelas OK.</strong></div>';
            }

            $body .= '<h2>Contagem de registros</h2>';
            $countRows = [];
            foreach ($expectedTables as $t) {
                $countRows[] = [$t, (string)($counts[$t] ?? '—')];
            }
            $body .= $this->table($countRows);

        } catch (Throwable $e) {
            $body .= '<div class="err"><strong>Falha ao conectar no banco:</strong><br>'
                . e($e->getMessage())
                . '</div>';
        }

        $body .= '<p><a href="/_diag">← voltar</a></p>';

        return response($this->wrapHtml('DB', $body));
    }

    public function routes()
    {
        $routes = [];

        foreach (Route::getRoutes() as $r) {
            $uri = $r->uri();
            $name = $r->getName() ?? '';

            if (
                str_contains($uri, 'avisos') ||
                str_contains($uri, '_diag') ||
                $uri === 'login' ||
                $uri === 'logout'
            ) {
                $routes[] = [
                    implode('|', $r->methods()),
                    '/' . ltrim($uri, '/'),
                    $name,
                    $r->getActionName(),
                ];
            }
        }

        $body = '<h1>Rotas relevantes</h1>';
        $body .= $this->table(array_merge([
            ['Métodos', 'URI', 'Name', 'Action'],
        ], $routes));

        $body .= '<p><a href="/_diag">← voltar</a></p>';

        return response($this->wrapHtml('Rotas', $body));
    }

    public function log(Request $request)
    {
        $logFile = $this->latestLaravelLogFile();

        $body = '<h1>Últimas linhas do log</h1>';

        if (!$logFile || !File::exists($logFile)) {
            $body .= '<div class="warn">Arquivo de log não encontrado em <code>storage/logs</code>.</div>';
            $body .= '<p><a href="/_diag">← voltar</a></p>';
            return response($this->wrapHtml('Log', $body));
        }

        $lines = (int) $request->query('lines', 250);
        if ($lines < 50) $lines = 50;
        if ($lines > 1000) $lines = 1000;

        $text = $this->tail($logFile, $lines);
        $text = $this->maskSensitive($text);

        $body .= '<p>Arquivo: <code>' . e(basename($logFile)) . '</code> · linhas: ' . e((string) $lines) . '</p>';
        $body .= '<pre style="white-space:pre-wrap; font-size:12px; padding:12px; background:#111; color:#eee; border-radius:8px; overflow:auto;">'
            . e($text)
            . '</pre>';
        $body .= '<p><a href="/_diag">← voltar</a></p>';

        return response($this->wrapHtml('Log', $body));
    }

    private function wrapHtml(string $title, string $body): string
    {
        return '<!doctype html><html lang="pt-br"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . e($title) . '</title>'
            . '<style>'
            . 'body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;max-width:1100px;margin:24px auto;padding:0 16px;line-height:1.4;}'
            . 'h1{margin:0 0 8px 0;font-size:22px;} h2{margin:18px 0 8px 0;font-size:16px;}'
            . 'code{background:#f3f4f6;padding:2px 6px;border-radius:6px;}'
            . 'table{border-collapse:collapse;width:100%;margin:10px 0;} td,th{border:1px solid #e5e7eb;padding:8px;font-size:13px;vertical-align:top;}'
            . '.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin:14px 0;}'
            . '.card{border:1px solid #e5e7eb;border-radius:10px;padding:12px;}'
            . '.card a{font-weight:700;text-decoration:none;}'
            . '.warn{border:1px solid #f59e0b;background:#fffbeb;padding:10px;border-radius:10px;margin:10px 0;}'
            . '.err{border:1px solid #ef4444;background:#fef2f2;padding:10px;border-radius:10px;margin:10px 0;}'
            . '.ok{border:1px solid #10b981;background:#ecfdf5;padding:10px;border-radius:10px;margin:10px 0;}'
            . '</style></head><body>'
            . $body
            . '</body></html>';
    }

    private function card(string $title, string $href, string $desc): string
    {
        return '<div class="card">'
            . '<div><a href="' . e($href) . '">' . e($title) . '</a></div>'
            . '<div style="color:#555;font-size:13px;margin-top:4px">' . e($desc) . '</div>'
            . '</div>';
    }

    private function table(array $rows): string
    {
        $html = '<table>';
        foreach ($rows as $i => $r) {
            $html .= '<tr>';
            foreach ($r as $cell) {
                if ($i === 0 && count($rows) > 1 && $rows[0] === ['Métodos', 'URI', 'Name', 'Action']) {
                    $html .= '<th>' . e((string) $cell) . '</th>';
                } else {
                    $html .= '<td>' . e((string) $cell) . '</td>';
                }
            }
            $html .= '</tr>';
        }
        $html .= '</table>';
        return $html;
    }

    private function latestLaravelLogFile(): ?string
    {
        $paths = glob(storage_path('logs/*.log')) ?: [];
        if (empty($paths)) return null;

        usort($paths, fn($a, $b) => filemtime($b) <=> filemtime($a));
        return $paths[0] ?? null;
    }

    private function tail(string $file, int $lines = 200): string
    {
        // Leitura simples (logs não são enormes na intranet). Se crescer, troca por tail eficiente.
        $content = @file($file, FILE_IGNORE_NEW_LINES);
        if ($content === false) return '';
        $slice = array_slice($content, -$lines);
        return implode("\n", $slice);
    }

    private function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function maskDbName($db): string
    {
        $db = (string) ($db ?? '');
        if ($db === '') return '—';
        if (strlen($db) <= 4) return '***';
        return substr($db, 0, 2) . str_repeat('*', max(0, strlen($db) - 4)) . substr($db, -2);
    }

    private function maskHost($host): string
    {
        $host = (string) ($host ?? '');
        if ($host === '') return '—';
        // Não mask se for localhost
        if (in_array($host, ['127.0.0.1', 'localhost'], true)) return $host;
        // Mascara domínio/IP
        return substr($host, 0, 3) . '***' . substr($host, -3);
    }

    private function maskSensitive(string $text): string
    {
        // Máscaras simples para evitar vazar credenciais.
        $patterns = [
            '/(password\s*[:=]\s*)([^\s,;]+)/i',
            '/(senha\s*[:=]\s*)([^\s,;]+)/i',
            '/(token\s*[:=]\s*)([^\s,;]+)/i',
            '/(secret\s*[:=]\s*)([^\s,;]+)/i',
        ];

        foreach ($patterns as $p) {
            $text = preg_replace($p, '$1***', $text) ?? $text;
        }

        // Mascara e-mails
        $text = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '***@***', $text) ?? $text;

        return $text;
    }
}
