const { defineConfig } = require('cypress');

module.exports = defineConfig({
  video: false,
  e2e: {
    baseUrl: 'http://localhost:8080',
    specPattern: 'cypress/e2e/**/*.cy.js',
    supportFile: false,
  },
});
