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
 * File containing the form definition to post in the forum.
 * THIS IS A CLONE OF THE STANDARD FORM, THAT IS MODIFIED A LITTLE
 * FOR THIS PLUGIN.
 *
 * @package   mod_forum
 * @copyright Jamie Pratt <me@jamiep.org>
 *            modified by Rober Schrenk <robert.schrenk@lernmanagement.at>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Class to post in a forum.
 *
 * @package   mod_forum
 * @copyright Jamie Pratt <me@jamiep.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_edusupport_post_form extends moodleform {

    /**
     * Returns the options array to use in filemanager for forum attachments
     *
     * @param stdClass $forum
     * @return array
     */
    public static function attachment_options($forum) {
        global $COURSE, $PAGE, $CFG;
        // We use the global variable discussion to load the forum from database.
        // All the fields of the forum variable are protected!
        global $DB, $discussion;
        $dbforum = $DB->get_record('forum', array('id' => $discussion->forum));
        $maxbytes = get_user_max_upload_file_size($PAGE->context, $CFG->maxbytes, $COURSE->maxbytes, $dbforum->maxbytes);
        return array(
            'subdirs' => 0,
            'maxbytes' => $maxbytes,
            'maxfiles' => $dbforum->maxattachments,
            'accepted_types' => '*',
            'return_types' => FILE_INTERNAL | FILE_CONTROLLED_LINK
        );
    }

    /**
     * Returns the options array to use in forum text editor
     *
     * @param context_module $context
     * @param int $postid post id, use null when adding new post
     * @return array
     */
    public static function editor_options(context_module $context, $postid) {
        global $COURSE, $PAGE, $CFG;
        // We use the global variable discussion to load the forum from database.
        // All the fields of the forum variable are protected!
        global $DB, $discussion;
        $dbforum = $DB->get_record('forum', array('id' => $discussion->forum));

        // TODO: add max files and max size support
        $maxbytes = get_user_max_upload_file_size($PAGE->context, $CFG->maxbytes, $COURSE->maxbytes, $dbforum->maxbytes);
        return array(
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'maxbytes' => $maxbytes,
            'trusttext'=> true,
            'return_types'=> FILE_INTERNAL | FILE_EXTERNAL,
            'subdirs' => file_area_contains_subdirs($context, 'mod_forum', 'post', $postid)
        );
    }

    /**
     * Form definition
     *
     * @return void
     */
    function definition() {
        global $CFG, $OUTPUT;

        $mform =& $this->_form;

        $course = $this->_customdata['course'];
        $cm = $this->_customdata['cm'];
        $coursecontext = $this->_customdata['coursecontext'];
        $modcontext = $this->_customdata['modcontext'];
        $forum = $this->_customdata['forum'];
        $post = $this->_customdata['post'];
        $subscribe = $this->_customdata['subscribe'];
        $edit = $this->_customdata['edit'];
        $thresholdwarning = $this->_customdata['thresholdwarning'];
        $canreplyprivately = array_key_exists('canreplyprivately', $this->_customdata) ?
            $this->_customdata['canreplyprivately'] : false;
        $inpagereply = $this->_customdata['inpagereply'] ?? false;

        if (!$inpagereply) {
            // Fill in the data depending on page params later using set_data.
            $mform->addElement('header', 'general', '');
        }

        // If there is a warning message and we are not editing a post we need to handle the warning.
        if (!empty($thresholdwarning) && !$edit) {
            // Here we want to display a warning if they can still post but have reached the warning threshold.
            if ($thresholdwarning->canpost) {
                $message = get_string($thresholdwarning->errorcode, $thresholdwarning->module, $thresholdwarning->additional);
                $mform->addElement('html', $OUTPUT->notification($message));
            }
        }

        $mform->addElement('text', 'subject', get_string('subject', 'forum'), 'size="48"');
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', get_string('required'), 'required', null, 'client');
        $mform->addRule('subject', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('editor', 'message', get_string('message', 'forum'), null, self::editor_options($modcontext, (empty($post->id) ? null : $post->id)));
        $mform->setType('message', PARAM_RAW);
        $mform->addRule('message', get_string('required'), 'required', null, 'client');

        if (!$inpagereply) {
            $manageactivities = has_capability('moodle/course:manageactivities', $coursecontext);

            $mform->addElement('hidden', 'discussionsubscribe');
            $mform->setType('discussionsubscribe', PARAM_INT);
            $mform->setDefaults('discussionsubscribe', 0);

            $mform->addElement('filemanager', 'attachments', get_string('attachment', 'forum'), null,
                self::attachment_options($forum));
            $mform->addHelpButton('attachments', 'attachment', 'forum');

            $mform->addElement('hidden', 'mailnow');
            $mform->setType('mailnow', PARAM_INT);
            $mform->setDefaults('mailnow', 1);

            $mform->addElement('hidden', 'timestart');
            $mform->setType('timestart', PARAM_INT);
            $mform->addElement('hidden', 'timeend');
            $mform->setType('timeend', PARAM_INT);
            $mform->setConstants(array('timestart' => 0, 'timeend' => 0));


            if (core_tag_tag::is_enabled('mod_forum', 'forum_posts')) {
                $mform->addElement('header', 'tagshdr', get_string('tags', 'tag'));

                $mform->addElement('tags', 'tags', get_string('tags'),
                    array('itemtype' => 'forum_posts', 'component' => 'mod_forum'));
            }
        }

        //-------------------------------------------------------------------------------
        // buttons
        if (isset($post->edit)) { // hack alert
            $submitstring = get_string('savechanges');
        } else {
            $submitstring = get_string('posttoforum', 'forum');
        }

        // Always register a no submit button so it can be picked up if redirecting to the original post form.
        $mform->registerNoSubmitButton('advancedadddiscussion');

        // This is an inpage add discussion which requires custom buttons.
        if ($inpagereply) {
            $mform->addElement('hidden', 'discussionsubscribe');
            $mform->setType('discussionsubscribe', PARAM_INT);
            $mform->disable_form_change_checker();
            $buttonarray = array();
            $buttonarray[] = &$mform->createElement('submit', 'submitbutton', $submitstring);
            $buttonarray[] = &$mform->createElement('button', 'cancelbtn',
                get_string('cancel', 'core'),
                // Additional attribs to handle collapsible div.
                ['data-toggle' => 'collapse', 'data-target' => "#collapseAddForm"]);
            $buttonarray[] = &$mform->createElement('submit', 'advancedadddiscussion',
                get_string('advanced'), null, null, ['customclassoverride' => 'btn-link']);

            $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
            $mform->closeHeaderBefore('buttonar');
        } else {
            $this->add_action_buttons(true, $submitstring);
        }

        $mform->addElement('hidden', 'course');
        $mform->setType('course', PARAM_INT);

        $mform->addElement('hidden', 'forum');
        $mform->setType('forum', PARAM_INT);

        $mform->addElement('hidden', 'discussion');
        $mform->setType('discussion', PARAM_INT);

        $mform->addElement('hidden', 'parent');
        $mform->setType('parent', PARAM_INT);

        $mform->addElement('hidden', 'groupid');
        $mform->setType('groupid', PARAM_INT);

        $mform->addElement('hidden', 'edit');
        $mform->setType('edit', PARAM_INT);

        $mform->addElement('hidden', 'reply');
        $mform->setType('reply', PARAM_INT);
    }

    /**
     * Form validation
     *
     * @param array $data data from the form.
     * @param array $files files uploaded.
     * @return array of errors.
     */
    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (($data['timeend']!=0) && ($data['timestart']!=0) && $data['timeend'] <= $data['timestart']) {
            $errors['timeend'] = get_string('timestartenderror', 'forum');
        }
        if (empty($data['message']['text'])) {
            $errors['message'] = get_string('erroremptymessage', 'forum');
        }
        if (empty($data['subject'])) {
            $errors['subject'] = get_string('erroremptysubject', 'forum');
        }
        return $errors;
    }
}
