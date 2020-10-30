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
  */
require_once(dirname(__FILE__).'/../../config.php');
$cid          = optional_param('cid', 0, PARAM_INT);// Course ID.
$action       = optional_param('action', 'view', PARAM_ALPHAEXT);
$start_date   = optional_param('start_date', '0', PARAM_RAW);
$end_date     = optional_param('end_date', '0', PARAM_RAW);
$partner      = optional_param('partner', '', PARAM_RAW);
$questionid   = optional_param('question', 0, PARAM_INT);

$plugin = 'block_questionreport';
// Require the javascript for wordcloud.
$PAGE->requires->js('/blocks/questionreport/js/wordCloud2.js');
$PAGE->set_pagelayout('standard');
$PAGE->set_url('/blocks/questionreport/report.php');
$PAGE->set_context(context_system::instance());
$header = get_string('reportheader', $plugin);
$PAGE->set_title($header);
$PAGE->set_heading($header);
$PAGE->set_cacheable(true);
$PAGE->navbar->add($header, new moodle_url('/blocks/questionreport/report.php'));

global $CFG, $OUTPUT, $USER, $DB;
require_once($CFG->dirroot.'/blocks/questionreport/locallib.php');
require_login($cid);
global $COURSE;
echo $OUTPUT->header();
// Build up the filters
$courselist = block_questionreport_get_courses();
echo html_writer::start_tag('h2');
echo get_string('filters', $plugin);
echo html_writer::end_tag('h2');
echo "<form class=\"questionreportform\" action=\"$CFG->wwwroot/blocks/questionreport/report.php\" method=\"get\">\n";
echo "<input type=\"hidden\" name=\"action\" value=\"view\" />\n";

echo html_writer::label(get_string('coursefilter', $plugin), false, array('class' => 'accesshide'));
echo html_writer::select($courselist,"cid",$cid, false);

$partnerlist = block_questionreport_get_partners_list();

echo html_writer::label(get_string('partnerfilter', $plugin), false, array('class' => 'accesshide'));
echo html_writer::select($partnerlist, "partner", $partner, get_string("all", $plugin));

// Date select.
echo html_writer::start_tag('div', array('class' => 'date-input-group'));
echo html_writer::label(get_string('datefilter', $plugin), false, array('class' => 'accesshide'));
echo '<input type="date" id="start-date" name="start_date" value="'.$start_date.'"/>';
echo html_writer::label(get_string('to'), false, array('class' => 'inline'));
echo '<input type="date" id="end-date" name="end_date" value="'.$end_date .'"/>';
echo '<input type="submit" class="btn btn-primary btn-submit" value="'.get_string('getthesurveys', $plugin).'" />';
echo '</form>';
echo html_writer::end_tag('div');
 
$content = '';
// Get teachers separated by roles.
$context = context_course::instance($COURSE->id);
  
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

// Get the tags list.
$tagvalue = get_config($plugin, 'tag_value');
$tagid = $DB->get_field('tag', 'id', array('name' => $tagvalue));
$moduleid = $DB->get_field('modules', 'id', array('name' => 'questionnaire'));
$sqlcourse = "SELECT m.course, m.id, m.instance
               FROM {course_modules} m
               JOIN {tag_instance} ti on ti.itemid = m.id
              WHERE m.module = ".$moduleid. "
               AND ti.tagid = ".$tagid . "
               AND m.course = ".$cid . "
               AND m.deletioninprogress = 0";

$surveys = $DB->get_record_sql($sqlcourse);
if (!$surveys) {
    echo 'not a valid survey';
    echo $OUTPUT->footer();
    exit();        
}
$surveyid = $surveys->instance;

// Get the survey results from this course.
$displayedteachers = array();
$sqlresp = "SELECT COUNT(r.id) crid FROM {questionnaire_response} r
             WHERE r.questionnaireid = ".$surveyid." AND r.complete = 'y'";

$paramscourse = array();
if ($start_date > 0) {
    $std = strtotime($start_date);
    $sqlresp = $sqlresp . " AND submitted >= :std";
    $paramscourse['std'] = $std;
}

