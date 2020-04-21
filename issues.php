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
//require_once($CFG->dirroot . '/blocks/edusupport/classes/lib.php');

$context = \context_system::instance();
$PAGE->set_context($context);
require_login();
$PAGE->set_url(new moodle_url('/blocks/edusupport/issues.php', array()));

$title = get_string('issues', 'block_edusupport');
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();

if (false && !\block_edusupport\lib::is_supporteam()) {
    $tocmurl = new moodle_url('/course/view.php', array('id' => $courseid));
    echo $OUTPUT->render_from_template('block_edusupport/alert', array(
        'content' => get_string('missing_permission', 'block_edusupport'),
        'type' => 'danger',
        'url' => $tocmurl->__toString(),
    ));
} else {
    $sql = "SELECT fd.id,fd.name,fd.userid
                FROM {block_edusupport_assignments} bea,
                    {forum_discussions} fd
                WHERE bea.discussionid=fd.id
                    AND bea.userid=?
                ORDER BY fd.timemodified DESC";
    $assignments = array_values($DB->get_records_sql($sql, array($USER->id)));
    $issues = array();
    foreach ($assignments AS $assignment) {
        $issue = \block_edusupport\lib::get_issue($assignment->id);
        $issue->name = $assignment->name;
        $firstpost = $DB->get_record('forum_posts', array('discussion' => $issue->discussionid, 'parent' => 0));
        $lastpost = $DB->get_record('forum_posts', array('discussion' => $issue->discussionid), '*', 'modified DESC');
        $issue->lastmodified = $lastpost->modified;
        $user = $DB->get_record('user', array('id' => $firstpost->userid));

        $issue->userfullname = \fullname($user);
        if (!empty($issue->currentsupporter)) {
            $supporter = $DB->get_record('block_edusupport_supporters', array('id' => $issue->currentsupporter));
            $supportuser = $DB->get_record('user', array('id' => $supporter->userid));
            $issue->currentsupportername = \fullname($supportuser);
            $issue->currentsupporterid = $supportuser->id;
        } else {
            $issue->currentsupportername = "2nd Level";
        }
        $issues[] = $issue;
    }
    echo $OUTPUT->render_from_template('block_edusupport/issues', array('issues' => $issues, 'wwwroot' => $CFG->wwwroot));
}

echo $OUTPUT->footer();
