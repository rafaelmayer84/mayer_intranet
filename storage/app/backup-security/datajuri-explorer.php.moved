<?php
/**
 * ============================================================
 * DATAJURI API EXPLORER v1.0
 * Mayer Advogados - Ferramenta permanente de mapeamento de API
 * ============================================================
 * 
 * Arquivo standalone - NÃO depende do Laravel.
 * Acesso: https://intranet.mayeradvogados.adv.br/datajuri-explorer.php
 * Segurança: requer login com token definido abaixo.
 * 
 * Funcionalidades:
 * - Lista todos os módulos disponíveis na API
 * - Seleciona quais módulos baixar
 * - Exibe amostras com TODOS os campos
 * - Navegação por registros (paginação)
 * - Busca dentro dos campos
 * - Exporta JSON completo
 * - Detalhe expandido de objetos aninhados
 * - Histórico de extrações salvas
 */

// ============================================================
// CONFIGURAÇÃO DE SEGURANÇA
// ============================================================
session_start();
define('EXPLORER_TOKEN', 'MayerDJ2026!Explorer'); // Altere este token
define('STORAGE_DIR', __DIR__ . '/storage/datajuri-explorer');

if (!is_dir(STORAGE_DIR)) {
    mkdir(STORAGE_DIR, 0755, true);
}

// ============================================================
// CARREGAR .env
// ============================================================
function loadEnv(): array {
    $envPath = dirname(__DIR__) . '/.env';
    if (!file_exists($envPath)) return [];
    $env = [];
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $env[trim($parts[0])] = trim($parts[1], '"\'');
        }
    }
    return $env;
}

$env = loadEnv();

// ============================================================
// AUTENTICAÇÃO DO EXPLORER
// ============================================================
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'logout') {
    session_destroy();
    header('Location: ' . basename(__FILE__));
    exit;
}

if ($action === 'login' && ($_POST['token'] ?? '') === EXPLORER_TOKEN) {
    $_SESSION['dj_explorer_auth'] = true;
    header('Location: ' . basename(__FILE__));
    exit;
}

if (empty($_SESSION['dj_explorer_auth'])) {
    showLoginPage();
    exit;
}

// ============================================================
// API DATAJURI - FUNÇÕES
// ============================================================
function djAuthenticate(array $env): ?string {
    $clientId = $env['DATAJURI_CLIENT_ID'] ?? '';
    $secretId = $env['DATAJURI_SECRET_ID'] ?? '';
    $username = $env['DATAJURI_USERNAME'] ?? '';
    $password = $env['DATAJURI_PASSWORD'] ?? '';
    $baseUrl  = $env['DATAJURI_BASE_URL'] ?? 'https://api.datajuri.com.br';

    if (!$clientId || !$secretId || !$username || !$password) return null;

    $ch = curl_init($baseUrl . '/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Basic ' . base64_encode($clientId . ':' . $secretId),
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'password',
            'username'   => $username,
            'password'   => $password,
        ]),
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) return null;
    $data = json_decode($resp, true);
    return $data['access_token'] ?? null;
}

function djListModules(string $baseUrl, string $token): array {
    $ch = curl_init($baseUrl . '/v1/modulos');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $resp = fixEncoding($resp);
    $data = json_decode($resp, true);

    if (!$data) return [];
    return $data['rows'] ?? $data ?? [];
}


