@api
Feature: Herbarium Specimen
  In order to know that a herbarium specimen works as intended
  As a website user
  I need to be able to search for and see the title of a herbarium specimen.

  @api
  Scenario: Herbarium specimen title builder
    Given "taxon_rank" terms:
      | name       |
      | Family     |
      | Genus      |
      | Species    |
      | ssp.       |
    And "herbarium_specimen_taxonomy" terms:
      | name                | field_dwc_taxonrank    | parent   | field_dwc_scientificnameauthor   |
      | Abampusa            | Family                 |          |                                  |
      | Biggusum            | Genus                  | Bumpusa  | Goofy                            |
      | Bimora              | Species                | Biggusum | Daisy                            |
      | Loscuma             | ssp.                   | Bimora   | Carter                           |
    And  "herbarium_specimen" content:
      | title        | field_taxonomy_tid     |
      | Jerfer       | Loscuma                |
    And I am logged in as a user with the "administrator" role
    When I go to "admin/content"
    And I should see "Biggusum Goofy Bimora Daisy ssp. Loscuma Carter"

  Scenario: Herbarium specimen search
    Given "taxon_rank" terms:
      | name       |
      | Family     |
      | Genus      |
      | Species    |
      | ssp.       |
    And "herbarium_specimen_collector" terms:
      | name           |
      | Queen Anne     |
    And "herbarium_specimen_taxonomy" terms:
      | name                | field_dwc_taxonrank    | parent   | field_dwc_scientificnameauthor   |
      | Abampusa            | Family                 |          |                                  |
      | Biggusum            | Genus                  | Bumpusa  | Goofy                            |
      | Bimora              | Species                | Biggusum | Daisy                            |
      | Loscuma             | ssp.                   | Bimora   | Carter                           |
    And  "herbarium_specimen" content:
      | title        | field_taxonomy_tid     | field_collector_tid   |
      | Jerfer       | Loscuma                | Queen Anne            |
    When I visit "/specimen/search"
    And I fill in "Scientific Name" with "Loscuma"
    Then I press "Apply search"
    Then I should see "Queen Anne"
