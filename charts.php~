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
  * Block questionreport Charts File.
  *
  * @package    block_questionreport
  */
require_once(dirname(__FILE__).'/../../config.php');
$plugin = 'block_questionreport';

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/blocks/questionreport/charts.php');
$PAGE->set_context(context_system::instance());
$header = get_string('chartsheader', $plugin);
$PAGE->set_title($header);
$PAGE->set_heading($header);
$PAGE->set_cacheable(true);
$PAGE->navbar->add($header, new moodle_url('/blocks/questionreport/charts.php'));
$cid          = optional_param('cid', 0, PARAM_RAW);// Course ID.
$sid          = optional_param('sid', 1, PARAM_INT);// Survey Tagid.
$action       = optional_param('action', 'view', PARAM_ALPHAEXT);
$start_date   = optional_param('start_date', '0', PARAM_RAW);
$end_date     = optional_param('end_date', '0', PARAM_RAW);
$questionid   = optional_param('question', 0, PARAM_INT);
$chart        = optional_param('chart','Bar1', PARAM_RAW); //Chart id.

global $CFG, $OUTPUT, $USER, $DB;
require_once($CFG->dirroot.'/blocks/questionreport/locallib.php');
require_once($CFG->dirroot.'/blocks/questionreport/chartlib.php');
$ctype = substr($cid, 0, 1);
$courseid = substr($cid, 2);
if ($ctype == "M") {
    require_login($courseid);
    global $COURSE;
}

echo $OUTPUT->header();

global $COURSE;
$courselist = block_questionreport_get_courses();
$surveylist = array("1" => "End of Course Survey", "2" => "Diagnostic Survey");
$tagvalue = get_config($plugin, 'tag_value');
$tagid = $DB->get_field('tag', 'id', array('name' => $tagvalue));
$moduleid = $DB->get_field('modules', 'id', array('name' => 'questionnaire'));
$sqlcourse = "SELECT m.course, m.id, m.instance
               FROM {course_modules} m
               JOIN {tag_instance} ti on ti.itemid = m.id
              WHERE m.module = ".$moduleid. "
               AND ti.tagid = ".$tagid . "
               AND m.course = ".$courseid . "
               AND m.deletioninprogress = 0";

$surveys = $DB->get_record_sql($sqlcourse);
if (!$surveys) {
    echo 'not a valid survey';
    echo $OUTPUT->footer();
    exit();        
}
$surveyid = $surveys->instance;
echo "<form class=\"questionreportform\" action=\"$CFG->wwwroot/blocks/questionreport/charts.php\" method=\"get\">\n";
echo "<input type=\"hidden\" name=\"action\" value=\"view\" />\n";
echo "<input type=\"hidden\" name=\"cid\" value=\"$cid\" />\n";

echo html_writer::label(get_string('surveyfilter', $plugin), false, array('class' => 'accesshide'));
echo html_writer::select($surveylist,"sid",$sid, false);
echo html_writer::label(get_string('chartquestion', $plugin), false, array('class' => 'accesshide'));
$questionlist = block_questionreport_get_chartquestions($surveyid);
echo html_writer::select($questionlist,"question",$questionid, false);

// Date select.
echo html_writer::start_tag('div', array('class' => 'date-input-group'));
echo html_writer::label(get_string('datefilter', $plugin), false, array('class' => 'accesshide'));
echo '<input type="date" id="start-date" name="start_date" value="'.$start_date.'"/>';
echo html_writer::label(get_string('to'), false, array('class' => 'inline'));
echo '<input type="date" id="end-date" name="end_date" value="'.$end_date .'"/>';
echo html_writer::label(get_string('selectchart', $plugin), false, array('class' => 'accesshide'));
$checked1 =  '';
$checked2 =  '';
$checked3 =  '';
$checked4 =  '';

if ($chart == 'Bar1') {
    $checked1 = 'checked';
}
if ($chart == 'Bar2') {
    $checked2 = 'checked';
}

if ($chart == 'Bar3') {
    $checked3 = 'checked';
}

if ($chart == 'Bar4') {
    $checked4 = 'checked';
}
echo '<input type="radio" id="chart" name="chart" value="Bar1" '.$checked1. ' />Bar Chart: Percent of reponses 4 and 5 by Partner Site<br>';
echo '<input type="submit" class="btn btn-primary btn-submit" value="'.get_string('getthechart', $plugin).'" />';
echo '</form>';
echo html_writer::end_tag('div');
if ($questionid <> '0') {
    $genchart = block_questionreport_setchart($ctype, $chart, $start_date, $end_date, $courseid, $sid, $questionid);
    if ($genchart == '0') {
        echo get_string('nochart', $plugin);    
    } else {
        echo $OUTPUT->render($genchart);
    }    
}   
echo $OUTPUT->footer();
