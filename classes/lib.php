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
        $DB->delete_records('block_edusupport_subscr', array('discussionid' => $discussionid));

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
     * Checks for groupmode in a forum and lists available groups of this user.
     * @return array of groups.
    **/
    public static function get_groups_for_user($forumid) {
        // Store rating if we are permitted to.
        global $CFG, $DB, $USER;

        if (empty($USER->id) || isguestuser($USER)) return array();

        // Check if this coursemodule has a groupmode
        $sql = "SELECT cm.*
                    FROM {course_modules} cm, {modules} m
                    WHERE cm.module=m.id
                        AND m.name='forum'
                        AND cm.instance=?";
        $cms = array_values($DB->get_records_sql($sql, array($forumid)));
        if (empty($cms[0]->groupmode)) return array();

        $sql = "SELECT g.*
                    FROM {groups} g, {groups_members} gm
                    WHERE g.id=gm.groupid
                        AND gm.userid=?
                        AND g.courseid=?
                    ORDER BY g.name ASC";
        $groups = array_values($DB->get_records_sql($sql, array($USER->id, $cms[0]->course)));
        return $groups;
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
    public static function expose_properties($object = array()) {
        global $CFG;
        $object = (array) $object;
        $keys = array_keys($object);
        foreach ($keys AS $key) {
            $xkey = explode("\0", $key);
            $xkey = $xkey[count($xkey)-1];
            $object[$xkey] = $object[$key];
            unset($object[$key]);
            if (is_object($object[$xkey])) {
                $object[$xkey] = self::expose_properties($object[$xkey]);
            }
        }
        return $object;
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
        $DB->delete_records('block_edusupport_subscr', array('discussionid' => $discussionid));

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

        // @TODO Only subscribe 1 person and make it responsible!
        $supporters = $DB->get_records('block_edusupport_supporters', array('supportlevel' => ''));
        foreach ($supporters AS $supporter) {
            self::subscription_add($issue->discussionid, $supporter->userid);
        }

        self::create_post($issue->discussionid,
            get_string('issue_assign_nextlevel:post', 'block_edusupport', array(
                'fromuserfullname' => \fullname($USER),
                'fromuserid' => $USER->id,
                'wwwroot' => $CFG->wwwroot,
            )),
            get_string('issue_assigned:subject', 'block_edusupport')
        );

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
            return -1;
        }
        // Check if the user taking the action belongs to the supportteam.
        if (!self::is_supportteam()) {
            return -2;
        }
        // Check if the assigned user belongs to the supportteam as well.
        if (!self::is_supportteam($userid)) {
            return -3;
        }

        // Set currentsupporter and add to subscribed users.
        $DB->set_field('block_edusupport_issues', 'currentsupporter', $userid, array('discussionid' => $discussion->id));
        self::subscription_add($discussionid, $userid);

        $supporter = $DB->get_record('block_edusupport_supporters', array('userid' => $userid));
        if (empty($supporter->supportlevel)) $supporter->supportlevel = '2nd Level Support';
        $touser = $DB->get_record('user', array('id' => $userid));
        self::create_post($discussionid,
            get_string(
                ($userid == $USER->id) ? 'issue_assign_3rdlevel:postself' : 'issue_assign_3rdlevel:post',
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
        \block_edusupport\lib::supportforum_rolecheck($forumid);
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

        $supportforum = $DB->get_record('block_edusupport', array('forumid' => $forumid));
        if (empty($supportforum->id)) {
            $supportforum = (object) array(
                'courseid' => $forum->course,
                'forumid' => $forum->id,
                'archiveid' => 0,
                'dedicatedsupporter' => 0,
            );
            $supportforum->id = $DB->insert_record('block_edusupport', $supportforum);
        }

        self::supportforum_managecaps($forumid, true);
        \block_edusupport\lib::supportforum_rolecheck($forumid);
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

        $cm = \get_coursemodule_from_instance('forum', $forumid, 0, false, MUST_EXIST);
        $ctxmod = \context_module::instance($cm->id);
        $ctxcourse = \context_course::instance($forum->course);

        // Capabilities that should be PREVENTED for supportforums
        $capabilities = array('moodle/course:activityvisibility', 'moodle/course:manageactivities', 'moodle/course:delete');
        $roles = array(7,7,7);
        $contexts = array($ctxmod, $ctxmod, $ctxcourse);
        $permission = ($trigger) ? CAP_PROHIBIT : CAP_INHERIT;
        for ($a = 0; $a < count($capabilities); $a++) {
            \role_change_permission($roles[$a], $contexts[$a], $capabilities[$a], $permission);
        }
    }

    /**
     * Checks for a forum, if all supportteam-members have the required role.
     * @param forumid.
     */
    public static function supportforum_rolecheck($forumid=0) {
        global $DB;
        if (empty($forumid)) {
            // We have to re-sync all supportforums.
            $forums = $DB->get_record('block_edusupport', array());
            foreach ($forums AS $forum) {
                self::supportforum_rolecheck($forum->id);
            }
        } else {
            $forum = $DB->get_record('forum', array('id' => $forumid), '*', MUST_EXIST);
            $issupportforum = self::is_supportforum($forumid);

            $cm = \get_coursemodule_from_instance('forum', $forumid, $forum->course, false, MUST_EXIST);
            $ctx = \context_module::instance($cm->id);
            $roleid = get_config('block_edusupport', 'supportteamrole');

            // Get all users that currently have the supporter-role.
            $sql = "SELECT userid FROM {role_assignments} WHERE roleid=? AND contextid=?";
            $curmembers = array_keys($DB->get_records_sql($sql, array($roleid, $ctx->id)));
            foreach ($curmembers AS $curmember) {
                $unassign = false;
                if (!$issupportforum) $unassign = true;
                else {
                    $sql = "SELECT * FROM {block_edusupport_supporters} WHERE userid=? AND (courseid=0 OR courseid=?)";
                    $issupporter = $DB->get_record_sql($sql, array($curmember, $forum->course));
                    $unassign = empty($issupporter->id);
                }
                if ($unassign) {
                    role_unassign($roleid, $curmember, $ctx->id);
                }
            }

            if ($issupportforum) {
                // Assign all current supportteam users.
                $sql = "SELECT * FROM {block_edusupport_supporters} WHERE courseid=1 OR courseid=?";
                $members = $DB->get_records_sql($sql, array($forum->course));
                foreach ($members AS $member) {
                    role_assign($roleid, $member->userid, $ctx->id);
                }
            }
        }
    }

    /**
     * Set the dedicated supporter for a particular forum.
     * @param userid.
     **/
    public static function supportforum_setdedicatedsupporter($forumid, $userid) {
        if (!self::is_supportteam($userid)) return false;
        if (!self::is_supportforum($forumid)) return false;
        global $DB;
        $DB->set_field('block_edusupport', 'dedicatedsupporter', $userid, array('forumid' => $forumid));
        return true;
    }
}
