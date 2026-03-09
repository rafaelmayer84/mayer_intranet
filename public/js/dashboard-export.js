(function () {
  'use strict';

  // Placeholder para exportação da Visão Gerencial.
  // Mantido propositalmente simples: evita 404 e facilita futura implementação.

  window.__DASHBOARD_EXPORT_LOADED__ = true;
  window.__DASHBOARD_EXPORT_LOADED_AT__ = new Date().toISOString();

  try {
    console.info('[DashboardExport] carregado em', window.__DASHBOARD_EXPORT_LOADED_AT__);
  } catch (_) {}
})();
