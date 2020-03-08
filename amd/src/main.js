define(
    ['jquery', 'core/ajax', 'core/notification', 'core/str', 'core/url', 'core/modal_factory', 'core/modal_events'],
    function($, AJAX, NOTIFICATION, STR, URL, ModalFactory, ModalEvents) {
    return {
        triggerSteps: 0,
        assignSupporter: function(discussionid, userid){
            if (typeof userid === 'undefined') {
                // Show a selection of possible supporters.
                AJAX.call([{
                    methodname: 'block_edusupport_get_potentialsupporters',
                    args: { discussionid: discussionid },
                    done: function(result) {
                        try { result = JSON.parse(result); } catch(e) {}
                        console.log('block_edusupport_get_potentialsupporters', result);
                        var supportlevels = Object.keys(result.supporters);
                        var body = '<input type="hidden" value="' + discussionid + '" />';
                        body += '<select>';
                        for (var a = 0; a < supportlevels.length; a++) {
                            body += '<optgroup label="' + supportlevels[a] + '">';
                            for (var b = 0; b < result.supporters[supportlevels[a]].length; b++) {
                                var supporter = result.supporters[supportlevels[a]][b];
                                body += '<option value="' + supporter.id + '"' + ((supporter.selected)?' selected="selected"':'') + '>' + supporter.firstname + ' ' + supporter.lastname + '</option>';
                            }
                            body += '</optgroup>';
                        }
                        body += '</select>';

                        //console.log(result);
                        ModalFactory.create({
                            title: STR.get_string('select', 'core'),
                            type: ModalFactory.types.SAVE_CANCEL,
                            body: body,
                            //footer: 'footer',
                        }).done(function(modal) {
                            console.log('Created modal');
                            modal.show();
                            modal.getRoot().on(ModalEvents.save, function(e) {
                                e.preventDefault();
                                var discussionid = $(this).find('.modal-body input').val();
                                var supporterid = $(this).find('.modal-body select').val();
                                var data = { 'discussionid': discussionid, 'supporterid': supporterid };
                                //console.log('Store', this, e, data);
                                AJAX.call([{
                                    methodname: 'block_edusupport_set_currentsupporter',
                                    args: data,
                                    done: function(result) {
                                        console.log(result);
                                        if (result == 1) {
                                            top.location.reload();
                                        } else {
                                            alert('Error: ' + result);
                                        }
                                    },
                                    fail: NOTIFICATION.exception
                                }]);
                            });
                        });
                    },
                    fail: NOTIFICATION.exception
                }]);
            } else {
                // Assign the supporter.
            }
        },
        /**
         * Close an issue.
        **/
        closeIssue: function(discussionid) {
            console.log('closeIssue(discussionid)', discussionid);
            AJAX.call([{
                methodname: 'block_edusupport_close_issue',
                args: { discussionid: discussionid },
                done: function(result) {
                    console.log(result);
                    if (result == 1) {
                        top.location.reload();
                    } else {
                        NOTIFICATION.exception(result);
                        //alert('Error: ' + result);
                    }
                },
                fail: NOTIFICATION.exception
            }]);
        },
        /**
         * Colorize shown discussions.
        **/
        colorize: function() {
            var discussionids = [];
            $('table.forumheaderlist tr.discussion td.starter a').each(function(){ var d = $(this).attr('href').split('?d='); discussionids[discussionids.length] = d[1]; });
            var data = { discussionids: discussionids };
            console.log('block_edusupport_colorize', data);
            AJAX.call([{
                methodname: 'block_edusupport_colorize',
                args: data,
                done: function(result) {
                    try { result = JSON.parse(result); } catch(e) {}
                    console.log(result);
                    if (typeof result.styles !== 'undefined') {
                        var discussionids = Object.keys(result.styles);
                        for (var a = 0; a < discussionids.length; a++) {
                            var discussionid = discussionids[a];
                            var style = result.styles[discussionid];
                            $('table.forumheaderlist tr.discussion td.starter a[href$="d=' + discussionid + '"]').closest('tr').attr('style', style);
                        }
                    }
                },
                fail: NOTIFICATION.exception
            }]);
        },
        postBox: function(modal) {
            var MAIN = this;
            if (typeof MAIN.is_sending !== 'undefined' && MAIN.is_sending) {
                console.log('Issue in queue, aborting');
                return;
            }
            //console.log('MAIN.postBox(modal)', modal);
            var subject = $('#block_edusupport_create_form #id_subject').val();
            var contactphone = $('#block_edusupport_create_form #id_contactphone').val();
            var description = $('#block_edusupport_create_form #id_description').val();
            var forum_group = $('#block_edusupport_create_form #id_forum_group').val();
            var post_screenshot = $('#block_edusupport_create_form #id_postscreenshot').is(':checked');
            var screenshot = $('#block_edusupport_create_form img#screenshot').attr('src');
            var url = top.location.href;

            if (subject.length < 3 || description.length < 5) {
                var editaPresent = STR.get_string('be_more_accurate', 'block_edusupport', {});
                $.when(editaPresent).done(function(localizedEditString) {
                    NOTIFICATION.alert('', localizedEditString);
                });
                return;
            }

            MAIN.is_sending = true;

            var imagedataurl = (post_screenshot && typeof screenshot !== 'undefined' ) ? screenshot : '';
            console.log('block_edusupport_create_issue', { subject: subject, description: description, forum_group: forum_group, image: imagedataurl, url: url });
            AJAX.call([{
                methodname: 'block_edusupport_create_issue',
                args: { subject: subject, description: description, forum_group: forum_group, image: imagedataurl, url: url, contactphone: contactphone },
                done: function(result) {
                    // result is the discussion id, -999 if sent by mail, or -1. if > 0 show confirm box that redirects to post. if -1 show error.
                    console.log(result);
                    modal.hide();
                    if (parseInt(result) == -999) {
                        // confirmation, was sent by mail.
                        STR.get_strings([
                            {'key' : 'create_issue_success_title', component: 'block_edusupport' },
                            {'key' : 'create_issue_success_description_mail', component: 'block_edusupport' },
                            {'key' : 'create_issue_success_close', component: 'block_edusupport' },
                            ]).done(function(s) {
                                NOTIFICATION.alert(s[0], s[1], s[2]);
                            }
                        ).fail(NOTIFICATION.exception);
                    } else if (parseInt(result) > 0) {
                        // confirmation
                        STR.get_strings([
                            {'key' : 'create_issue_success_title', component: 'block_edusupport' },
                            {'key' : 'create_issue_success_description', component: 'block_edusupport' },
                            {'key' : 'create_issue_success_goto', component: 'block_edusupport' },
                            {'key' : 'create_issue_success_close', component: 'block_edusupport' },
                            ]).done(function(s) {
                                NOTIFICATION.confirm(s[0], s[1], s[2], s[3], function(){ top.location.href = URL.fileUrl('/mod/forum/discuss.php', '?d=' + result); });
                            }
                        ).fail(NOTIFICATION.exception);
                    } else {
                        STR.get_strings([
                                {'key' : 'create_issue_error_title', component: 'block_edusupport' },
                                {'key' : 'create_issue_error_description', component: 'block_edusupport' },
                            ]).done(function(s) {
                                NOTIFICATION.alert(s[0], s[1]);
                            }
                        ).fail(NOTIFICATION.exception);
                    }
                    MAIN.is_sending = false;
                },
                fail: NOTIFICATION.exception
            }]);
        },
        prepareBox: function() {
            console.log('Showing modal');
            var MAIN = this;
            var body = $(MAIN.modal.body);
            if (body.find('#id_forum_group>option').length <= 1) {
                body.find('#id_forum_group').parent().parent().css('display', 'none');
            }

            MAIN.modal.setLarge();
            MAIN.modal.getRoot().on(ModalEvents.save, function(e) {
                // Stop the default save button behaviour which is to close the modal.
                MAIN.postBox(MAIN.modal);
                e.preventDefault();
                // Do your form validation here.
            });
            var editaPresent = STR.get_string('create_issue', 'block_edusupport', {});
            $.when(editaPresent).done(function(localizedEditString) {
                MAIN.modal.setSaveButtonText(localizedEditString);
            });
            $('#id_postscreenshot').closest('div.fitem').css('display', 'none');
            $('#screenshot').closest('div').css('display', 'none');
            if (typeof MAIN.canvas !== 'undefined') {
                MAIN.prepareScreenshot();
            }
            MAIN.modal.show();
        },
        prepareScreenshot: function(){
            var MAIN = this;
            var dataurl = MAIN.canvas.toDataURL();
            var body = $(MAIN.modal.body);
            body.find('img#screenshot').attr('src', dataurl);
            $('#screenshot').closest('div').css('display', undefined);
            $('#id_postscreenshot').closest('div.fitem').css('display', undefined);
            // delete canvas - next time we want a new screenshot!
            delete(MAIN.canvas);
        },
        showBox: function(forumid){
            if (typeof forumid === 'undefined') forumid = 0;
            var MAIN = this;
            // @todo no functional requirement that screenshot works.
            // @todo screenshot creation parallel to modal?
            // @todo save modal in object for manipulation
            delete(MAIN.canvas);
            if (typeof MAIN.modal !== 'undefined') {
                MAIN.prepareBox(forumid);
            } else {
                console.log('Fetching modal');
                MAIN.triggerSpinner(1);
                AJAX.call([{
                    methodname: 'block_edusupport_create_form',
                    args: { url: top.location.href, image: '', forumid: forumid },
                    done: function(result) {
                        console.log('Got modal');
                        MAIN.triggerSpinner(-1);
                        // Remove any previously created forms.
                        $('#block_edusupport_create_form').remove();
                        //console.log(result);
                        ModalFactory.create({
                            //title: 'create issue',
                            type: ModalFactory.types.SAVE_CANCEL,
                            body: result,
                            large: 1,
                            //footer: 'footer',
                        }).done(function(modal) {
                            console.log('Created modal');
                            MAIN.modal = modal;
                            MAIN.prepareBox();
                        });
                    },
                    fail: NOTIFICATION.exception
                }]);
            }

            // I decided to make screenshot in background - do not show spinner!
            //MAIN.triggerSpinner(1);
            require(['block_edusupport/html2canvas'], function(h2c) {
                console.log('Making screenshot');
                h2c(document.body).then(function(canvas) {
                    console.log('Got screenshot');
                    //MAIN.triggerSpinner(-1);
                    MAIN.canvas = canvas;
                    if (typeof MAIN.modal !== 'undefined') {
                        MAIN.prepareScreenshot();
                    }
                });
            });
        },
        triggerSpinner: function(steps) {
            MAIN = this;
            MAIN.triggerSteps += steps;
            if (MAIN.triggerSteps > 0) {
                if ($('body #edusupport-spinner').length == 0) {
                    $('body').append($('<div id="edusupport-spinner" class="spinner-grid show"><div></div><div></div><div></div><div></div></div>'));
                }
            } else {
                $('#edusupport-spinner').remove();
            }
        }
    };
});
