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
 * Block "questionreport" - Local library
 *
 * @package    block_people
 * @copyright  2017 Kathrin Osswald, Ulm University <kathrin.osswald@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function block_questionreport_get_choice_current($choiceid) {
    global $DB;
    $recsql = "SELECT count(id) from {questionnaire_response_rank} where choice_id = ".$choiceid ." and rankvalue > 3";
    $recs = $DB->count_records_sql($recsql); 
    // Total the results from this course for this choice.
    return $recs;
}

function block_questionreport_get_evaluations() {
    global $DB, $COURSE;  
    $plugin = 'block_questionreport';
    // Get the tags list.
    $tagvalue = get_config($plugin, 'tag_value');
    $tagid = $DB->get_field('tag', 'id', array('name' => $tagvalue));
    $moduleid = $DB->get_field('modules', 'id', array('name' => 'questionnaire'));
    $cid = $COURSE->id;
    $sqlcourse = "SELECT m.course, m.id, m.instance
               FROM {course_modules} m
               JOIN {tag_instance} ti on ti.itemid = m.id
              WHERE m.module = ".$moduleid. "
               AND ti.tagid = ".$tagid . "
               AND m.course = ".$cid . "
               AND m.deletioninprogress = 0";

    $surveys = $DB->get_record_sql($sqlcourse);
    $surveyid = $surveys->instance;
    $cnt = block_questionreport_get_question_results(1, $cid, $surveyid, $moduleid, $tagid, 0, 0, '');
    if ($cnt == 0) {
    	  $questionid = $DB->get_field('questionnaire_question', 'id', array('position' => '1', 'surveyid' => $surveyid));
        $totres = $DB->count_records('questionnaire_response_rank', array('question_id' => $questionid));        
        if ($totres > 0) {
        	   $content = $DB->get_field('questionnaire_question', 'content', array('position' => '1', 'surveyid' => $surveyid));
            $content .= ' 0%<br>';
        } else { 
           $content = get_string('nocoursevals', $plugin);
        }
    } else {
   	  $qcontent = $DB->get_field('questionnaire_question', 'content', array('position' => '1', 'surveyid' => $surveyid));
        $content = $qcontent . ' ' .$cnt;    
    }
    
    $cnt2 = block_questionreport_get_question_results(2, $cid, $surveyid, $moduleid, $tagid, 0, 0, '');
   if ($cnt2 == 0) {
   	  $questionid = $DB->get_field('questionnaire_question', 'id', array('position' => '2', 'surveyid' => $surveyid));
        $totres = $DB->count_records('questionnaire_response_rank', array('question_id' => $questionid));        
        if ($totres > 0) {
      	   $content .= $DB->get_field('questionnaire_question', 'content', array('position' => '2', 'surveyid' => $surveyid));
            $content .= ' 0%<br>';
        } else { 
           $content = get_string('nocoursevals', $plugin);
        }
    } else {
   	  $qcontent = $DB->get_field('questionnaire_question', 'content', array('position' => '2', 'surveyid' => $surveyid));
        $content = $content .$qcontent . ' ' .$cnt2;    
    }
    return $content;
}
function block_questionreport_get_choice_all($choicename) {
    global $DB, $USER;
    // Get teachers separated by roles.
    $roles = get_config('block_questionreport', 'roles');
    $teacherroles = explode(',', $roles);

    // Get the list of all courses where the user is an instructor and has this question.

    $questlistsql = "SELECT mq.id, mq.extradata, ms.courseid from {questionnaire_survey} ms 
                     JOIN {questionnaire_question} mq on mq.surveyid = ms.id
                     WHERE mq.name = 'Course Ratings' ";  
    $questions = $DB->get_records_sql($questlistsql);

    $qtot = 0;
    // check and see if the user is an instructor;
    foreach($questions as $quest) {
        $qid = $quest->id;
        $courseid = $quest->courseid;
        $valid = false;
        if (!is_siteadmin($USER)) {
             $context = context_course::instance($courseid);
             $roles = get_user_roles($context, $USER->id, true);
             foreach ($roles as $role) {
                if (in_array($role, $teacherroles)) {
                    $valid = true;                
                }              
             }
        } else {
            $valid = true;         
        }           
        if ($valid) {
            $content = $DB->sql_compare_text($choicename);
            $choicesql = "SELECT id FROM {questionnaire_quest_choice} where question_id = ".$qid ." AND content like '%".$content. "%'";          
            $choices = $DB->get_records_sql($choicesql);
            if ($choices) {               
                foreach($choices as $choice) {
                    $curtotal = block_questionreport_get_choice_current($choice->id);
                    $qtot = $qtot + $curtotal;
                }
            } 
        }
    }     
    return $qtot;
}

