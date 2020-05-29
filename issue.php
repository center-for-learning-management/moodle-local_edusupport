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
//require_once($CFG->dirroot . '/blocks/edusupport/classes/lib.php');

// We fake a forum discussion here.
// This code is mainly taken from /mod/forum/discuss.php.
$d = optional_param('d', 0, PARAM_INT); // discussionid.
$discussion = optional_param('discussion', 0, PARAM_INT); // discussionid.
$discussionid = $discussion | $d;
$reply = optional_param('reply', 0, PARAM_INT);          // If set, we reply to this post.

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

if (!\block_edusupport\lib::is_supportteam()) {
    echo $OUTPUT->header();
    $tocmurl = new moodle_url('/course/view.php', array('id' => $issue->courseid));
    echo $OUTPUT->render_from_template('block_edusupport/alert', array(
        'content' => get_string('missing_permission', 'block_edusupport'),
        'type' => 'danger',
        'url' => $tocmurl->__toString(),
    ));
} else {
    \block_edusupport\lib::expose_properties();
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

    //\block_edusupport\lib::assign_role($coursecontext, true);
    $options = array();

    if (!empty($issue->currentsupporter)) {
        $supporter = $DB->get_record('block_edusupport_supporters', array('id' => $issue->currentsupporter));
        $user = $DB->get_record('user', array('id' => $supporter->userid));

        $options[] = array(
            "title" => \fullname($user) . ' (' . $supporter->supportlevel . ')',
            "class" => '',
            //"icon" => 'i/checkpermissions',
            "href" => $CFG->wwwroot . '/user/profile.php?id' . $supporter->id,
        );
    }

    $options[] = array(
        "title" => get_string('issue_assign', 'block_edusupport'),
        "class" => 'btn-secondary',
        "icon" => 'i/assignroles',
        "href" => '#',
        "onclick" => "require(['block_edusupport/main'], function(MAIN){ MAIN.assignSupporter($d); }); return false;",
    );
    $options[] = array(
        "title" => get_string('issue_close', 'block_edusupport'),
        "class" => 'btn-primary',
        "icon" => 't/approve',
        "href" => '#',
        "onclick" => "require(['block_edusupport/main'], function(MAIN){ MAIN.closeIssue($d); }); return false;",
    );
    echo $OUTPUT->render_from_template('block_edusupport/issue_options', array('options' => $options));

    // We capture the output, as we need to modify links to attachments!
    ob_start();

    $vaultfactory = \mod_forum\local\container::get_vault_factory();
    $discussionvault = $vaultfactory->get_discussion_vault();
    $vdiscussion = $discussionvault->get_from_id($discussionid);
    $discussion = $DB->get_record('forum_discussions', array('id' => $discussionid));

    if (!$vdiscussion) {
        throw new \moodle_exception('Unable to find discussion with id ' . $discussionid);
    }

    $forumvault = $vaultfactory->get_forum_vault();
    $vforum = $forumvault->get_from_id($vdiscussion->get_forum_id());
    $forum = $DB->get_record('forum', array('id' => $vdiscussion->get_forum_id()));

    if (!$forum) {
        throw new \moodle_exception('Unable to find forum with id ' . $vdiscussion->get_forum_id());
    }

    //$course = $forum->get_course_record();
    $course = get_course($forum->course);
    //$cm = $forum->get_course_module_record();
    $cm =  get_coursemodule_from_instance('forum', $forum->id, 0, false, MUST_EXIST);

    $mode   = optional_param('mode', 0, PARAM_INT);          // If set, changes the layout of the thread
    $saveddisplaymode = get_user_preferences('forum_displaymode', $CFG->forum_displaymode);

    if ($mode) {
        $displaymode = $mode;
    } else {
        $displaymode = $saveddisplaymode;
    }

    if (get_user_preferences('forum_useexperimentalui', false)) {
        if ($displaymode == FORUM_MODE_NESTED) {
            $displaymode = FORUM_MODE_NESTED_V2;
        }
    } else {
        if ($displaymode == FORUM_MODE_NESTED_V2) {
            $displaymode = FORUM_MODE_NESTED;
        }
    }

    if ($displaymode != $saveddisplaymode) {
        set_user_preference('forum_displaymode', $displaymode);
    }

    if ($parent) {
        // If flat AND parent, then force nested display this time
        if ($displaymode == FORUM_MODE_FLATOLDEST or $displaymode == FORUM_MODE_FLATNEWEST) {
            $displaymode = FORUM_MODE_NESTED;
        }
    } else {
        $parent = $vdiscussion->get_first_post_id();
    }

    $postvault = $vaultfactory->get_post_vault();
    if (!$vpost = $postvault->get_from_id($parent)) {
        print_error("notexists", 'forum', "$CFG->wwwroot/mod/forum/view.php?f={$forum->get_id()}");
    }
    $post = $DB->get_record('forum_posts', array('id' => $parent->id));

    $forumnode = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
    if (empty($forumnode)) {
        $forumnode = $PAGE->navbar;
    } else {
        $forumnode->make_active();
    }
    $node = $forumnode->add(format_string($vdiscussion->get_name()), $discussionviewurl);
    $node->display = false;
    if ($node && $vpost->get_id() != $vdiscussion->get_first_post_id()) {
        $node->add(format_string($vpost->get_subject()), $PAGE->url);
    }

    $isnestedv2displaymode = $displaymode == FORUM_MODE_NESTED_V2;

    if ($isnestedv2displaymode) {
        $PAGE->add_body_class('nested-v2-display-mode reset-style');
        $settingstrigger = $OUTPUT->render_from_template('mod_forum/settings_drawer_trigger', null);
        $PAGE->add_header_action($settingstrigger);
    } else {
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $PAGE->set_button(forum_search_form($course));
    }

    if (!$isnestedv2displaymode) {
        //echo $OUTPUT->heading(format_string($forum->get_name()), 2);
        //echo $OUTPUT->heading(format_string($discussion->get_name()), 3, 'discussionname');
    }

    $rendererfactory = \mod_forum\local\container::get_renderer_factory();
    $discussionrenderer = $rendererfactory->get_discussion_renderer($vforum, $vdiscussion, $displaymode);
    $orderpostsby = $displaymode == FORUM_MODE_FLATNEWEST ? 'created DESC' : 'created ASC';
    $replies = $postvault->get_replies_to_post($USER, $vpost, true, $orderpostsby);
    $postids = array_map(function($vpost) {
        return $vpost->get_id();
    }, array_merge([$vpost], array_values($replies)));


/*
    foreach($replies AS $_reply) {
        var_dump($_reply);
        $exposed = \block_edusupport\lib::expose_properties($_reply);
        //print_r($exposed);
        die();
        //$serialized = serialize($_reply);
        //$serialized = str_replace("mod_forum\\local\\entities\\", "", $serialized);
        //echo $serialized;
        die(serialize((array) $_reply));
        die(unserialize(serialize((array) $_reply)));
        $_reply = unserialize(serialize($_reply));
        var_dump($_reply);
        $replies = array($reply);
    }
    print_r($replies);
*/


    echo $OUTPUT->render_from_template('mod_forum/forum_discussion_threaded_posts', array('posts' =>$replies));

    //echo $discussionrenderer->render($USER, $vpost, $replies);


    $out = ob_get_contents();
    ob_end_clean();

    // \block_edusupport\lib::assign_role($coursecontext, false);
    $out = str_replace($CFG->wwwroot . '/mod/forum/discuss.php', $CFG->wwwroot . '/blocks/edusupport/issue.php', $out);
    $out = str_replace($CFG->wwwroot . '/mod/forum/post.php?reply=', $CFG->wwwroot . '/blocks/edusupport/issue.php?discussion=' . $discussionid . '&parent=', $out);
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
    $replacements = array(
        array($CFG->wwwroot . '/pluginfile.php/' . $modcontext->id . '/mod_forum/', $CFG->wwwroot . '/pluginfile.php/' . $modcontext->id . '/block_edusupport/'),
    );
    foreach ($replacements AS $replacement) {
        $out = str_replace($replacement[0], $replacement[1], $out);
    }
    echo $out;

    if (!empty($reply)) {
        //require_once($CFG->dirroot . '/mod/forum/classes/post_form.php');
        require_once($CFG->dirroot . '/blocks/edusupport/classes/post_form.php');
        $mform_post = new \block_edusupport_post_form($CFG->wwwroot . '/blocks/edusupport/issue.php', array(
            'course' => $course,
            'cm' => $cm,
            'coursecontext' => $coursecontext,
            'modcontext' => $modcontext,
            'forum' => $forum,
            'post' => '',
            'subscribe' => 0,
            'thresholdwarning' => $thresholdwarning,
            'edit' => $edit,

            ), 'post', '', array('id' => 'mformforum')
        );

        $draftitemid = \file_get_submitted_draft_itemid('attachments');
        //\file_prepare_draft_area($draftitemid, $modcontext->id, 'mod_forum', 'attachment', empty($post->id)?null:$post->id, \mod_forum_post_form::attachment_options($forum));
        \file_prepare_draft_area($draftitemid, $modcontext->id, 'mod_forum', 'attachment', null, \block_edusupport_post_form::attachment_options($forum));

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
        $currenttext = file_prepare_draft_area($draftid_editor, $modcontext->id, 'mod_forum', 'post', $postid, \block_edusupport_post_form::editor_options($modcontext, $postid), $post->message);

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
                'mailnow'=> 1,
                'userid'=>$USER->id,
                'parent'=>$reply,
                'discussion'=>$discussionid,
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
}

echo $OUTPUT->footer();
