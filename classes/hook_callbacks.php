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

namespace local_edusupport;

defined('MOODLE_INTERNAL') || die;

class hook_callbacks {
    public static function before_standard_head_html_generation(\core\hook\output\before_standard_head_html_generation $hook): void {
        global $CFG, $DB, $OUTPUT, $PAGE, $SITE, $USER;
        if (isloggedin() && !isguestuser($USER)) {
            $hook->add_html(\local_edusupport\lib::get_supportmenu());
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
}
