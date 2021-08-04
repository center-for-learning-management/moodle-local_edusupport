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

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . '/local/edusupport/classes/lib.php');

class local_edusupport_external extends external_api {
    public static function close_issue_parameters() {
        return new external_function_parameters(array(
            'discussionid' => new external_value(PARAM_INT, 'discussionid')
        ));
    }
    public static function close_issue($discussionid) {
        global $CFG;
        $params = self::validate_parameters(self::close_issue_parameters(), array('discussionid' => $discussionid));
        return \local_edusupport\lib::close_issue($params['discussionid']);
    }
    public static function close_issue_returns() {
        return new external_value(PARAM_RAW, 'Returns 1 if successful, or error message.');
    }
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function create_issue_parameters() {
        return new external_function_parameters(array(
            'subject' => new external_value(PARAM_TEXT, 'subject of this issue'),
            'description' => new external_value(PARAM_RAW, 'default for whole package otherwise channel name'), // We use PARAM_RAW here, as the editor can send HTML
            'forum_group' => new external_value(PARAM_TEXT, 'Forum-ID and Group-ID to post to in format forumid_groupid.'),
            'postto2ndlevel' => new external_value(PARAM_INT, '1st level supporters can directly call the 2nd level support'),
            'image' => new external_value(PARAM_RAW, 'base64 encoded image as data url or empty string'),
            'screenshotname' => new external_value(PARAM_TEXT, 'the filename to use'),
            'url' => new external_value(PARAM_TEXT, 'URL where the error happened'), // We use PARAM_TEXT, as any input by the user is valid.
            'contactphone' => new external_value(PARAM_TEXT, 'Contactphone'), // We use PARAM_TEXT, was the user can enter any contact information.
        ));
    }

