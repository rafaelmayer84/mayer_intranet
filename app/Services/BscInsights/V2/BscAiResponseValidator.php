<?php

namespace App\Services\BscInsights\V2;

class BscAiResponseValidator
{
    private array $errors = [];
    private const PERSPECTIVAS = ['financeiro', 'clientes', 'processos', 'times'];
    private const SEVERIDADES  = ['info', 'atencao', 'critico'];

    public function validate(string $rawJson, array $snapshotKeys = []): array
    {
        $this->errors = [];
        $parsed = json_decode($rawJson, true);
        if (!$parsed) {
            $this->errors[] = 'JSON invalido: ' . json_last_error_msg();
            return ['valid' => false, 'errors' => $this->errors, 'cards' => [], 'meta' => []];
        }
        if (!isset($parsed['cards']) || !is_array($parsed['cards'])) {
            $this->errors[] = 'Campo "cards" ausente';
            return ['valid' => false, 'errors' => $this->errors, 'cards' => [], 'meta' => $parsed['meta'] ?? []];
        }

        $cards = $parsed['cards'];
        $maxTotal = config('bsc_insights.max_cards_total', 24);
        $minEvid  = config('bsc_insights.min_evidencias_per_card', 2);

        if (count($cards) > $maxTotal) $this->errors[] = 'Cards (' . count($cards) . ') > max (' . $maxTotal . ')';
        if (count($cards) < 4) $this->errors[] = 'Menos de 4 cards';

        $validCards = []; $titulos = [];
        foreach ($cards as $i => $card) {
            $errs = $this->validateCard($card, $i, $snapshotKeys, $minEvid);
            if (empty($errs)) {
                $key = ($card['perspectiva'] ?? '') . '|' . ($card['titulo'] ?? '');
                if (isset($titulos[$key])) { $this->errors[] = "Card #{$i}: duplicado"; continue; }
                $titulos[$key] = true;
                $validCards[] = $card;
            }
        }

        $perPersp = [];
        foreach ($validCards as $c) { $p = $c['perspectiva'] ?? '?'; $perPersp[$p] = ($perPersp[$p] ?? 0) + 1; }
        $maxPP = config('bsc_insights.max_cards_per_perspectiva', 6);
        foreach ($perPersp as $p => $cnt) {
            if ($cnt > $maxPP) $this->errors[] = "Perspectiva '{$p}': {$cnt} cards (max={$maxPP})";
        }

        return ['valid' => empty($this->errors), 'errors' => $this->errors, 'cards' => $validCards, 'meta' => $parsed['meta'] ?? []];
    }

    public function buildRepairPrompt(string $invalidJson, array $errors): string
    {
        $list = implode("\n", array_map(fn($e) => "- {$e}", array_slice($errors, 0, 10)));
        return "Resposta anterior com erros:\n\n{$list}\n\nOriginal (corrija e reenvie APENAS JSON valido):\n{$invalidJson}";
    }

    private function validateCard(array $c, int $i, array $sk, int $minEvid): array
    {
        $errs = [];
        foreach (['perspectiva','severidade','titulo','descricao','recomendacao'] as $f) {
            if (empty($c[$f])) $errs[] = "Card #{$i}: '{$f}' vazio";
        }
        if (!empty($c['perspectiva']) && !in_array($c['perspectiva'], self::PERSPECTIVAS)) $errs[] = "Card #{$i}: perspectiva invalida";
        if (!empty($c['severidade']) && !in_array($c['severidade'], self::SEVERIDADES)) $errs[] = "Card #{$i}: severidade invalida";
        if (strlen($c['titulo'] ?? '') > 200) $errs[] = "Card #{$i}: titulo > 200 chars";
        $evid = $c['evidencias'] ?? [];
        if (count($evid) < $minEvid) $errs[] = "Card #{$i}: " . count($evid) . " evidencias (min={$minEvid})";
        $conf = $c['confidence'] ?? 50;
        if ($conf < 0 || $conf > 100) $errs[] = "Card #{$i}: confidence fora de 0-100";
        $imp = $c['impact_score'] ?? 0;
        if ($imp < 0 || $imp > 10) $errs[] = "Card #{$i}: impact fora de 0-10";
        foreach ($errs as $e) $this->errors[] = $e;
        return $errs;
    }
}
