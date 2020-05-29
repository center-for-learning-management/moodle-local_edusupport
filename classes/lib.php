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
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_edusupport;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/adminlib.php');

class lib {
    /**
     * Add support user to the list of assigned users.
     * @param int dicussionid
     * @param int userid
     */
    public static function assignment_add($discussionid, $userid = 0) {
        global $DB, $USER;
        if (empty($userid)) $userid = $USER->id;
        if (!self::is_supportteam($userid)) return;
        $issue = self::get_issue($discussionid);
        $assignment = $DB->get_record('block_edusupport_assignments', array('discussionid' => $discussionid, 'userid' => $userid));
        if (empty($assignment->id)) {
            $assignment = (object) array(
                'issueid' => $issue->id,
                'discussionid' => $discussionid,
                'userid' => $userid,
            );
            $assignment->id = $DB->insert_record('block_edusupport_assignments', $assignment);
        }
        return $assignment;
    }
    /**
     * Remove support user from the list of assigned users.
     * @param int dicussionid
     * @param int userid
     */
    public static function assignment_remove($discussionid, $userid = 0) {
        global $DB, $USER;
        if (empty($userid)) $userid = $USER->id;
        $DB->delete_records('block_edusupport_assignments', array('discussionid' => $discussionid, 'userid' => $userid));
    }

    public static function can_config_course($courseid){
        global $USER;
        if (self::can_config_global()) return true;
        $context = context_course::instance($courseid);
        return is_enrolled($context, $USER, 'moodle/course:activityvisibility');
    }
    public static function can_config_global(){
        return self::is_admin();
    }
    /**
     * Close an issue.
     * @param discussionid.
    **/
    public static function close_issue($discussionid) {
        global $CFG, $DB, $USER;

        $discussion = $DB->get_record('forum_discussions', array('id' => $discussionid));
        $issue = self::get_issue($discussionid);
        if (!self::is_supportforum($discussion->forum)) {
            return false;
        }
        // Check if the user taking the action belongs to the supportteam.
        if (!self::is_supportteam()) {
            return false;
        }

        // 2.) create a post that we closed that issue.
        self::create_post($issue->discussionid,
            get_string(
                'issue_closed:post',
                'block_edusupport',
                array(
                    'fromuserfullname' => \fullname($USER),
                    'fromuserid' => $USER->id,
                    'wwwroot' => $CFG->wwwroot,
                )
            ),
            get_string('issue_closed:subject', 'block_edusupport')
        );

        // 3.) remove all supporters from the abo-list
        $DB->delete_records('block_edusupport_assignments', array('discussionid' => $discussionid));

        // 4.) remove issue-link from database
        $DB->delete_records('block_edusupport_issues', array('discussionid' => $discussionid));

        return true;
    }
    /**
     * Answer to the original discussion post of a discussion.
     * @param discussionid.
     * @param text as content.
     * @param subject subject for post, if not given first 30 chars of text are used.
     */
    public static function create_post($discussionid, $text, $subject = "") {
        global $DB, $USER;
        if (empty($subject)) $subject = substr($text, 0, 30);
        $discussion = $DB->get_record('forum_discussions', array('id' => $discussionid));
        $post = $DB->get_record('forum_posts', array('discussion' => $discussionid, 'parent' => 0));
        $post->parent = $post->id;
        unset($post->id);
        $post->userid = $USER->id;
        $post->created = time();
        $post->modified = time();
        $post->mailed = 0;
        $post->subject = $subject;
        $post->message = $text;
        $post->messageformat = 1;
        $post->id = $DB->insert_record('forum_posts', $post, 1);

        $forum = $DB->get_record('forum', array('id' => $discussion->forum));
        $dbcontext = $DB->get_record('course_modules', array('course' => $discussion->course, 'instance' => $discussion->forum));
        $context = \context_module::instance($dbcontext->id);
        $eventparams = array(
            'context' => $context,
            'objectid' => $post->id,
            'other' => array(
                'discussionid' => $discussion->id,
                'forumid' => $discussion->forum,
                'forumtype' => $forum->type,
            ),
        );
        $event = \mod_forum\event\post_created::create($eventparams);
        $event->add_record_snapshot('forum_posts', $post);
        $event->trigger();
    }

