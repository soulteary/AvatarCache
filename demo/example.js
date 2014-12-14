/**
 * 不完全解决方案，如果使用第三方插件，最好是等待三方插件提供API替换或者初始化完成事件
 */
var duoshuoAvatar = setInterval(function () {
    if (jQuery('#ds-reset').length && jQuery('#ds-reset .ds-avatar img').length) {
        //clearInterval(duoshuoAvatar);
        try {
            jQuery('#ds-reset .ds-avatar img').each(function (k, v) {
                var target = jQuery(v),
                    url = target.attr('src');
                if (url && url.indexOf('gravatar.com') > -1) {
                    url = url.toLocaleLowerCase().split('avatar/');
                    if (url.length == 2) {
                        var query = url[1].split('?'),
                            api = 'http://assets.soulteary.com/avatar/';
                        if (query[0]) {
                            api += query[0];
                            if (query[1]) {
                                var params = query[1].split('&');
                                for (var i = 0, j = params.length; i < j; i++) {
                                    if (params[i].indexOf('s=') > -1) {
                                        api += '/' + params[i].split('s=')[1];
                                    }
                                }
                            }
                        }
                    }
                    target.attr('src', api);
                }
            });
        } catch (e) {
        }
    }
}, 600);
