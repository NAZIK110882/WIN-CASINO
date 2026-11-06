/// <reference types="cypress" />

describe('WIN-CASINO E2E - Slots UI flows', () => {
  beforeEach(() => {
    // reset any state between tests if needed
  });

  it('Displays balance on slots page load', () => {
    cy.intercept('GET', '**/api/v1/user', {
      statusCode: 200,
      body: { success: true, username: 'tester', balance: '123.45' }
    }).as('getUser');

    cy.visit('/slots.html');

    cy.wait('@getUser');

    cy.get('#balanceDisplay').should('contain', '123.45');
  });

  it('Successful spin updates slots, balance and shows jackpot message for triple 7', () => {
    // stub user balance
    cy.intercept('GET', '**/api/v1/user', { success: true, username: 'tester', balance: '200.00' }).as('getUser');

    // Stub the API response so this scenario checks the UI independently of DB state.
    cy.intercept({ method: 'POST', url: '**/api/v1/spin' }, (req) => {
      // simulate response for bet 50
      req.reply({
        statusCode: 200,
        body: {
          success: true,
          result: ['7️⃣','7️⃣','7️⃣'],
          bet: 50,
          win: 2500,
          new_balance: 2700
        }
      });
    }).as('spin');

    cy.visit('/slots.html');
    cy.wait('@getUser');

    // ensure bet select has value 50 (default in UI)
    cy.get('#betSelect').should('have.value', '50');

    cy.get('#spinBtn').click();

    cy.wait('@spin');

    cy.get('#slotsDisplay').should('contain', '7️⃣');
    cy.get('#balanceDisplay').should('contain', '2700.00');
    cy.get('#messageDisplay').should(($message) => {
      expect($message.text()).to.match(/ДЖЕКПOT|Виграш/);
    });
  });

  it('Spin with insufficient funds shows error message and keeps balance unchanged', () => {
    cy.intercept('GET', '**/api/v1/user', { success: true, username: 'tester', balance: '10.00' }).as('getUser');

    cy.intercept({ method: 'POST', url: '**/api/v1/spin' }, {
      statusCode: 422,
      body: { success: false, error: { code: 'insufficient_funds', message: 'Недостатньо коштів' } }
    }).as('spinInsufficient');

    cy.visit('/slots.html');
    cy.wait('@getUser');

    // Choose a large bet to cover the insufficient-funds response.
    cy.get('#betSelect').select('100');

    cy.get('#spinBtn').click();

    cy.wait('@spinInsufficient');

    cy.get('#messageDisplay').should('contain', 'Недостатньо коштів');
    cy.get('#balanceDisplay').should('contain', '10.00');
  });
});
