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

$context = context_system::instance();
// Must pass login
$PAGE->set_url('/local/edusupport/testreminder.php');
require_login();
$PAGE->set_context($context);
$PAGE->set_title(get_string('cron:reminder:title', 'local_edusupport'));
$PAGE->set_heading(get_string('cron:reminder:title', 'local_edusupport'));

echo $OUTPUT->header();

if (local_edusupport::is_admin()) {
    require_once($CFG->dirroot . '/local/edusupport/classes/task/reminder.php');
    $reminder = new \local_edusupport\task\reminder();
    $reminder->execute(true);

    echo "Reminders sent";
} else {
    echo "No permission";
}


echo $OUTPUT->footer();
