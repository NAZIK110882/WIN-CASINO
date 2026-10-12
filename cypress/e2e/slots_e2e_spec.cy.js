/// <reference types="cypress" />

describe('WIN-CASINO E2E - Slots UI flows', () => {
  beforeEach(() => {
    // reset any state between tests if needed
  });

  it('Displays balance on slots page load', () => {
    cy.intercept('GET', '**/api/user.php', {
      statusCode: 200,
      body: { success: true, username: 'tester', balance: '123.45' }
    }).as('getUser');

    cy.visit('/slots.php');

    cy.wait('@getUser');

    cy.get('#balanceDisplay').should('contain', '123.45');
  });

  it('Successful spin updates slots, balance and shows jackpot message for triple 7', () => {
    // stub user balance
    cy.intercept('GET', '**/api/user.php', { success: true, username: 'tester', balance: '200.00' }).as('getUser');

    // stub spin response for bet=50
    cy.intercept({ method: 'GET', url: '**/api/spin.php*' }, (req) => {
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

    cy.visit('/slots.php');
    cy.wait('@getUser');

    // ensure bet select has value 50 (default in UI)
    cy.get('#betSelect').should('have.value', '50');

    cy.get('#spinBtn').click();

    cy.wait('@spin');

    cy.get('#slotsDisplay').should('contain', '7️⃣');
    cy.get('#balanceDisplay').should('contain', '2700.00');
    cy.get('#messageDisplay').should('contain', 'ДЖЕКПOT').or('contain', 'Виграш');
  });

  it('Spin with insufficient funds shows error message and keeps balance unchanged', () => {
    cy.intercept('GET', '**/api/user.php', { success: true, username: 'tester', balance: '10.00' }).as('getUser');

    cy.intercept({ method: 'GET', url: '**/api/spin.php*' }, {
      statusCode: 200,
      body: { error: 'Недостатньо коштів' }
    }).as('spinInsufficient');

    cy.visit('/slots.php');
    cy.wait('@getUser');

    // choose a large bet to trigger insufficient funds scenario in UI
    cy.get('#betSelect').select('100');

    cy.get('#spinBtn').click();

    cy.wait('@spinInsufficient');

    cy.get('#messageDisplay').should('contain', 'Помилка: Недостатньо коштів');
    cy.get('#balanceDisplay').should('contain', '10.00');
  });
});
