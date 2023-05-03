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
 * @copyright  2019 Digital Education Society (http://www.dibig.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$id = required_param('id', PARAM_INT); // This is the courseid
$course = get_course($id);
$context = context_course::instance($id);
// Must pass login
$PAGE->set_url('/local/edusupport/courseconfig.php?id=' . $id);
require_login($course->id);
$PAGE->set_context($context);
$PAGE->set_title(get_string('courseconfig', 'local_edusupport'));
$PAGE->set_heading(get_string('courseconfig', 'local_edusupport'));
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();

$cms = $DB->get_records_sql('SELECT cm.id,cm.instance,cm.course FROM {course_modules} cm, {modules} m WHERE cm.course=? AND cm.module=m.id AND cm.deletioninprogress=0 AND m.name="forum"', array($COURSE->id));
$forums = array();
$targetforum = get_config('local_edusupport', 'targetforum');
foreach($cms AS &$cm) {
    $forum = $DB->get_record('forum', array('id' => $cm->instance));
    if (empty($forum->type) || $forum->type != 'general') continue;
    if ($forum->id == $targetforum) {
        $forum->selectedforglobal = 1;
    } else {
        $chk = $DB->get_record('local_edusupport', array('courseid' => $COURSE->id));
        if (!empty($chk->forumid) && $chk->forumid == $forum->id) {
            $forum->selectedforcourse = 1;
        }
        if (!empty($chk->archiveid) && $chk->archiveid == $forum->id) {
            $forum->selectedforarchive = 1;
        }
    }
    $forums[] = $forum;
}

if (local_edusupport::can_config_course($course->id)){
    // capability moodle/course:viewhiddenactivities applies to editing and non editing teachers, but not to students.
    $enrolled = get_enrolled_users($context, 'moodle/course:viewhiddenactivities');
    $potentialsupporters = array();
    foreach($enrolled AS &$potentialsupporter) {
        $potentialsupporter->supportlevel = local_edusupport::get_supporter_level($course->id, $potentialsupporter->id);
        $potentialsupporter->courseid = $COURSE->id;
        $potentialsupporters[] = $potentialsupporter;
    }

    echo $OUTPUT->render_from_template(
        'local_edusupport/courseconfig',
        (object) array(
            'canconfigglobal' => local_edusupport::can_config_global(),
            'forums' => $forums,
            'supporters' => $potentialsupporters,
        )
    );
}


echo $OUTPUT->footer();
