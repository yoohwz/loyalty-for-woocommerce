jQuery(document).ready(function($) {
    $('.toggle-loyalty-points').click(function(e) {
        e.preventDefault(); // Prevent the default anchor click behavior
        $('.loyalty-points-toggle-content').slideToggle();
    });

    $(document.body).on('click', '.remove-loyalty-points', function(e) {
        e.preventDefault();

        $.ajax({
            type: 'POST',
            url: loyaltyPointsMessages.ajax_url,
            data: {
                action: 'delete_loyalty_coupon',
                loyalty_points_nonce: loyaltyPointsMessages.loyaltyPointsNonce
            },
            success: function(response) {
                if (response.success) {
                    $('#loyalty-points-message').html('<div class="woocommerce-message" style="margin-top: 10px;">' + response.data.message + '</div>');
                    $(document.body).trigger('update_checkout');
                }
            }
        });
    });

    $('#apply_loyalty_points').click(function() {
        var pointsToUse = $('#loyalty_points_input').val();
        var messageContainer = $('#loyalty-points-message');
        var minPoints = parseInt($('#loyalty_points_input').attr('min')); // Get the minimum points allowed
        var maxPoints = parseInt($('#loyalty_points_input').attr('max')); // Get the maximum points allowed

        // Clear previous messages
        messageContainer.html('');

        if (pointsToUse >= minPoints && pointsToUse <= maxPoints) {
            // Perform AJAX request to apply the points
            $.ajax({
                type: 'POST',
                url: loyaltyPointsMessages.applyPointsAjax,
                data: {
                    action: 'applying_points',
                    loyalty_points_input: pointsToUse,
                    loyalty_points_nonce: loyaltyPointsMessages.loyaltyPointsNonce // Add the nonce here
                },
                success: function(response) {
                    if (response.success) {
                        $.ajax({
                            type: 'POST',
                            url: wc_checkout_params.ajax_url,
                            data: {
                                action: 'woocommerce_update_order_review',
                                security: wc_checkout_params.update_order_review_nonce,
                                post_data: $('form.checkout').serialize()
                            },
                            success: function(result) {
                                messageContainer.html('<div class="woocommerce-message" style="margin-top: 10px;">' + response.data.message + '</div>');

                                // Trigger a full checkout update once the recalculation is done
                                $(document.body).trigger('update_checkout');

                                // Close the entire loyalty points div
                                $('#loyalty-using-points').hide();
                            }
                        });
                    } else {
                        messageContainer.html('<div class="woocommerce-error" style="margin-top: 10px;">' + response.data.message + '</div>');
                    }
                },
                error: function() {
                    // Handle any errors from the AJAX request
                    messageContainer.html('<div class="woocommerce-error" style="margin-top: 10px;">' + loyaltyPointsMessages.maxPointsError.replace('%d', maxPoints) + '</div>');
                }
            });
        } else {
            var errorMessage = pointsToUse < minPoints 
                ? loyaltyPointsMessages.minPointsError.replace('%d', minPoints) 
                : loyaltyPointsMessages.maxPointsError.replace('%d', maxPoints);
            messageContainer.html('<div class="woocommerce-error" style="margin-top: 10px;">' + errorMessage + '</div>');
        }
    });

    $(document.body).on('updated_checkout', function() {
        $.ajax({
            type: 'POST',
            url: loyaltyPointsMessages.applyPointsAjax,
            data: {
                action: 'get_earned_points',
                loyalty_points_nonce: loyaltyPointsMessages.loyaltyPointsNonce // Add the nonce here
            },
            success: function(response) {
                if (response.success) {
                    // Update the reward message with the recalculated points
                    $('#loyalty-reward-message').html(
                        '<div class="loyalty-reward-message">' + 
                        '<img src="' + loyaltyPointsMessages.rewardImageUrl + '" alt="' + loyaltyPointsMessages.rewardIconAlt + '" style="max-width: 22px;">' + 
                        loyaltyPointsMessages.rewardMessage.replace('%d', response.data.earned_points) + '</div>'
                    );
                }
            },
            error: function() {
                // Handle any errors from the AJAX request (you can add an alert or error message here)
            }
        });
    });
});
