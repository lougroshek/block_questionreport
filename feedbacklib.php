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
     switch($reportnum) { 
        case "1":
          $yr2 = $yrnum + 1;
          $header = get_string('report1', $plugin);
          $content = '<table><tr><th></th><th>01_JUN'.$yrnum.'</th>'.'<th>02_JUL'.$yrnum.'</th>'.
                                 '<th>03_AUG'.$yrnum.'</th><th>04_SEP'.$yrnum.'</th>'.
                                 '<th>05_OCT'.$yrnum.'</th><th>06_NOV'.$yrnum.'</th>'.
                                 '<th>07_DEC'.$yrnum.'</th><th>08_JAN'.$yr2.'</th>'.
                                 '<th>09_FEB'.$yr2.'</th><th>03_MAR'.$yr2.'</th>'.
                                 '<th>10_APR'.$yr2.'</th><th>11_MAY'.$yr2.'</th>'.
                                 '<th>12_JUN'.$yr2.'</th></tr>';
                                 
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
//                     $whereressql = $whereressql . ' AND qr.submitted >= :stdate'
 switch ($qnum) {
      case "1":
        $mdlsql = "where content = 'I am satisfied with the overall quality of this course.'";
        $nonmdl = "where satisfied >=4";
      break;
      case "2":
        $mdlsql = "where content = 'The topics for this course were relevant for my role.'";
        $nonmdl = "where topics >=4";
      break;
      case "3":
        $mdlsql = "where content = 'The independent online work activities were well-designed to help me meet the learning targets.'";
        $nonmdl = "where online >=4";
      break;
      case "4":
        $mdlsql = "where content = 'The Zoom meeting activities were well-designed to help me meet the learning targets.'";
        $nonmdl = "where zoom >=4";
      break;
      case "5":
        $mdlsql = "where content = 'I felt a sense of community with the other participants in this course even though we were meeting virtually.'";
        $nonmdl = "where community >=4";
      break;
      case "6":
        $mdlsql = "where content = 'This course helped me navigate remote and/or hybrid learning during COVID-19.'";
        $nonmdl = "where covid >=4";
      break;
      case "7":
        $mdlsql = "where content = 'I will apply my learning from this course to my practice in the next 4-6 weeks.'";
        $nonmdl = "where practice >=4";
      break;
      case "8":
        $mdlsql = "where content = 'Recommend this course to a colleague or friend.'";
        $nonmdl = "where reccomend >=9";
        $nonmdl1 = "where recommend <=8";
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
       $whereext = $whereext . " AND coursedate <= :endtd";
       $where1 = $where1 . " AND coursedate <= :endtd";
       $paramsext['endtd'] = $ndt;
   }
}