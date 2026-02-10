#!/usr/bin/env python3
"""
PATCH: Processos Internos — Período Ano/Mês + Remover Responsável
=================================================================
1. View: Troca seletor período (7d/15d/30d/custom) por Ano + Mês (igual financeiro)
2. View: Remove filtro Responsável
3. Controller: Troca resolverFiltros para usar ano/mes em vez de periodo/data_inicio/data_fim
4. Service: Ajustar para receber ano/mes

USO:
  cd ~/domains/mayeradvogados.adv.br/public_html/Intranet
  python3 patch_filtros_processos.py
"""

import os
import re
import shutil
from datetime import datetime

BASE = os.path.dirname(os.path.abspath(__file__))

def backup(fp):
    full = os.path.join(BASE, fp)
    if os.path.exists(full):
        bak = full + f'.bak_{datetime.now().strftime("%Y%m%d_%H%M%S")}'
        shutil.copy2(full, bak)
        print(f"  📦 Backup: {os.path.basename(bak)}")

def patch_view():
    """Troca filtros na view: Ano/Mês no lugar de Período, remove Responsável"""
    fp = os.path.join(BASE, 'resources/views/dashboard/processos-internos/index.blade.php')
    c = open(fp, 'r', encoding='utf-8').read()

    # ─── 1. Substituir bloco Período (select 7d/15d/30d/custom) por Ano + Mês ───
    old_periodo = """            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Período</label>
                <select name="periodo" onchange="toggleCustomDates(this); this.form.submit();"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    @foreach(['7'=>'7 dias','15'=>'15 dias','30'=>'30 dias','mes'=>'Mês atual','trimestre'=>'Trimestre','custom'=>'Personalizado'] as $val=>$label)
                        <option value="{{ $val }}" {{ ($filtros['periodo'] ?? '30')===(string)$val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>"""

    new_periodo = """            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Ano</label>
                <select name="ano" onchange="this.form.submit();"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    @for($a = date('Y'); $a >= 2020; $a--)
                        <option value="{{ $a }}" {{ (int)($filtros['ano'] ?? date('Y'))===$a ? 'selected' : '' }}>{{ $a }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Mês</label>
                <select name="mes" onchange="this.form.submit();"
                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm">
                    @foreach(['1'=>'Jan','2'=>'Fev','3'=>'Mar','4'=>'Abr','5'=>'Mai','6'=>'Jun','7'=>'Jul','8'=>'Ago','9'=>'Set','10'=>'Out','11'=>'Nov','12'=>'Dez'] as $mv=>$ml)
                        <option value="{{ $mv }}" {{ (int)($filtros['mes'] ?? date('n'))===(int)$mv ? 'selected' : '' }}>{{ $ml }}</option>
                    @endforeach
                </select>
            </div>"""

    if old_periodo in c:
        c = c.replace(old_periodo, new_periodo, 1)
        print("  ✔ Período → Ano + Mês")
    else:
        print("  ⚠ Bloco período não encontrado (pode já ter sido alterado)")

    # ─── 2. Remover campos De/Até (custom dates) ───
    # Bloco data_inicio
    pat_inicio = r'            <div id="custom-dates-inicio".*?</div>'
    c_new = re.sub(pat_inicio, '', c, count=1, flags=re.DOTALL)
    if c_new != c:
        c = c_new
        print("  ✔ Campo 'De' (data_inicio) removido")

    # Bloco data_fim
    pat_fim = r'            <div id="custom-dates-fim".*?</div>'
    c_new = re.sub(pat_fim, '', c, count=1, flags=re.DOTALL)
    if c_new != c:
        c = c_new
        print("  ✔ Campo 'Até' (data_fim) removido")

    # ─── 3. Remover bloco Responsável inteiro ───
    old_responsavel = """            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Responsável</label>
                <select name="responsavel[]" multiple class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 text-sm" style="min-height:36px">
                    @foreach($responsaveis ?? [] as $r)
                        <option value="{{ $r->id }}" {{ in_array($r->id, (array)($filtros['responsavel'] ?? [])) ? 'selected' : '' }}>{{ $r->nome }}</option>
                    @endforeach
                </select>
            </div>"""

    if old_responsavel in c:
        c = c.replace(old_responsavel, '', 1)
        print("  ✔ Filtro Responsável removido")
    else:
        print("  ⚠ Bloco responsável não encontrado")

    # ─── 4. Remover função toggleCustomDates do JavaScript ───
    pat_toggle = r'function toggleCustomDates\(.*?\}\s*\n'
    c_new = re.sub(pat_toggle, '', c, count=1, flags=re.DOTALL)
    if c_new != c:
        c = c_new
        print("  ✔ Função toggleCustomDates removida do JS")

    open(fp, 'w', encoding='utf-8').write(c)


