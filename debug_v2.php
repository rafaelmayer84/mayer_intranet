<?php
// CREDENCIAIS (Preenchidas automaticamente)
$clientId = 'a79mtxvdhsq0pgob733z';
$secretId = 'f21e0745-0b4f-4bd3-b0a6-959a4d47baa5';
$email = 'rafaelmayer@mayeradvogados.adv.br';
$password = 'Mayer01.';

// 1. AUTENTICACAO
echo "--- AUTENTICANDO ---\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.datajuri.com.br/oauth/token");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['grant_type'=>'password','username'=>$email,'password'=>$password]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Basic ".base64_encode("$clientId:$secretId"),"Content-Type: application/x-www-form-urlencoded"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$token = json_decode(curl_exec($ch), true)['access_token'] ?? null;
curl_close($ch);

if (!$token) die("ERRO DE TOKEN.\n");

function testarURL($nome, $url, $token) {
    echo "\n>>> TESTE $nome: $url\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    curl_close($ch);
    
    $item = json_decode($res, true)['rows'][0] ?? null;
    if ($item) {
        echo "CAMPOS RETORNADOS:\n";
        print_r(array_keys($item));
        // Procura o campo de status
        foreach ($item as $k => $v) {
            if (preg_match('/status|situacao|fase|lancado|etapa/i', $k)) {
                echo "!!! ACHADO !!! [$k] => " . json_encode($v) . "\n";
            }
        }
    } else {
        echo "Nenhum registro retornado ou erro.\n";
    }
}

// TENTATIVA 1: SEM O PARAMETRO CAMPOS (Padrao da API)
testarURL("PADRAO", "https://api.datajuri.com.br/v1/entidades/ContasReceber?removerHtml=true&pageSize=1", $token);

// TENTATIVA 2: LISTA EXPLICITA (Forca bruta)
testarURL("FORCA BRUTA", "https://api.datajuri.com.br/v1/entidades/ContasReceber?removerHtml=true&pageSize=1&campos=id,descricao,valor,status,situacao,fase,lancado,etapa,statusFinanceiro", $token);
?>
