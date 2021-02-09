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

// We fake a forum discussion here.
// This code is mainly taken from /mod/forum/discuss.php.
$d = optional_param('d', 0, PARAM_INT); // discussionid.
$discussion = optional_param('discussion', 0, PARAM_INT); // discussionid.
$discussionid = $discussion | $d;
$replyto = optional_param('replyto', 0, PARAM_INT);          // If set, we reply to this post.

$parent = optional_param('parent', 0, PARAM_INT);        // If set, then display this post and all children.
$mode   = optional_param('mode', 0, PARAM_INT);          // If set, changes the layout of the thread
$move   = optional_param('move', 0, PARAM_INT);          // If set, moves this discussion to another forum
$mark   = optional_param('mark', '', PARAM_ALPHA);       // Used for tracking read posts if user initiated.
$postid = optional_param('postid', 0, PARAM_INT);        // Used for tracking read posts if user initiated.
$pin    = optional_param('pin', -1, PARAM_INT);          // If set, pin or unpin this discussion.

$edit   = optional_param('edit', 0, PARAM_INT);
$delete   = optional_param('delete', 0, PARAM_INT);

$url = new moodle_url('/local/edusupport/issue.php', array('discussion'=>$discussionid, 'replyto' => $replyto, 'delete' => $delete));
if ($parent !== 0) {
    $url->param('parent', $parent);
}
$PAGE->set_url($url);

$context = \context_system::instance();
$PAGE->set_context($context);
require_login();

$issue = \local_edusupport\lib::get_issue($discussionid, false);
$discussion = $DB->get_record('forum_discussions', array('id' => $discussionid), '*', MUST_EXIST);
$PAGE->set_title($discussion->name);
$PAGE->set_heading($discussion->name);

