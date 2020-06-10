define(
    ['jquery', 'core/ajax', 'core/notification'],
    function($, AJAX, NOTIFICATION, STR, URL, ModalFactory, ModalEvents) {
    return {
        setDefault: function(uniqid, forumid) {
            alert('Not used anymore');
            /*
            var SELF = this;

            console.log('setDefault(uniqid, forumid)', uniqid, forumid);
            AJAX.call([{
                methodname: 'local_edusupport_set_default',
                args: { forumid: forumid },
                done: function(result) {
                    console.log('Result is', result);
                    if (result == '1') {
                        $('#' + uniqid + '-forum-' + forumid).css('background-color', 'rgba(0, 255, 0, 0.2)');
                    } else {
                        $('#' + uniqid + '-forum-' + forumid).css('background-color', 'rgba(255, 0, 0, 0.2)');
                    }
                    setTimeout(function(){ $('#' + uniqid + '-forum-' + forumid).css('background-color', ''); }, 500);
                    top.location.reload();
                },
                fail: NOTIFICATION.exception
            }]);
            */
        },
    };
});
