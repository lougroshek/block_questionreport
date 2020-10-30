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
$cid          = optional_param('cid', 0, PARAM_INT);// Course ID.
$sid          = optional_param('sid', 1, PARAM_INT);// Survey Tagid.

$action       = optional_param('action', 'view', PARAM_ALPHAEXT);
$start_date   = optional_param('start_date', '0', PARAM_RAW);
$end_date     = optional_param('end_date', '0', PARAM_RAW);
$partner      = optional_param('partner', '', PARAM_RAW);
$questionid   = optional_param('question', 0, PARAM_INT);

global $CFG, $OUTPUT, $USER, $DB;
require_once($CFG->dirroot.'/blocks/questionreport/locallib.php');
require_login($cid);
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
               AND m.course = ".$cid . "
               AND m.deletioninprogress = 0";

$surveys = $DB->get_record_sql($sqlcourse);
if (!$surveys) {
    echo 'not a valid survey';
    echo $OUTPUT->footer();
    exit();        
}
$surveyid = $surveys->instance;
echo html_writer::start_tag('h2');
echo get_string('filters', $plugin);
echo html_writer::end_tag('h2');
echo "<form class=\"questionreportform\" action=\"$CFG->wwwroot/blocks/questionreport/charts.php\" method=\"get\">\n";
echo "<input type=\"hidden\" name=\"action\" value=\"view\" />\n";
echo html_writer::label(get_string('surveyfilter', $plugin), false, array('class' => 'accesshide'));
echo html_writer::select($surveylist,"sid",$sid, false);


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
echo html_writer::label(get_string('questionlist', $plugin), false, array('class' => 'accesshide'));
$questionlist = block_questionreport_get_essay($surveyid);
echo html_writer::select($questionlist,"question",$questionid, false);
echo html_writer::start_tag('h2');
echo get_string('selectchart', $plugin);
echo html_writer::end_tag('h2');
echo '<input type="radio" id="chart" name="chart" value="Bar1"/>Bar Chart of Portfolios<br>';
echo '<input type="radio" id="chart" name="chart" value="Bar2"/>Bar Chart of Partner Sites<br>';
echo '<input type="radio" id="chart" name="chart" value="Bar3"/>Bar Chart of Courses<br>';
echo '<input type="radio" id="chart" name="chart" value="Bar4"/>Bar Chart of Facilitators<br>';
echo '<input type="radio" id="chart" name="chart" value="Line1"/>Line Chart of Portfolios';
echo '<input type="submit" class="btn btn-primary btn-submit" value="'.get_string('getthesurveys', $plugin).'" />';
echo '</form>';
echo html_writer::end_tag('div');

$graph = '
        <script type="text/javascript" src="https://www.google.com/jsapi"></script>
        <script type="text/javascript">
        google.load("visualization", "1", {packages:["corechart"]});
        google.setOnLoadCallback(drawChart);
        function drawChart() {
            var data = new google.visualization.DataTable();
            data.addColumn("string", "Day");';

$graph .= 'data.addColumn("number", "visitors")';
$graph .= 'data.addColumn("number", "Uniquevisitors")';

$days = array('7','12','14','16','22', '100', '12', '17');
$visits1 = array('10', '1', '22', '7', '9', '11','16');
$type1 = 'area';
$type2 = 'area';

$daysnb = 6;

$graph .= 'data.addRows([ ';
$a = 0;
for ($i = $daysnb; $i > -1; $i--) {
     $graph .= '["' . $days[$a] . '","' . $visits1[$a] . '"],';
     $a++;
}
$graph .= ' ]);';
$graph .= '
            var chart = new google.visualization.AreaChart(document.getElementById("chart_div"));
            chart.draw(data, options);
        }
        $(document).ready(function(){
            $(window).resize(function(){
                drawChart();
            });
        });
        </script>
        <div id="chart_div" style="width:100%" height:"500"></div>';

echo $graph;
echo $OUTPUT->header();
echo $OUTPUT->footer();
