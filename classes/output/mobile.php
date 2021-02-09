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
 * Mobile functions for edusupport
 *
 * @package    local_edusupport
 * @copyright  2019 Zentrum für Lernmanagement (www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edusupport\output;

defined('MOODLE_INTERNAL') || die();


/**
 * Ouput components to generate mobile app screens.
 */
class mobile {
    /**
     * Get the IDs of courses where the user should see the block.
     */
    public static function edusupport_init(array $args) : array {
        global $DB, $USER;
        $courseids = array();
        $allsupportforums = $DB->get_records('local_edusupport', array());
        foreach ($allsupportforums AS $supportforum) {
            // If we are part of the support team of this forum, add the course.
            if (!empty(\local_edusupport::get_supporter_level($supportforum->courseid, $USER->id))) {
                $courseids[] = $supportforum->courseid;
            }
        }

        return [
            'restrict' => [
                'courses' => $courseids
            ],
            //'javascript' => file_get_contents($CFG->dirroot . '/blocks/news/appjs/news_init.js')
        ];
    }

}
