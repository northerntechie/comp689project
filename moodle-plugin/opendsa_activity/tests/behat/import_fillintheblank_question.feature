@mod @mod_opendsa_activity
Feature: In a opendsa_activity activity, teacher can import blackboard fill in the blank question
  As a teacher
  I need to import a fill in the blank question made in Blackboard in a opendsa_activity

  @javascript @_file_upload
  Scenario: Import fill in the blank question in a opendsa_activity
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
    When I am on "Course 1" course homepage with editing mode on
    And I add a "Lesson" to section "1" and I fill the form with:
      | Name | Test opendsa_activity name |
      | Description | Test opendsa_activity description |
    And I follow "Test opendsa_activity name"
    And I follow "Import questions"
    And I set the field "File format" to "Blackboard"
    And I upload "mod/opendsa_activity/tests/fixtures/sample_blackboard_fib_qti.dat" file to "Upload" filemanager
    And I press "Import"
    Then I should see "Importing 1 questions"
    And I should see "Name an amphibian: __________"
    And I press "Continue"
    And I should not see "__________"
    And I should not see "Your answer"
    And I set the field "id_answer" to "frog"
    And I press "Submit"
    And I should see "Your answer : frog"
    And I should see "A frog is an amphibian"
    And I press "Continue"
    And I should see "Congratulations - end of opendsa_activity reached"
