<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SisrhFrequenciaController extends Controller
{
    private function emailToUserMap(): array
    {
        return DB::table('users')
            ->whereNotIn('id', [2, 5, 6, 9])
            ->pluck('id', 'email')
            ->toArray();
    }

    public function index(Request $request)
    {
        $this->checkAccess();

        $dataInicio = $request->filled('data_inicio')
            ? Carbon::parse($request->data_inicio)
            : Carbon::now()->startOfWeek(Carbon::MONDAY);

        $dataFim = $request->filled('data_fim')
            ? Carbon::parse($request->data_fim)
            : Carbon::now()->endOfWeek(Carbon::FRIDAY);

        $usuarios = DB::table('users')
            ->whereNotIn('id', [2, 5, 6, 9])
            ->whereIn('role', ['advogado', 'coordenador', 'socio', 'admin'])
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $query = DB::table('sisrh_frequencia_logins as f')
            ->leftJoin('users as u', 'f.user_id', '=', 'u.id')
            ->whereBetween('f.data_login', [$dataInicio->toDateString(), $dataFim->toDateString()])
            ->select('f.*', 'u.name as user_name');

        if ($request->filled('user_id')) {
            $query->where('f.user_id', $request->user_id);
        }

        $logins = $query->orderByDesc('f.data_login')
            ->orderByDesc('f.hora_login')
            ->paginate(50);

        $resumoQuery = DB::table('sisrh_frequencia_logins as f')
            ->leftJoin('users as u', 'f.user_id', '=', 'u.id')
            ->whereBetween('f.data_login', [$dataInicio->toDateString(), $dataFim->toDateString()])
            ->whereNotNull('f.user_id');

        if ($request->filled('user_id')) {
            $resumoQuery->where('f.user_id', $request->user_id);
        }

        $rawData = $resumoQuery->select(
            'f.user_id', 'u.name', 'f.data_login', 'f.hora_login',
            'f.status_login', 'f.ultimo_acesso'
        )->get();

        $resumo = [];
        foreach ($rawData->groupBy('user_id') as $userId => $registros) {
            $nome = $registros->first()->name ?? 'N/D';
            $exitoRegs = $registros->where('status_login', 'Êxito');
            $diasUnicos = $exitoRegs->pluck('data_login')->unique()->count();

            $horasEntrada = [];
            $horasSaida = [];
            foreach ($exitoRegs->groupBy('data_login') as $dia => $logsDia) {
                $sorted = $logsDia->sortBy('hora_login');
                $horasEntrada[] = $sorted->first()->hora_login;
                $ultimoAcesso = $sorted->max('ultimo_acesso');
                if ($ultimoAcesso) {
                    $horasSaida[] = Carbon::parse($ultimoAcesso)->format('H:i:s');
                }
            }

            $resumo[] = [
                'user_id' => $userId,
                'nome' => $nome,
                'dias_com_login' => $diasUnicos,
                'hora_media_entrada' => $this->mediaHoras($horasEntrada),
                'hora_media_saida' => $this->mediaHoras($horasSaida),
                'logins_exito' => $exitoRegs->count(),
                'logins_falha' => $registros->filter(fn($r) => $r->status_login !== 'Êxito' && $r->status_login !== 'Sessão')->count(),
            ];
        }

        usort($resumo, fn($a, $b) => strcmp($a['nome'], $b['nome']));

        return view('sisrh.frequencia', compact('logins', 'resumo', 'dataInicio', 'dataFim', 'usuarios'));
    }

    public function importar(Request $request)
    {
        $this->checkAdmin();

        $request->validate(['dados_brutos' => 'required|string|min:10']);

        $linhas = preg_split('/\r?\n/', trim($request->dados_brutos));
        $emailMap = $this->emailToUserMap();

        $inseridos = 0;
        $duplicados = 0;
        $erros = 0;

        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if (empty($linha)) continue;

            // Skip header
            if (str_contains(strtolower($linha), 'usuário') || str_contains(strtolower($linha), 'data de cadastro')) continue;

            $cols = str_contains($linha, "\t")
                ? explode("\t", $linha)
                : preg_split('/\s{2,}/', $linha);

            if (count($cols) < 2) { $erros++; continue; }

            $cols = array_map('trim', $cols);

            // Detectar email
            $email = null;
            $emailIdx = null;
            foreach ($cols as $i => $col) {
                if (str_contains($col, '@')) {
                    $email = strtolower($col);
                    $emailIdx = $i;
                    break;
                }
            }
            if (!$email) { $erros++; continue; }

            // Remover coluna de email
            $restante = [];
            foreach ($cols as $i => $col) {
                if ($i !== $emailIdx) $restante[] = $col;
            }

            // Parsear data/hora
            $dataLogin = null;
            $horaLogin = null;
            $ip = null;
            $url = null;
            $status = null;
            $minutos = null;
            $ultimoAcesso = null;
            $plataforma = null;
            $navegador = null;

            foreach ($restante as $val) {
                $val = trim($val);
                if (empty($val) || $val === '—') continue;

                // Data+hora juntas: "DD/MM/YYYY HH:MM"
                if (!$dataLogin && preg_match('#^(\d{2}/\d{2}/\d{4})\s+(\d{2}:\d{2})#', $val, $m)) {
                    try {
                        $dataLogin = Carbon::createFromFormat('d/m/Y', $m[1])->toDateString();
                        $horaLogin = $m[2] . ':00';
                    } catch (\Exception $e) { /* skip */ }
                    continue;
                }

                // IP
                if (!$ip && preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $val)) {
                    $ip = $val;
                    continue;
                }

                // URL
                if (!$url && str_starts_with($val, 'http')) {
                    $url = $val;
                    continue;
                }

                // Status
                if (!$status && in_array($val, ['Êxito', 'Senha inválida', 'Sessão', 'Bloqueado', 'Expirado'])) {
                    $status = $val;
                    continue;
                }

                // Último acesso (DD/MM/YYYY HH:MM) — segundo campo de data
                if ($dataLogin && !$ultimoAcesso && preg_match('#^(\d{2}/\d{2}/\d{4})\s+(\d{2}:\d{2})#', $val, $m2)) {
                    try {
                        $ultimoAcesso = Carbon::createFromFormat('d/m/Y H:i', $m2[1] . ' ' . $m2[2]);
                    } catch (\Exception $e) { /* skip */ }
                    continue;
                }

                // Minutos
                if (!$minutos && preg_match('/^\d+$/', $val) && (int)$val >= 1 && (int)$val <= 99999) {
                    $minutos = (int)$val;
                    continue;
                }

                // Plataforma
                if (!$plataforma && in_array($val, ['Windows', 'Android', 'iOS', 'macOS', 'Linux', 'Mac'])) {
                    $plataforma = $val;
                    continue;
                }

                // Navegador
                if (!$navegador && preg_match('/^(Chrome|Firefox|Safari|Edge|Opera)\s*-\s*[\d.]+/', $val)) {
                    $navegador = $val;
                    continue;
                }
            }

            if (!$dataLogin || !$horaLogin) { $erros++; continue; }

            if (!$status) $status = 'Sessão';

            $semanaRef = Carbon::parse($dataLogin)->startOfWeek(Carbon::MONDAY)->toDateString();

            // Duplicata check
            $existe = DB::table('sisrh_frequencia_logins')
                ->where('email_datajuri', $email)
                ->where('data_login', $dataLogin)
                ->where('hora_login', $horaLogin)
                ->exists();

            if ($existe) { $duplicados++; continue; }

            $userId = $emailMap[$email] ?? null;

            DB::table('sisrh_frequencia_logins')->insert([
                'email_datajuri' => $email,
                'user_id' => $userId,
                'data_login' => $dataLogin,
                'hora_login' => $horaLogin,
                'ip_origem' => $ip,
                'url_origem' => $url,
                'status_login' => $status,
                'minutos_expirar' => $minutos,
                'ultimo_acesso' => $ultimoAcesso,
                'plataforma' => $plataforma,
                'navegador' => $navegador,
                'importado_por' => Auth::id(),
                'semana_referencia' => $semanaRef,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $inseridos++;
        }

        $this->auditLog('sisrh_frequencia_importar', "Importacao: {$inseridos} inseridos, {$duplicados} duplicados, {$erros} erros");

        return back()
            ->with('success', 'Importacao processada com sucesso.')
            ->with('import_stats', compact('inseridos', 'duplicados', 'erros'));
    }

    private function mediaHoras(array $horas): string
    {
        if (empty($horas)) return '—';

        $totalMin = 0;
        foreach ($horas as $h) {
            $parts = explode(':', $h);
            $totalMin += ((int)($parts[0] ?? 0)) * 60 + (int)($parts[1] ?? 0);
        }

        $avg = (int) round($totalMin / count($horas));
        return str_pad((int)floor($avg / 60), 2, '0', STR_PAD_LEFT) . ':' . str_pad($avg % 60, 2, '0', STR_PAD_LEFT);
    }

    private function checkAccess(): void
    {
        if (!in_array(Auth::user()->role, ['admin', 'coordenador', 'socio'])) {
            abort(403);
        }
    }

    private function checkAdmin(): void
    {
        if (Auth::user()->role !== 'admin') abort(403);
    }

    private function auditLog(string $acao, string $descricao): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => Auth::id(),
            'action' => $acao,
            'description' => $descricao,
            'module' => 'sisrh',
            'user_name' => Auth::user()->name,
            'user_role' => Auth::user()->role,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'route' => request()->path(),
            'method' => request()->method(),
            'created_at' => now(),
        ]);
    }
}
