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
 * @package   local_edusupport
 * @copyright 2018 Digital Education Society (http://www.dibig.at)
 * @author    Robert Schrenk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Help';
$string['edusupport:addinstance'] = 'Add eduSupport block';
$string['edusupport:myaddinstance'] = 'Add eduSupport block';

$string['archive'] = 'Archive';
$string['assigned'] = 'Assigned';
$string['autocreate_orggroup'] = 'Automatically create groups for eduvidual-Organizations';
$string['autocreate_usergroup'] = 'Automatically create a private group for user';
$string['be_more_accurate'] = 'Please be more accurate when describing your problem!';
$string['edusupport:canforward2ndlevel'] = 'Can forward issues to platform support team';
$string['cachedef_supportmenu'] = 'Cache for the supportmenu';
$string['changes_saved_successfully'] = 'Changes saved successfully.';
$string['changes_saved_fail'] = 'Changes could not be saved.';
$string['contactphone'] = 'Telephone';
$string['contactphone_missing'] = 'Please enter your telephone number';
$string['coursecategorydeletion'] = 'You are trying to remove a category, that contains supportforums. Please ensure, that you disable the support forums first!';
$string['courseconfig'] = 'Course config';
$string['create_issue'] = 'Contact support';
$string['create_issue_error_title'] = 'Error';
$string['create_issue_error_description'] = 'Your issue could not be stored!';
$string['create_issue_mail_success_description'] = 'Your issue has been stored. We will help you as soon as possible!';
$string['create_issue_success_title'] = 'Success';
$string['create_issue_success_description'] = 'Your issue has been stored. We will help you as soon as possible!';
$string['create_issue_success_description_mail'] = 'Your issue has been sent by mail. We will help you as soon as possible!';
$string['create_issue_success_goto'] = 'view issue';
$string['create_issue_success_responsibles'] = 'Contact person for this ticket is/are:';
$string['create_issue_success_close'] = 'close';
$string['cron:reminder:title'] = 'eduSupport reminder';
$string['cron:reminder:intro'] = 'This is a friendly reminder about open issues, that are assigned to you as eduSupporter!';
$string['cron:deleteexpiredissues:title'] = 'delete expired issues';
$string['dedicatedsupporter'] = 'Dedicated';
$string['dedicatedsupporter:not_successfully_set'] = 'Dedicated supporter could not be set';
$string['dedicatedsupporter:successfully_set'] = 'Successfully set dedicated supporter';
$string['description'] = 'Description';
$string['description_missing'] = 'Missing description';
$string['deletethreshhold'] = 'Delete closed issues after';
$string['deletethreshhold:description'] = 'Set the threshhold for the deletion of closed issues in the issues view. This only affects the issues page, but not the forum posts. 0 means to keep closed issues forever (not yet recommended)';
$string['goto_tutorials'] = 'Documents & Tutorials';
$string['goto_targetforum'] = 'Supportforum';
$string['edusupport:manage'] = 'Manage';
$string['email_to_xyz'] = 'Send mail to {$a->email}';
$string['extralinks'] = 'Extralinks';
$string['extralinks:description'] = 'If you enter links here, the "help"-Button will be a menu instead of button. It will include the "help"-Button as first element, and all extra links as additional links. Enter links line by line in the following form: linkname|url|faicon|target';
$string['faqlink'] = 'FAQ-link';
$string['faqlink:description'] =  'link to FAQ';
$string['faqread'] = 'faq read toggle';
$string['faqread:description'] =  'I confirm, that I have read the <a href="{$a}" target="_blank">FAQ</a> prior to posting my question.';
$string['header'] = 'Request for help in <i>{$a}</i>';
$string['holidaymode'] = 'Holidaymode';
$string['holidaymode_is_on'] = 'Holidaymode is on';
$string['holidaymode_is_on_descr'] = 'As long as you are on holidays, no new issues will be assigned to you.';
$string['holidaymode_end'] = 'End holidaymode';
$string['issue'] = 'Issue';
$string['issue:countcurrent'] = 'Open issues';
$string['issue:countassigned'] = 'Subscribed issues';
$string['issue:countother'] = 'Other issues';
$string['issue:countclosed'] = 'Closed issues';
$string['issue_assign'] = 'Assign issue';
$string['issue_assign_3rdlevel:post'] = '<a href="{$a->wwwroot}/user/view.php?id={$a->fromuserid}">{$a->fromuserfullname}</a> has assigned this issue to <a href="{$a->wwwroot}/user/view.php?id={$a->touserid}">{$a->touserfullname}</a>.';
$string['issue_assign_3rdlevel:postself'] = '<a href="{$a->wwwroot}/user/view.php?id={$a->fromuserid}">{$a->fromuserfullname}</a> has taken responsibility for this issue.';
$string['issue_assign_nextlevel'] = 'Forward to the {$a->sitename}-support team';
$string['issue_assign_nextlevel:error'] = 'Sorry, this issue could not be forwarded to the platform support team';
$string['issue_assign_nextlevel:post'] = '<a href="{$a->wwwroot}/user/view.php?id={$a->fromuserid}">{$a->fromuserfullname}</a> forwarded this issue to the platform support team';
$string['issue_assigned:subject'] = 'Supportissue assigned';
$string['issue_assigned:text'] = 'The support issue was assigned to <a href="{$a->wwwroot}/user/view.php?id={$a->id}">{$a->firstname} {$a->lastname}</a>!';
$string['issue_close'] = 'Close issue';
$string['issue_closed:subject'] = 'Issue closed';
$string['issue_closed:post'] = 'This issue closed was closed by <a href="{$a->wwwroot}/user/view.php?id={$a->fromuserid}">{$a->fromuserfullname}</a>. If you need further assistance please forward this issue again to the platform support team.';
$string['issue_responsibles:post'] = '
    <p>
        The responsibility for this issue has been assigned to: {$a->responsibles}!
    </p>
    <p>
        The managers of your organization can forward this issue to the {$a->sitename}-Support by clicking the button "Forward this ticket to the {$a->sitename}-Support" (visible only for managers on the right upper side of the page).
    </p>
';
$string['issue_responsibles:subject'] = 'Issue assigned';
$string['issue_revoke'] = 'Revoke this issue from higher support level';
$string['issue_revoke:error'] = 'Sorry, this issue could not be revoked from the higher support levels';
$string['issue_revoke:post'] = '<a href="{$a->wwwroot}/user/view.php?id={$a->fromuserid}">{$a->fromuserfullname}</a> revoked this issue from the higher support level';
$string['issue_revoke:subject'] = 'Supportissue revoked';
$string['issues'] = 'Issues';
$string['issues:assigned'] = 'Subscribed';
$string['issues:assigned:none'] = 'Currently you do not have any issue subscriptions';
$string['issues:closed'] = 'Closed issues';
$string['issues:current'] = 'My issues';
$string['issues:current:none'] = 'Seems you deserve a break - no issue left for your!';
$string['issues:other'] = 'Other issues';
$string['issues:other:none'] = 'Great, there seem to be no more problems on that planet!';
$string['issues:openmine'] = '{$a} for me';
$string['issues:opennosupporter'] = '{$a} unassigned';
$string['issues:openall'] = '{$a} total open';
$string['label:2ndlevel'] = 'Platform support team';
$string['missing_permission'] = 'Missing required permission';
$string['missing_targetforum'] = 'Missing target forum, must be configured!';
$string['missing_targetforum_exists'] = 'The configured target forum does not exist. Wrong configuration!';
$string['no_such_issue'] = 'This is not an open issue! You can navigate to the <a href="{$a->todiscussionurl}"><u>discussion page</u></a> or go <a href="{$a->toissuesurl}"><u>back to the issues overview</u></a>.';
$string['only_you'] = 'Only you and our team';
$string['phonefield'] = 'disable phone field';
$string['phonefield:description'] = 'Deactivate phone field in the form for creating issues';
$string['postto2ndlevel'] = 'Submit to platform support team';
$string['postto2ndlevel:description'] = 'Directly forward to the {$a->sitename}-Support!';
$string['privacy:metadata'] = 'This plugin does not store any personal data as it uses a forum as target.';
$string['priority'] = 'set priority';
$string['prioritylvl'] = 'enable priorities';
$string['prioritylvl:description'] =  'If enabled you can select priorities in the issues list';
$string['prioritylvl:low'] = 'low priority';
$string['prioritylvl:mid'] = 'mid priority';
$string['prioritylvl:high'] = 'high priority';
$string['relativeurlsupportarea'] = 'Relative URL to Supportarea';
$string['screenshot'] = 'Post screenshot';
$string['screenshot:description'] = 'A screenshot may help to solve the problem.';
$string['screenshot:generateinfo'] = 'To generate the screenshot the form will be hidden, and reappears afterwards.';
$string['screenshot:upload:failed'] = 'Preparation of file failed!';
$string['screenshot:upload:successful'] = 'File has been successfully prepared for uploading!';
$string['select_isselected'] = 'Currently selected';
$string['select_unavailable'] = 'Unavailable';
$string['send'] = 'Send';
$string['subject'] = 'Subject';
$string['subject_missing'] = 'Missing subject';
$string['support_area'] = 'Helpdesk & Tutorials';
$string['supportcourse'] = 'Supportcourse';
$string['supporters'] = 'Supporters';
$string['supporters:choose'] = 'Choose supporters';
$string['supporters:description'] = 'All users of the course, that are enrolled at least as "non-editing teacher" can be configured as supporter. Just enter anything as supportlevel to activate somebody as supporter!';
$string['supportforum:choose'] = 'Choose forums for eduSupport';
$string['supportforum:central:disable'] = 'disable';
$string['supportforum:central:enable'] = 'enable';
$string['supportforum:disable'] = 'disable';
$string['supportforum:enable'] = 'enable';
$string['supportlevel'] = 'Supportlevel';
$string['targetforum'] = 'Supportforum';
$string['targetforum:description'] = 'Please select the forum that should be used as target for support issues within this course. This forum will be forced to have some group mode enabled. The Plugin will create an individual group for every single user.';
$string['targetforum:core:description'] = 'All users will be automatically enrolled to the systemwide supportforum as soon as they create a support issue. Furthermore groups can be created and managed automatically to seperate support issues.';
$string['to_group'] = 'To';
$string['toggle'] = 'Course Supportforum';
$string['toggle:central'] = 'Central Supportforum';
$string['trackhost'] = 'Track host';
$string['trackhost:description'] = 'Big moodle sites may use an architecture with multiple webhosts. If you enable this option, edusupport will add the hostname of the used webhost to the issue.';
$string['userid'] = 'UserID';
$string['userlinks'] = 'enable userlinks';
$string['userlinks:description'] =  'show userlinks in issues list';
$string['your_issues'] = 'Your issues';
$string['webhost'] = 'Host';
$string['weburl'] = 'URL';

/* PrivaCY API */
$string['privacy:metadata:edusupport:subscr'] = 'All subscribed issues';
$string['privacy:metadata:edusupport:issues'] = 'Issues of supporters';
$string['privacy:metadata:edusupport:fieldid'] = 'Id';
$string['privacy:metadata:edusupport:issueid'] = 'Issue Id';
$string['privacy:metadata:edusupport:discussionid'] = 'Forum discussion Id ';
$string['privacy:metadata:edusupport:userid'] = 'User Id';
$string['privacy:metadata:edusupport:supporters'] = 'All defined supporters';
$string['privacy:metadata:edusupport:supportlvl'] = 'Supportlevel';
$string['privacy:metadata:edusupport:courseid'] = 'Course Id with supportforum';
$string['privacy:metadata:edusupport:currentsupporter'] = 'User Id of the assigned user';
$string['privacy:metadata:edusupport:opened'] = 'Status of issue';