if (!\local_edusupport\lib::is_supportteam() && !is_siteadmin()) {
    echo $OUTPUT->header();
    $cm = \get_coursemodule_from_instance('forum', $discussion->forum);
    $tocmurl = new moodle_url('/mod/forum/view.php', array('id' => $cm->id));
    echo $OUTPUT->render_from_template('local_edusupport/alert', array(
        'content' => get_string('missing_permission', 'local_edusupport'),
        'type' => 'danger',
        'url' => $tocmurl->__toString(),
    ));
} else if (empty($issue->id)) {
    echo $OUTPUT->header();
    $toissuesurl = new moodle_url('/local/edusupport/issues.php', array());
    $todiscussionurl = new moodle_url('/mod/forum/discuss.php', array('d' => $discussionid));
    echo $OUTPUT->render_from_template('local_edusupport/alert', array(
        'content' => get_string('no_such_issue', 'local_edusupport', array(
            'todiscussionurl' => $todiscussionurl->__toString(),
            'toissuesurl' => $toissuesurl->__toString(),
        )),
        'type' => 'danger',
    ));
} else {
    $course = $DB->get_record('course', array('id' => $discussion->course), '*', MUST_EXIST);
    $forum = $DB->get_record('forum', array('id' => $discussion->forum), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
    $post = $DB->get_record('forum_posts', array('discussion' => $discussionid, 'parent' => 0));
    $coursecontext = \context_course::instance($forum->course);
    $modcontext = \context_module::instance($cm->id);

    $PAGE->set_title("$course->shortname: ".format_string($discussion->name));
    $PAGE->set_heading($course->fullname);

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


    if (!empty($replyto)) {
        //require_once($CFG->dirroot . '/mod/forum/classes/post_form.php');
        require_once($CFG->dirroot . '/local/edusupport/classes/post_form.php');
        $thresholdwarning = forum_check_throttling($vforum, $cm);
        $mform_post = new \local_edusupport_post_form($CFG->wwwroot . '/local/edusupport/issue.php?d=' . $discussionid . '&replyto=' . $replyto, array(
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
        $draftitemid = \file_get_submitted_draft_itemid('attachments');
        //\file_prepare_draft_area($draftitemid, $modcontext->id, 'mod_forum', 'attachment', empty($post->id)?null:$post->id, \mod_forum_post_form::attachment_options($forum));
        \file_prepare_draft_area($draftitemid, $modcontext->id, 'mod_forum', 'attachment', null, \local_edusupport_post_form::attachment_options($forum));
        $draftid_editor = file_get_submitted_draft_itemid('message');
        $currenttext = file_prepare_draft_area($draftid_editor, $modcontext->id, 'mod_forum', 'post', $postid, \local_edusupport_post_form::editor_options($modcontext, $postid), $post->message);

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
                'discussionsubscribe' => 0,
                'mailnow'=> 1,
                'userid'=>$USER->id,
                'parent'=>$replyto,
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
            redirect('/local/edusupport/issue.php?d=' . $discussion->id);
        } else if ($fromform = $mform_post->get_data()) {
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

                    $message = get_string("postaddedsuccess", "forum", fullname($USER));
                    $discussionurl = $CFG->wwwroot . '/local/edusupport/issue.php?d=' . $discussionid;

                    redirect(
                        $discussionurl,
                        $message,
                        null,
                        \core\output\notification::NOTIFY_SUCCESS
                    );
                } else {
                    $errordestination = $CFG->wwwroot . '/local/edusupport/issue.php?d=' . $discussionid;
                    print_error("couldnotadd", "forum", $errordestination);
                }
            }
        }
    }

    echo $OUTPUT->header();

    $options = array();

    if (!empty($issue->currentsupporter)) {
        $supporter = $DB->get_record('local_edusupport_supporters', array('userid' => $issue->currentsupporter));
        $user = $DB->get_record('user', array('id' => $supporter->userid));

        $options[] = array(
            "title" => \fullname($user) . ' (' . (!empty($supporter->supportlevel) ? $supporter->supportlevel : get_string('label:2ndlevel', 'local_edusupport')) . ')',
            "class" => '',
            //"icon" => 'i/checkpermissions',
            "href" => $CFG->wwwroot . '/user/view.php?id' . $supporter->id,
        );
    }

    $options[] = array(
        "title" => get_string('issue_assign', 'local_edusupport'),
        "class" => 'btn-secondary',
        "icon" => 'i/assignroles',
        "href" => '#',
        "onclick" => "require(['local_edusupport/main'], function(MAIN){ MAIN.assignSupporter($discussionid); }); return false;",
    );
    $options[] = array(
        "title" => get_string('issue_close', 'local_edusupport'),
        "class" => 'btn-primary',
        "icon" => 't/approve',
        "href" => '#',
        "onclick" => "require(['local_edusupport/main'], function(MAIN){ MAIN.closeIssue($discussionid); }); return false;",
    );
    echo $OUTPUT->render_from_template('local_edusupport/issue_options', array('options' => $options));

    // We capture the output, as we need to modify links to attachments!
    ob_start();

    if (!empty($delete)) {
        $deletepost = $DB->get_record('forum_posts', array('id' => $delete));
        if (!empty($deletepost->id) && $deletepost->userid == $USER->id) {
            $vaultfactory = mod_forum\local\container::get_vault_factory();
            $postvault = $vaultfactory->get_post_vault();
            $postentity = $postvault->get_from_id($delete);
            $managerfactory = mod_forum\local\container::get_manager_factory();
            $legacydatamapperfactory = mod_forum\local\container::get_legacy_data_mapper_factory();
            $forumdatamapper = $legacydatamapperfactory->get_forum_data_mapper();
            $postdatamapper = $legacydatamapperfactory->get_post_data_mapper();
            forum_delete_post(
                $postdatamapper->to_legacy_object($postentity),
                true, // capability
                $vforum->get_course_record(),
                $vforum->get_course_module_record(),
                $forumdatamapper->to_legacy_object($vforum)
            );
            echo $OUTPUT->render_from_template('local_edusupport/alert', array(
                'content' => get_string('deletedpost', 'mod_forum'),
                'type' => 'success'
            ));
        }
    }

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
        print_error("notexists", 'forum', "$CFG->wwwroot/mod/forum/view.php?f={$vforum->get_id()}");
    }

    $post = $DB->get_record('forum_posts', array('id' => $parent));

    $rendererfactory = \mod_forum\local\container::get_renderer_factory();
    $discussionrenderer = $rendererfactory->get_discussion_renderer($vforum, $vdiscussion, $displaymode);
    $orderpostsby = $displaymode == FORUM_MODE_FLATNEWEST ? 'created DESC' : 'created ASC';
    $replies = $postvault->get_replies_to_post($USER, $vpost, true, $orderpostsby);
    $postids = array_map(function($vpost) {
        return $vpost->get_id();
    }, array_merge([$vpost], array_values($replies)));

    // we use the first admin account for rendering the forum page.
    $admins = explode(',', get_config('core', 'siteadmins'));
    $user = $DB->get_record('user', array('id' => $admins[0]));
    echo $discussionrenderer->render($user, $vpost, $replies);

    $PAGE->requires->js_call_amd("local_edusupport/main", "injectReplyButtons", array($discussionid));

    // Now catch the output from the renderer and modify some parts.
    $out = ob_get_contents();
    ob_end_clean();

    $out = str_replace("class=\"discussion-settings-menu\"", "class=\"discussion-settings-menu\" style=\"display: none;\"", $out);
    $out = str_replace("class=\"next-discussion\"", "class=\"next-discussion\" style=\"display: none;\"", $out);
    $out = str_replace("class=\"prev-discussion\"", "class=\"prev-discussion\" style=\"display: none;\"", $out);

    $out = str_replace($CFG->wwwroot . '/mod/forum/discuss.php', $CFG->wwwroot . '/local/edusupport/issue.php', $out);
    $out = str_replace($CFG->wwwroot . '/mod/forum/post.php?reply=', $CFG->wwwroot . '/local/edusupport/issue.php?discussion=' . $discussionid . '&parent=', $out);
    $out = str_replace($CFG->wwwroot . '/mod/forum/post.php?edit=', $CFG->wwwroot . '/local/edusupport/editpost.php?discussion=' . $discussionid . '&edit=', $out);
    $out = str_replace($CFG->wwwroot . '/mod/forum/post.php?delete=', $CFG->wwwroot . '/local/edusupport/issue.php?discussion=' . $discussionid . '&delete=', $out);

    $starts = array(
        //'<div class="singleselect d-inline-block">',
        //'<div class="discussion-nav clearfix">',
        '<div class="commands">',
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
        array($CFG->wwwroot . '/pluginfile.php/' . $modcontext->id . '/mod_forum/', $CFG->wwwroot . '/pluginfile.php/' . $modcontext->id . '/local_edusupport/'),
    );
    foreach ($replacements AS $replacement) {
        $out = str_replace($replacement[0], $replacement[1], $out);
    }
    echo $out;

    if (!empty($replyto)) {
        $mform_post->display();
    }
}

echo $OUTPUT->footer();
