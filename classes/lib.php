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
    public static function assign_role($contextid, $assign) {
        global $DB, $USER;

        if (!empty($assign) && $assign) {
            \role_assign(5, $USER->id, $contextid);
        } else {
            \role_unassign(5, $USER->id, $contextid);
            // Reset cardinality of auto increment.
            $sql = "SELECT 1,MAX(id) AS id
                        FROM {role_assignments}";
            $max = $DB->get_records_sql($sql, array());
            $sql = "ALTER TABLE {role_assignments}
                        AUTO_INCREMENT=" . ($max[1]->id+1);
            $DB->execute($sql, array());
        }
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

        // 1.) Check if we are a supporter

        // 2.) create a post that we closed that issue.

        // 3.) remove all supporters from the abo-list

        // 4.) remove issue-link from database
    }
    /**
     * Answer to the original discussion post of a discussion.
     * @param discussionid.
     * @param text as content.
     * @param subject subject for post, if not given first 30 chars of text are used.
     */
    public static function create_post($discussionid, $text, $subject = "") {
        global $DB;
        if (empty($subject)) $subject = substr($text, 0, 30);
        $discussion = $DB->get_record('forum_discussions', array('id' => $discussinon->id));
        $post = $DB->get_record('forum_posts', array('discussion' => $discussion->id, 'parent' => 0));
        $post->parent = $post->id;
        unset($post->id);
        $post->userid = $USER->id;
        $post->created = time();
        $post->modified = time();
        $post->mailed = 0;
        $post->subject = $subject;
        $post->message = $message;
        $post->messageformat = 1;
        $post->id = $DB->insert_record('forum_posts', $post, 1);

        $forum = $DB->get_record('forum', array('id' => $discussion->forum));
        $dbcontext = $DB->get_record('course_modules', array('course' => $discussion->course, 'instance' => $discussion->forum));
        $context = context_module::instance($dbcontext->id);
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
            $forum->potentialgroups = self::get_groups_for_user($forum->id);
        }

        return $forums;
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
        return true;
    }

    /**
     * Send an issue to 2nd level support.
     * @param discussionid.
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
            $chk = $DB->get_record('forum_discussion_subs', array('discussion' => $issue->discussionid, 'userid' => $supporter->userid));
            if (empty($chk->id)) {
                $sub = (object) array(
                    'forum' => $discussion->forum,
                    'userid' => $supporter->userid,
                    'discussion' => $issue->discussionid,
                    'preference' => time(),
                );
                $DB->insert_record('forum_discussion_subs', $sub);
            }
            $chk = $DB->get_record('block_edusupport_assignments', array('discussionid' => $issue->discussionid, 'userid' => $supporter->userid));
            if (empty($chk->id)) {
                $assignment = (object) array(
                    'issueid' => $issue->id,
                    'discussionid' => $issue->discussionid,
                    'userid' => $supporter->userid,
                );
                $DB->insert_record('block_edusupport_assignments', $assignment);
            }
            self::create_post($issue->discussionid, get_string('issue_assign_nextlevel:post', 'block_edusupport', array('userfullname' => userfullname($USER))));
            // @TODO We hope that this sends a message to the supportteam, although they are probably not enrolle in the course. If that does not work, we have to send by mail on our own.
        }
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
        // @TODO create an automated posting.

        // @TODO add the designated userid to the list of supporters of this issue.
        $issue->currentsupporter = $userid;
        $DB->update_record('block_edusupport_issues', $issue);
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

        $supportforum = (object) array(
            'courseid' => $forum->course,
            'forumid' => $forum->id,
            'archiveid' => 0,
        );

        $supportforum->id = $DB->insert_record('block_edusupport', $supportforum);
        if (!empty($supportforum->id)) return $supportforum;
        else return false;
    }
}
