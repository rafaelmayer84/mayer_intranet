<?php

namespace App\Http\Controllers;

use App\Models\JustusConversation;
use App\Models\JustusAttachment;
use App\Models\JustusMessage;
use App\Models\JustusProcessProfile;
use App\Models\JustusApproval;
use App\Models\SystemEvent;
use App\Services\Justus\JustusBudgetService;
use App\Services\Justus\JustusOpenAiService;
use App\Jobs\JustusProcessPdfJob;
use App\Models\JustusStyleGuide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class JustusController extends Controller
{
    private JustusBudgetService $budgetService;

    public function __construct(JustusBudgetService $budgetService)
    {
        $this->budgetService = $budgetService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $conversationId = $request->query('c');

        $conversations = JustusConversation::where('user_id', $user->id)
            ->where('status', '!=', 'archived')
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->get();

        $activeConversation = null;
        $messages = collect();
        $profile = null;
        $attachments = collect();
        $approval = null;

        if ($conversationId) {
            $activeConversation = JustusConversation::where('id', $conversationId)
                ->where('user_id', $user->id)
                ->first();

            if ($activeConversation) {
                $messages = $activeConversation->messages()->orderBy('id')->get();
                $profile = $activeConversation->processProfile;
                $attachments = $activeConversation->attachments()->orderByDesc('created_at')->get();
                $approval = $activeConversation->approvals()->latest()->first();
            }
        }

        $budget = $this->budgetService->canProceed($user->id);

        return view('justus.index', compact(
            'conversations',
            'activeConversation',
            'messages',
            'profile',
            'attachments',
            'approval',
            'budget'
        ));
    }

    public function createConversation(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'type' => 'required|in:analise_estrategica,analise_completa,peca,calculo_prazo,higiene_autos',
            'mode' => 'required|in:consultor,assessor',
        ]);

        $user = Auth::user();

        $conversation = JustusConversation::create([
            'user_id' => $user->id,
            'title' => $request->input('title', 'Nova Análise'),
            'type' => $request->input('type'),
            'mode' => $request->input('mode', 'consultor'),
        ]);

        JustusProcessProfile::create([
            'conversation_id' => $conversation->id,
        ]);

        SystemEvent::sistema('justus', 'info', 'Nova conversa JUSTUS', null, ['conversation_id' => $conversation->id, 'type' => $conversation->type]);

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'conversation_id' => $conversation->id]);
        }
        return redirect()->route('justus.index', ['c' => $conversation->id]);
    }

    public function sendMessage(Request $request, int $conversationId)
    {
        $request->validate([
            'message' => 'required|string|max:10000',
        ]);

        $user = Auth::user();
        $conversation = JustusConversation::where('id', $conversationId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        // Limite de turnos por conversa
        $userTurnCount = $conversation->messages()->where('role', 'user')->count();
        if ($userTurnCount >= 10) {
            return response()->json(['success' => false, 'error' => 'Limite de 10 mensagens atingido nesta conversa. Crie uma nova análise.'], 429);
        }

        $openAiService = app(JustusOpenAiService::class);
        $result = $openAiService->sendMessage($conversation, $request->input('message'));

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json($result);
        }

        if (!$result['success'] && ($result['blocked'] ?? false)) {
            return back()->with('error', $result['error']);
        }

        return redirect()->route('justus.index', ['c' => $conversation->id]);
    }

    public function upload(Request $request, int $conversationId)
    {
        $request->validate([
            'pdf_file' => 'required|file|mimes:pdf|max:' . (config('justus.max_upload_size_mb', 50) * 1024),
        ]);

        $user = Auth::user();
        $conversation = JustusConversation::where('id', $conversationId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $file = $request->file('pdf_file');
        $originalName = $file->getClientOriginalName();
        $storagePath = config('justus.storage_base_path') . '/' . $user->id . '/' . $conversationId;
        $storedPath = $file->storeAs($storagePath, $originalName, 'local');

        $attachment = JustusAttachment::create([
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'original_name' => $originalName,
            'stored_path' => $storedPath,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'processing_status' => 'pending',
        ]);

        JustusProcessPdfJob::dispatch($attachment->id);

        SystemEvent::sistema('justus', 'info', 'JUSTUS: PDF enviado', null, ['attachment_id' => $attachment->id, 'file' => $originalName]);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'attachment' => $attachment->only(['id', 'original_name', 'processing_status', 'file_size']),
            ]);
        }

        return redirect()->route('justus.index', ['c' => $conversation->id])
            ->with('success', 'PDF enviado para processamento.');
    }

    public function download(int $conversationId, int $attachmentId)
    {
        $user = Auth::user();
        $conversation = JustusConversation::where('id', $conversationId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $attachment = JustusAttachment::where('id', $attachmentId)
            ->where('conversation_id', $conversation->id)
            ->firstOrFail();

        $path = Storage::disk('local')->path($attachment->stored_path);

        if (!file_exists($path)) {
            abort(404, 'Arquivo não encontrado.');
        }

        return response()->download($path, $attachment->original_name);
    }

    public function updateProfile(Request $request, int $conversationId)
    {
        $user = Auth::user();
        $conversation = JustusConversation::where('id', $conversationId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $profile = JustusProcessProfile::firstOrCreate(
            ['conversation_id' => $conversation->id]
        );

        $profile->update($request->only([
            'numero_cnj', 'fase_atual', 'objetivo_analise', 'tese_principal',
            'limites_restricoes', 'autor', 'reu', 'relator_vara',
            'data_intimacao', 'prazo_medio', 'manual_estilo_aceito',
        ]));

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('justus.index', ['c' => $conversation->id]);
    }

    public function approve(Request $request, int $conversationId)
    {
        $request->validate([
            'action' => 'required|in:request,approve,reject',
            'message_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:2000',
        ]);

        $user = Auth::user();
        $conversation = JustusConversation::where('id', $conversationId)->firstOrFail();

        if ($conversation->user_id !== $user->id && !in_array($user->role, ['admin', 'socio', 'coordenador'])) {
            abort(403);
        }

        $action = $request->input('action');

        if ($action === 'request') {
            $approval = JustusApproval::create([
                'conversation_id' => $conversation->id,
                'message_id' => $request->input('message_id'),
                'requested_by' => $user->id,
                'status' => 'pending',
            ]);

            SystemEvent::sistema('justus', 'info', 'JUSTUS: Aprovação solicitada', null, ['conversation_id' => $conversation->id, 'approval_id' => $approval->id]);
        } else {
            $approval = $conversation->approvals()->where('status', 'pending')->latest()->firstOrFail();
            $approval->update([
                'status' => $action === 'approve' ? 'approved' : 'rejected',
                'reviewed_by' => $user->id,
                'reviewer_notes' => $request->input('notes'),
            ]);

            SystemEvent::sistema('justus', 'info', "JUSTUS: Peca {$action}", null, ['conversation_id' => $conversation->id, 'approval_id' => $approval->id]);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['success' => true, 'approval' => $approval]);
        }

        return redirect()->route('justus.index', ['c' => $conversation->id]);
    }

    public function checkAttachmentStatus(int $conversationId, int $attachmentId)
    {
        $user = Auth::user();
        $attachment = JustusAttachment::whereHas('conversation', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('id', $attachmentId)->firstOrFail();

        return response()->json([
            'status' => $attachment->processing_status,
            'total_pages' => $attachment->total_pages,
            'error' => $attachment->processing_error,
        ]);
    }

    public function destroyConversation(int $conversationId)
    {
        $conversation = JustusConversation::where('id', $conversationId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        // Deletar mensagens, anexos, chunks, profile
        $conversation->messages()->delete();
        foreach ($conversation->attachments as $att) {
            $att->chunks()->delete();
            if ($att->stored_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($att->stored_path)) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($att->stored_path);
            }
            $att->delete();
        }
        if ($conversation->profile) {
            $conversation->profile->delete();
        }
        $conversation->delete();

        return response()->json(['success' => true]);
    }


    // ========================
    // ADMIN: Configuração JUSTUS
    // ========================

    public function adminConfig()
    {
        $this->authorizeAdmin();
        $guides = JustusStyleGuide::orderBy('mode')->orderByDesc('version')->get();
        $budget = $this->budgetService->getGlobalMonthlyUsage();
        $config = [
            'usd_brl' => config('justus.usd_brl', 5.80),
            'budget_monthly_max' => config('justus.budget_monthly_max'),
            'budget_user_max' => config('justus.budget_user_max'),
            'model_default' => config('justus.model_default'),
        ];
        return view('justus.admin', compact('guides', 'budget', 'config'));
    }

    public function adminUpdateGuide(Request $request, int $guideId)
    {
        $this->authorizeAdmin();
        $request->validate([
            'name' => 'required|string|max:255',
            'system_prompt' => 'required|string',
            'behavior_rules' => 'nullable|string',
            'ad003_disclaimer' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $guide = JustusStyleGuide::findOrFail($guideId);
        $guide->update($request->only(['name', 'system_prompt', 'behavior_rules', 'ad003_disclaimer', 'is_active']));

        SystemEvent::sistema('justus', 'info', "JUSTUS Admin: Style guide '{$guide->name}' atualizado", null, ['guide_id' => $guide->id]);

        return response()->json(['success' => true, 'message' => 'Configuração salva.']);
    }

    public function downloadDocument(int $conversationId, int $messageId)
    {
        $user = Auth::user();
        $message = JustusMessage::whereHas('conversation', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('id', $messageId)
          ->where('conversation_id', $conversationId)
          ->firstOrFail();

        $metadata = json_decode($message->metadata ?? '{}', true);
        $docPath = $metadata['doc_path'] ?? null;

        if (!$docPath) {
            abort(404, 'Documento não encontrado');
        }

        $fullPath = storage_path('app/public/' . $docPath);
        if (!file_exists($fullPath)) {
            abort(404, 'Arquivo não encontrado');
        }

        return response()->download($fullPath, basename($docPath));
    }

    public function messageFeedback(Request $request, int $conversationId, int $messageId)
    {
        $request->validate([
            'feedback' => 'required|in:positive,negative',
            'note' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();
        $message = JustusMessage::whereHas('conversation', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->where('id', $messageId)
          ->where('conversation_id', $conversationId)
          ->where('role', 'assistant')
          ->firstOrFail();

        $message->update([
            'feedback' => $request->input('feedback'),
            'feedback_note' => $request->input('note'),
            'feedback_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function adminFeedbackReport()
    {
        $this->authorizeAdmin();

        $stats = [
            'total' => JustusMessage::where('role', 'assistant')->whereNotNull('feedback')->count(),
            'positive' => JustusMessage::where('role', 'assistant')->where('feedback', 'positive')->count(),
            'negative' => JustusMessage::where('role', 'assistant')->where('feedback', 'negative')->count(),
        ];

        $negatives = JustusMessage::where('role', 'assistant')
            ->where('feedback', 'negative')
            ->with(['conversation:id,title,mode,type,user_id', 'conversation.user:id,name'])
            ->orderByDesc('feedback_at')
            ->limit(50)
            ->get(['id', 'conversation_id', 'content', 'feedback_note', 'feedback_at', 'model_used', 'input_tokens', 'output_tokens']);

        return response()->json([
            'stats' => $stats,
            'negatives' => $negatives,
        ]);
    }

    private function authorizeAdmin(): void
    {
        if (!in_array(auth()->user()->role, ['admin'])) {
            abort(403, 'Acesso restrito à administração.');
        }
    }

}
