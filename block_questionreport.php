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
 * Block "people"
 *
 * @package    block_people
 * @copyright  2013 Alexander Bias, Ulm University <alexander.bias@uni-ulm.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class block_questionreport
 *
 * @package    block_questionreport
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_questionreport extends block_base {
    /**
     * init function
     * @return void
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_questionreport');
    }

    /**
     * applicable_formats function
     * @return array
     */
    public function applicable_formats() {
        return array('course-view' => true, 'site' => true);
    }

    /**
     * has_config function
     * @return bool
     */
    public function has_config() {
        return true;
    }

    /**
     * instance_allow_multiple function
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * instance_can_be_hidden function
     * @return bool
     */
    public function instance_can_be_hidden() {
        // By default, instances can be hidden by the user.
        $hideblock = true;
        // If config 'hideblock' is disabled.
        if ((get_config('block_questionreport', 'hideblock')) == '0') {
            // Set value to false, so instance cannot be hidden.
            $hideblock = false;
        }
        return $hideblock;
    }

    /**
     * get_content function
     * @return string
     */
    public function get_content() {
        global $COURSE, $CFG, $OUTPUT, $USER, $DB;
        require_once($CFG->dirroot.'/blocks/questionreport/locallib.php');

        $plugin = 'block_questionreport';

        if ($this->content !== null) {
            return $this->content;
        }

        if (empty($this->instance)) {
            $this->content = '';
            return $this->content;
        }

        // Prepare output.
        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Get context.
        $currentcontext = $this->page->context;

        // Get teachers separated by roles.
        $roles = get_config('block_questionreport', 'roles');
        if (!empty($roles)) {
            $teacherroles = explode(',', $roles);
            $teachers = get_role_users($teacherroles,
                    $currentcontext,
                    true,
                    'ra.id AS raid, r.id AS roleid, r.sortorder, u.id, u.lastname, u.firstname, u.firstnamephonetic,
                            u.lastnamephonetic, u.middlename, u.alternatename, u.picture, u.imagealt, u.email',
                    'r.sortorder ASC, u.lastname ASC, u.firstname ASC');
        } else {
            $teachers = array();
        }

        // Get role names / aliases in course context.
        $rolenames = role_get_names($currentcontext, ROLENAME_ALIAS, true);

        // Get multiple roles config.
        $multipleroles = get_config('block_questionreport', 'multipleroles');

        // Start teachers list.
        $this->content->text .= html_writer::start_tag('div', array('class' => 'teachers'));

        // Initialize running variables.
        $teacherrole = null;
        $displayedteachers = array();
        //$sqlresp = "SELECT COUNT(r.id) FROM {questionnaire_response} r 
        //             WHERE r.questionnaireid = :questionnaireid AND r.complete = 'y'";
        //$paramsresp = array();
                     
        $totrespcourse = 10;
        $totresp = 20;
        $this->content->text .= html_writer::start_tag('table');
        $this->content->text .= html_writer::start_tag('tr');
        $this->content->text .= html_writer::start_tag('td');
        $this->content->text .= html_writer::end_tag('td');
        $this->content->text .= html_writer::start_tag('td');
        $this->content->text .= '<b>'.get_string('thiscourse',$plugin).'</b>';
        $this->content->text .= html_writer::end_tag('td');
        $this->content->text .= html_writer::start_tag('td');
        $this->content->text .= '<b>'.get_string('allcourses',$plugin).'</b>';
        $this->content->text .= html_writer::end_tag('td');
        $this->content->text .= html_writer::end_tag('tr');
        $this->content->text .= html_writer::start_tag('td');
        $this->content->text .= '<b>'.get_string('surveyresp',$plugin).'</b>';
        $this->content->text .= html_writer::end_tag('td');
        $this->content->text .= html_writer::start_tag('td');
        $this->content->text .= '<b>'.$totrespcourse.'</b>';
        $this->content->text .= html_writer::end_tag('td');
        $this->content->text .= html_writer::start_tag('td');
        $this->content->text .= '<b>'.$totresp.'</b>';
        $this->content->text .= html_writer::end_tag('td');
        $this->content->text .= html_writer::end_tag('tr');


        $this->content->text .= html_writer::start_tag('tr');
        $this->content->text .= html_writer::start_tag('td');
        $this->content->text .= '<b>'.get_string('session',$plugin).'</b>';
        $this->content->text .= html_writer::end_tag('td');
        $this->content->text .= html_writer::start_tag('td');
        $this->content->text .= '<b>'.get_string('thiscourse',$plugin).'</b>';
        $this->content->text .= html_writer::end_tag('td');
        $this->content->text .= html_writer::start_tag('td');
        $this->content->text .= '<b>'.get_string('allcourses',$plugin).'</b>';
        $this->content->text .= html_writer::end_tag('td');
        $this->content->text .= html_writer::end_tag('tr');
        // Get the questions
        $questlistsql = "SELECT mq.id, mq.extradata from {questionnaire_survey} ms 
                         JOIN {questionnaire_question} mq on mq.surveyid = ms.id
                         WHERE ms.courseid =".$COURSE->id ." and mq.name = 'Course Ratings' ";  
        $quest = $DB->get_record_sql($questlistsql);
        $qid = $quest->id;
        $extra = $quest->extradata;
        $choices = $DB->get_records('questionnaire_quest_choice', array('question_id' => $qid));
        foreach($choices as $choice) {
           $choicename = $choice->content;
           $curtotal = 0;
           $curtotal = block_questionreport_get_choice_current($choice->id);
           $grandtotal = block_questionreport_get_choice_all($choicename);

           $this->content->text .= html_writer::start_tag('tr');
           $this->content->text .= html_writer::start_tag('td');
           $this->content->text .= $choicename;
           $this->content->text .= html_writer::end_tag('td');
           $this->content->text .= html_writer::start_tag('td');
           $this->content->text .= $curtotal;
           $this->content->text .= html_writer::end_tag('td');
           $this->content->text .= html_writer::start_tag('td');
           $this->content->text .= $grandtotal;
           $this->content->text .= html_writer::end_tag('td');
           $this->content->text .= html_writer::end_tag('tr');
        }        
        
        $this->content->text .= html_writer::end_tag('table');


        // End teachers list.
        $this->content->text .= html_writer::end_tag('div');

        // Output participants list if the setting linkparticipantspage is enabled.
        if ((get_config('block_people', 'linkparticipantspage')) != 0) {
            $this->content->text .= html_writer::start_tag('div', array('class' => 'participants'));
            $this->content->text .= html_writer::tag('h3', get_string('participants'));

            // Only if user is allow to see participants list.
            if (course_can_view_participants($currentcontext)) {
                $this->content->text .= html_writer::start_tag('a',
                    array('href'  => new moodle_url('/user/index.php', array('contextid' => $currentcontext->id)),
                          'title' => get_string('participants')));
                $this->content->text .= $OUTPUT->pix_icon('i/users',
                        get_string('participants', 'core'), 'moodle');
                $this->content->text .= get_string('participantslist', 'block_people');
                $this->content->text .= html_writer::end_tag('a');
            } else {
                $this->content->text .= html_writer::start_tag('span', array('class' => 'hint'));
                $this->content->text .= get_string('noparticipantslist', 'block_people');
                $this->content->text .= html_writer::end_tag('span');
            }

            $this->content->text .= html_writer::end_tag('div');
        }

        return $this->content;
    }

    /**
     * Return the plugin config settings for external functions.
     *
     * @return stdClass the configs for both the block instance and plugin
     * @since Moodle 3.8
     */
    public function get_config_for_external() {

        // Return all settings for all users since it is safe (no private keys, etc..).
        $instanceconfigs = !empty($this->config) ? $this->config : new stdClass();
        $pluginconfigs = get_config('block_people');

        return (object) [
                'instance' => $instanceconfigs,
                'plugin' => $pluginconfigs,
        ];
    }
}
