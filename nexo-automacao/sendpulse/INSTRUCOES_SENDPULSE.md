# Configuração SendPulse - Fluxo Autenticação e Consultas

## 📱 Webhooks a Configurar

### 1. Identificar Cliente
- **URL:** `https://intranet.mayeradvogados.adv.br/api/nexo/identificar-cliente`
- **Método:** GET
- **Parâmetros:** `telefone={{phone}}`
- **Headers:** `X-Sendpulse-Token: SEU_TOKEN_SECRETO`
- **Variáveis de retorno:** `$encontrado`, `$cpf_cnpj`, `$bloqueado`

### 2. Gerar Perguntas de Autenticação
- **URL:** `https://intranet.mayeradvogados.adv.br/api/nexo/perguntas-auth`
- **Método:** POST
- **Body JSON:**
  ```json
  {
    "telefone": "{{phone}}",
    "cpf_cnpj": "{{$cpf_cnpj}}"
  }
  ```
- **Headers:** `X-Sendpulse-Token: SEU_TOKEN_SECRETO`
- **Variáveis de retorno:** `$pergunta1`, `$opcoes1`, `$pergunta2`, `$opcoes2`

### 3. Validar Autenticação
- **URL:** `https://intranet.mayeradvogados.adv.br/api/nexo/validar-auth`
- **Método:** POST
- **Body JSON:**
  ```json
  {
    "telefone": "{{phone}}",
    "resposta1": "{{$resposta1}}",
    "resposta2": "{{$resposta2}}"
  }
  ```
- **Headers:** `X-Sendpulse-Token: SEU_TOKEN_SECRETO`
- **Variáveis de retorno:** `$auth_ok`, `$tentativas_restantes`, `$bloqueado`

### 4. Consultar Status do Processo
- **URL:** `https://intranet.mayeradvogados.adv.br/api/nexo/consulta-status`
- **Método:** POST
- **Body JSON:**
  ```json
  {
    "telefone": "{{phone}}"
  }
  ```
- **Headers:** `X-Sendpulse-Token: SEU_TOKEN_SECRETO`
- **Variáveis de retorno:** `$resposta_ia`, `$sucesso`

---

## 🔧 Como Importar o Fluxo

1. Acessar SendPulse → Chatbots → WhatsApp
2. Criar novo fluxo: "Autenticação e Consultas"
3. **Opção A - Manual:** Recriar estrutura conforme `fluxo_autenticacao_consultas.json`
4. **Opção B - Importação:** Se disponível, importar JSON diretamente

---

## 🔗 Integração com Fluxo Existente

### Modificar "Mensagem de Boas-vindas"

No bloco onde o usuário seleciona "👤 Já sou cliente":

**ANTES:**
```
Tag "Clientes" → Abrir chat humano
```

**DEPOIS:**
```
Tag "Clientes" → Encaminhar para fluxo "Autenticação e Consultas"
```

---

## 📊 Estrutura do Fluxo

```
1. Webhook: Identificar Cliente
   ↓
2. Filtro: Encontrado?
   ├─ NÃO → Mensagem erro → Atendente
   └─ SIM → Confirma CPF/CNPJ
        ↓
3. Botão: Confirma?
   ├─ NÃO → Atendente
   └─ SIM → Webhook Perguntas Auth
        ↓
4. Pergunta 1 (múltipla escolha dinâmica)
   ↓
5. Pergunta 2 (múltipla escolha dinâmica)
   ↓
6. Webhook: Validar Auth
   ↓
7. Filtro: auth_ok?
   ├─ NÃO → Verificar tentativas
   │   ├─ > 0 → Tentar novamente
   │   └─ = 0 → Bloqueado → Atendente
   └─ SIM → Menu Consultas
        ├─ 📋 Status → Webhook Consulta → Resposta IA
        ├─ 💰 Outras → "Em breve"
        └─ 👤 Atendente → Abrir chat
```

---

## 🔐 Segurança

- **Token único** por ambiente (produção/teste)
- Nunca compartilhar o token publicamente
- Trocar token se comprometido
- Logs de todas as requisições ficam na intranet

---

## 📞 Testes

1. Enviar mensagem de teste no WhatsApp: "Já sou cliente"
2. Verificar se o fluxo inicia corretamente
3. Conferir logs na intranet: `/nexo/automacoes/monitor`
4. Testar autenticação com dados reais
5. Validar resposta de status de processo

---

**Dúvidas?** Consultar documentação Laravel ou logs do sistema.
