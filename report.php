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
echo "<form class=\"questionreportform\" action=\"$CFG->wwwroot/blocks/questionreport/report.php\" method=\"get\">\n";
echo "<input type=\"hidden\" name=\"action\" value=\"view\" />\n";

echo html_writer::label(get_string('coursefilter', $plugin), false, array('class' => 'accesshide'));
echo html_writer::select($courselist,"cid",$cid, false);

$partnerlist = block_questionreport_get_partners_list();
echo html_writer::label(get_string('partnerfilter', $plugin), false, array('class' => 'accesshide'));
echo html_writer::select($partnerlist, "partner", $partner, get_string("all", $plugin));

$datelist = block_questionreport_get_courses();
echo html_writer::label(get_string('datefilter', $plugin), false, array('class' => 'accesshide'));
echo '<input type="date" id="start-date" name="start_date" value="'.$start_date.'"/>';
echo html_writer::label(get_string('to'), false, array('class' => 'accesshide'));
echo '<input type="date" id="end-date" name="end_date" value="'.$end_date .'"/>';
echo '<input type="submit" value="'.get_string('getthesurveys', $plugin).'" />';
echo '</form>';
 
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

$resp = $DB->get_record_sql($sqlresp);

$totrespcourse = $resp->crid;

// Get the total responses.
$partnersql = '';
if ($partner > '') {
    $comparevalue = $DB->sql_compare_text($partner);
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
    	    $context = get_context_instance(CONTEXT_COURSE,$survey->id);
	       if (has_capability('moodle/legacy:editingteacher', $context, $USER->id, false)) {
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
$content .= html_writer::end_tag('table');
$content .= '<br>';

$content .= html_writer::start_tag('table');
$content .= html_writer::start_tag('tr');
$content .= html_writer::start_tag('td');
$content .= '<b>Facilitation Summary (% Agree & Strongly Agree)</b>';
$content .= html_writer::end_tag('td');
$content .= html_writer::start_tag('td');
$content .= '<b>'.get_string('thiscourse',$plugin).'</b>';
$content .= html_writer::end_tag('td');
$content .= html_writer::start_tag('td');
$content .= '<b>'.get_string('allcourses',$plugin).'</b>';
$content .= html_writer::end_tag('td');
$content .= html_writer::end_tag('tr');
$params = array();
$sql = 'select min(position) mp from {questionnaire_question} where surveyid = '.$surveyid .' and type_id = 11 order by position desc';
$records = $DB->get_record_sql($sql, $params);
$stp = $records->mp;

for ($x = 0; $x <= 1; $x++) {
	  $pnum = $stp + $x;
     $content .= html_writer::start_tag('tr');
     $content .= html_writer::start_tag('td');
     $qcontent = $DB->get_field('questionnaire_question', 'content', array('position' => $pnum, 'surveyid' => $surveyid));
     $content .= $qcontent;
     $content .= html_writer::end_tag('td');
     $content .= html_writer::start_tag('td');
     $content .= block_questionreport_get_question_results($pnum, $cid, $surveyid, $moduleid, $tagid, $start_date, $end_date, $partner);
     $content .= html_writer::end_tag('td');
     $content .= html_writer::start_tag('td');
     $content .= block_questionreport_get_question_results($pnum, 0, 0, $moduleid, $tagid, $start_date, $end_date, $partner);
     $content .= html_writer::end_tag('td');
     $content .= html_writer::end_tag('tr');
}
$content .= html_writer::end_tag('table');
$content .= html_writer::start_tag('table');
$content .= html_writer::start_tag('tr');
$content .= html_writer::start_tag('td');
$content .= '<b>Session (% Agree & Strongly Agree)<b>';
$content .= html_writer::end_tag('td');
$content .= html_writer::start_tag('td');
$content .= '<b>This course</b>';
$content .= html_writer::end_tag('td');
$content .= html_writer::start_tag('td');
$content .= '<b>All Courses</b>';
$content .= html_writer::end_tag('td');
$content .= html_writer::end_tag('tr');
for ($x = 3; $x <= 5; $x++) {
     $content .= html_writer::start_tag('tr');
     $content .= html_writer::start_tag('td');
     $qcontent = $DB->get_field('questionnaire_question', 'content', array('position' => $x, 'surveyid' => $surveyid));
     $content .= $qcontent;
     $content .= html_writer::end_tag('td');
     $content .= html_writer::start_tag('td');
     $content .= block_questionreport_get_question_results($x, $cid, $surveyid, $moduleid, $tagid, $start_date, $end_date, $partner);
     $content .= html_writer::end_tag('td');
     $content .= html_writer::start_tag('td');
     $content .= block_questionreport_get_question_results($x, 0, 0, $moduleid, $tagid, $start_date, $end_date, $partner);
     $content .= html_writer::end_tag('td');
     $content .= html_writer::end_tag('tr');
}    
    $content .= html_writer::end_tag('table');


// Assembled data for lead facilitator table.
$data = new stdClass();
// Values.
$data->values = new stdClass();
$data->values->this_course = $totrespcourse; // Number of responses for course.
$data->values->all_courses = $totresp; // Number of responses for all courses.
$data->values->fac_content_course = 45; // TODO: Derek, assign this course response for 'facilitator_rate_content'
$data->values->fac_content_all = 52;// TODO: Derek, assign all course response for 'facilitator_rate_content'
$data->values->fac_comm_course = 65; // TODO: Derek, assign this course response for 'facilitator_rate_community'
$data->values->fac_comm_all = 42;// TODO: Derek, assign all course response for 'facilitator_rate_community'





// Return rendered template.
$content .= $OUTPUT->render_from_template('block_questionreport/report_tables', $data);

// Assemble data for word cloud.
$word_cloud = new stdClass();
// Array should be in the list form stipulated here: 
// https://github.com/timdream/wordcloud2.js/
// [ [ "word", size], ["word", size], ... ]
$wordcount = block_questionreport_get_words($surveyid);
$default_font_size = 10;
$words = [];
foreach ($wordcount as $wd) {
    $word = [];
    array_push($word, $wd['word']);
    array_push($word, $wd['percent'] * $default_font_size);
    array_push($words, $word);
}

// Print wordCloud array to the page.
$content .= '<script>';
$content .= 'var wordCloud = '.json_encode($words).';';
$content .= '</script>';
// Return rendered word cloud.
$content .= $OUTPUT->render_from_template('block_questionreport/word_cloud', $word_cloud);

// Generate list of questions for select.
$questionlist = block_questionreport_get_essay($surveyid);
// Form to all for selection of question.
$content .=  "<form class=\"questionreportform\" action=\"$CFG->wwwroot/blocks/questionreport/report.php\" method=\"get\">\n";
$content .=  "<input type=\"hidden\" name=\"action\" value=\"view\" />\n";
$content .=  "<input type=\"hidden\" name=\"cid\" value=\"$cid\" />\n";
$content .=  "<input type=\"hidden\" name=\"partner\" value=\"$partner\" />\n";
$content .=  "<input type=\"hidden\" name=\"start_date\" value=\"$start_date\" />\n";
$content .=  "<input type=\"hidden\" name=\"end_date\" value=\"$end_date\" />\n";
$content .=  html_writer::label(get_string('by_question_instr', $plugin), false, array('class' => 'accesshide'));
$content .=  html_writer::select($questionlist,"question",$questionid, false);
$content .=  '<input type="submit" value="'.get_string('getthequestion', $plugin).'" />';
$content .=  '</form>';

// Build data object for text question quotes.
$quote_data = new stdClass();
// Array of text responses to render.
$quote_data->quotes = block_questionreport_get_essay_results($questionid, $cid, $tagid, $start_date, $end_date, $partner);

// Return rendered quote list.
$content .= $OUTPUT->render_from_template('block_questionreport/custom_quotes', $quote_data);

echo $content;  
echo $OUTPUT->footer();
