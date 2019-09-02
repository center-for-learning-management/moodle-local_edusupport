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
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . "/externallib.php");

class block_edusupport_external extends external_api {
    public static function close_issue_parameters() {
        return new external_function_parameters(array(
            'discussionid' => new external_value(PARAM_INT, 'discussionid')
        ));
    }
    public static function close_issue($discussionid) {
        global $CFG;
        $params = self::validate_parameters(self::close_issue_parameters(), array('discussionid' => $discussionid));
        require_once($CFG->dirroot . '/blocks/edusupport/block_edusupport.php');
        return block_edusupport::close_issue($params['discussionid']);
    }
    public static function close_issue_returns() {
        return new external_value(PARAM_RAW, 'Returns 1 if successful, or error message.');
    }


    public static function colorize_parameters() {
        return new external_function_parameters(
            array(
                'discussionids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'discussionid')
                )
            )
        );
    }
    public static function colorize($discussionids) {
        global $CFG, $DB;
        $params = self::validate_parameters(self::colorize_parameters(), array('discussionids' => $discussionids));

        require_once($CFG->dirroot . '/blocks/edusupport/block_edusupport.php');
        $styles = array();
        $forum_check = array();
        foreach($params['discussionids'] AS $discussionid) {
            $discussion = $DB->get_record('forum_discussions', array('id' => $discussionid));
            if (empty($forum_check[$discussion->forum])) {
                $support = $DB->get_record('block_edusupport', array('courseid' => $discussion->course));
                $forum_check[$discussion->forum] = ($support->forumid == $discussion->forum);
            }
            if (!$forum_check[$discussion->forum]) {
                // Sorry, this discussion is not from a support forum.
                continue;
            }
            $issue = block_edusupport::get_issue($discussionid);
            if (empty($issue->currentsupporter)) {
                $styles[$discussionid] = block_edusupport::$STYLE_UNASSIGNED;
            } elseif ($issue->opened == 1) {
                $styles[$discussionid] = block_edusupport::$STYLE_OPENED;
            } elseif ($issue->opened == 0) {
                $styles[$discussionid] = block_edusupport::$STYLE_CLOSED;
            }
        }
        return json_encode(array('styles' => $styles), JSON_NUMERIC_CHECK);
    }
    public static function colorize_returns() {
        return new external_value(PARAM_RAW, 'Returns a json encoded object containing directives to colorize discussions.');
    }
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function create_issue_parameters() {
        return new external_function_parameters(array(
            'subject' => new external_value(PARAM_TEXT, 'subject of this issue'),
            'description' => new external_value(PARAM_TEXT, 'default for whole package otherwise channel name'),
            'forumid' => new external_value(PARAM_INT, 'Forum-ID to post to.'),
            'to_group' => new external_value(PARAM_INT, 'Group-ID to post to.'),
            'image' => new external_value(PARAM_RAW, 'base64 encoded image as data url or empty string'),
            'url' => new external_value(PARAM_TEXT, 'URL where the error happened'),
            'contactphone' => new external_value(PARAM_TEXT, 'Contactphone'),
        ));
    }

    /**
     * Create an issue in the targetforum.
     * @return postid of created issue
     */
    public static function create_issue($subject, $description, $forumid, $to_group, $image, $url, $contactphone) {
        global $CFG, $DB, $PAGE, $USER;
        $params = self::validate_parameters(self::create_issue_parameters(), array('subject' => $subject, 'description' => $description, 'forumid' => $forumid, 'to_group' => $to_group, 'image' => $image, 'url' => $url, 'contactphone' => $contactphone));

        require_once($CFG->dirroot . '/blocks/edusupport/block_edusupport.php');
        block_edusupport::set_target($params['forumid']);
        $targetforum = block_edusupport::get_target();
        if ($targetforum < 0) return -1;

        $forum = $DB->get_record('forum', array('id'=>$targetforum));
        $cm    = get_coursemodule_from_instance('forum', $forum->id);
        if (!isset($cm->id) || $cm->id == 0) return -1;
        $PAGE->set_context(context_module::instance($cm->id));

        $groups = block_edusupport::get_groups();
        error_log("Groups: " . print_r($groups, 1));
        foreach ($groups AS $group) {
            if (isset($post_to_group->id)) continue;
            if (empty($to_group) || $to_group == $group->id) {
                $post_to_group = $group;
            }
        }

        // If we have no group - exit.
        if (!isset($post_to_group->id) || $post_to_group->id == 0) return -1;

        $message = '<p>URL: <a href="' . $params['url'] . '" target="_blank">' . $params['url'] . '</a></p>';
        if (!empty($params['contactphone'])) {
            $message .= '<p><a href="tel:' . $params['contactphone'] . '">' . $params['contactphone'] . '</a></p>';
        }
        $message .= $params['description'];
        $post = (object) array(
            'created' => time(),
            'modified' => time(),
            'parent' => 0,
            'userid' => $USER->id,
            'name' => $params['subject'], // for discussion it is name, for post it is subject.
            'subject' => $params['subject'],
            'mailed' => 0,
            'mailnow' => 0,
            'message' => $message,
            'messageformat' => 1,
            'messagetrust' => 0,
            'forum' => $targetforum,
            'course' => $cm->course,
            'groupid' => $post_to_group->id,
        );
        $post->id = $DB->insert_record("forum_posts", $post, true);

        $discussion = $post;
        $discussion->firstpost    = $post->id;
        $discussion->timemodified = time();
        $discussion->usermodified = $post->userid;
        $discussion->userid       = $post->userid;
        $discussion->assessed     = 0;
        $post->discussion = $DB->insert_record("forum_discussions", $discussion, true);
        $DB->set_field("forum_posts", "discussion", $post->discussion, array("id"=>$post->id));

        $attachments = 0;
        if (!empty($params['image'])) {
            // Write image to a temporary file
            $x = explode(",", $params['image']);
            $f = tmpfile();
            fwrite($f, base64_decode($x[1]));

            // Get mimetype (e.g. png)
            $type = str_replace('data:image/', '', $x[0]);
            $type = str_replace(';base64', '', $type);
            $filepath = stream_get_meta_data($f)['uri'];
            $filename = 'screenshot_' . date('Y-m-d_H_i_s') . '.' . $type;

            $context = context_module::instance($cm->id);
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
            $fr->itemid    = $post->id;
            $fr->license   = $CFG->sitedefaultlicense;
            $fr->author    = fullname($USER);
            $fr->source    = serialize((object)array('source' => $filename));

            $fs->create_file_from_pathname($fr, $filepath);
            $DB->set_field('forum_posts', 'attachment', 1, array('id'=>$post->id));
        }
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        if (isset($discussion->tags)) {
            core_tag_tag::set_item_tags('mod_forum', 'forum_posts', $post->id, context_module::instance($cm->id), $discussion->tags);
        }
        if (forum_tp_can_track_forums($forum) && forum_tp_is_tracked($forum)) {
            forum_tp_mark_post_read($post->userid, $post);
        }
        forum_trigger_content_uploaded_event($post, $cm, 'forum_add_discussion');
        $forum = $DB->get_record('forum', array('id' => $discussion->forum));
        $dbcontext = $DB->get_record('course_modules', array('course' => $discussion->course, 'instance' => $discussion->forum));
        $context = context_module::instance($dbcontext->id);
        $eventparams = array(
            'context' => $context,
            'objectid' => $discussion->id,
            'other' => array(
                'forumid' => $forum->id,
            )
        );
        $event = \mod_forum\event\discussion_created::create($eventparams);
        $event->add_record_snapshot('forum_discussions', $discussion);
        $event->trigger();
        return $post->discussion;
    }
    /**
     * Return definition.
     * @return external_value
     */
    public static function create_issue_returns() {
        return new external_value(PARAM_INT, 'Returns the post id of the created issue, or -1');
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

        if ($params['forumid'] == 0) {
            $params['forumid'] = get_config('block_edusupport', 'targetforum');
        }
        require_once($CFG->dirroot . '/blocks/edusupport/block_edusupport.php');
        if (block_edusupport::set_target($params['forumid'], $params)) {
            require_once($CFG->dirroot . '/blocks/edusupport/classes/issue_create_form.php');
            $params['contactphone'] = $USER->phone1;
            $form = new issue_create_form(null, null, 'post', '_self', array('id' => 'block_edusupport_create_form'), true);
            $form->set_data((object) $params);
            return $form->render();
        } else {
            return print_r($params, 1);
        }
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
        $issue = $DB->get_record('block_edusupport_issues', array('discussionid' => $discussion->id));
        if (!empty($discussion->course)) {
            $sql = "SELECT u.id,u.firstname,u.lastname,s.supportlevel
                        FROM {user} u, {block_edusupport_supporters} s
                        WHERE u.id=s.userid AND s.courseid=?
                        ORDER BY u.lastname ASC,u.firstname ASC";
            $supporters = $DB->get_records_sql($sql, array($discussion->course));
            foreach($supporters AS $supporter) {
                if (!isset($reply['supporters'][$supporter->supportlevel])) {
                    $reply['supporters'][$supporter->supportlevel] = array();
                }
                if (empty($issue->currentsupporter) && $supporter->id == $USER->id) $supporter->selected = true;
                elseif ($issue->currentsupporter == $supporter->id) $supporter->selected = true;
                $reply['supporters'][$supporter->supportlevel][] = $supporter;
            }
        }
        return json_encode($reply, JSON_NUMERIC_CHECK);
    }
    public static function get_potentialsupporters_returns() {
        return new external_value(PARAM_RAW, 'Returns a json encoded array containing potential supporters.');
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
        $discussion = $DB->get_record('forum_discussions', array('id' => $params['discussionid']));

        require_once($CFG->dirroot . '/blocks/edusupport/block_edusupport.php');
        return block_edusupport::set_current_supporter($discussion->id, $params['supporterid']);
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
        require_once($CFG->dirroot . '/blocks/edusupport/block_edusupport.php');

        $forum = $DB->get_record('forum', array('id' => $params['forumid']));
        if (empty($forum->id)) return -1;
        if ($params['asglobal']) {
            if (block_edusupport::can_config_global()) {
                set_config('targetforum', $forum->id, 'block_edusupport');
            } else {
                return -2;
            }
        } elseif ($forum->id == get_config('block_edusupport', 'targetforum')) {
            set_config('targetforum', 0, 'block_edusupport');
        }

        if (block_edusupport::can_config_course($forum->course)){
            $entry = $DB->get_record('block_edusupport', array('courseid' => $forum->course));
            if (!empty($entry->forumid)) {
                $entry->forumid = $forum->id;
                $DB->update_record('block_edusupport', $entry);
            } else {
                $entry = (object) array('courseid' => $forum->course, 'forumid' => $forum->id);
                $DB->insert_record('block_edusupport', $entry);
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
        require_once($CFG->dirroot . '/blocks/edusupport/block_edusupport.php');
        if (block_edusupport::can_config_course($params['courseid'])){
            if (empty($params['supportlevel'])) {
                $DB->delete_records('block_edusupport_supporters', array('courseid' => $params['courseid'], 'userid' => $params['userid']));
            } else {
                $entry = $DB->get_record('block_edusupport_supporters', array('courseid' => $params['courseid'], 'userid' => $params['userid']));
                if (!empty($entry->supportlevel)) {
                    $entry->supportlevel = $params['supportlevel'];
                    $DB->update_record('block_edusupport_supporters', $entry);
                } else {
                    $DB->insert_record('block_edusupport_supporters', (object) $params);
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
