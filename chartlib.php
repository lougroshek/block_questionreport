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
 * Block "questionreport" - chart library
 *
 * @package    block_questionreport
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function block_questionreport_get_portfolio_list() {
    global $DB;
    $plugin = 'block_questionreport';
    $courselist = array();
    $courselist[0] = get_string('all', $plugin);
    $fieldid = get_config($plugin, 'portfoliofield');
    $content = $DB->get_field('customfield_field', 'configdata', array('id' => $fieldid));
    $options = array();
    $x = json_decode($content);
    $opts = $x->options;
    $options = preg_split("/\s*\n\s*/", $opts);
    return $options;

}

function block_questionreport_get_teachers_list() {
    global $DB;
    $plugin = 'block_questionreport';
    $roles = get_config('block_questionreport', 'roles');
    $teacherlist = array();
    $teacherlist[0] = get_string('all', $plugin);
    $teachersql = "SELECT distinct(userid) usersid, lastname, firstname
                   FROM {role_assignments} ra, mdl_user as u
                   WHERE u.id = ra.userid and ra.roleid in (".$roles.")
                   order by lastname, firstname";
    $teacherfields = $DB->get_records_sql($teachersql);
    foreach ($teacherfields as $field) {
        $teacherlist[$field->usersid] = $field->firstname. " ". $field->lastname;
    }
    return $teacherlist;
}

function block_questionreport_get_allessay() {
    global $DB, $COURSE;
    $plugin = 'block_questionreport';
    $essaylist = array();
    $essaylist[0] = get_string('none', $plugin);
    $customfields = $DB->get_records('questionnaire_question', array('type_id' => '3'));
    foreach ($customfields as $field) {
    	  $content = $field->content;
    	  $display = strip_tags($content);
    	  $display = trim($display);
        $essaylist[$field->id] = $display;
    }
    return $essaylist;
}

function block_questionreport_get_adminreport($surveytype, $cid, $partner, $portfolio, 
                                              $stdate, $nddate, $teacher, $questionid) {
    // Return the adminreport
    // surveytype is the type of survey.
	 // cid is the current course, if its 0 then its all courses;
	 // tagid  is the tagid finding for the matching surveys
	 // stdate start date for the surveys (0 if not used)
	 // nddate end date for the surveys (0 if not used)
	 // partner partner - blank if not used.
	 // portfolio portfolio - blank if not used.
	 // teacher - teacher - blank if not used.
	 // questionid - specific question getting results for 

    global $DB;
    $content = '';
    if ($questionid == '0') {
        return $content;
        exit();    
    }
    $choiceid = 0;
    $nq = strpos($questionid, 'xx-');
    if ($nq) {
        $choiceid = substr($questionid, 4);
        $questionid = $DB->get_field('questionnaire_quest_choice', 'question_id', array('id' => $choiceid));
        $content = $DB->get_field('questionnaire_quest_choice', 'content', array('id' => $choiceid));
        $qq = $questionid;
        echo 'qq '.$qq;
    }
    $plugin = 'block_questionreport';
    $na = get_string('none', $plugin);
    
    $fieldid = get_config($plugin, 'partnerfield');
    $partnerid = $DB->get_field('customfield_field', 'configdata', array('id' => $fieldid));
   
    $portfieldid = get_config($plugin, 'portfoliofield');
    $portid = $DB->get_field('customfield_field', 'configdata', array('id' => $portfieldid));

    // Get the name of the question.
    $qname = $DB->get_field('questionnaire_question', 'name', array('id' => $questionid));
echo 'qname '.$qname;
    $sqladmin = "SELECT qt.id qtid, qq.id, qq.surveyid, qt.response, qr.submitted, qs.courseid, qq.content 
                   FROM {questionnaire_question} qq
                   JOIN {questionnaire_response_text} qt on qt.question_id = qq.id
                   JOIN {questionnaire_response} qr on qr.id = qt.response_id
                   JOIN {questionnaire_survey} qs on qs.id = qq.surveyid
                   WHERE qq.name = :qname";
    $paramsql = array('qname' => $qname);
    
    if ($stdate > 0) {
        $sqladmin = $sqladmin . ' AND qr.submitted >= :stdate';
        $std = strtotime($stdate);
        $paramsql['stdate'] = $std;        	  
    }
    if ($nddate > 0) {
        $sqladmin = $sqladmin . ' AND qr2.submitted <= :nddate';
        $ndt = strtotime($nddate);
        $paramsql['nddate'] = $ndt;        	  
    }

    if ($cid > 0) {
        $sqladmin = $sqladmin . ' AND qs.courseid = :courseid';
        $paramsql['courseid'] = $cid;
    }
    $results = $DB->get_records_sql($sqladmin, $paramsql);
    foreach($results as $result) {
    	 $valid = true;
    	 $courseid = $result->courseid;    	 
    	 $ps = $DB->get_field('customfield_data','intvalue', array('instanceid' => $courseid, 'fieldid' => $fieldid));    	     	
       if ($ps) {    
           $options = array();
           $partnercontent = $DB->get_field('customfield_field', 'configdata', array('id' => $fieldid));
           $x = json_decode($partnercontent);
           $opts = $x->options;
           $options = preg_split("/\s*\n\s*/", $opts);
           $partnerdisplay = $options[$ps];
       } else {
           $partnerdisplay = $na;       
       }
       $pf = $DB->get_field('customfield_data','intvalue', array('instanceid' => $courseid, 'fieldid' => $portfieldid));   	     	
       if ($pf) {    
           $options = array();
           $partnercontent = $DB->get_field('customfield_field', 'configdata', array('id' => $portfieldid));
           $x = json_decode($partnercontent);
           $opts = $x->options;
           $options = preg_split("/\s*\n\s*/", $opts);
           $portdisplay = $options[$pf];
       } else {
           $portdisplay = $na;       
       }       
    	 echo '<br> Date '.date('Y-m-d', $result->submitted);
       echo '<br> partner  '.$partnerdisplay;
       echo '<br> portfolio  '.$portdisplay;                      
       echo '<br> course '.$DB->get_field('course', 'fullname', array('id' => $courseid));
       if ($choiceid == 0) {
           $content = $result->content;
       }
       $display = strip_tags($content);
       $display = trim($display);
       echo '<br> question '.$display;
       $cr = $result->response;  
       $cr =  str_replace("&nbsp;", '', trim(strip_tags($cr)));   
       echo '<br> response '.$cr;
    }
    return $content;
}

