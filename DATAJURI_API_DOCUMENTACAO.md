# Documentação do Motor de Sincronização - API DataJuri

## Informações Gerais

**Data de Criação:** 07/01/2026  
**Última Atualização:** 07/01/2026  
**Status:** ✅ Funcionando

---

## 1. Credenciais de Acesso

### Credenciais Atuais (VÁLIDAS)
```
Client ID (username): a79mtxvdhsq0pgob733z
Secret ID (password): f21e0745-0b4f-4bd3-b0a6-959a4d47baa5
Email do Usuário: rafaelmayer@mayeradvogados.adv.br
Senha do Usuário: Mayer01.
```

### Localização no Servidor
- **Arquivo .env:** `/home/u492856976/domains/mayeradvogados.adv.br/public_html/Intranet/.env`
- **Variáveis:**
  - `DATAJURI_CLIENT_ID`
  - `DATAJURI_SECRET_ID`
  - `DATAJURI_EMAIL`
  - `DATAJURI_PASSWORD`

---

## 2. Autenticação

### Endpoint de Autenticação
```
POST https://api.datajuri.com.br/oauth/token
```

### Headers OBRIGATÓRIOS
```
Authorization: Basic {base64}
Content-Type: application/x-www-form-urlencoded
```

### ⚠️ FORMATO CORRETO DO BASE64
```php
// CORRETO - usar DOIS PONTOS (:) como separador
$credentials = base64_encode("{$clientId}:{$secretId}");

// ERRADO - NÃO usar arroba (@) como separador
// $credentials = base64_encode("{$clientId}@{$secretId}");
```

### Body da Requisição
```
grant_type=password
username={email_do_usuario}
password={senha_do_usuario}
```

### Resposta de Sucesso
```json
{
  "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "token_type": "bearer",
  "expires_in": 3600
}
```

---

## 3. Módulos Disponíveis

### Nomes CORRETOS dos Módulos
| Módulo | Nome na API | Status |
|--------|-------------|--------|
| Usuários/Advogados | `Usuario` | ✅ Funciona |
| Processos | `Processo` | ✅ Funciona |
| Atividades | `Atividade` | ✅ Funciona |
| Contas a Receber | `ContasReceber` | ✅ Funciona |
| Horas Trabalhadas | `HoraTrabalhada` | ✅ Funciona |
| Movimentos | `Movimento` | ✅ Funciona |

### ⚠️ NOMES INCORRETOS (NÃO USAR)
- ❌ `ContaReceber` (sem S) → Usar `ContasReceber`
- ❌ `LancamentoHora` → Usar `HoraTrabalhada`
- ❌ `Horas` → Usar `HoraTrabalhada`

---

## 4. Endpoints de Busca de Dados

### Endpoint CORRETO para Buscar Entidades
```
GET https://api.datajuri.com.br/v1/entidades/{modulo}
```

### ⚠️ ENDPOINT INCORRETO (NÃO USAR)
```
❌ GET https://api.datajuri.com.br/v1/modulo/{modulo}
```

### Parâmetros de Paginação
```
?pagina=1&porPagina=100
```

### Headers para Busca
```
Authorization: Bearer {access_token}
Content-Type: application/json
```

---

## 5. Estrutura das Respostas

### Estrutura CORRETA da Resposta
```json
{
  "rows": [...],       // Array com os registros
  "listSize": 1000,    // Total de registros
  "pageSize": 100,     // Registros por página
  "page": 1,           // Página atual
  "modulo": "Movimento"
}
```

### ⚠️ ESTRUTURA INCORRETA (NÃO ESPERAR)
```json
// NÃO usar estas chaves:
{
  "itens": [...],      // ❌ Usar "rows"
  "totalPaginas": 10,  // ❌ Calcular: ceil(listSize / pageSize)
  "content": [...]     // ❌ Usar "rows"
}
```

---

## 6. Campos Importantes do Módulo Movimento

### Campos Disponíveis
| Campo | Descrição | Exemplo |
|-------|-----------|---------|
| `id` | ID único do movimento | 798268 |
| `data` | Data do movimento | "06/01/2026" |
| `valorComSinal` | Valor formatado | "<span class='valor-positivo'>492,93</span>" |
| `planoConta.nomeCompleto` | Plano de contas completo | "3.01.01.01 - Receita bruta - Contrato PF" |
| `descricao` | Descrição do movimento | "C - Receita de Pessoa Física" |
| `pessoa.nome` | Nome da pessoa | "João Silva" |
| `observacao` | Observação | "Honorários..." |

### Classificação PF/PJ pelo Plano de Contas
```
3.01.01.01 - Receita bruta - Contrato PF → Pessoa Física
3.01.01.02 - Receita Bruta - Contrato PJ → Pessoa Jurídica
```

---

## 7. Conversão de Campos

### Campo `ativo` (Advogados)
A API retorna texto, o banco espera inteiro:
```php
// Conversão correta
'ativo' => ($usuario['ativo'] === 'Sim' || $usuario['ativo'] === true || $usuario['ativo'] === 1) ? 1 : 0
```

---

## 8. Arquivos do Sistema

### Localização dos Arquivos
```
/home/u492856976/domains/mayeradvogados.adv.br/public_html/Intranet/
├── app/Services/
│   ├── DataJuriService.php    # Conexão com API
│   └── SyncService.php        # Lógica de sincronização
├── app/Http/Controllers/
│   └── SyncController.php     # Controller da página
├── resources/views/sync/
│   └── index.blade.php        # Interface de sincronização
└── config/
    └── services.php           # Configurações da API
```

---

## 9. Checklist de Validação

Antes de qualquer alteração, verificar:

- [ ] Separador do base64 é `:` (dois pontos)
- [ ] Nome do módulo está correto (ver tabela acima)
- [ ] Endpoint é `/v1/entidades/` (não `/v1/modulo/`)
- [ ] Resposta usa `rows` (não `itens` ou `content`)
- [ ] Paginação usa `listSize` e `pageSize`
- [ ] Campo `ativo` é convertido para inteiro

---

## 10. Comandos Úteis

### Limpar Cache do Laravel
```bash
php artisan cache:clear
php artisan config:clear
```

### Testar Conexão via Tinker
```bash
php artisan tinker
$s = new App\Services\DataJuriService();
$token = $s->getToken();
echo $token ? "Conectado!" : "Erro!";
```

### Ver Logs de Sincronização
```bash
tail -50 storage/logs/laravel.log | grep DataJuri
```

---

## 11. Histórico de Erros Resolvidos

| Data | Erro | Causa | Solução |
|------|------|-------|---------|
| 07/01/2026 | HTTP 401 | Separador `@` no base64 | Trocar para `:` |
| 07/01/2026 | HTTP 404 ContaReceber | Nome errado do módulo | Usar `ContasReceber` |
| 07/01/2026 | HTTP 404 LancamentoHora | Nome errado do módulo | Usar `HoraTrabalhada` |
| 07/01/2026 | HTTP 404 Movimento | Endpoint errado | Usar `/v1/entidades/` |
| 07/01/2026 | 0 registros | Chave `itens` errada | Usar `rows` |
| 07/01/2026 | SQL Error ativo | Texto em campo inteiro | Converter Sim/Não → 1/0 |

---

**IMPORTANTE:** Este documento deve ser consultado antes de qualquer alteração no motor de sincronização.
