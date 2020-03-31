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

/**
* This script removes all users from the support course that did never create a forum post
* and removes the according groups too.
*/

namespace block_edusupport;

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$unlocked = optional_param('unlocked', 0, PARAM_INT);

$context = \context_system::instance();
$PAGE->set_context($context);
require_login();
$PAGE->set_url(new \moodle_url('/blocks/edusupport/cleanup.php', array('unlocked' => $unlocked)));

$PAGE->set_title('Cleaning');
$PAGE->set_heading('Cleaning');

echo $OUTPUT->header();


$targetforum = get_config('block_edusupport', 'targetforum');
$forum = $DB->get_record('forum', array('id' => $targetforum));
if (!is_siteadmin()) {
    $tourl = new \moodle_url('/my', array());
    echo $OUTPUT->render_from_template('block_edusupport/alert', array(
        'content' => get_string('missing_permission', 'block_edusupport'),
        'type' => 'danger',
        'url' => $tourl->__toString(),
    ));
} elseif (empty($targetforum) || empty($forum->id)) {
    $tourl = new \moodle_url('/my', array());
    echo $OUTPUT->render_from_template('block_edusupport/alert', array(
        'content' => get_string('missing_targetforum', 'block_edusupport'),
        'type' => 'danger',
        'url' => $tourl->__toString(),
    ));
} else {
    $sql = "SELECT fp.userid
                FROM {forum_posts} fp, {forum_discussions} fd
                WHERE fp.discussion=fd.id
                AND fd.forum=?";
    $params = array($targetforum);

    // Check if there is an archive.
    $entry = $DB->get_record('block_edusupport', array('courseid' => $forum->course));
    if (!empty($entry->archiveid)) {
        $sql .= "     OR forum=?";
        $params[] = $entry->archiveid;
    }
    $userids_active = array();
    $userids_inactive = array();
    $posts = $DB->get_records_sql($sql, $params);
    foreach ($posts AS $post) {
        $userids_active[] = $post->userid;
    }
    $enrolled = \get_enrolled_users(\context_course::instance($forum->course));
    foreach ($enrolled AS $u) {
        if (!in_array($u->id, $userids_active)) {
            $userids_inactive[] = $u->id;
        }
    }

    require_once($CFG->dirroot . '/blocks/edusupport/block_edusupport.php');
    $reply = array();
    echo "UNENROL " . count($userids_inactive) . " USERS<br />";
    if (!empty($unlocked)) \block_edusupport::course_manual_enrolments(array($forum->course), $userids_inactive, -1, $reply);

    // Get all groups that have members from our support course.
    $sql = "SELECT DISTINCT(gm.groupid)
                FROM {groups} g, {groups_members} gm
                WHERE g.id=gm.groupid
                    AND g.courseid=?";

    $groups_active = array_keys($DB->get_records_sql($sql, array($forum->course)));

    // Get all other groups in our support course, that logically have no members.
    $sql = "SELECT id
                FROM {groups} g
                WHERE id NOT IN (" . implode(',', $groups_active) . ")
                    AND g.courseid=?";
    $groups_inactive = $DB->get_records_sql($sql, array($forum->course));

    foreach ($groups_inactive AS $group) {
        echo "DELETE GROUP #" . $group->id . "<br />";
        if (!empty($unlocked)) $DB->delete_records('groups', array('id' => $group->id));
    }



}

echo $OUTPUT->footer();
