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
        global $CFG, $COURSE, $DB, $SITE;

        $faqread = get_config('local_edusupport','faqread');
        $faqlink = get_config('local_edusupport','faqlink');
        $prioritylvl = get_config('local_edusupport','prioritylvl');
        $disablephonefield = get_config('local_edusupport','phonefield');


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

        if ($faqread) {
            $mform->addElement('checkbox', 'faqread','', get_string('faqread:description', 'local_edusupport',$faqlink));
            $mform->setType('faqread', PARAM_BOOL);
            $mform->addRule('faqread', get_string('subject_missing', 'local_edusupport'), 'required', true, 'server');
        } else {
            $mform->addElement('html', '<input type="checkbox" id="id_faqread" class="autochecked" style="display: none;" checked="checked" />');
        }


        $mform->addElement('html','<div id="create_issue_input">');

        require_once($CFG->dirroot . '/local/edusupport/classes/lib.php');
        $potentialtargets = \local_edusupport\lib::get_potentialtargets();

        $hideifs = array('mail');

        // If there are not potentialtargets we don't care. We will send a mail to the Moodle default support contact.
        $options = array();
        $labels = array();

        foreach ($potentialtargets AS $pt) {
            $managers = array_values(\local_edusupport\lib::get_course_supporters($pt));
            $label = array();
            for ($a = 0; $a < count($managers) && $a < 3; $a++) {
                $manager = $managers[$a];
                $label[] = "<a href=\"{$CFG->wwwroot}/user/profile.php?id={$manager->id}\" target=\"_blank\">{$manager->firstname} {$manager->lastname}</a>";
            }
            if (count($managers) > 3) {
                $label[] = '...';
            }
            $label = implode(", ", $label);
            if (empty($pt->potentialgroups) || count($pt->potentialgroups) == 0) {
                $labels[$pt->id . '_0'] = $label;
                $options[$pt->id . '_0'] = $pt->name;
                if (empty($pt->postto2ndlevel)) {
                    $hideifs[] = $pt->id . '_0';
                }
            } else {
                foreach($pt->potentialgroups AS $group) {
                    $labels[$pt->id . '_' . $group->id] = $label;
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
            $labels['mail'] = $supportuser->email;
        }

        $hideifs = '["' . implode('","', $hideifs) . '"]';
        $postto2ndlevel_hideshow = [
            'require([\'jquery\'], function($) {',
                'var val = $(\'#id_forum_group\').val();',
                '$(\'.edusupport_label\').addClass(\'hidden\');',
                '$(\'#edusupport_label_\' + val).removeClass(\'hidden\');',
                'var hide = (' . $hideifs . '.indexOf(val) > -1);',
                'var pt2 = $(\'#id_postto2ndlevel\');',
                '$(pt2).prop(\'checked\', false);',
                '$(pt2).closest(\'div.form-group\').css(\'display\', hide ? \'none\' : \'block\');',
            '});'
        ];
        $mform->addElement('select', 'forum_group', get_string('to_group', 'local_edusupport'), $options, array('onchange' => implode("",$postto2ndlevel_hideshow)));
        $mform->setType('forum_group', PARAM_INT);

        $managerslabel = [
            '<div class="form-group row fitem">',
            '   <div class="col-md-3"></div>',
            '   <div class="col-md-9">',
        ];

        foreach ($labels as $identifier => $label) {
            $managerslabel[] = '        <div class="edusupport_label hidden" id="edusupport_label_' . $identifier . '" class="hidden">';
            $managerslabel[] = '            ' . $label;
            $managerslabel[] = '        </div>';
        }

        $managerslabel[] = '   </div>';
        $managerslabel[] = '</div>';

        $mform->addElement('html', implode("\n", $managerslabel));

        $mform->addElement('text', 'subject', get_string('subject', 'local_edusupport'), array('style' => 'width: 100%;', 'type' => 'tel'));
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', get_string('subject_missing', 'local_edusupport'), 'required', null, 'server');

        if(!$disablephonefield) {
            $mform->addElement('text', 'contactphone', get_string('contactphone', 'local_edusupport'), array('style' => 'width: 100%;'));
            $mform->setType('contactphone', PARAM_TEXT);
        }
        else {
            $mform->addElement('hidden', 'contactphone', '');
            $mform->setType('contactphone', PARAM_TEXT);
        }
        $mform->addElement('textarea', 'description', get_string('description', 'local_edusupport'), array('style' => 'width: 100%;', 'rows' => 10));
        $mform->setType('description', PARAM_RAW);
        $mform->addRule('description', get_string('description_missing', 'local_edusupport'), 'required', null, 'server');

        $mform->addElement('checkbox', 'postto2ndlevel', '', get_string('postto2ndlevel:description', 'local_edusupport', array('sitename' => $SITE->fullname)));
        $mform->setType('postto2ndlevel', PARAM_BOOL);
        $mform->setDefault('postto2ndlevel', 0);

        $fileupload = [
            '<div class="form-group row fitem">',
            '   <div class="col-md-3">' . get_string('screenshot', 'local_edusupport') . '</div>',
            '   <div class="col-md-9" id="edusupport_screenshot">',
            '       <input type="file" onchange="require([\'local_edusupport/main\'], function(M) { M.uploadScreenshot(); });" /><br />',
            '       <div class="alert alert-danger hidden">' . get_string('screenshot:upload:failed', 'local_edusupport') . '</div>',
            '       <div class="alert alert-success hidden">' . get_string('screenshot:upload:successful', 'local_edusupport') . '</div>',
            '   </div>',
            '</div>'
        ];
        $mform->addElement('html', implode("\n", $fileupload));
        /*
        $html = array(
            '<div id="screenshot_ok"  style="display: none;"><p>',
            get_string('screenshot:generateinfo', 'local_edusupport'),
            '</p><a href="#" onclick="var b = this; require([\'local_edusupport/main\'], function(M) { M.generateScreenshot(b); }); return false;" class="btn btn-primary btn-block">',
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
            '<div id="screenshot_new" class="text-center m-2" style="display:none;">',
            '<a href="#" onclick="var b = this; require([\'local_edusupport/main\'], function(M) { M.generateScreenshot(b); }); return false;" class="btn btn-primary">',
            get_string('new'),
            '</a></div>'
        );
        $mform->addElement('html', implode("\n", $html));
        */
        $mform->addElement('html', '<script> setTimeout(function() { ' . implode('', $postto2ndlevel_hideshow) . ' }, 100);</script>');

        $mform->addElement('html','</div>');

        /*
        if ($prioritylvl) {
            $mform->addElement('select', 'prioritylvl', get_string('prioritylvl', 'local_edusupport'), $this->return_priority_options());
        }
        */
    }

    //Custom validation should be added here
    function validation($data, $files) {
        $errors = array();
        return $errors;
    }

    function return_priority_options() {
        return [
            "" => get_string('prioritylvl:low', 'local_edusupport'),
            "!" => get_string('prioritylvl:mid', 'local_edusupport'),
            "!!" => get_string('prioritylvl:high', 'local_edusupport'),
        ];
    }
}
