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

$edit   = required_param('edit', PARAM_INT);

$url = new moodle_url('/local/edusupport/editpost.php', array('discussion'=>$discussionid, 'edit' => $edit));
$PAGE->set_url($url);

$context = \context_system::instance();
$PAGE->set_context($context);
require_login();

$issue = \local_edusupport\lib::get_issue($discussionid);
$discussion = $DB->get_record('forum_discussions', array('id' => $discussionid), '*', MUST_EXIST);
$PAGE->set_title($discussion->name);
$PAGE->set_heading($discussion->name);

if (!\local_edusupport\lib::is_supportteam()) {
    echo $OUTPUT->header();
    $tocmurl = new moodle_url('/course/view.php', array('id' => $issue->courseid));
    echo $OUTPUT->render_from_template('local_edusupport/alert', array(
        'content' => get_string('missing_permission', 'local_edusupport'),
        'type' => 'danger',
        'url' => $tocmurl->__toString(),
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

    $options = array();

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


    $postvault = $vaultfactory->get_post_vault();
    if (!$vpost = $postvault->get_from_id($edit)) {
        print_error("notexists", 'forum', "$CFG->wwwroot/mod/forum/view.php?f={$vforum->get_id()}");
    }
    $post = $DB->get_record('forum_posts', array('id' => $edit));

    require_once($CFG->dirroot . '/local/edusupport/classes/post_form.php');
    $thresholdwarning = forum_check_throttling($vforum, $cm);
    $mform_post = new \local_edusupport_post_form($CFG->wwwroot . '/local/edusupport/editpost.php?d=' . $discussionid . '&edit=' . $edit, array(
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

    $draftitemid = \file_get_submitted_draft_itemid('attachments');
    //\file_prepare_draft_area($draftitemid, $modcontext->id, 'mod_forum', 'attachment', empty($post->id)?null:$post->id, \mod_forum_post_form::attachment_options($forum));
    \file_prepare_draft_area($draftitemid, $modcontext->id, 'mod_forum', 'attachment', $post->id, \local_edusupport_post_form::attachment_options($forum));

    $draftid_editor = file_get_submitted_draft_itemid('message');
    $currenttext = file_prepare_draft_area($draftid_editor, $modcontext->id, 'mod_forum', 'post', $post->id, \local_edusupport_post_form::editor_options($modcontext, $post->id), $post->message);
    $mform_post->set_data(
        array(
            'attachments'=>$draftitemid,
            'general'=>$heading,
            'subject'=> 'Re: ' . $post->subject,
            'message'=>array(
                'text'  => $currenttext,
                'format'=> editors_get_preferred_format(),
                'itemid'=>$draftid_editor
            ),
            'mailnow'=> 1,
            'userid'=>$post->userid,
            'parent'=>$post->parent,
            'discussion'=>$discussionid,
            'course'=>$course->id,
            'forum' => $forum->id,
            'edit' => $edit,
            'itemid' => 0,
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
        redirect($CFG->wwwroot . '/local/edusupport/issue.php?d=' . $discussion->id);
    } else if ($fromform = $mform_post->get_data()) {
        if (empty($SESSION->fromurl)) {
            $errordestination = $PAGE->url->__toString();
        } else {
            $errordestination = $SESSION->fromurl;
        }

        $post->timestart     = isset($discussion->timestart) ? $discussion->timestart : 0;
        $post->timeend       = isset($discussion->timeend) ? $discussion->timeend : 0;
        $post->itemid        = $fromform->message['itemid'];
        $post->messageformat = $fromform->message['format'];
        $post->message       = $fromform->message['text'];
        // WARNING: the $fromform->message array has been overwritten, do not use it anymore!
        $post->messagetrust  = trusttext_trusted($modcontext);

        if (!forum_update_post($post, $mform_post)) {
            print_error("couldnotupdate", "forum", $errordestination);
        }
        // Move uploaded files manually.
        //$currenttext = file_prepare_draft_area($draftid_editor, $modcontext->id, 'mod_forum', 'post', $postid, \local_edusupport_post_form::editor_options($modcontext, $postid), $post->message);
        file_save_draft_area_files($fromform->attachments, $modcontext->id, 'mod_forum', 'attachment',
                    $post->id, \local_edusupport_post_form::editor_options($modcontext, $post->id));
        file_save_draft_area_files($fromform->attachments, $modcontext->id, 'mod_forum', 'post',
                    $post->id, \local_edusupport_post_form::editor_options($modcontext, $post->id));

        forum_trigger_post_updated_event($post, $discussion, $modcontext, $forum);

        if ($USER->id === $vpost->get_author_id()) {
            $message = get_string("postupdated", "forum");
        } else {
            $realuser = \core_user::get_user($vpost->get_author_id());
            $message = get_string("editedpostupdated", "forum", fullname($realuser));
        }

        $discussionurl = $CFG->wwwroot . '/local/edusupport/issue.php?d=' . $discussionid;

        redirect(
            $discussionurl,
            $message,
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
        exit;
    }
    echo $OUTPUT->header();
    $mform_post->display();
}

echo $OUTPUT->footer();
