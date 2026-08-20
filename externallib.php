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
        return new external_function_parameters([
            'discussionid' => new external_value(PARAM_INT, 'discussionid'),
        ]);
    }

    public static function close_issue($discussionid) {
        global $CFG;
        $params = self::validate_parameters(self::close_issue_parameters(), ['discussionid' => $discussionid]);
        return \local_edusupport\lib::close_issue($params['discussionid']);
    }

    public static function close_issue_returns() {
        return new external_value(PARAM_RAW, 'Returns 1 if successful, or error message.');
    }

    public static function get_potentialsupporters_parameters() {
        return new external_function_parameters(
            [
                'discussionid' => new external_value(PARAM_INT, 'discussionid'),
            ]
        );
    }

    public static function get_potentialsupporters($discussionid) {
        global $DB, $USER;
        $params = self::validate_parameters(self::get_potentialsupporters_parameters(), ['discussionid' => $discussionid]);
        $reply['supporters'] = [];

        $discussion = $DB->get_record('forum_discussions', ['id' => $params['discussionid']]);
        $sql = "SELECT s.userid,u.firstname,u.lastname,s.supportlevel
                    FROM {user} u, {local_edusupport_supporters} s
                    WHERE u.id=s.userid
                        AND (s.courseid=1 OR s.courseid=?)
                    ORDER BY u.lastname ASC,u.firstname ASC";
        $supporters = $DB->get_records_sql($sql, [$discussion->course]);
        foreach ($supporters as $supporter) {
            if (empty($supporter->supportlevel)) {
                $supporter->supportlevel = get_string('label:2ndlevel', 'local_edusupport');
            }
            if (!isset($reply['supporters'][$supporter->supportlevel])) {
                $reply['supporters'][$supporter->supportlevel] = [];
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
        return new external_function_parameters([
            'forumid' => new external_value(PARAM_INT, 'ForumID of archive'),
        ]);
    }

    public static function set_archive($forumid) {
        global $CFG, $DB, $PAGE;

        $params = self::validate_parameters(self::set_archive_parameters(), ['forumid' => $forumid]);

        $forum = $DB->get_record('forum', ['id' => $params['forumid']]);
        if (empty($forum->id)) {
            return -1;
        }

        if (local_edusupport::can_config_course($forum->course)) {
            $entry = $DB->get_record('local_edusupport', ['courseid' => $forum->course]);
            if (!empty($entry->courseid)) {
                $entry->forumid = !empty($entry->forumid) ? $entry->forumid : 0;
                $entry->archiveid = $forum->id;
                $DB->update_record('local_edusupport', $entry);
            } else {
                $entry = (object)['courseid' => $forum->course, 'forumid' => $forum->id, 'archiveid' => 0];
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
            [
                'discussionid' => new external_value(PARAM_INT, 'discussionid'),
                'supporterid' => new external_value(PARAM_INT, 'supporterid (userid)'),
            ]
        );
    }

    public static function set_currentsupporter($discussionid, $supporterid) {
        global $CFG, $DB, $USER;
        $params = self::validate_parameters(self::set_currentsupporter_parameters(), ['discussionid' => $discussionid, 'supporterid' => $supporterid]);
        return \local_edusupport\lib::set_current_supporter($params['discussionid'], $params['supporterid']);
    }

    public static function set_currentsupporter_returns() {
        return new external_value(PARAM_RAW, 'Returns 1 if successful.');
    }


    public static function set_default_parameters() {
        return new external_function_parameters([
            'forumid' => new external_value(PARAM_INT, 'ForumID of new systemwide forum'),
            'asglobal' => new external_value(PARAM_BOOL, 'Whether this should be used as global target forum or not'),
        ]);
    }

    public static function set_default($forumid, $asglobal) {
        global $CFG, $DB, $PAGE;

        $params = self::validate_parameters(self::set_default_parameters(), ['forumid' => $forumid, 'asglobal' => $asglobal]);

        $forum = $DB->get_record('forum', ['id' => $params['forumid']]);
        if (empty($forum->id)) {
            return -1;
        }
        if ($params['asglobal']) {
            if (local_edusupport::can_config_global()) {
                set_config('targetforum', $forum->id, 'local_edusupport');
            } else {
                return -2;
            }
        } elseif ($forum->id == get_config('local_edusupport', 'targetforum')) {
            set_config('targetforum', 0, 'local_edusupport');
        }

        if (local_edusupport::can_config_course($forum->course)) {
            $entry = $DB->get_record('local_edusupport', ['courseid' => $forum->course]);
            if (!empty($entry->forumid)) {
                $entry->forumid = $forum->id;
                $DB->update_record('local_edusupport', $entry);
            } else {
                $entry = (object)['courseid' => $forum->course, 'forumid' => $forum->id];
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
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'CourseID'),
            'userid' => new external_value(PARAM_INT, 'UserID'),
            'supportlevel' => new external_value(PARAM_TEXT, 'Supportlevel to set'),
        ]);
    }

    public static function set_supporter($courseid, $userid, $supportlevel) {
        global $CFG, $DB, $PAGE;

        $params = self::validate_parameters(self::set_supporter_parameters(), ['courseid' => $courseid, 'userid' => $userid, 'supportlevel' => $supportlevel]);
        if (local_edusupport::can_config_course($params['courseid'])) {
            if (empty($params['supportlevel'])) {
                $DB->delete_records('local_edusupport_supporters', ['courseid' => $params['courseid'], 'userid' => $params['userid']]);
            } else {
                $entry = $DB->get_record('local_edusupport_supporters', ['courseid' => $params['courseid'], 'userid' => $params['userid']]);
                if (!empty($entry->supportlevel)) {
                    $entry->supportlevel = $params['supportlevel'];
                    $DB->update_record('local_edusupport_supporters', $entry);
                } else {
                    $DB->insert_record('local_edusupport_supporters', (object)$params);
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
