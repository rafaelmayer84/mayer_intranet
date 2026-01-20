# Patch Quadro de Avisos (DEBUG + Estabilização)

Este patch resolve o ciclo de “erro 500 sem visibilidade” adicionando **diagnóstico via UI** e tornando o módulo de avisos **tolerante** a setup incompleto (ex.: migrations ainda não rodaram).

## 1) O que este patch entrega

- Rotas do Quadro de Avisos e rotas de Admin **sem restrição por perfil** (somente `auth`).
- Página de diagnóstico, acessível após login:
  - `/_diag` (menu)
  - `/_diag/status` (ambiente/storage/log)
  - `/_diag/db` (conexão DB + checagem das tabelas + contagens)
  - `/_diag/routes` (rotas de login/avisos/diag carregadas)
  - `/_diag/log` (tail do `storage/logs/laravel*.log`)
- Tratamento de falha em `/avisos`: quando algo quebra, a tela mostra um **ID do erro** e aponta para `/_diag/log`.
- Ajuste de migrations duplicadas (idempotentes com `Schema::hasTable()`), evitando “Table already exists”.
- Ajuste do `CategoriaAvisoSeeder` para não falhar por coluna inexistente.

## 2) Como aplicar no servidor

1. **Backup** (mínimo recomendado):
   - Copie `routes/web.php`, `app/Http/Controllers/*Aviso*.php`, `database/migrations/*avisos*`, `database/seeders/CategoriaAvisoSeeder.php`.

2. Suba este patch e aplique por cima da pasta do projeto (mantendo a mesma estrutura de diretórios).

3. Limpe caches (Laravel):

```bash
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
```

4. Rode migrations (essencial):

```bash
php artisan migrate --force
```

5. Rode o seeder de categorias (opcional, mas recomendado):

```bash
php artisan db:seed --class=CategoriaAvisoSeeder --force
```

## 3) Como diagnosticar rapidamente (sem SSH)

- Faça login normalmente.
- Abra `/_diag`.
- Clique em:
  - `Banco de dados` (`/_diag/db`) → confirma se as tabelas existem.
  - `Tail do laravel.log` (`/_diag/log`) → mostra o erro real (stacktrace) do 500.

Se `/avisos` falhar, a tela vai mostrar um **ID do erro**. Use esse ID para localizar o log.

## 4) Observações

- Este patch mantém o diagnóstico **somente para usuário autenticado**.
- Não implementa regras de Admin/gerente por enquanto (conforme solicitado).
