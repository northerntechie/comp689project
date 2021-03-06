@mod @mod_opendsa
Feature: Limit opendsa responses
  In order to restrict students from selecting a response more than a specified number of times
  As a teacher
  I need to limit the opendsa responses

  Scenario: Limit the number of responses allowed for a opendsa activity and verify the result as students
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
      | student2 | Student | 2 | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |
      | student2 | C1 | student |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "OpenDSA" to section "1" and I fill the form with:
      | OpenDSA name | OpenDSA name |
      | Description | OpenDSA Description |
      | Limit the number of responses allowed | 1 |
      | option[0] | Option 1 |
      | limit[0] | 1 |
      | option[1] | Option 2 |
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I choose "Option 1" from "OpenDSA name" opendsa activity
    Then I should see "Your selection: Option 1"
    And I should see "Your opendsa has been saved"
    And I log out
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "OpenDSA name"
    And I should see "Option 1 (Full)"
    And the "opendsa_1" "radio" should be disabled
