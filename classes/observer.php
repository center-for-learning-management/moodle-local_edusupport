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
 * @copyright  2020 Center for Learningmangement (www.lernmanagement.at)
 * @author     Robert Schrenk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_edusupport;

defined('MOODLE_INTERNAL') || die;

class observer {
    public static function event($event) {
        global $CFG, $DB;

        //error_log("OBSERVER EVENT: " . print_r($event, 1));
        $entry = (object)$event->get_data();

        if (substr($entry->eventname, 0, strlen("\\mod_forum\\event\\post_")) == "\\mod_forum\\event\\post_") {
            $post = $DB->get_record("forum_posts", array("id" => $entry->objectid));
            $discussion = $DB->get_record("forum_discussions", array("id" => $post->discussion));
        } else {
            $discussion = $DB->get_record("forum_discussions", array("id" => $entry->objectid));
            $post = $DB->get_record("forum_posts", array("discussion" => $discussion->id, "parent" => 0));
        }
        $forum = $DB->get_record("forum", array("id" => $discussion->forum));

        $issue = $DB->get_record('block_edusupport_issues', array('discussionid' => $discussion->id));
        if (empty($issue->id)) return;

        $course = \get_course($forum->course);
        $cm = \$cm = get_coursemodule_from_instance('forum', $forum->id, 0, false, MUST_EXIST);
        $author = \get_user($post->userid);

        // Get all subscribers
        $fromuser = \core_user::get_support_user();
        $subscribers = $DB->get_records('block_edusupport_subscr', array('discussionid' => $discussion->id));
        foreach ($subscribers AS $subscriber) {
            // Check if this person is not a subscriber of the forum itself.
            $chkforum = $DB->get_record('forum_subscriptions', array('userid' => $subscriber->userid, 'forumid' => $discussion->forum));
            $chkdisc = $DB->get_record('forum_discussion_subs', array('userid' => $subscriber->userid, 'discussionid' => $discussion->id));
            if (empty($chkforum->id) && empty($chkdisc->id)) {
                $touser = \get_user($subscriber->userid);

                $data = new \mod_forum\output\forum_post_email(
                    $course,
                    $cm,
                    $forum,
                    $discussion,
                    $post,
                    $author,
                    $touser,
                    true
                );

                print_r($data);

                // Send notification
                $subject = $discussion->name;
                $mailhtml =  $OUTPUT->render_from_template('mod_forum/forum_post_emaildigestfull_htmlemail', array($post));
                $mailhtml =  $OUTPUT->render_from_template('mod_forum/forum_post_emaildigestfull_textemail', array($post));

                \email_to_user($touser, $author, $subject, $mailtext, $mailhtml, "", true);
            }
        }

        return true;
    }
}
