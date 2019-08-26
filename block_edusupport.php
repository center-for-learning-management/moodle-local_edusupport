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

require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/blocks/moodleblock.class.php');

class block_edusupport extends block_base {
    public static $STYLE_UNASSIGNED = 'font-weight: bold; background-color: rgba(255, 0, 0, 0.3);';
    public static $STYLE_OPENED = 'background-color: rgba(255,246,143, 0.6)';
    public static $STYLE_CLOSED = 'background-color: rgba(0, 255, 0, 0.3)';

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
     * Returns the supportlevel of a user within a course.
    **/
    public static function get_supporter_level($courseid, $userid) {
        global $DB;
        $entry = $DB->get_record('block_edusupport_supporters', array('courseid' => $courseid, 'userid' => $userid));
        if (!empty($entry->supportlevel)) {
            return $entry->supportlevel;
        } else {
            return '';
        }
    }
    /**
     * Checks if the configured targetforum is a forum.
     * @return returns the forumid of the forum, -2 if it is invalid or -1 if it is not set.
    **/
    public static function get_target() {
        global $COURSE, $DB;
        if (isset($COURSE->id) && $COURSE->id > 1) {
            $entry = $DB->get_record('block_edusupport', array('courseid' => $COURSE->id));
            if (!empty($entry->forumid)) {
                return $entry->forumid;
            }
        }

        $targetforum = get_config('block_edusupport', 'targetforum');
        if (!empty($targetforum)) {
            // Check if this targetforum exists.
            $sql = "SELECT cm.id
                        FROM {course_modules} cm, {modules} m
                        WHERE cm.instance=?
                            AND cm.module=m.id
                            AND m.name='forum'";
            $chks = $DB->get_records_sql($sql, array($targetforum));
            foreach($chks AS $chk) { return $targetforum; }
            return -2;
        } else {
            return -1;
        }
    }
    /**
     * Checks for groupmode in targetforum and lists available groups of this user.
     * @return array of groups.
    **/
    public static function get_groups() {
        // Store rating if we are permitted to.
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/blocks/edusupport/block_edusupport.php');
        $targetforum = block_edusupport::get_target();
        if ($targetforum < 0) return -1;

        // Check if this coursemodule has a groupmode
        $sql = "SELECT cm.*
                    FROM {course_modules} cm, {modules} m
                    WHERE cm.module=m.id
                        AND m.name='forum'
                        AND cm.instance=?";
        $cms = $DB->get_records_sql($sql, array($targetforum));
        foreach($cms AS $cm) {
            if ($cm->groupmode == 0) {
                $cm->groupmode = 1;
                $DB->update_record('course_modules', $cm);
            }
            break;
        }
        if (empty($cm->id)) return -1;
        if (empty($USER->id) || !isguestuser($USER)) return -1;

        block_edusupport::course_manual_enrolments(array($cm->course), array($USER->id), 5); // Enrol as student

        $autocreate_usergroup = get_config('block_edusupport', 'autocreate_usergroup');
        if ($autocreate_usergroup) {
            // Create a group for this user himself.
            require_once($CFG->dirroot . '/group/lib.php');
            $group = groups_get_group_by_idnumber($cm->course, 'user-' . $USER->id);
            if (empty($group->id)) {
                $group = (object) array('courseid' => $cm->course, 'idnumber' => 'user-' . $USER->id, 'name' => '#' . $USER->id . ', ' . $USER->firstname . ' ' . $USER->lastname . ' (' . get_string('only_you', 'block_edusupport') . ')', 'timecreated' => time(), 'timemodified' => time());
                $group->id = groups_create_group($group, false);
            } else {
                // Ensure group has the correct name.
                $group->name = '#' . $USER->id . ', ' . $USER->firstname . ' ' . $USER->lastname . ' (' . get_string('only_you', 'block_edusupport') . ')';
                groups_update_group($group, false);
            }

            $ismember = $DB->get_record('groups_members', array('groupid' => $group->id, 'userid' => $USER->id));
            if (empty($ismember->id)) {
                groups_add_member($group, $USER);
            }
        }

        // Attention: This refers to a plugin that is currently not public!
        // It will not be available to others.
        // This is not nice, but currently there is no other way...
        $autocreate_orggroup = get_config('block_edusupport', 'autocreate_orggroup');
        if (file_exists($CFG->dirroot . '/blocks/eduvidual/block_eduvidual.php') && $cm->instance == get_config('block_edusupport', 'targetforum') && $autocreate_orggroup) {
            // Custom creation of groups for block_eduvidual.
            require_once($CFG->dirroot . '/group/lib.php');
            $orgs = $DB->get_records('block_eduvidual_orgid_userid', array('userid' => $USER->id));
            foreach($orgs AS $org) {
                if ($org->orgid < 10) continue;
                $group = groups_get_group_by_idnumber($cm->course, 'org-' . $org->orgid);
                if (!isset($group->id) || $group->id == 0) {
                    $org = $DB->get_record('block_eduvidual_org', array('orgid' => $org->orgid));
                    $group = (object) array('courseid' => $cm->course, 'idnumber' => 'org-' . $org->orgid, 'name' => $org->orgid . ' ' . $org->name, 'timecreated' => time(), 'timemodified' => time());
                    $group->id = groups_create_group($group);
                }
                $ismember = $DB->get_record('groups_members', array('groupid' => $group->id, 'userid' => $USER->id));
                if (!isset($ismember->id) || $ismember->id == 0) {
                    groups_add_member($group, $USER);
                }
            }
        }
        // This is the end of the code for the non-available plugin

        $groups = $DB->get_records_sql('SELECT g.* FROM {groups} g, {groups_members} gm WHERE g.id=gm.groupid AND gm.userid=? AND g.courseid=? ORDER BY g.name ASC', array($USER->id, $cm->course));

        return $groups;
    }
    /**
     * Close an issue.
    **/
    public static function close_issue($discussionid) {
        global $DB, $USER;
        $issue = self::get_issue($discussionid);
        $discussion = $DB->get_record('forum_discussions', array('id' => $discussionid));

        $supportlevel = self::get_supporter_level($discussion->course, $USER->id);
        if (!empty($supportlevel)) {
            $post = $DB->get_record('forum_posts', array('discussion' => $discussion->id, 'parent' => 0));
            $post->parent = $post->id;
            unset($post->id);
            $post->userid = $USER->id;
            $post->created = time();
            $post->modified = time();
            $post->mailed = 0;
            $post->subject = get_string('issue_closed:subject', 'block_edusupport');
            $post->message = get_string('issue_closed:text', 'block_edusupport');
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

            // 7. Adjust supporter in database
            $issue->currentsupporter = $USER->id;
            $issue->currentlevel = $supportlevel;
            $issue->opened = 0;
            $DB->update_record('block_edusupport_issues', $issue);

            $log = (object) array('issueid' => $issue->id, 'userid' => $USER->id, 'supportlevel' => $supportlevel, 'created' => time());
            $DB->insert_record('block_edusupport_supportlog', $log);
            return 1;
        } else {
            return 'no permission';
        }
    }
    /**
     * Enrols users to specific courses
     * @param courseids array containing courseid or a single courseid
     * @param userids array containing userids or a single userid
     * @param roleid roleid to assign, or -1 if wants to unenrol
     * @param reply (optional) array to log debug messages.
     * @return true or false
    **/
    public static function course_manual_enrolments($courseids, $userids, $roleid, &$reply = array()) {
        global $CFG, $DB;
        //print_r($courseids); print_r($userids); echo $roleid;
        if (!is_array($courseids)) $courseids = array($courseids);
        if (!is_array($userids)) $userids = array($userids);
        // Retrieve the manual enrolment plugin.
        $enrol = enrol_get_plugin('manual');
        if (empty($enrol)) {
            return false;
        }
        $failures = 0;
        foreach ($courseids AS $courseid) {
            // Check manual enrolment plugin instance is enabled/exist.
            $instance = null;
            $enrolinstances = enrol_get_instances($courseid, false);
            $reply['enrolinstances'] = $enrolinstances;
            foreach ($enrolinstances as $courseenrolinstance) {
              if ($courseenrolinstance->enrol == "manual") {
                  $instance = $courseenrolinstance;
                  break;
              }
            }
            if (empty($instance)) {
                // We have to add a "manual-enrolment"-instance
                $course = $DB->get_record('course', array('id' => $courseid));
                $fields = array(
                    'status' => 0,
                    'roleid' => 5, // student
                    'enrolperiod' => 0,
                    'expirynotify' => 0,
                    'expirytreshold' => 0,
                    'notifyall' => 0
                );
                require_once($CFG->dirroot . '/enrol/manual/lib.php');
                $emp = new enrol_manual_plugin();
                $reply['createinstance'] = true;
                $instance = $emp->add_instance($course, $fields);
            }
            $reply['enrolinstance'] = $instance;
            if (empty($instance)) {
                $failures++;
            } else {
                if ($instance->status == 1) {
                    // It is inactive - we have to activate it!
                    $course = $DB->get_record('course', array('id' => $courseid));
                    $data = (object)array('status' => 0);
                    require_once($CFG->dirroot . '/enrol/manual/lib.php');
                    $emp = new enrol_manual_plugin();
                    $reply['updateinstance'] = true;
                    $emp->update_instance($instance, $data);
                    $instance->status = $data->status;
                }
                foreach ($userids AS $userid) {
                    if (empty($userid)) continue;
                    if ($roleid == -1) {
                        $enrol->unenrol_user($instance, $userid);
                    } else {
                        $enrol->enrol_user($instance, $userid, $roleid, 0, 0, ENROL_USER_ACTIVE);
                    }
                }
            }
        }
        return ($failures == 0);
    }
    /**
     * Loads an issue from database
     * @param discussionid the discussionid of that issue
     * @return the issue
     */
    public static function get_issue($discussionid) {
        global $DB;
        $issue = $DB->get_record('block_edusupport_issues', array('discussionid' => $discussionid));
        if (empty($issue->id)) {
            $discussion = $DB->get_record('forum_discussions', array('id' => $discussionid));
            if (empty($discussion->id)) return;
            // Attention: This issue should exist. We create it.
            $issue = (object) array('courseid' => $discussion->course, 'discussionid' => $discussionid, 'currentsupporter' => 0, 'currentlevel' => '', 'opened' => 1, 'created' =>  time());
            $issue->id = $DB->insert_record('block_edusupport_issues', $issue);
        }
        return $issue;
    }
    /**
     * @return true if user is sysadmin
    **/
    public static function is_admin() {
        $sysctx = context_system::instance();
        return has_capability('moodle/site:config', $sysctx);
    }
    public static function set_current_supporter($discussionid, $userid) {
        global $DB, $USER;

        $discussion = $DB->get_record('forum_discussions', array('id' => $discussionid));
        // 1. Check if discussion exists
        if (empty($discussion->id)) return 'discussion missing';
        // 2. Check if forum is supportforum
        $support = $DB->get_record('block_edusupport', array('courseid' => $discussion->course, 'forumid' => $discussion->forum));
        if (empty($support->id)) return 'no support forum';
        // 3. Check if calling user is in the supportteam
        $supportlevel = self::get_supporter_level($support->courseid, $USER->id);
        if (empty($supportlevel)) return 'no permission';
        // 4. Check if designated user is in the supportteam
        $supportlevel = self::get_supporter_level($support->courseid, $userid);
        if (empty($supportlevel)) return 'no permission for designated user';
        // 5. Check if supporter has to be changed
        $issue = $DB->get_record('block_edusupport_issues', array('discussionid' => $discussion->id));
        if (empty($issue->id)) {
            // ERROR: AN ISSUE SHOULD EXIST. FIX THAT!
            $issue = (object) array('courseid' => $discussion->course, 'discussionid' => $discussion->id, 'currentlevel' => '', 'currentsupporter' => 0, 'opened' => 1);
            $issue->id = $DB->insert_record('block_edusupport_issues', $issue);
        }
        if ($issue->currentsupporter == $userid) return 'current supporter has not changed';
        // 6. Create post
        $supportuser = $DB->get_record('user', array('id' => $userid));
        $post = $DB->get_record('forum_posts', array('discussion' => $discussion->id, 'parent' => 0));
        $post->parent = $post->id;
        unset($post->id);
        $post->userid = $USER->id;
        $post->created = time();
        $post->modified = time();
        $post->mailed = 0;
        $post->subject = get_string('issue_assigned:subject', 'block_edusupport');
        $post->message = get_string('issue_assigned:text', 'block_edusupport', array('wwwroot' => $CFG->wwwroot, 'id' => $supportuser->id, 'firstname' => $supportuser->firstname, 'lastname' => $supportuser->lastname, 'supportlevel' => $supportlevel));
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

        // 7. Adjust supporter in database
        $issue->currentsupporter = $userid;
        $issue->currentlevel = $supportlevel;
        $DB->update_record('block_edusupport_issues', $issue);

        $log = (object) array('issueid' => $issue->id, 'userid' => $userid, 'supportlevel' => $supportlevel, 'created' => time());
        $DB->insert_record('block_edusupport_supportlog', $log);
        return 1;
    }
    /**
     * Checks if the forum is a valid target and the user has access to it.
     * Sets the page context to the course and returns true.
     * @param forumid Forumid that should be set as target.
     * @param params (optional) array that will be filled with debug messages
     * @return true if user can access this forum.
    **/
    public static function set_target($forumid, &$params = array()) {
        global $DB, $PAGE, $USER;
        $params['log_set_target'] = array();
        $entry = $DB->get_record('block_edusupport', array('forumid' => $forumid));
        $params['log_set_target'][] = $entry;
        if (!empty($entry->courseid)) {
            $module = $DB->get_record('course_modules', array('course' => $entry->courseid, 'instance' => $forumid));
            $params['log_set_target'][] = $module;
            if (!empty($module->id)) {
                // If this is the system target forum force enrolment!
                $systargetforum = get_config('block_edusupport', 'targetforum');
                $contextforum = context_module::instance($module->id);
                if ($systargetforum == $forumid || has_capability('mod/forum:startdiscussion', $contextforum)) {
                    if ($systargetforum == $forumid) {
                        self::course_manual_enrolments(array($entry->courseid), array($USER->id), 5);
                    }
                    $contextcourse = context_course::instance($entry->courseid);
                    $params['log_set_target'][] = $contextcourse;
                    $PAGE->set_context($contextcourse);
                    require_login(get_course($entry->courseid));
                    return $contextcourse;
                }
            }
        }
        return false;
    }

