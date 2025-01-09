describe('Test that console command user', () => {
  it('can list users', () => {
    cy.exec(`php ${Cypress.env('cmsPath')}/cli/joomla.php user:list`)
      .its('stdout')
      .should('contain', `${Cypress.env('username')}`);
  });
  it('can add a user', () => {
    const para = '--username=test --name=test --password=123456789012 --email=test@530.test --usergroup=Manager -n';
    cy.exec(`php ${Cypress.env('cmsPath')}/cli/joomla.php user:add ${para}`)
      .its('stdout')
      .should('contain', 'User created!');
  });
  it('can delete a user', () => {
    const para = '--username=test -n';
    cy.exec(`php ${Cypress.env('cmsPath')}/cli/joomla.php user:delete ${para}`)
      .its('stdout')
      .should('contain', 'User test deleted!');
  });
});
