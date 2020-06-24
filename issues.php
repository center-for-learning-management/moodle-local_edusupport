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
//require_once($CFG->dirroot . '/local/edusupport/classes/lib.php');

$context = \context_system::instance();
$PAGE->set_context($context);
require_login();
$PAGE->set_url(new moodle_url('/local/edusupport/issues.php', array()));

$title = get_string('issues', 'local_edusupport');
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();

if (false && !\local_edusupport\lib::is_supporteam()) {
    $tocmurl = new moodle_url('/course/view.php', array('id' => $courseid));
    echo $OUTPUT->render_from_template('local_edusupport/alert', array(
        'content' => get_string('missing_permission', 'local_edusupport'),
        'type' => 'danger',
        'url' => $tocmurl->__toString(),
    ));
} else {
    $assign = optional_param('assign', 0, PARAM_INT); // discussion id we want to assign to
    $unassign = optional_param('unassign', 0, PARAM_INT); // discussion id we want to unassign from
    $take = optional_param('take', 0, PARAM_INT); // discussion id we want to unassign from

    $sql = "SELECT id,discussionid FROM {local_edusupport_issues}";
    $issues = $DB->get_records('local_edusupport_issues', array(), 'id,discussionid');

    $params = array(
        'current' => array(), // issues the user is responsible for
        'assigned' => array(), // issues the user receives notifications for
        'other' => array(), // all other issues
        'wwwroot' => $CFG->wwwroot,
    );

    foreach ($issues AS $issue) {
        // Collect certain data about this issue.
        $discussion = $DB->get_record('forum_discussions', array('id' => $issue->discussionid));
        $issue->name = $discussion->name;
        $issue->userid = $discussion->userid;
        $postinguser = $DB->get_record('user', array('id' => $discussion->userid));
        $issue->userfullname = \fullname($postinguser);
        $sql = "SELECT id,modified FROM {forum_posts} WHERE discussion=? ORDER BY modified DESC LIMIT 0,1";
        $lastpost = $DB->get_record_sql($sql, array($issue->discussionid));
        $issue->lastmodified = $lastpost->modified;
        $assigned = $DB->get_record('local_edusupport_subscr', array('discussionid' => $issue->discussionid, 'userid' => $USER->id));

        // Check for any actions.
        if (!empty($assign) && $assign == $issue->discussionid && empty($assigned->id)) {
            $assigned = \local_edusupport\lib::subscription_add($issue->discussionid);
        }
        if (!empty($unassign) && $unassign == $issue->discussionid) {
            \local_edusupport\lib::subscription_remove($issue->discussionid);
            unset($assigned);
        }
        if (!empty($take) && $take == $issue->discussionid) {
            \local_edusupport\lib::set_current_supporter($issue->discussionid, $USER->id);
            $assigned = \local_edusupport\lib::subscription_add($issue->discussionid);
            $issue->currentsupporter = $USER->id;
        }

        // Now get the current supporter
        if (!empty($issue->currentsupporter)) {
            $supportuser = $DB->get_record('user', array('id' => $issue->currentsupporter));
            $issue->currentsupportername = \fullname($supportuser);
            $issue->currentsupporterid = $issue->currentsupporter;
        } else {
            $issue->currentsupportername = get_string('label:2ndlevel', 'local_edusupport');
        }

        // Now separate between current, assigned and other issues.
        if ($issue->currentsupporter == $USER->id) {
            $params['current'][] = $issue;
        } elseif (!empty($assigned->id)) {
            $params['assigned'][] = $issue;
        } else {
            $params['other'][] = $issue;
        }
    }
    echo $OUTPUT->render_from_template('local_edusupport/issues', $params);
}

echo $OUTPUT->footer();
