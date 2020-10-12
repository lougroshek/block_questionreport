<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
  * Block questionreport Report File.
  *
  * @package    block_questionreport
  * @copyright  (C) 2018 Remote Learner.net Inc http://www.remote-learner.net
  */
require_once(dirname(__FILE__).'/../../config.php');
$plugin = 'block_questionreport';

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/blocks/questionreport/report.php');
$PAGE->set_context(context_system::instance());
$header = get_string('reportheader', $plugin);
$PAGE->set_title($header);
$PAGE->set_heading($header);
$PAGE->set_cacheable(true);
$PAGE->navbar->add($header, new moodle_url('/blocks/questionreport/report.php'));
global $COURSE, $CFG, $OUTPUT, $USER, $DB;
require_once($CFG->dirroot.'/blocks/questionreport/locallib.php');

echo $OUTPUT->header();
// Build up the filters
$courselist = block_questionreport_get_courses();
echo html_writer::label(get_string('coursefilter', $plugin), false, array('class' => 'accesshide'));
echo html_writer::select($courselist, "course", 'courseid', get_string("all", $plugin));

$partnerlist = block_questionreport_get_partners();
echo html_writer::label(get_string('partnerfilter', $plugin), false, array('class' => 'accesshide'));
echo html_writer::select($partnerlist, "partner", 'partnerid', get_string("all", $plugin));

$datelist = block_questionreport_get_courses();
echo html_writer::label(get_string('datefilter', $plugin), false, array('class' => 'accesshide'));
echo '<input type="date" id="start-date" name="start_date" />';
echo html_writer::label(get_string('to'), false, array('class' => 'accesshide'));
echo '<input type="date" id="end-date" name="end_date" />';
 
$content = '';
// Get teachers separated by roles.
$context = context_course::instance($COURSE->id);
//$context = cr_get_context(CONTEXT_COURSE, $COURSE->id);
  
$roles = get_config('block_questionreport', 'roles');

if (!empty($roles)) {
    $teacherroles = explode(',', $roles);
    $teachers = get_role_users($teacherroles,
                   $context,
                    true,
                    'ra.id AS raid, r.id AS roleid, r.sortorder, u.id, u.lastname, u.firstname, u.firstnamephonetic,
                            u.lastnamephonetic, u.middlename, u.alternatename, u.picture, u.imagealt, u.email',
                    'r.sortorder ASC, u.lastname ASC, u.firstname ASC');
} else {
   $teachers = array();
}
// Get role names / aliases in course context.
$rolenames = role_get_names($context, ROLENAME_ALIAS, true);

// Get multiple roles config.
$multipleroles = get_config($plugin, 'multipleroles');
// Start teachers list.
$content .= html_writer::start_tag('div', array('class' => 'teachers'));
$questlistsql = "SELECT mq.id, mq.extradata, ms.id surveyid from {questionnaire_survey} ms
                 JOIN {questionnaire_question} mq on mq.surveyid = ms.id
                 WHERE ms.courseid =".$COURSE->id ." and mq.name = 'Course Ratings' ";
$quest = $DB->get_record_sql($questlistsql);
if ($quest) {
    $qid = $quest->id;     
    $surveyid = $quest->surveyid;
    $teacherrole = null;
    $displayedteachers = array();
    $sqlresp = "SELECT COUNT(r.id) crid FROM {questionnaire_response} r
                WHERE r.questionnaireid = ".$surveyid." AND r.complete = 'y'";

    $resp = $DB->get_record_sql($sqlresp);

    $totrespcourse = $resp->crid;
    $totresp = 0;
    $surveysql = "SELECT distinct(ms.id) surveyid from {questionnaire_survey} ms
                    JOIN {questionnaire_question} mq on mq.surveyid = ms.id
                   WHERE mq.name = 'Course Ratings' ";
    $surveys = $DB->get_record_sql($surveysql);
    foreach($surveys as $survey) {
       $sid = $surveyid;
       $sqltot = "SELECT COUNT(r.id) crid FROM {questionnaire_response} r
                   WHERE r.questionnaireid = ".$sid." AND r.complete = 'y'";

       $respsql = $DB->get_record_sql($sqltot);
       $totresp = $respsql->crid + $totresp;
    }

    $content .= html_writer::start_tag('table');
    $content .= html_writer::start_tag('tr');
    $content .= html_writer::start_tag('td');
    $content .= html_writer::end_tag('td');
    $content .= html_writer::start_tag('td');
    $content .= '<b>'.get_string('thiscourse',$plugin).'</b>';
    $content .= html_writer::end_tag('td');
    $content .= html_writer::start_tag('td');
    $content .= '<b>'.get_string('allcourses',$plugin).'</b>';
    $content .= html_writer::end_tag('td');
    $content .= html_writer::end_tag('tr');
    $content .= html_writer::start_tag('td');
    $content .= '<b>'.get_string('surveyresp',$plugin).'</b>';
    $content .= html_writer::end_tag('td');
    $content .= html_writer::start_tag('td');
    $content .= '<b>'.$totrespcourse.'</b>';
    $content .= html_writer::end_tag('td');
    $content .= html_writer::start_tag('td');
    $content .= '<b>'.$totresp.'</b>';
    $content .= html_writer::end_tag('td');
    $content .= html_writer::end_tag('tr');

//      blank line
    $content .= html_writer::start_tag('tr');
    $content .= html_writer::start_tag('td');
    $content .= html_writer::end_tag('td');
    $content .= html_writer::start_tag('td');
    $content .= html_writer::end_tag('td');
    $content .= html_writer::start_tag('td');
    $content .= html_writer::end_tag('td');
    $content .= html_writer::end_tag('tr');

    $content .= html_writer::start_tag('tr');
    $content .= html_writer::start_tag('td');
    $content .= '<b>'.get_string('session',$plugin).'</b>';
    $content .= html_writer::end_tag('td');
    $content .= html_writer::start_tag('td');
    $content .= '<b>'.get_string('thiscourse',$plugin).'</b>';
    $content .= html_writer::end_tag('td');
    $content .= html_writer::start_tag('td');
    $content .= '<b>'.get_string('allcourses',$plugin).'</b>';
    $content .= html_writer::end_tag('td');
    $content .= html_writer::end_tag('tr');
    // Get the questions
    $questlistsql = "SELECT mq.id, mq.extradata, ms.id surveyid from {questionnaire_survey} ms
                      JOIN {questionnaire_question} mq on mq.surveyid = ms.id
                     WHERE ms.courseid =".$COURSE->id ." and mq.name = 'Course Ratings' ";
    $quest = $DB->get_record_sql($questlistsql);
    $qid = $quest->id;
    $extra = $quest->extradata;
    $choices = $DB->get_records('questionnaire_quest_choice', array('question_id' => $qid));
    foreach($choices as $choice) {
       $choicename = $choice->content;
       $curtotal = 0;
       $curtotal = block_questionreport_get_choice_current($choice->id);
       $grandtotal = block_questionreport_get_choice_all($choicename);

       $content .= html_writer::start_tag('tr');
       $content .= html_writer::start_tag('td');
       $content .= $choicename;
       $content .= html_writer::end_tag('td');
       $content .= html_writer::start_tag('td');
       $content .= $curtotal;
       $content .= html_writer::end_tag('td');
       $content .= html_writer::start_tag('td');
       $content .= $grandtotal;
       $content .= html_writer::end_tag('td');
       $content .= html_writer::end_tag('tr');
    }

    $content .= html_writer::end_tag('table');
}
echo $content;  
echo $OUTPUT->footer();