    /**
     * Get the issue for this discussionid.
     * @param discussionid.
     */
    public static function get_issue($discussionid) {
        global $DB;
        if (empty($discussionid)) return;
        $issue = $DB->get_record('block_edusupport_issues', array('discussionid' => $discussionid));
        if (empty($issue->id)) {
            $issue = (object) array(
                'discussionid' => $discussionid,
                'currentsupporter' => 0,
                'created' => time()
            );
            $issue->id = $DB->insert_record('block_edusupport_issues', $issue);
        }
        return $issue;
    }
    /**
     * Get potential targets of a user.
     * @param userid if empty will use current user.
     * @return array containing forums and their possible groups.
     */
    public static function get_potentialtargets($userid = 0) {
        global $DB, $USER;
        if (empty($userid)) $userid = $USER->id;
        $courseids = implode(',', array_keys(enrol_get_all_users_courses($userid)));

        $sql = "SELECT f.id,f.name,f.course
                    FROM {block_edusupport} be, {forum} f
                    WHERE f.course IN ($courseids)
                        AND be.forumid=f.id
                    ORDER BY f.name ASC";
        $forums = array_values($DB->get_records_sql($sql, array()));
        foreach ($forums AS &$forum) {
            $coursecontext = \context_course::instance($forum->course);
            $forum->postto2ndlevel = has_capability('moodle/course:update', $coursecontext);
            $forum->potentialgroups = self::get_groups_for_user($forum->id);
        }

        return $forums;
    }

    /**
     * Clones an object to reveal private fields.
     * @param object.
     * @return object.
     */
    public static function expose_properties() {
        global $CFG;
        require_once($CFG->dirroot . '/mod/forum/classes/local/entities/post.php');
        $props = array('id', 'discussionid', 'parentid', 'authorid');
        foreach ($props AS $prop) {
            $reflectionProperty = new \ReflectionProperty(\mod_forum\local\entities\post::class, $prop);
            $reflectionProperty->setAccessible(true);
        }

    }

    /**
     * Checks if a user belongs to the support team.
     */
    public static function is_supportteam($userid = 0) {
        global $DB, $USER;
        if (empty($userid)) $userid = $USER->id;
        $chk = $DB->get_record('block_edusupport_supporters', array('userid' => $userid));
        return !empty($chk->userid);
    }

    /**
     * Checks if a given forum is used as support-forum.
     * @param forumid.
     * @return true or false.
     */
    public static function is_supportforum($forumid) {
        global $DB;
        $chk = $DB->get_record('block_edusupport', array('forumid' => $forumid));
        return !empty($chk->id);
    }

    /**
     * Similar to close_issue, but can be done by a trainer in the supportforum.
     * @param discussionid.
    **/
    public static function revoke_issue($discussionid) {
        global $CFG, $DB, $USER;

        $discussion = $DB->get_record('forum_discussions', array('id' => $discussionid));
        $issue = self::get_issue($discussionid);
        if (!self::is_supportforum($discussion->forum)) {
            return false;
        }
        // Check if the user taking the action has trainer permissions.
        $coursecontext = \context_course::instance($discussion->course);
        if (!has_capability('moodle/course:update', $coursecontext)) {
            return false;
        }

        // 2.) create a post that we closed that issue.
        self::create_post($issue->discussionid,
            get_string(
                'issue_revoke:post',
                'block_edusupport',
                array(
                    'fromuserfullname' => \fullname($USER),
                    'fromuserid' => $USER->id,
                    'wwwroot' => $CFG->wwwroot,
                )
            ),
            get_string('issue_revoke:subject', 'block_edusupport')
        );

        // 3.) remove all supporters from the abo-list
        $DB->delete_records('block_edusupport_assignments', array('discussionid' => $discussionid));

        // 4.) remove issue-link from database
        $DB->delete_records('block_edusupport_issues', array('discussionid' => $discussionid));

        return true;
    }

    /**
     * Send an issue to 2nd level support.
     * @param discussionid.
     * @return true or false.
     */
    public static function set_2nd_level($discussionid) {
        global $CFG, $DB, $USER;

        $discussion = $DB->get_record('forum_discussions', array('id' => $discussionid));
        $issue = self::get_issue($discussionid);
        if (!self::is_supportforum($discussion->forum)) {
            return false;
        }

        $supporters = $DB->get_records('block_edusupport_supporters', array('supportlevel' => ''));
        foreach ($supporters AS $supporter) {
            self::assignment_add($issue->discussionid, $supporter->userid);
        }
        self::create_post($issue->discussionid,
            get_string('issue_assign_nextlevel:post', 'block_edusupport', array(
                'fromuserfullname' => \fullname($USER),
                'fromuserid' => $USER->id,
                'wwwroot' => $CFG->wwwroot,
            )),
            get_string('issue_assigned:subject', 'block_edusupport')
        );
        // @TODO We hope that this sends a message to the supportteam, although they are probably not enrolled in the course. If that does not work, we have to send by mail on our own.
        return true;
    }

