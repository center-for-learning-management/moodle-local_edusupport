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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $settings = new admin_settingpage( 'local_edusupport_settings', get_string('pluginname', 'local_edusupport'));
    $ADMIN->add('localplugins', $settings);

    // Possibly we changed the menu, therefore we delete the cache. We should find a better place for this.
    $cache = cache::make('local_edusupport', 'supportmenu');
    $cache->delete('rendered');

    $settings->add(
        new admin_setting_configtextarea(
            'local_edusupport/extralinks',
            get_string('extralinks', 'local_edusupport'),
            get_string('extralinks:description', 'local_edusupport'),
            '',
            PARAM_TEXT
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'local_edusupport/trackhost',
            get_string('trackhost', 'local_edusupport'),
            get_string('trackhost:description', 'local_edusupport'),
            1
        )
    );

    // FAQ read.
    $settings->add(
        new admin_setting_configcheckbox(
            'local_edusupport/faqread',
            get_string('faqread', 'local_edusupport'),
            '',
            1
        )
    );

    // FAQ Link.
    $settings->add(
        new admin_setting_configtext(
            'local_edusupport/faqlink',
            get_string('faqlink', 'local_edusupport'),
            get_string('faqlink:description', 'local_edusupport'),
            ''
        )
    );

    // Disable User Profile Links.
    $settings->add(
        new admin_setting_configcheckbox(
            'local_edusupport/userlinks',
            get_string('userlinks', 'local_edusupport'),
            get_string('userlinks:description', 'local_edusupport'),
            1
        )
    );

    // Priority LVL.
    $settings->add(
        new admin_setting_configcheckbox(
            'local_edusupport/prioritylvl',
            get_string('prioritylvl', 'local_edusupport'),
            get_string('prioritylvl:description', 'local_edusupport'),
            1
        )
    );

    // Disable Telephone Link.
    $settings->add(
        new admin_setting_configcheckbox(
            'local_edusupport/phonefield',
            get_string('phonefield', 'local_edusupport'),
            get_string('phonefield:description', 'local_edusupport'),
            1
        )
    );

    // Delete threshhold.
    $settings->add(
        new admin_setting_configduration(
            'local_edusupport/deletethreshhold',
            get_string('deletethreshhold', 'local_edusupport'),
            get_string('deletethreshhold:description', 'local_edusupport'),
            4 * WEEKSECS)
    );



    // @TODO a feature from the future.
    // $settings->add(new admin_setting_configcheckbox('local_edusupport/sendreminders', get_string('cron:reminder:title', 'local_edusupport'), '', '', PARAM_INT));

    $actions = array(
        (object) array('name' => 'supporters', 'href' => 'choosesupporters.php')
    );
    $links = "<div class=\"grid-eq-3\">";
    foreach($actions AS $action) {
        $links .= '<a class="btn btn-secondary" href="' . $CFG->wwwroot . '/local/edusupport/' . $action->href . '">' .
                        '<i class="fa fa-users"></i> ' .
                        get_string($action->name, 'local_edusupport') .
                  '</a>';
    }
    $links .= "</div>";
    $settings->add(new admin_setting_heading('local_edusupport_actions', get_string('settings'), $links));
}