function getCamposForModule(string \$mod): string {
    \$map = [
        'Processo' => 'id,pasta,numero,status,tipoAcao,tipoProcesso,natureza,assunto,valorCausa,valorProvisionado,valorSentenca,possibilidade,ganhoCausa,tipoEncerramento,proprietario.nome,proprietario.id,cliente.nome,clienteId,cliente.numeroDocumento,adverso.nome,adversoId,posicaoCliente,posicaoAdverso,advogadoCliente.nome,advogadoClienteId,faseAtual.numero,faseAtual.vara,faseAtual.instancia,faseAtual.orgao.nome,dataAbertura,dataDistribuicao,dataEncerrado,observacao,dataCadastro',
        'Movimento' => 'id,data,valor,valorComSinal,tipo,descricao,observacao,planoConta.nomeCompleto,planoConta.codigo,planoConta.id,pessoa.nome,pessoa.id,contrato.id,contrato.numero,processo.pasta,processo.id,proprietario.nome,proprietario.id,formaPagamento,conciliado,relativoa,contaId,dataCadastro',
        'ContasReceber' => 'id,descricao,valor,dataVencimento,dataPagamento,prazo,tipo,pessoa.nome,pessoaId,cliente.nome,clienteId,processo.pasta,processoId,contrato.numero,contratoId,observacao,dataCadastro',
        'Contrato' => 'id,numero,valor,tipo,status,dataAssinatura,dataVencimento,contratante.nome,contratante.id,contratante.numeroDocumento,proprietario.nome,proprietario.id,processo.pasta,processo.id,observacao,dataCadastro',
        'HoraTrabalhada' => 'id,data,duracao,totalHoraTrabalhada,horaInicial,horaFinal,valorHora,valorTotal,assunto,tipo,status,processo.pasta,processo.id,proprietario.id,proprietario.nome,particular,dataFaturado,dataCadastro',
        'Atividade' => 'id,status,dataHora,dataConclusao,dataPrazoFatal,descricao,processo.pasta,processo.id,proprietario.id,proprietario.nome,particular,dataCadastro',
        'Pessoa' => 'id,nome,email,outroEmail,telefone,celular,numeroDocumento,cpf,cnpj,tipoPessoa,statusPessoa,cliente,enderecoprua,enderecopnumero,enderecopbairro,enderecopcidade,enderecopestado,dataNascimento,profissao,proprietario.nome,proprietarioId,codigoPessoa,valorHora,dataCadastro',
        'Fase' => 'id,processo.pasta,processo.id,tipoFase,localidade,instancia,data,faseAtual,diasFaseAtiva,dataUltimoAndamento,proprietario.nome,proprietario.id',
        'AndamentoFase' => 'id,faseProcesso.id,processo.id,processo.pasta,dataAndamento,descricao,tipo,parecer,proprietario.id,proprietario.nome',
    ];
    return \$map[\$mod] ?? '';
}

require_once __DIR__ . '/datajuri-campos.php';

function djFetchEntity(string $baseUrl, string $token, string $module, int $page = 1, int $qty = 5, array $extraParams = []): array {
    $params = array_merge([
        'pagina'    => $page,
        'max' => $qty,
    ], $extraParams);

    $url = $baseUrl . '/v1/entidades/' . $module . '?' . http_build_query($params);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $resp = fixEncoding($resp);
    $data = json_decode($resp, true);

    if (!$data) {
        return [
            'error' => "HTTP $code - JSON decode falhou" . ($curlError ? " ($curlError)" : ''),
            'raw' => substr($resp, 0, 500),
        ];
    }

    return [
        'http_code' => $code,
        'url'       => $url,
        'listSize'  => $data['listSize'] ?? 0,
        'error'     => $data['error'] ?? null,
        'rows'      => $data['rows'] ?? [],
    ];
}

