/* eslint-disable max-len, no-console, jsdoc/require-param, jsdoc/require-param-type */
define(
  ['jquery', 'core/ajax', 'core/notification', 'core/str', 'core/url', 'core/modal_save_cancel', 'core/modal_events'],
  function($, AJAX, NOTIFICATION, STR, URL, ModalSaveCancel, ModalEvents) {
    return {
      debug: 0,
      triggerSteps: 0,
      assignSupporter: function(discussionid /* , userid*/) {
        var userid = 0;
        var MAIN = this;
        if (MAIN.debug > 0) {
          console.log('local_edusupport/main:assignSupporter(discussionid, userid)', discussionid, userid);
        }
        // Show a selection of possible supporters.
        AJAX.call([{
          methodname: 'local_edusupport_get_potentialsupporters',
          args: {discussionid: discussionid},
          done: async function(result) {
            try {
              result = JSON.parse(result);
            } catch (e) {
            }
            if (MAIN.debug > 0) {
              console.log('local_edusupport_external:local_edusupport_get_potentialsupporters', result);
            }
            var supportlevels = Object.keys(result.supporters);
            var body = '<input type="hidden" value="' + discussionid + '" />';
            body += '<select>';
            for (var a = 0; a < supportlevels.length; a++) {
              body += '<optgroup label="' + supportlevels[a] + '">';
              for (var b = 0; b < result.supporters[supportlevels[a]].length; b++) {
                var supporter = result.supporters[supportlevels[a]][b];
                body += '<option value="' + supporter.userid + '"' + ((supporter.selected) ? ' selected="selected"' : '') + '>' + supporter.firstname + ' ' + supporter.lastname + '</option>';
              }
              body += '</optgroup>';
            }
            body += '</select>';

            const modal = await ModalSaveCancel.create({
              title: STR.get_string('select', 'core'),
              body: body,
              show: true,
            });
            modal.getRoot().on(ModalEvents.save, function(e) {
              e.preventDefault();
              var discussionid = $(this).find('.modal-body input').val();
              var supporterid = $(this).find('.modal-body select').val();
              var data = {'discussionid': discussionid, 'supporterid': supporterid};
              AJAX.call([{
                methodname: 'local_edusupport_set_currentsupporter',
                args: data,
                done: function(result) {
                  if (result == 1) {
                    top.location.reload();
                  } else {
                    alert('Error: ' + result);
                  }
                },
                fail: NOTIFICATION.exception
              }]);
            });
          },
          fail: NOTIFICATION.exception
        }]);
      },
      /**
       * Checks if a particular support form has a screenshot. If not, it hides the modal and creates one.
       */
      injectHelpButton: function(supportmenu) {
        // OBSOLETE SINCE 2021083000
        var MAIN = this;
        if (MAIN.debug > 0) {
          console.log('local_edusupport/main:injectHelpButton(supportmenu)');
        }
        $(supportmenu).insertBefore($('.nav .usermenu'));
      },
      /**
       * Scans the page for all discussion posts and adds a reply-button.
       */
      injectReplyButtons: function(discussion) {
        STR.get_strings([
          {'key': 'reply', component: 'forum'},
        ]).done(function(s) {
            // Remove default reply links.
            $('a[href*="issue.php?discussion=' + discussion + '&parent="]').remove();
            $('a[href*="issue.php?discussion=' + discussion + '&delete="]').remove();
            $('a[href*="post.php?prune="]').remove();
            // Add our customized reply links.
            $('.forum-post-container>.forumpost').each(function() {
              var postid = $(this).attr('data-post-id');
              if ($(this).find('.reply-' + postid).length == 0) {
                $(this).find('.post-actions:first-child').append(
                  $('<a data-region="post-action" class="btn btn-link reply-' + postid + '" title="' + s[0] + '" aria-label="' + s[0] + '" role="menuitem" tabindex="-1">')
                    .html(s[0]).attr('href', URL.relativeUrl('/local/edusupport/issue.php?discussion=' + discussion + '&replyto=' + postid))
                );
              }
            });
          }
        ).fail(NOTIFICATION.exception);
      },
      /**
       * Close an issue.
       **/
      closeIssue: function(discussionid) {
        console.log('closeIssue(discussionid)', discussionid);
        AJAX.call([{
          methodname: 'local_edusupport_close_issue',
          args: {discussionid: discussionid},
          done: function(result) {
            console.log(result);
            if (result == 1) {
              top.location.href = URL.relativeUrl('/local/edusupport/issues.php', {});
            } else {
              NOTIFICATION.exception(result);
              // Alert('Error: ' + result);
            }
          },
          fail: NOTIFICATION.exception
        }]);
      },
      /**
       * Let's inject a button to call the 2nd level support.
       * @param discussionid
       * @param isissue determines if this issue is already at higher support levels.
       * @param sitename the full sitename
       */
      injectForwardButton: function(discussionid, isissue, sitename) {
        if (this.debug) {
          console.log('local_edusupport/main:injectForwardButton(discussionid, isissue)', discussionid, isissue);
        }
        if (!discussionid) {
          return;
        }
        STR.get_strings([
          {
            key: (typeof isissue !== 'undefined' && isissue) ? 'issue_revoke' : 'issue_assign_nextlevel',
            component: 'local_edusupport',
            param: {
              sitename: sitename,
            }
          },
        ]).done(function(s) {
          // TODO: remove the onclick below, that is bad design!
            $('#page-content div[role="main"] .discussionname').parent().prepend(
              $('<a href="#">')
                .attr('onclick', "require(['local_edusupport/main'], function(MAIN) { MAIN.injectForwardModal(" + discussionid + ", " + isissue + ", '" + sitename + "'); }); return false;")
                .attr('style', 'float: right')
                .addClass("btn btn-primary")
                .html(s[0])
            );
          }
        ).fail(NOTIFICATION.exception);
      },
      injectTest: function() {
        var discussionname = $(".discussionname");
        if (discussionname.text().substr(0, 2) == "! ") {
          discussionname.addClass("alert-warning");
        }
        if (discussionname.text().substr(0, 2) == "!!") {
          discussionname.addClass("alert-danger");
        }


      },
      injectForwardModal: async function(discussionid, revoke, sitename) {
        try {
          const s = await STR.get_strings([
            {
              key: 'confirm',
              component: 'core'
            },
            {
              key: (typeof revoke !== 'undefined' && revoke) ? 'issue_revoke' : 'issue_assign_nextlevel',
              component: 'local_edusupport',
              param: {
                sitename: sitename,
              }
            },
          ]);
          const modal = await ModalSaveCancel.create({
            title: s[0],
            body: s[1],
            show: true,
          });
          modal.getRoot().on(ModalEvents.save, function() {
            top.location.href = URL.relativeUrl('/local/edusupport/forward_2nd_level.php', {d: discussionid, revoke: revoke});
          });
        } catch (e) {
          NOTIFICATION.exception(e);
        }
      },

      triggerSpinner: function(steps) {
        var MAIN = this;
        MAIN.triggerSteps += steps;
        if (MAIN.triggerSteps > 0) {
          if ($('body #edusupport-spinner').length == 0) {
            $('body').append($('<div id="edusupport-spinner" class="spinner-grid show"><div></div><div></div><div></div><div></div></div>'));
          }
        } else {
          $('#edusupport-spinner').remove();
        }
      },
    };
  });
