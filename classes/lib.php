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
    /**
     * Perform some actions before the popup is rendered.
     */
    public static function before_popup() {
        global $CFG, $DB, $USER;
        $centralforum = get_config('local_edusupport', 'centralforum');
        if (!empty($centralforum) && self::is_supportforum($centralforum)) {
            $forum = $DB->get_record('forum', array('id' => $centralforum));
            $coursectx = \context_course::instance($forum->course);
            if (!empty($coursectx->id)) {
                if (!is_enrolled($coursectx, $USER, '', true)) {
                    // Enrol as student.
                    self::course_manual_enrolments(array($forum->course), array($USER->id), 5);
                }
                require_once("$CFG->dirroot/group/lib.php");
                $groupname = fullname($USER) . ' (' . $USER->id . ')';
                $group = $DB->get_record('groups', array('courseid' => $forum->course, 'name' => $groupname));
                if (empty($group->id)) {
                    // create a group for this user.
                    $group = (object) array(
                        'courseid' => $forum->course,
                        'name' => $groupname,
                        'description' => '',
                        'descriptionformat' => 1,
                        'timecreated' => time(),
                        'timemodified' => time(),
                    );
                    $group->id = groups_create_group($group, false);
                }
                if (!empty($group->id)) {
                    groups_add_member($group, $USER);
                }
            }
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
                'local_edusupport',
                array(
                    'fromuserfullname' => \fullname($USER),
                    'fromuserid' => $USER->id,
                    'wwwroot' => $CFG->wwwroot,
                )
            ),
            get_string('issue_closed:subject', 'local_edusupport')
        );

        // 3.) remove all supporters from the abo-list
        $DB->delete_records('local_edusupport_subscr', array('discussionid' => $discussionid));

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

        $issue = $DB->get_record('local_edusupport_issues', array('discussionid' => $discussionid));
        if (!empty($issue->id)) {
            // remove all supporters from the abo-list
            $DB->delete_records('local_edusupport_subscr', array('discussionid' => $discussionid));
            // delete issue.
            $DB->delete_records('local_edusupport_issues', array('discussionid' => $discussionid));
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
        $extralinks = array();
        if (!empty($_extralinks)) {
            $_extralinks = explode("\n", $_extralinks);
            for ($a = 0; $a < count($_extralinks); $a++) {
                $tmp = explode('|', $_extralinks[$a]);
                $extralink = (object) array('id' => $a);
                if (!empty($tmp[0])) $extralink->name = $tmp[0];
                if (!empty($tmp[1])) $extralink->url = $tmp[1];
                if (!empty($tmp[2])) $extralink->faicon = $tmp[2];
                if (!empty($tmp[3])) $extralink->target = trim($tmp[3]);
                $extralinks[] = $extralink;
            }
        }

        global $OUTPUT;
        $nav = $OUTPUT->render_from_template('local_edusupport/injectbutton', array('extralinks' => $extralinks, 'hasextralinks' => count($extralinks) > 0));
        $cache->set('rendered', $nav);
        return $nav;
    }
    /**
     * Close an issue.
     * @param discussionid.
    **/
    public static function reopen_issue($discussionid) {
        global $CFG, $DB, $USER;
        $discussion = $DB->get_record('forum_discussions', array('id' => $discussionid));
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
     * Enrols users to specific courses
     * @param courseids array containing courseid or a single courseid
     * @param userids array containing userids or a single userid
     * @param roleid roleid to assign, or -1 if wants to unenrol
     * @return true or false
    **/
    public static function course_manual_enrolments($courseids, $userids, $roleid) {
        global $CFG, $DB;
        if (!is_array($courseids)) $courseids = array($courseids);
        if (!is_array($userids)) $userids = array($userids);

        // Check manual enrolment plugin instance is enabled/exist.
        $enrol = enrol_get_plugin('manual');
        if (empty($enrol)) {
            throw new \moodle_exception('manualpluginnotinstalled', 'enrol_manual');
        }
        $failures = 0;
        $instances = array();
        foreach ($courseids AS $courseid) {
            // Check if course exists.
            $course = $DB->get_record('course', array('id' => $courseid), '*', IGNORE_MISSING);
            //$course = get_course($courseid);
            if (empty($course->id)) continue;
            if (empty($instances[$courseid])) {
                $instances[$courseid] = self::get_enrol_instance($courseid);
            }

            foreach($userids AS $userid) {
                $user = $DB->get_record('user', array('id' => $userid));
                if (empty($user->id)) continue;
                if ($roleid == -1) {
                    $enrol->unenrol_user($instances[$courseid], $userid);
                } else {
                    $enrol->enrol_user($instances[$courseid], $userid, $roleid, time(), 0, ENROL_USER_ACTIVE);
                }

            }
        }
        return ($failures == 0);
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
     * Checks for groupmode in a forum and lists available groups of this user.
     * @return array of groups.
    **/
    public static function get_groups_for_user($forumid) {
        // Store rating if we are permitted to.
        global $CFG, $DB, $USER;

        if (empty($USER->id) || isguestuser($USER)) return;

        $forum = $DB->get_record('forum', array('id' => $forumid));
        $course = $DB->get_record('course', array('id' => $forum->course));

        $cm = \get_coursemodule_from_instance('forum', $forumid);

        $groupmode = \groups_get_activity_groupmode($cm);
        // If we do not use groups in this forum, return without groups.
        if (empty($groupmode)) return;

        // We do not use the function groups_get_user_groups, as it does not
        // return groups that don't have members!!
        // $_groups = \groups_get_user_groups($course->id);
        $_groups = $DB->get_records('groups', array('courseid' => $course->id));
        if (count($_groups) == 0) return;

        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $groups = array();
        foreach ($_groups AS $k => $group) {
            $ismember = $DB->get_record('groups_members', array('groupid' => $group->id, 'userid' => $USER->id));
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
        if (empty($discussionid)) return;
        $issue = $DB->get_record('local_edusupport_issues', array('discussionid' => $discussionid));
        if (empty($issue->id) && !empty($createifnotexist)) {
            $issue = (object) array(
                'discussionid' => $discussionid,
                'currentsupporter' => 0,
                'created' => time()
            );
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
        if (empty($userid)) $userid = $USER->id;

        $forums = array();
        $courseids = implode(',', array_keys(enrol_get_all_users_courses($userid)));
        if (strlen($courseids) > 0) {
            $sql = "SELECT f.id,f.name,f.course
                        FROM {local_edusupport} be, {forum} f, {course} c
                        WHERE f.course=c.id
                            AND be.forumid=f.id
                            AND c.id IN ($courseids)
                        ORDER BY c.fullname ASC, f.name ASC";
            $_forums = $DB->get_records_sql($sql, array());
            $delimiter = ' > ';
            foreach ($_forums AS &$forum) {
                $course = $DB->get_record('course', array('id' => $forum->course), 'id,fullname');
                $coursecontext = \context_course::instance($forum->course);
                if (empty($coursecontext->id)) continue;

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
        }

        return $forums;
    }

    /**
     * Get issues closed a month ago
     * @return array containing discussionids of closed and expired issues.
     */
    public static function get_expiredissues() {

        global $DB;
        $time =   get_config('local_edusupport', 'deletethreshhold');
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
        if (empty($userid)) $userid = $USER->id;
        $sql = "SELECT id,userid
                    FROM {local_edusupport_supporters}
                    WHERE userid = ?";
        $params = array($userid);

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
        $chk = $DB->get_record('local_edusupport', array('forumid' => $forumid));
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

        $discussion = $DB->get_record('forum_discussions', array('id' => $discussionid));
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
        self::create_post($issue->discussionid,
            get_string(
                'issue_revoke:post',
                'local_edusupport',
                array(
                    'fromuserfullname' => \fullname($USER),
                    'fromuserid' => $USER->id,
                    'wwwroot' => $CFG->wwwroot,
                )
            ),
            get_string('issue_revoke:subject', 'local_edusupport')
        );

        // 3.) remove all supporters from the abo-list
        $DB->delete_records('local_edusupport_subscr', array('discussionid' => $discussionid));

        // 4.) remove issue-link from database
        $DB->delete_records('local_edusupport_issues', array('discussionid' => $discussionid));

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
        $issue = self::get_issue($discussionid, true);
        if (!self::is_supportforum($discussion->forum)) {
            return false;
        }

        // @TODO Only subscribe 1 person and make it responsible!
        $supportforum = $DB->get_record('local_edusupport', array('forumid' => $discussion->forum));
        $sql = "SELECT *
                    FROM {local_edusupport_supporters}
                    WHERE supportlevel = ''
                        AND holidaymode < ?
                        AND (
                            courseid = ?
                            OR
                            courseid = ?
                        )";
        $supporters = $DB->get_records_sql($sql, array(time(), \local_edusupport\lib::SYSTEM_COURSE_ID, $discussion->course));

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
            $supporters = $DB->get_records_sql($sql, array(\local_edusupport\lib::SYSTEM_COURSE_ID, $discussion->course));
        }

        if (!empty($supportforum->dedicatedsupporter) && !empty($supporters[$supportforum->dedicatedsupporter]->id)) {
            $dedicated = $supporters[$supportforum->dedicatedsupporter];
        } else {
            // Choose one supporter randomly.
            $keys = array_keys($supporters);
            $dedicated = $supporters[$keys[array_rand($keys)]];
        }
        $DB->set_field('local_edusupport_issues', 'currentsupporter', $dedicated->userid, array('discussionid' => $discussion->id));
        self::subscription_add($discussionid, $dedicated->userid);

        self::create_post($issue->discussionid,
            get_string('issue_assign_nextlevel:post', 'local_edusupport', (object) array(
                'fromuserfullname' => \fullname($USER),
                'fromuserid' => $USER->id,
                'wwwroot' => $CFG->wwwroot,
            )),
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
        if (!self::is_supportteam($userid, $discussion->course)) {
            return -3;
        }

        // Set currentsupporter and add to subscribed users.
        $DB->set_field('local_edusupport_issues', 'currentsupporter', $userid, array('discussionid' => $discussion->id));
        self::subscription_add($discussionid, $userid);

        $supporter = $DB->get_record('local_edusupport_supporters', array('userid' => $userid));
        if (empty($supporter->supportlevel)) {
            $supporter->supportlevel = get_string('label:2ndlevel', 'local_edusupport');
        }
        $touser = $DB->get_record('user', array('id' => $userid));
        self::create_post($discussionid,
            get_string(
                ($userid == $USER->id) ? 'issue_assign_3rdlevel:postself' : 'issue_assign_3rdlevel:post',
                'local_edusupport',
                (object) array(
                    'fromuserfullname' => \fullname($USER),
                    'fromuserid' => $USER->id,
                    'touserfullname' => \fullname($touser),
                    'touserid' => $userid,
                    'tosupportlevel' => $supporter->supportlevel,
                    'wwwroot' => $CFG->wwwroot,
                )
            ),
            get_string('issue_assigned:subject', 'local_edusupport')
        );

        return true;
    }

    public static function set_prioritylvl($discussionid,$priority) {
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
        if (empty($userid)) $userid = $USER->id;
        $discussion = $DB->get_record('forum_discussions', array('id' => $discussionid));
        if (!self::is_supportteam($userid, $discussion->course)) return;
        $issue = self::get_issue($discussionid);
        $subscription = $DB->get_record('local_edusupport_subscr', array('discussionid' => $discussionid, 'userid' => $userid));
        if (empty($subscription->id)) {
            $subscription = (object) array(
                'issueid' => $issue->id,
                'discussionid' => $discussionid,
                'userid' => $userid,
            );
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
        if (empty($userid)) $userid = $USER->id;
        $DB->delete_records('local_edusupport_subscr', array('discussionid' => $discussionid, 'userid' => $userid));
    }

    /**
     * Removes a forum as potential supportforum.
     * @param forumid.
     * @return true.
     */
    public static function supportforum_disable($forumid) {
        global $DB;
        $DB->delete_records('local_edusupport', array('forumid' => $forumid));
        self::supportforum_managecaps($forumid, false);
        \local_edusupport\lib::supportforum_rolecheck($forumid);
        $centralforum = get_config('local_edusupport', 'centralforum');
        if ($forumid == $centralforum) {
            self::supportforum_disablecentral();
        }
        // @TODO shall we check for orphaned discussions too?
    }

    /**
     * Removes a forum as central support-forum.
    **/
    public static function supportforum_disablecentral() {
        if (!is_siteadmin()) return;
        set_config('centralforum', 0, 'local_edusupport');
    }


    /**
     * Sets a forum as possible support-forum.
     * @param forumid.
     * @return forum as object on success.
    **/
    public static function supportforum_enable($forumid) {
        global $DB, $USER;
        $forum = $DB->get_record('forum', array('id' => $forumid));
        if (empty($forum->course)) return false;

        $supportforum = $DB->get_record('local_edusupport', array('forumid' => $forumid));
        if (empty($supportforum->id)) {
            $course = $DB->get_record('course', array('id' => $forum->course));
            $supportforum = (object) array(
                'categoryid' => $course->category,
                'courseid' => $forum->course,
                'forumid' => $forum->id,
                'archiveid' => 0,
                'dedicatedsupporter' => 0,
            );
            $supportforum->id = $DB->insert_record('local_edusupport', $supportforum);
        }

        self::supportforum_managecaps($forumid, true);
        \local_edusupport\lib::supportforum_rolecheck($forumid);
        if (!empty($supportforum->id)) return $supportforum;
        else return false;
    }

    /**
     * Sets a forum as central support-forum.
     * @param forumid.
     * @return forum as object on success.
    **/
    public static function supportforum_enablecentral($forumid) {
        global $DB, $USER;
        if (!is_siteadmin()) return false;
        $forum = $DB->get_record('forum', array('id' => $forumid));
        if (empty($forum->course)) return false;

        $supportforum = $DB->get_record('local_edusupport', array('forumid' => $forumid));
        if (!empty($supportforum->id)) {
            set_config('centralforum', $forum->id, 'local_edusupport');
            return $forum;
        }
        return false;
    }

    /**
     * Sets the capabilities for the context to prevent deletion.
     * @param forumid.
     * @param trigger true if we enable the forum, false if we disable it.
    **/
    public static function supportforum_managecaps($forumid, $trigger) {
        global $DB, $USER;
        $forum = $DB->get_record('forum', array('id' => $forumid));
        if (empty($forum->course)) return false;

        $cm = \get_coursemodule_from_instance('forum', $forumid, 0, false, MUST_EXIST);
        $ctxmod = \context_module::instance($cm->id);
        $ctxcourse = \context_course::instance($forum->course);

        $capabilities = array(
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
        );
        $roles = array(
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
        );
        $contexts = array(
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
        );
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
            $forums = $DB->get_records('local_edusupport', array());
            foreach ($forums AS $forum) {
                self::supportforum_rolecheck($forum->forumid);
            }
        } else {
            $forum = $DB->get_record('forum', array('id' => $forumid), '*', IGNORE_MISSING);
            if (empty($forum->id)) return;
            $issupportforum = self::is_supportforum($forumid);

            $cm = \get_coursemodule_from_instance('forum', $forumid, $forum->course, false, MUST_EXIST);
            $ctx = \context_module::instance($cm->id);

            $roleid = get_config('local_edusupport', 'supportteamrole');

            // Get all users that currently have the supporter-role.
            $sql = "SELECT userid FROM {role_assignments} WHERE roleid=? AND contextid=?";
            $curmembers = array_keys($DB->get_records_sql($sql, array($roleid, $ctx->id)));
            foreach ($curmembers AS $curmember) {
                $unassign = false;
                if (!$issupportforum) $unassign = true;
                else {
                    $issupporter = self::is_supportteam($curmember,  $forum->course);
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
                $params = array(self::SYSTEM_COURSE_ID, $forum->course);
                $members = $DB->get_records_sql($sql, $params);
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
        if (!self::is_supportforum($forumid)) return false;
        global $DB;
        if ($userid == -1) {
            $DB->set_field('local_edusupport', 'dedicatedsupporter', -1, array('forumid' => $forumid));
        } else {
            if (!self::is_supportteam($userid)) return false;
            $DB->set_field('local_edusupport', 'dedicatedsupporter', $userid, array('forumid' => $forumid));
        }
        return true;
    }
}
