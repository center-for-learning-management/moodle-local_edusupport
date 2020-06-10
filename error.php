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
* @package    local_edusupport
* @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
* @author     Robert Schrenk
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

require_once('../../config.php');

$error = required_param('error', PARAM_ALPHANUM);

$urlparams = array(
    'error' => $error
);
$errparams = array(
    'type' => 'danger'
);

switch ($error) {
    case 'coursecategorydeletion':
        $categoryid = required_param('categoryid', PARAM_INT);
        $urlparams['categoryid'] = $categoryid;
        $errparams['content'] = get_string('coursecategorydeletion', 'local_edusupport');
        $errparams['url'] = new \moodle_url('/course/management.php', array('categoryid' => 15));
    break;

}

$context = \context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/edusupport/error.php', $urlparams));

require_login();

$PAGE->set_title(get_string('error'));
$PAGE->set_heading(get_string('error'));


echo $OUTPUT->header();

echo $OUTPUT->render_from_template('local_edusupport/alert', $errparams);

echo $OUTPUT->footer();
