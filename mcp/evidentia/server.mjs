#!/usr/bin/env node

/**
 * Evidentia MCP Server
 * Expõe busca semântica de jurisprudência para o Claude Desktop.
 *
 * Configuração via variáveis de ambiente:
 *   EVIDENTIA_API_URL   — URL base da intranet (ex: https://intranet.mayeradvogados.adv.br)
 *   EVIDENTIA_MCP_TOKEN — token Bearer gerado no .env da intranet
 */

import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import {
  CallToolRequestSchema,
  ListToolsRequestSchema,
} from '@modelcontextprotocol/sdk/types.js';

const API_URL   = (process.env.EVIDENTIA_API_URL || 'https://intranet.mayeradvogados.adv.br').replace(/\/$/, '');
const API_TOKEN = process.env.EVIDENTIA_MCP_TOKEN;

if (!API_TOKEN) {
  process.stderr.write('[evidentia-mcp] ERRO: EVIDENTIA_MCP_TOKEN não definido.\n');
  process.exit(1);
}

// ─── HTTP helper ─────────────────────────────────────────────────────────────

async function apiCall(method, path, body = null) {
  const url = `${API_URL}/api/evidentia-mcp${path}`;

  const options = {
    method,
    headers: {
      'Authorization': `Bearer ${API_TOKEN}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    signal: AbortSignal.timeout(120_000), // 2 min — busca híbrida é lenta
  };

  if (body) {
    options.body = JSON.stringify(body);
  }

  const res = await fetch(url, options);
  const json = await res.json();

  if (!res.ok) {
    throw new Error(json.error || `HTTP ${res.status}`);
  }

  return json;
}

// ─── Formatação de resultado para leitura do Claude ──────────────────────────

function formatResultados(data) {
  const lines = [
    `🔍 Busca: "${data.query}"`,
    `📊 ${data.total_resultados} resultados | ${data.latencia_ms}ms | $${data.custo_usd} USD`,
    data.degraded_mode ? '⚠️  Modo degradado (busca semântica indisponível)' : '',
    `search_id: ${data.search_id}`,
    '',
  ].filter(Boolean);

  for (const r of data.resultados) {
    lines.push(`─── #${r.rank} — ${r.tribunal} | Score: ${r.score_final}`);
    lines.push(`Processo: ${r.numero_processo || 'N/D'}`);
    lines.push(`Classe: ${r.sigla_classe || ''} ${r.descricao_classe || ''}`);
    lines.push(`Relator: ${r.relator || 'N/D'} | Órgão: ${r.orgao_julgador || 'N/D'}`);
    lines.push(`Data: ${r.data_decisao || 'N/D'}`);
    if (r.rerank_justificativa) {
      lines.push(`💡 ${r.rerank_justificativa}`);
    }
    lines.push(`Ementa: ${(r.ementa || '').substring(0, 600)}...`);
    lines.push('');
  }

  return lines.join('\n');
}

function formatCitation(data) {
  return [
    `📝 Bloco de citação — busca #${data.search_id}`,
    `Tema: "${data.query}"`,
    '',
    '## Síntese objetiva',
    data.sintese_objetiva,
    '',
    '## Precedentes (pronto para inserir na peça)',
    data.bloco_precedentes,
    '',
    `Custo: $${data.custo_usd} USD`,
  ].join('\n');
}

// ─── Servidor MCP ─────────────────────────────────────────────────────────────

const server = new Server(
  { name: 'evidentia', version: '1.0.0' },
  { capabilities: { tools: {} } }
);

server.setRequestHandler(ListToolsRequestSchema, async () => ({
  tools: [
    {
      name: 'evidentia_buscar',
      description:
        'Busca jurisprudência relevante no banco Evidentia usando busca híbrida (fulltext + semântica + rerank por IA). ' +
        'Cobre TJSC, STJ, TRF4 e TRT12. Use antes de redigir qualquer peça jurídica para encontrar precedentes. ' +
        'Retorna até 10 acórdãos rankeados com ementa, relator, data e justificativa do ranking.',
      inputSchema: {
        type: 'object',
        properties: {
          query: {
            type: 'string',
            description: 'Tese jurídica ou tema a pesquisar. Seja específico: ex. "dano moral atraso voo consumidor responsabilidade objetiva"',
          },
          tribunal: {
            type: 'string',
            enum: ['TJSC', 'STJ', 'TRF4', 'TRT12'],
            description: 'Filtrar por tribunal (opcional). Omitir para pesquisar todos.',
          },
          topk: {
            type: 'integer',
            minimum: 3,
            maximum: 20,
            default: 10,
            description: 'Número de resultados a retornar (padrão: 10).',
          },
          periodo_inicio: {
            type: 'string',
            description: 'Data inicial no formato YYYY-MM-DD (opcional).',
          },
          periodo_fim: {
            type: 'string',
            description: 'Data final no formato YYYY-MM-DD (opcional).',
          },
        },
        required: ['query'],
      },
    },
    {
      name: 'evidentia_resultado',
      description:
        'Recupera os resultados de uma busca Evidentia já realizada pelo search_id. ' +
        'Use quando quiser revisitar uma busca anterior sem gastar novo orçamento de IA.',
      inputSchema: {
        type: 'object',
        properties: {
          search_id: {
            type: 'integer',
            description: 'ID da busca retornado por evidentia_buscar.',
          },
        },
        required: ['search_id'],
      },
    },
    {
      name: 'evidentia_gerar_citacao',
      description:
        'Gera um bloco de citação jurídica pronto para inserir em petições e pareceres. ' +
        'Retorna: (1) síntese objetiva dos precedentes encontrados; (2) bloco formatado com as referências completas. ' +
        'Requer um search_id de uma busca já realizada com evidentia_buscar.',
      inputSchema: {
        type: 'object',
        properties: {
          search_id: {
            type: 'integer',
            description: 'ID da busca retornado por evidentia_buscar.',
          },
        },
        required: ['search_id'],
      },
    },
  ],
}));

server.setRequestHandler(CallToolRequestSchema, async (request) => {
  const { name, arguments: args } = request.params;

  try {
    if (name === 'evidentia_buscar') {
      const body = { query: args.query };
      if (args.tribunal)       body.tribunal       = args.tribunal;
      if (args.topk)           body.topk           = args.topk;
      if (args.periodo_inicio) body.periodo_inicio = args.periodo_inicio;
      if (args.periodo_fim)    body.periodo_fim    = args.periodo_fim;

      const data = await apiCall('POST', '/search', body);
      return { content: [{ type: 'text', text: formatResultados(data) }] };
    }

    if (name === 'evidentia_resultado') {
      const data = await apiCall('GET', `/results/${args.search_id}`);
      return { content: [{ type: 'text', text: formatResultados(data) }] };
    }

    if (name === 'evidentia_gerar_citacao') {
      const data = await apiCall('POST', `/citation/${args.search_id}`);
      return { content: [{ type: 'text', text: formatCitation(data) }] };
    }

    throw new Error(`Tool desconhecida: ${name}`);
  } catch (err) {
    return {
      content: [{ type: 'text', text: `❌ Erro: ${err.message}` }],
      isError: true,
    };
  }
});

// ─── Start ────────────────────────────────────────────────────────────────────

const transport = new StdioServerTransport();
await server.connect(transport);
process.stderr.write('[evidentia-mcp] Servidor iniciado.\n');
