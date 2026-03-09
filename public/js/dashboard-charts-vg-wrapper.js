(function(){
  try {
    if (window.location && (window.location.pathname || '').indexOf('/visao-gerencial') !== -1) {
      // Na Visão Gerencial, os gráficos são renderizados pelo JS inline do Blade.
      // Não executar bootstrap externo aqui para evitar conflito com Chart.js.
      return;
    }
  } catch(e) {}
})();
