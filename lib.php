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
function local_edusupport_before_standard_html_head(){
    global $CFG, $DB, $OUTPUT, $PAGE, $SITE, $USER;
    if (isloggedin() && !isguestuser($USER)) {
        echo \local_edusupport\lib::get_supportmenu();
    }

    if (strpos($_SERVER["SCRIPT_FILENAME"], '/mod/forum/discuss.php') > 0) {
        $d = optional_param('d', 0, PARAM_INT);
        $discussion = $DB->get_record('forum_discussions', array('id' => $d));
        $coursecontext = \context_course::instance($discussion->course);
        if (has_capability('local/edusupport:canforward2ndlevel', $coursecontext)
                && \local_edusupport\lib::is_supportforum($discussion->forum)) {
            $sql = "SELECT id
                        FROM {local_edusupport_subscr}
                        WHERE discussionid=? LIMIT 1 OFFSET 0";
            $chk = $DB->get_record_sql($sql, array($discussion->id));

            $PAGE->requires->js_call_amd('local_edusupport/main', 'injectForwardButton', array($d, !empty($chk->id), $SITE->fullname));
        }
        if (\local_edusupport\lib::is_supportforum($discussion->forum)) {
            $PAGE->requires->js_call_amd('local_edusupport/main', 'injectTest');
        }
    }

    if (strpos($_SERVER["SCRIPT_FILENAME"], '/course/management.php') > 0) {
        // The user could potentially move a supportforum-course,
        // or delete a course category, that contains a supportforum-course.
        // In that case we will move the supportforum-course to a safe location.
        $categoryid = optional_param('categoryid', 0, PARAM_INT);
        $action = optional_param('action', '', PARAM_ALPHANUM);
        if ($action == 'deletecategory') {
            // Check if there are any supportforums below this context.
            $coursecatcontext = \context_coursecat::instance($categoryid);
            $sql = "SELECT *
                        FROM {context}
                        WHERE contextlevel=?
                            AND (
                                path LIKE ?
                                OR path LIKE ?
                            )";
            $subcategories = $DB->get_records_sql($sql, array(CONTEXT_COURSECAT, $coursecatcontext->path, $coursecatcontext->path . '/%'));
            foreach ($subcategories AS $subcategory) {
                $chkforforum = $DB->get_record('local_edusupport', array('categoryid' => $subcategory->instanceid));
                if (!empty($chkforforum->id)) {
                    redirect(new \moodle_url('/local/edusupport/error.php', array('error' => 'coursecategorydeletion', 'categoryid' => $categoryid)));
                }
            }
        }

        // Check if the coursecategory exists and is visible.
        $coursecat = \core_course_category::get($categoryid, MUST_EXIST, true);
        if (empty($coursecat->__get('visible'))) {
            $coursecat->update(array('visible' => 1));
        }

        // Check for any supportforum-courses that are should be contained by this coursecat.
        $supportforums = $DB->get_records('local_edusupport', array('categoryid' => $categoryid));
        foreach ($supportforums AS $supportforum) {
            // Check if the course is in place and the category
            $course = $DB->get_record('course', array('id' => $supportforum->id));
            if (!empty($course->id) && $course->category != $categoryid) {
                // Update our database
                $DB->set_field('local_edusupport', 'categoryid', $categoryid, array('courseid' => $course->id));
            }
        }
    }
}


/**
 * Extend Moodle Navigation.
 */
function local_edusupport_extend_navigation($navigation) {
    if (\local_edusupport\lib::is_supportteam()) {
        $nodehome = $navigation->get('home');
        if (empty($nodehome)){
            $nodehome = $navigation;
        }
        $label = get_string('issues', 'local_edusupport');
        $link = new moodle_url('/local/edusupport/issues.php', array());
        $icon = new pix_icon('docs', '', '');
        $nodecreatecourse = $nodehome->add($label, $link, navigation_node::NODETYPE_LEAF, $label, 'edusupportissues', $icon);
        $nodecreatecourse->showinflatnavigation = true;
    }
}

function local_edusupport_extend_navigation_course($parentnode, $course, $context) {
    // If we allow support users on course level, we can remove the next line.
    if (!is_siteadmin()) return;
    //$coursecontext = \context_course::instance($course->id);
    //if (!has_capability('local/edusupport:canforward2ndlevel', $coursecontext)) return;

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
        $url = '/local/edusupport/chooseforum.php';
        $node = navigation_node::create(get_string('supportforum:choose', 'local_edusupport'),
            new moodle_url($url, array('courseid' => $course->id)),
            navigation_node::TYPE_SETTING, null, 'advancedsettings',
            new pix_icon('i/marker', 'eduSupport'));
        $parentnode->add_node($node, $beforekey);
    }
    /*
    // This is prepared for later use, if we allow support users on course level.
    $url = '/local/edusupport/choosesupporters.php';
    $node = navigation_node::create(get_string('supporters:choose', 'local_edusupport'),
        new moodle_url($url, array('courseid' => $course->id)),
        navigation_node::TYPE_SETTING, null, 'advancedsettings',
        new pix_icon('t/eye', ''));
    $parentnode->add_node($node, $beforekey);
    */
}

/**
 * Serves the forum attachments. Implements needed access control ;-)
 *
 * @package  local_edusupport --> we fake downloads for mod_forum.
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
function local_edusupport_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG, $DB, $USER;
    require_once($CFG->dirroot . '/local/edusupport/classes/lib.php');
    require_once($CFG->dirroot . '/mod/forum/lib.php');

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    // instead of requiring course login we check if the current user is support user of this discussion!
    //require_course_login($course, true, $cm);
    if (!\local_edusupport\lib::is_supportteam($USER->id, $course->id)) {
        return false;
    }

    $postid = (int)array_shift($args);
    if (!$post = $DB->get_record('forum_posts', array('id'=>$postid))) {
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

    if (!\local_edusupport\lib::is_supportforum($discussion->forum)) {
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

/**
 * If a course category was deleted we remove all contained support forums.
 * @param category the course category.
 */
function local_edusupport_pre_course_category_delete($category) {
    global $DB;
    $courses = $DB->get_records('course', array('category' =>  $category->id));
    foreach ($courses AS $course) {
        local_edusupport_pre_course_delete($course);
    }
}

/**
 * If a course was deleted we remove all contained support forums.
 * @param course the course.
 */
function local_edusupport_pre_course_delete($course) {
    global $DB;
    $supportforums = $DB->get_records('local_edusupport', array('courseid' => $course->id));
    foreach ($supportforums AS $supportforum) {
        \local_edusupport\lib::supportforum_disable($supportforum->id);
    }
}
/**
 * If a forum was deleted we remove it as support forum.
 * @param cm the course module.
 */
function local_edusupport_pre_course_module_delete($cm) {
    global $DB;
    $forumtype = $DB->get_record('modules', array('name' => 'forum'));
    if (!empty($forumtype->id) && !empty($cm->module) && $cm->module == $forumtype->id) {
        \local_edusupport\lib::supportforum_disable($cm->instance);
    }
}