def patch_controller():
    """Troca resolverFiltros para usar ano/mes"""
    fp = os.path.join(BASE, 'app/Http/Controllers/Dashboard/ProcessosInternosController.php')
    c = open(fp, 'r', encoding='utf-8').read()

    # Substituir o bloco de filtros
    old_filtros = """        $filtros = [
            'periodo'                  => $request->get('periodo', $sessao['periodo'] ?? '30'),
            'data_inicio'              => $request->get('data_inicio', $sessao['data_inicio'] ?? null),
            'data_fim'                 => $request->get('data_fim', $sessao['data_fim'] ?? null),
            'responsavel'              => $request->get('responsavel', $sessao['responsavel'] ?? []),
            'grupo'                    => $request->get('grupo', $sessao['grupo'] ?? []),
            'area'                     => $request->get('area', $sessao['area'] ?? null),
            'tipo_atividade'           => $request->get('tipo_atividade', $sessao['tipo_atividade'] ?? null),
            'status_processo'          => $request->get('status_processo', $sessao['status_processo'] ?? null),
            'agrupamento_evolucao'     => $request->get('agrupamento_evolucao', $sessao['agrupamento_evolucao'] ?? 'semana'),
            'comparar_periodo_anterior'=> $request->get('comparar_periodo_anterior', $sessao['comparar_periodo_anterior'] ?? '0'),
            'dias_sem_andamento'       => $request->get('dias_sem_andamento', $sessao['dias_sem_andamento'] ?? '30'),
        ];"""

    new_filtros = """        $filtros = [
            'ano'                      => (int) $request->get('ano', $sessao['ano'] ?? date('Y')),
            'mes'                      => (int) $request->get('mes', $sessao['mes'] ?? date('n')),
            'area'                     => $request->get('area', $sessao['area'] ?? null),
            'tipo_atividade'           => $request->get('tipo_atividade', $sessao['tipo_atividade'] ?? null),
            'status_processo'          => $request->get('status_processo', $sessao['status_processo'] ?? null),
            'agrupamento_evolucao'     => $request->get('agrupamento_evolucao', $sessao['agrupamento_evolucao'] ?? 'semana'),
            'comparar_periodo_anterior'=> $request->get('comparar_periodo_anterior', $sessao['comparar_periodo_anterior'] ?? '0'),
            'dias_sem_andamento'       => $request->get('dias_sem_andamento', $sessao['dias_sem_andamento'] ?? '30'),
        ];"""

    if old_filtros in c:
        c = c.replace(old_filtros, new_filtros, 1)
        print("  ✔ Controller: filtros atualizados (ano/mes, sem responsavel/grupo)")
    else:
        print("  ⚠ Bloco filtros não encontrado no controller")

    open(fp, 'w', encoding='utf-8').write(c)


