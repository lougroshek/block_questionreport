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
 * Block "questionreport"
 *
 * @package    block_questionreport
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
class block_questionreport extends block_base
{
    /**
     * init function
     * @return void
     */
    public function init()
    {
        $this->title = get_string('pluginname', 'block_questionreport');
    }

    /**
     * applicable_formats function
     * @return array
     */
    public function applicable_formats()
    {
        return array('course-view' => true, 'site' => true);
    }

    /**
     * has_config function
     * @return bool
     */
    public function has_config()
    {
        return true;
    }

    /**
     * instance_allow_multiple function
     * @return bool
     */
    public function instance_allow_multiple()
    {
        return false;
    }

    /**
     * instance_can_be_hidden function
     * @return bool
     */
    public function instance_can_be_hidden()
    {
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
    public function get_content()
    {
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
        $this->content->text = block_questionreport_get_evaluations();

        return $this->content;
    }

    /**
     * Return the plugin config settings for external functions.
     *
     * @return stdClass the configs for both the block instance and plugin
     * @since Moodle 3.8
     */
    public function get_config_for_external()
    {

        // Return all settings for all users since it is safe (no private keys, etc..).
        $instanceconfigs = !empty($this->config) ? $this->config : new stdClass();
        $pluginconfigs = get_config('block_people');

        return (object) [
                'instance' => $instanceconfigs,
                'plugin' => $pluginconfigs,
        ];
    }
}
