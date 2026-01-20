/**
 * Menu Mobile Responsivo - Intranet Mayer
 * Gerencia abertura/fechamento do menu em dispositivos móveis
 */

class MobileMenu {
  constructor() {
    this.sidebar = null;
    this.hamburgerBtn = null;
    this.overlay = null;
    this.init();
  }

  /**
   * Inicializa o sistema de menu mobile
   */
  init() {
    // Aguardar DOM estar pronto
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => this.setup());
    } else {
      this.setup();
    }
  }

  /**
   * Configura os elementos do menu
   */
  setup() {
    // Procurar elementos
    this.sidebar = document.querySelector('.sidebar') || document.querySelector('aside');
    
    if (!this.sidebar) {
      console.warn('Sidebar não encontrada');
      return;
    }

    // Criar hamburger button se não existir
    this.hamburgerBtn = document.getElementById('hamburger-btn');
    if (!this.hamburgerBtn) {
      this.createHamburgerButton();
    }

    // Criar overlay se não existir
    this.overlay = document.querySelector('.sidebar-overlay');
    if (!this.overlay) {
      this.createOverlay();
    }

    // Adicionar event listeners
    this.attachEventListeners();
  }

  /**
   * Cria o botão hamburger
   */
  createHamburgerButton() {
    const header = document.querySelector('header') || document.querySelector('nav');
    
    if (!header) {
      console.warn('Header não encontrado para inserir hamburger button');
      return;
    }

    this.hamburgerBtn = document.createElement('button');
    this.hamburgerBtn.id = 'hamburger-btn';
    this.hamburgerBtn.className = 'hamburger-btn';
    this.hamburgerBtn.setAttribute('aria-label', 'Abrir menu');
    this.hamburgerBtn.setAttribute('aria-expanded', 'false');
    
    this.hamburgerBtn.innerHTML = `
      <span></span>
      <span></span>
      <span></span>
    `;

    // Inserir antes do último elemento do header (geralmente onde fica o logout)
    const lastChild = header.lastElementChild;
    if (lastChild) {
      header.insertBefore(this.hamburgerBtn, lastChild);
    } else {
      header.appendChild(this.hamburgerBtn);
    }
  }

  /**
   * Cria o overlay semi-transparente
   */
  createOverlay() {
    this.overlay = document.createElement('div');
    this.overlay.className = 'sidebar-overlay';
    document.body.appendChild(this.overlay);
  }

  /**
   * Adiciona event listeners
   */
  attachEventListeners() {
    // Botão hamburger
    if (this.hamburgerBtn) {
      this.hamburgerBtn.addEventListener('click', () => this.toggleMenu());
    }

    // Overlay
    if (this.overlay) {
      this.overlay.addEventListener('click', () => this.closeMenu());
    }

    // Links do menu
    this.attachSidebarLinkListeners();

    // Tecla Escape
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        this.closeMenu();
      }
    });

    // Redimensionamento da janela
    window.addEventListener('resize', () => {
      if (window.innerWidth > 768) {
        this.closeMenu();
      }
    });
  }

  /**
   * Adiciona listeners aos links do sidebar
   */
  attachSidebarLinkListeners() {
    if (!this.sidebar) return;

    const links = this.sidebar.querySelectorAll('a');
    links.forEach(link => {
      link.addEventListener('click', () => {
        // Fechar menu ao clicar em um link (apenas em mobile)
        if (window.innerWidth <= 768) {
          this.closeMenu();
        }
      });
    });
  }

  /**
   * Alterna o menu (abre/fecha)
   */
  toggleMenu() {
    const isOpen = this.sidebar.classList.contains('active');
    if (isOpen) {
      this.closeMenu();
    } else {
      this.openMenu();
    }
  }

  /**
   * Abre o menu
   */
  openMenu() {
    if (!this.sidebar || !this.hamburgerBtn) return;

    // Adicionar classe active ao sidebar
    this.sidebar.classList.add('active');
    
    // Adicionar classe active ao hamburger button
    this.hamburgerBtn.classList.add('active');
    
    // Mostrar overlay
    if (this.overlay) {
      this.overlay.classList.add('active');
    }

    // Atualizar aria-expanded
    this.hamburgerBtn.setAttribute('aria-expanded', 'true');

    // Prevenir scroll do body
    document.body.style.overflow = 'hidden';

    // Disparar evento customizado
    window.dispatchEvent(new CustomEvent('menuopen'));
  }

  /**
   * Fecha o menu
   */
  closeMenu() {
    if (!this.sidebar || !this.hamburgerBtn) return;

    // Remover classe active do sidebar
    this.sidebar.classList.remove('active');
    
    // Remover classe active do hamburger button
    this.hamburgerBtn.classList.remove('active');
    
    // Esconder overlay
    if (this.overlay) {
      this.overlay.classList.remove('active');
    }

    // Atualizar aria-expanded
    this.hamburgerBtn.setAttribute('aria-expanded', 'false');

    // Restaurar scroll do body
    document.body.style.overflow = '';

    // Disparar evento customizado
    window.dispatchEvent(new CustomEvent('menuclose'));
  }

  /**
   * Verifica se o menu está aberto
   */
  isOpen() {
    return this.sidebar && this.sidebar.classList.contains('active');
  }

  /**
   * Obtém o estado do menu
   */
  getState() {
    return {
      isOpen: this.isOpen(),
      sidebar: this.sidebar,
      hamburgerBtn: this.hamburgerBtn,
      overlay: this.overlay
    };
  }
}

// Inicializar quando o DOM estiver pronto
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    new MobileMenu();
  });
} else {
  new MobileMenu();
}

// Exportar para uso em outros scripts
window.MobileMenu = MobileMenu;
