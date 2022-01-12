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
require_once($CFG->libdir . '/adminlib.php');

$courseid = required_param('courseid', PARAM_INT);
$forumid = optional_param('forumid', 0, PARAM_INT);
$state = optional_param('state', 0, PARAM_INT);
$central = optional_param('central', 0, PARAM_INT);

$context = context_course::instance($courseid);
$PAGE->set_context($context);
require_login($courseid);
$PAGE->set_url(new moodle_url('/local/edusupport/chooseforum.php', array('courseid' => $courseid)));

$title = get_string('supportforum:choose', 'local_edusupport');
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();

if (!is_siteadmin()) {
    $tocmurl = new moodle_url('/course/view.php', array('id' => $courseid));
    echo $OUTPUT->render_from_template('local_edusupport/alert', array(
        'content' => get_string('missing_permission', 'local_edusupport'),
        'type' => 'danger',
        'url' => $tocmurl->__toString(),
    ));
} else {
    if (!empty($forumid)) {
        $dedicatedsupporter = optional_param('dedicatedsupporter', 0, PARAM_INT);
        if (!empty($dedicatedsupporter)) {
            if (\local_edusupport\lib::supportforum_setdedicatedsupporter($forumid, $dedicatedsupporter)) {
                echo $OUTPUT->render_from_template('local_edusupport/alert', array(
                    'content' => get_string('dedicatedsupporter:successfully_set', 'local_edusupport'),
                    'type' => 'success'
                ));
            } else {
                echo $OUTPUT->render_from_template('local_edusupport/alert', array(
                    'content' => get_string('dedicatedsupporter:not_successfully_set', 'local_edusupport'),
                    'type' => 'danger'
                ));
            }

        } else {
            switch($state) {
                case 1: \local_edusupport\lib::supportforum_enable($forumid); break;
                case -1: \local_edusupport\lib::supportforum_disable($forumid); break;
            }
            switch($central) {
                case 1: \local_edusupport\lib::supportforum_enablecentral($forumid); break;
                case -1: \local_edusupport\lib::supportforum_disablecentral($forumid); break;
            }
        }
    }

    $sql = "SELECT userid,supportlevel
                FROM {local_edusupport_supporters}
                WHERE courseid=1
                    OR courseid=?
                ORDER BY supportlevel ASC";
    $supporters = array_values($DB->get_records_sql($sql, array($courseid)));
    foreach ($supporters AS &$supporter) {
        $u = $DB->get_record('user', array('id' => $supporter->userid));
        $supporter->userfullname = fullname($u);
        $supporter->firstname = $u->firstname;
        $supporter->lastname = $u->lastname;
        $supporter->email = $u->email;
        if (empty($supporter->supportlevel)) {
            $supporter->supportlevel = get_string('label:2ndlevel', 'local_edusupport');
        }
    }

    $sql = "SELECT id,name,course
                FROM {forum}
                WHERE course=?
                    AND type='general'
                ORDER BY name ASC";
    $forums = array_values($DB->get_records_sql($sql, array($courseid)));

    $centralforum = get_config('local_edusupport', 'centralforum');

    foreach ($forums AS &$forum) {
        $state = $DB->get_record('local_edusupport', array('forumid' => $forum->id));
        $forum->state = (!empty($state->id));
        $forum->statecentral = (!empty($centralforum) && $centralforum == $forum->id);
        $forum->dedicatedsupporter = !empty($state->dedicatedsupporter) ? $state->dedicatedsupporter : 0;
        $forum->supporters = json_decode(json_encode($supporters));
        if (!empty($forum->dedicatedsupporter)) {
            foreach ($forum->supporters AS &$supporter) {
                $supporter->selected = $forum->dedicatedsupporter == $supporter->userid;
            }
        }
    }

    echo $OUTPUT->render_from_template('local_edusupport/chooseforum', array('forums' => $forums, 'wwwroot' => $CFG->wwwroot));
}

echo $OUTPUT->footer();
