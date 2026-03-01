<?php

namespace App\Jobs;

use App\Models\JustusAttachment;
use App\Models\JustusDocumentPage;
use App\Models\JustusDocumentChunk;
use App\Models\JustusProcessProfile;
use App\Models\SystemEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class JustusProcessPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    private int $attachmentId;

    public function __construct(int $attachmentId)
    {
        $this->attachmentId = $attachmentId;
        $this->queue = config('justus.queue_name', 'justus');
    }

    public function handle(): void
    {
        $attachment = JustusAttachment::find($this->attachmentId);
        if (!$attachment) {
            Log::warning('JUSTUS PDF Job: Attachment not found', ['id' => $this->attachmentId]);
            return;
        }

        $attachment->update(['processing_status' => 'processing']);

        try {
            $filePath = Storage::disk('local')->path($attachment->stored_path);

            if (!file_exists($filePath)) {
                throw new \Exception('Arquivo PDF não encontrado no storage: ' . $attachment->stored_path);
            }

            // Extrair texto via Ghostscript (txtwrite) - robusto para PDFs judiciais
            $totalPages = $this->countPdfPages($filePath);

            if ($totalPages === 0) {
                throw new \Exception('PDF sem páginas extraíveis.');
            }

            $pageTexts = [];
            $tmpFile = \tempnam(\sys_get_temp_dir(), 'justus_gs_') . '.txt';
            $escapedPath = \escapeshellarg($filePath);

            for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++) {
                $cmd = \sprintf(
                    'gs -sDEVICE=txtwrite -dNOPAUSE -dBATCH -dQUIET -dFirstPage=%d -dLastPage=%d -sOutputFile=%s %s 2>&1',
                    $pageNumber,
                    $pageNumber,
                    \escapeshellarg($tmpFile),
                    $escapedPath
                );

                $exitCode = $this->runCommand($cmd);

                $text = '';
                if ($exitCode === 0 && \file_exists($tmpFile)) {
                    $text = \trim(\file_get_contents($tmpFile));
                    @\unlink($tmpFile);
                }

                $charCount = \mb_strlen($text);

                JustusDocumentPage::create([
                    'attachment_id' => $attachment->id,
                    'page_number' => $pageNumber,
                    'text_content' => $text,
                    'char_count' => $charCount,
                ]);

                $pageTexts[$pageNumber] = $text;
            }

            @\unlink($tmpFile);

            if (empty($pageTexts)) {
                throw new \Exception('Ghostscript nao conseguiu extrair texto do PDF.');
            }

            // Classificar paginas (relevante vs ruido)
            $this->classifyPages($attachment->id);

            // Criar chunks APENAS com paginas relevantes
            $relevantPages = JustusDocumentPage::where('attachment_id', $attachment->id)
                ->where('is_relevant', true)
                ->orderBy('page_number')
                ->pluck('page_number')
                ->toArray();

            $chunkSizePages = config('justus.chunk_size_pages', 2);
            $chunkIndex = 0;

            for ($i = 0; $i < count($relevantPages); $i += $chunkSizePages) {
                $chunkPageNumbers = \array_slice($relevantPages, $i, $chunkSizePages);
                $pageStart = min($chunkPageNumbers);
                $pageEnd = max($chunkPageNumbers);

                $chunkContent = '';
                foreach ($chunkPageNumbers as $pn) {
                    if (isset($pageTexts[$pn]) && !empty($pageTexts[$pn])) {
                        $chunkContent .= "[Pagina {$pn}]\n" . $pageTexts[$pn] . "\n\n";
                    }
                }

                if (empty(trim($chunkContent))) continue;

                $tokenEstimate = (int) (mb_strlen($chunkContent) / 4);

                JustusDocumentChunk::create([
                    'attachment_id' => $attachment->id,
                    'chunk_index' => $chunkIndex,
                    'page_start' => $pageStart,
                    'page_end' => $pageEnd,
                    'content' => $chunkContent,
                    'token_estimate' => $tokenEstimate,
                ]);

                $chunkIndex++;
            }

            $attachment->update([
                'processing_status' => 'completed',
                'total_pages' => $totalPages,
                'processed_at' => now(),
            ]);

            $this->tryExtractProcessProfile($attachment, $pageTexts);

            SystemEvent::sistema('justus', 'info', 'JUSTUS: PDF processado', null, ['attachment_id' => $attachment->id, 'pages' => $totalPages]);

        } catch (\Exception $e) {
            $attachment->update([
                'processing_status' => 'failed',
                'processing_error' => $e->getMessage(),
            ]);

            Log::error('JUSTUS PDF Job Failed', [
                'attachment_id' => $this->attachmentId,
                'error' => $e->getMessage(),
            ]);

            SystemEvent::sistema('justus', 'error', 'JUSTUS: Falha PDF', null, ['error' => $e->getMessage()]);
        }
    }

    private function classifyPages(int $attachmentId): void
    {
        $pages = JustusDocumentPage::where('attachment_id', $attachmentId)->get();

        // Tipos de documento que sempre interessam
        $relevantPatterns = [
            'PETI' => 'peticao',
            'INICIAL' => 'peticao_inicial',
            'CONTESTAC' => 'contestacao',
            'RECONVEN' => 'reconvencao',
            'IMPUGNA' => 'impugnacao',
            'EMBARGO' => 'embargos',
            'RECURSO' => 'recurso',
            'APELA' => 'apelacao',
            'AGRAVO' => 'agravo',
            'SENTEN' => 'sentenca',
            'ACORDAO' => 'acordao',
            'DESPACHO' => 'despacho_decisao',
            'DECISAO' => 'despacho_decisao',
            'INTERLOCUT' => 'decisao_interlocutoria',
            'TUTELA' => 'tutela',
            'LAUDO' => 'laudo_pericial',
            'PARECER' => 'parecer',
            'CONTRATO' => 'contrato',
            'PROCURA' => 'procuracao',
            'SUBSTABELEC' => 'substabelecimento',
            'ALVARA' => 'alvara',
            'PLANILHA' => 'planilha_calculo',
            'CALCULO' => 'planilha_calculo',
            'CONTRAFÉ' => 'contrafe',
            'CONTRSOCIAL' => 'contrato_social',
        ];

        // Tipos que sao ruido (nao interessam para analise juridica)
        $noisePatterns = [
            'SISBAJUD' => 'sisbajud',
            'RENAJUD' => 'renajud',
            'INFOJUD' => 'infojud',
            'PREVJUD' => 'prevjud',
            'SNIP' => 'snip',
            'TRANS_REC_SISBA' => 'transferencia_sisbajud',
            'CON_EXT_SISBA' => 'extrato_sisbajud',
            'CON_EXT_RENA' => 'extrato_renajud',
            'DETSISPARTOT' => 'detalhamento_sisbajud',
            'AVISO DE RECEBIMENTO' => 'aviso_recebimento',
            'MANDADO' => 'mandado',
            'ATO ORDINAT' => 'ato_ordinatorio',
            'CERTIDAO' => 'certidao',
            'CERT1' => 'certidao',
            'OFICIO' => 'oficio',
            'COMUNICAC' => 'comunicacao',
            'EXTRATO DE SUBCONTA' => 'extrato_subconta',
            'CONFIRMAC' => 'confirmacao_pagamento',
        ];

        // Texto minimo para considerar pagina com conteudo real
        $minCharsRelevant = 150;

        // Regex para detectar pagina de separacao (EPROC/PJe)
        $separadorPattern = '/P.GINA.{0,3}DE.{0,3}SEPARA.{1,3}O/iu';

        foreach ($pages as $page) {
            $text = $page->text_content;
            $normalizedText = \iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
            $upperText = \strtoupper($normalizedText);
            $isSeparador = (bool) \preg_match($separadorPattern, $normalizedText);
            // Se eh pagina de separacao, classificar pelo tipo de documento mencionado
            if ($isSeparador) {
                $docType = 'separador';
                $isRelevant = false;

                // Verificar se o separador indica documento relevante
                foreach ($relevantPatterns as $pattern => $type) {
                    if (\mb_strpos($upperText, \mb_strtoupper($pattern)) !== false) {
                        $docType = 'separador_' . $type;
                        // Separador em si nao eh relevante, mas marca para identificar documento
                        break;
                    }
                }
                foreach ($noisePatterns as $pattern => $type) {
                    if (\mb_strpos($upperText, \mb_strtoupper($pattern)) !== false) {
                        $docType = 'separador_' . $type;
                        break;
                    }
                }

                $page->update(['doc_type' => $docType, 'is_relevant' => false]);
                continue;
            }

            // Pagina com conteudo real - classificar
            $docType = 'conteudo';
            $isRelevant = true;

            // Se pouco texto, provavelmente eh rodape/capa
            if ($page->char_count < $minCharsRelevant) {
                $docType = 'capa_ou_rodape';
                $isRelevant = false;
                $page->update(['doc_type' => $docType, 'is_relevant' => false]);
                continue;
            }

            // Verificar se eh pagina de ruido pelo conteudo
            foreach ($noisePatterns as $pattern => $type) {
                if (\mb_strpos($upperText, \mb_strtoupper($pattern)) !== false) {
                    // Verificar se eh APENAS referencia no rodape vs conteudo real
                    $patternCount = \mb_substr_count($upperText, \mb_strtoupper($pattern));
                    // Se padrão de ruido aparece e pagina tem pouco texto juridico real
                    if ($patternCount > 0 && $page->char_count < 500) {
                        $docType = $type;
                        $isRelevant = false;
                        break;
                    }
                }
            }

            // Verificar se eh pagina com conteudo juridico substantivo
            if ($isRelevant) {
                $juridicalTerms = ['requer', 'julgo', 'defiro', 'indefiro', 'condeno', 'absolvo',
                    'contrato', 'clausula', 'obrigac', 'indeniz', 'honorar', 'autor', 'reu',
                    'exequente', 'executad', 'apelante', 'apelad', 'agravante', 'reclamante',
                    'preliminar', 'merito', 'fundament', 'direito', 'codigo civil', 'cpc',
                    'sentenc', 'decisao', 'acordo', 'citac', 'intimac', 'prazo', 'recurso',
                    'improcedent', 'procedent', 'parcialmente', 'tutela', 'liminar',
                    'dano moral', 'dano material', 'lucro cessante', 'proveito economico'];

                $lowerText = \mb_strtolower($text);
                $termCount = 0;
                foreach ($juridicalTerms as $term) {
                    if (\mb_strpos($lowerText, $term) !== false) {
                        $termCount++;
                    }
                }

                // Pagina com texto longo e termos juridicos = alta relevancia
                if ($termCount >= 2) {
                    $docType = 'conteudo_juridico';
                } elseif ($page->char_count > 800) {
                    $docType = 'conteudo_longo';
                }
            }

            $page->update(['doc_type' => $docType, 'is_relevant' => $isRelevant]);
        }

        Log::info('JUSTUS: Classificacao de paginas', [
            'attachment_id' => $attachmentId,
            'total' => $pages->count(),
            'relevantes' => JustusDocumentPage::where('attachment_id', $attachmentId)->where('is_relevant', true)->count(),
            'ruido' => JustusDocumentPage::where('attachment_id', $attachmentId)->where('is_relevant', false)->count(),
        ]);
    }

    private function countPdfPages(string $filePath): int
    {
        $cmd = sprintf('gs -q -dNODISPLAY -c "(%s) (r) file runpdfbegin pdfpagecount = quit" 2>/dev/null', \addcslashes($filePath, '()'));
        $result = trim($this->runCommandOutput($cmd));

        if (is_numeric($result) && (int) $result > 0) {
            return (int) $result;
        }

        // Fallback: contar via grep no PDF
        $cmd2 = sprintf("grep -c '/Type\\s*/Page' %s 2>/dev/null", \escapeshellarg($filePath));
        $result2 = trim($this->runCommandOutput($cmd2));

        return is_numeric($result2) ? max((int) $result2 - 1, 1) : 0;
    }

    private function runCommand(string $cmd): int
    {
        $proc = \proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!\is_resource($proc)) {
            return 1;
        }

        \fclose($pipes[0]);
        \stream_get_contents($pipes[1]);
        \fclose($pipes[1]);
        \stream_get_contents($pipes[2]);
        \fclose($pipes[2]);

        return \proc_close($proc);
    }

    private function runCommandOutput(string $cmd): string
    {
        $proc = \proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!\is_resource($proc)) {
            return '';
        }

        \fclose($pipes[0]);
        $output = \stream_get_contents($pipes[1]);
        \fclose($pipes[1]);
        \stream_get_contents($pipes[2]);
        \fclose($pipes[2]);
        \proc_close($proc);

        return $output ?: '';
    }

    private function tryExtractProcessProfile(JustusAttachment $attachment, array $pageTexts): void
    {
        $fullText = implode("\n", array_slice($pageTexts, 0, 10));

        $profile = JustusProcessProfile::firstOrCreate(
            ['conversation_id' => $attachment->conversation_id],
            []
        );

        if (preg_match('/\d{7}[\-\.]\d{2}\.\d{4}\.\d\.\d{2}\.\d{4}/', $fullText, $m)) {
            $profile->numero_cnj = $m[0];
        }

        if (preg_match('/(?:AUTOR|EXEQUENTE|REQUERENTE|RECLAMANTE|APELANTE)\s*[:\-]\s*(.+?)(?:\n|$)/i', $fullText, $m)) {
            $profile->autor = trim($m[1]);
        }

        if (preg_match('/(?:RÉU|EXECUTADO|REQUERIDO|RECLAMADO|APELADO)\s*[:\-]\s*(.+?)(?:\n|$)/i', $fullText, $m)) {
            $profile->reu = trim($m[1]);
        }

        if (preg_match('/(?:CLASSE|AÇÃO)\s*[:\-]\s*(.+?)(?:\n|$)/i', $fullText, $m)) {
            $profile->classe = trim($m[1]);
        }

        if (preg_match('/(?:VARA|TURMA|CÂMARA|SEÇÃO)\s*[:\-]?\s*(.+?)(?:\n|$)/i', $fullText, $m)) {
            $profile->relator_vara = trim($m[1]);
        }

        if (preg_match('/(?:INTIMAÇÃO|PUBLICAÇÃO)\s*[:\-]?\s*(\d{2}\/\d{2}\/\d{4})/i', $fullText, $m)) {
            try {
                $profile->data_intimacao = \Carbon\Carbon::createFromFormat('d/m/Y', $m[1])->toDateString();
            } catch (\Exception $e) {
            }
        }

        $profile->save();
    }
}
