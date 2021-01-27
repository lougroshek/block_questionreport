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

// Function to generate the feedback reports.
function block_questionreport_genfeedback($reportnum, $yrnum, $partner) {
    global $DB;
    $content = '';
    $plugin = 'block_questionreport';
    if ($reportnum == '0') {
        return $content;
        exit();
    }
    // Generate the feedback reports
    // report1 = 'Participant feedback, by month';
    // report2 = 'Participant feedback, by portfolio';
    // report3 = 'Participant feedback, by partner site';
     $qlist = array();
     $qlist[1] = 'I am satisfied with the overall quality of this course';
     $qlist[2] = 'The topics for this course were relevant for my role.';
     $qlist[3] = 'The independent online work activities were well-designed to help me meet the learning targets';
     $qlist[4] = 'The Zoom meeting activities were well-designed to help me meet the learning targets';
     $qlist[5] = 'I felt a sense of community with the other participants in this course even though we were meeting virtually.';
     $qlist[6] = 'This course helped me navigate remote and/or hybrid learning during COVID-19.';
     $qlist[7] = 'I will apply my learning from this course to my practice in the next 4-6 weeks';
     $qlist[8] = 'Recommend this course to a colleague or friend';
     $qlist[100] = ' He/she/they facilitated the content clearly. ';
     $qlist[101] = ' He/she/they effectively built a community of learners.';
     
     
     switch($reportnum) { 
        case "1":
          $yr2 = $yrnum + 1;
          $header = get_string('report1', $plugin);
          $content = '<h1><p>'.$header.'</p></h1><br>';
          $content .= '<table><tr><th></th>';
          $tablestart = '<th>01_JUN'.$yrnum.'</th><th>02_JUL'.$yrnum.'</th>'.
                                 '<th>03_AUG'.$yrnum.'</th><th>04_SEP'.$yrnum.'</th>'.
                                 '<th>05_OCT'.$yrnum.'</th><th>06_NOV'.$yrnum.'</th>'.
                                 '<th>07_DEC'.$yrnum.'</th><th>08_JAN'.$yr2.'</th>'.
                                 '<th>09_FEB'.$yr2.'</th><th>03_MAR'.$yr2.'</th>'.
                                 '<th>10_APR'.$yr2.'</th><th>11_MAY'.$yr2.'</th><tr>';
          $content = $content.$tablestart;
          $line1 = '<tr><td>Number of Survey Responses</td>';
          for ($mnlist = 6; $mnlist < 13; $mnlist ++) {
          	   $stdate = '01-'.$mnlist.'-'.$yrnum;
          	   if ($mnlist < 12) {
          	   	 $nm2 = $mnlist + 1; 
                   $nddate = '01-'.$nm2.'-'.$yrnum;
               } else {
                   $nddate = '01-01-'.$yr2;
               }
               $mn1 = block_questionreport_choicequestion(0, $stdate, $nddate);
               $line1 .= '<td>'.$mn1.'</td>';
          }
          for ($mnlist = 1; $mnlist < 6; $mnlist ++) {
          	   $stdate = '01-'.$mnlist.'-'.$yr2;
          	   $nm2 = $mnlist + 1;
               $nddate = '01-'.$nm2.'-'.$yr2;
               $mn1 = block_questionreport_choicequestion(0, $stdate, $nddate);
               $line1 .= '<td>'.$mn1.'</td>';
          }
          $content = $content .$line1.'<tr><td colspan = "13">&nbsp;</td></tr>';
          $content = $content.'<tr><th><b>Session Summary (% Agree and Strongly Agree)</b></th>'.$tablestart;
          for ($ql = 1; $ql < 9; $ql++) {
               $line1 = '<tr><td>'.$qlist[$ql].'</td>';
               for ($mnlist = 6; $mnlist < 13; $mnlist ++) {
          	        $stdate = '01-'.$mnlist.'-'.$yrnum;
          	        if ($mnlist < 12) {
                   	   $nm2 = $mnlist + 1;          	        	
                        $nddate = '01-'.$nm2.'-'.$yrnum;
                    } else {
                        $nddate = '01-01-'.$yr2;
                    }
                    $mn1 = block_questionreport_choicequestion($ql, $stdate, $nddate);
                    $line1 .= '<td>'.$mn1.'</td>';
               }
               for ($mnlist = 1; $mnlist < 6; $mnlist ++) {
          	        $stdate = '01-'.$mnlist.'-'.$yr2;
              	     $nm2 = $mnlist + 1;
                    $nddate = '01-'.$nm2.'-'.$yr2;
                    $mn1 = block_questionreport_choicequestion($ql, $stdate, $nddate);
                    $line1 .= '<td>'.$mn1.'</td>';
               }
               $content = $content .$line1;            
          }
          $content = $content .$line1.'<tr><td colspan = "13">&nbsp;</td></tr>';
          $content = $content.'<tr><th><b>Facilitation Summary (% Agree and Strongly Agree)</b></th>'.$tablestart;
          for ($ql = 100; $ql < 102; $ql++) {
               $line1 = '<tr><td>'.$qlist[$ql].'</td>';
               for ($mnlist = 6; $mnlist < 13; $mnlist ++) {
          	        $stdate = '01-'.$mnlist.'-'.$yrnum;
          	        if ($mnlist < 12) {
                   	   $nm2 = $mnlist + 1;          	        	
                        $nddate = '01-'.$nm2.'-'.$yrnum;
                    } else {
                        $nddate = '01-01-'.$yr2;
                    }
                    $mn1 = block_questionreport_choicequestion($ql, $stdate, $nddate);
                    $line1 .= '<td>'.$mn1.'</td>';
               }
               for ($mnlist = 1; $mnlist < 6; $mnlist ++) {
          	        $stdate = '01-'.$mnlist.'-'.$yr2;
              	     $nm2 = $mnlist + 1;
                    $nddate = '01-'.$nm2.'-'.$yr2;
                    $mn1 = block_questionreport_choicequestion($ql, $stdate, $nddate);
                    $line1 .= '<td>'.$mn1.'</td>';
               }
               $content = $content .$line1;            
          }
          $content = $content.'</table>';
          echo $content;
          exit();                                      
          break;
        case "2":
          $header = get_string('report2', $plugin);
          break;
       case "3":
          $header = get_string('report3', $plugin);
          break; 
     }         
}
// Function to return the % of question