if ($end_date > 0) {
    $endtd = strtotime($end_date);
    $sqlresp = $sqlresp. " AND submitted <= :endtd";
    $paramscourse['endtd'] = $endtd;
}

$resp = $DB->get_record_sql($sqlresp, $paramscourse);

$totrespcourse = $resp->crid;

// Get the total responses.
$partnersql = '';
if ($partner > '') {
    $comparevalue = $DB->sql_compare_text($partner);
    $comparevalue = $comparevalue + 1;
    $partnerid = get_config($plugin, 'partnerfield');
    $partnersql = 'JOIN {customfield_data} cd ON cd.instanceid = m.course AND cd.fieldid = '.$partnerid .' AND cd.value = '.$comparevalue;
}
    
$totresp = 0;
$sqlcourses = "SELECT m.course, m.id, m.instance
               FROM {course_modules} m
               JOIN {tag_instance} ti on ti.itemid = m.id ".$partnersql. " 
              WHERE m.module = ".$moduleid. "
               AND ti.tagid = ".$tagid . "
               AND m.deletioninprogress = 0";

$sqltot = "SELECT COUNT(r.id) crid ";
$fromtot = " FROM {questionnaire_response} r ";
$wheretot = "WHERE r.questionnaireid = :questionnaireid AND r.complete = 'y' ";
$paramstot = array();
if ($start_date > 0) {
    $std = strtotime($start_date);
    $wheretot = $wheretot . "AND submitted >= :std";
    $paramstot['std'] = $std;
}

if ($end_date > 0) {
    $endtd = strtotime($end_date);
    $wheretot = $wheretot . "AND submitted <= :endtd";
    $paramstot['endtd'] = $endtd;
}

$surveys = $DB->get_records_sql($sqlcourses);
foreach($surveys as $survey) {
	    $valid = false;
	    if (is_siteadmin() ) {
           $valid = true;	    
	    } else {
           $context = context_course::instance($survey->course);
           if (has_capability('moodle/question:editall', $context, $USER->id, false)) {
               $valid = true;	       
	        }    
	    }
	    if ($valid) {
           $sid = $survey->instance;
           $paramstot['questionnaireid'] = $sid;
           $sqlquestion = $sqltot . $fromtot . $wheretot;
           $respsql = $DB->get_record_sql($sqlquestion, $paramstot);
           $totresp = $respsql->crid + $totresp;
       }
}

// Assembled data for lead facilitator table.
$data = new stdClass();
// Response data.
$data->responses = new stdClass();
$data->responses->this_course = $totrespcourse;
$data->responses->all_courses = $totresp;

// Facilitator data container.
$data->facilitator = [];

$params = array();
$sql = 'select min(position) mp from {questionnaire_question} where surveyid = '.$surveyid .' and type_id = 11 order by position desc';
$records = $DB->get_record_sql($sql, $params);
$stp = $records->mp;

for ($x = 0; $x <= 1; $x++) {
	  $pnum = $stp + $x;
     // Question
     $qcontent = $DB->get_field('questionnaire_question', 'content', array('position' => $pnum, 'surveyid' => $surveyid, 'type_id' => '11'));
     // Course
     $course = block_questionreport_get_question_results($pnum, $cid, $surveyid, $moduleid, $tagid, $start_date, $end_date, $partner);
     $all = block_questionreport_get_question_results($x, 0, 0, $moduleid, $tagid, $start_date, $end_date, $partner);
     // Build object from data and assign it to the $data object passed to the template.
     $obj = new stdClass();
     $obj->question = str_replace("&nbsp;", ' ', trim(strip_tags($qcontent)));
     $obj->course = $course;
     $obj->all = $all;
     array_push($data->facilitator, $obj);
}

// Container for session survey questions passed to template.
$data->session = [];

