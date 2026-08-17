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

class create_issue_form extends moodleform {
    /** Screenshots are meant to be small, a full page capture stays well below this. */
    var $maxbytes = 10485760;

    /** Image types accepted as screenshot, mapped from the extension to the expected mimetype. */
    const SCREENSHOT_TYPES = [
        'gif' => 'image/gif',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    function definition() {
        global $CFG, $COURSE, $SITE;

        $faqread = get_config('local_edusupport', 'faqread');
        $faqlink = get_config('local_edusupport', 'faqlink');
        $disablephonefield = get_config('local_edusupport', 'phonefield');

        $mform = $this->_form;

        // The page the user came from, it is shown as a link to the supporters.
        $mform->addElement('hidden', 'url', '');
        $mform->setType('url', PARAM_LOCALURL);

        $mform->addElement('header', 'header', get_string('header', 'local_edusupport', $COURSE->fullname));

        if ($faqread) {
            $mform->addElement('checkbox', 'faqread', '', get_string('faqread:description', 'local_edusupport', $faqlink));
            $mform->setType('faqread', PARAM_BOOL);
            $mform->addRule('faqread', get_string('faqread', 'local_edusupport'), 'required', null, 'server');
        }

        require_once($CFG->dirroot . '/local/edusupport/classes/lib.php');
        $potentialtargets = \local_edusupport\lib::get_potentialtargets();

        $hideifs = array('mail');

        // If there are no potentialtargets we don't care. We will send a mail to the Moodle default support contact.
        $options = array();
        $labels = array();

        foreach ($potentialtargets as $pt) {
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
                foreach ($pt->potentialgroups as $group) {
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
            $options['mail'] = get_string('email_to_xyz', 'local_edusupport', (object)array('email' => $supportuser->email));
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
            '});',
        ];
        $mform->addElement('select', 'forum_group', get_string('to_group', 'local_edusupport'), $options, array('onchange' => implode("", $postto2ndlevel_hideshow)));
        $mform->setType('forum_group', PARAM_TEXT);

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

        $mform->addElement('text', 'subject', get_string('subject', 'local_edusupport'), array('style' => 'width: 100%;'));
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', get_string('subject_missing', 'local_edusupport'), 'required', null, 'server');

        if (!$disablephonefield) {
            $mform->addElement('text', 'contactphone', get_string('contactphone', 'local_edusupport'), array('style' => 'width: 100%;'));
        } else {
            $mform->addElement('hidden', 'contactphone', '');
        }
        $mform->setType('contactphone', PARAM_TEXT);

        $mform->addElement('textarea', 'description', get_string('description', 'local_edusupport'), array('style' => 'width: 100%;', 'rows' => 10));
        // Kept raw so the user can paste error messages containing angle brackets, it is
        // escaped before it goes into the post.
        $mform->setType('description', PARAM_RAW);
        $mform->addRule('description', get_string('description_missing', 'local_edusupport'), 'required', null, 'server');

        $mform->addElement('checkbox', 'postto2ndlevel', '', get_string('postto2ndlevel:description', 'local_edusupport', array('sitename' => $SITE->fullname)));
        $mform->setType('postto2ndlevel', PARAM_BOOL);
        $mform->setDefault('postto2ndlevel', 0);

        // Derived from the types we can verify, so both lists cannot drift apart.
        $acceptedtypes = array_map(function ($extension) {
            return '.' . $extension;
        }, array_keys(static::SCREENSHOT_TYPES));

        $mform->addElement('filepicker', 'screenshot', get_string('screenshot', 'local_edusupport'), null, array(
            'maxbytes' => $this->maxbytes,
            'accepted_types' => $acceptedtypes,
        ));
        $mform->addElement('static', 'screenshot_description', '', get_string('screenshot:description', 'local_edusupport'));

        $mform->addElement('html', '<script> setTimeout(function() { ' . implode('', $postto2ndlevel_hideshow) . ' }, 100);</script>');

        // No cancel button, the form is opened in its own window and is closed by the user.
        $this->add_action_buttons(false, get_string('send', 'local_edusupport'));
    }

    /**
     * The filepicker adds each chosen file to the draft area without removing the previous one.
     * A rejected file would therefore be validated again after the user picked a valid one, so we
     * drop everything but the file that was selected last. Runs before the validation.
     */
    function definition_after_data() {
        global $USER;

        parent::definition_after_data();

        $draftitemid = $this->_form->getElementValue('screenshot');
        if (is_array($draftitemid)) {
            $draftitemid = reset($draftitemid);
        }
        if (!$draftitemid) {
            return;
        }

        $usercontext = \context_user::instance($USER->id);
        $files = get_file_storage()->get_area_files($usercontext->id, 'user', 'draft',
            $draftitemid, 'id DESC', false);
        array_shift($files);
        foreach ($files as $file) {
            $file->delete();
        }
    }

    function validation($data, $files) {
        global $USER;

        $errors = parent::validation($data, $files);

        if (strlen(trim($data['subject'])) < 3) {
            $errors['subject'] = get_string('be_more_accurate', 'local_edusupport');
        }
        if (strlen(trim($data['description'])) < 5) {
            $errors['description'] = get_string('be_more_accurate', 'local_edusupport');
        }

        // The filepicker already enforced the allowed extensions, additionally we check that the
        // content really is an image of that type, so a renamed file cannot pass.
        if (!empty($data['screenshot'])) {
            $usercontext = \context_user::instance($USER->id);
            $draftfiles = get_file_storage()->get_area_files($usercontext->id, 'user', 'draft',
                $data['screenshot'], 'id DESC', false);
            $file = reset($draftfiles);
            if ($file && !static::is_valid_screenshot($file->get_content(), $file->get_filename())) {
                $errors['screenshot'] = get_string('screenshot:invalid', 'local_edusupport');
            }
        }

        return $errors;
    }

    /**
     * Checks whether an uploaded screenshot really is an image of the type its extension claims.
     *
     * The extension itself is already restricted by the filepicker, this verifies that the
     * content matches it, so a renamed file cannot pass.
     */
    public static function is_valid_screenshot(string $content, string $filename): bool {
        $extension = \core_text::strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!isset(static::SCREENSHOT_TYPES[$extension])) {
            return false;
        }

        // getimagesizefromstring() reads the magic bytes, therefore it tells us what the file
        // really is instead of what the client claims it to be.
        $imageinfo = @getimagesizefromstring($content);
        if (!$imageinfo || $imageinfo['mime'] !== static::SCREENSHOT_TYPES[$extension]) {
            return false;
        }

        // A file that only carries a valid header still passes the checks above, it reports
        // nonsense dimensions though. Decoding it is what really proves it is an image.
        $image = @imagecreatefromstring($content);
        if (!$image) {
            return false;
        }
        imagedestroy($image);

        return true;
    }
}
