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

require_once('../../config.php');
require_once($CFG->dirroot . '/blocks/edusupport/classes/lib.php');

// We fake a forum discussion here.
// This code is mainly taken from /mod/forum/discuss.php.
$d = optional_param('d', 0, PARAM_INT); // discussionid.
$discussionid = optional_param('discussion', 0, PARAM_INT); // discussionid.
$discussionid = $discussionid | $d;
$parent = optional_param('parent', 0, PARAM_INT);        // If set, then display this post and all children.
$mode   = optional_param('mode', 0, PARAM_INT);          // If set, changes the layout of the thread
$move   = optional_param('move', 0, PARAM_INT);          // If set, moves this discussion to another forum
$mark   = optional_param('mark', '', PARAM_ALPHA);       // Used for tracking read posts if user initiated.
$postid = optional_param('postid', 0, PARAM_INT);        // Used for tracking read posts if user initiated.
$pin    = optional_param('pin', -1, PARAM_INT);          // If set, pin or unpin this discussion.

$url = new moodle_url('/blocks/edusupport/issue.php', array('discussion'=>$discussionid));
if ($parent !== 0) {
    $url->param('parent', $parent);
}
$PAGE->set_url($url);

$context = \context_system::instance();
$PAGE->set_context($context);
require_login();

$issue = \block_edusupport\lib::get_issue($discussionid);
$discussion = $DB->get_record('forum_discussions', array('id' => $discussionid));
$PAGE->set_title($discussion->name);
$PAGE->set_heading($discussion->name);

