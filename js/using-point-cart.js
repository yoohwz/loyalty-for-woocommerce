jQuery(document).ready(function($) {
    // Function to initialize the toggle functionality and form handlers
    function initLoyaltyPointsToggle() {
        // Toggle link click handler
        $('.toggle-loyalty-points').off('click').on('click', function(e) {
            e.preventDefault();
            $('.loyalty-points-toggle-content').slideToggle();
        });

        $('.remove-loyalty-points').off('click').on('click', function(e) {
            e.preventDefault();

            $.ajax({
                type: 'POST',
                url: loyaltyPointsMessages.ajax_url,
                dataType: 'json',
                data: {
                    action: 'delete_loyalty_coupon',
                    loyalty_points_nonce: loyaltyPointsMessages.loyaltyPointsNonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#loyalty-points-message').html('<div class="woocommerce-message" style="margin-top: 10px;">' + response.data.message + '</div>');
                        $(document.body).trigger('update_checkout');
                        $(document.body).trigger('wc_update_cart');
                    }
                }
            });
        });

        // Apply points button click handler
        $('#apply_loyalty_points').off('click').on('click', function() {
            var pointsInput = parseFloat($('#loyalty_points_input').val());
            var messageContainer = $('#loyalty-points-message');
            var minPoints = parseInt($('#loyalty_points_input').attr('min')); // Get the minimum points allowed
            var maxPoints = parseInt($('#loyalty_points_input').attr('max')); // Get the maximum points allowed
            var loyaltyPointsContainer = $('#loyalty-using-points'); // The container to hide

            // Validate points input
            if (isNaN(pointsInput) || pointsInput < loyaltyPointsData.minPoints || pointsInput > loyaltyPointsData.userPoints) {
                var errorMessage = pointsInput < loyaltyPointsData.minPoints 
                    ? loyaltyPointsMessages.minPointsError.replace('%d', loyaltyPointsData.minPoints) 
                    : loyaltyPointsMessages.maxPointsError.replace('%d', loyaltyPointsData.userPoints);

                messageContainer.html('<div class="woocommerce-error" style="margin-top: 10px;">' + errorMessage + '</div>');
                return;
            }

            // Disable the button to prevent multiple submissions
            $(this).prop('disabled', true);

            // AJAX request to apply points
            $.ajax({
                url: wc_cart_params.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'applying_points',
                    loyalty_points_input: pointsInput,
                    loyalty_points_nonce: loyaltyPointsMessages.loyaltyPointsNonce // Include nonce for security
                },
                success: function(response) {
                    if (response.success) {
                        // Show a success message in WooCommerce message format
                        messageContainer.html('<div class="woocommerce-message" style="margin-top: 10px;">' + response.data.message + '</div>');

                        // Hide the loyalty points container after processing is done
                        loyaltyPointsContainer.slideUp(function() {
                            $(this).css('display', 'none');
                        });

                        // Trigger an update for the cart totals and fragments
                        $(document.body).trigger('update_checkout');
                        $(document.body).trigger('wc_update_cart');

                        // Refresh cart fragments
                        $.ajax({
                            url: wc_cart_fragments_params.wc_ajax_url.toString().replace('%%endpoint%%', 'get_refreshed_fragments'),
                            type: 'POST',
                            success: function(data) {
                                if (data && data.fragments) {
                                    // Replace the cart totals and mini-cart
                                    $.each(data.fragments, function(key, value) {
                                        $(key).replaceWith(value);
                                    });

                                    // Trigger cart update completed event
                                    $(document.body).trigger('wc_fragments_refreshed');
                                }
                            }
                        });
                    } else {
                        // Show an error message in WooCommerce error format
                        messageContainer.html('<div class="woocommerce-error" style="margin-top: 10px;">' + response.data.message + '</div>');
                    }

                    // Re-enable the button
                    $('#apply_loyalty_points').prop('disabled', false);
                },
                error: function(xhr, status, error) {
                    // Show a generic error message in WooCommerce error format
                    messageContainer.html('<div class="woocommerce-error" style="margin-top: 10px;">' + loyaltyPointsMessages.requestError + '</div>');

                    // Re-enable the button
                    $('#apply_loyalty_points').prop('disabled', false);
                }
            });
        });
    }

    // Initialize the toggle and form handlers on page load
    initLoyaltyPointsToggle();

    // Re-initialize the toggle and form handlers when cart fragments are refreshed
    $(document.body).on('wc_fragments_refreshed', function() {
        initLoyaltyPointsToggle();

    });
});
