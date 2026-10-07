jQuery(document).ready(function($) {
    let actionType;

    function operationScope(user, action) {
        if (!/^[1-9][0-9]*$/.test(String(ajax_object.actor_id))) { return null; }
        return JSON.stringify([1, window.location.origin, ajax_object.ajaxurl, String(ajax_object.actor_id), String(user), action]);
    }
    function restoreOperation(user, action) {
        const scope = operationScope(user, action);
        if (!scope) { return; }
        try {
            const pending = JSON.parse(sessionStorage.getItem('loyf-admin:' + scope) || 'null');
            if (pending) { $('#points-amount').val(pending.points); $('#points-description').val(pending.description); }
        } catch (e) { alert(ajax_object.request_error); }
    }


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
            restoreOperation(userId, actionType);
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
            restoreOperation(userId, actionType);
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
		const description = $('#points-description').val();
 // Get the description value
		const userId = $(this).data('user-id'); // Get the user ID from the button
	
		// Check if the points field is empty
		if (!/^[1-9][0-9]{0,7}$/.test(points)) {
			alert(ajax_object.empty_points_alert); // Use the translatable alert message
			return; // Exit the function
		}
	
		        const actorScope = operationScope(userId, actionType);
        if (!actorScope) { alert(ajax_object.request_error); return; }
        let retained = JSON.parse(sessionStorage.getItem('loyf-admin:' + actorScope) || 'null');
        if (retained && (retained.points !== points || retained.description !== description)) { alert(ajax_object.request_error); return; }
        if (!retained) { retained = {id: (crypto.randomUUID ? crypto.randomUUID() : '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, c => (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16))), points, description}; sessionStorage.setItem('loyf-admin:' + actorScope, JSON.stringify(retained)); }
        const operationId = retained.id;
        if (actionType === 'reward') {
			$.ajax({
				url: ajax_object.ajaxurl,
				type: 'POST',
				data: {
					action: 'reward_user_points',
					user_id: userId, // Use the user ID from the button
					points: points,
                    operation_id: operationId,
					description: description,
					security: ajax_object.security // Include nonce here
				},
				success: function(response) {
					if (response.success) {
						if (JSON.parse(sessionStorage.getItem('loyf-admin:' + actorScope) || 'null')?.id === operationId) { sessionStorage.removeItem('loyf-admin:' + actorScope); }
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
                    operation_id: operationId,
					description: description,
					security: ajax_object.security // Include nonce here
				},
				success: function(response) {
					if (response.success) {
						if (JSON.parse(sessionStorage.getItem('loyf-admin:' + actorScope) || 'null')?.id === operationId) { sessionStorage.removeItem('loyf-admin:' + actorScope); }
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
