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

// This file forwards a forum discussion to the 2nd level support.

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
//require_once($CFG->dirroot . '/blocks/edusupport/classes/lib.php');

$d = required_param('d', PARAM_INT);
$revoke = optional_param('revoke', 0, PARAM_BOOL);
$discussion = $DB->get_record('forum_discussions', array('id' => $d), '*', MUST_EXIST);
$courseid = $discussion->course;

$context = context_course::instance($discussion->course);
$PAGE->set_context($context);
require_login($discussion->course);
$PAGE->set_url(new moodle_url('/blocks/edusupport/forward_2nd_level.php', array('d' => $d, 'revoke' => $revoke)));

$title = get_string(empty($revoke) ? 'issue_assign_nextlevel': 'issue_revoke', 'block_edusupport');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$todiscussion = new moodle_url('/mod/forum/discuss.php', array('d' => $d));
if (!has_capability('moodle/course:update', $context)) {
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('block_edusupport/alert', array(
        'content' => get_string('missing_permission', 'block_edusupport'),
        'type' => 'danger',
        'url' => $todiscussion->__toString(),
    ));
} else {
    if (empty($revoke)) {
        if(\block_edusupport\lib::set_2nd_level($d)) {
            redirect($todiscussion->__toString());
            echo $OUTPUT->header();
            echo $OUTPUT->render_from_template('block_edusupport/alert', array(
                'content' => get_string('success'),
                'type' => 'success',
                'url' => $todiscussion->__toString(),
            ));
        } else {
            echo $OUTPUT->header();
            echo $OUTPUT->render_from_template('block_edusupport/alert', array(
                'content' => get_string('issue_assign_nextlevel:error', 'block_edusupport'),
                'type' => 'danger',
                'url' => $todiscussion->__toString(),
            ));
        }
    } else {
        if(\block_edusupport\lib::revoke_issue($d)) {
            redirect($todiscussion->__toString());
            echo $OUTPUT->header();
            echo $OUTPUT->render_from_template('block_edusupport/alert', array(
                'content' => get_string('success'),
                'type' => 'success',
                'url' => $todiscussion->__toString(),
            ));
        } else {
            echo $OUTPUT->header();
            echo $OUTPUT->render_from_template('block_edusupport/alert', array(
                'content' => get_string('issue_revoke:error', 'block_edusupport'),
                'type' => 'danger',
                'url' => $todiscussion->__toString(),
            ));
        }
    }
}

echo $OUTPUT->footer();
