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

}
function block_edusupport_extend_navigation_course($parentnode, $course, $context) {
    if (!is_siteadmin()) return;

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

    $url = '/blocks/edusupport/chooseforum.php';
    $node = navigation_node::create(get_string('supportforum:choose', 'block_edusupport'),
        new moodle_url($url, array('courseid' => $courseid)),
        navigation_node::TYPE_SETTING, null, 'advancedsettings',
        new pix_icon('t/eye', ''));
    $parentnode->add_node($node, $beforekey);
}
