jQuery(document).ready(function($) {
    let actionType;

    function closeModal() {
        $('#points-modal').hide();
        $('#points-amount').val('');
        $('#points-description').val('');
    }

    // Function to get the username
    function getUsername(userId, callback) {
        $.ajax({
            url: ajax_object.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_user_name', // Define the action
                user_id: userId,
                security: ajax_object.security // Include nonce for security
            },
            success: function(response) {
                if (response.success) {
                    callback(response.data.username); // Call the callback with the username
                } else {
                    console.error(ajax_object.username_error, response.data);
                }
            },
            error: function() {
                console.error(ajax_object.username_fetch_error);
            }
        });
    }

    // Event listener for Reward button
    $(document).on('click', '.reward-points', function(e) {
        e.preventDefault(); // Prevent default action
        const userId = $(this).data('user-id'); // Get the user ID from the button

        getUsername(userId, function(username) {
            actionType = 'reward';
            $('#modal-heading').text(ajax_object.add_points_text + ' ' + username); // Set heading with username
            $('#points-modal').show(); // Open modal
            $('#submit-points').data('user-id', userId); // Store user ID in submit button
        });
    });

    // Event listener for Deduct button
    $(document).on('click', '.deduct-points', function(e) {
        e.preventDefault(); // Prevent default action
        const userId = $(this).data('user-id'); // Get the user ID from the button

        getUsername(userId, function(username) {
            actionType = 'deduct';
            $('#modal-heading').text(ajax_object.remove_points_text + ' ' + username); // Set heading with username
            $('#points-modal').show(); // Open modal
            $('#submit-points').data('user-id', userId); // Store user ID in submit button
        });
    });

    $('.close-modal').on('click', function() {
        closeModal();
    });
	
	$('#submit-points').on('click', function(e) {
		e.preventDefault(); // Prevent default action
		const points = $('#points-amount').val(); // Get the points value
		const description = $('#points-description').val(); // Get the description value
		const userId = $(this).data('user-id'); // Get the user ID from the button
	
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
					user_id: userId, // Use the user ID from the button
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
					user_id: userId, // Use the user ID from the button
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
});
