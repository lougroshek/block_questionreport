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
    $plugin = 'block_questionreport';
    $adminvalue = get_config($plugin, 'adminroles');
    $adminarray = explode(',',$adminvalue);
    $adminuser = false;
    if (is_siteadmin($USER)) {
        $adminuser = true;
    }
    // check the system roles.
    if (!$adminuser) {
        $systemcontext = context_system::instance();
        $roles = get_user_roles($systemcontext, $USER->id, true);
        foreach ($adminarray as $val) {
            foreach ($roles as $rl) {
                if ( $rl->roleid == $val ) {
                     $adminuser = true;
                }
            }
        }
    }

    return $adminuser;
}

function block_questionreport_get_evaluations() {
    global $DB, $CFG, $COURSE, $USER, $PAGE, $OUTPUT;
    $plugin = 'block_questionreport';
    $ctype = "M";
    // The object we will pass to mustache.
    $data = new stdClass();
    // Does the current course have results to display?
    $has_responses_contentq = true;
    $has_responses_commq = true;
    // Is the user a teacher or an admin?
    $is_admin = block_questionreport_is_admin();
    $is_teacher = block_questionreport_is_teacher();
    if (!$is_admin && !$is_teacher ) {
       return '';
       exit();
    }
    // Add buttons object.
    $data->buttons = new stdClass();
    // Build reports button object.
    $reports = new stdClass();
    $reports->text = get_string('reports', $plugin);
    $cid = "M-".$COURSE->id;
    $reports->href = $CFG->wwwroot.'/blocks/questionreport/report.php?action=view&cid='.$cid;
    $data->buttons->reports = $reports;
    // Conditionally add charts button object.
    $adminvalue = get_config($plugin, 'adminroles');
    $adminarray = explode(',',$adminvalue);

    // check to see if they are an admin.
    $adminuser = false;
    if (!!$is_admin) {
        $adminuser = true;
    } else {
        $context = context_course::instance($COURSE->id);
        $roles = get_user_roles($context, $USER->id, true);
        foreach ($adminarray as $val) {
            $sql = "SELECT * FROM {role_assignments} AS ra
                      LEFT JOIN {user_enrolments} AS ue ON ra.userid = ue.userid
                      LEFT JOIN {role} AS r ON ra.roleid = r.id
                      LEFT JOIN {context} AS c ON c.id = ra.contextid
                      LEFT JOIN {enrol} AS e ON e.courseid = c.instanceid AND ue.enrolid = e.id
                      WHERE r.id= ".$val." AND ue.userid = ".$USER->id. " AND e.courseid = ".$COURSE->id;
            $result = $DB->get_records_sql($sql, array( ''));
            if ( $result ) {
                $adminuser = true;
            }
        }
        // check the system roles.
        if (!$adminuser) {
            $systemcontext = context_system::instance();
            $roles = get_user_roles($systemcontext, $USER->id, true);
            foreach ($adminarray as $val) {
                foreach ($roles as $rl) {
                    if ( $rl->roleid == $val ) {
                        $adminuser = true;
                    }
                }
            }
        }

    }
    if ($adminuser) {
        $data->role = 'admin';
        // Build charts object.
        $charts = new stdClass();
        $charts->text = get_string('charts', $plugin);
        $charts->href = $CFG->wwwroot.'/blocks/questionreport/charts.php?action=view&cid='.$cid;
        $data->buttons->charts = $charts;
        // Build admin reports button object.
        $adminreports = new stdClass();
        $adminreports->text = get_string('adminreports', $plugin);
        $adminreports->href = $CFG->wwwroot.'/blocks/questionreport/adminreport.php?action=view&cid='.$cid;
        $data->buttons->adminreports = $adminreports;
    }
    if (!!$is_teacher) {
        $data->role = 'teacher';
    }
    //exit();
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
    $cnt = block_questionreport_get_question_results($ctype, $stp, $cid, $surveyid, $moduleid, $tagid, 0, 0, '', 0, 0);
    if ($cnt == '-') {
        $questionid = $DB->get_field('questionnaire_question', 'id', array('position' => $stp, 'surveyid' => $surveyid));
        $totresql = "SELECT count(*) crnt
                     FROM {questionnaire_response_rank} mr
                     JOIN {questionnaire_response} qr on qr.id = mr.response_id
                     AND mr.question_id = ".$questionid;
        // $totres = $DB->count_records('questionnaire_response_rank', array('question_id' => $questionid));
        $totres = $DB->get_record_sql($totresql);
        if ($totres->crnt > 0) {
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
        $cnt2 = block_questionreport_get_question_results($ctype, $stp, $cid, $surveyid, $moduleid, $tagid, 0, 0, '', 0, 0);
        if ($cnt2 == '-') {
            $questionid = $DB->get_field('questionnaire_question', 'id', array('position' => $stp, 'surveyid' => $surveyid));
            $totresql = "SELECT count(*) crnt
            FROM {questionnaire_response_rank} mr
            JOIN {questionnaire_response} qr on qr.id = mr.response_id
            AND mr.question_id = ".$questionid;

            //  $totres = $DB->count_records('questionnaire_response_rank', array('question_id' => $questionid));
            $totres = $DB->get_records_sql($totsql);
            if ($totres->crnct > 0) {
                $commq->stat = 0;
            } else {
                $has_responses_commq = false;
            }
        } else {
            $commq->stat = $cnt2;
        }
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
    $is_admin = block_questionreport_is_admin();

    $plugin = 'block_questionreport';
    $courselist = array();
    $courselist[0] = get_string('allcourses', $plugin);
    $tagvalue = get_config($plugin, 'tag_value');
    $tagid = $DB->get_field('tag', 'id', array('name' => $tagvalue));
    $moduleid = $DB->get_field('modules', 'id', array('name' => 'questionnaire'));
    $lfroleid = $DB->get_field('role','id', array('shortname' => 'leadfacilitator'));

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
        if ($is_admin) {
            $valid = true;
        } else {
            $context = context_course::instance($coursecert->id);
            $roles = get_user_roles($context, $USER->id, true);
            foreach ($roles as $val) {
               if ($val->roleid == $lfroleid) {
                   $valid = true;
               }
            }
        }
        if ($valid) {
            $cid = "M-".$coursecert->id;
            $cname = "M-".$coursecert->fullname;
            $courselist[$cid] = $cname;
        }
    }
    if ($is_admin) {
        // Get the non moodle courses;
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('local_teaching_course')) {
            $altcourses = $DB->get_records('local_teaching_course');
            foreach($altcourses as $alt) {
               $cid = "A-".$alt->id;
               $cname = "A-".$alt->coursename;
               $courselist[$cid] = $cname;
            }
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
    $fieldid = get_config($plugin, 'partnerfield');

    $courselist[0] = get_string('all', $plugin);
    $content = $DB->get_field('customfield_field', 'configdata', array('id' => $fieldid));
    $options = array();
    $x = json_decode($content);
    $opts = $x->options;
    $options = preg_split("/\s*\n\s*/", $opts);
    return $options;

}
function block_questionreport_get_question_results_rank($ctype, $questionid, $choiceid, $cid, $surveyid, $moduleid, $tagid, $stdate, $nddate,
$partner, $portfolio, $teacher, $qname) {
    // Return the percentage of questions answered with a rank 4, 5;
    // questionid  question #
    // choice id is the choice id for a specific survey. For all courses then which choice option.
    // cid is the current course, if its 0 then its all courses;
    // surveyid is the surveyid for the selected course. If its all courses, then it will 0;
    // tagid  is the tagid finding for the matching surveys
    // stdate start date for the surveys (0 if not used)
    // nddate end date for the surveys (0 if not used)
    // partner partner - blank if not used.
    global $DB, $USER, $COURSE;
    $plugin = 'block_questionreport';
    $retval = get_string('none', $plugin);
    $partnersql = '';
    $gtnpr = 0;
    if ($partner > '') {
        $comparevalue = $DB->sql_compare_text($partner);
        $partnerid = get_config($plugin, 'partnerfield');
        $comparevalue = $comparevalue + 1;
        $partnersql = 'JOIN {customfield_data} cd ON cd.instanceid = m.course AND cd.fieldid = '.$partnerid .' AND cd.value = '.$comparevalue;
    }
    if ($teacher > "") {
        // Get teachers separated by roles.
        $roles = get_config('block_questionreport', 'roles');
        $roles = str_replace('"', "", $roles);
    }
    if ($surveyid > 0) {
        if ($ctype == 'M') {
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
            $qname = $DB->get_field('questionnaire_question', 'name', array('id' => $questionid));
            if ($totres > 0) {
                $totgoodsql  = "SELECT count(rankvalue) ";
                $fromgoodsql = " FROM {questionnaire_response_rank} mr ";
                if ($qname == 'NPS') {
                    $wheregoodsql = "WHERE mr.question_id = ".$questionid ." AND choice_id = ".$choiceid. " AND (rankvalue = 9 or rankvalue = 10) ";
                    $wherenps = "WHERE mr.question_id = ".$questionid ." AND choice_id = ".$choiceid. " AND (rankvalue < 9 ) ";
                } else {
                    $wheregoodsql = "WHERE mr.question_id = ".$questionid ." AND choice_id = ".$choiceid. " AND (rankvalue = 4 or rankvalue = 5) ";
                }
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
                    if ($qname == 'NPS') {
                        $percent = ($totgood / $totres) * 100;
                        $totnpr = $totgoodsql .' '.$fromgoodsql. ' '.$wherenps;
                        $totnpr = $DB->count_records_sql($totnpr, $paramsql);
                        $percent2 = ($totnpr / $totres) * 100;
                        $percent = $percent - $percent2;
                        $retval = round($percent, 0)."(%)";
                    } else {
                        $percent = ($totgood / $totres) * 100;
                        $retval = round($percent, 0)."(%)";
                    }
                } else {
                    $retval = "0(%)";
                }
            }
        } else {
            $sqlext = "SELECT COUNT(ts.courseid) cdtot
                       FROM {local_teaching_survey} ts";
            $whereext = "WHERE 1 = 1";
            $paramsext = array();
            if ($stdate > 0) {
                $std = strtotime($stdate);
                $whereext = $whereext . " AND coursedate >= :std";
                $paramsext['std'] = $std;
            }

            if ($nddate > 0) {
                $endtd = strtotime($nddate);
                $whereext = $whereext . " AND coursedate <= :endtd";
                $paramsext['endtd'] = $endtd;
            }
            $sqlext = $sqlext .' '.$whereext;
            $respext = $DB->get_record_sql($sqlext, $paramsext);


            $totres = $respext->cdtot;
            if ($totres > 0) {
                $sqlext = "SELECT COUNT(ts.courseid) cdgood
                FROM {local_teaching_survey} ts";
                switch ($cid) {
                    case "1":
                    $whereext = "where satisfied >=4";
                    break;
                    case "2":
                    $whereext = "where topics >=4";
                    break;
                    case "3" :
                    $whereext = "where online >=4";
                    break;
                    case "4" :
                    $whereext = "where zoom >=4";
                    break;
                    case "5" :
                    $whereext = "where community >=4";
                    break;
                    case "6" :
                    $whereext = "where covid >=4";
                    break;
                    case "7" :
                    $whereext = "where practice >=4";
                    break;
                    case "8" :
                    $whereext = "where reccomend >= 9";
                    $where1 = "where reccommend <= 8";
                    break;
                }
                if ($stdate > 0) {
                    $std = strtotime($stdate);
                    $whereext = $whereext . " AND coursedate >= :std";
                    $where1 = $where1 . " AND coursedate >= :std";
                    $paramsext['std'] = $std;
                }
                if ($nddate > 0) {
                    $endtd = strtotime($nddate);
                    $whereext = $whereext . " AND coursedate <= :endtd";
                    $where1 = $where1 . " AND coursedate <= :endtd";
                    $paramsext['endtd'] = $endtd;
                }
                $sqlext = $sqlext .' '.$whereext;
                $respext = $DB->get_record_sql($sqlext, $paramsext);
                $totgood = $respext->cdgood;
                if ($totgood > 0) {
                    if ($cid <> 8) {
                        $percent = ($totgood / $totres) * 100;
                        $retval = round($percent, 0)."(%)";
                    } else {
                        $percent = ($totgood / $totres) * 100;
                        $sqlnpr = $sqlext .' '.$where1;
                        $repnpr = $DB->get_record_sql($sqlnpr, $paramsext);
                        $totnpr = $repnpr->cdgood;
                        $gtnpr = ($totnpr / $totres) * 100;
                        $percent = $percent - $gtnpr;
                        $retval = round($percent, 0)."(%)";
                    }
                } else {
                    $retval = "0(%)";
                }
            }

        }

    } else  {
        // Get all the courses;
        $gtres = 0;
        $gttotres = 0;
        $gtnpr = 0;
        if ($portfolio > "" && $portfolio > 0) {
            $portfieldid = get_config($plugin, 'portfoliofield');
            $portid = $DB->get_field('customfield_field', 'configdata', array('id' => $portfieldid));
        }
        $coursfilter = '0';
        $filtertype = '0';
        if ($cid <> '0') {
            $filtertype = substr($cid, 0, 1);
            $coursefilter = substr($cid, 2);
        }
        $sqlcourses = "SELECT m.course, m.id, m.instance
                         FROM {course_modules} m
                         JOIN {tag_instance} ti on ti.itemid = m.id " .$partnersql. "
                        WHERE m.module = ".$moduleid. "
                          AND ti.tagid = ".$tagid . "
                          AND m.deletioninprogress = 0";
        if ($filtertype == 'M' and $coursefilter > '0') {
            $sqlcourses = $sqlcourses ." AND m.course = ".$coursefilter;
        }
        if ($filtertype == 'A' ) {
            $sqlcourses = $sqlcourses ." AND 2 = 4";
        }
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
            if ($valid && $portfolio > "" && $portfolio > '0')  {
                $courseport = $DB->get_field('customfield_data', 'intvalue', array('instanceid' => $survey->course,
                'fieldid' => $portfieldid));
                if ($courseport != $portfolio) {
                    $valid = false;
                }
            }
            $teacher = trim($teacher);
            $lt = strlen($teacher);
            if ($valid and $lt == 0) {
                $lfroleid = $DB->get_field('role','id', array('shortname' => 'leadfacilitator'));
                $adminvalue = get_config($plugin, 'adminroles');
                $adminarray = explode(',',$adminvalue);
                // check to see if they are an admin.
                $adminuser = false;
                $is_admin = block_questionreport_is_admin();
                if (!!$is_admin) {
                    $adminuser = true;
                } else {
                    $context = context_course::instance($COURSE->id);
                    $roles = get_user_roles($context, $USER->id, true);
                    foreach ($adminarray as $val) {
                   	    $sqladmin = "SELECT * FROM {role_assignments}
       	                             AS ra LEFT JOIN {user_enrolments}
       	                             AS ue ON ra.userid = ue.userid
        	                            LEFT JOIN {role} AS r ON ra.roleid = r.id
        	                            LEFT JOIN {context} AS c ON c.id = ra.contextid
        	                            LEFT JOIN {enrol} AS e ON e.courseid = c.instanceid AND ue.enrolid = e.id
        	                            WHERE r.id= ".$val." AND ue.userid = ".$USER->id. " AND e.courseid = ".$COURSE->id;
                         $radmin = $DB->get_records_sql($sqladmin, array( ''));
                         if ( $radmin ) {
                             $adminuser = true;
                         }
                    }
                    // check the system roles.
                    if (!$adminuser) {
                        $systemcontext = context_system::instance();
                        $sroles = get_user_roles($systemcontext, $USER->id, true);
                        foreach ($adminarray as $val) {
                           foreach ($sroles as $rl) {
                              if ( $rl->roleid == $val ) {
                                 $adminuser = true;
                              }
                         }
                    }
                 }
              }
              if (!$adminuser) {
          	      $lf = true;
          	      $teacher = $teacher;
              }
            }
            if ($valid and $teacher > "") {
                $validteacher = false;
                $context = context_course::instance($survey->course);
                $contextid = $context->id;
                 $roles = get_user_roles($context, $teacher, true);
                //  echo '$roles, ', $roles;
                 foreach($roles as $rl) {
              	   $rlrole = $rl->roleid;
                   $sqlteacher = "SELECT u.id, u.firstname, u.lastname
                                    FROM {user} u
                                    JOIN {role_assignments} ra on ra.userid = u.id
                                     AND   ra.contextid = :context
                                     AND roleid = ".$rlrole;
                   $paramteacher = array ('context' => $contextid);
                   $teacherlist = $DB->get_records_sql($sqlteacher, $paramteacher);
                    $tlist = '';
                    foreach($teacherlist as $te) {
                       if ($te->id == $teacher) {
                           $validteacher = true;
                       }
                   }
                }
                if (!$validteacher) {
                    $valid = false;
                }
            }
            $sid = $survey->instance;
            $qid = $DB->get_field('questionnaire_question', 'id', array('position' => '1', 'surveyid' => $sid, 'type_id' => '8'));
            if (!$valid) {
                $choices = $DB->get_records('questionnaire_quest_choice', array('question_id' => $qid));
                $cnt = 0;
                foreach ($choices as $choice) {
                    $chid = $choice->id;
                    $cnt = $cnt + 1;
                    if ($cnt == $choiceid) {
                        break;
                    }
                }
            }
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
                $wherenps = '';
                if ($qname == 'NPS') {
                    $wheregoodsql = "WHERE mr.question_id = ".$questionid ." AND choice_id = ".$choiceid. " AND (rankvalue = 9 or rankvalue = 10) ";
                    $wherenps = "WHERE mr.question_id = ".$questionid ." AND choice_id = ".$choiceid. " AND (rankvalue < 9 ) ";
                }
                $paramsql = array();
                if ($stdate > 0) {
                    $fromgoodsql = $fromgoodsql .' JOIN {questionnaire_response} qr on qr.id = mr.response_id';
                    $wheregoodsql = $wheregoodsql . ' AND qr.submitted >= :stdate';
                    $wherenps = $wherenps . ' AND qr.submitted >= :stdate';
                    $std = strtotime($stdate);
                    $paramsql['stdate'] = $std;
                }
                if ($nddate > 0) {
                    $fromgoodsql = $fromgoodsql .' JOIN {questionnaire_response} qr2 on qr2.id = mr.response_id';
                    $wheregoodsql = $wheregoodsql . ' AND qr2.submitted <= :nddate';
                    $wherenps = $wherenps . ' AND qr2.submitted <= :nddate';
                    $ndt = strtotime($nddate);
                    $paramsql['nddate'] = $ndt;
                }
                $totsql = $totgoodsql .' '.$fromgoodsql. ' '.$wheregoodsql;
                $totgood = $DB->count_records_sql($totsql, $paramsql);
                if ($totgood > 0) {
                    $gttotres = $gttotres + $totgood;
                    if ($qname == 'NPS') {
                        $totnpr = $totgoodql . ' '.$fromgoodsql.' '.$wherenps;
                        $gtnpr = $DB->count_records_sql($totnpr, $paramsql);
                    }
                }
            }
        }
        $sqlext = "SELECT COUNT(ts.courseid) cdtot
                   FROM {local_teaching_survey} ts";
        $whereext = "WHERE 1 = 1";
        $paramsext = array();
        if ($stdate > 0) {
            $std = strtotime($stdate);
            $whereext = $whereext . " AND coursedate >= :std";
            $paramsext['std'] = $std;
        }

        if ($nddate > 0) {
            $endtd = strtotime($nddate);
            $whereext = $whereext . " AND coursedate <= :endtd";
            $paramsext['endtd'] = $endtd;
        }
        if ($portfolio > "") {
            $whereext = $whereext . " AND (port1id = ".$portfolio. " or port2id = ".$portfolio ." )" ;
        }
        if ($teacher > " ") {
            $whereext = $whereext . " AND (teacher1id = ".$teacher. " or teacher2id = ".$teacher ." )" ;
        }
        if ($filtertype == 'A' and $coursefilter > '0') {
            $whereext = $whereext ." AND ts.courseid = ".$coursefilter;
        }
        if ($filtertype == 'M' ) {
             $whereext = $whereext ." AND 2 = 4";
        }
       // echo $sqlext;

        $sqlext = $sqlext .' '.$whereext;
        $respext = $DB->get_record_sql($sqlext, $paramsext);
        $where1 = '';
        $gtres = $gtres + $respext->cdtot;
        if ($respext->cdtot > 0) {
            $sqlext = "SELECT COUNT(ts.courseid) cdgood
            FROM {local_teaching_survey} ts";
            if (!isset($cnt)) {
                $cnt = 1;
            }
            switch ($cnt) {
                case "1":
                $whereext = "where satisfied >=4";
                break;
                case "2":
                $whereext = "where topics >=4";
                break;
                case "3" :
                $whereext = "where online >=4";
                break;
                case "4" :
                $whereext = "where zoom >=4";
                break;
                case "5" :
                $whereext = "where community >=4";
                break;
                case "6" :
                $whereext = "where covid >=4";
                break;
                case "7" :
                $whereext = "where practice >=4";
                break;
                case "8" :
                $whereext = "where reccomend >= 9";
                $where1 = "where reccommend <= 8";
                break;
            }
            if ($stdate > 0) {
                $std = strtotime($stdate);
                $whereext = $whereext . " AND coursedate >= :std";
                $where1 = $where1 . " AND coursedate >= :std";
                $paramsext['std'] = $std;
            }

            if ($nddate > 0) {
                $endtd = strtotime($nddate);
                $whereext = $whereext . " AND coursedate <= :endtd";
                $where1 = $where1 . " AND coursedate >= :std";
                $paramsext['endtd'] = $endtd;
            }
            if ($portfolio > "") {
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
                $whereext = $whereext . " AND (port1name = '".$portval. "' or port2name =' ".$portval ."' )" ;
                $where1 = $where1 . " AND (port1name = '".$portval. "' or port2name = '".$portval ."' )" ;
            }
            if ($teacher > " ") {
                $whereext = $whereext . " AND (teacher1id = ".$teacher. " or teacher2id = ".$teacher ." )" ;
                $where1 = $where1 . " AND (teacher1id = ".$teacher. " or teacher2id = ".$teacher ." )" ;
            }
            if ($filtertype == 'A' and $coursefilter > '0') {
                $whereext = $whereext ." AND ts.courseid = ".$coursefilter;
                $where1 = $where1 ." AND ts.courseid = ".$coursefilter;
            }
            if ($filtertype == 'M' ) {
                $whereext = $whereext ." AND 2 = 4";
                $where1 = $where1 ." AND 2 = 4";
            }

            $sqlext = $sqlext .' '.$whereext;
            $respext = $DB->get_record_sql($sqlext, $paramsext);
            if ($qname <> 'NPS') {
                $tot2 = $respext->cdgood;
                $gttotres = $gttotres + $tot2;
            } else {
             	 $tot2 = $respext->cdgood;
                $gttotres = $gttotres + $tot2;
                $sqlnpr = $sqlext .' '.$where1;
                $repnpr = $DB->get_record_sql($sqlnpr, $paramsext);
                $totnpr = $repnpr->cdgood;
                $gtnpr = $gtnpr + $totnpr;
            }
        }
        $qname = trim($qname);
        if ($gtres > 0) {
            if ($gttotres > 0) {
                if ($qname  <> 'NPS') {
                    $percent = ($gttotres / $gtres) * 100;
                    $retval = round($percent, 0)."(%)";
                } else {
                    $percent = ($gttotres / $gtres) * 100;
                    $percent2 = ($gtnpr / $gtres) * 100;
                    $percent = $percent - $percent2;
                    $retval = round($percent, 0)."(%)";
                }
            } else {
                $retval = "0(%)";
            }
        } else {
            $retval = get_string('none', $plugin);
        }
    }
    return $retval;

}
function block_questionreport_get_question_results($ctype, $position, $courseid, $surveyid, $moduleid, $tagid, $stdate,
                                                   $nddate, $partner, $portfolio, $teacher) {
    // Return the percentage of questions answered with a rank 4, 5;
    // position is the question #
    // cid is the current course, if its 0 then its all courses;
    // surveyid is the surveyid for the selected course. If its all courses, then it will 0;
    // tagid  is the tagid finding for the matching surveys
    // stdate start date for the surveys (0 if not used)
    // nddate end date for the surveys (0 if not used)
    // partner partner - blank if not used.
    global $DB, $USER, $COURSE;
    $plugin = 'block_questionreport';
    $retval = get_string('none', $plugin);
    if ($position == 0) {
        $qname = 'facilitator_rate_content';
    } else {
        $qname = 'facilitator_rate_community';
    }
    $partnersql = '';
    if ($partner > '') {
        $comparevalue = $DB->sql_compare_text($partner);
        $partnerid = get_config($plugin, 'partnerfield');
        $comparevalue = $comparevalue + 1;
        $partnersql = 'JOIN {customfield_data} cd ON cd.instanceid = m.course AND cd.fieldid = '.$partnerid .' AND cd.value = '.$comparevalue;
    }
    if ($teacher > "") {
        $roles = get_config('block_questionreport', 'roles');
        $teacherroles = explode(',', $roles);
    }

    if ($surveyid > 0) {
        // Get the question id;
        if ($ctype <> 'M') {
            $sqlext = "SELECT COUNT(ts.courseid) cdtot
            FROM {local_teaching_survey} ts";
            $whereext = "WHERE courseid = ".$courseid;
            $paramsext = array();
            if ($stdate > 0) {
                $std = strtotime($stdate);
                $whereext = $whereext . " AND coursedate >= :std";
                $paramsext['std'] = $std;
            }

            if ($nddate > 0) {
                $endtd = strtotime($nddate);
                $whereext = $whereext . " AND coursedate <= :endtd";
                $paramsext['endtd'] = $endtd;
            }
            $sqlext = $sqlext .' '.$whereext;
            $respext = $DB->get_record_sql($sqlext, $paramsext);
            $totres = $respext->cdtot;
            if ($totres > 0) {
                $sqlext = "SELECT COUNT(ts.courseid) cdtot
                FROM {local_teaching_survey} ts";
                $whereext = "WHERE courseid = ".$courseid;
                if ($position == 0 ) {
                    $whereext = $whereext ." AND (content1 >= 4 or content2 >=4)";
                } else {
                    $whereext = $whereext ." AND (community1 >=4 or community2 >=4)";
                }
                $paramsext = array();
                if ($stdate > 0) {
                    $std = strtotime($stdate);
                    $whereext = $whereext . " AND coursedate >= :std";
                    $paramsext['std'] = $std;
                }

                if ($nddate > 0) {
                    $endtd = strtotime($nddate);
                    $whereext = $whereext . " AND coursedate <= :endtd";
                    $paramsext['endtd'] = $endtd;
                }
                $sqlext = $sqlext .' '.$whereext;
                $resgood = $DB->get_record_sql($sqlext, $paramsext);
                $totgood = $resgood->cdtot;
                if ($totgood > 0) {
                    $percent = ($totgood / $totres) * 100;
                    $retval = round($percent, 0)."(%)";
                } else {
                    $retval = "0(%)";
                }
            }
        } else {
           // see if the user is a lead facilitator
           $lf = false;
           $lfroleid = $DB->get_field('role','id', array('shortname' => 'leadfacilitator'));
           $context = context_course::instance($courseid);
           $sqllf = "SELECT * FROM {role_assignments}
                             AS ra LEFT JOIN {user_enrolments}
                             AS ue ON ra.userid = ue.userid
                          LEFT JOIN {role} AS r ON ra.roleid = r.id
                          LEFT JOIN {context} AS c ON c.id = ra.contextid
                          LEFT JOIN {enrol} AS e ON e.courseid = c.instanceid AND ue.enrolid = e.id
                          WHERE r.id= ".$lfroleid." AND ue.userid = ".$USER->id. " AND e.courseid = ".$courseid;
            $lfuser = $DB->get_records_sql($sqllf, array( ''));
            if ($lfuser) {
                $lf = true;
                $teacher = $USER->id;
            }
            $questionid = $DB->get_field('questionnaire_question', 'id', array('name' => $qname, 'surveyid' => $surveyid));
            $totresql  = "SELECT count(rankvalue) ";
            if ($teacher > 0) {
                $totresql = "SELECT * ";
            }
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

            if ($teacher > 0) {
                $totres = 0;
                $ui = $teacher;
                $respsql = "SELECT count(id) cntid from {questionnaire_quest_ins} where question_id =".$questionid ." and userid = ".$ui;
                $resp = $DB->get_record_sql($respsql, array(''));
                $totres = $resp->cntid;
            }  else {
                $totres = $DB->count_records_sql($totgoodsql, $paramsql);
            }
            if ($totres > 0) {
                $totgoodsql  = "SELECT count(rankvalue) ";
                if ($teacher > 0) {
                    $totgoodsql = "SELECT * ";
                }
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
                if ($teacher > 0) {
                    $totgood = 0;
                    $ui = $teacher;
              	    $resp = $DB->get_records_sql($totsql, $paramsql);
                    foreach($resp as $res) {
                       $rv = $res->rankvalue;
                       $respondid = $res->response_id;
                       // Check to see the if its for the lead facilitator.
                       $studentid = $DB->get_field('questionnaire_response', 'userid', array('id' => $respondid));
                       $qi = $DB->get_field('questionnaire_quest_ins', 'id', array('question_id' => $questionid, 'staffid' => $ui,
                                 'userid'=> $studentid));
                       if ($qi) {
                          $totgood = $totgood + 1;
                       }
                   }
                } else {
                    $totgood = $DB->count_records_sql($totsql, $paramsql);
                }
                if ($totgood > 0) {
                    $percent = ($totgood / $totres) * 100;
                    $retval = round($percent, 0)."(%)";
                } else {
                    $retval = "0(%)";
                }
            }
        }
    } else  {
        // Get all the courses;
        $gtres = 0;
        $gttotres = 0;
        $coursefilter = '0';
        $filtertype = '0';
        if ($courseid <> '0') {
            $filtertype = substr($courseid, 0, 1);
            $coursefilter = substr($courseid, 2);
        }

        $sqlcourses = "SELECT m.course, m.id, m.instance
                       FROM {course_modules} m
                       JOIN {tag_instance} ti on ti.itemid = m.id " .$partnersql. "
                       WHERE m.module = ".$moduleid. "
                         AND ti.tagid = ".$tagid . "
                         AND m.deletioninprogress = 0";
        // Check to see if the user is a lead facilator.
        $lfroleid = $DB->get_field('role','id', array('shortname' => 'leadfacilitator'));
        $lf = false;
        if ($filtertype == 'M' and $coursefilter > '0') {
            $sqlcourses = $sqlcourses .' AND m.course ='.$coursefilter;
        }
        if ($filtertype == 'A') {
            $sqlcourses = $sqlcourses .' AND 2 = 3';
        }
        if ($coursefilter == '0') {
        	   // Check to see if the user is an admin.
            $adminvalue = get_config($plugin, 'adminroles');
            $adminarray = explode(',',$adminvalue);
            // check to see if they are an admin.
            $adminuser = false;
            $is_admin = block_questionreport_is_admin();
            if (!!$is_admin) {
                $adminuser = true;
            } else {
                $context = context_course::instance($COURSE->id);
                $roles = get_user_roles($context, $USER->id, true);
                foreach ($adminarray as $val) {
                   	$sqladmin = "SELECT * FROM {role_assignments}
       	                        AS ra LEFT JOIN {user_enrolments}
       	                        AS ue ON ra.userid = ue.userid
        	                      LEFT JOIN {role} AS r ON ra.roleid = r.id
        	                      LEFT JOIN {context} AS c ON c.id = ra.contextid
        	                      LEFT JOIN {enrol} AS e ON e.courseid = c.instanceid AND ue.enrolid = e.id
        	                      WHERE r.id= ".$val." AND ue.userid = ".$USER->id. " AND e.courseid = ".$COURSE->id;
                    $radmin = $DB->get_records_sql($sqladmin, array( ''));
                    if ( $radmin ) {
                         $adminuser = true;
                    }
                }
                // check the system roles.
                if (!$adminuser) {
                    $systemcontext = context_system::instance();
                    $roles = get_user_roles($systemcontext, $USER->id, true);
                    foreach ($adminarray as $val) {
                      foreach ($roles as $rl) {
                        if ( $rl->roleid == $val ) {
                             $adminuser = true;
                        }
                    }
                }
             }
          }
          if (!$adminuser) {
               $lf = true;
               $sqllf = "SELECT mc.instanceid
                        FROM {role_assignments} ra
                        JOIN {context} mc ON mc.id = ra.contextid
                        WHERE ra.roleid =".$lfroleid . " and ra.userid = ".$USER->id;
               $clist = $DB->get_records_sql($sqllf);
               $cs = "";
               $cnt = 0;
               foreach($clist as $cl) {
                  if ($cnt == 0) {
                      $cs = "'".$cl->instanceid."'";
                  } else {
                      $cs = $cs.",'".$cl->instanceid."'";
                  }
                  $cnt = $cnt + 1;
              }
              $sqlcourses = $sqlcourses ." AND m.course in (".$cs.")";
           }
        }
        $context = context_course::instance($COURSE->id);
        $roles = get_user_roles($context, $USER->id, true);
        $surveys = $DB->get_records_sql($sqlcourses);
        foreach($surveys as $survey) {
            // Check to see if the user has rights.
            $valid = true;
            if ($valid && $portfolio > "" && $portfolio > '0') {
                $portfieldid = get_config($plugin, 'portfoliofield');
                $courseport = $DB->get_field('customfield_data', 'intvalue', array('instanceid' => $survey->course,
                'fieldid' => $portfieldid));
                if ($courseport != $portfolio) {
                    $valid = false;
                }
            }
            if ($valid and $teacher > "") {
                $context = context_course::instance($survey->course);
                $contextid = $context->id;
                $validteacher = false;
                $roles = get_user_roles($context, $teacher, true);

                foreach($roles as $rllist) {
                     $rid = $rllist->roleid;
                     $sqlteacher = "SELECT u.id, u.firstname, u.lastname
                                    FROM {user} u
                                    JOIN {role_assignments} ra on ra.userid = u.id
                                    AND   ra.contextid = :context
                                    AND roleid = ".$rid;
                     $paramteacher = array ('context' => $contextid);
                     $teacherlist = $DB->get_records_sql($sqlteacher, $paramteacher);
                     $tlist = '';
                     foreach($teacherlist as $te) {
                        if ($te->id == $teacher) {
                            $validteacher = true;
                        }
                     }
                }
                if (!$validteacher) {
                    $valid = false;
                }
            }
            $sid = $survey->instance;

            $questionid = $DB->get_field('questionnaire_question', 'id', array('name' => $qname, 'surveyid' => $sid));
            if (empty($questionid) or !$valid) {
                $totres = 0;
            } else {
                $totresql  = "SELECT count(rankvalue) ";
                if ($teacher > 0) {
                    $totresql  = "SELECT * ";
                }
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
                if ($teacher > 0) {
             	    $totres = 0;
                    $ui = $teacher;
                    $resp = $DB->get_records_sql($totgoodsql, $paramsql);
                    foreach($resp as $res) {
                       $rv = $res->rankvalue;
                       $respondid = $res->response_id;
                       // Check to see the if its for the lead facilitator.
                       $studentid = $DB->get_field('questionnaire_response', 'userid', array('id' => $respondid));
                       $qi = $DB->get_field('questionnaire_quest_ins', 'id', array('question_id' => $questionid, 'staffid' => $ui,
                                 'userid'=> $studentid));
                       if ($qi) {
                           $totres = $totres + 1;
                       }
                	  }
                }  else {
                  $totres = $DB->count_records_sql($totgoodsql, $paramsql);
                }
            }

            if($totres > 0) {
                $gtres = $gtres + $totres;
                if ($lf) {
                    $totgoodsql  = "SELECT * ";
                } else {
                    $totgoodsql  = "SELECT count(rankvalue) ";
                }
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
                if ($lf) {
               	    $totgood = 0;
                    $ui = $teacher;
                    $resp = $DB->get_records_sql($totsql, $paramsql);
                    foreach($resp as $res) {
                       $rv = $res->rankvalue;
                       $respondid = $res->response_id;
                       // Check to see the if its for the lead facilitator.
                       $studentid = $DB->get_field('questionnaire_response', 'userid', array('id' => $respondid));
                       $qi = $DB->get_field('questionnaire_quest_ins', 'id', array('question_id' => $questionid, 'staffid' => $ui,
                                 'userid'=> $studentid));
                       if ($qi) {
                          $totgood = $totgood + 1;
                       }
                   }
                } else {
                   $totgood = $DB->count_records_sql($totsql, $paramsql);
                }
                if ($totgood > 0) {
                    $gttotres = $gttotres + $totgood;
                }
            }
        }
//xxxx
        // Add in the non moodle courses.
        $sqlext = "SELECT COUNT(ts.courseid) cdtot
        FROM {local_teaching_survey} ts";
        $whereext = "WHERE 1 = 1";
        $paramsext = array();
        if ($stdate > 0) {
            $std = strtotime($stdate);
            $whereext = $whereext . " AND coursedate >= :std";
            $paramsext['std'] = $std;
        }

        if ($nddate > 0) {
            $endtd = strtotime($nddate);
            $whereext = $whereext . " AND coursedate <= :endtd";
            $paramsext['endtd'] = $endtd;
        }
        if ($portfolio > "") {
            $whereext = $whereext . " AND (port1id = ".$portfolio. " or port2id = ".$portfolio ." )" ;
        }
        if ($teacher > "") {
            $whereext = $whereext . " AND (teacher1id = ".$teacher. " or teacher2id = ".$teacher ." )" ;
        }

        if ($filtertype == 'A' and $coursefilter > '0') {
            $whereext = $whereext .' AND ts.courseid ='.$coursefilter;
        }
        if ($filtertype == 'M') {
            $whereext = $whereext .' AND 2 = 3';
        }

        $sqlext = $sqlext .' '.$whereext;

        $respext = $DB->get_record_sql($sqlext, $paramsext);

        $gtres = $gtres + $respext->cdtot;
        if ($respext->cdtot > 0) {
            if ($ctype == 'M') {
                if ($qname == 'facilitator_rate_content') {
                    $sqlext = "SELECT COUNT(ts.courseid) cdgood
                    FROM {local_teaching_survey} ts";
                    $whereext =" where (content1 >=4 or content2 >=4)";
                } else {
                    $sqlext = "SELECT COUNT(ts.courseid) cdgood
                    FROM {local_teaching_survey} ts";
                    $whereext =" where (community1 >=4 or community2 >=4)";
                }
            } else {
                if ($position == '0') {
                    $sqlext = "SELECT COUNT(ts.courseid) cdgood
                    FROM {local_teaching_survey} ts";
                    $whereext =" where (content1 >=4 or content2 >=4)";
                } else {
                    $sqlext = "SELECT COUNT(ts.courseid) cdgood
                    FROM {local_teaching_survey} ts";
                    $whereext =" where (community1 >=4 or community2 >=4)";
                }

            }
            if ($stdate > 0) {
                $std = strtotime($stdate);
                $whereext = $whereext . " AND coursedate >= :std";
                $paramsext['std'] = $std;
            }

            if ($nddate > 0) {
                $endtd = strtotime($nddate);
                $whereext = $whereext . " AND coursedate <= :endtd";
                $paramsext['endtd'] = $endtd;
            }
            if ($portfolio > "") {
                $whereext = $whereext . " AND (port1id = ".$portfolio. " or port2id = ".$portfolio ." )" ;
            }
            if ($teacher > " ") {
                $whereext = $whereext . " AND (teacher1id = ".$teacher. " or teacher2id = ".$teacher ." )" ;
            }

            $sqlext = $sqlext .' '.$whereext;
            $respext = $DB->get_record_sql($sqlext, $paramsext);
            $tot2 = $respext->cdgood;
            $gttotres = $gttotres + $tot2;
        }
        if ($gtres > 0) {
            if ($gttotres > 0) {
                $percent = ($gttotres / $gtres) * 100;
                $retval = round($percent, 0)."(%)";
            } else {
                $retval = "0(%)";
            }
        } else {
            $retval = get_string('none', $plugin);
        }
    }
    return $retval;
}
function block_questionreport_get_essay($ctype, $surveyid) {
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
    $essaylist[10] = 'All';

    return $essaylist;
}

