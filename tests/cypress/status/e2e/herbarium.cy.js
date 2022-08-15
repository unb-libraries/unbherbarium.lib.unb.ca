const host = 'https://unbherbarium.lib.unb.ca'
describe('Connell Memorial Herbarium', {baseUrl: host, groups: ['sites']}, () => {

  context('Specimen search', {baseUrl: host}, () => {
    beforeEach(() => {
      cy.visit('/specimen/search')
      cy.title()
        .should('contain', 'Connell Memorial Herbarium')
    })

    specify('Default search should find 25+ results', () => {
      cy.get('.region-content form')
        .submit()
      cy.get('tr')
        .should('have.lengthOf.at.least', 25)
    })

  })

})
