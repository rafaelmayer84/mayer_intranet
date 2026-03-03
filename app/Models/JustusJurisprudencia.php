<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class JustusJurisprudencia extends Model
{
    protected $table = 'justus_jurisprudencia';

    protected $fillable = [
        'external_id', 'tribunal', 'numero_processo', 'numero_registro', 'numero_documento',
        'sigla_classe', 'descricao_classe', 'classe_padronizada', 'orgao_julgador', 'relator',
        'data_publicacao', 'data_decisao', 'ementa', 'tipo_decisao', 'decisao',
        'tese_juridica', 'termos_auxiliares', 'referencias_legislativas', 'acordaos_similares',
        'area_direito', 'fonte_dataset', 'fonte_resource',
    ];

    protected $casts = [
        'data_decisao' => 'date',
        'referencias_legislativas' => 'array',
        'acordaos_similares' => 'array',
    ];

    /**
     * Mapeamento tribunal -> conexao do banco
     */
    private static array $tribunalConnections = [
        'TJSC' => 'justus_tjsc',
        'STJ'  => 'justus_stj',
        // 'FALCAO' => 'justus_falcao', // habilitar quando tiver dados
    ];

    /**
     * Retorna a conexao correta para um tribunal
     */
    public static function connectionForTribunal(string $tribunal): string
    {
        return self::$tribunalConnections[strtoupper($tribunal)] ?? 'mysql';
    }

    /**
     * Seta a conexao default do Model para o banco do tribunal.
     * Usado pelos Commands de sync para direcionar INSERTs.
     */
    public static function setTribunalConnection(string $tribunal): void
    {
        $conn = self::connectionForTribunal($tribunal);
        $instance = new static;
        $instance->setConnection($conn);
        // Guardar para que novas instancias usem esta conexao
        static::$resolvedConnection = $conn;
    }

    private static ?string $resolvedConnection = null;

    public function getConnectionName()
    {
        return static::$resolvedConnection ?? parent::getConnectionName();
    }

    /**
     * Retorna nova instancia do model apontando para o banco do tribunal
     */
    public static function onTribunal(string $tribunal): \Illuminate\Database\Eloquent\Builder
    {
        $conn = self::connectionForTribunal($tribunal);
        return (new static)->setConnection($conn)->newQuery();
    }

    /**
     * Retorna todas as conexoes de tribunais configuradas
     */
    public static function allTribunalConnections(): array
    {
        return self::$tribunalConnections;
    }

    // Mapeamento orgao_julgador -> area_direito
    public static function inferAreaDireito(string $orgao): ?string
    {
        $orgaoUpper = mb_strtoupper($orgao);
        if (str_contains($orgaoUpper, 'PRIMEIRA TURMA') || str_contains($orgaoUpper, 'SEGUNDA TURMA') || str_contains($orgaoUpper, 'PRIMEIRA SEÇÃO')) {
            return 'tributario';
        }
        if (str_contains($orgaoUpper, 'TERCEIRA TURMA') || str_contains($orgaoUpper, 'QUARTA TURMA') || str_contains($orgaoUpper, 'SEGUNDA SEÇÃO')) {
            return 'civil';
        }
        if (str_contains($orgaoUpper, 'QUINTA TURMA') || str_contains($orgaoUpper, 'SEXTA TURMA') || str_contains($orgaoUpper, 'TERCEIRA SEÇÃO')) {
            return 'penal';
        }
        if (str_contains($orgaoUpper, 'CORTE ESPECIAL')) {
            return 'civil';
        }
        return null;
    }

    public static function inferAreaDireitoTjsc(string $orgao): ?string
    {
        $orgaoUpper = mb_strtoupper($orgao);
        if (str_contains($orgaoUpper, 'CRIMINAL')) return 'penal';
        if (str_contains($orgaoUpper, 'DIREITO CIVIL') || str_contains($orgaoUpper, 'CÍVEL')) return 'civil';
        if (str_contains($orgaoUpper, 'COMERCIAL')) return 'comercial';
        if (str_contains($orgaoUpper, 'PÚBLICO') || str_contains($orgaoUpper, 'PUBLICO')) return 'publico';
        if (str_contains($orgaoUpper, 'RECURSAL') || str_contains($orgaoUpper, 'RECURSOS')) return 'civil';
        return null;
    }

    /**
     * Busca FULLTEXT com relevancia em TODOS os bancos de tribunais
     */
    public static function searchRelevant(string $query, int $limit = 5, ?string $area = null): Collection
    {
        $keywords = self::extractSearchTerms($query);
        if (empty($keywords)) {
            return collect();
        }

        $matchExpr = implode(' ', $keywords);
        $allResults = collect();

        // Buscar em cada banco de tribunal
        foreach (self::$tribunalConnections as $tribunal => $conn) {
            try {
                $results = DB::connection($conn)
                    ->table('justus_jurisprudencia')
                    ->whereRaw('MATCH(ementa) AGAINST(? IN BOOLEAN MODE)', [$matchExpr])
                    ->selectRaw('*, MATCH(ementa) AGAINST(? IN BOOLEAN MODE) as relevance', [$matchExpr])
                    ->when($area, fn($q) => $q->where('area_direito', $area))
                    ->orderByDesc('relevance')
                    ->orderByDesc('data_decisao')
                    ->limit($limit)
                    ->get();

                // Adicionar tribunal_source para rastreabilidade
                $results->each(fn($r) => $r->tribunal_source = $tribunal);
                $allResults = $allResults->merge($results);
            } catch (\Exception $e) {
                \Log::warning("Justus: falha ao buscar jurisprudencia em {$tribunal}: " . $e->getMessage());
            }
        }

        // Tambem buscar no banco principal (para tribunais ainda nao migrados)
        try {
            $mainResults = DB::connection('mysql')
                ->table('justus_jurisprudencia')
                ->whereRaw('MATCH(ementa) AGAINST(? IN BOOLEAN MODE)', [$matchExpr])
                ->selectRaw('*, MATCH(ementa) AGAINST(? IN BOOLEAN MODE) as relevance', [$matchExpr])
                ->when($area, fn($q) => $q->where('area_direito', $area))
                ->orderByDesc('relevance')
                ->orderByDesc('data_decisao')
                ->limit($limit)
                ->get();

            $mainResults->each(fn($r) => $r->tribunal_source = 'PRINCIPAL');
            $allResults = $allResults->merge($mainResults);
        } catch (\Exception $e) {
            // Tabela pode estar vazia no principal, OK
        }

        // Ordenar por relevancia global e limitar
        return $allResults->sortByDesc('relevance')->take($limit)->values();
    }

    public function toPromptFormat(): string
    {
        $ref = $this->sigla_classe . ' ' . $this->numero_registro;
        $relator = $this->relator;
        $orgao = $this->orgao_julgador;
        $data = $this->data_decisao ? (is_string($this->data_decisao) ? $this->data_decisao : $this->data_decisao->format('d/m/Y')) : 'data n/d';
        $ementa = trim($this->ementa);

        return "{$ref}, Rel. Min. {$relator}, {$orgao}, j. {$data}\nEMENTA: {$ementa}";
    }

    private static function extractSearchTerms(string $query): array
    {
        $stopWords = [
            'o','a','os','as','um','uma','de','do','da','dos','das','em','no','na','nos','nas',
            'por','para','com','sem','que','e','ou','se','mas','como','mais','menos','ao','aos',
            'pelo','pela','este','esta','esse','essa','isso','isto','não','sim','já','foi','são',
            'está','qual','quais','onde','quando','quem','sobre','entre','até','após','ante',
            'ser','ter','fazer','poder','dever','haver','artigo','art','lei','código','processo',
            'recurso','especial','agravo','apelação','ação',
        ];

        $words = preg_split('/[\s,;.:()\[\]{}]+/', mb_strtolower($query));
        $words = array_filter($words, function ($w) use ($stopWords) {
            return mb_strlen($w) >= 3 && !in_array($w, $stopWords);
        });

        return array_values(array_unique(array_slice($words, 0, 10)));
    }

    public function getShortRefAttribute(): string
    {
        $data = $this->data_decisao ? (is_string($this->data_decisao) ? $this->data_decisao : $this->data_decisao->format('d/m/Y')) : '';
        return "{$this->sigla_classe} {$this->numero_registro}, Rel. Min. {$this->relator}, {$this->orgao_julgador}, j. {$data}";
    }
}
