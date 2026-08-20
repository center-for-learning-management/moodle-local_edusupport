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

require_once('../../config.php');
require_once($CFG->dirroot . '/local/edusupport/classes/create_issue_form.php');

// The page the user came from, it is shown as a link to the supporters.
$returnurl = \local_edusupport\lib::clean_local_url(optional_param('url', '', PARAM_RAW));
$sent = optional_param('sent', 0, PARAM_INT);

$PAGE->set_context(\context_system::instance());
// Guests cannot open a ticket, so we send them to the login page instead of logging them in.
require_login(null, false);

if (isguestuser()) {
    throw new \moodle_exception('noguest');
}

$PAGE->set_url(new moodle_url('/local/edusupport/create_issue.php', ['url' => $returnurl]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('create_issue', 'local_edusupport'));
$PAGE->set_heading(get_string('create_issue', 'local_edusupport'));

$form = new \create_issue_form();

if ($data = $form->get_data()) {
    $discussionid = \local_edusupport\lib::create_issue($data);
    if ($discussionid > 0) {
        redirect(
            new moodle_url('/mod/forum/discuss.php', ['d' => $discussionid]),
            get_string('create_issue_success_description', 'local_edusupport'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    // A mailed issue has no page to link to. We redirect to ourselves so that a reload cannot
    // submit the issue twice, and show the confirmation instead of the form.
    redirect(new moodle_url($PAGE->url, ['sent' => 1]));
}

echo $OUTPUT->header();

if ($sent) {
    echo $OUTPUT->notification(
        get_string('create_issue_success_description_mail', 'local_edusupport'),
        \core\output\notification::NOTIFY_SUCCESS
    );
} else {
    $form->set_data((object)[
        'url' => $returnurl,
        'contactphone' => $USER->phone1,
    ]);
    $form->display();
}

echo $OUTPUT->footer();
