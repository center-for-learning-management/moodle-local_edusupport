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

namespace block_edusupport;

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$id = optional_param('id', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$supportlevel = optional_param('supportlevel', '', PARAM_TEXT);
$remove = optional_param('remove', 0, PARAM_BOOL);

// The next param is not used yet. We could select supporters, that are resposible for certain courses only.
$courseid = optional_param('courseid', 1, PARAM_INT);

$context = \context_system::instance();
$PAGE->set_context($context);
require_login();
$PAGE->set_url(new \moodle_url('/blocks/edusupport/choosesupporters.php', array('id' => $id, 'userid' => $userid, 'supportlevel' => $supportlevel, 'remove' => $remove)));

$title = get_string('supporters', 'block_edusupport');
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();

if (!is_siteadmin()) {
    $tourl = new moodle_url('/my', array());
    echo $OUTPUT->render_from_template('block_edusupport/alert', array(
        'content' => get_string('missing_permission', 'block_edusupport'),
        'type' => 'danger',
        'url' => $tourl->__toString(),
    ));
} else {
    if (!empty($userid)) {
        $success = false;
        if (!empty($id)) {
            if (!empty($remove)) {
                $success = $DB->delete_records('block_edusupport_supporters', array('id' => $id));
            } else {
                $success = $DB->update_record('block_edusupport_supporters', array(
                    'id' => $id,
                    'courseid' => $courseid,
                    'userid' => $userid,
                    'supportlevel' => $supportlevel,
                ));
            }
        } else {
            $success = $DB->insert_record('block_edusupport_supporters', array(
                'courseid' => $courseid,
                'userid' => $userid,
                'supportlevel' => $supportlevel,
            ));
        }
        if ($success) {
            if (!empty($remove)) {
                $chk = $DB->get_record('block_edusupport_supporters', array('userid' => $userid));
                if (empty($chk->id)) {
                    // This supporter left the team. We remove all assignments.
                    $DB->delete_records('block_edusupport_assignments', array('userid' => $userid));
                }
            } elseif (empty($supportlevel)) {
                $issues = $DB->get_records('block_edusupport_issues', array('currentsupporter' => 0));
                foreach ($issues AS $issue) {
                    $DB->insert_record('block_edusupport_assignments', array(
                        'issueid' => $issue->id,
                        'discussionid' => $issue->discussionid,
                        'userid' => $userid,
                    ));
                }
            }
        }
        echo $OUTPUT->render_from_template('block_edusupport/alert', array(
            'content' => get_string(($success) ? 'changes_saved_successfully' : 'changes_saved_fail', 'block_edusupport'),
            'type' => ($success) ? 'success' : 'danger',
        ));
    }

    $sql = "SELECT bes.*,u.firstname,u.lastname
                FROM {block_edusupport_supporters} bes, {user} u
                WHERE u.id=bes.userid
                ORDER BY u.lastname ASC, u.firstname ASC, bes.supportlevel ASC";
    $supporters = array_values($DB->get_records_sql($sql, array()));
    echo $OUTPUT->render_from_template('block_edusupport/choosesupporters', array('supporters' => $supporters, 'wwwroot' => $CFG->wwwroot));
}

echo $OUTPUT->footer();
