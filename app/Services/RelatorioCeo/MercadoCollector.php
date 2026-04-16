<?php

namespace App\Services\RelatorioCeo;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MercadoCollector
{
    private array $queries = [
        'advocacia Itajaí SC',
        'escritório advocacia Santa Catarina',
        'direito trabalhista SC',
        'direito civil Itajaí',
        'TJSC jurisprudência',
        'mercado jurídico Santa Catarina 2026',
    ];

    public function coletar(Carbon $inicio, Carbon $fim): array
    {
        $noticias = [];

        foreach ($this->queries as $query) {
            try {
                $encoded = urlencode($query);
                $url = "https://news.google.com/rss/search?q={$encoded}&hl=pt-BR&gl=BR&ceid=BR:pt-419";
                $response = Http::timeout(10)->get($url);

                if (!$response->successful()) continue;

                $itens = $this->parseRss($response->body(), $inicio);
                $noticias = array_merge($noticias, $itens);
            } catch (\Exception $e) {
                Log::warning("RelatorioCeo MercadoCollector: falha na query '{$query}'", ['error' => $e->getMessage()]);
            }
        }

        // Deduplica por título e limita
        $vistos = [];
        $noticias = array_filter($noticias, function ($n) use (&$vistos) {
            $key = md5($n['titulo']);
            if (isset($vistos[$key])) return false;
            $vistos[$key] = true;
            return true;
        });

        usort($noticias, fn($a, $b) => strcmp($b['data'], $a['data']));
        $noticias = array_slice(array_values($noticias), 0, 15);

        return [
            'total_noticias'  => count($noticias),
            'periodo_busca'   => "{$inicio->format('d/m/Y')} a {$fim->format('d/m/Y')}",
            'noticias'        => $noticias,
            'fontes_buscadas' => $this->queries,
        ];
    }

    private function parseRss(string $xml, Carbon $desde): array
    {
        $itens = [];
        try {
            libxml_use_internal_errors(true);
            $feed = simplexml_load_string($xml);
            if (!$feed) return [];

            foreach ($feed->channel->item ?? [] as $item) {
                $data = (string)$item->pubDate;
                try {
                    $dataCarbon = Carbon::parse($data);
                    if ($dataCarbon->lt($desde->copy()->subDays(30))) continue;
                } catch (\Exception) {
                    continue;
                }

                $itens[] = [
                    'titulo'   => strip_tags((string)$item->title),
                    'fonte'    => strip_tags((string)$item->source ?? ''),
                    'data'     => $dataCarbon->toDateString(),
                    'resumo'   => mb_substr(strip_tags((string)$item->description), 0, 300),
                ];

                if (count($itens) >= 5) break;
            }
        } catch (\Exception $e) {
            // RSS parsing failed silently
        }

        return $itens;
    }
}
