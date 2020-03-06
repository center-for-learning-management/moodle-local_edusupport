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
 * @package    block_edusupport
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$courseid = required_param('courseid', PARAM_INT);
$forumid = optional_param('forumid', 0, PARAM_INT);
$state = optional_param('state', 0, PARAM_INT);

$context = context_course::instance($courseid);
$PAGE->set_context($context);
require_login($courseid);
$PAGE->set_url(new moodle_url('/blocks/edusupport/chooseforum.php', array('courseid' => $courseid)));

$title = get_string('supportforum:choose', 'block_edusupport');
$PAGE->set_title($title);
$PAGE->set_heading($title);

require_once($CFG->dirroot . '/blocks/edusupport/locallib.php');
print_r(\block_edusupport\lib::get_potentialtargets());
die();

echo $OUTPUT->header();

if (!is_siteadmin()) {
    $tocmurl = new moodle_url('/course/view.php', array('id' => $courseid));
    echo $OUTPUT->render_from_template('block_edusupport/alert', array(
        'content' => get_string('missing_permission', 'block_edusupport'),
        'type' => 'danger',
        'url' => $tocmurl->__toString(),
    ));
} else {
    if (!empty($forumid)) {
        require_once($CFG->dirroot . '/blocks/edusupport/locallib.php');
        switch($state) {
            case 1: \block_edusupport\lib::supportforum_enable($forumid); break;
            case -1: \block_edusupport\lib::supportforum_disable($forumid); break;
        }
    }

    $sql = "SELECT id,name,course
                FROM {forum}
                WHERE course=?
                ORDER BY name ASC";
    $forums = array_values($DB->get_records_sql($sql, array($courseid)));
    foreach ($forums AS &$forum) {
        $state = $DB->get_record('block_edusupport', array('forumid' => $forum->id));
        $forum->state = (!empty($state->id));
    }
    echo $OUTPUT->render_from_template('block_edusupport/chooseforum', array('forums' => $forums));
}

echo $OUTPUT->footer();