if (false && !\block_edusupport\lib::is_supporteam()) {
    echo $OUTPUT->header();
    $tocmurl = new moodle_url('/course/view.php', array('id' => $issue->courseid));
    echo $OUTPUT->render_from_template('block_edusupport/alert', array(
        'content' => get_string('missing_permission', 'block_edusupport'),
        'type' => 'danger',
        'url' => $tocmurl->__toString(),
    ));
} else {
    $discussion = $DB->get_record('forum_discussions', array('id' => $discussionid), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $discussion->course), '*', MUST_EXIST);
    $forum = $DB->get_record('forum', array('id' => $discussion->forum), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
    $post = $DB->get_record('forum_posts', array('discussion' => $discussionid, 'parent' => 0));
    $coursecontext = \context_course::instance($forum->course);
    $modcontext = \context_module::instance($cm->id);


    $PAGE->set_title("$course->shortname: ".format_string($discussion->name));
    $PAGE->set_heading($course->fullname);

    echo $OUTPUT->header();

    \block_edusupport\lib::assign_role($coursecontext, true);

    // move this down fix for MDL-6926
    require_once($CFG->dirroot.'/mod/forum/lib.php');

    // Trigger discussion viewed event.
    forum_discussion_view($modcontext, $forum, $discussion);

    unset($SESSION->fromdiscussion);

    if ($mode) {
        set_user_preference('forum_displaymode', $mode);
    }

    $displaymode = get_user_preferences('forum_displaymode', $CFG->forum_displaymode);

    if ($parent) {
        // If flat AND parent, then force nested display this time
        if ($displaymode == FORUM_MODE_FLATOLDEST or $displaymode == FORUM_MODE_FLATNEWEST) {
            $displaymode = FORUM_MODE_NESTED;
        }
    } else {
        $parent = $discussion->firstpost;
    }

    if (! $post = forum_get_post_full($parent)) {
        print_error("notexists", 'forum', "$CFG->wwwroot/mod/forum/view.php?f=$forum->id");
    }

    if (!forum_user_can_see_post($forum, $discussion, $post, null, $cm, false)) {
        print_error('noviewdiscussionspermission', 'forum', "$CFG->wwwroot/mod/forum/view.php?id=$forum->id");
    }
    if ($mark == 'read' or $mark == 'unread') {
        if ($CFG->forum_usermarksread && forum_tp_can_track_forums($forum) && forum_tp_is_tracked($forum)) {
            if ($mark == 'read') {
                forum_tp_add_read_record($USER->id, $postid);
            } else {
                // unread
                forum_tp_delete_read_records($USER->id, $postid);
            }
        }
    }

    $forumnode = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
    if (empty($forumnode)) {
        $forumnode = $PAGE->navbar;
    } else {
        $forumnode->make_active();
    }
    $node = $forumnode->add(format_string($discussion->name), new moodle_url('/mod/forum/discuss.php', array('d'=>$discussion->id)));
    $node->display = false;
    if ($node && $post->id != $discussion->firstpost) {
        $node->add(format_string($post->subject), $PAGE->url);
    }


    $renderer = $PAGE->get_renderer('mod_forum');

    ob_start();

    /// Print the controls across the top
    echo '<div class="discussioncontrols clearfix"><div class="controlscontainer m-b-1">';

    // groups selector not needed here
    echo '<div class="discussioncontrol displaymode">';
    forum_print_mode_form($discussion->id, $displaymode);
    echo "</div>";

    echo "</div></div>";

    if (forum_discussion_is_locked($forum, $discussion)) {
        echo $OUTPUT->notification(get_string('discussionlocked', 'forum'),
        \core\output\notification::NOTIFY_INFO . ' discussionlocked');
    }

    if (!empty($forum->blockafter) && !empty($forum->blockperiod)) {
        $a = new stdClass();
        $a->blockafter  = $forum->blockafter;
        $a->blockperiod = get_string('secondstotime'.$forum->blockperiod);
        echo $OUTPUT->notification(get_string('thisforumisthrottled','forum',$a));
    }

    if ($forum->type == 'qanda' && !has_capability('mod/forum:viewqandawithoutposting', $modcontext) &&
    !forum_user_has_posted($forum->id,$discussion->id,$USER->id)) {
        echo $OUTPUT->notification(get_string('qandanotify', 'forum'));
    }

    if ($move == -1 and confirm_sesskey()) {
        echo $OUTPUT->notification(get_string('discussionmoved', 'forum', format_string($forum->name,true)), 'notifysuccess');
    }

    $canrate = has_capability('mod/forum:rate', $modcontext);

    forum_print_discussion($course, $cm, $forum, $discussion, $post, $displaymode, $canreply, $canrate);

    $out = ob_get_contents();
    ob_end_clean();
    \block_edusupport\lib::assign_role($coursecontext, false);
    $out = str_replace($CFG->wwwroot . '/mod/forum/discuss.php', $CFG->wwwroot . '/blocks/edusupport/issue.php', $out);
    $starts = array(
        //'<div class="singleselect d-inline-block">',
        //'<div class="discussion-nav clearfix">',
        '<div class="commands">'
    );
    $ends = array(
        //'</div>',
        //'</div>',
        '</div>'
    );
    for ($a = 0; $a < count($starts); $a++) {
        if (empty($starts[$a] || empty($ends[$a]))) continue;
        while(($cutstart = strpos($out, $starts[$a])) > 0) {
            $cutend = strpos($out, $ends[$a], $cutstart);
            $out1 = substr($out, 0, $cutstart);
            $out2 = substr($out, $cutend + strlen($ends[$a]));
            $out = $out1 . $out2;
        }
    }
    echo $out;

    // Add the subscription toggle JS.
    $PAGE->requires->yui_module('moodle-mod_forum-subscriptiontoggle', 'Y.M.mod_forum.subscriptiontoggle.init');


    require_once($CFG->dirroot . '/mod/forum/classes/post_form.php');
    $mform_post = new \mod_forum_post_form($CFG->wwwroot . '/blocks/edusupport/issue.php', array(
        'course' => $course,
        'cm' => $cm,
        'coursecontext' => $coursecontext,
        'modcontext' => $modcontext,
        'forum' => $forum,
        'post' => '',
        'subscribe' => \mod_forum\subscriptions::is_subscribed($USER->id, $forum, null, $cm),
        'thresholdwarning' => $thresholdwarning,
        'edit' => $edit), 'post', '', array('id' => 'mformforum')
    );
    $draftitemid = \file_get_submitted_draft_itemid('attachments');
    //\file_prepare_draft_area($draftitemid, $modcontext->id, 'mod_forum', 'attachment', empty($post->id)?null:$post->id, \mod_forum_post_form::attachment_options($forum));
    \file_prepare_draft_area($draftitemid, $modcontext->id, 'mod_forum', 'attachment', null, \mod_forum_post_form::attachment_options($forum));

    $formheading = '';
    if (!empty($parent)) {
        $heading = get_string("yourreply", "forum");
        $formheading = get_string('reply', 'forum');
    } else {
        if ($forum->type == 'qanda') {
            $heading = get_string('yournewquestion', 'forum');
        } else {
            $heading = get_string('yournewtopic', 'forum');
        }
    }

    $postid = empty($post->id) ? null : $post->id;
    $draftid_editor = file_get_submitted_draft_itemid('message');
    $currenttext = file_prepare_draft_area($draftid_editor, $modcontext->id, 'mod_forum', 'post', $postid, \mod_forum_post_form::editor_options($modcontext, $postid), $post->message);

    $mform_post->set_data(
        array(
            'attachments'=>$draftitemid,
            'general'=>$heading,
            'subject'=> 'Re: ' . $post->subject,
            'message'=>array(
                'text'  => '',
                'format'=> editors_get_preferred_format(),
                'itemid'=>$draftid_editor
            ),
            'discussionsubscribe' => $discussionsubscribe,
            'mailnow'=>!empty($post->mailnow),
            'userid'=>$USER->id,
            'parent'=>$post->id,
            'discussion'=>$post->discussion,
            'course'=>$course->id,
            'forum' => $forum->id,
        )
        // $page_params
        +(isset($post->format) ? array('format'=>$post->format) : array())
        +(isset($discussion->timestart)?array('timestart'=>$discussion->timestart):array())
        +(isset($discussion->timeend)?array('timeend'=>$discussion->timeend):array())
        +(isset($discussion->pinned) ? array('pinned' => $discussion->pinned):array())
        +(isset($post->groupid)?array('groupid'=>$post->groupid):array())
        +(isset($discussion->id)?array('discussion'=>$discussion->id):array())
    );
    if ($mform_post->is_cancelled()) {
        redirect($PAGE->url->__toString());
    } else if ($fromform = $mform_post->get_data()) {
        if (empty($SESSION->fromurl)) {
            $errordestination = $PAGE->url->__toString();
        } else {
            $errordestination = $SESSION->fromurl;
        }

        $fromform->itemid        = $fromform->message['itemid'];
        $fromform->messageformat = $fromform->message['format'];
        $fromform->message       = $fromform->message['text'];
        // WARNING: the $fromform->message array has been overwritten, do not use it anymore!
        $fromform->messagetrust  = trusttext_trusted($modcontext);

        // Clean message text.
        $fromform = trusttext_pre_edit($fromform, 'message', $modcontext);

        if ($fromform->discussion) { // Adding a new post to an existing discussion

            // Before we add this we must check that the user will not exceed the blocking threshold.
            \forum_check_blocking_threshold($thresholdwarning);

            unset($fromform->groupid);
            $message = '';
            $addpost = $fromform;
            $addpost->forum=$forum->id;
            if ($fromform->id = \forum_add_new_post($addpost, $mform_post)) {

                $fromform->deleted = 0;
                $subscribemessage = \forum_post_subscription($fromform, $forum, $discussion);

                if (!empty($fromform->mailnow)) {
                    $message .= get_string("postmailnow", "forum");
                } else {
                    $message .= '<p>'.get_string("postaddedsuccess", "forum") . '</p>';
                    $message .= '<p>'.get_string("postaddedtimeleft", "forum", format_time($CFG->maxeditingtime)) . '</p>';
                }

                $discussionurl = $PAGE->url->__toString();

                $params = array(
                    'context' => $modcontext,
                    'objectid' => $fromform->id,
                    'other' => array(
                        'discussionid' => $discussion->id,
                        'forumid' => $forum->id,
                        'forumtype' => $forum->type,
                    )
                );
                $event = \mod_forum\event\post_created::create($params);
                $event->add_record_snapshot('forum_posts', $fromform);
                $event->add_record_snapshot('forum_discussions', $discussion);
                $event->trigger();

                // Update completion state
                $completion = new \completion_info($course);
                if($completion->is_enabled($cm) &&
                ($forum->completionreplies || $forum->completionposts)) {
                    $completion->update_state($cm,COMPLETION_COMPLETE);
                }
                redirect(
                    forum_go_back_to($discussionurl),
                    $message . $subscribemessage,
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            } else {
                print_error("couldnotadd", "forum", $errordestination);
            }
            exit;

        }
    }
    $mform_post->display();
}

echo $OUTPUT->footer();
