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
function block_edusupport_extend_settings_navigation(settings_navigation $settings,
        navigation_node $modnode) {

    // If we are not site admin return.
    if (!is_siteadmin()) return;

    global $DB, $PAGE;
    // 1. Check if this is currently a forum a supportforum.
    $context = $PAGE->cm->context;
    $cmid = $PAGE->cm->id;
    $cm = $PAGE->cm;
    $course = $PAGE->course;
    print_r(array($cm, $course); die();

    $forumid = $cm->instance;
    $courseid = $course->id;

    // We want to add these new nodes after the Edit settings node, and before the
    // Locally assigned roles node. Of course, both of those are controlled by capabilities.
    $keys = $modnode->get_children_key_list();
    $beforekey = null;
    $i = array_search('modedit', $keys);
    if (($i === false) && array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }

    $chk = $DB->get_record('block_edusupport', array('forumid' => $forumid));
    if (!empty($chk->id)) {
        if ($chk->courseid != $courseid) {
            $DB->set_field('block_edusupport', 'courseid', $courseid, array('id' => $chk->id));
        }
        // Add link to disable this supportforum.
        $url = '/block/edusupport/toggleforum.php';
        $node = navigation_node::create(get_string('supportforum:disable'),
            new moodle_url($url, array('cmid' => $cm->id, 'forumid' => $forumid, 'state' => '0')),
            navigation_node::TYPE_SETTING, null, 'advancedsettings',
            new pix_icon('t/eye', ''));
        $modnode->add_node($node, $beforekey);
    } else {
        // Add link to enable this forum as supportforum.
        // Add link to disable this supportforum.
        $url = '/block/edusupport/toggleforum.php';
        $node = navigation_node::create(get_string('supportforum:enable'),
            new moodle_url($url, array('cmid' => $cm->id, 'forumid' => $forumid, 'state' => '1')),
            navigation_node::TYPE_SETTING, null, 'advancedsettings',
            new pix_icon('t/eye', ''));
        $modnode->add_node($node, $beforekey);
    }
}
