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
    public static $STYLE_MINE = 'background-color: rgba(200, 140, 20, 0.6)';
    public static $STYLE_CLOSED = 'background-color: rgba(0, 255, 0, 0.3)';

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
        require_once($CFG->dirroot . '/blocks/edusupport/locallib.php');

        $options = array();


        if (isloggedin() && !isguestuser($USER)) {
            $options[] = array(
                "title" => get_string('create_issue', 'block_edusupport'),
                "href" => '#',
                "id" => 'btn-block_edusupport_create_issue',
                "onclick" => 'require(["block_edusupport/main"], function(MAIN){ MAIN.showBox(' . $targetforum . '); }); return false;',
                "icon" => '/pix/t/messages.svg',
            );
        }
        if (!empty(get_config('block_edusupport', 'relativeurlsupportarea'))) {
            $options[] = array(
                "title" => get_string('goto_tutorials', 'block_edusupport'),
                "href" => $CFG->wwwroot . get_config('block_edusupport', 'relativeurlsupportarea'),
                "class" => '',
                "icon" => '/pix/docs.svg',
            );
        }

        if (\block_edusupport\lib::is_supportteam()) {

        } else {
            // Determine current view.

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

            $cm = $DB->get_record('course_modules', array('course' => $forum->course, 'instance' => $targetforum));
            $options[] = array(
                "title" => get_string('goto_targetforum', 'block_edusupport'),
                "href" => $CFG->wwwroot . '/mod/forum/view.php?id=' . $cm->id,
                "class" => '',
                "icon" => '/pix/i/publish.svg',
            );



            if ($COURSE->id > 1 && self::can_config_global()) { //self::can_config_course($COURSE->id)) {
                $options[] = array(
                    "title" => get_string('courseconfig', 'block_edusupport'),
                    "icon" => '/pix/t/edit.svg',
                    "href" => $CFG->wwwroot . '/blocks/edusupport/courseconfig.php?id=' . $COURSE->id
                );
            }

            if (!empty($supportlevel)) {
                // Get open issues with me as supporter.
                $sql = "SELECT '0' AS id,COUNT(id) AS cnt
                            FROM {block_edusupport_issues}
                            WHERE currentsupporter=?";
                $tmine = $DB->get_records_sql($sql, array($USER->id));
                $rmine = $tmine[0];

                $options[] = array(
                    "title" => get_string('issues:openmine', 'block_edusupport', $rmine->cnt),
                    "icon" => '/pix/i/completion-auto-y.svg',
                );

                // Get open issues of 2nd level
                $sql = "SELECT '0' AS id,COUNT(id) AS cnt
                            FROM {block_edusupport_issues}
                            WHERE AND currentsupporter=0";
                $tnone = $DB->get_records_sql($sql, array());
                $rnone = $tnone[0];

                $options[] = array(
                    "title" => get_string('issues:opennosupporter', 'block_edusupport', $rnone->cnt),
                    "icon" => '/pix/i/completion-auto-fail.svg',
                );
            }
        }

        foreach($options AS $option) {
            $tx = $option["title"];
            if (!empty($option["icon"])) $tx = "<img src='" . $option["icon"] . "' class='icon'>" . $tx;
            if (!empty($option["href"])) $tx = "
                <a href='" . $option["href"] . "' " . ((!empty($option["onclick"])) ? " onclick='" . $option["onclick"] . "'" : "") . "
                   " . ((!empty($option["target"])) ? " target=\"" . $option["target"] . "\"" : "") . "'>" . $tx . "</a>";
            else  $tx = "<a>" . $tx . "</a>";
            $this->content->text .= $tx. "<br />";
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
