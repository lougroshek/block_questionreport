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
  * Block questionreport Charts File.
  *
  * @package    block_questionreport
  * @copyright  (C) 2018 Remote Learner.net Inc http://www.remote-learner.net
  */
require_once(dirname(__FILE__).'/../../config.php');
$plugin = 'block_questionreport';

$PAGE->set_pagelayout('standard');
$PAGE->set_url('/blocks/questionreport/charts.php');
$PAGE->set_context(context_system::instance());
$header = get_string('chartsheader', $plugin);
$PAGE->set_title($header);
$PAGE->set_heading($header);
$PAGE->set_cacheable(true);
$PAGE->navbar->add($header, new moodle_url('/blocks/questionreport/charts.php'));

echo $OUTPUT->header();

echo $OUTPUT->footer();
