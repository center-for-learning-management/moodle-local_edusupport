# moodle-block_edusupport
Plugin to manage a moodle based helpdesk

This block allows users to instantly post problems to a standard forum from wherever they are on the site, including the possibility to attach a screenshot of the current page. It is recommended to use separated groups within this forum.

For that purpose eduSupport creates a group for each user to ensure a private communication channel to the support team. Users can be automatically enrolled to the course containing the support-forum when posting a problem, if this option is enabled in admin settings.

## Required configuration

* Before the plugin can be used it is necessary to add the block in a course that is dedicated to be the support course.
* Click on "course configuration" in the block
* Assign the support team by specifying a support level. You can use any label here.
* Select the desired forum by clicking the "Select"-Button in the column "System"
* Optionally you can set another forum as archive. Closed issues will be automatically moved there.