    /**
     * Used by 2nd-level support to assign an issue to a particular person from 3rd level.
     * @param discussionid.
     * @param userid.
     * @return true on success.
     */
    public static function set_current_supporter($discussionid, $userid) {
        global $CFG, $DB, $USER;

        $discussion = $DB->get_record('forum_discussions', array('id' => $discussionid));
        $issue = self::get_issue($discussionid);
        if (!self::is_supportforum($discussion->forum)) {
            return false;
        }
        // Check if the user taking the action belongs to the supportteam.
        if (!self::is_supportteam()) {
            return false;
        }
        // Check if the assigned user belongs to the supportteam as well.
        if (!self::is_supportteam($userid)) {
            return false;
        }

        // Set currentsupporter and add to assigned users.
        $DB->set_field('block_edusupport_issues', 'currentsupporter', $userid, array('discussionid' => $discussion->id));
        self::assignment_add($discussionid, $userid);

        $supporter = $DB->get_record('block_edusupport_supporters', array('userid' => $userid));
        if (empty($supporter->supportlevel)) $supporter->supportlevel = '2nd Level Support';
        $touser = $DB->get_record('user', array('id' => $userid));
        self::create_post($discussionid,
            get_string(
                'issue_assign_3rdlevel:post',
                'block_edusupport',
                array(
                    'fromuserfullname' => \fullname($USER),
                    'fromuserid' => $USER->id,
                    'touserfullname' => \fullname($touser),
                    'touserid' => $userid,
                    'tosupportlevel' => $supporter->supportlevel,
                    'wwwroot ' => $CFG->wwwroot,
                )
            ),
            get_string('issue_assigned:subject', 'block_edusupport')
        );

        return true;
    }

    /**
     * Removes a forum as potential supportforum.
     * @param forumid.
     * @return true.
     */
    public static function supportforum_disable($forumid) {
        global $DB;
        $DB->delete_records('block_edusupport', array('forumid' => $forumid));
        self::supportforum_managecaps($forumid, false);
        // @TODO shall we check for orphaned discussions too?
    }

    /**
     * Sets a forum as possible support-forum.
     * @param forumid.
     * @return forum as object on success.
    **/
    public static function supportforum_enable($forumid) {
        global $DB, $USER;
        if (!is_siteadmin()) return false;
        $forum = $DB->get_record('forum', array('id' => $forumid));
        if (empty($forum->course)) return false;

        self::supportforum_managecaps($forumid, true);

        $supportforum = (object) array(
            'courseid' => $forum->course,
            'forumid' => $forum->id,
            'archiveid' => 0,
        );
        $supportforum->id = $DB->insert_record('block_edusupport', $supportforum);
        if (!empty($supportforum->id)) return $supportforum;
        else return false;
    }

    /**
     * Sets the capabilities for the context to prevent deletion.
     * @param forumid.
     * @param trigger true if we enable the forum, false if we disable it.
    **/
    public static function supportforum_managecaps($forumid, $trigger) {
        global $DB, $USER;
        if (!is_siteadmin()) return false;
        $forum = $DB->get_record('forum', array('id' => $forumid));
        if (empty($forum->course)) return false;

        $cm = \get_coursemodule_from_instance('forum', 16, 0, false, MUST_EXIST);
        $ctxmod = \context_module::instance($cm->id);
        $ctxcourse = \context_course::instance($forum->course);

        $capabilities = array('moodle/course:activityvisibility', 'moodle/course:manageactivities', 'moodle/course:delete');
        $roles = array(7,7,7);
        $contexts = array($ctxmod, $ctxmod, $ctxcourse);
        $permission = ($trigger) ? CAP_PROHIBIT : CAP_INHERIT;
        for ($a = 0; $a < count($capabilities); $a++) {
            \role_change_permission($roles[$a], $contexts[$a], $capabilities[$a], $permission);
        }
    }
}
