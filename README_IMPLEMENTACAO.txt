PATCH - QUADRO DE AVISOS (build sem restrição Admin/Público)

O que este patch entrega
- Quadro de Avisos como página inicial (/ e /avisos)
- CRUD completo de avisos (/admin/avisos) e categorias (/admin/categorias-avisos)
- Rastreamento de leitura (POST /avisos/{id}/lido)
- Agendamento (data_inicio) e validade (data_fim), com filtro automático de avisos vencidos
- Cards com prioridade e ícones, filtros e ordenação
- Seed automático de categorias padrão (quando tabela vazia)
- Proteção contra ERRO 500 quando as migrations ainda não foram rodadas (mostra tela explicativa)

Arquivos
- routes/web.php (rota / redireciona para /avisos; rotas do módulo)
- app/Models/*Aviso*.php + relacionamento em User.php
- app/Services/AvisoService.php
- app/Http/Controllers/AvisoController.php e CategoriaAvisoController.php
- migrations: 2026_01_09_000001/2/3_...
- views: resources/views/avisos/**
- layout: resources/views/layouts/app.blade.php (link no menu lateral)

Passo a passo no servidor
1) Faça backup do projeto.
2) Copie o conteúdo deste patch para dentro do projeto, preservando caminhos.
3) Rode:
   php artisan migrate
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
4) Acesse /avisos e crie um aviso em /admin/avisos.

Observação
- NESTE BUILD: rotas /admin/avisos e /admin/categorias-avisos NÃO têm middleware de permissão. A fase de perfis e gates ficará para a próxima entrega.
