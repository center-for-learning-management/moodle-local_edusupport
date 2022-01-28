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

require_once('../../config.php');
//require_once($CFG->dirroot . '/local/edusupport/classes/lib.php');

$context = \context_system::instance();
$PAGE->set_context($context);
require_login();
$PAGE->set_url(new moodle_url('/local/edusupport/issues.php', array()));
$PAGE->requires->css('/local/edusupport/style/edusupport.css');
$title = get_string('issues', 'local_edusupport');
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();

if (!\local_edusupport\lib::is_supportteam()) {
    $tocmurl = new moodle_url('/course/view.php', array('id' => $courseid));
    echo $OUTPUT->render_from_template('local_edusupport/alert', array(
        'content' => get_string('missing_permission', 'local_edusupport'),
        'type' => 'danger',
        'url' => $tocmurl->__toString(),
    ));
} else {
    $assign = optional_param('assign', 0, PARAM_INT); // discussion id we want to assign to
    $unassign = optional_param('unassign', 0, PARAM_INT); // discussion id we want to unassign from
    $take = optional_param('take', 0, PARAM_INT); // discussion id we want to unassign from
    $give = optional_param('give', 0, PARAM_INT);
    $reopen = optional_param('reopen', 0, PARAM_INT);
    $close = optional_param('close', 0, PARAM_INT);
    $prio= optional_param('prio', 0, PARAM_INT);
    $lvl= optional_param('lvl', 0, PARAM_INT);
    $sql = "SELECT id,discussionid FROM {local_edusupport_issues}";
    $issues = $DB->get_records('local_edusupport_issues', array(), 'opened,id,discussionid');

    $params = array(
        'current' => array(), // issues the user is responsible for
        'assigned' => array(), // issues the user receives notifications for
        'other' => array(), // all other issues
        'wwwroot' => $CFG->wwwroot,
        'count' => array()
    );
    $hasprio = get_config('local_edusupport','prioritylvl');
    $params['count']['current'] = 0;
    $params['count']['closed'] = 0;
    $params['count']['assigned'] = 0;
    $params['count']['other'] = 0;
    $params['userlinks'] = get_config('local_edusupport','userlinks');
    $params['hasprio'] = $hasprio;
    foreach (array_reverse($issues) AS $issue) {
        // Collect certain data about this issue.
        $discussion = $DB->get_record('forum_discussions', array('id' => $issue->discussionid));
        $issue->name = $discussion->name;
        $issue->userid = $discussion->userid;
        $postinguser = $DB->get_record('user', array('id' => $discussion->userid));
        $issue->userfullname = \fullname($postinguser);
        $sql = "SELECT id,modified,userid FROM {forum_posts} WHERE discussion=? ORDER BY modified DESC LIMIT 1 OFFSET 0";
        $lastpost = $DB->get_record_sql($sql, array($issue->discussionid));
        $issue->lastmodified = $lastpost->modified;
        $issue->lastpostuserid = $lastpost->userid;
        $lastuser = $DB->get_record('user', array('id' => $issue->lastpostuserid));
        $issue->lastpostuserfullname = fullname($lastuser);
        $assigned = $DB->get_record('local_edusupport_subscr', array('discussionid' => $issue->discussionid, 'userid' => $USER->id));
        $issue->prio = "";
        $issue->priolow = "";
        $issue->priomid = "";
        $issue->priohigh = "";

        // Check for any actions.
        if (!empty($assign) && $assign == $issue->discussionid && empty($assigned->id)) {
            $assigned = \local_edusupport\lib::subscription_add($issue->discussionid);
        }
        if (!empty($unassign) && $unassign == $issue->discussionid) {
            \local_edusupport\lib::subscription_remove($issue->discussionid);
            unset($assigned);
        }
        if (!empty($take) && $take == $issue->discussionid) {
            \local_edusupport\lib::set_current_supporter($issue->discussionid, $USER->id);
            $assigned = \local_edusupport\lib::subscription_add($issue->discussionid);
            $issue->currentsupporter = $USER->id;
        }
        if (!empty($give) && $give == $issue->discussionid) {
            \local_edusupport\lib::set_current_supporter($issue->discussionid, "1");
            $assigned = \local_edusupport\lib::subscription_add($issue->discussionid);
            $issue->currentsupporter = "1";
        }
        if (!empty($reopen) && $reopen == $issue->discussionid) {
            \local_edusupport\lib::reopen_issue($issue->discussionid);
            $issue->opened = "1";
            $issue->name = ltrim($issue->name, "[Closed] ");
        }
       if (!empty($close) && $close == $issue->discussionid) {
            \local_edusupport\lib::close_issue($issue->discussionid);
            $issue->opened = "0";
            unset($assigned);
            $issue->name = "[Closed] " . ltrim($discussion->name, "[Closed] ");
        }
        if (!empty($prio) && $prio == $issue->discussionid && !empty($lvl)) {
            \local_edusupport\lib::set_prioritylvl($issue->discussionid,$lvl);
            $issue->opened = $lvl;
            //$issue->name = "[Closed] " . ltrim($discussion->name, "[Closed] ");
        }

        // Now get the current supporter
        if (!empty($issue->currentsupporter)) {
            $supportuser = $DB->get_record('user', array('id' => $issue->currentsupporter));
            $issue->currentsupportername = \fullname($supportuser);
            $issue->currentsupporterid = $issue->currentsupporter;
        } else {
            $issue->currentsupportername = get_string('label:2ndlevel', 'local_edusupport');
        }


        if ($hasprio) {
            if ($issue->opened <= 1) {
                $issue->priolow = "active";
                $issue->priomid = "";
                $issue->priohigh = "";
            }
            if ($issue->opened > 1) {
                $issue->priolow = "";
                $issue->priomid = "active";
                $issue->priohigh = "";
            }
            if ($issue->opened > 2) {
                $issue->priolow = "";
                $issue->priomid = "";
                $issue->priohigh = "active";
            }
        }
        // Now separate between current, assigned and other issues.
        if ($issue->currentsupporter == $USER->id && $issue->opened > 0) {
            $params['current'][] = $issue;
            $params['count']['current'] = $params['count']['current'] + 1;
        } elseif (!empty($assigned->id)) {
            $params['assigned'][] = $issue;
            $params['count']['assigned'] = $params['count']['assigned'] + 1;
        } elseif($issue->opened > 0) {
            $params['other'][] = $issue;
            $params['count']['other'] = $params['count']['other'] + 1;
        }
        elseif($issue->opened == 0) {
            $params['closed'][] = $issue;
            $params['count']['closed'] = $params['count']['closed'] + 1;
        }
    }

    $supporter = $DB->get_record('local_edusupport_supporters', array('userid' => $USER->id));

    $hm = optional_param('holidaymode', 0, PARAM_INT);
    if ($hm == -1) {
        // Disable holiday mode.
        $supporter->holidaymode = 0;
        $DB->set_field('local_edusupport_supporters', 'holidaymode', 0, array('userid' => $supporter->userid));
    } elseif (is_array($hm)) {
        $supporter->holidaymode = mktime($hm['hour'], $hm['minute'], 0, $hm['month'], $hm['day'], $hm['year']);
        $DB->set_field('local_edusupport_supporters', 'holidaymode', $supporter->holidaymode, array('userid' => $supporter->userid));
    }
    if ($supporter->holidaymode < time()) {
        // Expired holidaymode - invalidate.
        $supporter->holidaymode = 0;
        $DB->set_field('local_edusupport_supporters', 'holidaymode', 0, array('userid' => $supporter->userid));
    }

    require_once($CFG->dirroot . '/local/edusupport/classes/holidaymode_form.php');
    $mform = new \local_edusupport\holidaymode_form();
    $supporter->holidayform = $mform->render();
    echo $OUTPUT->render_from_template('local_edusupport/holidaymode', $supporter);
    echo $OUTPUT->render_from_template('local_edusupport/issues', $params);
}

echo $OUTPUT->footer();
