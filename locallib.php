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