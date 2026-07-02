jQuery(document).ready(function($) {
    let actionType;

    function openModal() {
        $('#points-modal').show();
    }

    function closeModal() {
        $('#points-modal').hide();
        $('#points-amount').val('');
        $('#points-description').val('');
    }

    $('#reward-points').on('click', function(e) {
        e.preventDefault(); // Prevent default action
        actionType = 'reward';
        $('#modal-heading').text(ajax_object.add_points_text); // Use localized string
        openModal();
    });
    
    $('#deduct-points').on('click', function(e) {
        e.preventDefault(); // Prevent default action
        actionType = 'deduct';
        $('#modal-heading').text(ajax_object.remove_points_text); // Use localized string
        openModal();
    });
    

    $('.close-modal').on('click', function() {
        closeModal();
    });

    $('#submit-points').on('click', function(e) {
        e.preventDefault(); // Prevent default action
        const points = $('#points-amount').val();
        const description = $('#points-description').val();
    
        // Check if the points field is empty
        if (!points || points <= 0) {
            alert(ajax_object.empty_points_alert); // Use the translatable alert message
            return; // Exit the function
        }
    
        if (actionType === 'reward') {
            $.ajax({
                url: ajax_object.ajaxurl,
                type: 'POST',
                data: {
                    action: 'reward_user_points',
                    user_id: ajax_object.user_id, // Ensure this is the correct user ID
                    points: points,
                    description: description,
                    security: ajax_object.security // Include nonce here
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message); // Display success message
                        closeModal();
                        location.reload(); // Refresh the page after closing the modal
                    } else {
                        alert(ajax_object.error_prefix + ' ' + response.data); // Display error message
                    }
                },
                error: function() {
                    alert(ajax_object.request_error); // Handle AJAX error
                }
            });
        } else if (actionType === 'deduct') {
            $.ajax({
                url: ajax_object.ajaxurl,
                type: 'POST',
                data: {
                    action: 'deduct_user_points',
                    user_id: ajax_object.user_id, // Ensure this is the correct user ID
                    points: points,
                    description: description,
                    security: ajax_object.security // Include nonce here
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message); // Display success message
                        closeModal();
                        location.reload(); // Refresh the page after closing the modal
                    } else {
                        alert(ajax_object.error_prefix + ' ' + response.data); // Display error message
                    }
                },
                error: function() {
                    alert(ajax_object.request_error); // Handle AJAX error
                }
            });
        }
    });
    
    $(window).on('click', function(event) {
        if ($(event.target).is('#points-modal')) {
            closeModal();
        }
    });

    // Open Points Log Modal
    $('#points-log').on('click', function(e) {
        e.preventDefault(); // Prevent default action
        $('#points-log-table tbody').empty(); // Clear previous log entries

        $.ajax({
            url: ajax_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_points_log',
                user_id: ajax_object.user_id,
                security: ajax_object.security // Include nonce for security
            },
            success: function(response) {
                if (response.success) {
                    const logEntries = response.data;
                    logEntries.forEach(function(entry) {
                        const amountColor = entry.amount.startsWith('+') ? '#00a32a' : '#d63638'; // Determine color
                        $('#points-log-table tbody').append(
                            `<tr>
                                <td>${entry.date}</td>
                                <td style="color: ${amountColor};">${entry.amount}</td> <!-- Apply color -->
                                <td>${entry.order_id}</td>
                                <td>${entry.description}</td>
                            </tr>`
                        );
                    });
                    $('#points-log-modal').show();
                } else {
                    const errorMessage = response.data?.message || ajax_object.unexpected_error;
                    alert(errorMessage); // Display error message
                }
            },
            error: function() {
                alert(ajax_object.points_log_error); // Handle AJAX error
            }
        });
    });

    // Close Points Log Modal
    $('#points-log-modal').on('click', function(event) {
        if ($(event.target).is('#points-log-modal') || $(event.target).hasClass('close-modal-log')) {
            $('#points-log-modal').hide(); // Close modal
        }
    });
});