function fixEncoding(string $str): string {
    $det = mb_detect_encoding($str, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($det && $det !== 'UTF-8') {
        return mb_convert_encoding($str, 'UTF-8', $det);
    }
    return $str;
}

function extractAllKeys(array $item, string $prefix = ''): array {
    $keys = [];
    foreach ($item as $k => $v) {
        $fullKey = $prefix ? "$prefix.$k" : (string)$k;
        $keys[] = $fullKey;
        if (is_array($v) && !empty($v) && !isset($v[0])) {
            $keys = array_merge($keys, extractAllKeys($v, $fullKey));
        }
    }
    return $keys;
}

function getNestedValue(array $data, string $dotKey) {
    $keys = explode('.', $dotKey);
    $current = $data;
    foreach ($keys as $k) {
        if (!is_array($current) || !array_key_exists($k, $current)) return null;
        $current = $current[$k];
    }
    return $current;
}

function formatValue($val): string {
    if (is_null($val)) return '<span class="text-gray-400 italic">null</span>';
    if (is_bool($val)) return $val ? '<span class="text-green-600 font-bold">true</span>' : '<span class="text-red-600 font-bold">false</span>';
    if (is_array($val)) return '<span class="text-purple-600">' . htmlspecialchars(json_encode($val, JSON_UNESCAPED_UNICODE), ENT_QUOTES) . '</span>';
    $s = (string)$val;
    if (strlen($s) > 120) return htmlspecialchars(substr($s, 0, 120)) . '<span class="text-gray-400">... (' . strlen($s) . ' chars)</span>';
    return htmlspecialchars($s);
}

// ============================================================
// PROCESSAR AÇÕES AJAX/POST
// ============================================================
$baseUrl = $env['DATAJURI_BASE_URL'] ?? 'https://api.datajuri.com.br';

if ($action === 'api_fetch') {
    header('Content-Type: application/json; charset=utf-8');

    $token = djAuthenticate($env);
    if (!$token) {
        echo json_encode(['error' => 'Falha na autenticacao OAuth2 DataJuri']);
        exit;
    }

    $module = $_POST['module'] ?? '';
    $page   = (int)($_POST['page'] ?? 1);
    $qty    = (int)($_POST['qty'] ?? 5);

    if (!$module) {
        echo json_encode(['error' => 'Modulo nao informado']);
        exit;
    }

    $result = djFetchEntity($baseUrl, $token, $module, $page, $qty);

    // Extrair campos unicos de todas as rows
    $allFields = [];
    foreach ($result['rows'] ?? [] as $row) {
        $allFields = array_merge($allFields, extractAllKeys($row));
    }
    $allFields = array_values(array_unique($allFields));
    sort($allFields);
    $result['all_fields'] = $allFields;

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'api_modules') {
    header('Content-Type: application/json; charset=utf-8');

    $token = djAuthenticate($env);
    if (!$token) {
        echo json_encode(['error' => 'Falha na autenticacao OAuth2']);
        exit;
    }

    $modules = djListModules($baseUrl, $token);
    echo json_encode(['modules' => $modules], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'save_snapshot') {
    header('Content-Type: application/json; charset=utf-8');

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!$payload) {
        echo json_encode(['error' => 'Payload vazio']);
        exit;
    }

    $filename = 'snapshot_' . date('Ymd_His') . '.json';
    $filepath = STORAGE_DIR . '/' . $filename;
    file_put_contents($filepath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode(['ok' => true, 'file' => $filename]);
    exit;
}

if ($action === 'list_snapshots') {
    header('Content-Type: application/json; charset=utf-8');

    $files = glob(STORAGE_DIR . '/snapshot_*.json');
    $list = [];
    foreach ($files as $f) {
        $list[] = [
            'name' => basename($f),
            'size' => filesize($f),
            'date' => date('Y-m-d H:i:s', filemtime($f)),
        ];
    }
    usort($list, fn($a, $b) => strcmp($b['date'], $a['date']));

    echo json_encode(['snapshots' => $list]);
    exit;
}

if ($action === 'load_snapshot') {
    header('Content-Type: application/json; charset=utf-8');

    $name = basename($_GET['name'] ?? '');
    $filepath = STORAGE_DIR . '/' . $name;
    if (!file_exists($filepath)) {
        echo json_encode(['error' => 'Arquivo nao encontrado']);
        exit;
    }
    echo file_get_contents($filepath);
    exit;
}

if ($action === 'delete_snapshot') {
    header('Content-Type: application/json; charset=utf-8');

    $name = basename($_POST['name'] ?? '');
    $filepath = STORAGE_DIR . '/' . $name;
    if (file_exists($filepath)) unlink($filepath);
    echo json_encode(['ok' => true]);
    exit;
}

// ============================================================
// PÁGINA DE LOGIN
// ============================================================
function showLoginPage() {
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DataJuri Explorer - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center">
    <div class="bg-slate-800 rounded-2xl shadow-2xl p-8 w-full max-w-sm">
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-blue-600 rounded-xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-white">DataJuri API Explorer</h1>
            <p class="text-slate-400 text-sm mt-1">Mayer Advogados</p>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="login">
            <input type="password" name="token" placeholder="Token de acesso"
                   class="w-full px-4 py-3 bg-slate-700 border border-slate-600 rounded-lg text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 mb-4">
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition">
                Entrar
            </button>
        </form>
    </div>
</body>
</html>
<?php
}

// ============================================================
// PÁGINA PRINCIPAL
// ============================================================
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DataJuri API Explorer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .fade-in { animation: fadeIn 0.3s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .field-row:hover { background-color: rgba(59, 130, 246, 0.08); }
        .json-key { color: #93c5fd; }
        .json-str { color: #86efac; }
        .json-num { color: #fcd34d; }
        .json-null { color: #94a3b8; font-style: italic; }
        .json-bool { color: #f472b6; font-weight: bold; }
        pre.json-view { font-size: 13px; line-height: 1.5; max-height: 600px; overflow: auto; }
        .tab-active { border-bottom: 3px solid #3b82f6; color: #3b82f6; font-weight: 600; }
        .tab-inactive { border-bottom: 3px solid transparent; color: #94a3b8; }
        .tab-inactive:hover { color: #cbd5e1; }
        .spinner { display: inline-block; width: 20px; height: 20px; border: 3px solid rgba(255,255,255,.3); border-radius: 50%; border-top-color: #3b82f6; animation: spin 0.7s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .toast { animation: slideIn 0.3s ease, fadeOut 0.3s ease 2.7s; }
        @keyframes slideIn { from { transform: translateX(100%); } to { transform: translateX(0); } }
        @keyframes fadeOut { to { opacity: 0; } }
    </style>
</head>
<body class="bg-slate-900 text-slate-200 min-h-screen">

<!-- HEADER -->
<header class="bg-slate-800 border-b border-slate-700 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>
                </svg>
            </div>
            <div>
                <h1 class="text-lg font-bold text-white">DataJuri API Explorer</h1>
                <p class="text-xs text-slate-400">Mapeamento completo da API</p>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <span id="authStatus" class="text-sm text-slate-400"></span>
            <a href="?action=logout" class="text-sm text-slate-400 hover:text-red-400 transition">Sair</a>
        </div>
    </div>
</header>

<!-- MAIN -->
<main class="max-w-7xl mx-auto px-4 py-6">

    <!-- STEP 1: MÓDULOS -->
    <div id="sectionModules" class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                <span class="w-7 h-7 bg-blue-600 rounded-full flex items-center justify-center text-xs font-bold">1</span>
                Selecione os Módulos
            </h2>
            <button onclick="loadModules()" class="text-sm bg-slate-700 hover:bg-slate-600 px-4 py-2 rounded-lg transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Carregar da API
            </button>
        </div>

        <div id="modulesLoading" class="hidden text-center py-8">
            <div class="spinner mb-3"></div>
            <p class="text-sm text-slate-400">Conectando ao DataJuri...</p>
        </div>

        <div id="modulesGrid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
            <!-- Módulos conhecidos (hardcode + API) -->
        </div>

        <div class="mt-4 flex items-center gap-3">
            <input type="number" id="inputQty" value="3" min="1" max="50"
                   class="w-24 bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
            <span class="text-sm text-slate-400">registros por módulo</span>
            <input type="number" id="inputPage" value="1" min="1"
                   class="w-24 bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:ring-2 focus:ring-blue-500 focus:outline-none">
            <span class="text-sm text-slate-400">página</span>
            <button onclick="fetchSelected()" class="ml-auto bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/></svg>
                Extrair Dados
            </button>
        </div>
    </div>

    <!-- STEP 2: RESULTADOS -->
    <div id="sectionResults" class="hidden fade-in">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                <span class="w-7 h-7 bg-green-600 rounded-full flex items-center justify-center text-xs font-bold">2</span>
                Resultados
            </h2>
            <div class="flex gap-2">
                <button onclick="saveSnapshot()" class="text-sm bg-slate-700 hover:bg-slate-600 px-4 py-2 rounded-lg transition">
                    💾 Salvar Snapshot
                </button>
                <button onclick="exportJSON()" class="text-sm bg-slate-700 hover:bg-slate-600 px-4 py-2 rounded-lg transition">
                    📋 Copiar JSON
                </button>
                <button onclick="downloadJSON()" class="text-sm bg-slate-700 hover:bg-slate-600 px-4 py-2 rounded-lg transition">
                    ⬇️ Download JSON
                </button>
            </div>
        </div>

        <!-- Tabs dos módulos -->
        <div id="resultTabs" class="flex gap-1 border-b border-slate-700 mb-4 overflow-x-auto"></div>

        <!-- Conteúdo do módulo ativo -->
        <div id="resultContent"></div>
    </div>

    <!-- STEP 3: SNAPSHOTS SALVOS -->
    <div class="mt-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                <span class="w-7 h-7 bg-slate-600 rounded-full flex items-center justify-center text-xs font-bold">📁</span>
                Snapshots Salvos
            </h2>
            <button onclick="loadSnapshots()" class="text-sm bg-slate-700 hover:bg-slate-600 px-3 py-1 rounded-lg transition">
                Atualizar
            </button>
        </div>
        <div id="snapshotsList" class="space-y-2"></div>
    </div>

</main>

<!-- TOAST -->
<div id="toastContainer" class="fixed top-4 right-4 z-50 space-y-2"></div>

<script>
// ============================================================
// STATE
// ============================================================
const KNOWN_MODULES = [
    { name: 'Processo', label: 'Processos', icon: '⚖️', priority: true },
    { name: 'Movimento', label: 'Movimentos Financeiros', icon: '💰', priority: true },
    { name: 'ContasReceber', label: 'Contas a Receber', icon: '📄', priority: true },
    { name: 'Contrato', label: 'Contratos', icon: '📝', priority: true },
    { name: 'HoraTrabalhada', label: 'Horas Trabalhadas', icon: '⏱️', priority: true },
    { name: 'Atividade', label: 'Atividades', icon: '📋', priority: true },
    { name: 'Pessoa', label: 'Pessoas/Clientes', icon: '👤', priority: false },
    { name: 'Tarefa', label: 'Tarefas', icon: '✅', priority: false },
    { name: 'Usuario', label: 'Usuários DataJuri', icon: '🔑', priority: false },
    { name: 'Custas', label: 'Custas', icon: '🏛️', priority: false },
    { name: 'Procuracao', label: 'Procurações', icon: '📜', priority: false },
    { name: 'OrdemServico', label: 'Ordens de Serviço', icon: '🔧', priority: false },
    { name: 'Boleto', label: 'Boletos', icon: '🏦', priority: false },
    { name: 'FasesProcesso', label: 'Fases do Processo', icon: '📊', priority: false },
    { name: 'AndamentoFase', label: 'Andamentos', icon: '📑', priority: false },
    { name: 'PlanoConta', label: 'Plano de Contas', icon: '📒', priority: false },
];

let selectedModules = new Set();
let fetchedData = {};
let activeTab = '';

// ============================================================
// INICIALIZAÇÃO
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
    renderModulesGrid(KNOWN_MODULES);
    // Pré-selecionar módulos prioritários
    KNOWN_MODULES.filter(m => m.priority).forEach(m => selectedModules.add(m.name));
    updateModuleCards();
    loadSnapshots();
});

// ============================================================
// MÓDULOS
// ============================================================
function renderModulesGrid(modules) {
    const grid = document.getElementById('modulesGrid');
    grid.innerHTML = modules.map(m => `
        <div id="mod-${m.name}" onclick="toggleModule('${m.name}')"
             class="cursor-pointer rounded-xl border-2 p-3 transition-all hover:shadow-lg
                    ${selectedModules.has(m.name) ? 'border-blue-500 bg-blue-500/10' : 'border-slate-700 bg-slate-800 hover:border-slate-500'}">
            <div class="flex items-center gap-2">
                <span class="text-lg">${m.icon}</span>
                <div>
                    <div class="text-sm font-semibold text-white">${m.label}</div>
                    <div class="text-xs text-slate-400 font-mono">${m.name}</div>
                </div>
            </div>
        </div>
    `).join('');
}

function toggleModule(name) {
    if (selectedModules.has(name)) {
        selectedModules.delete(name);
    } else {
        selectedModules.add(name);
    }
    updateModuleCards();
}

function updateModuleCards() {
    KNOWN_MODULES.forEach(m => {
        const el = document.getElementById('mod-' + m.name);
        if (!el) return;
        if (selectedModules.has(m.name)) {
            el.className = el.className.replace('border-slate-700 bg-slate-800 hover:border-slate-500', '').trim();
            if (!el.className.includes('border-blue-500')) {
                el.className += ' border-blue-500 bg-blue-500/10';
            }
        } else {
            el.className = el.className.replace('border-blue-500 bg-blue-500/10', '').trim();
            if (!el.className.includes('border-slate-700')) {
                el.className += ' border-slate-700 bg-slate-800 hover:border-slate-500';
            }
        }
    });
}

async function loadModules() {
    const loading = document.getElementById('modulesLoading');
    loading.classList.remove('hidden');

    try {
        const resp = await fetch('<?= basename(__FILE__) ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=api_modules'
        });
        const data = await resp.json();

        if (data.error) {
            toast(data.error, 'error');
            return;
        }

        if (data.modules && Array.isArray(data.modules)) {
            // Adicionar módulos descobertos que não estão na lista conhecida
            const knownNames = new Set(KNOWN_MODULES.map(m => m.name));
            data.modules.forEach(m => {
                const modName = m.nome || m.name || m;
                if (typeof modName === 'string' && !knownNames.has(modName)) {
                    KNOWN_MODULES.push({ name: modName, label: modName, icon: '🔹', priority: false });
                }
            });
            renderModulesGrid(KNOWN_MODULES);
            updateModuleCards();
            toast(`${KNOWN_MODULES.length} módulos disponíveis`, 'success');
        }
    } catch (e) {
        toast('Erro de conexão: ' + e.message, 'error');
    } finally {
        loading.classList.add('hidden');
    }
}

// ============================================================
// FETCH DADOS
// ============================================================
async function fetchSelected() {
    if (selectedModules.size === 0) {
        toast('Selecione ao menos um módulo', 'error');
        return;
    }

    const qty = parseInt(document.getElementById('inputQty').value) || 3;
    const page = parseInt(document.getElementById('inputPage').value) || 1;

    fetchedData = {};
    const section = document.getElementById('sectionResults');
    section.classList.remove('hidden');

    const modules = Array.from(selectedModules);
    let completed = 0;

    document.getElementById('resultContent').innerHTML = `
        <div class="text-center py-12">
            <div class="spinner mb-3"></div>
            <p class="text-sm text-slate-400">Extraindo <span id="fetchProgress">0</span>/${modules.length} módulos...</p>
        </div>
    `;

    for (const mod of modules) {
        try {
            const resp = await fetch('<?= basename(__FILE__) ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=api_fetch&module=${encodeURIComponent(mod)}&page=${page}&qty=${qty}`
            });
            const data = await resp.json();
            fetchedData[mod] = data;
        } catch (e) {
            fetchedData[mod] = { error: e.message, rows: [] };
        }
        completed++;
        const prog = document.getElementById('fetchProgress');
        if (prog) prog.textContent = completed;
    }

    renderResults();
    toast(`${modules.length} módulo(s) extraído(s)`, 'success');
}

// ============================================================
// RENDERIZAR RESULTADOS
// ============================================================
function renderResults() {
    const tabs = document.getElementById('resultTabs');
    const modules = Object.keys(fetchedData);

    // Tabs
    tabs.innerHTML = modules.map((mod, i) => {
        const d = fetchedData[mod];
        const count = d.listSize || d.rows?.length || 0;
        return `<button onclick="switchTab('${mod}')"
                    id="tab-${mod}"
                    class="px-4 py-2 text-sm whitespace-nowrap transition ${i === 0 ? 'tab-active' : 'tab-inactive'}">
                    ${mod} <span class="text-xs opacity-60">(${count})</span>
                </button>`;
    }).join('');

    activeTab = modules[0];
    renderTabContent(activeTab);
}

function switchTab(mod) {
    document.querySelectorAll('[id^="tab-"]').forEach(el => {
        el.className = el.className.replace('tab-active', 'tab-inactive');
    });
    document.getElementById('tab-' + mod).className = document.getElementById('tab-' + mod).className.replace('tab-inactive', 'tab-active');
    activeTab = mod;
    renderTabContent(mod);
}

function renderTabContent(mod) {
    const data = fetchedData[mod];
    const content = document.getElementById('resultContent');

    if (data.error) {
        content.innerHTML = `<div class="bg-red-900/30 border border-red-700 rounded-xl p-4 text-red-300">${data.error}</div>`;
        return;
    }

    const fields = data.all_fields || [];
    const rows = data.rows || [];

    // Barra de info
    let html = `
        <div class="bg-slate-800 rounded-xl p-4 mb-4 flex items-center justify-between">
            <div class="flex gap-6">
                <div><span class="text-slate-400 text-sm">Total na API:</span> <span class="text-white font-bold">${(data.listSize || 0).toLocaleString()}</span></div>
                <div><span class="text-slate-400 text-sm">Amostras:</span> <span class="text-white font-bold">${rows.length}</span></div>
                <div><span class="text-slate-400 text-sm">Campos únicos:</span> <span class="text-white font-bold">${fields.length}</span></div>
            </div>
            <div class="flex gap-2">
                <input type="text" id="fieldSearch-${mod}" placeholder="Buscar campo..."
                       onkeyup="filterFields('${mod}')"
                       class="bg-slate-700 border border-slate-600 rounded-lg px-3 py-1 text-sm text-white focus:ring-2 focus:ring-blue-500 focus:outline-none w-48">
                <button onclick="toggleView('${mod}')" class="bg-slate-700 hover:bg-slate-600 px-3 py-1 rounded-lg text-sm transition">
                    Alternar Vista
                </button>
            </div>
        </div>
    `;

    // Sub-tabs: Campos | JSON | Registros
    html += `
        <div class="flex gap-1 mb-4">
            <button onclick="showSubTab('${mod}','fields')" id="sub-${mod}-fields" class="px-4 py-2 text-sm rounded-t-lg bg-slate-700 text-white font-semibold">📊 Mapa de Campos</button>
            <button onclick="showSubTab('${mod}','records')" id="sub-${mod}-records" class="px-4 py-2 text-sm rounded-t-lg bg-slate-800 text-slate-400 hover:text-white">📝 Registros Individuais</button>
            <button onclick="showSubTab('${mod}','json')" id="sub-${mod}-json" class="px-4 py-2 text-sm rounded-t-lg bg-slate-800 text-slate-400 hover:text-white">{ } JSON Bruto</button>
        </div>
    `;

    // === CAMPOS ===
    html += `<div id="subtab-${mod}-fields">`;
    html += `<div class="bg-slate-800 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-700/50">
                    <th class="text-left px-4 py-2 text-slate-300 font-medium w-8">#</th>
                    <th class="text-left px-4 py-2 text-slate-300 font-medium">Campo (dot-notation)</th>
                    <th class="text-left px-4 py-2 text-slate-300 font-medium">Tipo</th>
                    ${rows.map((_, i) => `<th class="text-left px-4 py-2 text-slate-300 font-medium">Amostra ${i+1}</th>`).join('')}
                </tr>
            </thead>
            <tbody id="fieldsBody-${mod}">`;

    fields.forEach((field, idx) => {
        const depth = field.split('.').length - 1;
        const indent = depth * 16;

        html += `<tr class="field-row border-t border-slate-700/50" data-field="${field.toLowerCase()}">
            <td class="px-4 py-2 text-slate-500 text-xs">${idx + 1}</td>
            <td class="px-4 py-2 font-mono text-xs" style="padding-left:${indent + 16}px">
                ${depth > 0 ? '<span class="text-slate-600">└ </span>' : ''}
                <span class="text-blue-300">${field}</span>
            </td>
            <td class="px-4 py-2 text-xs text-slate-400">${detectType(rows, field)}</td>`;

        rows.forEach(row => {
            const val = getNestedValue(row, field);
            html += `<td class="px-4 py-2 text-xs max-w-xs truncate">${formatVal(val)}</td>`;
        });

        html += `</tr>`;
    });

    html += `</tbody></table></div></div>`;

    // === REGISTROS INDIVIDUAIS ===
    html += `<div id="subtab-${mod}-records" class="hidden">`;
    rows.forEach((row, i) => {
        html += `
            <details class="bg-slate-800 rounded-xl mb-3 overflow-hidden">
                <summary class="px-4 py-3 cursor-pointer hover:bg-slate-700/50 flex items-center gap-3">
                    <span class="text-blue-400 font-mono text-sm">Registro #${i+1}</span>
                    <span class="text-slate-400 text-xs">ID: ${row.id || 'N/A'}</span>
                </summary>
                <div class="px-4 pb-4">
                    <pre class="json-view bg-slate-900 rounded-lg p-4 overflow-auto">${syntaxHighlight(JSON.stringify(row, null, 2))}</pre>
                </div>
            </details>`;
    });
    html += `</div>`;

    // === JSON BRUTO ===
    html += `<div id="subtab-${mod}-json" class="hidden">
        <pre class="json-view bg-slate-800 rounded-xl p-4 overflow-auto">${syntaxHighlight(JSON.stringify(data, null, 2))}</pre>
    </div>`;

    content.innerHTML = html;
}

function showSubTab(mod, tab) {
    ['fields', 'records', 'json'].forEach(t => {
        const el = document.getElementById('subtab-' + mod + '-' + t);
        const btn = document.getElementById('sub-' + mod + '-' + t);
        if (el) el.classList.toggle('hidden', t !== tab);
        if (btn) {
            if (t === tab) {
                btn.className = 'px-4 py-2 text-sm rounded-t-lg bg-slate-700 text-white font-semibold';
            } else {
                btn.className = 'px-4 py-2 text-sm rounded-t-lg bg-slate-800 text-slate-400 hover:text-white';
            }
        }
    });
}

function filterFields(mod) {
    const search = (document.getElementById('fieldSearch-' + mod)?.value || '').toLowerCase();
    const rows = document.querySelectorAll('#fieldsBody-' + mod + ' tr');
    rows.forEach(row => {
        const field = row.dataset.field || '';
        row.style.display = field.includes(search) ? '' : 'none';
    });
}

// ============================================================
// HELPERS JS
// ============================================================
function getNestedValue(obj, dotKey) {
    return dotKey.split('.').reduce((o, k) => (o && o[k] !== undefined) ? o[k] : null, obj);
}

function detectType(rows, field) {
    const types = new Set();
    rows.forEach(row => {
        const v = getNestedValue(row, field);
        if (v === null || v === undefined) types.add('null');
        else if (Array.isArray(v)) types.add('array');
        else if (typeof v === 'object') types.add('object');
        else if (typeof v === 'number') types.add(Number.isInteger(v) ? 'int' : 'float');
        else if (typeof v === 'boolean') types.add('bool');
        else if (typeof v === 'string') {
            if (/^\d{2}\/\d{2}\/\d{4}/.test(v)) types.add('date');
            else if (/^-?\d+[\.,]\d+$/.test(v.replace(/\./g, ''))) types.add('decimal-str');
            else types.add('string');
        }
    });
    return Array.from(types).join(' | ');
}

function formatVal(val) {
    if (val === null || val === undefined) return '<span class="text-slate-500 italic">null</span>';
    if (typeof val === 'boolean') return `<span class="${val ? 'text-green-400' : 'text-red-400'} font-bold">${val}</span>`;
    if (typeof val === 'number') return `<span class="text-yellow-300">${val}</span>`;
    if (Array.isArray(val)) return `<span class="text-purple-400">[${val.length} items]</span>`;
    if (typeof val === 'object') return `<span class="text-purple-400">{object}</span>`;
    const s = String(val);
    if (s.length > 80) return '<span title="' + escHtml(s) + '">' + escHtml(s.substring(0, 80)) + '<span class="text-slate-500">...</span></span>';
    return escHtml(s);
}

function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function syntaxHighlight(json) {
    return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
        let cls = 'json-num';
        if (/^"/.test(match)) {
            cls = /:$/.test(match) ? 'json-key' : 'json-str';
        } else if (/true|false/.test(match)) {
            cls = 'json-bool';
        } else if (/null/.test(match)) {
            cls = 'json-null';
        }
        return '<span class="' + cls + '">' + match + '</span>';
    });
}

// ============================================================
// EXPORT / SAVE
// ============================================================
function exportJSON() {
    const json = JSON.stringify(fetchedData, null, 2);
    navigator.clipboard.writeText(json).then(() => toast('JSON copiado!', 'success'));
}

function downloadJSON() {
    const json = JSON.stringify({ extracted_at: new Date().toISOString(), modules: fetchedData }, null, 2);
    const blob = new Blob([json], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'datajuri_extract_' + new Date().toISOString().slice(0, 19).replace(/[:-]/g, '') + '.json';
    a.click();
    URL.revokeObjectURL(url);
    toast('Download iniciado', 'success');
}

async function saveSnapshot() {
    const payload = { saved_at: new Date().toISOString(), modules: fetchedData };
    const resp = await fetch('<?= basename(__FILE__) ?>?action=save_snapshot', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    const data = await resp.json();
    if (data.ok) {
        toast('Snapshot salvo: ' + data.file, 'success');
        loadSnapshots();
    } else {
        toast(data.error || 'Erro ao salvar', 'error');
    }
}

async function loadSnapshots() {
    const resp = await fetch('<?= basename(__FILE__) ?>?action=list_snapshots');
    const data = await resp.json();
    const list = document.getElementById('snapshotsList');

    if (!data.snapshots || data.snapshots.length === 0) {
        list.innerHTML = '<p class="text-sm text-slate-500">Nenhum snapshot salvo.</p>';
        return;
    }

    list.innerHTML = data.snapshots.map(s => `
        <div class="bg-slate-800 rounded-lg px-4 py-3 flex items-center justify-between">
            <div>
                <span class="text-sm font-mono text-blue-300">${s.name}</span>
                <span class="text-xs text-slate-400 ml-3">${s.date}</span>
                <span class="text-xs text-slate-500 ml-2">${(s.size / 1024).toFixed(1)} KB</span>
            </div>
            <div class="flex gap-2">
                <button onclick="restoreSnapshot('${s.name}')" class="text-xs bg-blue-600/20 hover:bg-blue-600/40 text-blue-300 px-3 py-1 rounded transition">Carregar</button>
                <button onclick="deleteSnapshot('${s.name}')" class="text-xs bg-red-600/20 hover:bg-red-600/40 text-red-300 px-3 py-1 rounded transition">Excluir</button>
            </div>
        </div>
    `).join('');
}

async function restoreSnapshot(name) {
    const resp = await fetch('<?= basename(__FILE__) ?>?action=load_snapshot&name=' + encodeURIComponent(name));
    const data = await resp.json();
    if (data.error) { toast(data.error, 'error'); return; }
    fetchedData = data.modules || data;
    document.getElementById('sectionResults').classList.remove('hidden');
    renderResults();
    toast('Snapshot restaurado', 'success');
}

async function deleteSnapshot(name) {
    if (!confirm('Excluir ' + name + '?')) return;
    await fetch('<?= basename(__FILE__) ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete_snapshot&name=' + encodeURIComponent(name)
    });
    loadSnapshots();
    toast('Excluído', 'success');
}

// ============================================================
// TOAST
// ============================================================
function toast(msg, type = 'info') {
    const colors = { success: 'bg-green-600', error: 'bg-red-600', info: 'bg-blue-600' };
    const container = document.getElementById('toastContainer');
    const el = document.createElement('div');
    el.className = `toast ${colors[type] || colors.info} text-white px-4 py-2 rounded-lg shadow-lg text-sm`;
    el.textContent = msg;
    container.appendChild(el);
    setTimeout(() => el.remove(), 3000);
}
</script>

</body>
</html>