def patch_service():
    """Ajustar Service para usar ano/mes em vez de periodo"""
    fp = os.path.join(BASE, 'app/Services/ProcessosInternosService.php')
    c = open(fp, 'r', encoding='utf-8').read()

    # Substituir montagem do período: trocar lógica de periodo/data_inicio/data_fim por ano/mes
    old_periodo_block = """        $periodo = $filtros['periodo'] ?? '30';
        $agora = Carbon::now();

        switch ($periodo) {
            case '7':
                $inicio = $agora->copy()->subDays(7)->startOfDay();
                $fim = $agora->copy()->endOfDay();
                break;
            case '15':
                $inicio = $agora->copy()->subDays(15)->startOfDay();
                $fim = $agora->copy()->endOfDay();
                break;
            case 'mes':
                $inicio = $agora->copy()->startOfMonth();
                $fim = $agora->copy()->endOfMonth();
                break;
            case 'trimestre':
                $inicio = $agora->copy()->subMonths(3)->startOfDay();
                $fim = $agora->copy()->endOfDay();
                break;
            case 'custom':
                $inicio = Carbon::parse($filtros['data_inicio'] ?? $agora->copy()->subDays(30))->startOfDay();
                $fim = Carbon::parse($filtros['data_fim'] ?? $agora)->endOfDay();
                break;
            default: // 30
                $inicio = $agora->copy()->subDays(30)->startOfDay();
                $fim = $agora->copy()->endOfDay();
        }"""

    new_periodo_block = """        $ano = (int) ($filtros['ano'] ?? date('Y'));
        $mes = max(1, min(12, (int) ($filtros['mes'] ?? date('n'))));

        $inicio = Carbon::create($ano, $mes, 1)->startOfDay();
        $fim = $inicio->copy()->endOfMonth()->endOfDay();"""

    if old_periodo_block in c:
        c = c.replace(old_periodo_block, new_periodo_block, 1)
        print("  ✔ Service: período → ano/mes")
    else:
        # Tentar encontrar variação
        if "switch ($periodo)" in c:
            # Abordagem regex mais flexível
            pat = r"\$periodo = \$filtros\['periodo'\].*?default:.*?endOfDay\(\);\s*\}"
            match = re.search(pat, c, re.DOTALL)
            if match:
                c = c[:match.start()] + new_periodo_block.lstrip() + c[match.end():]
                print("  ✔ Service: período → ano/mes (regex)")
            else:
                print("  ⚠ Bloco switch(periodo) não casou com regex")
        else:
            print("  ⚠ Bloco período não encontrado no Service")

    # Remover referências a responsavel nos filtros do Service
    # Buscar e remover bloco que filtra por responsavel
    pat_resp = r"        // Filtro: responsável.*?(?=\n        // Filtro:|\n        return)"
    c_new = re.sub(pat_resp, '', c, count=1, flags=re.DOTALL)
    if c_new != c:
        c = c_new
        print("  ✔ Service: filtro responsável removido")

    # Ajustar periodo_anterior para usar mês anterior
    old_anterior = """        // Período anterior (mesmo tamanho, imediatamente antes)
        $duracao = $inicio->diffInDays($fim) + 1;
        $inicioAnterior = $inicio->copy()->subDays($duracao);
        $fimAnterior = $inicio->copy()->subDay()->endOfDay();"""

    new_anterior = """        // Período anterior (mês anterior)
        $inicioAnterior = $inicio->copy()->subMonth()->startOfMonth();
        $fimAnterior = $inicioAnterior->copy()->endOfMonth()->endOfDay();"""

    if old_anterior in c:
        c = c.replace(old_anterior, new_anterior, 1)
        print("  ✔ Service: período anterior → mês anterior")
    else:
        # Variação: pode não ter o texto exato
        if 'inicioAnterior' in c and 'subDays' in c:
            pat_ant = r"        // Período anterior.*?\$fimAnterior = .*?endOfDay\(\);"
            match = re.search(pat_ant, c, re.DOTALL)
            if match:
                c = c[:match.start()] + new_anterior.lstrip() + c[match.end():]
                print("  ✔ Service: período anterior → mês anterior (regex)")

    open(fp, 'w', encoding='utf-8').write(c)


def main():
    print("=" * 60)
    print("PATCH: Filtros Processos Internos — Ano/Mês + Sem Responsável")
    print("=" * 60)

    print("\n[PASSO 0] Backups...")
    backup('resources/views/dashboard/processos-internos/index.blade.php')
    backup('app/Http/Controllers/Dashboard/ProcessosInternosController.php')
    backup('app/Services/ProcessosInternosService.php')

    print("\n[PASSO 1] View — Ano/Mês + remover Responsável...")
    patch_view()

    print("\n[PASSO 2] Controller — filtros ano/mes...")
    patch_controller()

    print("\n[PASSO 3] Service — período ano/mes...")
    patch_service()

    print("\n" + "=" * 60)
    print("PRÓXIMOS PASSOS:")
    print("=" * 60)
    print("""
1. Validar sintaxe:
   php -l app/Http/Controllers/Dashboard/ProcessosInternosController.php
   php -l app/Services/ProcessosInternosService.php

2. Limpar cache:
   php artisan cache:clear && php artisan view:clear && php artisan config:clear

3. Testar no browser:
   https://intranet.mayeradvogados.adv.br/resultados/bsc/processos-internos

4. Se ok, commit:
   git add -A && git commit -m "fix: processos internos filtros Ano/Mes, remove responsavel" && git push
""")


if __name__ == '__main__':
    main()
