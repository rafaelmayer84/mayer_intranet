<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SendPulseWhatsAppService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class NexoTemplateController extends Controller
{
public function listar(Request $request)
    {
        try {
            $service = app(SendPulseWhatsAppService::class);
            $templates = $service->getWhatsAppTemplates();

            // API retorna {success:true, data:[...]} — extrair array real
            if (is_array($templates) && isset($templates['data'])) {
                $templates = $templates['data'];
            }

            if ($templates === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao consultar templates no SendPulse.',
                    'templates' => [],
                ], 500);
            }

            $aprovados = collect($templates)->filter(function ($tpl) {
                $status = $tpl['status'] ?? '';
                return in_array(strtoupper($status), ['APPROVED', 'CONFIRMED']);
            })->map(function ($tpl) {
                return [
                    'name'     => $tpl['name'] ?? '',
                    'status'   => $tpl['status'] ?? '',
                    'category' => $tpl['category'] ?? '',
                    'language' => $tpl['language'] ?? ($tpl['lang'] ?? ''),
                    'body'     => $this->extrairBodyText($tpl),
                    'buttons'  => $this->extrairButtons($tpl),
                    'header'   => $this->extrairHeader($tpl),
                    'footer'   => $this->extrairFooter($tpl),
                    'has_vars' => $this->temVariaveis($tpl),
                    'template' => $tpl['template'] ?? $tpl,
                ];
            })->values();

            return response()->json([
                'success'   => true,
                'templates' => $aprovados,
                'total'     => $aprovados->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('NexoTemplate@listar erro: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erro interno: ' . $e->getMessage(),
                'templates' => [],
            ], 500);
        }
    }

    public function enviar(Request $request)
    {
        $request->validate([
            'telefone'      => 'required|string',
            'template_name' => 'required|string',
            'template_data' => 'required|array',
            'language'      => 'nullable|string',
        ]);

        $telefone     = preg_replace('/[^0-9]/', '', $request->input('telefone'));
        $templateName = $request->input('template_name');
        $templateData = $request->input('template_data');

        try {
            $service = app(SendPulseWhatsAppService::class);
            // Garantir que language seja array conforme API SendPulse exige
            $lang = $request->input('language', 'pt_BR');
            $templatePayload = ['name' => $templateName, 'language' => ['code' => $lang], 'components' => []];
            $result = $service->sendTemplateByPhone($telefone, $templatePayload);

            Log::info('NexoTemplate@enviar', [
                'user'     => Auth::user()->name ?? 'N/A',
                'telefone' => $telefone,
                'template' => $templateName,
                'result'   => $result,
            ]);

            if (isset($result['success']) && $result['success'] === false) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Erro ao enviar template.',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Template enviado com sucesso para ' . $telefone,
                'result'  => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('NexoTemplate@enviar erro: ' . $e->getMessage(), [
                'telefone' => $telefone,
                'template' => $templateName,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao enviar: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function extrairBodyText(array $tpl): string
    {
        if (isset($tpl['components'])) {
            foreach ($tpl['components'] as $comp) {
                if (($comp['type'] ?? '') === 'BODY') {
                    return $comp['text'] ?? '';
                }
            }
        }
        if (isset($tpl['text'])) {
            return $tpl['text'];
        }
        if (isset($tpl['body'])) {
            return is_string($tpl['body']) ? $tpl['body'] : ($tpl['body']['text'] ?? '');
        }
        return '';
    }

    private function extrairButtons(array $tpl): array
    {
        if (isset($tpl['components'])) {
            foreach ($tpl['components'] as $comp) {
                if (($comp['type'] ?? '') === 'BUTTONS') {
                    return $comp['buttons'] ?? [];
                }
            }
        }
        return $tpl['buttons'] ?? [];
    }

    private function extrairHeader(array $tpl): string
    {
        if (isset($tpl['components'])) {
            foreach ($tpl['components'] as $comp) {
                if (($comp['type'] ?? '') === 'HEADER') {
                    return $comp['text'] ?? ($comp['format'] ?? '');
                }
            }
        }
        return $tpl['header'] ?? '';
    }

    private function extrairFooter(array $tpl): string
    {
        if (isset($tpl['components'])) {
            foreach ($tpl['components'] as $comp) {
                if (($comp['type'] ?? '') === 'FOOTER') {
                    return $comp['text'] ?? '';
                }
            }
        }
        return $tpl['footer'] ?? '';
    }

    private function temVariaveis(array $tpl): bool
    {
        $body = $this->extrairBodyText($tpl);
        return (bool) preg_match('/\{\{\d+\}\}/', $body);
    }
}
