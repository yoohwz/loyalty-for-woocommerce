document.addEventListener("DOMContentLoaded", function() {
    updateCircle(ajax_object.circle_progress_percent); // Use the localized value from PHP
});

function updateCircle(progressPercent) {
    const progressElement = document.getElementById('circle-progress');
    progressElement.setAttribute('stroke-dasharray', `${progressPercent}, 100`);
}

jQuery(document).ready(function($) {
    $('#load-more-points-log').on('click', function() {
        var button = $(this);
        var offset = button.data('offset');

        $.ajax({
            url: ajax_object.ajaxurl, // Use the localized ajaxurl
            type: 'POST',
            data: {
                action: 'load_more_points_log',
                security: ajax_object.nonce, // Use the localized nonce value
                offset: offset
            },
            beforeSend: function() {
                button.text(ajax_object.loading_text); // Localized text
            },
            success: function(response) {
                if (response.success) {
                    var newRows = '';
                    $.each(response.data, function(index, log) {
                        var orderLink = log.order_id > 0 ? '<a href="' + ajax_object.view_order_url + log.order_id + '">#' + log.order_id + '</a>' : '-';
                        var amountColor = log.amount.startsWith('+') ? '#00a32a' : '#d63638';

                        newRows += '<tr>' +
                            '<td>' + log.date + '</td>' +
                            '<td>' + orderLink + '</td>' +
                            '<td>' + log.description + '</td>' +
                            '<td style="color:' + amountColor + ';">' + log.amount + '</td>' +
                            '</tr>';
                    });

                    $('#my-points-table tbody').append(newRows);
                    button.data('offset', offset + 10);
                    button.text(ajax_object.load_more_text); // Localized text
                } else {
                    button.text(ajax_object.no_more_points_text).prop('disabled', true); // Localized text
                }
            },
            error: function() {
                button.text(ajax_object.error_text); // Localized error text
            }
        });
    });
});
