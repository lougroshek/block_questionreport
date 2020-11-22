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
//    $teacherlist[0] = get_string('all', $plugin);
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
                                              $stdate, $nddate, $teacher, $questionid, $action) {
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
     // Get teachers separated by roles.
    $roles = get_config('block_questionreport', 'roles');
    $roles = str_replace('"', "", $roles);
    $choiceid = 0;
    $nq = strpos($questionid, 'xx-');
    if ($nq) {
        $choiceid = substr($questionid, 4);
        $questionid = $DB->get_field('questionnaire_quest_choice', 'question_id', array('id' => $choiceid));
        $quest = $DB->get_field('questionnaire_quest_choice', 'content', array('id' => $choiceid));
    }
    $plugin = 'block_questionreport';
    $na = get_string('none', $plugin);
    
    $fieldid = get_config($plugin, 'partnerfield');
    $partnerid = $DB->get_field('customfield_field', 'configdata', array('id' => $fieldid));
   
    $portfieldid = get_config($plugin, 'portfoliofield');
    $portid = $DB->get_field('customfield_field', 'configdata', array('id' => $portfieldid));

    // Get the name of the question.
    $qname = $DB->get_field('questionnaire_question', 'name', array('id' => $questionid));
    if ($choiceid == 0) {    
        $sqladmin = "SELECT qt.id qtid, qq.id, qq.surveyid, qt.response, qr.submitted, qs.courseid, qq.content 
                       FROM {questionnaire_question} qq
                       JOIN {questionnaire_response_text} qt on qt.question_id = qq.id
                       JOIN {questionnaire_response} qr on qr.id = qt.response_id
                       JOIN {questionnaire_survey} qs on qs.id = qq.surveyid
                      WHERE qq.name = :qname";
          $paramsql = array('qname' => $qname);
    } else {
          $sqladmin = "SELECT rankvalue response, qq.id, qq.surveyid, qs.courseid, qr.submitted
                         FROM {questionnaire_response_rank} mr
                         JOIN {questionnaire_question} qq on qq.id = mr.question_id
                         JOIN {questionnaire_survey} qs on qs.id = qq.surveyid
                         JOIN {questionnaire_response} qr on qr.id = mr.response_id
                        WHERE mr.question_id = :questionid
                          AND choice_id = :choiceid";
          $paramsql = array ('questionid' => $questionid, 'choiceid' => $choiceid);                    
    }
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
    $displaycnt = 0;
    $display = true;
    if ($action == 'csv') {
        $display = false;    
    }
    $maxdisplay = 10;
    if ($action == 'csv') {
        $rowheaders = array('date','partner', 'portfolio','teacher', 'course', 'question', 'response');    
    }
    $output = array();
    $content = [];
    $var = array();
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
       $pl = strlen(trim($partner));
       if ($pl > 0) {
           $partnercheck = $partner + 1;
           if ($partnercheck == $ps) {
               $valid = true;           
           } else {
               $valid = false;           
           }
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
       $pflen = strlen(trim($portfolio));
       if ($valid and $pflen > 0) {
           $portcheck = $portfolio + 1;
           if ($pf == $portcheck) {                          
           } else {
              $valid = false;           
           }
       }
       $ltea = strlen(trim($teacher));
       $teachercheck = false;
       if ($ltea > 0) {
           $teachercheck = true;
           $validteacher = false;       
       }
       // Course context.
       $context = context_course::instance($courseid);
       $contextid = $context->id;
       $sqlteacher = "SELECT u.firstname, u.lastname, u.id 
                       FROM {user} u 
                       JOIN {role_assignments} ra on ra.userid = u.id
                        AND ra.contextid = :context 
                        AND roleid in (".$roles.")";
       $paramteacher = array ('context' => $contextid);
       $teacherlist = $DB->get_records_sql($sqlteacher, $paramteacher);
       $tlist = '';
       foreach($teacherlist as $te) {
            $tlist = $tlist .$te->firstname .' - '. $te->lastname;
            // Check for the valid teacher .
            if ($teachercheck) {
               if ($te->id == $teacher) {
                   $validteacher = true;               
               }            
            }
       }
       if ($valid and $teachercheck) {
           if ($validteacher ) {           
           } else {
               $valid = false;           
           } 
       }
       if ($valid) {
           if ($choiceid == 0) {
               $quest = $result->content;
           }
           $quest = strip_tags($quest);
           $quest = trim($quest);

       	  if ($display) { 
               $row = new stdClass();
               $row->date = date('Y-m-d', $result->submitted);
               $row->partner = $partnerdisplay;
               $row->portfolio = $portdisplay;
               $row->course_id = $courseid;
               $row->course = $DB->get_field('course', 'fullname', array('id' => $courseid));
               $row->question = $quest;
               $cr = $result->response;
               $cr =  str_replace("&nbsp;", '', trim(strip_tags($cr))); 
               $row->response = $cr;
               $row->teachers = $tlist;
               array_push($content, $row);

               $displaycnt = $displaycnt + 1;
               if ($displaycnt > $maxdisplay) { 
                   break;
               }
           } else {
           	   $sub = date('Y-m-d', $result->submitted);
           	   $cfname = $DB->get_field('course', 'fullname', array('id' => $courseid));
               $cr = $result->response;  
               $cr =  str_replace("&nbsp;", '', trim(strip_tags($cr)));
               $displaycnt = $displaycnt + 1;                             
               $output[] = array($sub, $partnerdisplay, $portdisplay, $tlist, $cfname, $quest, $cr);
           }
       }    
    }
    
    if ($action == "csv") {   
        $name = 'Results';
        \core\dataformat::download_data($name, 'csv', $rowheaders, $output);
        exit();
    }    
    return $content;
}

