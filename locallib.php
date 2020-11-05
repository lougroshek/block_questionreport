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

function block_questionreport_check_has_choices($choiceid) {
    global $DB;
    $recsql = "SELECT count(id) from {questionnaire_response_rank} where choice_id = ".$choiceid;
    $recs = $DB->count_records_sql($recsql); 
    // Total the results from this course for this choice.
    return $recs;
}

/**
 * Checks whether user has the designated role in the course.
 */
function block_questionreport_is_teacher() {
    global $USER, $COURSE;
    $roles = get_config('block_questionreport', 'roles');
    $teacherroles = explode(',', $roles);
        
    $valid = false;
    if (!is_siteadmin($USER)) {
        $courseid = $COURSE->id;
        $context = context_course::instance($courseid);
        $userroles = get_user_roles($context, $USER->id, true);
        foreach ($userroles as $role) {
            $rid = $role->roleid;
            if (in_array($rid, $teacherroles)) {
                $valid = true;
            }
        }
    } else {
        $valid = true;         
    }
    return $valid;
}

function block_questionreport_is_admin() {
    global $USER;
    return is_siteadmin($USER);
}

function block_questionreport_get_evaluations() {

    global $DB, $CFG, $COURSE, $PAGE, $OUTPUT; 
    $plugin = 'block_questionreport';
    
    // The object we will pass to mustache.
    $data = new stdClass();
    
    // Does the current course have results to display? 
    $has_responses_contentq = true;
    $has_responses_commq = true;
    
    // Is the user a teacher or an admin?
    $is_admin = block_questionreport_is_admin();
    $is_teacher = block_questionreport_is_teacher();
    if (!$is_admin && !$is_teacher) {
        return;
    }
    
    // Add buttons object.
    $data->buttons = new stdClass();
    // Build reports button object.
    $reports = new stdClass();
    $reports->text = get_string('reports', $plugin);
    $reports->href = $CFG->wwwroot.'/blocks/questionreport/report.php?action=view&cid='.$COURSE->id;
    $data->buttons->reports = $reports;
    // Conditionally add charts button object.
    if (!!$is_admin) {
        // echo 'user is admin';
        $data->role = 'admin';
        // Build charts object.
        $charts = new stdClass();
        $charts->text = get_string('charts', $plugin);
        $charts->href = $CFG->wwwroot.'/blocks/questionreport/charts.php?action=view&cid='.$COURSE->id;
        $data->buttons->charts = $charts;
        // Build admin reports button object.
        $adminreports = new stdClass();
        $adminreports->text = get_string('adminreports', $plugin);
        $adminreports->href = $CFG->wwwroot.'/blocks/questionreport/adminreport.php?action=view&cid='.$COURSE->id;
        $data->buttons->adminreports = $adminreports;
    } 
    if (!!$is_teacher) {
        $data->role = 'teacher';
    }
    
    // Objects for the question and percent display.
    $contentq = new stdClass();
    $contentq->desc = get_string('contentq_desc', $plugin);
    $contentq->stat = null;
    
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
    if (!$surveys) {
        return 'no surveys done';    
    }
    $surveyid = $surveys->instance;
    $params = array();
    // Get the first instructor question - type 11.
    $sql = 'select min(position) mp from {questionnaire_question} where surveyid = '.$surveyid .' and type_id = 11 order by position desc';
    $records = $DB->get_record_sql($sql, $params);
    $stp = $records->mp;
    $cnt = block_questionreport_get_question_results($stp, $cid, $surveyid, $moduleid, $tagid, 0, 0, '');
    if ($cnt == '-') {
    	  $questionid = $DB->get_field('questionnaire_question', 'id', array('position' => $stp, 'surveyid' => $surveyid));
        $totres = $DB->count_records('questionnaire_response_rank', array('question_id' => $questionid));        
        if ($totres > 0) {
            $contentq->stat = 0;
        } else {
            $has_responses_contentq = false;
        }
    } else {
       $contentq->stat = $cnt;
    }
    
    if ($has_responses_contentq) {
        // Object for question 2 text and value.
        $commq = new stdClass();
        $commq->desc = get_string('commq_desc', $plugin);
        $commq->stat = null;
        $stp = $stp + 1;    
        $cnt2 = block_questionreport_get_question_results($stp, $cid, $surveyid, $moduleid, $tagid, 0, 0, '');
        if ($cnt2 == '-') {
   	      $questionid = $DB->get_field('questionnaire_question', 'id', array('position' => $stp, 'surveyid' => $surveyid));
            $totres = $DB->count_records('questionnaire_response_rank', array('question_id' => $questionid));        
            if ($totres > 0) {
                $commq->stat = 0;
            } else { 
                $has_responses_commq = false;
            }
        } else {
            $commq->stat = $cnt2;  
        }
        // echo '<p>$commq</p>';
    // print_r($commq);
    }
    // Insert data into object if content responses exist.
    if (!!$has_responses_contentq) {
        $data->contentq = $contentq;
    }
    // Insert data into object if community responses exist.
    if (!!$has_responses_commq) {
    	  if (!empty($commq)) {
            $data->commq = $commq;
        }
    }
    // If no response data, add no response string to data.
    if (!$has_responses_contentq && !$has_responses_contentq) {
        $data->no_responses = get_string('nocoursevals', $plugin);
    } else {
        $data->has_responses = true;
    }
        
    // Return rendered template.
    return $OUTPUT->render_from_template('block_questionreport/initial', $data);
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
    global $DB, $USER;     
    $plugin = 'block_questionreport';
    $courselist = array();
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
    	 $valid = false;
	    if (is_siteadmin() ) {
           $valid = true;	    
	    } else {
          $context = context_course::instance($coursecert->id);
           
	       if (has_capability('moodle/question:editall', $context, $USER->id, false)) {
              $valid = true;	       
	       }    
	    }
	    if ($valid) {
           $courselist[$coursecert->id] = $coursecert->fullname;
       }
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
    	  $fid = $field->id;
    	  $fid = $fid + 1;
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
    $options = array();   
    $x = json_decode($content);       
    $opts = $x->options;
    $options = preg_split("/\s*\n\s*/", $opts);
    return $options;

}
function block_questionreport_get_question_results_rank($questionid, $choiceid, $cid, $surveyid, $moduleid, $tagid, $stdate, $nddate, $partner) {
	 // Return the percentage of questions answered with a rank 4, 5;
	 // questionid  question #
	 // choice id is the choice id for a specific survey. For all courses then which choice option.
	 // cid is the current course, if its 0 then its all courses;
	 // surveyid is the surveyid for the selected course. If its all courses, then it will 0;
	 // tagid  is the tagid finding for the matching surveys
	 // stdate start date for the surveys (0 if not used)
	 // nddate end date for the surveys (0 if not used)
	 // partner partner - blank if not used.
    global $DB, $USER;
    $plugin = 'block_questionreport';
    $retval = get_string('none', $plugin);
    $partnersql = '';
    if ($partner > '') {
    	  $comparevalue = $DB->sql_compare_text($partner);
        $partnerid = get_config($plugin, 'partnerfield');
        $comparevalue = $comparevalue + 1;
        $partnersql = 'JOIN {customfield_data} cd ON cd.instanceid = m.course AND cd.fieldid = '.$partnerid .' AND cd.value = '.$comparevalue;
    }
    if ($surveyid > 0) {
        $totresql  = "SELECT count(rankvalue) ";
        $fromressql = " FROM {questionnaire_response_rank} mr ";
        $whereressql = "WHERE mr.question_id = ".$questionid ." AND choice_id = ".$choiceid;
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
           $totgoodsql = $totresql .' '.$fromressql. ' '.$whereressql;
           
           $totres = $DB->count_records_sql($totgoodsql, $paramsql);        
           if ($totres > 0) {
        	      $totgoodsql  = "SELECT count(rankvalue) ";
        	      $fromgoodsql = " FROM {questionnaire_response_rank} mr ";
        	      $wheregoodsql = "WHERE mr.question_id = ".$questionid ." AND choice_id = ".$choiceid. " AND (rankvalue = 4 or rankvalue = 5) ";
        	      $paramsql = array();
        	      if ($stdate > 0) {
                   $fromgoodsql = $fromgoodsql .' JOIN {questionnaire_response} qr on qr.id = mr.response_id';
                   $wheregoodsql = $wheregoodsql . ' AND qr.submitted >= :stdate';
                   $std = strtotime($stdate);
                   $paramsql['stdate'] = $std;        	  
        	      }
        	      if ($nddate > 0) {
                   $fromgoodsql = $fromgoodsql .' JOIN {questionnaire_response} qr2 on qr2.id = mr.response_id';
                   $wheregoodsql = $wheregoodsql . ' AND qr2.submitted <= :nddate';
                   $ndt = strtotime($nddate);
                   $paramsql['nddate'] = $ndt;        	  
        	      }
        	      $totsql = $totgoodsql .' '.$fromgoodsql. ' '.$wheregoodsql;
        	      $totgood = $DB->count_records_sql($totsql, $paramsql);
               if ($totgood > 0) {
                   $percent = ($totgood / $totres) * 100;
                   $retval = round($percent, 2)."(%)";
               } else {
                   $retval = "0(%)"; 
               } 
           }    
    } else  {
    	   // Get all the courses;
    	   $gtres = 0;
    	   $gttotres = 0;
         $sqlcourses = "SELECT m.course, m.id, m.instance
                          FROM {course_modules} m
                          JOIN {tag_instance} ti on ti.itemid = m.id " .$partnersql. "                          
                         WHERE m.module = ".$moduleid. "
                           AND ti.tagid = ".$tagid . "
                           AND m.deletioninprogress = 0";
         $surveys = $DB->get_records_sql($sqlcourses);
         foreach($surveys as $survey) {
           // Check to see if the user has rights.
           $valid = false;
           if (is_siteadmin() ) {
               $valid = true;	    
	        } else {
               $context = context_course::instance($survey->course);
               if (has_capability('moodle/question:editall', $context, $USER->id, false)) {
                   $valid = true;	       
	            }    	            
	        }	
           $sid = $survey->instance;
           $qid = $DB->get_field('questionnaire_question', 'id', array('position' => '1', 'surveyid' => $sid, 'type_id' => '8'));
           if (empty($qid) or !$valid) {
              $totres = 0;           
           } else { 
              $choices = $DB->get_records('questionnaire_quest_choice', array('question_id' => $qid));
              $cnt = 0;              
              foreach ($choices as $choice) {          
                 $chid = $choice->id;
                 $cnt = $cnt + 1;
                 if ($cnt == $choiceid) {
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
             }  
           
             if($totres > 0) {
           	    $gtres = $gtres + $totres;
          	    $totgoodsql  = "SELECT count(rankvalue) ";
         	    $fromgoodsql = " FROM {questionnaire_response_rank} mr ";
         	    $wheregoodsql = "WHERE mr.question_id = ".$qid ." AND choice_id =".$chid." AND (rankvalue = 4 or rankvalue = 5) ";
          	    $paramsql = array();
        	       if ($stdate > 0) {
                    $fromgoodsql = $fromgoodsql .' JOIN {questionnaire_response} qr on qr.id = mr.response_id';
                    $wheregoodsql = $wheregoodsql . ' AND qr.submitted >= :stdate';
                    $std = strtotime($stdate);
                    $paramsql['stdate'] = $std;        	  
        	       }
        	       if ($nddate > 0) {
                    $fromgoodsql = $fromgoodsql .' JOIN {questionnaire_response} qr2 on qr2.id = mr.response_id';
                    $wheregoodsql = $wheregoodsql . ' AND qr2.submitted <= :nddate';
                    $ndt = strtotime($nddate);
                    $paramsql['nddate'] = $ndt;        	  
        	       }
     	          $totsql = $totgoodsql .' '.$fromgoodsql. ' '.$wheregoodsql;
          	    $totgood = $DB->count_records_sql($totsql, $paramsql);
                if ($totgood > 0) {
                    $gttotres = $gttotres + $totgood;        
                }               
                
            } 
        }
        if ($gtres > 0) {
            if ($gttotres > 0) {
                $percent = ($gttotres / $gtres) * 100;
                $retval = round($percent, 2)."(%)";
            } else {
               $retval = "0(%)";                    
            }
        } else {
            $retval = get_string('none', $plugin);        
        }
}
    
    return $retval;  

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
    global $DB, $USER;
    $plugin = 'block_questionreport';
    $retval = get_string('none', $plugin);
    $partnersql = '';
    if ($partner > '') {
    	  $comparevalue = $DB->sql_compare_text($partner);
        $partnerid = get_config($plugin, 'partnerfield');
        $comparevalue = $comparevalue + 1;
        $partnersql = 'JOIN {customfield_data} cd ON cd.instanceid = m.course AND cd.fieldid = '.$partnerid .' AND cd.value = '.$comparevalue;
    }
    if ($surveyid > 0) {
        // Get the question id;
         $questionid = $DB->get_field('questionnaire_question', 'id', array('position' => $position, 'surveyid' => $surveyid, 'type_id' => 11));         
         $totresql  = "SELECT count(rankvalue) ";
           $fromressql = " FROM {questionnaire_response_rank} mr ";
        	  $whereressql = "WHERE mr.question_id = ".$questionid ;
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
           $totgoodsql = $totresql .' '.$fromressql. ' '.$whereressql;
           
           $totres = $DB->count_records_sql($totgoodsql, $paramsql);
           if ($totres > 0) {
        	      $totgoodsql  = "SELECT count(rankvalue) ";
        	      $fromgoodsql = " FROM {questionnaire_response_rank} mr ";
        	      $wheregoodsql = "WHERE mr.question_id = ".$questionid ." AND (rankvalue = 4 or rankvalue = 5) ";
        	      $paramsql = array();
        	      if ($stdate > 0) {
                   $fromgoodsql = $fromgoodsql .' JOIN {questionnaire_response} qr on qr.id = mr.response_id';
                   $wheregoodsql = $wheregoodsql . ' AND qr.submitted >= :stdate';
                   $std = strtotime($stdate);
                   $paramsql['stdate'] = $std;        	  
        	      }
        	      if ($nddate > 0) {
                   $fromgoodsql = $fromgoodsql .' JOIN {questionnaire_response} qr2 on qr2.id = mr.response_id';
                   $wheregoodsql = $wheregoodsql . ' AND qr2.submitted <= :nddate';
                   $ndt = strtotime($nddate);
                   $paramsql['nddate'] = $ndt;        	  
        	      }
        	      $totsql = $totgoodsql .' '.$fromgoodsql. ' '.$wheregoodsql;
        	      $totgood = $DB->count_records_sql($totsql, $paramsql);
               if ($totgood > 0) {
                   $percent = ($totgood / $totres) * 100;
                   $retval = round($percent, 2)."(%)";
               } else { 
                   $retval = "0(%)";
               } 
           }    
    } else  {
    	   // Get all the courses;
    	   $gtres = 0;
    	   $gttotres = 0;
         $sqlcourses = "SELECT m.course, m.id, m.instance
                          FROM {course_modules} m
                          JOIN {tag_instance} ti on ti.itemid = m.id " .$partnersql. "                          
                         WHERE m.module = ".$moduleid. "
                           AND ti.tagid = ".$tagid . "
                           AND m.deletioninprogress = 0";
         $surveys = $DB->get_records_sql($sqlcourses);
         foreach($surveys as $survey) {
           // Check to see if the user has rights.
           $valid = false;
           if (is_siteadmin() ) {
               $valid = true;	    
	        } else {
               $context = context_course::instance($survey->course);
               if (has_capability('moodle/question:editall', $context, $USER->id, false)) {
                   $valid = true;	       
	            }    	            
	        }	
           $sid = $survey->instance;
           $questionid = $DB->get_field('questionnaire_question', 'id', array('position' => $position, 'surveyid' => $sid, 'type_id' => 11));
           if (empty($questionid) or !$valid) {
              $totres = 0;           
           } else {           
              $totresql  = "SELECT count(rankvalue) ";
              $fromressql = " FROM {questionnaire_response_rank} mr ";
        	     $whereressql = "WHERE mr.question_id = ".$questionid ;
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
                  $std = strtotime($stdate);
                  $paramsql['stdate'] = $std;        	  
        	     }
        	     if ($nddate > 0) {
                  $fromgoodsql = $fromgoodsql .' JOIN {questionnaire_response} qr2 on qr2.id = mr.response_id';
                  $wheregoodsql = $wheregoodsql . ' AND qr2.submitted <= :nddate';
                  $ndt = strtotime($nddate);
                  $paramsql['nddate'] = $ndt;        	  
        	     }
     	        $totsql = $totgoodsql .' '.$fromgoodsql. ' '.$wheregoodsql;
          	  $totgood = $DB->count_records_sql($totsql, $paramsql);
              if ($totgood > 0) {
                  $gttotres = $gttotres + $totgood;        
              }  
           }
        }
        if ($gtres > 0) {
            if ($gttotres > 0) {
                $percent = ($gttotres / $gtres) * 100;
                $retval = round($percent, 2)."(%)";
            } else {
                $retval = "0(%)";                    
            }
        } else {
            $retval = get_string('none', $plugin);        
        }
}
    
    return $retval;  

}
function block_questionreport_get_essay($surveyid) {
    global $DB, $COURSE;  
    $plugin = 'block_questionreport';
    $essaylist = array();
    $essaylist[0] = get_string('none', $plugin);
    $customfields = $DB->get_records('questionnaire_question', array('type_id' => '3', 'surveyid' => $surveyid));
    foreach ($customfields as $field) {
    	  $content = $field->content;
    	  $display = strip_tags($content);
    	  $display = trim($display);
        $essaylist[$field->id] = $display;
    }
    return $essaylist;
}

function block_questionreport_get_essay_results($questionid, $stdate, $nddate, $limit) {
    global $DB, $COURSE;
    // If limit = 0 return all essay results. Otherwise return the limit.
    $sqlessay  = "SELECT qt.response, qt.id ";
    $fromessaysql = " FROM {questionnaire_response_text} qt ";
    $whereessaysql = "WHERE qt.question_id = ".$questionid;
    $paramsql = array();
    if ($stdate > 0) {
        $fromessaysql = $fromessaysql .' JOIN {questionnaire_response} qr on qr.id = qt.response_id';
        $whereessaysql = $whereessaysql . ' AND qr.submitted >= :stdate';
        $std = strtotime($stdate);
        $paramsql['stdate'] = $std;        	  
     }
     if ($nddate > 0) {
        $fromessaysql = $fromessaysql .' JOIN {questionnaire_response} qr2 on qr2.id = qt.response_id';
        $whereessaysql = $whereessaysql . ' AND qr2.submitted <= :nddate';
        $ndt = strtotime($nddate);
        $paramsql['nddate'] = $ndt;        	  
     }
     $sql = $sqlessay .' '.$fromessaysql. ' '.$whereessaysql;
     $arrayid = array();     
     $resultlist = $DB->get_records_sql($sql, $paramsql);
     foreach($resultlist as $result) {
         $arrayid[] = $result->id;
     }
     $return = [];
     if (!empty($arrayid)) {
         shuffle($arrayid);
         $cnt = 0;
         foreach($arrayid as $resid) {
            $cr = $DB->get_field('questionnaire_response_text','response', array('id' => $resid));
    	      $return[] = str_replace("&nbsp;", '', trim(strip_tags($cr)));   
    	      $cnt = $cnt + 1;
    	      if ($limit > 0 and $limit > $cnt) {
                break;    	   
    	      }
        }   
     }
     return $return;
}

function block_questionreport_get_words($surveyid, $stdate, $nddate) {
    global $DB;
    
    $words = [];
    $customfields = $DB->get_records('questionnaire_question', array('type_id' => '3', 'surveyid' => $surveyid));
    foreach ($customfields as $field) {
    	   $questionid = $field->id;
    	   array_push($words, block_questionreport_get_essay_results($questionid, $stdate, $nddate, 0));
    }
    
    $popwords = calculate_word_popularity($words, 4);
    return $popwords;
}

function calculate_word_popularity($word_arrs, $min_word_char = 2, $exclude_words = array()) {
   $words = [];
   
   foreach ($word_arrs as $w) {
       foreach($w as $val) {
           $wrds = explode(' ', $val);
           foreach($wrds as $z) {
               array_push($words, $z);
           }
       }
   }
   
   // echo '<br />$words<br />';
   // print_r($words);
   
	$result = array_combine($words, array_fill(0, count($words), 0));

   foreach($words as $word) {
       $result[$word]++;
   }
   
   // echo '<br />$result<br />';
   // print_r($result);
   
   $ret = array();   
   $total_words = 0;
   foreach($result as $word => $count) {
	 $stl = strlen($word);
     $wd = new stdClass();
	 if ($stl > $min_word_char) {
         $total_words = $total_words + $count;
         $wd->word = str_replace("&nbsp;", '', $word);
         $wd->count = $count;
         array_push($ret, $wd);
	 }
   }
   
   // echo '<br />$ret<br />';
   // print_r($ret);

   $return = [];
   foreach($ret as $word) {
       $word->percent = round($word->count/$total_words * 100, 2);
       array_push($return, $word);
   }
   // echo '<br />$return<br />';
   // print_r($return);
   return $return;
   //   echo "There are $count instances of $word.\n";
}
function block_questionreport_get_question_results_percent($questionid, $choiceid, $cid, $surveyid, $moduleid, $tagid, $stdate, $nddate, $partner) {
	 // Return the percentage of questions answered with a rank 4, 5;
	 // questionid  question #
	 // choice id is the choice id for a specific survey. For all courses then which choice option.
	 // cid is the current course, if its 0 then its all courses;
	 // surveyid is the surveyid for the selected course. If its all courses, then it will 0;
	 // tagid  is the tagid finding for the matching surveys
	 // stdate start date for the surveys (0 if not used)
	 // nddate end date for the surveys (0 if not used)
	 // partner partner - blank if not used.
    global $DB, $USER;
    $plugin = 'block_questionreport';
    $retval = get_string('none', $plugin);
    $partnersql = '';
    if ($partner > '') {
    	  $comparevalue = $DB->sql_compare_text($partner);
    	  $comparevalue = $comparevalue + 1;
        $partnerid = get_config($plugin, 'partnerfield');
        $partnersql = 'JOIN {customfield_data} cd ON cd.instanceid = m.course AND cd.fieldid = '.$partnerid .' AND cd.value = '.$comparevalue;
    }
    if ($surveyid > 0) {
        $totresql  = "SELECT count(rankvalue) ";
        $fromressql = " FROM {questionnaire_response_rank} mr ";
        $whereressql = "WHERE mr.question_id = ".$questionid ." AND choice_id = ".$choiceid;
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
           $totgoodsql = $totresql .' '.$fromressql. ' '.$whereressql;
           
           $totres = $DB->count_records_sql($totgoodsql, $paramsql);        
           if ($totres > 0) {
        	      $totgoodsql  = "SELECT sum(rankvalue) sr ";
        	      $fromgoodsql = " FROM {questionnaire_response_rank} mr ";
        	      $wheregoodsql = "WHERE mr.question_id = ".$questionid ." AND choice_id = ".$choiceid;
        	      $paramsql = array();
        	      if ($stdate > 0) {
                   $fromgoodsql = $fromgoodsql .' JOIN {questionnaire_response} qr on qr.id = mr.response_id';
                   $wheregoodsql = $wheregoodsql . ' AND qr.submitted >= :stdate';
                   $std = strtotime($stdate);
                   $paramsql['stdate'] = $std;        	  
        	      }
        	      if ($nddate > 0) {
                   $fromgoodsql = $fromgoodsql .' JOIN {questionnaire_response} qr2 on qr2.id = mr.response_id';
                   $wheregoodsql = $wheregoodsql . ' AND qr2.submitted <= :nddate';
                   $ndt = strtotime($end_date);
                   $paramsql['nddate'] = $ndt;        	  
        	      }
        	      $totsql = $totgoodsql .' '.$fromgoodsql. ' '.$wheregoodsql;
        	      $trsql = $DB->get_record_sql($totsql, $paramsql);
        	      $totgood = $trsql->sr;
               if ($totgood > 0) {
                   $percent = ($totgood / $totres) * 100;
                   $retval = round($percent, 2)."(%)";
               }  
           }    
    } else  {
    	   // Get all the courses;
    	   $gtres = 0;
    	   $gttotres = 0;
         $sqlcourses = "SELECT m.course, m.id, m.instance
                          FROM {course_modules} m
                          JOIN {tag_instance} ti on ti.itemid = m.id " .$partnersql. "                          
                         WHERE m.module = ".$moduleid. "
                           AND ti.tagid = ".$tagid . "
                           AND m.deletioninprogress = 0";
         $surveys = $DB->get_records_sql($sqlcourses);
         foreach($surveys as $survey) {
           // Check to see if the user has rights.
           $valid = false;
           if (is_siteadmin() ) {
               $valid = true;	    
	        } else {
               $context = context_course::instance($survey->course);
               if (has_capability('moodle/question:editall', $context, $USER->id, false)) {
                   $valid = true;	       
	            }    	            
	        }	
           $sid = $survey->instance;
           $qid = $DB->get_field('questionnaire_question', 'id', array('position' =>'9', 'surveyid' => $sid, 'type_id' => '8'));
           if (empty($qid) or !$valid) {
              $totres = 0;           
           } else { 
              $choices = $DB->get_records('questionnaire_quest_choice', array('question_id' => $qid));
              $cnt = 0;              
              foreach ($choices as $choice) {          
                 $chid = $choice->id;
                 $cnt = $cnt + 1;
                 if ($cnt == $choiceid) {
                     break;                                      
                 }
              }
                 $totresql  = "SELECT count(rankvalue)" ;
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
             }  
           
             if($totres > 0) {
           	    $gtres = $gtres + $totres;
          	    $totgoodsql  = "SELECT sum(rankvalue) src";
         	    $fromgoodsql = " FROM {questionnaire_response_rank} mr ";
         	    $wheregoodsql = "WHERE mr.question_id = ".$qid ." AND choice_id =".$chid;
          	    $paramsql = array();
        	       if ($stdate > 0) {
                    $fromgoodsql = $fromgoodsql .' JOIN {questionnaire_response} qr on qr.id = mr.response_id';
                    $wheregoodsql = $wheregoodsql . ' AND qr.submitted >= :stdate';
                    $std = strtotime($stdate);
                    $paramsql['stdate'] = $std;        	  
        	       }
        	       if ($nddate > 0) {
                    $fromgoodsql = $fromgoodsql .' JOIN {questionnaire_response} qr2 on qr2.id = mr.response_id';
                    $wheregoodsql = $wheregoodsql . ' AND qr2.submitted <= :nddate';
                    $ndt = strtotime($nddate);
                    $paramsql['nddate'] = $ndt;        	  
        	       }
     	          $totgoodsql = $totgoodsql .' '.$fromgoodsql. ' '.$wheregoodsql;
     	          
          	    $trsql = $DB->get_record_sql($totgoodsql, $paramsql);
          	    $totgood = $trsql->src;
                if ($totgood > 0) {
                    $gttotres = $gttotres + $totgood;        
                }  
           }
        }
        if ($gttotres > 0) {
            $percent = ($gttotres / $gtres) * 100;
            $retval = round($percent, 2)."(%)";

        }
}
    
    return $retval;  

}
