@mod @mod_opendsa
Feature: A student can see how the results of the opendsa activity will be published
  In order to put my mind at ease when it comes to answering a opendsa
  As a student
  I need to learn how my opendsa will be handled and published to the other course participants.

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on

  Scenario: Results will not be published to the students
    Given I add a "OpenDSA" to section "1" and I fill the form with:
      | OpenDSA name | OpenDSA 1 |
      | Description | OpenDSA Description |
      | Publish results | Do not publish results to students |
      | option[0] | Option 1 |
      | option[1] | Option 2 |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I follow "OpenDSA 1"
    Then I should see "The results of this activity will not be published after you answer."

  Scenario: Full results will be shown to the students after they answer
    Given I add a "OpenDSA" to section "1" and I fill the form with:
      | OpenDSA name | OpenDSA 1 |
      | Description | OpenDSA Description |
      | option[0] | Option 1 |
      | option[1] | Option 2 |
      | Publish results | Show results to students after they answer |
      | Privacy of results | Publish full results, showing names and their opendsas |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I follow "OpenDSA 1"
    Then I should see "Full results, showing everyone's opendsas, will be published after you answer."

  Scenario: Anonymous results will be shown to students after they answer
    Given I add a "OpenDSA" to section "1" and I fill the form with:
      | OpenDSA name | OpenDSA 1 |
      | Description | OpenDSA Description |
      | option[0] | Option 1 |
      | option[1] | Option 2 |
      | Publish results | Show results to students after they answer |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I follow "OpenDSA 1"
    Then I should see "Anonymous results will be published after you answer."

  Scenario: Full results will be shown to students only after the opendsa is closed
    Given I add a "OpenDSA" to section "1" and I fill the form with:
      | OpenDSA name | OpenDSA 1 |
      | Description | OpenDSA Description |
      | Publish results | Show results to students only after the opendsa is closed |
      | Privacy of results | Publish full results, showing names and their opendsas |
      | option[0] | Option 1 |
      | option[1] | Option 2 |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I follow "OpenDSA 1"
    Then I should see "Full results, showing everyone's opendsas, will be published after the activity is closed."

  Scenario: Anonymous results will be shown to students only after the opendsa is closed
    Given I add a "OpenDSA" to section "1" and I fill the form with:
      | OpenDSA name | OpenDSA 1 |
      | Description | OpenDSA Description |
      | Publish results | Show results to students only after the opendsa is closed |
      | option[0] | Option 1 |
      | option[1] | Option 2 |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I follow "OpenDSA 1"
    Then I should see "Anonymous results will be published after the activity is closed."

  Scenario: Results will always be shown to students
    Given I add a "OpenDSA" to section "1" and I fill the form with:
      | OpenDSA name | OpenDSA 1 |
      | Description | OpenDSA Description |
      | option[0] | Option 1 |
      | option[1] | Option 2 |
      | Publish results | Always show results to students |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I follow "OpenDSA 1"
    Then I should not see "Full results, showing everyone's opendsas, will be published after you answer."
    And I should not see "Full results, showing everyone's opendsas, will be published after the activity is closed."
    And I should not see "Anonymous results will be published after you answer."
    And I should not see "Anonymous results will be published after the activity is closed."
    And I should not see "The results of this activity will not be published after you answer."
