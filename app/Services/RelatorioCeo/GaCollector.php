<?php

namespace App\Services\RelatorioCeo;

use Carbon\Carbon;
use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\ApiCore\CredentialsWrapper;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Google\Analytics\Data\V1beta\OrderBy;
use Google\Analytics\Data\V1beta\OrderBy\MetricOrderBy;
use Illuminate\Support\Facades\Log;

class GaCollector
{
    private ?string $propertyId;
    private ?string $credentialsPath;

    public function __construct()
    {
        $this->propertyId      = env('GA4_PROPERTY_ID');
        $this->credentialsPath = env('GA_SERVICE_ACCOUNT_PATH');
    }

    public function coletar(Carbon $inicio, Carbon $fim): array
    {
        if (!$this->propertyId || !$this->credentialsPath || !file_exists($this->credentialsPath)) {
            return ['configurado' => false, 'motivo' => 'GA4_PROPERTY_ID ou GA_SERVICE_ACCOUNT_PATH não configurados.'];
        }

        try {
            $keyData = json_decode(file_get_contents($this->credentialsPath), true);
            $saCreds = new ServiceAccountCredentials(
                ['https://www.googleapis.com/auth/analytics.readonly'],
                $keyData
            );
            $creds  = new CredentialsWrapper($saCreds, null);
            $client = new BetaAnalyticsDataClient(['credentials' => $creds]);
            $property   = "properties/{$this->propertyId}";
            $dateRange  = new DateRange([
                'start_date' => $inicio->toDateString(),
                'end_date'   => $fim->toDateString(),
            ]);
            $dateRangePrev = new DateRange([
                'start_date' => $inicio->copy()->subDays(15)->toDateString(),
                'end_date'   => $inicio->copy()->subDay()->toDateString(),
            ]);

            $visaoGeral     = $this->visaoGeral($client, $property, $dateRange);
            $porCanal       = $this->porCanal($client, $property, $dateRange);
            $porDispositivo = $this->porDispositivo($client, $property, $dateRange);
            $topPaginas     = $this->topPaginas($client, $property, $dateRange);
            $anterior       = $this->visaoGeral($client, $property, $dateRangePrev);

            $client->close();

            return [
                'configurado'       => true,
                'periodo'           => "{$inicio->toDateString()} a {$fim->toDateString()}",
                'visao_geral'       => $visaoGeral,
                'periodo_anterior'  => $anterior,
                'por_canal'         => $porCanal,
                'por_dispositivo'   => $porDispositivo,
                'top_paginas'       => $topPaginas,
                'variacao_sessoes'  => $this->variacao($anterior['sessions'] ?? 0, $visaoGeral['sessions'] ?? 0),
                'variacao_usuarios' => $this->variacao($anterior['active_users'] ?? 0, $visaoGeral['active_users'] ?? 0),
            ];

        } catch (\Exception $e) {
            Log::error('RelatorioCeo GaCollector: erro ao consultar GA4', ['error' => $e->getMessage()]);
            return ['configurado' => true, 'erro' => $e->getMessage()];
        }
    }

    private function visaoGeral(\Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient $client, string $property, DateRange $dateRange): array
    {
        $response = $client->runReport(new RunReportRequest([
            'property'    => $property,
            'date_ranges' => [$dateRange],
            'metrics'     => [
                new Metric(['name' => 'sessions']),
                new Metric(['name' => 'activeUsers']),
                new Metric(['name' => 'newUsers']),
                new Metric(['name' => 'bounceRate']),
                new Metric(['name' => 'averageSessionDuration']),
                new Metric(['name' => 'conversions']),
            ],
        ]));

        $row = $response->getRows()[0] ?? null;
        if (!$row) return [];

        $vals = array_map(fn($v) => $v->getValue(), iterator_to_array($row->getMetricValues()));

        return [
            'sessions'                 => (int)   $vals[0],
            'active_users'             => (int)   $vals[1],
            'new_users'                => (int)   $vals[2],
            'bounce_rate'              => round((float)$vals[3] * 100, 1),
            'avg_session_duration_sec' => round((float)$vals[4], 0),
            'conversions'              => (int)   $vals[5],
        ];
    }

    private function porCanal(\Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient $client, string $property, DateRange $dateRange): array
    {
        $response = $client->runReport(new RunReportRequest([
            'property'    => $property,
            'date_ranges' => [$dateRange],
            'dimensions'  => [new Dimension(['name' => 'sessionDefaultChannelGrouping'])],
            'metrics'     => [
                new Metric(['name' => 'sessions']),
                new Metric(['name' => 'newUsers']),
                new Metric(['name' => 'conversions']),
            ],
            'order_bys' => [new OrderBy(['metric' => new MetricOrderBy(['metric_name' => 'sessions']), 'desc' => true])],
            'limit'     => 10,
        ]));

        $resultado = [];
        foreach ($response->getRows() as $row) {
            $canal = $row->getDimensionValues()[0]->getValue();
            $vals  = array_map(fn($v) => $v->getValue(), iterator_to_array($row->getMetricValues()));
            $resultado[] = [
                'canal'       => $canal,
                'sessions'    => (int)$vals[0],
                'new_users'   => (int)$vals[1],
                'conversions' => (int)$vals[2],
            ];
        }

        return $resultado;
    }

    private function porDispositivo(\Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient $client, string $property, DateRange $dateRange): array
    {
        $response = $client->runReport(new RunReportRequest([
            'property'    => $property,
            'date_ranges' => [$dateRange],
            'dimensions'  => [new Dimension(['name' => 'deviceCategory'])],
            'metrics'     => [new Metric(['name' => 'sessions'])],
        ]));

        $resultado = [];
        foreach ($response->getRows() as $row) {
            $resultado[$row->getDimensionValues()[0]->getValue()] =
                (int)$row->getMetricValues()[0]->getValue();
        }

        return $resultado;
    }

    private function topPaginas(\Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient $client, string $property, DateRange $dateRange): array
    {
        $response = $client->runReport(new RunReportRequest([
            'property'    => $property,
            'date_ranges' => [$dateRange],
            'dimensions'  => [new Dimension(['name' => 'pageTitle'])],
            'metrics'     => [
                new Metric(['name' => 'screenPageViews']),
                new Metric(['name' => 'averageSessionDuration']),
            ],
            'order_bys' => [new OrderBy(['metric' => new MetricOrderBy(['metric_name' => 'screenPageViews']), 'desc' => true])],
            'limit'     => 8,
        ]));

        $resultado = [];
        foreach ($response->getRows() as $row) {
            $titulo = $row->getDimensionValues()[0]->getValue();
            $vals   = array_map(fn($v) => $v->getValue(), iterator_to_array($row->getMetricValues()));
            $resultado[] = [
                'pagina'    => mb_substr($titulo, 0, 60),
                'pageviews' => (int)$vals[0],
                'avg_dur'   => round((float)$vals[1], 0),
            ];
        }

        return $resultado;
    }

    private function variacao(float $anterior, float $atual): float
    {
        if ($anterior == 0) return 0;
        return round((($atual - $anterior) / $anterior) * 100, 1);
    }
}