    /**
     * Create an issue in the targetforum.
     * @return postid of created issue
     */
    public static function create_issue($subject, $description, $forum_group, $postto2ndlevel, $image, $screenshotname, $url, $contactphone) {
        global $CFG, $DB, $OUTPUT, $PAGE, $USER, $SITE;
        $params = self::validate_parameters(self::create_issue_parameters(), array('subject' => $subject, 'description' => $description, 'forum_group' => $forum_group, 'postto2ndlevel' => $postto2ndlevel, 'image' => $image, 'screenshotname' => $screenshotname, 'url' => $url, 'contactphone' => $contactphone));
        $reply = array(
            'discussionid' => 0,
            'responsibles' => array(),
        );
        if (!empty(get_config('local_edusupport', 'trackhost'))) {
            $params['webhost'] = gethostname();
        }
        $params['description'] = nl2br($params['description']);

        $tmp = explode('_', $forum_group);
        $forumid = 0; $groupid = 0;
        if (count($tmp) == 2) {
            $forumid = $tmp[0];
            $groupid = $tmp[1];
        }

        $PAGE->set_context(\context_system::instance());

        if ($forum_group == 'mail' || empty($forumid)) {
            // fallback and send by mail!
            $subject = $params['subject'];
            $params['includeemail'] = $USER->email;
            $messagehtml = $OUTPUT->render_from_template("local_edusupport/issue_template", $params);
            $messagetext = html_to_text($messagehtml);

            $supportuser = \core_user::get_support_user();
            $recipients = array($supportuser);
            $reply['responsibles'][] = array(
                'userid' => $supportuser->id,
                'name' => \fullname($supportuser),
                'email' => $supportuser->email,
            );
            $fromuser = $USER;

            if (!empty($params['image'])) {
                $filename = $params['screenshotname'];
                // Write image to a temporary file
                $x = explode(",", $params['image']);
                $filepath = $CFG->tempdir . '/edusupport-' . md5($USER->id . date("Y-m-d H:i:s"));
                file_put_contents($filepath, base64_decode($x[1]));
                \core\antivirus\manager::scan_file($filepath, $filename, true);

                foreach($recipients AS $recipient) {
                    email_to_user($recipient, $fromuser, $subject, $messagetext, $messagehtml, $filepath, 'screenshot.' . $type);
                }
                if (file_exists($filepath)) unlink($filepath);
            } else {
                foreach($recipients AS $recipient) {
                    email_to_user($recipient, $fromuser, $subject, $messagetext, $messagehtml, "", true);
                }
            }
            $reply['discussionid'] = -999;
            return $reply;
        } else {
            $potentialtargets = \local_edusupport\lib::get_potentialtargets();
            if (\local_edusupport\lib::is_supportforum($forumid) && !empty($potentialtargets[$forumid]->id)) {
                $canpostto2ndlevel = $potentialtargets[$forumid]->postto2ndlevel;
                // Mainly copied from mod/forum/externallib.php > add_discussion()
                $warnings = array();

                // Request and permission validation.
                $forum = $DB->get_record('forum', array('id' => $forumid), '*', MUST_EXIST);
                list($course, $cm) = get_course_and_cm_from_instance($forum, 'forum');

                $coursesupporters = \local_edusupport\lib::get_course_supporters($forum);
                foreach ($coursesupporters as $coursesupporter) {
                    $reply['responsibles'][] = array(
                        'userid' => $coursesupporter->id,
                        'name' => \fullname($coursesupporter),
                        'email' => $coursesupporter->email,
                    );
                }

                $context = context_module::instance($cm->id);
                self::validate_context($context);

                // Validate options.
                $options = array(
                    'discussionsubscribe' => true,
                    'discussionpinned' => false,
                    'inlineattachmentsid' => 0,
                    'attachmentsid' => null
                );

                // Normalize group.
                if (!groups_get_activity_groupmode($cm)) {
                    // Groups not supported, force to -1.
                    $groupid = -1;
                } else {
                    // Check if we receive the default or and empty value for groupid,
                    // in this case, get the group for the user in the activity.
                    if (empty($groupid)) {
                        $groupid = groups_get_activity_group($cm);
                    }
                }

                if (!forum_user_can_post_discussion($forum, $groupid, -1, $cm, $context)) {
                    throw new moodle_exception('cannotcreatediscussion', 'forum');
                }

                $thresholdwarning = forum_check_throttling($forum, $cm);
                forum_check_blocking_threshold($thresholdwarning);

                $message = $OUTPUT->render_from_template("local_edusupport/issue_template", $params);

                // Create the discussion.
                $discussion = new stdClass();
                $discussion->course = $course->id;
                $discussion->forum = $forum->id;
                $discussion->message = $message;
                $discussion->messageformat = FORMAT_HTML;   // Force formatting for now.
                $discussion->messagetrust = trusttext_trusted($context);
                $discussion->itemid = 0; //$options['inlineattachmentsid'];
                $discussion->groupid = $groupid;
                $discussion->mailnow = 1;
                $discussion->subject = $params['subject'];
                $discussion->name = $discussion->subject;
                $discussion->timestart = 0;
                $discussion->timeend = 0;
                $discussion->timelocked = 0;
                $discussion->attachment = 0;

                if (has_capability('mod/forum:pindiscussions', $context) && $options['discussionpinned']) {
                    $discussion->pinned = FORUM_DISCUSSION_PINNED;
                } else {
                    $discussion->pinned = FORUM_DISCUSSION_UNPINNED;
                }

                if ($discussionid = forum_add_discussion($discussion)) {
                    $discussion->id = $discussionid;

                    if (!empty($params['image'])) {
                        $filename = $params['screenshotname'];

                        $x = explode(",", $params['image']);
                        // Write the file to a temp target.
                        $filepath = $CFG->tempdir . '/edusupport-' . md5($USER->id . date("Y-m-d H:i:s"));
                        file_put_contents($filepath, base64_decode($x[1]));

                        $fs = get_file_storage();
                        // Scan for viruses.
                        \core\antivirus\manager::scan_file($filepath, $filename, true);

                        $fr = new stdClass;
                        $fr->component = 'mod_forum';
                        $fr->contextid = $context->id;
                        $fr->userid    = $USER->id;
                        $fr->filearea  = 'attachment';
                        $fr->filename  = $filename;
                        $fr->filepath  = '/';
                        $fr->itemid    = $discussion->firstpost;
                        $fr->license   = $CFG->sitedefaultlicense;
                        $fr->author    = fullname($USER);
                        $fr->source    = serialize((object)array('source' => $filename));

                        $fs->create_file_from_pathname($fr, $filepath);
                        $DB->set_field('forum_posts', 'attachment', 1, array('id'=>$discussion->firstpost));
                    }

                    // Trigger events and completion.

                    $evparams = array(
                        'context' => $context,
                        'objectid' => $discussion->id,
                        'other' => array(
                            'forumid' => $forum->id,
                        )
                    );
                    $event = \mod_forum\event\discussion_created::create($evparams);
                    $event->add_record_snapshot('forum_discussions', $discussion);
                    $event->trigger();

                    $completion = new completion_info($course);
                    if ($completion->is_enabled($cm) &&
                            ($forum->completiondiscussions || $forum->completionposts)) {
                        $completion->update_state($cm, COMPLETION_COMPLETE);
                    }

                    $settings = new stdClass();
                    $settings->discussionsubscribe = $options['discussionsubscribe'];
                    forum_post_subscription($settings, $forum, $discussion);

                    if ($canpostto2ndlevel && !empty($postto2ndlevel)) {
                        \local_edusupport\lib::set_2nd_level($discussion->id);
                    } else {
                        // Post answer containing the reponsibles.
                        $managers = array_values(\local_edusupport\lib::get_course_supporters($forum));
                        $resposibles = array();
                        foreach ($managers as $manager) {
                            $responsibles[] = "<a href=\"{$CFG->wwwroot}/user/profile.php?id={$manager->id}\" target=\"_blank\">{$manager->firstname} {$manager->lastname}</a>";
                        }
                        \local_edusupport\lib::create_post($discussion->id,
                            get_string(
                                'issue_responsibles:post',
                                'local_edusupport',
                                array(
                                    'responsibles' => implode(', ', $responsibles),
                                    'sitename' => $SITE->fullname,
                                )
                            ),
                            get_string('issue_responsibles:subject', 'local_edusupport')
                        );
                    }
                    $reply['discussionid'] = $discussionid;
                    return $reply;
                } else {
                    throw new moodle_exception('couldnotadd', 'forum');
                }
                $reply['discussionid'] = -2;
                return $reply;

            } else {
                $reply['discussionid'] = -1;
                return $reply;
            }
        }
    }
    /**
     * Return definition.
     * @return external_value
     */
    public static function create_issue_returns() {
        return new \external_single_structure(
            array(
                'discussionid' => new \external_value(PARAM_INT, 'Returns the discussion id of the created issue, -999 when mail was sent, or -1 on error'),
                'responsibles' => new \external_multiple_structure(
                    new \external_single_structure(
                        array(
                            'userid' => new \external_value(PARAM_INT, 'UserID of person or entity'),
                            'name' => new \external_value(PARAM_TEXT, 'Name of person or entity'),
                            'email' => new \external_value(PARAM_EMAIL, 'e-Mail of person or entity'),
                        )
                    )
                )
            )
        );
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function create_form_parameters() {
        return new external_function_parameters(array(
            'url' => new external_value(PARAM_TEXT, 'subject of this issue'),
            'image' => new external_value(PARAM_RAW, 'base64 encoded image or empty'),
            'forumid' => new external_value(PARAM_INT, 'forumid the form is for'),
        ));
    }

    /**
     * Create an issue in the targetforum.
     * @return postid of created issue
     */
    public static function create_form($url, $image, $forumid) {
        global $CFG, $PAGE, $USER;

        $params = self::validate_parameters(self::create_form_parameters(), array('url' => $url, 'image' => $image, 'forumid' => $forumid));

        $PAGE->set_context(context_system::instance());

        \local_edusupport\lib::before_popup();

        require_once($CFG->dirroot . '/local/edusupport/classes/issue_create_form.php');
        $params['contactphone'] = $USER->phone1;
        $form = new \issue_create_form(null, null, 'post', '_self', array('id' => 'local_edusupport_create_form'), true);
        $form->set_data((object) $params);
        return $form->render();
    }
    /**
     * Return definition.
     * @return external_value
     */
    public static function create_form_returns() {
        return new external_value(PARAM_RAW, 'Returns the form as html');
    }

    public static function get_potentialsupporters_parameters() {
        return new external_function_parameters(
            array(
                'discussionid' => new external_value(PARAM_INT, 'discussionid')
            )
        );
    }
    public static function get_potentialsupporters($discussionid) {
        global $DB, $USER;
        $params = self::validate_parameters(self::get_potentialsupporters_parameters(), array('discussionid' => $discussionid));
        $reply['supporters'] = array();

        $discussion = $DB->get_record('forum_discussions', array('id' => $params['discussionid']));
        $sql = "SELECT s.userid,u.firstname,u.lastname,s.supportlevel
                    FROM {user} u, {local_edusupport_supporters} s
                    WHERE u.id=s.userid
                        AND (s.courseid=1 OR s.courseid=?)
                    ORDER BY u.lastname ASC,u.firstname ASC";
        $supporters = $DB->get_records_sql($sql, array($discussion->course));
        foreach($supporters AS $supporter) {
            if (empty($supporter->supportlevel)) {
                $supporter->supportlevel = get_string('label:2ndlevel', 'local_edusupport');
            }
            if (!isset($reply['supporters'][$supporter->supportlevel])) {
                $reply['supporters'][$supporter->supportlevel] = array();
            }
            if (empty($issue->currentsupporter) && $supporter->userid == $USER->id) {
                $supporter->selected = true;
            } elseif ($issue->currentsupporter == $supporter->userid) {
                $supporter->selected = true;
            }
            $reply['supporters'][$supporter->supportlevel][] = $supporter;
        }

        return json_encode($reply, JSON_NUMERIC_CHECK);
    }
    public static function get_potentialsupporters_returns() {
        return new external_value(PARAM_RAW, 'Returns a json encoded array containing potential supporters.');
    }

    public static function set_archive_parameters() {
        return new external_function_parameters(array(
            'forumid' => new external_value(PARAM_INT, 'ForumID of archive'),
        ));
    }
    public static function set_archive($forumid) {
        global $CFG, $DB, $PAGE;

        $params = self::validate_parameters(self::set_archive_parameters(), array('forumid' => $forumid));

        $forum = $DB->get_record('forum', array('id' => $params['forumid']));
        if (empty($forum->id)) return -1;

        if (local_edusupport::can_config_course($forum->course)){
            $entry = $DB->get_record('local_edusupport', array('courseid' => $forum->course));
            if (!empty($entry->courseid)) {
                $entry->forumid = !empty($entry->forumid) ? $entry->forumid : 0;
                $entry->archiveid = $forum->id;
                $DB->update_record('local_edusupport', $entry);
            } else {
                $entry = (object) array('courseid' => $forum->course, 'forumid' => $forum->id, 'archiveid' => 0);
                $DB->insert_record('local_edusupport', $entry);
            }
            return 1;
        }
        return 0;
    }
    public static function set_archive_returns() {
        return new external_value(PARAM_INT, 'Returns 1 if successful');
    }

    public static function set_currentsupporter_parameters() {
        return new external_function_parameters(
            array(
                'discussionid' => new external_value(PARAM_INT, 'discussionid'),
                'supporterid' => new external_value(PARAM_INT, 'supporterid (userid)'),
            )
        );
    }
    public static function set_currentsupporter($discussionid, $supporterid) {
        global $CFG, $DB, $USER;
        $params = self::validate_parameters(self::set_currentsupporter_parameters(), array('discussionid' => $discussionid, 'supporterid' => $supporterid));
        return \local_edusupport\lib::set_current_supporter($params['discussionid'], $params['supporterid']);
    }
    public static function set_currentsupporter_returns() {
        return new external_value(PARAM_RAW, 'Returns 1 if successful.');
    }


    public static function set_default_parameters() {
        return new external_function_parameters(array(
            'forumid' => new external_value(PARAM_INT, 'ForumID of new systemwide forum'),
            'asglobal' => new external_value(PARAM_BOOL, 'Whether this should be used as global target forum or not'),
        ));
    }
    public static function set_default($forumid, $asglobal) {
        global $CFG, $DB, $PAGE;

        $params = self::validate_parameters(self::set_default_parameters(), array('forumid' => $forumid, 'asglobal' => $asglobal));

        $forum = $DB->get_record('forum', array('id' => $params['forumid']));
        if (empty($forum->id)) return -1;
        if ($params['asglobal']) {
            if (local_edusupport::can_config_global()) {
                set_config('targetforum', $forum->id, 'local_edusupport');
            } else {
                return -2;
            }
        } elseif ($forum->id == get_config('local_edusupport', 'targetforum')) {
            set_config('targetforum', 0, 'local_edusupport');
        }

        if (local_edusupport::can_config_course($forum->course)){
            $entry = $DB->get_record('local_edusupport', array('courseid' => $forum->course));
            if (!empty($entry->forumid)) {
                $entry->forumid = $forum->id;
                $DB->update_record('local_edusupport', $entry);
            } else {
                $entry = (object) array('courseid' => $forum->course, 'forumid' => $forum->id);
                $DB->insert_record('local_edusupport', $entry);
            }
            return 1;
        }
        return 0;
    }
    public static function set_default_returns() {
        return new external_value(PARAM_INT, 'Returns 1 if successful');
    }

    public static function set_supporter_parameters() {
        return new external_function_parameters(array(
            'courseid' => new external_value(PARAM_INT, 'CourseID'),
            'userid' => new external_value(PARAM_INT, 'UserID'),
            'supportlevel' => new external_value(PARAM_TEXT, 'Supportlevel to set'),
        ));
    }
    public static function set_supporter($courseid, $userid, $supportlevel) {
        global $CFG, $DB, $PAGE;

        $params = self::validate_parameters(self::set_supporter_parameters(), array('courseid' => $courseid, 'userid' => $userid, 'supportlevel' => $supportlevel));
        if (local_edusupport::can_config_course($params['courseid'])){
            if (empty($params['supportlevel'])) {
                $DB->delete_records('local_edusupport_supporters', array('courseid' => $params['courseid'], 'userid' => $params['userid']));
            } else {
                $entry = $DB->get_record('local_edusupport_supporters', array('courseid' => $params['courseid'], 'userid' => $params['userid']));
                if (!empty($entry->supportlevel)) {
                    $entry->supportlevel = $params['supportlevel'];
                    $DB->update_record('local_edusupport_supporters', $entry);
                } else {
                    $DB->insert_record('local_edusupport_supporters', (object) $params);
                }
            }
            return 1;
        }
        return 0;
    }
    public static function set_supporter_returns() {
        return new external_value(PARAM_INT, 'Returns 1 if successful');
    }

}
