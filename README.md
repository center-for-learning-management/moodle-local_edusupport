# moodle-local_edusupport

## How to configure edusupport?
This plugin allows to manage a moodle based decentralized helpdesk with three support levels. It basically works with Moodle forums. After you installed the plugin, you should navigate to the Website Administration > Plugins > Local plugins > Help and set up your support site's team (only 2nd and 3rd level).

### Choose your support team (only 2nd and 3rd level supporters)

![Choose support team](/doc/choosesupporters.png)

If you enter a support level, this user will belong to the 3rd level, you can enter any label here (e.g. a special topic for that user like 'technical', 'pedagogy', ...), so that the 2nd level can decide to whom they should forward special issues. Leave the support level empty for 2nd level support users.

### Choose your support forums

Now you need to create at least one course with one general forum and mark it as a supportforum in the course settings of each course (only site administrators can do that). You can also decide here if a forum is marked as site wide supportforum. Only 1 forum can be set as site wide supportforum. If you enable it, all users that open the "help"-modal will be automatically enrolled into that course with the student-role and a group for that user will be created. This ensures private communication in that supportforum, so it is recommended to set the groupmode to "separated groups" in that forum.

![Choose support forums](/doc/chooseforums.png)

All users with the teacher role in this course will be the 1st level support. You can enable separated groups, visible groups or no group mode - doesn't matter. Users will receive notifications as usual if they subscribe the forum.

You can set one team member of the site's support team (2nd and 3d Level) as a dedicated support user. This means, that all issues forwarded to 2nd level will be automatically assigned to the responsibility of this team member.

Once a forum is activated as supportforum some capabilities in the forum module and course are set to prevent the deletion.

The members of the site's support team (2nd and 3rd level support) will not receive notifications as long as the ticket was not forwarded to the 2nd level. They need not be enrolled to the course itself and will not be able to read all discussions. They will only be granted access to those discussions that have been forwarded by the 1st level support.

## How to post issues?

As we are using normal Moodle forums, users can go to the forum and create new discussions. But possibly not everybody finds that very user friendly, and information can get lost (error messages that appeared, the URL where the problem occurred, ...)

Therefore we integrated a "help"-button (works in boost theme, not tested elsewhere) in the usermenu toolbox (near the conversations-icon).

![The help button](/doc/help-button.png)

If a user presses this button a modal dialog appears and the user can describe the problem directly on the page it occurred. Optionally a screenshot of the page can be attached.

![The help modal](/doc/help-modal.png)

If the user has access to several supportforums he can choose the target, also if the forums use the group feature, the target group can be chosen. If the user has not access to any supportforum the system automatically falls back to an email mode, and the issue is sent to the mailaddress of the supportusers specified in the site administration.

If a user has the capability 'edusupport:canforward2ndlevel', which is set by default for the teacher role (1st Level Support), it is possible to forward the issue directly to the 2nd level on creation. You can also decide to grant this capability to other roles as well.


## Who is responsible for a ticket?

1. The 1st level support, which means: all teachers in the support course. These users can forward an issue to the 2nd level. On the discussion page they will find a button "Forward this issue to 2nd level support". If a dedicated supporter was set for this forum, this user will be named to be responsible for this ticket. Otherwise a random user from the 2nd level support team will be selected. For transparency reasons a post on behalf of the 1st level support user is automatically added to the discussion. Anybody from the 1st level support can also revoke the issue from the 2nd level.

![Forward issue to 2nd level](/doc/issue-forward.png)

2. All support members of 2nd and 3rd level will find a link "Issues" in the left panel. Under that link they have access to an overview page that shows them all issues, that currently belong to the 2nd or 3rd level support. These issues are grouped to "My issues" (I am responsible), "Subscribed" (I am not responsible, but will receive notifications), "Other issues" (I will not receive any notification, but can access and subscribe).

![Manage issues](/doc/issue-manage.png)

3. Members of the 2nd and 3rd level support can now assign responsibilities. Once the issue is solved, they can mark it as solved on the discussion page.

![Issue discussion page](/doc/issue-discussion.png)

4. When an issue is closed any subscriptions of the support team will be removed. An automated post on behalf of the closing user makes this transparent to the 1st level and end user. No access to the discussion will be possible anymore, unless the 1st level support forwards the issue again.

This overviewpage allows access to the discussions that represent the issue. All support team members can access only these discussions. On the overview page itself they can subscribe for notifications, if they are not responsible, they can also unsubscribe.
 They can forward the responsibility to someone else on the discussion page, or can take subscribe / unsubscribe from notifications.


1. It splits your support team into the three support levels, and each issue can be forwarded to the next level.
    * 1st level: pedagogic issues or simple technical questions
    * 2nd level:  
2. The first level works on course level. You can have as much support courses for the first level as you like. In our case we have a bunch of schools in our Moodle, and each school has its own support course with the 1st level support from staff of that school.

This block allows users to instantly post problems to a standard forum from wherever they are on the site, including the possibility to attach a screenshot of the current page. It is recommended to use separated groups within this forum.

For that purpose eduSupport creates a group for each user to ensure a private communication channel to the support team. Users can be automatically enrolled to the course containing the support-forum when posting a problem, if this option is enabled in admin settings.
