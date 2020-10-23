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
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
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

        $mform->addElement('header', 'header', get_string('header', 'local_edusupport', $COURSE->fullname));

        $mform->addElement('text', 'subject', get_string('subject', 'local_edusupport'), array('style' => 'width: 100%;', 'type' => 'tel'));
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', get_string('subject_missing', 'local_edusupport'), 'required', null, 'server');

        $mform->addElement('text', 'contactphone', get_string('contactphone', 'local_edusupport'), array('style' => 'width: 100%;'));
        $mform->setType('contactphone', PARAM_TEXT);
        //$mform->addRule('contactphone', get_string('contactphone_missing', 'local_edusupport'), 'required', null, 'server');

        $mform->addElement('textarea', 'description', get_string('description', 'local_edusupport'), array('style' => 'width: 100%;', 'rows' => 10));
        $mform->setType('description', PARAM_RAW);
        $mform->addRule('description', get_string('description_missing', 'local_edusupport'), 'required', null, 'server');

        require_once($CFG->dirroot . '/local/edusupport/classes/lib.php');
        $potentialtargets = \local_edusupport\lib::get_potentialtargets();

        $hideifs = array('mail');
        // If there are not potentialtargets we don't care. We will send a mail to the Moodle default support contact.
        $options = array();

        foreach ($potentialtargets AS $pt) {
            if (empty($pt->potentialgroups) || count($pt->potentialgroups) == 0) {
                $options[$pt->id . '_0'] = $pt->name;
                if (empty($pt->postto2ndlevel)) {
                    $hideifs[] = $pt->id . '_0';
                }
            } else {
                foreach($pt->potentialgroups AS $group) {
                    $options[$pt->id . '_' . $group->id] = $pt->name . ' > ' . $group->name;
                    if (empty($pt->postto2ndlevel)) {
                        $hideifs[] = $pt->id . '_' . $group->id;
                    }
                }
            }
        }
        $supportuser = \core_user::get_support_user();
        if (count($potentialtargets) == 0) {
            $options['mail'] = get_string('email_to_xyz', 'local_edusupport', (object) array('email' => $supportuser->email));
        }

        $hideifs = '["' . implode('","', $hideifs) . '"]';
        $postto2ndlevel_hideshow = 'var hide = (' . $hideifs . '.indexOf($(\'#id_forum_group\').val()) > -1); var pt2 = $(\'#id_postto2ndlevel\'); $(pt2).prop(\'checked\', false); $(pt2).closest(\'div.form-group\').css(\'display\', hide ? \'none\' : \'block\');';
        $mform->addElement('select', 'forum_group', get_string('to_group', 'local_edusupport'), $options, array('onchange' => $postto2ndlevel_hideshow));
        $mform->setType('forum_group', PARAM_INT);

        $mform->addElement('checkbox', 'postto2ndlevel', get_string('postto2ndlevel', 'local_edusupport'), get_string('postto2ndlevel:description', 'local_edusupport'));
        $mform->setType('postto2ndlevel', PARAM_BOOL);
        $mform->setDefault('postto2ndlevel', 0);

        $html = array(
            '<div id="screenshot_ok" style="display: none;">',
            get_string('screenshot:generateinfo', 'local_edusupport'),
            '<br /><a href="#" onclick="var b = this; require([\'local_edusupport/main\'], function(M) { M.generateScreenshot(b); }); return false;" class="btn btn-primary btn-block">',
            get_string('ok'),
            '</a></div>'
        );
        $mform->addElement('checkbox', 'postscreenshot', get_string('screenshot', 'local_edusupport'),
                                get_string('screenshot:description', 'local_edusupport') . implode("\n", $html),
                                array('onclick' => 'var c = this; require(["local_edusupport/main"], function(M) { M.checkHasScreenshot(c); });')
                        );
        $mform->setType('postscreenshot', PARAM_BOOL);
        $mform->setDefault('postscreenshot', 0);

        $html = array(
            '<div style="text-align: center;">',
            '<img id="screenshot" src="" alt="Screenshot" style="max-width: 50%; display: none;"/>',
            '</div>',
        );
        $mform->addElement('html', implode("\n", $html));
        $mform->addElement('html', '<script> setTimeout(function() { ' . $postto2ndlevel_hideshow . ' }, 100);</script>');
    }

    //Custom validation should be added here
    function validation($data, $files) {
        $errors = array();
        return $errors;
    }
}