function block_questionreport_get_chartquestions($surveyid) {
    global $DB, $COURSE;
    $plugin = 'block_questionreport';
    $essaylist = array();
    $essaylist[0] = get_string('none', $plugin);
    $customfields = $DB->get_records('questionnaire_question', array('type_id' => '8', 'surveyid' => $surveyid, 'deleted' => 'n'));
    foreach ($customfields as $field) {
        $fid = $field->id;
        $choices = $DB->get_records('questionnaire_quest_choice', array('question_id' => $fid));
        $choicecnt = 0;
        foreach ($choices as $choice) {
            $fid = $choice->id;
            $display = $choice->content;
            $display = strip_tags($display);
            $display = trim($display);
            $essaylist[$fid] = $display;
        }
    }
    return $essaylist;
}
function block_questionreport_get_essay_results($ctype, $questionid, $stdate, $nddate, $limit, $surveyid, $action, $portfolio, $teacher, $courseid) {
    global $DB, $USER, $COURSE, $CFG;
    $plugin = 'block_questionreport';
    require_once($CFG->libdir . '/pdflib.php');
    if ($action == 'pdf') {
        $html = '<table border="0" cellpadding="6">';
    }
    if ($ctype == 'M') {
        // If limit = 0 return all essay results. Otherwise return the limit.
        if ($action == 'pdf' ) {
            $quest = $DB->get_field('questionnaire_question', 'content', array('id' => $questionid));
            $html = $html .'<tr><td><b>Question: '.$quest.'</b></td></tr>';
        }

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
                if ($action == 'pdf') {
                    $html = $html .'<tr><td>'.str_replace("&nbsp;", '', trim(strip_tags($cr))).'</td></tr>';
                } else {
                    $return[] = str_replace("&nbsp;", '', trim(strip_tags($cr)));
                }
                $cnt = $cnt + 1;
                if ($limit > 0 and $limit > $cnt) {
                    break;
                }
            }
        }
    } else {
        switch($questionid) {
            case "1":
            $sql = "SELECT uidsurvey, learning response";
            $quest = "What is the learning from this course that you are most excited about trying out?";
            break;
            case "2":
            $sql = "SELECT uidsurvey, navigate response ";
            $quest = "How, if in any way, this course helped you prepare for school opening after COVID-19?'";
            break;
            case "3":
            $sql = "SELECT uidsurvey, overall response ";
            $quest = "Overall, what went well in this course?";
            break;
            case "4":
            $sql = "SELECT uidsurvey, activities response ";
            $quest = "Which activities best supported your learning in this course?";
            break;
            case "5":
            $sql = " SELECT uidsurvey, improved response ";
            $quest = " What could have improved your experience in this course?";
            break;
            case "6":
            $quest = "Why did you choose this rating?";
            $sql = " SELECT uidsurvey, choose response ";
            break;
            case "7":
            $quest = " Do you have additional comments about  this course?'";
            $sql = " SELECT uidsurvey, comment response ";
            break;
        }
        $return = [];
        $sql = $sql. " FROM {local_teaching_survey} WHERE courseid  = ".$surveyid;
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
        $resultlist = $DB->get_records_sql($sql, $paramsql);
        if (!empty($resultlist)) {
            $cnt = 0;
            if ($action == 'pdf' ) {
                $html = $html .'<tr><td><b>Question: '.$quest.'</b></td></tr>';
            }
            foreach($resultlist as $resid) {
                $cr = $resid->response;
                if ($action == 'pdf') {
                    $html = $html .'<tr><td>'.str_replace("&nbsp;", '', trim(strip_tags($cr))).'</td></tr>';
                } else {
                    $return[] = str_replace("&nbsp;", '', trim(strip_tags($cr)));
                }
                $cnt = $cnt + 1;
                if ($limit > 0 and $limit > $cnt) {
                    break;
                }
            }
        }
    }
    if ($action == 'view') {
        return $return;
    } else {
        $doc = new pdf;
        $doc->setPrintHeader(false);
        $doc->setPrintFooter(false);
        $doc->setFont('helvetica',' ', '4');
        $doc->SetFillColor(0,255,0);
        $doc->AddPage();

        $height = get_config($plugin, 'height_value');
        $width = get_config($plugin, 'width_value');

        //       $filename = get_config($plugin, 'logofile');
        //       $fparts = pathinfo($filename, $options = null);
        //var_dump($fparts);
        //     $ext = $fparts['extension'];
        //   echo 'ext '.$ext;
        //  exit();
        $fs = get_file_storage();
        // Prepare file record object
        //$fileinfo = self::get_certificate_image_fileinfo($this->context->id);
        //$firstpageimagefile = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
        //                                    $fileinfo['itemid'], $fileinfo['filepath'], $this->get_instance()->certificateimage);
        // Read contents
        //if ($firstpageimagefile) {
        //    $temp_filename = $firstpageimagefile->copy_content_to_temp(self::CERTIFICATE_COMPONENT_NAME, 'first_image_');
        //   $doc->Image($temp_filename, 0, 0, $width, $height);
        //        @unlink($temp_filename);
        // } else {
        //         print_error(get_string('filenotfound', 'simplecertificate', $this->get_instance()->certificateimage));
        // }
        $ext = 'png';
        $url = $CFG->wwwroot;
        $size = getimagesize('images/logo.png');
        $width = $size[0];
        $height = $size[1];
        $img_yx_ratio = $height/$width;
        // Convert width and height to preferred dimensions,
        // and convert to mm.
        $mm_conv_factor = 0.2645833333;
        $margins = $doc->getMargins(); // in mm.
        $page_width = $doc->getPageWidth() - $margins['left'] - $margins['right']; // in mm.
        $max_width = 1600 * $mm_conv_factor;
        $max_height = $max_width * $img_yx_ratio * $mm_conv_factor;
        $img_left = $page_width/2 - $max_width/2;
        $img_top = '';

        $doc->Image('images/logo.png', '', 15, $max_width, $max_height, $ext, $url, 'T', 2, 150, 'C', false, false, 0, true, false, false);

        $doc->SetXY(5000, 50);
        $plugin = 'block_questionreport';
        $tagvalue = get_config($plugin, 'tag_value');
        $tagid = $DB->get_field('tag', 'id', array('name' => $tagvalue));
        $moduleid = $DB->get_field('modules', 'id', array('name' => 'questionnaire'));
        $htmlhead = '';
        $partner = '';
        $partnerid = get_config($plugin, 'partnerfield');

        // Check to see if the user is an admin.
        $adminvalue = get_config($plugin, 'adminroles');
        $adminarray = explode(',',$adminvalue);
        // check to see if they are an admin.
        $adminuser = false;
        $is_admin = block_questionreport_is_admin();
        if (!!$is_admin) {
            $adminuser = true;
        } else {
            $context = context_course::instance($COURSE->id);
            $roles = get_user_roles($context, $USER->id, true);
            foreach ($adminarray as $val) {
                $sql = "SELECT * FROM {role_assignments}
                AS ra LEFT JOIN {user_enrolments}
                AS ue ON ra.userid = ue.userid
                LEFT JOIN {role} AS r ON ra.roleid = r.id
                LEFT JOIN {context} AS c ON c.id = ra.contextid
                LEFT JOIN {enrol} AS e ON e.courseid = c.instanceid AND ue.enrolid = e.id
                WHERE r.id= ".$val." AND ue.userid = ".$USER->id. " AND e.courseid = ".$COURSE->id;
                $result = $DB->get_records_sql($sql, array( ''));
                if ( $result ) {
                    $adminuser = true;
                }
            }
           // check the system roles.
           if (!$adminuser) {
                $systemcontext = context_system::instance();
                $roles = get_user_roles($systemcontext, $USER->id, true);
                foreach ($adminarray as $val) {
                   foreach ($roles as $rl) {
                      if ( $rl->roleid == $val ) {
                          $adminuser = true;
                      }
                  }
               }
           }

        }
        if ($courseid > 0) {
            if ($ctype == 'M') {
                $cname = $DB->get_field('course','fullname', array ('id' => $courseid));
                $htmlhead = '<h1 style="font-size:20px;">'.$cname.'</h1><br>';
                $role = $DB->get_record('role', array('shortname' => 'leadfacilitator'));
                $context = context_course::instance($courseid);
               // echo ' context '.$context;

                $tlist = get_role_users($role->id, $context);
                // Write list of facilitators included in this report.
                $htmlhead = $htmlhead .'<h2 style="font-size:12px;">Facilitators</h2>';
                $htmlhead = $htmlhead . '<p style="font-size:8px;">';
                $base = $htmlhead;
                $is_first = true;
                foreach ($tlist as $key => $value) {
                   if ($value->id == $USER->id) {
                        $htmlhead = $base . fullname($value);
                        break;
                  } else {
                        if (!!$is_first) {
                            $htmlhead = $htmlhead . fullname($value);
                            $is_first = false;
                        } else {
                            $htmlhead = $htmlhead . ', ' . fullname($value);
                        }
                   }
                }
                $htmlhead = $htmlhead . '</p>';
                // Partner.
                $partnervalue = $DB->get_field('customfield_data', 'intvalue', array ('fieldid' => $partnerid, 'instanceid' => $courseid));
                if ($partnervalue) {
                    $partnervalue = $partnervalue - 1;
                    $htmlhead = $htmlhead.'<br /><br /><h2 style="font-size:12px;margin-top:16px;">Partner</h2>';
                    $plist = block_questionreport_get_partners_list();
                    $htmlhead = $htmlhead . '<span style="font-size:8px;">' . $plist[$partnervalue] . '</span>';
                }
            } else {
                $cname = $DB->get_field('local_teaching_course', 'coursename', array('id' => $courseid));
                $htmlhead = '<h1 style="font-size:20px;">'.$cname.'</h1><br>';
                // Get the teacher lists
                $tlist = '';
                $tcnt = 0;
                $teacherlists = $DB->get_records('local_teaching_survey', array('courseid' => $courseid));
                foreach($teacherlists as $teachers) {
                    $t1 = $teachers->teacher1id;
                    $t2 = $teachers->teacher2id;
                    if ($t1 > 0) {
                        $tcnt = $tcnt + 1;
                        if ($tcnt == 0) {
                            $tlist = $t1;
                        } else {
                            $tlist = $tlist.','.$t1;
                        }
                    }
                    if ($t2 > 0) {
                        $tcnt = $tcnt + 1;
                        if ($tcnt == 0) {
                            $tlist = $t2;
                        } else {
                            $tlist = $tlist.','.$t2;
                        }
                    }
                }
                if ($tcnt > 0) {
                    $htmlhead = $htmlhead.'<br /><h2 style="font-size:12px;margin-top:16px;">Facilitators</h2>';
                    $sqlteacher = "SELECT distinct(teachername) from {local_teaching_teacher} where id in ($tlist)";
                    $teachernames = $DB->get_records_select($sqlteacher, array());
                    $htmlhead = $htmlhead.'<ul>';
                    foreach($teachernames as $teacher) {
                        $htmlhead = $htmlhead . '<li style="font-size:8px;">'.$teacher->teachername.'</li>';
                    }
                    $htmlhead = $htmlhead.'</ul>';
                }
            }

        }
        $html1 = $htmlhead . '<br /><h2 style="font-size:12px;margin-top:24px;">Facilitation Summary (% Agree and Strongly Agree)</h2>';
        $html1 .= '<table border="0.25" cellpadding="4">';
        $html1 .= '<tr><th></th><th align="center" style="font-weight:bold;font-size:8px;">This Course</th><th align="center" style="font-weight:bold;font-size:8px;">All Courses</th></tr>';

        if ($ctype == 'M') {
            $params = array();
            $courseid = $DB->get_field('questionnaire_survey','courseid', array('id' => $surveyid));
            $sql = 'select min(position) mp from {questionnaire_question} where surveyid = '.$surveyid .' and type_id = 11 order by position desc';
            $records = $DB->get_record_sql($sql, $params);
            $stp = $records->mp;
            for ($x = 0; $x <= 1; $x++) {
                if ($x == 0) {
                    $font = ' style="background-color:#ebebeb;font-size:8px;"';
                    $qcontent = "He/she/they facilitated the content clearly. ";
                } else {
                    $font = ' style="font-size:8px;"';
                    $qcontent = "He/she/they effectively built a community of learners. ";
                }
                $cr = block_questionreport_get_question_results($ctype, $x, $courseid, $surveyid, $moduleid, $tagid, $stdate, $nddate, $partner, '0', '0');
                $all = block_questionreport_get_question_results($ctype, $x, $limit, 0, $moduleid, $tagid, $stdate, $nddate, $partner, $portfolio, $teacher);
                $html1 .= '<tr' .$font.'><td>'.$qcontent.'</td><td align="center" valign="middle">'.$cr.'</td><td align="center" valign="middle">'.$all.'</td></tr>';
            }
         } else {
            for($x =0; $x <=1; $x++) {
                if ($x == 0) {
                    $font = ' style="background-color:#ebebeb;font-size:8px;"';
                    $qcontent = "He/she/they facilitated the content clearly. ";
                } else {
                    $font = '';
                    $qcontent = "He/she/they effectively built a community of learners. ";
                }
                $cr = block_questionreport_get_question_results($ctype, $x, $surveyid, 1, $moduleid, $tagid, $stdate, $nddate, $partner);
                $all = block_questionreport_get_question_results($ctype, $x, $limit, 0, $moduleid, $tagid, $stdate, $nddate, $partner);
                $html1 .= '<tr' .$font.'><td>'.$qcontent.'</td><td align="center" valign="middle">'.$cr.'</td><td align="center" valign="middle">'.$all.'</td></tr>';
            }
        }
        $html1 .= '</table>';
        // Second table.
        $html1 .= '<br /><h2 style="font-size:12px;margin-top:24px;">Session Summary (% Agree and Strongly Agree)</h2>';
        $html1 .= '<table border="0.25" cellpadding="4">';
        $html1 .= '<tr><th></th><th align="center" style="font-weight:bold;font-size:8px;">This Course</th><th align="center" style="font-weight:bold;font-size:8px;">All Courses</th></tr>';

        if ($ctype == 'M') {
            $qcontent = $DB->get_field('questionnaire_question', 'content', array('position' => '1', 'surveyid' => $surveyid, 'type_id' => '8'));
            $qid = $DB->get_field('questionnaire_question', 'id', array('position' => '1', 'surveyid' => $surveyid, 'type_id' => '8'));
            $choices = $DB->get_records('questionnaire_quest_choice', array('question_id' => $qid));
            $choicecnt = 0;
            $qname = $DB->get_field('questionnaire_question', 'name', array('id' => $qid));
            foreach ($choices as $choice) {
                $choiceid = $choice->id;
                $choicecnt = $choicecnt + 1;
                $course = block_questionreport_get_question_results_rank($ctype, $qid, $choiceid, $surveyid, $surveyid, $moduleid, $tagid, $stdate, $nddate, $partner, $portfolio, $teacher, $qname);
                $all = block_questionreport_get_question_results_rank($ctype, $qid, $choicecnt, $limit, 0, $moduleid, $tagid, $stdate, $nddate, $partner, $portfolio, $teacher, $qname);
                if ($choice->id %2 == 0) {
                    $font = ' style="background-color:#ebebeb;font-size:8px;"';
                } else {
                    $font = ' style="font-size:8px;"';
                }
                $html1 .= '<tr'.$font.'><td>'.$choice->content.'</td><td align="center" valign="middle">'.$course.'</td><td align="center" valign="middle">'.$all.'</td></tr>';
            }
            $qcontent = $DB->get_field('questionnaire_question', 'content', array('name' => 'NPS', 'surveyid' => $surveyid));
            $qid = $DB->get_field('questionnaire_question', 'id', array('name' => 'NPS', 'surveyid' => $surveyid));
            $choices = $DB->get_records('questionnaire_quest_choice', array('question_id' => $qid));
            $choicecnt = 0;
            foreach ($choices as $choice) {
                $choiceid = $choice->id;
                $choicecnt = $choicecnt + 1;
                $course = block_questionreport_get_question_results_rank($ctype, $qid, $choiceid, $surveyid, $surveyid, $moduleid, $tagid, $stdate, $nddate, $partner, $portfolio, $teacher, 'NPS');
                $all = block_questionreport_get_question_results_rank($ctype, $qid, $choicecnt, $limit, 0, $moduleid, $tagid, $stdate, $nddate, $partner, $portfolio, $teacher, 'NPS');
                if ($choice->id %2 == 0) {
                    $font = ' style="background-color:#ebebeb;font-size:8px;"';
                } else {
                    $font = ' style="font-size:8px;"';
                }
                if ($adminuser && $choice->content == 'Recommend this course to a colleague or friend.') {
                    $html1 .= '<tr'.$font.'><td>'.$choice->content.'</td><td align="center" valign="middle">'.$course.'</td><td align="center" valign="middle">'.$all.'</td></tr>';
                } else {
                    if ($choice->content != 'Recommend this course to a colleague or friend.') {
                        $html1 .= '<tr'.$font.'><td>'.$choice->content.'</td><td align="center" valign="middle">'.$course.'</td><td align="center" valign="middle">'.$all.'</td></tr>';
                    }
                }
            }


        } else {
            $endloop = 8;
            if ($adminuser) {
                $endloop = 9;
            }
            for ($x=1; $x< $endloop; $x++) {
                switch ($x) {
                    case "1":
                    $quest = "I am satisfied with the overall quality of this course.";
                    break;
                    case "2":
                    $quest = "The topics for this course were relevant for my role. ";
                    break;
                    case "3" :
                    $quest = "The independent online work activities were well-designed to help me meet the learning targets. ";
                    break;
                    case "4" :
                    $quest = "The Zoom meeting activities were well-designed to help me meet the learning targets.";
                    break;
                    case "5" :
                    $quest = "I felt a sense of community with the other participants in this course even though we were meeting virtually. ";
                    break;
                    case "6" :
                    $quest = "This course helped me navigate remote and/or hybrid learning during COVID-19. ";
                    break;
                    case "7" :
                    $quest = "I will apply my learning from this course to my practice in the next 4-6 weeks. ";
                    break;
                    case "8":
                    $quest = "How likely are you to recommend this professional learning to a colleague or friend?";
                    break;
                }
                $course = block_questionreport_get_question_results_rank($ctype, $x, $x, $surveyid, 1, $moduleid, $tagid, $stdate, $nddate, $partner);
                $all = block_questionreport_get_question_results_rank($ctype, $x, $x, $limit, 0, $moduleid, $tagid, $stdate, $nddate, $partner);

                if ($x % 2 == 0) {
                    $font = ' style="background-color:#ebebeb;"';
                } else {
                    $font = '';
                }
                $html1 .= '<tr '.$font.' ><td>'.$quest.'</td><td align="center" valign="middle">'.$course.'</td><td align="center" valign="middle">'.$all.'</td></tr>';
            }
        }

        $html1 .= '</table>';
        $html = $html1;
        //       echo $html;
        $doc->writeHTML($html, $linebreak = true, $fill = false, $reseth = true, $cell = false, $align = '');
        $date = date('Y-m-d');
        $name = 'Evaluation-report-course-'.$cname.'-'.$date.'.pdf';
        $doc->Output($name);

        // $doc->Output();
        exit();
    }
}

