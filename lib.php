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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

defined('MOODLE_INTERNAL') || die;

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $modnode The node to add module settings to
 *
 * $settings is unused, but API requires it. Suppress PHPMD warning.
 *
 */
function block_edusupport_before_standard_html_head(){
    global $CFG, $DB;
    if (strpos($_SERVER["SCRIPT_FILENAME"], '/mod/forum/discuss.php') > 0) {
        require_once($CFG->dirroot . '/blocks/edusupport/classes/lib.php');
        $d = optional_param('d', 0, PARAM_INT);
        $discussion = $DB->get_record('forum_discussions', array('id' => $d));
        $coursecontext = \context_course::instance($discussion->course);
        if (has_capability('moodle/course:update', $coursecontext)
                && \block_edusupport\lib::is_supportforum($discussion->forum)) {
            $PAGE->requires->js_call_amd('block_edusupport/main', 'injectForwardButton', array($d));
        }
    }
}
function block_edusupport_extend_navigation_course($parentnode, $course, $context) {
    // If we allow support users on course level, we can remove the next line.
    if (!is_siteadmin()) return;
    //$coursecontext = \context_course::instance($course->id);
    //if (!has_capability('moodle/course:update', $coursecontext)) return;

    global $DB, $PAGE;
    // 1. Check if this is currently a forum a supportforum.
    $context = $PAGE->cm->context;
    $cmid = $PAGE->cm->id;
    $cm = $PAGE->cm;
    $course = $PAGE->course;
    $forumid = $cm->instance;
    $courseid = $course->id;

    // We want to add these new nodes after the Edit settings node, and before the
    // Locally assigned roles node. Of course, both of those are controlled by capabilities.
    $keys = $parentnode->get_children_key_list();

    $beforekey = null;
    $i = array_search('modedit', $keys);
    if (($i === false) && array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }

    if (is_siteadmin()) {
        $url = '/blocks/edusupport/chooseforum.php';
        $node = navigation_node::create(get_string('supportforum:choose', 'block_edusupport'),
            new moodle_url($url, array('courseid' => $courseid)),
            navigation_node::TYPE_SETTING, null, 'advancedsettings',
            new pix_icon('t/eye', ''));
        $parentnode->add_node($node, $beforekey);
    }
    /*
    // This is prepared for later use, if we allow support users on course level.
    $url = '/blocks/edusupport/choosesupporters.php';
    $node = navigation_node::create(get_string('supporters:choose', 'block_edusupport'),
        new moodle_url($url, array('courseid' => $courseid)),
        navigation_node::TYPE_SETTING, null, 'advancedsettings',
        new pix_icon('t/eye', ''));
    $parentnode->add_node($node, $beforekey);
    */
}

/**
 * Serves the forum attachments. Implements needed access control ;-)
 *
 * @package  block_edusupport --> we fake downloads for mod_forum.
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function block_edusupport_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG, $DB, $USER;
    require_once($CFG->dirroot . '/blocks/edusupport/classes/lib.php');
    require_once($CFG->dirroot . '/mod/forum/lib.php');

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    // instead of requiring course login we check if the current user is support user of this discussion!
    //require_course_login($course, true, $cm);
    if (!\block_edusupport\lib::is_supportteam()) {
        return false;
    }

    $postid = (int)array_shift($args);
    if (!$post = $DB->get_record('forum_posts', array('id'=>$postid))) {
        return false;
    }

    $assignment = $DB->get_record('block_edusupport_assignments', array('discussionid' => $post->discussion, 'userid' => $USER->id));
    if (empty($assignment->id)) {
        return false;
    }

    $areas = \forum_get_file_areas($course, $cm, $context);

    // filearea must contain a real area
    if (!isset($areas[$filearea])) {
        return false;
    }

    if (!$discussion = $DB->get_record('forum_discussions', array('id'=>$post->discussion))) {
        return false;
    }

    if (!$forum = $DB->get_record('forum', array('id'=>$discussion->forum))) {
        return false;
    }

    $fs = \get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_forum/$filearea/$postid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // We skip this check, we already checked, that we belong to the supportteam and have access.
    // Make sure groups allow this user to see this file
    /*
    if ($discussion->groupid > 0) {
        $groupmode = \groups_get_activity_groupmode($cm, $course);
        if ($groupmode == SEPARATEGROUPS) {
            if (!\groups_is_member($discussion->groupid) and !has_capability('moodle/site:accessallgroups', $context)) {
                return false;
            }
        }
    }
    */

    // We skip this check, we already checked, that we belong to the supportteam and have access.
    // Make sure we're allowed to see it...
    /*
    if (!forum_user_can_see_post($forum, $discussion, $post, NULL, $cm)) {
        return false;
    }
    */

    // finally send the file
    send_stored_file($file, 0, 0, true, $options); // download MUST be forced - security!
}
