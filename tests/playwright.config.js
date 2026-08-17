const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './specs',
  timeout: 30000,
  fullyParallel: true,
  reporter: 'list',
  use: {
    baseURL: process.env.NAVI_TEST_BASE_URL || 'http://localhost:8080',
    viewport: { width: 1440, height: 900 },
  },
});