function block_questionreport_get_courses() {
    global $DB;     
    $plugin = 'block_questionreport';
    $courselist = array();
    $courselist[0] = get_string('all', $plugin);
    $tagvalue = get_config($plugin, 'tag_value');
    $tagid = $DB->get_field('tag', 'id', array('name' => $tagvalue));
    $moduleid = $DB->get_field('modules', 'id', array('name' => 'questionnaire'));
    $sqlcourse = "SELECT m.course, c.id, c.fullname
               FROM {course_modules} m
               JOIN {tag_instance} ti on ti.itemid = m.id
               JOIN {course} c on c.id = m.course
              WHERE m.module = ".$moduleid. "
               AND ti.tagid = ".$tagid . "
               AND m.deletioninprogress = 0
               AND c.visible = 1";

    $coursenames = $DB->get_records_sql($sqlcourse);
    foreach ($coursenames as $coursecert) {
        $courselist[$coursecert->id] = $coursecert->fullname;
    }
    return $courselist;
}

function block_questionreport_get_partners() {
    global $DB;     
    $plugin = 'block_questionreport';
    $courselist = array();
    $courselist[0] = get_string('all', $plugin);
    $sql = 'SELECT tif.id, tif.name, tif.shortname
             FROM {customfield_field} tif
             WHERE type = :type
             ORDER BY tif.sortorder ASC';
 
    $customfields = $DB->get_records_sql($sql, array('type' => 'select'));
    foreach ($customfields as $field) {
        $courselist[$field->id] = $field->name;
    }
    return $courselist;
}

