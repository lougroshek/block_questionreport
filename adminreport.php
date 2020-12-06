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
  * Block questionreport admin Report File.
  *
  * @package    block_questionreport
  */
require_once(dirname(__FILE__).'/../../config.php');
$cid          = optional_param('cid', 0, PARAM_RAW);// Course ID.
$action       = optional_param('action', 'view', PARAM_ALPHAEXT);
$start_date   = optional_param('start_date', '0', PARAM_RAW);
$end_date     = optional_param('end_date', '0', PARAM_RAW);
$partner      = optional_param('partner', '', PARAM_RAW);
$questionid   = optional_param('question', '0', PARAM_RAW);
$portfolio    = optional_param('portfolio', '', PARAM_RAW);
$sid          = optional_param('sid', 1, PARAM_INT); // Survey id.
$teacher      = optional_param('teacher', '', PARAM_RAW); //Teacher id.

$plugin = 'block_questionreport';
$PAGE->set_pagelayout('standard');
$PAGE->set_url('/blocks/questionreport/adminreport.php');
$PAGE->set_context(context_system::instance());
$header = get_string('reportheader', $plugin);
$PAGE->set_title($header);
$PAGE->set_heading($header);
$PAGE->set_cacheable(true);
$PAGE->navbar->add($header, new moodle_url('/blocks/questionreport/adminreport.php'));

global $CFG, $OUTPUT, $USER, $DB;
require_once($CFG->dirroot.'/blocks/questionreport/locallib.php');
require_once($CFG->dirroot.'/blocks/questionreport/chartlib.php');

$ctype = substr($cid, 0, 1);
$courseid = substr($cid, 2);
if ($ctype == "M") {
    require_login($courseid);
    global $COURSE;
}
if ($cid == '0') {
    $ctype = 'A';
    $cid = 0;
    $courseid = 0;
}
// Build up the filters
$courselist = block_questionreport_get_courses();
$surveylist = array("1" => "End of Course Survey", "2" => "Diagnostic Survey");

if ($sid == 1) {
 	 $tagvalue = get_config($plugin, 'tag_value');
} else {
    $tagvalue = get_config($plugin, 'tag_value_diagnostic');
}
$moduleid = $DB->get_field('modules', 'id', array('name' => 'questionnaire'));
$tagid = $DB->get_field('tag', 'id', array('name' => $tagvalue));
$params = array();
if ($ctype == "M") {
	
    if ($courseid == 0) {
	     // Get a survey.
        $tagid = $DB->get_field('tag', 'id', array('name' => $tagvalue));
        $sqlcourse = "SELECT  max(m.instance) instance
                        FROM {course_modules} m
                        JOIN {tag_instance} ti on ti.itemid = m.id
                        JOIN {course} c on c.id = m.course
                       WHERE m.module = ".$moduleid. "
                         AND ti.tagid = ".$tagid . "
                         AND m.deletioninprogress = 0
                        AND c.visible = 1";

         $surveys = $DB->get_record_sql($sqlcourse, $params);
         if (!$surveys) {
    	       // Should never get here.
             echo 'no surveys';
             echo $OUTPUT->footer();
             exit();        
         }
         $surveyid = $surveys->instance;
     } else {
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
     }     
 } else {
     $surveyid = 0;
 } 
    
 
 
if ($action == 'csv') {
   $content = block_questionreport_get_adminreport($ctype, $sid, $courseid, $partner, $portfolio, $start_date, $end_date, $teacher, $questionid, $action); 

} else {
    echo $OUTPUT->header();

    echo html_writer::start_tag('h2');
    echo get_string('filters', $plugin);
    echo html_writer::end_tag('h2');
    echo "<form class=\"questionreportform\" action=\"$CFG->wwwroot/blocks/questionreport/adminreport.php\" method=\"get\">\n";
    echo "<input type=\"hidden\" name=\"action\" value=\"view\" />\n";
    echo html_writer::label(get_string('surveyfilter', $plugin), false, array('class' => 'accesshide'));
    echo html_writer::select($surveylist,"sid",$sid, false);

    echo html_writer::label(get_string('coursedesc', $plugin), false, array('class' => 'accesshide'));
    echo html_writer::select($courselist,"cid",$cid, false);

    $partnerlist = block_questionreport_get_partners_list();

    echo html_writer::label(get_string('partnerdesc', $plugin), false, array('class' => 'accesshide'));
    echo html_writer::select($partnerlist, "partner", $partner, get_string("all", $plugin));

    $portfoliolist = block_questionreport_get_portfolio_list();

    echo html_writer::label(get_string('portfoliofilter', $plugin), false, array('class' => 'accesshide'));
    echo html_writer::select($portfoliolist, "portfolio", $portfolio, get_string("all", $plugin));

    // Date select.
    echo html_writer::start_tag('div', array('class' => 'date-input-group'));
    echo html_writer::label(get_string('datefilter', $plugin), false, array('class' => 'accesshide'));
    echo '<input type="date" id="start-date" name="start_date" value="'.$start_date.'"/>';
    echo html_writer::label(get_string('to'), false, array('class' => 'inline'));
    echo '<input type="date" id="end-date" name="end_date" value="'.$end_date .'"/>';

    $teacherlist = block_questionreport_get_teachers_list();

    echo html_writer::label(get_string('teacherfilter', $plugin), false, array('class' => 'accesshide'));
    echo html_writer::select($teacherlist, "teacher", $teacher, get_string("all", $plugin));

    $questionlist = block_questionreport_get_essay($ctype, $surveyid);

    echo html_writer::label(get_string('questionlist', $plugin), false, array('class' => 'accesshide'));
    echo html_writer::select($questionlist,"question",$questionid, false);

    echo '<input type="submit" class="btn btn-primary btn-submit" value="'.get_string('getthesurveys', $plugin).'" />';
    echo '</form>';
    // echo html_writer::end_tag('div');

    // Assemble the object to pass to the mustache template.
    $rows = block_questionreport_get_adminreport($ctype, $sid, $courseid, $partner, $portfolio, $start_date, $end_date, $teacher, $questionid, $action);
    $data = new StdClass();
    $data->rows = $rows;

    $content = '';
    // Write template output to content.
    $content .= $OUTPUT->render_from_template('block_questionreport/admin_table', $data);
    // Write download button to content.
    if ($questionid > '0') {
     	 $content .= "<form class=\"questionreportform2\" action=\"$CFG->wwwroot/blocks/questionreport/adminreport.php\" method=\"get\">\n";
        $content .= "<input type=\"hidden\" name=\"cid\" value=\"$cid\" />\n";
        $content .= "<input type=\"hidden\" name=\"partner\" value=\"$partner\" />\n";
        $content .= "<input type=\"hidden\" name=\"start_date\" value=\"$start_date\" />\n";
        $content .= "<input type=\"hidden\" name=\"end_date\" value=\"$end_date\" />\n";
        $content .= "<input type=\"hidden\" name=\"teacher\" value=\"$teacher\" />\n";
        $content .= "<input type=\"hidden\" name=\"portfolio\" value=\"$portfolio\" />\n";
        $content .= "<input type=\"hidden\" name=\"sid\" value=\"$sid\" />\n";
        $content .= "<input type=\"hidden\" name=\"action\" value=\"csv\" />\n";    
        $content .= "<input type=\"hidden\" name=\"question\" value=\"$questionid\" />\n";    
        $content .= '<input class="btn btn-primary btn-submit" type="submit" value="'.get_string('generatecsv', $plugin).'" />';
        $content .= '</form>';
        echo $content;
    }
}
echo $OUTPUT->footer();
