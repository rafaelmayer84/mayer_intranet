<?php
// Script para adicionar rotas ao web.php

$webPhpPath = __DIR__ . '/routes/web.php';
$content = file_get_contents($webPhpPath);

// Verificar se as rotas já existem
if (strpos($content, 'visao-gerencial') === false) {
    $newRoutes = <<<'ROUTES'

// Sistema Resultados - Novas rotas
Route::middleware(['auth'])->group(function () {
    Route::get('/visao-gerencial', [App\Http\Controllers\DashboardController::class, 'visaoGerencial'])->name('visao-gerencial');
    Route::get('/configurar-metas', [App\Http\Controllers\DashboardController::class, 'configurarMetas'])->name('configurar-metas');
    Route::put('/configurar-metas', [App\Http\Controllers\DashboardController::class, 'updateMetas'])->name('configurar-metas.update');
});
ROUTES;

    file_put_contents($webPhpPath, $content . $newRoutes);
    echo "Rotas adicionadas com sucesso!\n";
} else {
    echo "Rotas já existem no arquivo.\n";
}
