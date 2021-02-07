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
 * @copyright  2018 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edusupport\task;

defined('MOODLE_INTERNAL') || die;

class delete extends \core\task\scheduled_task {
    public function get_name() {
        // Shown in admin screens.
        return get_string('cron:deleteexpiredissues:title', 'local_edusupport');
    }

    public function execute($debug=false) {
        $issues = \local_edusupport\lib::get_expiredissues();
        foreach ($issues AS $issue) {
            \local_edusupport\lib::delete_issue($issue->discussionid);
        }
    }
}
