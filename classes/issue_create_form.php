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
 * @package    block_edupublisher
 * @copyright  2018 Digital Education Society (http://www.dibig.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . "/formslib.php");

class issue_create_form extends moodleform {
    var $maxbytes = 1024*1024;
    var $areamaxbytes = 10485760;
    var $maxfiles = 1;
    var $subdirs = 0;

    function definition() {
        global $CFG, $COURSE, $DB;

        $editoroptions = array('subdirs'=>0, 'maxbytes'=>0, 'maxfiles'=>0,
                               'changeformat'=>0, 'context'=>null, 'noclean'=>0,
                               'trusttext'=>0, 'enable_filemanagement' => false);

        $mform = $this->_form;
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'forumid', '');
        $mform->setType('forumid', PARAM_INT);
        $mform->addElement('hidden', 'url', '');
        $mform->setType('url', PARAM_TEXT);
        $mform->addElement('hidden', 'image', ''); // base64 encoded image
        $mform->setType('image', PARAM_RAW);

        $mform->addElement('header', 'header', get_string('header', 'block_edusupport', $COURSE->fullname));

        $mform->addElement('text', 'subject', get_string('subject', 'block_edusupport'), array('style' => 'width: 100%;', 'type' => 'tel'));
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', get_string('subject_missing', 'block_edusupport'), 'required', null, 'server');

        $mform->addElement('text', 'contactphone', get_string('contactphone', 'block_edusupport'), array('style' => 'width: 100%;'));
        $mform->setType('contactphone', PARAM_TEXT);
        //$mform->addRule('contactphone', get_string('contactphone_missing', 'block_edusupport'), 'required', null, 'server');

        $mform->addElement('textarea', 'description', get_string('description', 'block_edusupport'), array('style' => 'width: 100%;', 'rows' => 10));
        $mform->setType('description', PARAM_RAW);
        $mform->addRule('description', get_string('description_missing', 'block_edusupport'), 'required', null, 'server');

        // This ensures, that there will exist at least one group to show!
        require_once($CFG->dirroot . '/blocks/edusupport/locallib.php');
        $potentialtargets = block_edusupport\lib::get_potentialtargets();

        // If there are not potentialtargets we don't care. We will send a mail to the Moodle default support contact.
        $options = array();
        foreach ($potentialtargets AS $pt) {
            if (empty($pt->potentialgroups)) {
                $options[$pt->id . '_0'] = $pt->name;
            } else {
                foreach($groups AS $group) {
                    $options[$pt->id . '_' . $group->id] = $group->name;
                }
            }
        }

        $mform->addElement('select', 'forum_group', get_string('to_group', 'block_edusupport'), $options);
        $mform->setType('forum_group', PARAM_INT);

        $mform->addElement('checkbox', 'postscreenshot', get_string('screenshot', 'block_edusupport'), get_string('screenshot:description', 'block_edusupport'), array('onclick' => 'var c = this; require(["jquery"], function($) { $(c).closest("form").find("#screenshot").css("display", ($(c).is(":checked") ? "inline" : "none")); });'));
        $mform->setType('postscreenshot', PARAM_BOOL);
        $mform->setDefault('postscreenshot', 1);
        $mform->addElement('html', '<div style="text-align: center;"><img id="screenshot" src="" alt="Screenshot" style="max-width: 50%;"/></div>');
    }

    //Custom validation should be added here
    function validation($data, $files) {
        $errors = array();
        return $errors;
    }
}
