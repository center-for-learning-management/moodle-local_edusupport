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
 * @copyright  2020 Center for Learningmanagement (www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_edusupport;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');


class lib {
    const SYSTEM_COURSE_ID = 1;

    public static function can_config_course($courseid) {
        global $USER;
        if (self::can_config_global()) {
            return true;
        }
        $context = context_course::instance($courseid);
        return is_enrolled($context, $USER, 'moodle/course:activityvisibility');
    }

    public static function can_config_global() {
        return self::is_admin();
    }

    /**
     * Close an issue.
     * @param discussionid.
     **/
    public static function close_issue($discussionid) {
        global $CFG, $DB, $USER;

        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
        $issue = self::get_issue($discussionid);
        if (!self::is_supportforum($discussion->forum)) {
            return false;
        }
        // Check if the user taking the action belongs to the supportteam.
        if (!self::is_supportteam()) {
            return false;
        }

        // 2.) create a post that we closed that issue.
        self::create_post(
            $issue->discussionid,
            get_string(
                'issue_closed:post',
                'local_edusupport',
                [
                    'fromuserfullname' => \fullname($USER),
                    'fromuserid' => $USER->id,
                    'wwwroot' => $CFG->wwwroot,
                ]
            ),
            get_string('issue_closed:subject', 'local_edusupport')
        );

        // 3.) remove all supporters from the abo-list
        $DB->delete_records('local_edusupport_subscr', ['discussionid' => $discussionid]);

        $issue->opened = 0;
        $issue->discussionid = $discussionid;

        // 4.) remove issue-link from database
        $DB->update_record('local_edusupport_issues', $issue);
        // Mark post as closed
        $discussion->name = "[Closed] " . ltrim($discussion->name, "[Closed] ");
        $discussion->modified = time();
        $DB->update_record('forum_discussions', $discussion);

        return true;
    }

    /**
     * Delete an issue of which the discussion has been deleted.
     * @param discussionid.
     **/
    public static function delete_issue($discussionid) {
        global $CFG, $DB, $USER;

        $issue = $DB->get_record('local_edusupport_issues', ['discussionid' => $discussionid]);
        if (!empty($issue->id)) {
            // remove all supporters from the abo-list
            $DB->delete_records('local_edusupport_subscr', ['discussionid' => $discussionid]);
            // delete issue.
            $DB->delete_records('local_edusupport_issues', ['discussionid' => $discussionid]);
        }
        return true;
    }

    /**
     * Get the helpbutton menu from cache or generate it.
     */
    public static function get_supportmenu() {
        $cache = \cache::make('local_edusupport', 'supportmenu');
        if (!empty($cache->get('rendered'))) {
            return $cache->get('rendered');
        }

        $_extralinks = get_config('local_edusupport', 'extralinks');
        $extralinks = [];
        if (!empty($_extralinks)) {
            $_extralinks = explode("\n", $_extralinks);
            for ($a = 0; $a < count($_extralinks); $a++) {
                $tmp = explode('|', $_extralinks[$a]);
                $extralink = (object)['id' => $a];
                if (!empty($tmp[0])) {
                    $extralink->name = $tmp[0];
                }
                if (!empty($tmp[1])) {
                    $extralink->url = $tmp[1];
                }
                if (!empty($tmp[2])) {
                    $extralink->faicon = $tmp[2];
                }
                if (!empty($tmp[3])) {
                    $extralink->target = trim($tmp[3]);
                }
                $extralinks[] = $extralink;
            }
        }

        global $OUTPUT;
        $nav = $OUTPUT->render_from_template('local_edusupport/injectbutton', [
            'extralinks' => $extralinks,
            'hasextralinks' => count($extralinks) > 0,
            'createurl' => (new \moodle_url('/local/edusupport/create_issue.php'))->out(false),
        ]);
        $cache->set('rendered', $nav);
        return $nav;
    }

    /**
     * Close an issue.
     * @param discussionid.
     **/
    public static function reopen_issue($discussionid) {
        global $CFG, $DB, $USER;
        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
        $issue = self::get_issue($discussionid);

        $issue->opened = 1;
        $issue->discussionid = $discussionid;

        // 4.) remove issue-link from database
        $DB->update_record('local_edusupport_issues', $issue);
        // Mark post as closed
        $discussion->name = ltrim($discussion->name, "[Closed] ");
        $discussion->modified = time();
        $DB->update_record('forum_discussions', $discussion);


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
        if (empty($subject)) {
            $subject = substr($text, 0, 30);
        }
        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
        $post = $DB->get_record('forum_posts', ['discussion' => $discussionid, 'parent' => 0]);
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

        $forum = $DB->get_record('forum', ['id' => $discussion->forum]);
        $dbcontext = $DB->get_record('course_modules', ['course' => $discussion->course, 'instance' => $discussion->forum]);
        $context = \context_module::instance($dbcontext->id);
        $eventparams = [
            'context' => $context,
            'objectid' => $post->id,
            'other' => [
                'discussionid' => $discussion->id,
                'forumid' => $discussion->forum,
                'forumtype' => $forum->type,
            ],
        ];
        $event = \mod_forum\event\post_created::create($eventparams);
        $event->add_record_snapshot('forum_posts', $post);
        $event->trigger();
    }

    /**
     * Clones an object to reveal private fields.
     * @param object.
     * @return object.
     */
    public static function expose_properties($object = []) {
        global $CFG;
        $object = (array)$object;
        $keys = array_keys($object);
        foreach ($keys as $key) {
            $xkey = explode("\0", $key);
            $xkey = $xkey[count($xkey) - 1];
            $object[$xkey] = $object[$key];
            unset($object[$key]);
            if (is_object($object[$xkey])) {
                $object[$xkey] = self::expose_properties($object[$xkey]);
            }
        }
        return $object;
    }


    /**
     * Checks for groupmode in a forum and lists available groups of this user.
     * @return array of groups.
     **/
    public static function get_groups_for_user($forumid) {
        // Store rating if we are permitted to.
        global $CFG, $DB, $USER;

        if (empty($USER->id) || isguestuser($USER)) {
            return;
        }

        $forum = $DB->get_record('forum', ['id' => $forumid]);
        $course = $DB->get_record('course', ['id' => $forum->course]);

        $cm = \get_coursemodule_from_instance('forum', $forumid);

        $groupmode = \groups_get_activity_groupmode($cm);
        // If we do not use groups in this forum, return without groups.
        if (empty($groupmode)) {
            return;
        }

        // We do not use the function groups_get_user_groups, as it does not
        // return groups that don't have members!!
        // $_groups = \groups_get_user_groups($course->id);
        $_groups = $DB->get_records('groups', ['courseid' => $course->id]);
        if (count($_groups) == 0) {
            return;
        }

        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $groups = [];
        foreach ($_groups as $k => $group) {
            $ismember = $DB->get_record('groups_members', ['groupid' => $group->id, 'userid' => $USER->id]);
            if (!empty($ismember->id)) {
                $groups[$k] = $group;
            }
        }

        return $groups;
    }

    /**
     * Get the issue for this discussionid.
     * @param discussionid.
     * @param createifnotexist (optional).
     */
    public static function get_issue($discussionid, $createifnotexist = false) {
        global $DB;
        if (empty($discussionid)) {
            return;
        }
        $issue = $DB->get_record('local_edusupport_issues', ['discussionid' => $discussionid]);
        if (empty($issue->id) && !empty($createifnotexist)) {
            $issue = (object)[
                'discussionid' => $discussionid,
                'currentsupporter' => 0,
                'created' => time(),
            ];
            $issue->id = $DB->insert_record('local_edusupport_issues', $issue);
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
        if (empty($userid)) {
            $userid = $USER->id;
        }

        $courseids = array_keys(enrol_get_all_users_courses($userid));

        $forums = [];
        if (!$courseids) {
            return $forums;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($courseids);
        $sql = "SELECT f.id,f.name,f.course
                    FROM {local_edusupport} be, {forum} f, {course} c
                    WHERE f.course=c.id
                        AND be.forumid=f.id
                        AND c.id $insql
                    ORDER BY c.fullname ASC, f.name ASC";
        $_forums = $DB->get_records_sql($sql, $inparams);
        $delimiter = ' > ';
        foreach ($_forums as &$forum) {
            $course = $DB->get_record('course', ['id' => $forum->course], 'id,fullname');
            $coursecontext = \context_course::instance($forum->course);
            if (empty($coursecontext->id)) {
                continue;
            }

            $fcm = get_coursemodule_from_instance('forum', $forum->id, 0, false, MUST_EXIST);
            $fctx = \context_module::instance($fcm->id);
            $modinfo = get_fast_modinfo($course);
            $cm = $modinfo->get_cm($fcm->id);

            if ($cm->uservisible && has_capability('mod/forum:startdiscussion', $fctx)) {
                $forum->name = $course->fullname . $delimiter . $forum->name;
                $forum->postto2ndlevel = has_capability('local/edusupport:canforward2ndlevel', $coursecontext);
                $forum->potentialgroups = self::get_groups_for_user($forum->id);
                $forums[$forum->id] = $forum;
            }
        }

        return $forums;
    }

    /**
     * Get issues closed a month ago
     * @return array containing discussionids of closed and expired issues.
     */
    public static function get_expiredissues() {

        global $DB;
        $time = get_config('local_edusupport', 'deletethreshhold');
        $expirationtime = time() - $time;
        if (!$time || $time == 0) {
            $expirationtime = 0;
        }

        $sql = "SELECT edu.id, edu.discussionid, edu.opened, f.id, f.timemodified
                FROM {local_edusupport_issues} edu
                JOIN {forum_discussions} f
                    ON edu.discussionid = f.id
                WHERE edu.opened = 0
                AND f.timemodified < {$expirationtime}";
        $records = $DB->get_records_sql($sql);
        return $records;
    }


    /**
     * Checks if a user belongs to the support team.
     * @param userid check particular user, or current user
     * @param course check for particular course
     * @param includeglobalteam if checking for particular course, also include global team.
     */
    public static function is_supportteam($userid = 0, $courseid = 0, $includeglobalteam = true) {
        global $DB, $USER;
        if (empty($userid)) {
            $userid = $USER->id;
        }
        $sql = "SELECT id,userid
                    FROM {local_edusupport_supporters}
                    WHERE userid = ?";
        $params = [$userid];

        if ($courseid > 0 && !$includeglobalteam) {
            $sql .= " AND courseid = ?";
            $params[] = $courseid;
        } elseif ($courseid > 0 && $includeglobalteam) {
            $sql .= " AND (courseid = ? OR courseid = ?)";
            $params[] = $courseid;
            $params[] = self::SYSTEM_COURSE_ID;
        } else {
            $sql .= " AND courseid = ?";
            $params[] = self::SYSTEM_COURSE_ID;
        }

        $sql .= " LIMIT 1 OFFSET 0";

        $chk = $DB->get_record_sql($sql, $params);
        return !empty($chk->userid);
    }

    /**
     * Checks if a given forum is used as support-forum.
     * @param forumid.
     * @return true or false.
     */
    public static function is_supportforum($forumid) {
        global $DB;
        $chk = $DB->get_record('local_edusupport', ['forumid' => $forumid]);
        return !empty($chk->id);
    }

    /**
     * Get all supporters for a certain course (the trainers).
     * @param object forum
     */
    public static function get_course_supporters($forum) {
        $ctx = \context_course::instance($forum->course);
        return \get_users_by_capability($ctx, 'moodle/course:update');
    }

    /**
     * Get the enrol instance for manual enrolments of a course, or create one.
     * @param courseid
     * @return object enrolinstance
     */
    private static function get_enrol_instance($courseid) {
        // Check manual enrolment plugin instance is enabled/exist.
        $enrol = enrol_get_plugin('manual');
        if (empty($enrol)) {
            throw new \moodle_exception('manualpluginnotinstalled', 'enrol_manual');
        }
        $instance = null;
        $enrolinstances = enrol_get_instances($courseid, false);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                return $courseenrolinstance;
            }
        }
        if (empty($instance)) {
            $course = get_course($courseid);
            $enrol->add_default_instance($course);
            return self::get_enrol_instance($courseid);
        }
    }

    /**
     * Similar to close_issue, but can be done by a trainer in the supportforum.
     * @param discussionid.
     **/
    public static function revoke_issue($discussionid) {
        global $CFG, $DB, $USER;

        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
        $issue = self::get_issue($discussionid);
        if (!self::is_supportforum($discussion->forum)) {
            return false;
        }
        // Check if the user taking the action has trainer permissions.
        $coursecontext = \context_course::instance($discussion->course);
        if (!has_capability('local/edusupport:canforward2ndlevel', $coursecontext)) {
            return false;
        }

        // 2.) create a post that we closed that issue.
        self::create_post(
            $issue->discussionid,
            get_string(
                'issue_revoke:post',
                'local_edusupport',
                [
                    'fromuserfullname' => \fullname($USER),
                    'fromuserid' => $USER->id,
                    'wwwroot' => $CFG->wwwroot,
                ]
            ),
            get_string('issue_revoke:subject', 'local_edusupport')
        );

        // 3.) remove all supporters from the abo-list
        $DB->delete_records('local_edusupport_subscr', ['discussionid' => $discussionid]);

        // 4.) remove issue-link from database
        $DB->delete_records('local_edusupport_issues', ['discussionid' => $discussionid]);

        return true;
    }

    /**
     * Send an issue to 2nd level support.
     * @param discussionid.
     * @return true or false.
     */
    public static function set_2nd_level($discussionid) {
        global $CFG, $DB, $USER;

        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
        $issue = self::get_issue($discussionid, true);
        if (!self::is_supportforum($discussion->forum)) {
            return false;
        }

        // @TODO Only subscribe 1 person and make it responsible!
        $supportforum = $DB->get_record('local_edusupport', ['forumid' => $discussion->forum]);
        $sql = "SELECT *
                    FROM {local_edusupport_supporters}
                    WHERE supportlevel = ''
                        AND holidaymode < ?
                        AND (
                            courseid = ?
                            OR
                            courseid = ?
                        )";
        $supporters = $DB->get_records_sql($sql, [time(), self::SYSTEM_COURSE_ID, $discussion->course]);

        if (count($supporters) == 0) {
            // Fall back without holidaymode.
            $sql = "SELECT *
                        FROM {local_edusupport_supporters}
                        WHERE supportlevel = ''
                            AND (
                                courseid = ?
                                OR
                                courseid = ?
                            )";
            $supporters = $DB->get_records_sql($sql, [self::SYSTEM_COURSE_ID, $discussion->course]);
        }

        if (!empty($supportforum->dedicatedsupporter) && !empty($supporters[$supportforum->dedicatedsupporter]->id)) {
            $dedicated = $supporters[$supportforum->dedicatedsupporter];
        } else {
            // Choose one supporter randomly.
            $keys = array_keys($supporters);
            $dedicated = $supporters[$keys[array_rand($keys)]];
        }
        $DB->set_field('local_edusupport_issues', 'currentsupporter', $dedicated->userid, ['discussionid' => $discussion->id]);
        self::subscription_add($discussionid, $dedicated->userid);

        self::create_post(
            $issue->discussionid,
            get_string('issue_assign_nextlevel:post', 'local_edusupport', (object)[
                'fromuserfullname' => \fullname($USER),
                'fromuserid' => $USER->id,
                'wwwroot' => $CFG->wwwroot,
            ]),
            get_string('issue_assigned:subject', 'local_edusupport')
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

        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
        $issue = self::get_issue($discussionid);
        if (!self::is_supportforum($discussion->forum)) {
            return -1;
        }
        // Check if the user taking the action belongs to the supportteam.
        if (!self::is_supportteam()) {
            return -2;
        }
        // Check if the assigned user belongs to the supportteam as well.
        if (!self::is_supportteam($userid, $discussion->course)) {
            return -3;
        }

        // Set currentsupporter and add to subscribed users.
        $DB->set_field('local_edusupport_issues', 'currentsupporter', $userid, ['discussionid' => $discussion->id]);
        self::subscription_add($discussionid, $userid);

        $supporter = $DB->get_record('local_edusupport_supporters', ['userid' => $userid]);
        if (empty($supporter->supportlevel)) {
            $supporter->supportlevel = get_string('label:2ndlevel', 'local_edusupport');
        }
        $touser = $DB->get_record('user', ['id' => $userid]);
        self::create_post(
            $discussionid,
            get_string(
                ($userid == $USER->id) ? 'issue_assign_3rdlevel:postself' : 'issue_assign_3rdlevel:post',
                'local_edusupport',
                (object)[
                    'fromuserfullname' => \fullname($USER),
                    'fromuserid' => $USER->id,
                    'touserfullname' => \fullname($touser),
                    'touserid' => $userid,
                    'tosupportlevel' => $supporter->supportlevel,
                    'wwwroot' => $CFG->wwwroot,
                ]
            ),
            get_string('issue_assigned:subject', 'local_edusupport')
        );

        return true;
    }

    public static function set_prioritylvl($discussionid, $priority) {
        global $DB;

        $issue = self::get_issue($discussionid);
        $issue->opened = $priority;
        $issue->discussionid = $discussionid;

        $DB->update_record('local_edusupport_issues', $issue);
        return true;
    }


    /**
     * Add support user to the list of assigned users.
     * @param int dicussionid
     * @param int userid
     */
    public static function subscription_add($discussionid, $userid = 0) {
        global $DB, $USER;
        if (empty($userid)) {
            $userid = $USER->id;
        }
        $discussion = $DB->get_record('forum_discussions', ['id' => $discussionid]);
        if (!self::is_supportteam($userid, $discussion->course)) {
            return;
        }
        $issue = self::get_issue($discussionid);
        $subscription = $DB->get_record('local_edusupport_subscr', ['discussionid' => $discussionid, 'userid' => $userid]);
        if (empty($subscription->id)) {
            $subscription = (object)[
                'issueid' => $issue->id,
                'discussionid' => $discussionid,
                'userid' => $userid,
            ];
            $subscription->id = $DB->insert_record('local_edusupport_subscr', $subscription);
        }
        return $subscription;
    }

    /**
     * Remove support user from the list of assigned users.
     * @param int dicussionid
     * @param int userid
     */
    public static function subscription_remove($discussionid, $userid = 0) {
        global $DB, $USER;
        if (empty($userid)) {
            $userid = $USER->id;
        }
        $DB->delete_records('local_edusupport_subscr', ['discussionid' => $discussionid, 'userid' => $userid]);
    }

    /**
     * Removes a forum as potential supportforum.
     * @param forumid.
     * @return true.
     */
    public static function supportforum_disable($forumid) {
        global $DB;
        $DB->delete_records('local_edusupport', ['forumid' => $forumid]);
        self::supportforum_managecaps($forumid, false);
        self::supportforum_rolecheck($forumid);
        // @TODO shall we check for orphaned discussions too?
    }

    /**
     * Sets a forum as possible support-forum.
     * @param forumid.
     * @return forum as object on success.
     **/
    public static function supportforum_enable($forumid) {
        global $DB, $USER;
        $forum = $DB->get_record('forum', ['id' => $forumid]);
        if (empty($forum->course)) {
            return false;
        }

        $supportforum = $DB->get_record('local_edusupport', ['forumid' => $forumid]);
        if (empty($supportforum->id)) {
            $course = $DB->get_record('course', ['id' => $forum->course]);
            $supportforum = (object)[
                'categoryid' => $course->category,
                'courseid' => $forum->course,
                'forumid' => $forum->id,
                'archiveid' => 0,
                'dedicatedsupporter' => 0,
            ];
            $supportforum->id = $DB->insert_record('local_edusupport', $supportforum);
        }

        self::supportforum_managecaps($forumid, true);
        self::supportforum_rolecheck($forumid);
        if (!empty($supportforum->id)) {
            return $supportforum;
        } else { return false;
        }
    }

    /**
     * Sets the capabilities for the context to prevent deletion.
     * @param forumid.
     * @param trigger true if we enable the forum, false if we disable it.
     **/
    public static function supportforum_managecaps($forumid, $trigger) {
        global $DB, $USER;
        $forum = $DB->get_record('forum', ['id' => $forumid]);
        if (empty($forum->course)) {
            return false;
        }

        $cm = \get_coursemodule_from_instance('forum', $forumid, 0, false, MUST_EXIST);
        $ctxmod = \context_module::instance($cm->id);
        $ctxcourse = \context_course::instance($forum->course);

        $capabilities = [
            'moodle/course:activityvisibility',
            'moodle/course:changecategory',
            'moodle/course:changefullname',
            'moodle/course:changeidnumber',
            'moodle/course:changeshortname',
            'moodle/course:enrolconfig',
            'moodle/course:manageactivities',
            'moodle/course:delete',
            'moodle/course:reset',
            'moodle/course:visibility',
            'moodle/restore:configure',
            'moodle/restore:restorecourse',
            'moodle/restore:restoresection',
            'moodle/restore:viewautomatedfilearea',
        ];
        $roles = [
            7,
            7,
            7,
            7,
            7,
            7,
            7,
            7,
            7,
            7,
            7,
            7,
            7,
            7,
        ];
        $contexts = [
            $ctxmod,
            $ctxcourse,
            $ctxcourse,
            $ctxcourse,
            $ctxcourse,
            $ctxcourse,
            $ctxmod,
            $ctxcourse,
            $ctxcourse,
            $ctxcourse,
            $ctxcourse,
            $ctxcourse,
            $ctxcourse,
            $ctxcourse,
        ];
        $permission = ($trigger) ? CAP_PROHIBIT : CAP_INHERIT;
        for ($a = 0; $a < count($capabilities); $a++) {
            \role_change_permission($roles[$a], $contexts[$a], $capabilities[$a], $permission);
        }
    }

    /**
     * Checks for a forum, if all supportteam-members have the required role.
     * @param forumid.
     */
    public static function supportforum_rolecheck($forumid = 0) {
        global $DB;
        if (empty($forumid)) {
            // We have to re-sync all supportforums.
            $forums = $DB->get_records('local_edusupport', []);
            foreach ($forums as $forum) {
                self::supportforum_rolecheck($forum->forumid);
            }
        } else {
            $forum = $DB->get_record('forum', ['id' => $forumid], '*', IGNORE_MISSING);
            if (empty($forum->id)) {
                return;
            }
            $issupportforum = self::is_supportforum($forumid);

            $cm = \get_coursemodule_from_instance('forum', $forumid, $forum->course, false, MUST_EXIST);
            $ctx = \context_module::instance($cm->id);

            $roleid = get_config('local_edusupport', 'supportteamrole');

            // Get all users that currently have the supporter-role.
            $sql = "SELECT userid FROM {role_assignments} WHERE roleid=? AND contextid=?";
            $curmembers = array_keys($DB->get_records_sql($sql, [$roleid, $ctx->id]));
            foreach ($curmembers as $curmember) {
                $unassign = false;
                if (!$issupportforum) {
                    $unassign = true;
                } else {
                    $issupporter = self::is_supportteam($curmember, $forum->course);
                    $unassign = empty($issupporter->id);
                }
                if ($unassign) {
                    role_unassign($roleid, $curmember, $ctx->id);
                }
            }

            if ($issupportforum) {
                // Assign all current supportteam users.
                $sql = "SELECT *
                            FROM {local_edusupport_supporters}
                            WHERE courseid=? OR courseid=?";
                $params = [self::SYSTEM_COURSE_ID, $forum->course];
                $members = $DB->get_records_sql($sql, $params);
                foreach ($members as $member) {
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
        if (!self::is_supportforum($forumid)) {
            return false;
        }
        global $DB;
        if ($userid == -1) {
            $DB->set_field('local_edusupport', 'dedicatedsupporter', -1, ['forumid' => $forumid]);
        } else {
            if (!self::is_supportteam($userid)) {
                return false;
            }
            $DB->set_field('local_edusupport', 'dedicatedsupporter', $userid, ['forumid' => $forumid]);
        }
        return true;
    }

    /**
     * Reduces a url to an absolute url of this Moodle instance, everything else becomes empty.
     *
     * PARAM_LOCALURL alone also lets relative urls pass. Those are shown to the supporters as a
     * link within a forum post, where they would resolve against the wrong base.
     */
    public static function clean_local_url(string $url): string {
        global $CFG;

        $url = clean_param($url, PARAM_LOCALURL);
        if (strpos($url, $CFG->wwwroot) !== 0) {
            return '';
        }

        // The browser resolves ../ before it sends the request, so a url can leave the Moodle
        // installation even though it starts with the wwwroot.
        $path = parse_url($url, PHP_URL_PATH);
        if ($path !== null && strpos($path, '..') !== false) {
            return '';
        }

        return $url;
    }

    /**
     * Creates an issue from the data submitted by \create_issue_form.
     *
     * @return the discussionid of the created issue, or -999 if it was sent by mail instead.
     */
    public static function create_issue(object $data): int {
        global $CFG, $DB, $OUTPUT, $SITE, $USER;

        $protecttime = get_config('local_edusupport', 'spamprotectionthreshold');
        $protectamount = get_config('local_edusupport', 'spamprotectionlimit');

        $cache = \cache::make('local_edusupport', 'spamprotect');
        $timeoffset = time() - $protecttime;
        // The cache returns false as long as nothing has been stored for this session.
        $log = $cache->get('log') ?: [];
        if ($log) {
            for ($a = 0; $a < count($log); $a++) {
                if ($log[$a] < $timeoffset) {
                    $log[$a] = '';
                }
            }
            $log = array_values(array_filter($log));
            $cache->set('log', $log);
            if ($protectamount <= count($log)) {
                throw new \moodle_exception('spamprotection:exception', 'local_edusupport');
            }
        }
        $log[] = time();
        $cache->set('log', $log);

        $templatedata = [
            // The template prints the description unescaped, so we escape it here and only
            // let the line breaks through as html.
            'description' => nl2br(s($data->description)),
            'contactphone' => $data->contactphone,
            'url' => static::clean_local_url($data->url),
        ];
        if (get_config('local_edusupport', 'trackhost')) {
            $templatedata['webhost'] = gethostname();
        }

        // The file has already been checked by the form, it was scanned for viruses on upload.
        $screenshot = null;
        if ($data->screenshot) {
            $usercontext = \context_user::instance($USER->id);
            $draftfiles = get_file_storage()->get_area_files(
                $usercontext->id, 'user', 'draft',
                $data->screenshot, 'id DESC', false
            );
            $screenshot = reset($draftfiles);
        }

        $tmp = explode('_', $data->forum_group);
        $forumid = 0;
        $groupid = 0;
        if (count($tmp) == 2) {
            $forumid = $tmp[0];
            $groupid = $tmp[1];
        }

        if ($data->forum_group == 'mail' || !$forumid) {
            // Fallback, there is no supportforum available for this user.
            $templatedata['includeemail'] = $USER->email;
            $messagehtml = $OUTPUT->render_from_template('local_edusupport/issue_template', $templatedata);
            $messagetext = html_to_text($messagehtml);
            $supportuser = \core_user::get_support_user();

            if ($screenshot) {
                // email_to_user() needs the attachment as a file on disk.
                $filepath = $CFG->tempdir . '/edusupport-' . md5($USER->id . microtime());
                $screenshot->copy_content_to($filepath);
                email_to_user(
                    $supportuser, $USER, $data->subject, $messagetext, $messagehtml,
                    $filepath, $screenshot->get_filename()
                );
                unlink($filepath);
            } else {
                email_to_user($supportuser, $USER, $data->subject, $messagetext, $messagehtml, '', true);
            }
            return -999;
        }

        $potentialtargets = static::get_potentialtargets();
        if (!static::is_supportforum($forumid) || empty($potentialtargets[$forumid]->id)) {
            throw new \moodle_exception('couldnotadd', 'forum');
        }

        // Mainly copied from mod/forum/externallib.php > add_discussion().
        $forum = $DB->get_record('forum', ['id' => $forumid], '*', MUST_EXIST);
        [$course, $cm] = get_course_and_cm_from_instance($forum, 'forum');
        $context = \context_module::instance($cm->id);

        if (!groups_get_activity_groupmode($cm)) {
            $groupid = -1;
        } elseif (!$groupid) {
            $groupid = groups_get_activity_group($cm);
        }

        if (!forum_user_can_post_discussion($forum, $groupid, -1, $cm, $context)) {
            throw new \moodle_exception('cannotcreatediscussion', 'forum');
        }

        forum_check_blocking_threshold(forum_check_throttling($forum, $cm));

        $discussion = new \stdClass();
        $discussion->course = $course->id;
        $discussion->forum = $forum->id;
        $discussion->message = $OUTPUT->render_from_template('local_edusupport/issue_template', $templatedata);
        $discussion->messageformat = FORMAT_HTML;   // Force formatting for now.
        $discussion->messagetrust = trusttext_trusted($context);
        $discussion->itemid = 0;
        $discussion->groupid = $groupid;
        $discussion->mailnow = 1;
        $discussion->subject = $data->subject;
        $discussion->name = $discussion->subject;
        $discussion->timestart = 0;
        $discussion->timeend = 0;
        $discussion->timelocked = 0;
        $discussion->attachment = 0;
        $discussion->pinned = FORUM_DISCUSSION_UNPINNED;

        $discussionid = forum_add_discussion($discussion);
        if (!$discussionid) {
            throw new \moodle_exception('couldnotadd', 'forum');
        }
        $discussion->id = $discussionid;

        if ($screenshot) {
            $fr = (object)[
                'component' => 'mod_forum',
                'contextid' => $context->id,
                'userid' => $USER->id,
                'filearea' => 'attachment',
                'filename' => $screenshot->get_filename(),
                'filepath' => '/',
                'itemid' => $discussion->firstpost,
                'license' => $CFG->sitedefaultlicense,
                'author' => fullname($USER),
            ];
            $fr->source = serialize((object)['source' => $fr->filename]);
            get_file_storage()->create_file_from_storedfile($fr, $screenshot);
            $DB->set_field('forum_posts', 'attachment', 1, ['id' => $discussion->firstpost]);
        }

        $event = \mod_forum\event\discussion_created::create([
            'context' => $context,
            'objectid' => $discussion->id,
            'other' => ['forumid' => $forum->id],
        ]);
        $event->add_record_snapshot('forum_discussions', $discussion);
        $event->trigger();

        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm) && ($forum->completiondiscussions || $forum->completionposts)) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }

        $settings = new \stdClass();
        $settings->discussionsubscribe = true;
        forum_post_subscription($settings, $forum, $discussion);

        // An unchecked checkbox is not submitted at all, so the property may be missing.
        if ($potentialtargets[$forumid]->postto2ndlevel && ($data->postto2ndlevel ?? 0)) {
            static::set_2nd_level($discussion->id);
        } else {
            // Post answer containing the responsibles.
            $responsibles = [];
            foreach (array_values(static::get_course_supporters($forum)) as $manager) {
                $responsibles[] = "<a href=\"{$CFG->wwwroot}/user/profile.php?id={$manager->id}\" target=\"_blank\">{$manager->firstname} {$manager->lastname}</a>";
            }
            static::create_post(
                $discussion->id,
                get_string('issue_responsibles:post', 'local_edusupport', [
                    'responsibles' => implode(', ', $responsibles),
                    'sitename' => $SITE->fullname,
                ]),
                get_string('issue_responsibles:subject', 'local_edusupport')
            );
        }

        return $discussionid;
    }
}
