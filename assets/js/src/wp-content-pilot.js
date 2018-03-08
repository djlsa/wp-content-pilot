/**
 * WP Content Pilot
 * http://pluginever.com
 *
 * Copyright (c) 2018 PluginEver
 * Licensed under the GPLv2+ license.
 */

/*jslint browser: true */
/*global jQuery:false */
/*global wpcp:false */

window.Wp_Content_Pilot = (function (window, document, $, undefined) {
    'use strict';

    var app = {};

    app.init = function () {
        console.log('init');
        app.update_template_tags();
        $('#_campaign_type').on('change', app.update_template_tags);
        $('#wpcp-test-run').on('click', app.run_test_campaign);
    };

    app.update_template_tags = function () {
        var campaign_type = $('#_campaign_type').val();
        $.post(
            ajaxurl,
            {
                'action': 'wpcp_get_template_tags',
                'data': {type: campaign_type}
            },
            function (response) {
                if (response.data) {
                    var tags = response.data;
                    var html = '';

                    $.each(tags, function (index, tag) {
                        html += '<code>{' + tag + '}</code>, ';
                    });

                    $('.wpcp-supported-tags').html(html);

                }
            }
        );
    };

    app.run_test_campaign = function (e) {
        e.preventDefault();
        var post_id = parseInt($("#post_ID").val());
        var btn = $(this);

        btn.attr('disabled', 'disabled');
        btn.text('Posting.....');

        if (!post_id && NaN) {

            alert('Campaign is not fully configured. Configure the campaign first to test.');
            return false;
        }

        $.post(
            ajaxurl,
            {
                action: 'wpcp_run_test_campaign',
                campaign_id: post_id,
                nonce: wpcp.nonce
            },
            function (response) {
                console.log(response.data);
                if (response.success === true &&
                    response.data.permalink !== undefined &&
                    response.data.message !== ''
                ) {
                    var visit = confirm(response.data.message);
                    if (visit === true) {
                        btn.css('color', 'green');
                        window.location.href = response.data.permalink;
                    }

                    return false;
                }


                if (!response.success  &&   response.data.message !== undefined && response.data.message !== '') {
                    console.log('1');
                    btn.text('failed. Please try again.');
                    btn.css('color', 'red');
                    alert(response.data.message);
                    return false;
                } else {
                    console.log('2');
                    btn.text('failed. Please try again.');
                    btn.css('color', 'red');
                    alert('Test Failed. Please change or add more keywords then Update and try again.');
                    return false;
                }



            }
        );
        console.log(post_id);

    };


    $(document).ready(app.init);


    return app;

})(window, document, jQuery);