function block_questionreport_get_partners_list() {
    global $DB;     
    $plugin = 'block_questionreport';
    $courselist = array();
    $courselist[0] = get_string('all', $plugin);
    $fieldid = get_config($plugin, 'partnerfield');
    $content = $DB->get_field('customfield_field', 'configdata', array('id' => $fieldid));
    $x = json_decode($content);
    $opts = $x->options;
    $options = preg_split("/\s*\n\s*/", $opts);
    return $options;

}
function block_questionreport_get_question_results($position, $cid, $surveyid, $moduleid, $tagid, $stdate, $nddate, $partner) {
	 // Return the percentage of questions answered with a rank 4, 5;
	 // position is the question #
	 // cid is the current course, if its 0 then its all courses;
	 // surveyid is the surveyid for the selected course. If its all courses, then it will 0;
	 // tagid  is the tagid finding for the matching surveys
	 // stdate start date for the surveys (0 if not used)
	 // nddate end date for the surveys (0 if not used)
	 // partner partner - blank if not used.
    global $DB;
    $retval = 0;
    if ($surveyid > 0) {
        // Get the question id;
        $questionid = $DB->get_field('questionnaire_question', 'id', array('position' => $position, 'surveyid' => $surveyid));
         $totresql  = "SELECT count(rankvalue) ";
           $fromressql = " FROM {questionnaire_response_rank} mr ";
        	  $whereressql = "WHERE mr.question_id = ".$questionid ;
           $paramsql = array();
        	  if ($stdate > 0) {
               $fromressql = $fromressql .' JOIN {questionnaire_response} qr on qr.id = mr.response_id';
               $whereressql = $whereressql . ' AND qr.submitted >= :stdate';
               $std = strtotime($start_date);
               $paramsql['stdate'] = $std;        	  
        	  }
        	  if ($nddate > 0) {
               $fromressql = $fromressql .' JOIN {questionnaire_response} qr2 on qr2.id = mr.response_id';
               $whereressql = $whereressql . ' AND qr2.submitted <= :nddate';
               $ndt = strtotime($end_date);
               $paramsql['nddate'] = $ndt;        	  
        	  }
           $totgoodsql = $totresql .$fromressql. $whereressql;
           $totres = $DB->count_records_sql($totgoodsql, $paramsql);        
        if($totres > 0) {
        	  $totgoodsql  = "SELECT count(rankvalue) ";
        	  $fromgoodsql = " FROM {questionnaire_response_rank} mr ";
        	  $wheregoodsql = "WHERE mr.question_id = ".$questionid ." AND (rankvalue = 4 or rankvalue = 5) ";
        	  $paramsql = array();
        	  if ($stdate > 0) {
               $fromgoodsql = $fromgoodsql .' JOIN {questionnaire_response} qr on qr.id = mr.response_id';
               $wheregoodsql = $wheregoodsql . ' AND qr.submitted >= :stdate';
               $std = strtotime($start_date);
               $paramsql['stdate'] = $std;        	  
        	  }
        	  if ($nddate > 0) {
               $fromgoodsql = $fromgoodsql .' JOIN {questionnaire_response} qr2 on qr2.id = mr.response_id';
               $wheregoodsql = $wheregoodsql . ' AND qr2.submitted <= :nddate';
               $ndt = strtotime($end_date);
               $paramsql['nddate'] = $ndt;        	  
        	  }
        	  $totsql = $totgoodsql .$fromgoodsql. $wheregoodsql;
        	  $paramsql = array();
        	  $totgood = $DB->count_records_sql($totsql, $paramsql);
           if ($totgood > 0) {
               $percent = ($totgood / $totres) * 100;
               $retval = round($percent, 2);
           }  
        }    
    } else  {
    	   // Get all the courses;
    	   $gtres = 0;
    	   $gttotres = 0;
         $sqlcourses = "SELECT m.course, m.id, m.instance
                          FROM {course_modules} m
                          JOIN {tag_instance} ti on ti.itemid = m.id
                         WHERE m.module = ".$moduleid. "
                           AND ti.tagid = ".$tagid . "
                           AND m.deletioninprogress = 0";
        $surveys = $DB->get_records_sql($sqlcourses);
        foreach($surveys as $survey) {
           $sid = $survey->instance;
           $questionid = $DB->get_field('questionnaire_question', 'id', array('position' => $position, 'surveyid' => $sid));
           if (empty($questionid)) {
               $totres = 0;           
           } else {
           
           $totresql  = "SELECT count(rankvalue) ";
           $fromressql = " FROM {questionnaire_response_rank} mr ";
        	  $whereressql = "WHERE mr.question_id = ".$questionid ;
           $paramsql = array();
        	  if ($stdate > 0) {
               $fromressql = $fromressql .' JOIN {questionnaire_response} qr on qr.id = mr.response_id';
               $whereressql = $whereressql . ' AND qr.submitted >= :stdate';
               $std = strtotime($start_date);
               $paramsql['stdate'] = $std;        	  
        	  }
        	  if ($nddate > 0) {
               $fromressql = $fromressql .' JOIN {questionnaire_response} qr2 on qr2.id = mr.response_id';
               $whereressql = $whereressql . ' AND qr2.submitted <= :nddate';
               $ndt = strtotime($end_date);
               $paramsql['nddate'] = $ndt;        	  
        	  }
           $totgoodsql = $totresql .$fromressql. $whereressql;
           $totres = $DB->count_records_sql($totgoodsql, $paramsql);
        }
           if($totres > 0) {
           	  $gtres = $gtres + $totres;
          	  $totgoodsql  = "SELECT count(rankvalue) ";
         	  $fromgoodsql = " FROM {questionnaire_response_rank} mr ";
         	  $wheregoodsql = "WHERE mr.question_id = ".$questionid ." AND (rankvalue = 4 or rankvalue = 5) ";
          	  $paramsql = array();
        	     if ($stdate > 0) {
                  $fromgoodsql = $fromgoodsql .' JOIN {questionnaire_response} qr on qr.id = mr.response_id';
                  $wheregoodsql = $wheregoodsql . ' AND qr.submitted >= :stdate';
                  $std = strtotime($start_date);
                  $paramsql['stdate'] = $std;        	  
        	     }
        	     if ($nddate > 0) {
                  $fromgoodsql = $fromgoodsql .' JOIN {questionnaire_response} qr2 on qr2.id = mr.response_id';
                  $wheregoodsql = $wheregoodsql . ' AND qr2.submitted <= :nddate';
                  $ndt = strtotime($end_date);
                  $paramsql['nddate'] = $ndt;        	  
        	     }
     	        $totsql = $totgoodsql .$fromgoodsql. $wheregoodsql;
          	  $totgood = $DB->count_records_sql($totsql, $paramsql);
              if ($totgood > 0) {
                  $gttotres = $gttotres + $totgood;        
              }  
           }
        }
        if ($gttotres > 0) {
            $percent = ($gttotres / $gtres) * 100;
            $retval = round($percent, 2);

        }
}
    
    return $retval;
}