function block_questionreport_setchart($chartid, $stdate, $nddate, $cid, $sid, $questionid) {
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
    $plugin = 'block_questionreport';
    $content = '';
    if ($questionid == '0') {
        return $content;
        exit();    
    }
    $svcnt = 0;
    $tagvalue = get_config($plugin, 'tag_value');
    $tagid = $DB->get_field('tag', 'id', array('name' => $tagvalue));
    $moduleid = $DB->get_field('modules', 'id', array('name' => 'questionnaire'));

     // Get teachers separated by roles.
    $roles = get_config($plugin, 'roles');
    $roles = str_replace('"', "", $roles);
    $choiceid = $questionid;
    $qid = $DB->get_field('questionnaire_quest_choice', 'question_id', array('id' => $choiceid));
    $quest = $DB->get_field('questionnaire_quest_choice', 'content', array('id' => $choiceid));
    $na = get_string('none', $plugin);
    
    $fieldid = get_config($plugin, 'partnerfield');
    $partnerid = $DB->get_field('customfield_field', 'configdata', array('id' => $fieldid));
   
    $portfieldid = get_config($plugin, 'portfoliofield');
    $portid = $DB->get_field('customfield_field', 'configdata', array('id' => $portfieldid));
    $choices = $DB->get_records('questionnaire_quest_choice', array('question_id' => $qid));
    $choicecnt = 0;
    foreach ($choices as $choice) {          
       $chid = $choice->id;
       $choicecnt = $choicecnt + 1;
       if ($chid == $choiceid) {
           break;                                      
       }
    }
    $chart = new core\chart_bar();   
    $partnerlist = block_questionreport_get_partners_list();
    $qname = $DB->get_field('questionnaire_question', 'name', array('id' => $qid));
    $pcnt = 0;
    $labelarray = array();
    foreach ($partnerlist as $partnername) {
        $comparevalue = $DB->sql_compare_text($partnername);
        $partnerid = get_config($plugin, 'partnerfield');
        //$partnerid  = $partnerid + 1;
        $partnersql = "JOIN {customfield_data} cd ON cd.instanceid = m.course 
                        AND cd.fieldid = ".$partnerid ." AND cd.value = ".$pcnt;
        $sqlcourses = "SELECT m.course, m.id, m.instance
                          FROM {course_modules} m
                          JOIN {tag_instance} ti on ti.itemid = m.id " .$partnersql. "                          
                         WHERE m.module = ".$moduleid. "
                           AND ti.tagid = ".$tagid . "
                           AND m.deletioninprogress = 0";
        $surveys = $DB->get_records_sql($sqlcourses);
echo $paramsql;
        foreach ($surveys as $survey) {
           $sid = $survey->instance;
           echo ' sid '.$sid;
           $qid = $DB->get_field('questionnaire_question', 'id', array('name' => $qname, 'surveyid' => $sid, 'type_id' => '8', 'deleted' => 'n'));
           $choices = $DB->get_records('questionnaire_quest_choice', array('question_id' => $qid));
           $cnt = 0;              
           foreach ($choices as $choice) {          
              $chid = $choice->id;
              $cnt = $cnt + 1;
              if ($cnt == $choicecnt) {
                   break;                                      
              }
           }
           $totresql  = "SELECT count(rankvalue) ";
           $fromressql = " FROM {questionnaire_response_rank} mr ";
        	  $whereressql = "WHERE mr.question_id = ".$qid ." AND choice_id = ".$chid;
           $paramsql = array();
        	  if ($stdate > 0) {
               $fromressql = $fromressql .' JOIN {questionnaire_response} qr on qr.id = mr.response_id';
               $whereressql = $whereressql . ' AND qr.submitted >= :stdate';
               $std = strtotime($stdate);
               $paramsql['stdate'] = $std;        	  
        	   }
        	  if ($nddate > 0) {
               $fromressql = $fromressql .' JOIN {questionnaire_response} qr2 on qr2.id = mr.response_id';
               $whereressql = $whereressql . ' AND qr2.submitted <= :nddate';
               $ndt = strtotime($nddate);
               $paramsql['nddate'] = $ndt;        	  
        	  }
           $totgoodsql = $totresql .' '. $fromressql. ' '. $whereressql;
           $totres = $DB->count_records_sql($totgoodsql, $paramsql);
           if ($totres > 0) {
           	   $svcnt = $svcnt + 1;
               $ranksql = "SELECT sum(rankvalue) rv ";
               $totranksql = $ranksql .' '. $fromressql. ' '. $whereressql;
               $ranksql = $DB->get_record_sql($totranksql, $paramsql);
               $val = $ranksql->rv / $totres;
               $val = round($val, 2);
               $valarray[] = $val;
               $labelarray[] = $partnername;
           }    
        }
        $pcnt = $pcnt + 1; 
    }
    if ($svcnt == 0 ) {
    	 return '0';
    } else {
       $series1 = new \core\chart_series('Series 1 (Bar)', $valarray);
       $chart->add_series($series1);
       $chart->set_labels($labelarray);
       return $chart;
    }
/*
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
        $partners = block_questionreport_get_partners_list();
        $cnt = 0;
        foreach($partners as $partner) {
           $cnt = $cnt + 1;
           if ($cnt == 1) {
               $pl = "'".$partner."'";
           } else {
               $pl = $pl.",'".$partner."'";
           }         
        }
        $pl = '['.$pl.']';
      
        $series1 = new \core\chart_series('Series 1 (Bar)', [1000, 660, 1030]);
        $series2 = new \core\chart_series('Series 2 (Line)', [400, 1120, 540]);
        $series2->set_type(\core\chart_series::TYPE_LINE); // Set the series type to line chart.
        $chart->add_series($series2);
        $chart->add_series($series1);
        $chart->set_labels($partners);
        
        //$chart->set_labels($partners);
          
      break;
   }
   return $chart;
   */         
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
