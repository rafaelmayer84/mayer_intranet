# Instruções de implementação (Agente implementador)

Este patch adiciona o módulo **Quadro de Avisos** e um conjunto de rotas de **diagnóstico** para identificar rapidamente a causa de erros 500 sem depender de SSH.

## 0) Pré-requisitos
- Projeto em Laravel (rodando via Apache/Nginx)
- Acesso para subir arquivos no diretório do projeto
- PHP/Composer já configurados no servidor (ou no ambiente de deploy)
- Usuário com sessão autenticada para acessar as telas (este build **não** aplica restrição admin/público)

## 1) Backup (obrigatório)
1. Faça backup do diretório do projeto (ao menos: `routes/`, `app/`, `resources/`, `database/`).
2. Faça backup do banco de dados.

## 2) Aplicação do patch
1. Extraia o conteúdo deste .zip **na raiz do projeto Laravel** (mesmo nível de `app/`, `routes/`, `resources/`, `database/`), mantendo a estrutura de pastas.
2. Se o servidor não permitir sobrescrita direta, copie os arquivos manualmente preservando caminhos.

Arquivos principais alterados/adicionados:
- `routes/web.php` e `routes/_avisos_routes.php`
- Controllers: `AvisoController`, `CategoriaAvisoController`, `DiagController`
- Views: `resources/views/avisos/*`
- Migrations + Seeder

## 3) Comandos pós-deploy
Execute na raiz do projeto:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --class=CategoriaAvisoSeeder --force
```

Se o ambiente não permitir `--force`, execute sem ele.

## 4) Teste funcional mínimo (URLs)
Após login, testar nesta ordem:

1) **Diagnóstico geral**
- `/_diag`  
Deve mostrar informações de ambiente (APP_ENV, cache, storage, etc).

2) **Diagnóstico do banco**
- `/_diag/db`  
Deve listar se existem as tabelas:
- `categorias_avisos`
- `avisos`
- `avisos_lidos`

3) **Quadro de Avisos**
- `/avisos` (lista)
- `/avisos/{id}` (detalhe)

4) **Admin (sem restrição neste build)**
- `/admin/avisos`
- `/admin/avisos/create`
- `/admin/categorias-avisos`

## 5) Se ocorrer erro 500
1. Abra `/_diag/log` para ver as últimas linhas do `storage/logs/laravel.log`.
2. Se o log estiver vazio, valide permissões em:
   - `storage/` e `bootstrap/cache/` (devem ser graváveis pelo usuário do servidor web)
3. Se o erro for SQL (tabela/coluna inexistente), rode `php artisan migrate --force` novamente e reteste `/_diag/db`.

## 6) Observações importantes
- Este build não usa gating de admin: qualquer autenticado acessa as rotas de gerenciamento.
- Se houver conflitos com o `routes/web.php` atual do projeto, faça merge manual (não sobrescreva cegamente).
- Em caso de deploy incremental, prefira:
  - copiar `routes/_avisos_routes.php` e incluir o require no seu `web.php` existente.

## 7) Rollback (em caso de emergência)
1. Remover rotas adicionadas em `routes/web.php` (ou remover `require routes/_avisos_routes.php`)
2. Reverter arquivos alterados a partir do backup.
3. (Opcional) Remover tabelas do módulo se necessário:
   - `avisos_lidos`, `avisos`, `categorias_avisos` (nesta ordem)
