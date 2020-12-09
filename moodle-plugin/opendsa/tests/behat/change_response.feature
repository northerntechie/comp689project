@mod @mod_opendsa
Feature: Teacher can choose whether to allow students to change their opendsa response
  In order to allow students to change their opendsa
  As a teacher
  I need to enable the option to change the opendsa

  Scenario: Add a opendsa activity and complete the activity as a student
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
    And I add a "OpenDSA" to section "1" and I fill the form with:
      | OpenDSA name | OpenDSA name |
      | Description | OpenDSA Description |
      | Allow opendsa to be updated | No |
      | option[0] | Option 1 |
      | option[1] | Option 2 |
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I choose "Option 1" from "OpenDSA name" opendsa activity
    Then I should see "Your selection: Option 1"
    And I should see "Your opendsa has been saved"
    And "Save my opendsa" "button" should not exist
    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "OpenDSA name"
    And I follow "Edit settings"
    And I set the following fields to these values:
      | Allow opendsa to be updated | Yes |
    And I press "Save and display"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "OpenDSA name"
    And I should see "Your selection: Option 1"
    And "Save my opendsa" "button" should exist
    And "Remove my opendsa" "link" should exist
    And I set the field "Option 2" to "1"
    And I press "Save my opendsa"
    And I should see "Your opendsa has been saved"
    And I should see "Your selection: Option 2"