    // NON STATIC AREA
    public function init() {
        $this->title = get_string('pluginname', 'block_edusupport');
    }
    public function get_content() {
        if ($this->content !== null) {
          return $this->content;
        }
        global $CFG, $COURSE, $DB, $PAGE, $USER;
        $PAGE->requires->css('/blocks/edusupport/style/spinner.css');

        $this->content = (object) array(
            'text' => '',
            'footer' => array()
        );
        $options = array();

        $targetforum = self::get_target();
        $forum = $DB->get_record('forum', array('id' => $targetforum));
        $supportlevel = self::get_supporter_level($forum->course, $USER->id);

        if ($COURSE->id > 1 && self::can_config_course($COURSE->id)) {
            $options[] = array(
                "title" => get_string('courseconfig', 'block_edusupport'),
                "icon" => '/pix/t/edit.svg',
                "href" => $CFG->wwwroot . '/blocks/edusupport/courseconfig.php?id=' . $COURSE->id
            );
        }

        if ($targetforum < 0) {
            $options[] = array(
                "title" => get_string('missing_targetforum', 'block_edusupport'),
                "class" => '',
                "icon" => '/pix/i/info.svg',
            );
        } else {
            // Determine current view.
            if (strpos($_SERVER["SCRIPT_FILENAME"], '/mod/forum/view.php') > 0) {
                if (!empty($supportlevel)) {
                    $PAGE->requires->js_call_amd('block_edusupport/main', 'colorize', array('forumid' => $targetforum));
                }
            }
            if (strpos($_SERVER["SCRIPT_FILENAME"], '/mod/forum/discuss.php') > 0) {
                $discussionid = optional_param('d', 0, PARAM_INT);
                $issue = $DB->get_record('block_edusupport_issues', array('discussionid' => $discussionid));
                if (!empty($issue->currentsupporter)) {
                    $supporter = $DB->get_record('user', array('id' => $issue->currentsupporter));
                    $options[] = array(
                        "title" => $supporter->firstname . ' ' . $supporter->lastname . '(' . $issue->currentlevel . ')',
                        "class" => '',
                        "icon" => '/pix/i/user.svg',
                        "href" => $CFG->wwwroot . '/user/profile.php?id' . $supporter->id,
                    );
                }
                if (!empty($supportlevel)) {
                    $options[] = array(
                        "title" => get_string('issue_assign', 'block_edusupport'),
                        "class" => '',
                        "icon" => '/pix/i/users.svg',
                        "href" => '#',
                        "onclick" => 'require(["block_edusupport/main"], function(MAIN){ MAIN.assignSupporter(' . $discussionid . '); }); return false;',
                    );
                    $options[] = array(
                        "title" => get_string('issue_close', 'block_edusupport'),
                        "class" => '',
                        "icon" => '/pix/i/users.svg',
                        "href" => '#',
                        "onclick" => 'require(["block_edusupport/main"], function(MAIN){ MAIN.closeIssue(' . $discussionid . '); }); return false;',
                    );
                }
            }

            self::get_groups();
            $options[] = array(
                "title" => get_string('create_issue', 'block_edusupport'),
                "href" => '#',
                "id" => 'btn-block_edusupport_create_issue',
                "onclick" => 'require(["block_edusupport/main"], function(MAIN){ MAIN.showBox(' . $targetforum . '); }); return false;',
                "icon" => '/pix/t/messages.svg',
            );
            $cm = $DB->get_record('course_modules', array('course' => $forum->course, 'instance' => $targetforum));
            $options[] = array(
                "title" => get_string('goto_targetforum', 'block_edusupport'),
                "href" => $CFG->wwwroot . '/mod/forum/view.php?id=' . $cm->id,
                "class" => '',
                "icon" => '/pix/i/publish.svg',
            );
            if (file_exists($CFG->dirroot . '/blocks/eduvidual/block_eduvidual.php')) {
                $options[] = array(
                    "title" => get_string('goto_tutorials', 'block_edusupport'),
                    "href" => $CFG->wwwroot . '/course/view.php?id=' . $forum->course . '&section=3',
                    "class" => '',
                    "icon" => '/pix/docs.svg',
                );
            }

            /*
            $sql = "SELECT i.id issueid, i.opened opened, s.courseid courseid, c.fullname coursename, d.id discussionid, d.name discussionname
                        FROM {block_edusupport} s, {course} c, {forum_discussions} d, {block_edusupport_issues} i
                        WHERE s.courseid=d.course
                            AND s.courseid=c.id
                            AND d.course=c.id
                            AND i.discussionid=d.id
                            AND i.opened=1
                            AND i.id IN (SELECT DISTINCT(l.issueid) FROM oer_block_edusupport_supportlog l WHERE l.userid=?)
                        ORDER BY d.timemodified DESC";
            $issues = $DB->get_records_sql($sql, array($USER->id));
            //$discussions = $DB->get_records('forum_discussions', array('forum' => $targetforum, 'userid' => $USER->id));
            $header_printed = '';
            foreach($issues AS $issue) {
                if ($header_printed != $issue->coursename) {
                    $options[] = array(
                        "title" => $issue->coursename,
                        "icon" => '/pix/i/publish.svg',
                        "class" => 'divider',
                    );
                    $header_printed = $issue->coursename;
                }
                $options[] = array(
                    "title" => (strlen($issue->discussionname) > 27) ? substr($issue->discussionname, 0, 24) . '...' : $issue->discussionname,
                    "icon" => '',
                    "href" => $CFG->wwwroot . '/mod/forum/discuss.php?d=' . $issue->discussionid,
                    "style" => (!empty($issue->opened) && $issue->opened == 1) ? self::$STYLE_OPENED : self::$STYLE_CLOSED,
                );
            }
            */
        }

        foreach($options AS $option) {
            $tx = $option["title"];
            if (!empty($option["icon"])) $tx = "<img src='" . $option["icon"] . "' class='icon'>" . $tx;
            if (!empty($option["href"])) $tx = "
                <a href='" . $option["href"] . "' " . ((!empty($option["onclick"])) ? " onclick=\"" . $option["onclick"] . "\"" : "") . "
                   " . ((!empty($option["target"])) ? " target=\"" . $option["target"] . "\"" : "") . "'>" . $tx . "</a>";
            else  $tx = "<a>" . $tx . "</a>";
            $this->content->text .= $tx . "<br />";
        }

        return $this->content;
    }
    public function hide_header() {
        return false;
    }
    public function has_config() {
        return true;
    }
    public function instance_allow_multiple() {
        return false;
    }
}