function block_questionreport_choicequestion($qnum, $stdate, $nddate) {
   global $DB;
   $paramsql = array();
   $paramsext = array();
   //select * from mdl_questionnaire_quest_choice where content = 'I am satisfied with the overall quality of this course.';
//select * from mdl_questionnaire_question  quest 
//join mdl_questionnaire_quest_choice qc on qc.question_id = quest.id
//where qc.content = 'I am satisfied with the overall quality of this course.'
//and quest.name = 'course_ratings'


//select quest.id questid, qc.id choiceid 
//from mdl_questionnaire_question  quest 
//join mdl_questionnaire_quest_choice qc on qc.question_id = quest.id
//join mdl_questionnaire_response qr
//where qc.content = 'I am satisfied with the overall quality of this course.'
//and quest.name = 'course_ratings'

//select quest.id questid, qc.id choiceid 
//from mdl_questionnaire_question  quest 
//join mdl_questionnaire_quest_choice qc on qc.question_id = quest.id
//where qc.content = 'I am satisfied with the overall quality of this course.'
//and quest.name = 'course_ratings'

// $totresql  = "SELECT count(rankvalue) ";
//                $fromressql = " FROM {questionnaire_response_rank} mr ";
//                $whereressql = "WHERE mr.question_id = ".$qid ." AND choice_id = ".$chid;
//                $paramsql = array();
//                if ($stdate > 0) {
//                     $fromressql = $fromressql .' JOIN {questionnaire_response} qr on qr.id = mr.response_id';
//  
//                   $whereressql = $whereressql . ' AND qr.submitted >= :stdate'
 $fromressql = "" ;
 $whereressql = " ";
 $where1 = " ";
 $whereext = " ";
 switch ($qnum) {
      case "0" :
        $mdlsql = "WHERE content = 'I am satisfied with the overall quality of this course.'";
        $nonmdl = "WHERE  1 = 1";
        break;           
      case "1":
        $mdlsql = "AND content = 'I am satisfied with the overall quality of this course.'";
        $nonmdl = "WHERE satisfied >=4";
        break;
      case "2":
        $mdlsql = "AND content = 'The topics for this course were relevant for my role.'";
        $nonmdl = "WHERE topics >=4";
        break;
      case "3":
        $mdlsql = "AND content = 'The independent online work activities were well-designed to help me meet the learning targets.'";
        $nonmdl = "WHERE online >=4";
        break;
      case "4":
        $mdlsql = "AND content = 'The Zoom meeting activities were well-designed to help me meet the learning targets.'";
        $nonmdl = "WHERE zoom >=4";
        break;
      case "5":
        $mdlsql = "AND content = 'I felt a sense of community with the other participants in this course even though we were meeting virtually.'";
        $nonmdl = "WHERE community >=4";
        break;
      case "6":
        $mdlsql = "AND content = 'This course helped me navigate remote and/or hybrid learning during COVID-19.'";
        $nonmdl = "WHERE covid >=4";
        break;
      case "7":
        $mdlsql = "AND content = 'I will apply my learning from this course to my practice in the next 4-6 weeks.'";
        $nonmdl = "WHERE practice >=4";
        break;
      case "8":
        $mdlsql = "AND content = 'Recommend this course to a colleague or friend.'";
        $nonmdl = "WHERE reccomend >=9";
        $nonmdl1 = " WHERE reccomend <=6";
        break;
     case "100" :
        $qname = "facilitator_rate_content";  
        $mdlsql = " 1 = 1";
        $nonmdl = " where (content1 >=4 or content2 >=4";
        break;
     case "101" :
        $mdlsql = " 1 = 1";
        $qname = "facilitator_rate_community";
        $nonmdl = " where (community1 >=4 or community2 >=4";
        break;   
   }
   //
   if ($stdate > 0) {
       $fromressql = $fromressql .' JOIN {questionnaire_response} qr on qr.id = mr.response_id';
       $whereressql = $whereressql . ' AND qr.submitted >= :stdate';
       $std = strtotime($stdate);
       $whereext = $whereext . " AND coursedate >= :std";
       $where1 = $where1 . " AND coursedate >= :std";
       $paramsext['std'] = $std;
       $paramsql['stdate'] = $std;
   }
   if ($nddate > 0) {
       $fromressql = $fromressql .' JOIN {questionnaire_response} qr2 on qr2.id = mr.response_id';
       $whereressql = $whereressql . ' AND qr2.submitted <= :nddate';
       $ndt = strtotime($nddate);
       $paramsql['nddate'] = $ndt;
       $whereext = $whereext . " AND coursedate < :endtd";
       $where1 = $where1 . " AND coursedate < :endtd";
       $paramsext['endtd'] = $ndt;
   }
   
   // Get the total responses.
   $sqlext = "SELECT COUNT(ts.courseid) cdtot
              FROM {local_teaching_survey} ts ";
   $sqlext = $sqlext .$nonmdl.$whereext;
   $sqlnonmoodle = $DB->get_record_sql($sqlext, $paramsext);
   $cntnonmoodle = $sqlnonmoodle->cdtot;
   $sqlmoodle = " SELECT COUNT(qr.id) crid 
                         FROM {questionnaire_response} qr 
                         JOIN {questionnaire} q on q.id = qr.questionnaireid
                        WHERE q.name = 'End-of-Course Survey' 
                          AND qr.complete = 'y'
                          AND qr.submitted >= :stdate 
                          AND qr.submitted < :nddate";
   $sqlrecmoodle = $DB->get_record_sql($sqlmoodle, $paramsql);

   $cntmoodle = $sqlrecmoodle->crid;
   if ($qnum == 0) {
       $val = $cntmoodle + $cntnonmoodle;
       return $val;   
   } else {
       $val = $cntmoodle + $cntnonmoodle;
       if ($qnum < 100 )  {  	
           $sqlmoodle = " select count(rankvalue) crid
                            from {questionnaire_quest_choice} qc
                            join {questionnaire_response_rank} qr on qr.question_id = qc.question_id
                            join {questionnaire_response} q on q.id = qr.response_id 
                            and qc.id = qr.choice_id
                            AND q.submitted >= :stdate 
                           AND q.submitted < :nddate";
            if ($qnum != 8) {
                $sqlmoodle = $sqlmoodle . " AND (rankvalue = 4 or rankvalue = 5) ";          
            } else {
                $sqlmoodle2 = $sqlmoodle . " AND (rankvalue < 9 ) ";                  
                $sqlmoodle = $sqlmoodle . " AND (rankvalue = 9 or rankvalue = 10) ";                  
            }
        } else {
          $sqlmoodle = " SELECT COUNT(qr.id) crid 
                         FROM {questionnaire_response} qr 
                         JOIN {questionnaire} q on q.id = qr.questionnaireid
                        WHERE q.name = '".$qname. ."' 
                          AND qr.complete = 'y'
                          AND qr.submitted >= :stdate 
                          AND qr.submitted < :nddate";

        }                    
        $sqlmoodle = $sqlmoodle ." ".$mdlsql;
        $sqlrecans = $DB->get_record_sql($sqlmoodle, $paramsql);
        if ($qnum == 8) {
            $sqlrecans2 = $DB->get_record_sql($sqlmoodle2, $paramsql);
            $ans1a = $sqlrecans2->crid;        
        }
        $ans1 = $sqlrecans->crid;
        $sqlext = "SELECT COUNT(ts.courseid) cdtot
                   FROM {local_teaching_survey} ts ";
        if ($qnum == 8) {
            $sqlext2 = $sqlext .$nonmdl1. $whereext;            
            $sqlrecans2a = $DB->get_record_sql($sqlext2, $paramsext);
            $ans2a = $sqlrecans2a->cdtot;
        }
        $sqlext = $sqlext .$nonmdl.$whereext;
        $sqlrecans2 = $DB->get_record_sql($sqlext, $paramsext);
        $ans2 = $sqlrecans2->cdtot;
        if ($val > 0) {
            if ($qnum <> 8 ) {       	   
                $totgood = $ans1 + $ans2;
                $val = ($totgood / $val) * 100;
                $ret = round($val, 0)."(%)";
            } else {
                $ans1 = $ans1 + $ans2;
                $ans1a = $ans1a + $ans2a;
                $ans1 = ($ans1 / $val) * 100;
                $ans1a = ($ans1a / $val) * 100;
                $ret = $ans1 - $ans1a;
                $ret = round($ret, 0);   
            }
            return $ret; 
        } else {
           return '-';        
        }  
                  
   }    
                               
}