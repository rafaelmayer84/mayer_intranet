<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\SisrhDocumento;

class SisrhDocumentoController extends Controller
{
    private $categorias = [
        'geral' => 'Geral',
        'contrato' => 'Contrato',
        'procuracao' => 'Procuração',
        'certidao' => 'Certidão',
        'oab' => 'OAB',
        'identidade' => 'Identidade/RG/CPF',
        'comprovante' => 'Comprovante de Residência',
        'diploma' => 'Diploma/Certificado',
        'atestado' => 'Atestado',
        'recibo' => 'Recibo',
        'outro' => 'Outro',
    ];

    public function index(Request $request, int $userId)
    {
        $this->checkPermissao($userId);

        $adv = DB::table('users')->where('id', $userId)->first();
        if (!$adv) abort(404);

        $query = SisrhDocumento::where('user_id', $userId)->orderByDesc('created_at');

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->categoria);
        }

        $documentos = $query->get();
        $categorias = $this->categorias;

        return view('sisrh.documentos', compact('adv', 'documentos', 'categorias'));
    }

    public function upload(Request $request, int $userId)
    {
        $this->checkAdmin();

        $request->validate([
            'arquivo' => 'required|file|mimes:pdf|max:10240',
            'categoria' => 'required|string|max:50',
            'description' => 'nullable|string|max:500',
        ]);

        $file = $request->file('arquivo');
        $nomeOriginal = $file->getClientOriginalName();
        $nomeStorage = $userId . '_' . time() . '_' . Str::slug(pathinfo($nomeOriginal, PATHINFO_FILENAME)) . '.pdf';

        $file->storeAs('public/sisrh-docs', $nomeStorage);

        SisrhDocumento::create([
            'user_id' => $userId,
            'categoria' => $request->categoria,
            'nome_original' => $nomeOriginal,
            'nome_storage' => $nomeStorage,
            'mime_type' => 'application/pdf',
            'tamanho' => $file->getSize(),
            'description' => $request->descricao,
            'uploaded_by' => Auth::id(),
        ]);

        DB::table('audit_logs')->insert([
            'user_id' => Auth::id(),
            'action' => 'sisrh_doc_upload',
            'description' => "Upload doc '$nomeOriginal' para user $userId",
            'module' => 'sisrh', 'user_name' => Auth::user()->name, 'user_role' => Auth::user()->role, 'created_at' => now(),
        ]);

        return back()->with('success', "Documento \"$nomeOriginal\" enviado.");
    }

    public function download(int $id)
    {
        $doc = SisrhDocumento::findOrFail($id);
        $this->checkPermissao($doc->user_id);

        $path = storage_path('app/public/sisrh-docs/' . $doc->nome_storage);
        if (!file_exists($path)) abort(404, 'Arquivo não encontrado.');

        return response()->download($path, $doc->nome_original, ['Content-Type' => 'application/pdf']);
    }

    public function visualizar(int $id)
    {
        $doc = SisrhDocumento::findOrFail($id);
        $this->checkPermissao($doc->user_id);

        $path = storage_path('app/public/sisrh-docs/' . $doc->nome_storage);
        if (!file_exists($path)) abort(404, 'Arquivo não encontrado.');

        return response()->file($path, ['Content-Type' => 'application/pdf']);
    }

    public function excluir(int $id)
    {
        $this->checkAdmin();

        $doc = SisrhDocumento::findOrFail($id);

        Storage::delete('public/sisrh-docs/' . $doc->nome_storage);

        DB::table('audit_logs')->insert([
            'user_id' => Auth::id(),
            'action' => 'sisrh_doc_excluir',
            'description' => "Excluiu doc '{$doc->nome_original}' do user {$doc->user_id}",
            'module' => 'sisrh', 'user_name' => Auth::user()->name, 'user_role' => Auth::user()->role, 'created_at' => now(),
        ]);

        $doc->delete();

        return back()->with('success', 'Documento excluído.');
    }

    private function checkPermissao(int $userId): void
    {
        $user = Auth::user();
        if (!$user) abort(403);
        if ($user->role === 'admin') return;
        if ($user->role === 'coordenador') return; // coordenador vê todos
        if ($user->id === $userId) return; // próprio
        abort(403);
    }

    private function checkAdmin(): void
    {
        if (!in_array(Auth::user()->role ?? '', ['admin', 'coordenador'])) abort(403);
    }
}
