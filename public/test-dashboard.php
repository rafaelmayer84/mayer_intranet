<?php
// Script de teste para validar dados da dashboard
// Coloque este arquivo em /public_html/Intranet/public/test-dashboard.php
// Acesse: https://intranet.mayeradvogados.adv.br/test-dashboard.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bootstrap/app.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);

// Simular request
$request = \Illuminate\Http\Request::create('/');
$response = $kernel->handle($request);

// Agora temos o container pronto
$service = app(\App\Services\DashboardFinanceProdService::class);
$data = $service->getDashboardData(2026, 1);

echo "<pre>";
echo "=== RECEITA PF 12 MESES ===\n";
var_dump($data['receitaPF12Meses']);
echo "\n=== RECEITA PJ 12 MESES ===\n";
var_dump($data['receitaPJ12Meses']);
echo "\n=== LUCRATIVIDADE 12 MESES ===\n";
var_dump($data['lucratividade12Meses']);
echo "</pre>";
