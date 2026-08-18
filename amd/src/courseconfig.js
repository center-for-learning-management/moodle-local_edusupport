/* eslint-disable max-len, no-console */
define(
  ['jquery', 'core/ajax', 'core/notification'],
  function ($, AJAX, NOTIFICATION) {
    return {
      setArchive: function (uniqid, forumid) {
        console.log('setArchive(uniqid, forumid)', uniqid, forumid);
        AJAX.call([{
          methodname: 'local_edusupport_set_archive',
          args: {forumid: forumid},
          done: function (result) {
            console.log('Result is', result);
            if (result == '1') {
              $('#' + uniqid + '-forum-' + forumid).css('background-color', 'rgba(0, 255, 0, 0.2)');
            } else {
              $('#' + uniqid + '-forum-' + forumid).css('background-color', 'rgba(255, 0, 0, 0.2)');
            }
            setTimeout(function () {
              $('#' + uniqid + '-forum-' + forumid).css('background-color', '');
            }, 500);
            top.location.reload();
          },
          fail: NOTIFICATION.exception
        }]);
      },
      setDefault: function (uniqid, forumid, asglobal) {
        if (!asglobal) {
          asglobal = 0;
        }

        console.log('setDefault(uniqid, forumid, asglobal)', uniqid, forumid, asglobal);
        AJAX.call([{
          methodname: 'local_edusupport_set_default',
          args: {forumid: forumid, asglobal: asglobal},
          done: function (result) {
            console.log('Result is', result);
            if (result == '1') {
              $('#' + uniqid + '-forum-' + forumid).css('background-color', 'rgba(0, 255, 0, 0.2)');
            } else {
              $('#' + uniqid + '-forum-' + forumid).css('background-color', 'rgba(255, 0, 0, 0.2)');
            }
            setTimeout(function () {
              $('#' + uniqid + '-forum-' + forumid).css('background-color', '');
            }, 500);
            top.location.reload();
          },
          fail: NOTIFICATION.exception
        }]);
      },
      setSupporter: function (uniqid, courseid, userid, supportlevel) {
        console.log('setSupporter(uniqid, courseid,userid,supportlevel)', uniqid, courseid, userid, supportlevel);
        AJAX.call([{
          methodname: 'local_edusupport_set_supporter',
          args: {courseid: courseid, userid: userid, supportlevel: supportlevel},
          done: function (result) {
            console.log('Result is', result);
            if (result == '1') {
              $('#' + uniqid + '-setsupporter-' + userid).css('background-color', 'rgba(0, 255, 0, 0.2)');
            } else {
              $('#' + uniqid + '-setsupporter-' + userid).css('background-color', 'rgba(255, 0, 0, 0.2)');
            }
            setTimeout(function () {
              $('#' + uniqid + '-setsupporter-' + userid).css('background-color', '');
            }, 500);
          },
          fail: NOTIFICATION.exception
        }]);
      },
    };
  });
