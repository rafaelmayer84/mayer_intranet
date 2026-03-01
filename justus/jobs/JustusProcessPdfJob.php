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

    public int $timeout = 300;
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

            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            $pages = $pdf->getPages();
            $totalPages = count($pages);

            if ($totalPages === 0) {
                throw new \Exception('PDF sem páginas extraíveis. Pode ser documento baseado em imagem/scan.');
            }

            $pageTexts = [];
            foreach ($pages as $index => $page) {
                $pageNumber = $index + 1;
                $text = $page->getText();
                $charCount = mb_strlen($text);

                JustusDocumentPage::create([
                    'attachment_id' => $attachment->id,
                    'page_number' => $pageNumber,
                    'text_content' => $text,
                    'char_count' => $charCount,
                ]);

                $pageTexts[$pageNumber] = $text;
            }

            $chunkSizePages = config('justus.chunk_size_pages', 2);
            $chunkIndex = 0;
            $pageNumbers = array_keys($pageTexts);

            for ($i = 0; $i < count($pageNumbers); $i += $chunkSizePages) {
                $chunkPages = array_slice($pageNumbers, $i, $chunkSizePages);
                $pageStart = min($chunkPages);
                $pageEnd = max($chunkPages);

                $chunkContent = '';
                foreach ($chunkPages as $pn) {
                    $chunkContent .= "[Página {$pn}]\n" . $pageTexts[$pn] . "\n\n";
                }

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

            SystemEvent::sistema(
                'JUSTUS: PDF processado com sucesso',
                'info',
                [
                    'attachment_id' => $attachment->id,
                    'conversation_id' => $attachment->conversation_id,
                    'pages' => $totalPages,
                    'chunks' => $chunkIndex,
                    'file' => $attachment->original_name,
                ],
                $attachment->user_id
            );

        } catch (\Exception $e) {
            $attachment->update([
                'processing_status' => 'failed',
                'processing_error' => $e->getMessage(),
            ]);

            Log::error('JUSTUS PDF Job Failed', [
                'attachment_id' => $this->attachmentId,
                'error' => $e->getMessage(),
            ]);

            SystemEvent::sistema(
                'JUSTUS: Falha ao processar PDF',
                'error',
                [
                    'attachment_id' => $this->attachmentId,
                    'error' => $e->getMessage(),
                    'file' => $attachment->original_name,
                ],
                $attachment->user_id
            );
        }
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
