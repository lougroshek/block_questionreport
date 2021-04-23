# Questionnaire Report Block

- This block reports individualized and averaged scores for instructor roles in the settings.
- The block draws on questions from questionnaire modules with a special set of tagged questions. The designated questionnaire is also tagged, and that tag is passed to the block in the block admin settings.
- The questionnaire used with this block has been slightly modified to include a special rate question type that displays a list of all instructurs with the designated role.

The goal of the project is to do some reporting based on surveys done in each course.

There are a number of types of users in the system:

- Students/Participants – those people who typically take the course;
- Instructor – those people who teach the course;
- Lead Facilitator – essentially the main instructor in the course. This is a custom role.
- Supervisor – essentially a manager – this is a custom role.

Any role can be assigned as “administrator” within the block settings. An administrator can see all types
of reports.

Besides moodle courses, the system can import survey results based on a spreadsheet using the
import.php routine which will be described later in this document.

The project has 2 modules:

- mod questionnaire – this is a standard questionnaire module with an instructor rank question type added;
  The instructor question type allows for a rank of users that have the role of teachers in course.
  Repo: https://github.com/lougroshek/mod_questionnaire.git – branch wip-MASTER
  Look for changes from the main (core branch) – by looking for questionnaire_quest_ins table.

## block_questionnaire

The repo is https://github.com/shediac2/block_questionreport.git – branch wip-FINAL
It requires that a role be created called leadfacilitator.
this is the main part of the project. It has 6 main parts:

### Display

When the block is added to a course, it displays the satisfaction ranking of two questions from the survey. The question names are: facilitator_rate_content, and facilitator_rate_community.

The code looks for the survey with those questions and checks the number of results with a ranking of 4
or 5 and does a percentage of the total number of questions answered with 4 or 5 as a ranking against the total number of questions answered. For example if 5 questions are answered, and 3 have a ranking of 4 or 5 then the result would be 60%.

It does this for each of those questions.

If the user is a student, the results do not display. If the user is a lead facilitator only the system displays the percentage results and has a button with access survey reports (called the lead facilitator reports).

If the user is an admin (either a site admin in moodle, or a user with an admin role as defined in the blocks settings) then 2 other buttons display with admin reports, and charts. The role must be checked based the course settings and on the global role settings.

### Lead facilitator report

This report displays the satisfaction for the two questions for
facilitator_rate_content, and facilitator_rate_community – questions. It displays the results for the course, and also for all courses.

If the user is a Lead facilitator it displays the results for only those courses where the user is a lead
facilitator.

The report can also be filtered other courses (based on the drop down list of courses – both Moodle and
Non Moodle), date range (when the survey was taken), facilitators, and partners;
The courses list in the report has the course ids, prefix by M for Moodle and A for Non Moodle courses.
The system also displays the results of some ranking questions for the course. It only displays the NPS
question (How likely are you to recommend this professional learning to a colleague or friend?) only is
the user is an administrator.
Last the system displays a word cloud with the top words entered in the free form text questions.
The report can be downloaded as a pdf.

### Admin report

This a detailed report that is displayed with a specific question from the survey filtering
by many of the same items in the lead facilitator report. If you pick all, then it can only be viewed by
downloading the results as a csv file.

### Chart

The system will generate a bar chart of Bar Chart: Percent of responses 4 and 5 by Partner Site.

### Feedback

The system has 3 feedback reports created

- report1 = 'Participant feedback, by month
- report2 = 'Participant feedback, by portfolio
- report3 = 'Participant feedback, by partner site.

## block settings

There are several settings within this block:

- _admin_ settings this allows the site admin to select which user roles are treated as administrators within the block.
- _tag_ value end of course survey – a questionnaire is only included with the tag questionnaire contains the tag value selected here in the survey;
- _partner_ field – select the field from the list available for the custom menu course field that stores the partner for the course.
- _portfolio_ field – select the field from the list available for the custom menu course field that stores the potfolio for the course

## Import Non Moodle courses

The import of non moodle courses is run from the command line using `import.php`.

An example of the spreadsheet used as an example is attached:

SY20-21 Teaching Lab End-of-Course Feedback Survey (Responses).xlsx

The tables it uses are defined in the hamy.sql file (attached). This script will load the content from the production site (non moodle courses) into the database.

To convert this into a file that can be read by the import process, do a search and replace in all columns for commas (,) and replace them with dashes, then do an export to csv. Name the file import.csv. It should be in the same folder as the block.

The import can be run from the web site {url}\blocks\questionreport\import.php

The file reads the csv file into mdl_local_teaching_survey table.

- It checks for teachers in the mdl_local_teaching_teacher and adds them as needed;
- it checks for portfolios in the mdl_local_teaching_port table and adds them as needed;
- it checks for courses in the mdl_local_teaching_course table and adds them as needed.
