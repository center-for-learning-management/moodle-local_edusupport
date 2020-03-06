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
require_once($CFG->libdir . '/adminlib.php');

required_param('cmid', PARAM_INT);
required_param('forumid', PARAM_INT);
required_param('state', PARAM_INT);

$context = context_module::instance($cmid);
$PAGE->set_context($context);
require_login();
$PAGE->set_url(new moodle_url('blocks/edusupport/toggleforum.php', array('cmid' => $cmid, 'forumid' => $forumid, 'state' => $state)));

$title = get_string(!empty($state) ? 'supportforum:enable': 'supportforum:disable', 'block_edusupport');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$tocmurl = new moodle_url('mod/forum/view.php', array('id' => 7716));

if (!is_siteadmin()) {
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('block_edusupport/alert', array(
        'content' => get_string('missing_permission', 'block_edusupport'),
        'type' => 'danger',
        'url' => $tocmurl->__toString(),
    ));
    echo $OUTPUT->footer();
}
$context = context_system::instance();

require_once($CFG->dirroot . '/blocks/edusupport/locallib.php');

if (!empty($state)) {
    // We want to enable.
    $chk = \block_edusupport\lib::supportforum_enable($forumid);
} else {
    //We want to disable.
    $chk = \block_edusupport\lib::supportforum_disable($forumid);
}
redirect($tocmurl->__toString());
