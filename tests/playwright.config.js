const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './specs',
  timeout: 30000,
  // Pas de parallélisme : tous les tests tournent contre la même instance
  // PrestaShop réelle et mutable (config partagée, cache CCC partagé) — un
  // run en parallèle fait courir aux tests le risque de se marcher dessus
  // (ex. withConfig() d'un fichier de specs qui change une valeur pendant
  // qu'un autre fichier est en plein test), constaté concrètement avec
  // cookie-consent.spec.js échouant de façon intermittente uniquement en
  // suite complète.
  fullyParallel: false,
  workers: 1,
  reporter: 'list',
  use: {
    baseURL: process.env.NAVI_TEST_BASE_URL || 'http://localhost:8080',
    viewport: { width: 1440, height: 900 },
  },
});
