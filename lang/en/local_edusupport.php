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
$string['edusupport:canforward2ndlevel'] = 'Can forward issues to 2nd Level Support';
$string['cachedef_supportmenu'] = 'Cache for the supportmenu';
$string['changes_saved_successfully'] = 'Changes saved successfully.';
$string['changes_saved_fail'] = 'Changes could not be saved.';
$string['contactphone'] = 'Telephone';
$string['contactphone_missing'] = 'Please enter your telephone number';
$string['coursecategorydeletion'] = 'You are trying to remove a category, that contains supportforums. Please ensure, that you disable the support forums first!';
$string['courseconfig'] = 'Course config';
$string['create_issue'] = 'Create issue';
$string['create_issue_error_title'] = 'Error';
$string['create_issue_error_description'] = 'Your issue could not be stored!';
$string['create_issue_mail_success_description'] = 'Your issue has been stored. We will help you as soon as possible!';
$string['create_issue_success_title'] = 'Success';
$string['create_issue_success_description'] = 'Your issue has been stored. We will help you as soon as possible!';
$string['create_issue_success_description_mail'] = 'Your issue has been sent by mail. We will help you as soon as possible!';
$string['create_issue_success_goto'] = 'view issue';
$string['create_issue_success_close'] = 'close';
$string['cron:reminder:title'] = 'eduSupport reminder';
$string['cron:reminder:intro'] = 'This is a friendly reminder about open issues, that are assigned to you as eduSupporter!';
$string['dedicatedsupporter'] = 'Dedicated';
$string['dedicatedsupporter:not_successfully_set'] = 'Dedicated supporter could not be set';
$string['dedicatedsupporter:successfully_set'] = 'Successfully set dedicated supporter';
$string['description'] = 'Description';
$string['description_missing'] = 'Missing description';
$string['goto_tutorials'] = 'Documents & Tutorials';
$string['goto_targetforum'] = 'Supportforum';
$string['edusupport:manage'] = 'Manage';
$string['email_to_xyz'] = 'Send mail to {$a->email}';
$string['extralinks'] = 'Extralinks';
$string['extralinks:description'] = 'If you enter links here, the "help"-Button will be a menu instead of button. It will include the "help"-Button as first element, and all extra links as additional links. Enter links line by line in the following form: linkname|url|faicon|target';
$string['header'] = 'Request for help in <i>{$a}</i>';
$string['issue'] = 'Issue';
$string['issue_assign'] = 'Assign issue';
$string['issue_assign_3rdlevel:post'] = '<a href="{$a->wwwroot}/profile/view.php?id={$a->fromuserid}">{$a->fromuserfullname}</a> has assigned this issue to <a href="{$a->wwwroot}/profile/view.php?id={$a->touserid}">{$a->touserfullname}</a>.';
$string['issue_assign_3rdlevel:postself'] = '<a href="{$a->wwwroot}/profile/view.php?id={$a->fromuserid}">{$a->fromuserfullname}</a> has taken responsibility for this issue.';
$string['issue_assign_nextlevel'] = 'Forward this issue to 2nd level support';
$string['issue_assign_nextlevel:error'] = 'Sorry, this issue could not be forwarded to the 2nd level support';
$string['issue_assign_nextlevel:post'] = '<a href="{$a->wwwroot}/profile/view.php?id={$a->fromuserid}">{$a->fromuserfullname}</a> forwarded this issue to the 2nd level support';
$string['issue_assigned:subject'] = 'Supportissue assigned';
$string['issue_assigned:text'] = 'The support issue was assigned to <a href="{$a->wwwroot}/profile/view.php?{$a->id}">{$a->firstname} {$a->lastname}</a>!';
$string['issue_revoke'] = 'Revoke this issue from higher support level';
$string['issue_revoke:error'] = 'Sorry, this issue could not be revoked from the higher support levels';
$string['issue_revoke:post'] = '<a href="{$a->wwwroot}/profile/view.php?id={$a->fromuserid}">{$a->fromuserfullname}</a> revoked this issue from the higher support level';
$string['issue_revoke:subject'] = 'Supportissue revoked';
$string['issue_close'] = 'Close issue';
$string['issue_closed:subject'] = 'Issue closed';
$string['issue_closed:post'] = 'This issue closed was closed by <a href="{$a->wwwroot}/profile/view.php?id={$a->fromuserid}">{$a->fromuserfullname}</a>. If you need further assistance please forward this issue again to the 2nd level support.';
$string['issues'] = 'Issues';
$string['issues:assigned'] = 'Subscribed';
$string['issues:current'] = 'My issues';
$string['issues:other'] = 'Other issues';
$string['issues:openmine'] = '{$a} for me';
$string['issues:opennosupporter'] = '{$a} unassigned';
$string['issues:openall'] = '{$a} total open';
$string['missing_permission'] = 'Missing required permission';
$string['missing_targetforum'] = 'Missing target forum, must be configured!';
$string['missing_targetforum_exists'] = 'The configured target forum does not exist. Wrong configuration!';
$string['only_you'] = 'Only you and our team';
$string['postto2ndlevel'] = 'Call 2nd level support';
$string['postto2ndlevel:description'] = 'It seems you are the first level support of the forum you selected. If you want to, you can directly call the 2nd level support for your issue!';
$string['privacy:metadata'] = 'This plugin does not store any personal data as it uses a forum as target.';
$string['relativeurlsupportarea'] = 'Relative URL to Supportarea';
$string['screenshot'] = 'Post screenshot';
$string['screenshot:description'] = 'A screenshot may help to solve the problem.';
$string['screenshot:generateinfo'] = 'To generate the screenshot the form will be hidden, and reappears afterwards.';
$string['select_isselected'] = 'Currently selected';
$string['select_unavailable'] = 'Unavailable';
$string['subject'] = 'Subject';
$string['subject_missing'] = 'Missing subject';
$string['support_area'] = 'Helpdesk & Tutorials';
$string['supporters'] = 'Supporters';
$string['supporters:choose'] = 'Choose supporters';
$string['supporters:description'] = 'All users of the course, that are enrolled at least as "non-editing teacher" can be configured as supporter. Just enter anything as supportlevel to activate somebody as supporter!';
$string['supportforum:choose'] = 'Choose forums for eduSupport';
$string['supportforum:disable'] = 'Disable as supportforum';
$string['supportforum:enable'] = 'Enable as supportforum';
$string['supportlevel'] = 'Supportlevel';
$string['targetforum'] = 'Supportforum';
$string['targetforum:description'] = 'Please select the forum that should be used as target for support issues within this course. This forum will be forced to have some group mode enabled. The Plugin will create an individual group for every single user.';
$string['targetforum:core:description'] = 'All users will be automatically enrolled to the systemwide supportforum as soon as they create a support issue. Furthermore groups can be created and managed automatically to seperate support issues.';
$string['to_group'] = 'Visible to';
$string['toggle'] = 'Toggle';
$string['userid'] = 'UserID';
$string['your_issues'] = 'Your issues';