$qcontent = $DB->get_field('questionnaire_question', 'content', array('position' => '1', 'surveyid' => $surveyid, 'type_id' => '8'));
$qid = $DB->get_field('questionnaire_question', 'id', array('position' => '1', 'surveyid' => $surveyid, 'type_id' => '8'));
$choices = $DB->get_records('questionnaire_quest_choice', array('question_id' => $qid));
$choicecnt = 0;
foreach ($choices as $choice) {
    $obj = new stdClass;
    $obj->question = $choice->content;
    $choiceid = $choice->id;
    $choicecnt = $choicecnt + 1;
    $course = block_questionreport_get_question_results_rank($qid, $choiceid, $cid, $surveyid, $moduleid, $tagid, $start_date, $end_date, $partner);
    $all = block_questionreport_get_question_results_rank($qid, $choicecnt, 0, 0, $moduleid, $tagid, $start_date, $end_date, $partner);
    $obj->course = $course; // TODO: Derek: Pass the actual choice values for course and all here.
    $obj->all = $all; 
    array_push($data->session, $obj);
}

// Return rendered template.
$content .= $OUTPUT->render_from_template('block_questionreport/report_tables', $data);

// Assemble data for word cloud.
$word_cloud = new stdClass();
// wordcount is an array.
// Array should be in the list form stipulated here: 
// https://github.com/timdream/wordcloud2.js/
// [ [ "word", size], ["word", size], ... ]
$wordcount = block_questionreport_get_words($surveyid, $start_date, $end_date);
$default_font_size = 20; // Adjust for more words.
$words = [];
foreach ($wordcount as $wd) {
    $word = [];
    array_push($word, $wd->word);
    array_push($word, $wd->percent * $default_font_size);
    array_push($words, $word);
}

// Print wordCloud array to the page.
$content .= '<script>';
$content .= 'var wordCloud = '.json_encode($words).';';
$content .= '</script>';
// Return rendered word cloud.
$content .= $OUTPUT->render_from_template('block_questionreport/word_cloud', $word_cloud);

// Build data object for text question quotes.
$quote_data = new stdClass();
// Array of text responses to render.
if ($questionid > 0 ){
    $quote_data->quotes = block_questionreport_get_essay_results($questionid, $start_date, $end_date, 0);
}

$questionlist = block_questionreport_get_essay($surveyid);
$content .= "<form class=\"questionreportform\" action=\"$CFG->wwwroot/blocks/questionreport/report.php\" method=\"get\">\n";
$content .= "<input type=\"hidden\" name=\"action\" value=\"view\" />\n";
$content .= "<input type=\"hidden\" name=\"cid\" value=\"$cid\" />\n";
$content .= "<input type=\"hidden\" name=\"partner\" value=\"$partner\" />\n";
$content .= "<input type=\"hidden\" name=\"start_date\" value=\"$start_date\" />\n";
$content .= "<input type=\"hidden\" name=\"end_date\" value=\"$end_date\" />\n";
$content .= html_writer::label(get_string('questionlist', $plugin), false, array('class' => 'accesshide'));
$content .= html_writer::select($questionlist,"question",$questionid, false);
$content .= '<input class="btn btn-primary btn-submit" type="submit" value="'.get_string('getthequestion', $plugin).'" />';
$content .= '</form>';

// Lou this is for How likely are you to reccomend this course.
/*
$qcontent = $DB->get_field('questionnaire_question', 'content', array('position' => '9', 'surveyid' => $surveyid, 'type_id' => '8'));
$qid = $DB->get_field('questionnaire_question', 'id', array('position' => '9', 'surveyid' => $surveyid, 'type_id' => '8'));
$choices = $DB->get_records('questionnaire_quest_choice', array('question_id' => $qid));
$choicecnt = 0;
foreach ($choices as $choice) {
    $obj = new stdClass;
    $obj->question = $choice->content;
    $choiceid = $choice->id;
    $choicecnt = $choicecnt + 1;
    $course = block_questionreport_get_question_results_percent($qid, $choiceid, $cid, $surveyid, $moduleid, $tagid, $start_date, $end_date, $partner);
    $all = block_questionreport_get_question_results_percent($qid, $choicecnt, 0, 0, $moduleid, $tagid, $start_date, $end_date, $partner);
    $obj->course = $course;
    $obj->all = $all; 
    array_push($data->session, $obj);
}
*/
// Return rendered quote list.
$content .= $OUTPUT->render_from_template('block_questionreport/custom_quotes', $quote_data);

echo $content;  
echo $OUTPUT->footer();
