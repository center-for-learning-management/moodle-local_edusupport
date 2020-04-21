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
 * @copyright  2018 Digital Education Society (http://www.dibig.at)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

defined('MOODLE_INTERNAL') || die;
if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configcheckbox('block_edusupport/autocreate_usergroup', get_string('autocreate_usergroup', 'block_edusupport'), '', '', PARAM_INT));
    $settings->add(new admin_setting_configcheckbox('block_edusupport/sendreminders', get_string('cron:reminder:title', 'block_edusupport'), '', '', PARAM_INT));

    $settings->add(new admin_setting_configtext('block_edusupport/relativeurlsupportarea', get_string('relativeurlsupportarea', 'block_edusupport'), '', '', PARAM_URL));
    $settings->add(new admin_externalpage(
        'block_edusupport_supporters',
        get_string('supporters', 'block_edusupport'),
        $CFG->wwwroot . '/blocks/edusupport/choosesupporters.php'
    ));


}
