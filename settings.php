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
 * Block "questionreport" - Settings
 *
 * @package    block_questionreport
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    global $CFG;
    require_once($CFG->dirroot.'/blocks/questionreport/locallib.php');

    // Settings title to group role related settings together with a common heading. We don't want a description here.
    $name = 'block_questionreport/rolesheading';
    $title = get_string('setting_rolesheading', 'block_questionreport', null, true);
    $setting = new admin_setting_heading($name, $title, null);
    $settings->add($setting);

    // Setting to configure the roles to be shown within the block.
    $name = 'block_questionreport/roles';
    $title = get_string('setting_roles', 'block_questionreport', null, true);
    $description = get_string('setting_roles_desc', 'block_questionreport', null, true);
    $default = array('editingteacher');
    $settings->add(new admin_setting_pickroles($name, $title, $description, $default));

    // Setting to configure the roles to view the admin reports.
    $name = 'block_questionreport/adminroles';
    $title = get_string('setting_admin_roles', 'block_questionreport', null, true);
    $description = get_string('setting_admin_roles_desc', 'block_questionreport', null, true);
    $default = array('editingteacher');
    $settings->add(new admin_setting_pickroles($name, $title, $description, $default));
 
    // Setting to show multiple roles within the block.
    $name = 'block_questionreport/multipleroles';
    $title = get_string('setting_multipleroles', 'block_questionreport', null, true);
    $description = get_string('setting_multipleroles_desc', 'block_questionreport', null, true);
    $settings->add(new admin_setting_configcheckbox($name, $title, $description, 0));

    // Setting to show link to the participants page within the block.
    $name = 'block_questionreport/linkparticipantspage';
    $title = get_string('setting_linkparticipantspage', 'block_questionreport', null, true);
    $description = get_string('setting_linkparticipantspage_desc', 'block_questionreport', null, true);
    $settings->add(new admin_setting_configcheckbox($name, $title, $description, 1));
    
    $settings->add(new admin_setting_configtext(
         'block_questionreport/tag_value',get_string('tagvalue', 'block_questionreport'),
          get_string('tagvalue_desc', 'block_questionreport'),'teachinglab', PARAM_RAW ));
    
    $settings->add(new admin_setting_configtext(
         'block_questionreport/tag_value_diagnostic',get_string('tagvalue', 'block_questionreport'),
          get_string('tagvalue_desc_diagnostic', 'block_questionreport'),'teachinglab', PARAM_RAW ));
 
    $customcoursefields = block_questionreport_get_partners();
    $setting = new admin_setting_configselect('partnerfield',
         get_string('partnerfield', 'block_questionreport'), get_string('partnerfieldhelp', 'block_questionreport'), 0, $customcoursefields);
    $setting->plugin = 'block_questionreport';
    $settings->add($setting);
    
    $setting = new admin_setting_configselect('portfoliofield',
         get_string('portfoliofield', 'block_questionreport'), get_string('portfoliofieldhelp', 'block_questionreport'), 0, $customcoursefields);
    $setting->plugin = 'block_questionreport';
    $settings->add($setting); 
    
    // logo Image file
/*
    $name = get_string('logofile', 'block_questionreport');
    $title = get_string('logofile','block_questionreport');
    $description = get_string('logo_desc', 'block_questionreport');
    $setting = new admin_setting_configstoredfile('block_questionreport/logofile', $title, $description, 'preset', 0,
        array('maxfiles' => 1, 'accepted_types' => array('.png, .img, jpg')));
    $settings->add($setting);
    
    $settings->add(new admin_setting_configtext(
         'block_questionreport/width_value',get_string('width', 'block_questionreport'),
          get_string('width_desc', 'block_questionreport'),'200', PARAM_RAW ));
          
    $settings->add(new admin_setting_configtext(
         'block_questionreport/height_value',get_string('height', 'block_questionreport'),
          get_string('height_desc', 'block_questionreport'),'200', PARAM_RAW ));
      
*/
}