function block_questionreport_setchart($chartid, $start_date, $end_date, $cid, $sid, $questionid, $teacher) {
    // Return a chart object;
    // surveytype is the type of survey.
	 // cid is the current course, if its 0 then its all courses;
	 // tagid  is the tagid finding for the matching surveys
	 // stdate start date for the surveys (0 if not used)
	 // nddate end date for the surveys (0 if not used)
	 // partner partner - blank if not used.
	 // portfolio portfolio - blank if not used.
	 // teacher - teacher - blank if not used.
	 // questionid - specific question getting results for 

    global $DB;
    $chart = new core\chart_bar();    
    switch($chartid) {
    	case "Bar1": 
    	  $series1 = new \core\chart_series('Series 1 (Bar)', [1000, 1170, 660, 1030]);
        $series2 = new \core\chart_series('Series 2 (Line)', [400, 460, 1120, 540]);
        $series2->set_type(\core\chart_series::TYPE_LINE); // Set the series type to line chart.
        $chart->add_series($series2);
        $chart->add_series($series1);
        $chart->set_labels(['2004', '2005', '2006', '2007']);
      break;
      case "Bar2":
      break;
   }
   return $chart;         
}
function block_questionreport_get_all_questions($surveyid) {
    global $DB, $COURSE;  
    $plugin = 'block_questionreport';
    $essaylist = array();
    $essaylist[0] = get_string('none', $plugin);
    $customfields = $DB->get_records('questionnaire_question', array('surveyid' => $surveyid, 'deleted'=> 'n'));
    foreach ($customfields as $field) {
        $fid = $field->id;
    	  $content = $field->content;
    	  $display = strip_tags($content);
    	  $display = trim($display);
        if ($field->type_id == '8') {
            $choices = $DB->get_records('questionnaire_quest_choice', array('question_id' => $fid));
            $choicecnt = 0;
            foreach ($choices as $choice) {
               $fid = 'xxx-'.$choice->id;	
               $display = $choice->content;
               $display = strip_tags($display);
               $display = trim($display);
               $essaylist[$fid] = $display;
            }
        } else {
           $essaylist[$field->id] = $display;        
        }
    }
    return $essaylist;
}
