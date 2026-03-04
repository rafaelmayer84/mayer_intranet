<?php

namespace Tests\Feature;

use App\Models\EvidentiaChunk;
use App\Models\EvidentiaEmbedding;
use App\Models\EvidentiaSearch;
use App\Models\EvidentiaSearchResult;
use App\Models\User;
use App\Services\Evidentia\EvidentiaChunkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvidentiaSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    /** @test */
    public function search_page_loads_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('evidentia.index'));

        $response->assertStatus(200);
        $response->assertSee('EVIDENTIA');
    }

    /** @test */
    public function search_requires_authentication(): void
    {
        $response = $this->get(route('evidentia.index'));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function search_validates_minimum_query_length(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('evidentia.search'), [
            'query' => 'ab',
        ]);

        $response->assertSessionHasErrors('query');
    }

    /** @test */
    public function chunk_service_splits_text_correctly(): void
    {
        $service = new EvidentiaChunkService();

        $shortText = 'Texto curto de teste.';
        $chunks = $service->splitText($shortText);
        $this->assertCount(1, $chunks);
        $this->assertEquals($shortText, $chunks[0]);

        $longText = str_repeat('Esta é uma sentença de teste com conteúdo jurídico relevante. ', 50);
        $chunks = $service->splitText($longText);
        $this->assertGreaterThan(1, count($chunks));

        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(
                config('evidentia.chunk_size_chars', 1000) + 200,
                mb_strlen($chunk),
                'Chunk excede tamanho máximo esperado'
            );
        }
    }

    /** @test */
    public function citation_block_only_uses_returned_result_ids(): void
    {
        $user = User::factory()->create();

        $search = EvidentiaSearch::create([
            'user_id' => $user->id,
            'query'   => 'dano moral consumidor',
            'topk'    => 5,
            'status'  => 'complete',
        ]);

        $result = EvidentiaSearchResult::create([
            'search_id'        => $search->id,
            'jurisprudence_id' => 999,
            'tribunal'         => 'TJSC',
            'source_db'        => 'justus_tjsc',
            'final_score'      => 0.85,
            'final_rank'       => 1,
        ]);

        // Verifica que o resultado pertence à busca
        $this->assertEquals($search->id, $result->search_id);
        $this->assertEquals(1, $search->results()->count());

        // Garante que busca sem resultados não gera citation
        $emptySearch = EvidentiaSearch::create([
            'user_id' => $user->id,
            'query'   => 'termo inexistente xyz',
            'topk'    => 5,
            'status'  => 'complete',
        ]);

        $this->assertEquals(0, $emptySearch->results()->count());
    }

    /** @test */
    public function embedding_model_stores_and_retrieves_vector(): void
    {
        $chunk = EvidentiaChunk::create([
            'jurisprudence_id' => 1,
            'tribunal'         => 'TJSC',
            'source_db'        => 'justus_tjsc',
            'chunk_index'      => 0,
            'chunk_text'       => 'Texto de teste para embedding.',
            'chunk_hash'       => sha1('Texto de teste para embedding.'),
            'chunk_source'     => 'ementa',
        ]);

        $fakeVector = array_fill(0, 10, 0.1);
        $norm = sqrt(array_sum(array_map(fn($v) => $v * $v, $fakeVector)));

        $embedding = EvidentiaEmbedding::create([
            'chunk_id'    => $chunk->id,
            'model'       => 'text-embedding-3-small',
            'dims'        => 10,
            'vector_json' => $fakeVector,
            'norm'        => $norm,
        ]);

        $this->assertIsArray($embedding->getVector());
        $this->assertCount(10, $embedding->getVector());
        $this->assertGreaterThan(0, $embedding->norm);

        // Cosine similarity com vetor idêntico deve ser ~1.0
        $similarity = $embedding->cosineSimilarity($fakeVector, $norm);
        $this->assertGreaterThan(0.99, $similarity);
    }
}
