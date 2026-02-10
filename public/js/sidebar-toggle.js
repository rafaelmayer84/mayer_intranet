/**
 * Sidebar Toggle System - Intranet Mayer Advogados
 * Sistema de retração manual + automática do menu lateral
 * Mantém ícones visíveis quando retraído
 * Estado persistido em localStorage
 */

(function() {
    'use strict';

    // Seletores
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebar-toggle-btn');
    const mainContent = document.getElementById('main-content');
    const logo = document.querySelector('.sidebar-logo');
    const logoText = document.querySelector('.sidebar-logo-text');
    const menuTexts = document.querySelectorAll('.menu-text');
    const menuArrows = document.querySelectorAll('.menu-arrow');
    
    // Estado persistido
    const STORAGE_KEY = 'sidebar_collapsed';
    
    /**
     * Verifica se o menu deve estar retraído
     */
    function shouldCollapse() {
        // 1. Verifica preferência salva do usuário
        const savedState = localStorage.getItem(STORAGE_KEY);
        if (savedState !== null) {
            return savedState === 'true';
        }
        
        // 2. Retrai automaticamente em telas < 1024px
        return window.innerWidth < 1024;
    }
    
    /**
     * Aplica o estado de retração
     */
    function applyCollapseState(collapsed) {
        if (collapsed) {
            sidebar.classList.add('sidebar-collapsed');
            mainContent.classList.add('main-expanded');
            toggleBtn.innerHTML = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path></svg>';
            
            // Esconde textos
            menuTexts.forEach(text => text.classList.add('hidden'));
            menuArrows.forEach(arrow => arrow.classList.add('hidden'));
            if (logoText) logoText.classList.add('hidden');
        } else {
            sidebar.classList.remove('sidebar-collapsed');
            mainContent.classList.remove('main-expanded');
            toggleBtn.innerHTML = '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path></svg>';
            
            // Mostra textos
            menuTexts.forEach(text => text.classList.remove('hidden'));
            menuArrows.forEach(arrow => arrow.classList.remove('hidden'));
            if (logoText) logoText.classList.remove('hidden');
        }
        
        // Salva estado
        localStorage.setItem(STORAGE_KEY, collapsed.toString());
    }
    
    /**
     * Toggle manual do usuário
     */
    function toggleSidebar() {
        const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
        applyCollapseState(!isCollapsed);
    }
    
    /**
     * Inicialização
     */
    function init() {
        // Aplica estado inicial
        applyCollapseState(shouldCollapse());
        
        // Event listener do botão
        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggleSidebar);
        }
        
        // Reaplica estado em resize (apenas se não houver preferência salva)
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                // Só reaplica automaticamente se usuário não definiu preferência
                if (localStorage.getItem(STORAGE_KEY) === null) {
                    applyCollapseState(window.innerWidth < 1024);
                }
            }, 250);
        });
    }
    
    // Aguarda DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
