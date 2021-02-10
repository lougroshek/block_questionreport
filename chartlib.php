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
    $options = array();
    $fieldid = get_config($plugin, 'portfoliofield');
    $content = $DB->get_field('customfield_field', 'configdata', array('id' => $fieldid));
    
    $x = json_decode($content);
    $opts = $x->options;
    $options_old = preg_split("/\s*\n\s*/", $opts);
    $x = 1;
    foreach($options_old as $val) {
       $options[$x] = $val;
       $x = $x + 1;    
    }
    return $options;

}

function block_questionreport_get_teachers_list() {
    global $DB;
    $plugin = 'block_questionreport';
    $roles = get_config('block_questionreport', 'roles');
    $teacherlist = array();
    $teachersql = "SELECT distinct(userid) usersid, lastname, firstname
                   FROM {role_assignments} ra, mdl_user as u
                   WHERE u.id = ra.userid and ra.roleid in (".$roles.")
                   order by lastname, firstname";
    $teacherfields = $DB->get_records_sql($teachersql);
    foreach ($teacherfields as $field) {
        $teacherlist[$field->usersid] = $field->firstname. " ". $field->lastname;
    }
    $dbman = $DB->get_manager();
    if ($dbman->table_exists('local_teaching_teacher')) {
        $altcourses = $DB->get_records('local_teaching_teacher');
        foreach($altcourses as $alt) {
           $teacherlist[$alt->id] = $alt->teachername;
        }
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

function block_questionreport_get_adminreport($ctype, $surveytype, $cid, $partner, $portfolio,
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
    $content = array();
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
    if ($questionid < 10 or $questionid > 20) {
        $qname = $DB->get_field('questionnaire_question', 'name', array('id' => $questionid));
        $nquestionid = $questionid;
    } else {
    	  $nquestionid = $questionid;
    	  $choiceid = -1;
        $questionid = 0; 
        $qname = 'course_ratings';
        $sqladmin = "select qr.id, rankvalue response , qu.course courseid, content, q.submitted
                      from {questionnaire_quest_choice} qc 
                      join {questionnaire_response_rank} qr on qr.question_id = qc.question_id 
                      join {questionnaire_response} q on q.id = qr.response_id and qc.id = qr.choice_id 
                      JOIN {questionnaire} qu on qu.id = q.questionnaireid
                      where content = '";    	  
        switch($nquestionid) {
        	  case '10':
             $nqname = 'I am satisfied with the overall quality of this course.';
        	    break;
        	  case '11' :    
        	    $nqname = 'The topics for this course were relevant for my role.';
             break;
           case '12':  
             $nqname = 'The independent online work activities were well-designed to help me meet the learning targets.';
             break;
           case '13' :      
             $nqname = 'The Zoom meeting activities were well-designed to help me meet the learning targets.';
             break;
           case '14':  
             $nqname = 'I felt a sense of community with the other participants in this course even though we were meeting virtually.';
             break;
          case '15' :       
            $nqname = 'This course helped me navigate remote and/or hybrid learning during COVID-19.';
            break;
          case '16':       
            $nqname = 'I will apply my learning from this course to my practice in the next 4-6 weeks.';
            break;
          case '17':  
            $nqname = 'Recommend this course to a colleague or friend.';
            break;
         case '18':
            $nqname = 'facilitator_rate_content';
            $sqladmin = "SELECT qr.id, rankvalue response, qr.submitted, qq.content, course courseid
                           FROM {questionnaire_response_rank} mr 
                           JOIN {questionnaire_question} qq ON qq.id = mr.question_id 
                           JOIN {questionnaire_response} qr on qr.id = mr.response_id 
                           JOIN {questionnaire} qu on qu.id = qr.questionnaireid
                          WHERE qq.name = '".$nqname;
             $nqname = "";                  
                  
            break;
         case '19':   
            $nqname = 'facilitator_rate_community';
            $sqladmin = "SELECT qr.id, rankvalue response, qr.submitted, qq.content, course courseid
                           FROM {questionnaire_response_rank} mr 
                           JOIN {questionnaire_question} qq ON qq.id = mr.question_id 
                           JOIN {questionnaire_response} qr on qr.id = mr.response_id 
                           JOIN {questionnaire} qu on qu.id = qr.questionnaireid
                          WHERE qq.name = '".$nqname;
             $nqname = "";                  
            break;
          }
          $sqladmin = $sqladmin .$nqname."'";
    
    } 
    if ($ctype == 'M' or $questionid > 20)  {
        // Get the question id for Non Moodle courses;
       switch($qname) {
       	case 'additional_comments_text':
       	  $nquestionid = "7";
       	  break;
       	case 'takeaway_text':
       	  $nquestionid = "1";
       	  break;
       	case 'covid_prepare_text':
       	  $nquestionid = "2";
       	  break;
      	case 'went_well_text':
       	  $nquestionid = "3";
       	  break;
       	case 'supported_text':
       	  $nquestionid = "4";
       	  break;
       	case 'improve_experience_text':
       	  $nquestionid = "5";
       	  break;
       	case 'why_NPS':
       	  $nquestionid = "6";
       	  break;
       }	              
    }
    $orderby = "ORDER BY ID";
    if ($choiceid == 0) {
        $sqladmin = "SELECT qt.id qtid, qq.id, qq.surveyid, qt.response, qr.userid, qr.submitted, qs.courseid, qq.content 
                       FROM {questionnaire_question} qq
                       JOIN {questionnaire_response_text} qt on qt.question_id = qq.id
                       JOIN {questionnaire_response} qr on qr.id = qt.response_id
                       JOIN {questionnaire_survey} qs on qs.id = qq.surveyid
                      WHERE qq.name = :qname and qr.complete = 'y'";
          $paramsql = array('qname' => $qname);
    } else {
    	    if ($choiceid > 1 ) { 
              $sqladmin = "SELECT mr.id, mr.rankvalue response, qq.surveyid, qr.userid, qs.courseid, qr.submitted
                            FROM {questionnaire_response_rank} mr
                            JOIN {questionnaire_question} qq on qq.id = mr.question_id
                            JOIN {questionnaire_survey} qs on qs.id = qq.surveyid
                            JOIN {questionnaire_response} qr on qr.id = mr.response_id
                           WHERE mr.question_id = :questionid
                            AND qr.complete = 'y'
                            AND choice_id = :choiceid";
            $paramsql = array ('questionid' => $questionid, 'choiceid' => $choiceid);
         }         
    }
    if ($questionid == '10') {
        $sqladmin = "SELECT qt.id qtid, qq.id, qq.surveyid, qr.userid, qt.response, qr.submitted, qs.courseid, qq.content, qq.name
                     FROM {questionnaire_question} qq
                     JOIN {questionnaire_response_text} qt ON qt.question_id = qq.id
                     JOIN {questionnaire_response} qr ON qr.id = qt.response_id
                     JOIN {questionnaire_survey} qs ON qs.id = qq.surveyid
                     WHERE (qq.name = 'takeaway_text' )";
        $orderby = "ORDER BY userid";
    }
    if ($stdate > 0) {
        $sqladmin = $sqladmin . ' AND qr.submitted >= :stdate';
        $std = strtotime($stdate);
        $paramsql['stdate'] = $std;
    }
    if ($nddate > 0) {
        $sqladmin = $sqladmin . ' AND qr.submitted <= :nddate';
        $ndt = strtotime($nddate);
        $paramsql['nddate'] = $ndt;
    }
    if ($cid > 0) {
    	  if ($choiceid > -1) {
             $sqladmin = $sqladmin . ' AND qs.courseid = :courseid';
        } else {
             $sqladmin = $sqladmin . ' AND qu.course = :courseid';        
        }     
        $paramsql['courseid'] = $cid;
    }
    $sqladmin = $sqladmin. ' '.$orderby;
    $results = $DB->get_records_sql($sqladmin, $paramsql);
    $displaycnt = 0;
    $display = true;
    if ($action == 'csv') {
        $display = false;
    }
    $maxdisplay = 10;
    $allquest = 0;
    // Non Moodle courses
    if ($questionid == 10) {
        $allquest = "1";
        $questionid = "1";
        $nquestionid = "1";
    }
    if ($action == 'csv') {
        $rowheaders = array('date','partner', 'portfolio','teacher', 'course', 'question', 'response');
        if ($allquest == "1") {
            $rowheaders = array('date','partner', 'portfolio','teacher', 'course', 'question1', 'response1', 'question2', 'response2',
                                'question3', 'response3', 'question4', 'response4', 'question5', 'response5', 'question6', 'response6',
                                'question7', 'response7');
        }
    }
    $output = array();
    $content = [];
    $var = array();
    $olduserid = 0;
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
           if (isset($options[$pf])) {
               $portdisplay = $options[$pf];
           } else {
              $portdisplay = $na;
           }
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
       $sqlteacher = "SELECT u.id, u.firstname, u.lastname
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
           if ($choiceid == 0 or $choiceid < 0)  {
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
               if ($allquest == "1") {
                   // Get all the questions.
               	   $user = $result->userid;
                   $sql2 = "SELECT qt.id qtid, qq.id, qq.surveyid, qr.userid, qt.response, qr.submitted, qs.courseid, qq.content, qq.name
                              FROM {questionnaire_question} qq
                              JOIN {questionnaire_response_text} qt ON qt.question_id = qq.id
                              JOIN {questionnaire_response} qr ON qr.id = qt.response_id
                              JOIN {questionnaire_survey} qs ON qs.id = qq.surveyid";
                   $where1 = "  WHERE qq.name = 'covid_prepare_text' AND userid = ".$user;
                   $where2 = "  WHERE qq.name = 'went_well_text' AND userid = ".$user;
                   $where3 = "  WHERE qq.name = 'supported_text' AND userid = ".$user;
                   $where4 = "  WHERE qq.name = 'improve_experience_text' AND userid = ".$user;
                   $where5 = "  WHERE qq.name = 'why_NPS' AND userid = ".$user;
                   $where6 = "  WHERE qq.name = 'additional_comments_text' AND userid = ".$user;
                   $params = array();
                   $sqlnew = $sql2. $where1;
                   $res1 = $DB->get_records_sql($sqlnew, $params);
                   foreach($res1 as $res) {
                     $r1 = $res->response;
                   }
                   $r1 = $res->response;
                   $r1 =  str_replace("&nbsp;", '', trim(strip_tags($r1)));
                   $q1 = "How, if in any way, this course helped you prepare for school opening after COVID-19?";
                   $sqlnew = $sql2. $where2;
                   $res2 = $DB->get_records_sql($sqlnew, $params);
                   foreach($res2 as $res) {
                      $r2 = $res->response;
                   }
                   $r2 =  str_replace("&nbsp;", '', trim(strip_tags($r2)));
                   $q2 = 'Overall, what went well in this course?';
                   $sqlnew = $sql2. $where3;

                   $res3 = $DB->get_records_sql($sqlnew, $params);
                   foreach($res3 as $res) {
                      $r3 = $res->response;
                   }
                   $r3 =  str_replace("&nbsp;", '', trim(strip_tags($r3)));
                   $q3 = 'Which activities best supported your learning in this course?';

                   $sqlnew = $sql2. $where4;
                   $res4 = $DB->get_records_sql($sqlnew, $params);
                   foreach($res4 as $res) {
                      $r4 = $res->response;
                   }
                   $r4 =  str_replace("&nbsp;", '', trim(strip_tags($r4)));
                   $q4 = 'What could have improved your experience in this course?';

                   $sqlnew = $sql2. $where5;
                   $res5 = $DB->get_records_sql($sqlnew, $params);
                   foreach($res5 as $res) {
                      $r5 = $res->response;
                   }
                   $r5 =  str_replace("&nbsp;", '', trim(strip_tags($r5)));
                   $q5 = 'Why did you chose this rating';

                   $sqlnew = $sql2. $where6;
                   $res6 = $DB->get_records_sql($sqlnew, $params);
                   foreach($res6 as $res) {
                      $r6 = $res->response;
                   }
                   $r6 =  str_replace("&nbsp;", '', trim(strip_tags($r5)));
                   $q6 =  'Do you have additional comments about  this course';
                   $output[] = array($sub, $partnerdisplay, $portdisplay, $tlist, $cfname, $quest, $cr,
                                    $q1, $r1, $q2, $r2, $q3, $r3, $q4, $r4, $q5, $r5, $q6, $r6);
               } else {
                  $output[] = array($sub, $partnerdisplay, $portdisplay, $tlist, $cfname, $quest, $cr);
               }
           }
       }
    }
    // Non Moodle courses
 //   exit();
     if ($portfolio > 0) {    
         $portfieldid = get_config($plugin, 'portfoliofield');
         $data = $DB->get_field('customfield_field', 'configdata', array('id' => $portfieldid));    
         $x = json_decode($data);
         $opts = $x->options;
         $x = 1;
         $options_old = preg_split("/\s*\n\s*/", $opts);
         foreach($options_old as $val) {
            if ($x == $portfolio) {
                $portval = $val;
            }
            $x = $x + 1;    
         }
     }
        $sql = "Select * ";
        switch($nquestionid) {
           case "1":
              $sql = "SELECT uidsurvey, coursedate, courseid, district, port1name, port2name, teacher1id, teacher2id, learning response ";
              $qname = "What is the learning from this course that you are most excited about trying out";
              break;
     	   case "2":
     	       $qname = "How, if in any way, this course helped you prepare for school opening after COVID-19?";
     	       $sql = "SELECT uidsurvey, coursedate, courseid, district, port1name, port2name, teacher1id, teacher2id, navigate response ";
               break;
       	   case "3":
       	       $qname = "Overall, what went well in this course?";
       	       $sql = "SELECT uidsurvey, coursedate, courseid, district, port1name, port2name, teacher1id, teacher2id, overall response ";
               break;
           case "4":
               $qname = "Which activities best supported your learning in this course?";
               $sql = "SELECT uidsurvey, coursedate, courseid, district, port1name, port2name, teacher1id, teacher2id, activities response ";
               break;
           case "5":
               $qname = "What could have improved your experience in this course?";
               $sql = " SELECT uidsurvey, coursedate, courseid, district, port1name, port2name, teacher1id, teacher2id, improved response ";
               break;
           case "6":
               $qname = "Why did you choose this rating?";
               $sql = " SELECT uidsurvey, coursedate, courseid, district, port1name, port2name, teacher1id, teacher2id, choose response ";
               break;
           case "7":
              $qname = "Do you have additional comments about  this course?";
              $sql = " SELECT uidsurvey, coursedate, courseid, district, port1name, port2name, teacher1id, teacher2id, comment response ";
              break;
     }
     $return = [];
     if ($allquest == 1) {
         $sql = "SELECT * ";
     }
     $sql = $sql. " FROM {local_teaching_survey} ";
     if ($cid > 0) {
         $sql = $sql. " WHERE courseid  = ".$cid;
     } else {
         $sql = $sql. " WHERE 1 = 1 ";
     }
     $paramsql = array();
     if ($stdate > 0) {
         $std = strtotime($stdate);
         $sql = $sql ." AND coursedate >= :stdate";
         $paramsql['stdate'] = $std;
     }
     if ($nddate > 0) {
         $sql = $sql. " AND coursedate <= :nddate";
         $ndt = strtotime($nddate);
         $paramsql['nddate'] = $ndt;
     }
     if ($teacher > 1) {
         $sql = $sql. " AND (teacherid1= :teacher1) OR (teacherid2= :teacher2)";
         $paramsql['teacher1'] = $teacher;
         $paramsql['teacher2'] = $teacher;
     }
     if ($portfolio > 0) {
         $sql = $sql. " AND (port1name = :port1) OR (port2name= :port2)";
         $paramsql['port1'] = $portval;
         $paramsql['port2'] = $portval;
     
     }
     $resultlist = $DB->get_records_sql($sql, $paramsql);
     
//     $portdisplay = "N/A";
     if (!empty($resultlist)) {
        $cnt = 0;
        foreach($resultlist as $result) {
           $row = new stdClass();
           $row->date = date('Y-m-d', $result->coursedate);
           $row->partner = $result->district;
           $portdisplay = '';
           $p1 = $result->port1name;
           $p2 = $result->port2name;
           if ($p1) {
               $portdisplay = $p1;           
           }
           if ($p2) {
               $portdisplay = $portdisplay .' '.$p2;           
           }
           $row->portfolio = $portdisplay;
           $row->course_id = $cid;
           $row->course = $DB->get_field('local_teaching_course', 'coursename', array('id' => $cid));
           $row->question = $qname;
           if ($allquest == 1) {
              $cr = $result->learning;
           } else {
              $cr = $result->response;
           }
           $cr =  str_replace("&nbsp;", '', trim(strip_tags($cr)));
           $row->response = $cr;
           $t1 = $result->teacher1id;
           $t2 = $result->teacher2id;
           $tname = '';
           $t1name = $DB->get_field('local_teaching_teacher', 'teachername', array('id' => $t1));
           if ($t1name)  {
              $tname = $t1name;
           }
           $t2name = $DB->get_field('local_teaching_teacher', 'teachername', array('id' => $t2));
           if ($t2name)  {
              $tname .= '  '.$t2name;
           }
           $row->teachers = $tname;
         //  var_dump($row);
          // exit();
           array_push($content, $row);

           $displaycnt = $displaycnt + 1;
           if ($display) {
               if ($displaycnt > $maxdisplay) {
                   break;
               }
           } else {
               $partnerdisplay = $result->district;
               $sub = date('Y-m-d', $result->coursedate);
               $displaycnt = $displaycnt + 1;
               $courseid = $result->courseid;
               $cfname = $DB->get_field('local_teaching_course', 'coursename', array('id' => $courseid));
               if ($allquest == 1) {
                  $cr1 = $result->learning;
                  $cr1 =  str_replace("&nbsp;", '', trim(strip_tags($cr1)));
                  $cr2 = $result->navigate;
                  $cr2 =  str_replace("&nbsp;", '', trim(strip_tags($cr2)));
                  $cr3 = $result->overall;
                  $cr3 =  str_replace("&nbsp;", '', trim(strip_tags($cr3)));
                  $cr4 = $result->activities;
                  $cr4 =  str_replace("&nbsp;", '', trim(strip_tags($cr4)));
                  $cr5 = $result->improved;
                  $cr5 =  str_replace("&nbsp;", '', trim(strip_tags($cr5)));
                  $cr6 = $result->choose;
                  $cr6 =  str_replace("&nbsp;", '', trim(strip_tags($cr6)));
                  $cr7 = $result->comment;
                  $cr7 =  str_replace("&nbsp;", '', trim(strip_tags($cr7)));
     	          $q2 = "How, if in any way, this course helped you prepare for school opening after COVID-19?";
                  $q3 = "Overall, what went well in this course?";
       	          $q4 = "Which activities best supported your learning in this course?";
       	          $q5 = "What could have improved your experience in this course?";
               	  $q6 = "Why did you choose this rating?";
               	  $q7 = "Do you have additional comments about  this course?";
                  $output[] = array($sub, $partnerdisplay, $portdisplay, $tname, $cfname, $qname, $cr1, $q2, $cr2, $q3,$cr3,
                                      $q4, $cr4, $q5, $cr5, $q6, $cr6, $q7, $cr7);

               } else {
                  $cr = $result->response;
                  $cr =  str_replace("&nbsp;", '', trim(strip_tags($cr)));
                  $output[] = array($sub, $partnerdisplay, $portdisplay, $tname, $cfname, $qname, $cr);
               }
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

function block_questionreport_setchart($ctype, $chartid, $stdate, $nddate, $cid, $sid, $questionid) {
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
    $pcnt = 1;
    $labelarray = array();
    foreach ($partnerlist as $partnername) {
        $comparevalue = $DB->sql_compare_text($partnername);
        $partnerid = get_config($plugin, 'partnerfield');
        $partnersql = "JOIN {customfield_data} cd ON cd.instanceid = m.course
                        AND cd.fieldid = ".$partnerid ." AND cd.value = ".$pcnt;
        $sqlcourses = "SELECT m.course, m.id, m.instance
                          FROM {course_modules} m
                          JOIN {tag_instance} ti on ti.itemid = m.id " .$partnersql. "
                         WHERE m.module = ".$moduleid. "
                           AND ti.tagid = ".$tagid . "
                           AND m.deletioninprogress = 0";
        $surveys = $DB->get_records_sql($sqlcourses);
        $totsurveys = 0;
        $totvalue = 0;
        foreach ($surveys as $survey) {
           $sid = $survey->instance;
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
           $fromressql .= "JOIN {questionnaire_response} qr ON qr.id = mr.response_id AND qr.complete ='y'";
           $whereressql = "WHERE qr.complete = 'y' AND mr.question_id = ".$qid ." AND choice_id = ".$chid;
           $paramsql = array();
           if ($stdate > 0) {
               $whereressql = $whereressql . ' AND qr.submitted >= :stdate';
               $std = strtotime($stdate);
               $paramsql['stdate'] = $std;
           }
           if ($nddate > 0) {
               $whereressql = $whereressql . ' AND qr.submitted <= :nddate';
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
               $totvalue = $ranksql->rv + $totvalue;
           }
        }
        if ($totsurveys > 0) {
            $labelarray[] = $partnername;
            $val = $totvalue / $totsurveys;
            $val = round($val, 2);
            $valarray[] = $val;
        }
        $pcnt = $pcnt + 1;
    }
    // Get the non moodle examples
    $sql = "SELECT distinct(district) district
             FROM {local_teaching_survey} order by district";
    $districts = $DB->get_records_sql($sql);

    foreach($districts as $district) {
        $partner = $district->district;
        $params = array();
        $whereext = " FROM {local_teaching_survey} ts
                   WHERE district = '".$partner."'";
        $sqlext = "SELECT count(uidsurvey) cdgood ".$whereext;
        $whereressql = " ";
    	  if ($stdate > 0) {
            $whereressql = $whereressql . ' AND coursedate >= :stdate';
            $std = strtotime($stdate);
            $params['stdate'] = $std;
        }
     	  if ($nddate > 0) {
            $whereressql = $whereressql . ' AND coursedate <= :nddate';
            $ndt = strtotime($nddate);
            $params['nddate'] = $ndt;
        }
        $sqlext = $sqlext .$whereressql;
        $base = $DB->get_record_sql($sqlext, $params);
        $basetot = $base->cdgood;

        if ($basetot > 0) {
     	      $s = "SELECT count(*) rv";
            switch ($choicecnt) {
              case "1":
                 $whereressql = $whereressql . ' AND (satisfied = 4 or satisfied = 5)';
                 break;
              case "2": 
                 $whereressql = $whereressql . ' AND (topics = 4 or topics = 5)';
                 break;
              case "3" :
                 $whereressql = $whereressql . ' AND (online = 4 or online = 5)';
                 break;
               case "4" :
                 $whereressql = $whereressql . ' AND (zoom = 4 or zoom = 5)';
                 break;
              case "5" :
                 $whereressql = $whereressql . ' AND (community = 4 or community = 5)';
                 break;
              case "6" :
                 $whereressql = $whereressql . ' AND (covid = 4 or covid = 5)';
                 break;
              case "7" :
                 $whereressql = $whereressql . ' AND (practice = 4 or practice = 5)';
                 break;
            }
            $sqltot = $s. " ".$whereext. ' '.$whereressql;
            $totrec = $DB->get_record_sql($sqltot, $params);
            $totgood = $totrec->rv;
            $labelarray[] = $partner;
            $val = $totgood / $basetot;
            $val = round($val, 2);
            $val = $val * 100;
            $valarray[] = $val;
            $svcnt = $svcnt + 1;
         }
    }
    if ($svcnt == 0 ) {
    	 return '0';
    } else {
       $series1 = new \core\chart_series('Series 1 (Bar)', $valarray);
       $chart->add_series($series1);
       $chart->set_labels($labelarray);
       return $chart;
    }
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
function block_questionreport_display_all_questions($ctype, $surveyid) {
    global $DB, $COURSE;
    $plugin = 'block_questionreport';
    $essaylist = array();
    $essaylist[0] = get_string('none', $plugin);
    if ($ctype == "M") {
        $customfields = $DB->get_records('questionnaire_question', array('type_id' => '3', 'surveyid' => $surveyid));
        foreach ($customfields as $field) {
            $content = $field->content;
            $display = strip_tags($content);
            $display = trim($display);
            $essaylist[$field->id] = $display;
        }
    } else {
        $essaylist[1] = 'What is the learning from this course that you are most excited about trying out?';
        $essaylist[2] = 'How, if in any way, this course helped you prepare for school opening after COVID-19?';
        $essaylist[3] = 'Overall, what went well in this course?';
        $essaylist[4] = 'Which activities best supported your learning in this course?';
        $essaylist[5] = 'What could have improved your experience in this course?';
        $essaylist[6] = 'Why did you choose this rating?';
        $essaylist[7] = 'Do you have additional comments about  this course?';
    }

    $essaylist[10] = 'I am satisfied with the overall quality of this course.';
    $essaylist[11] = 'The topics for this course were relevant for my role.';
    $essaylist[12] = 'The independent online work activities were well-designed to help me meet the learning targets.';
    $essaylist[13] = 'The Zoom meeting activities were well-designed to help me meet the learning targets.';
    $essaylist[14] = 'I felt a sense of community with the other participants in this course even though we were meeting virtually.';
    $essaylist[15] = 'This course helped me navigate remote and/or hybrid learning during COVID-19';
    $essaylist[16] = 'I will apply my learning from this course to my practice in the next 4-6 weeks.';
    $essaylist[17] = 'Recommend this course to a colleague or friend.';
    $essaylist[18] = 'He/she/they facilitated the content clearly.';
    $essaylist[19] = 'He/she/they effectively built a community of learners.';
        
    $essaylist[100] = 'All';
    
    return $essaylist;
}
//ALTER TABLE mdl_local_teaching_survey
//ADD COLUMN port1name VARCHAR(254) AFTER activities;

//ALTER TABLE mdl_local_teaching_survey
//ADD COLUMN port2name VARCHAR(254) AFTER port1name;

//select distinct(port1id) from mdl_local_teaching_survey
/*
SET SQL_SAFE_UPDATES=0;
update mdl_local_teaching_survey set port1name ="Math: IM" where port1id = 19
update mdl_local_teaching_survey set port1name ="ELA: Guidebooks" where port1id = 20
update mdl_local_teaching_survey set port1name ="ELA: EL" where port1id = 21
update mdl_local_teaching_survey set port1name ="State-level" where port1id = 22
update mdl_local_teaching_survey set port1name ="ELA: Flexible" where port1id = 23
update mdl_local_teaching_survey set port1name ="Math: Eureka/EngageNY" where port1id = 24
update mdl_local_teaching_survey set port1name ="Math: Flexible" where port1id = 25
update mdl_local_teaching_survey set port1name = "Math: Zearn" where port1id = 26



update mdl_local_teaching_survey set port2name ="Math: IM" where port2id = 19
update mdl_local_teaching_survey set port2name ="ELA: Guidebooks" where port2id = 20
update mdl_local_teaching_survey set port2name ="ELA: EL" where port2id = 21
update mdl_local_teaching_survey set port2name ="State-level" where port2id = 22
update mdl_local_teaching_survey set port2name ="ELA: Flexible" where port2id = 23
update mdl_local_teaching_survey set port2name ="Math: Eureka/EngageNY" where port2id = 24
update mdl_local_teaching_survey set port2name ="Math: Flexible" where port2id = 25
update mdl_local_teaching_survey set port2name = "Math: Zearn" where port2id = 26
*/