function block_questionreport_get_words($ctype, $surveyid, $questionid, $stdate, $nddate, $action, $portfolio, $teacher, $courseid) {
    global $DB;
    $words = [];
    array_push($words, block_questionreport_get_essay_results($ctype, $questionid, $stdate, $nddate, 0, $surveyid, $action, $portfolio, $teacher, $courseid));
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
                $retval = round($percent, 0)."(%)";
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
            $retval = round($percent, 0)."(%)";

        }
    }

    return $retval;

}
// Check to see if the user is a lead facilitator.

function block_questionreport_checklf() {
    global $USER, $COURSE, $DB;
    $lf = false;
    $plugin = 'block_questionreport';
    $lfroleid = $DB->get_field('role','id', array('shortname' => 'leadfacilitator'));
    $adminvalue = get_config($plugin, 'adminroles');
    $adminarray = explode(',',$adminvalue);
    // Check to see if they are an admin.
    $adminuser = false;
    $is_admin = block_questionreport_is_admin();
    if (!!$is_admin) {
        $adminuser = true;
    } else {
         $context = context_course::instance($COURSE->id);
         $roles = get_user_roles($context, $USER->id, true);
         foreach ($adminarray as $val) {
       	    $sqladmin = "SELECT * FROM {role_assignments}
                             AS ra LEFT JOIN {user_enrolments}
                             AS ue ON ra.userid = ue.userid
                          LEFT JOIN {role} AS r ON ra.roleid = r.id
                          LEFT JOIN {context} AS c ON c.id = ra.contextid
                          LEFT JOIN {enrol} AS e ON e.courseid = c.instanceid AND ue.enrolid = e.id
                          WHERE r.id= ".$val." AND ue.userid = ".$USER->id. " AND e.courseid = ".$COURSE->id;
              $radmin = $DB->get_records_sql($sqladmin, array( ''));
              if ( $radmin ) {
                   $adminuser = true;
              }
         }
         // check the system roles.
         if (!$adminuser) {
              $systemcontext = context_system::instance();
              $sroles = get_user_roles($systemcontext, $USER->id, true);
              foreach ($adminarray as $val) {
                  foreach ($sroles as $rl) {
                    if ( $rl->roleid == $val ) {
                         $adminuser = true;
                    }
                }
             }
          }
     }
     if (!$adminuser) {
         $lf = true;
     }
     return $lf;
}

