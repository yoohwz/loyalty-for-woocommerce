jQuery(document).ready(function($) {
    $('.upload_image_button').on('click', function(e){
		e.preventDefault();
	
		var button = $(this);
		// try to read data-role; if absent, we’re handling the message-icon
		var role = button.data('role');
		var inputField = role
		  ? $('#loyalty_level_icon_' + role)
		  : $('#loyalty_message_icon');
	
		if ( wp && wp.media ) {
		  var file_frame = wp.media({
			title: loyaltyCustomizationMessages.mediaTitle,
			button: { text: loyaltyCustomizationMessages.mediaButtonText },
			multiple: false
		  });
	
		  file_frame.on( 'select', function(){
			var attachment = file_frame.state().get('selection').first().toJSON();
			inputField.val( attachment.url ).trigger('change');
		  });
	
		  file_frame.open();
		} else {
		  console.error(loyaltyCustomizationMessages.mediaError);
		}
	});

	// Sync color picker and hex input field for shop and product message settings
	$('.loyalty_customization input[type="color"]').on('input change', function() {
		var colorPicker = $(this);
		var hexInput = $(this).closest('td').find('input[type="text"]');

		// Update the hex input field with the color picker's value
		hexInput.val(colorPicker.val());
	});

	$('.loyalty_customization input[type="text"]').on('input', function() {
		var hexInput = $(this);
		var colorPicker = $(this).closest('td').find('input[type="color"]');

		// Ensure the value is a valid hex color before setting the color picker
		if (/^#([0-9A-F]{3}){1,2}$/i.test(hexInput.val())) {
			colorPicker.val(hexInput.val());
		}
	});

	// Individual syncing for the membercard
	$('#loyalty_membercard_text_color').on('input change', function() {
		$('#loyalty_membercard_text_color_hex').val($(this).val());
	});

	$('#loyalty_membercard_text_color_hex').on('input', function() {
		var hexVal = $(this).val();
		if (/^#([0-9A-F]{3}){1,2}$/i.test(hexVal)) {
			$('#loyalty_membercard_text_color').val(hexVal);
		}
	});

	$('#loyalty_membercard_background_color').on('input change', function() {
		$('#loyalty_membercard_background_color_hex').val($(this).val());
	});

	$('#loyalty_membercard_background_color_hex').on('input', function() {
		var hexVal = $(this).val();
		if (/^#([0-9A-F]{3}){1,2}$/i.test(hexVal)) {
			$('#loyalty_membercard_background_color').val(hexVal);
		}
	});

	$('#loyalty_membercard_border_color').on('input change', function() {
		$('#loyalty_membercard_border_color_hex').val($(this).val());
	});

	$('#loyalty_membercard_border_color_hex').on('input', function() {
		var hexVal = $(this).val();
		if (/^#([0-9A-F]{3}){1,2}$/i.test(hexVal)) {
			$('#loyalty_membercard_border_color').val(hexVal);
		}
	});
	
	// Individual syncing for 'Colors' section for the shop page
	$('#loyalty_message_shop_page_text_color').on('input change', function() {
		$('#loyalty_message_shop_page_text_color_hex').val($(this).val());
	});

	$('#loyalty_message_shop_page_text_color_hex').on('input', function() {
		var hexVal = $(this).val();
		if (/^#([0-9A-F]{3}){1,2}$/i.test(hexVal)) {
			$('#loyalty_message_shop_page_text_color').val(hexVal);
		}
	});

	$('#loyalty_message_shop_page_background_color').on('input change', function() {
		$('#loyalty_message_shop_page_background_color_hex').val($(this).val());
	});

	$('#loyalty_message_shop_page_background_color_hex').on('input', function() {
		var hexVal = $(this).val();
		if (/^#([0-9A-F]{3}){1,2}$/i.test(hexVal)) {
			$('#loyalty_message_shop_page_background_color').val(hexVal);
		}
	});

	$('#loyalty_message_shop_page_border_color').on('input change', function() {
		$('#loyalty_message_shop_page_border_color_hex').val($(this).val());
	});

	$('#loyalty_message_shop_page_border_color_hex').on('input', function() {
		var hexVal = $(this).val();
		if (/^#([0-9A-F]{3}){1,2}$/i.test(hexVal)) {
			$('#loyalty_message_shop_page_border_color').val(hexVal);
		}
	});

	// Repeat similar logic for product page color pickers
	$('#loyalty_message_product_page_text_color').on('input change', function() {
		$('#loyalty_message_product_page_text_color_hex').val($(this).val());
	});

	$('#loyalty_message_product_page_text_color_hex').on('input', function() {
		var hexVal = $(this).val();
		if (/^#([0-9A-F]{3}){1,2}$/i.test(hexVal)) {
			$('#loyalty_message_product_page_text_color').val(hexVal);
		}
	});

	$('#loyalty_message_product_page_background_color').on('input change', function() {
		$('#loyalty_message_product_page_background_color_hex').val($(this).val());
	});

	$('#loyalty_message_product_page_background_color_hex').on('input', function() {
		var hexVal = $(this).val();
		if (/^#([0-9A-F]{3}){1,2}$/i.test(hexVal)) {
			$('#loyalty_message_product_page_background_color').val(hexVal);
		}
	});

	$('#loyalty_message_product_page_border_color').on('input change', function() {
		$('#loyalty_message_product_page_border_color_hex').val($(this).val());
	});

	$('#loyalty_message_product_page_border_color_hex').on('input', function() {
		var hexVal = $(this).val();
		if (/^#([0-9A-F]{3}){1,2}$/i.test(hexVal)) {
			$('#loyalty_message_product_page_border_color').val(hexVal);
		}
	});
});

document.addEventListener('DOMContentLoaded', function () {
	// Open rows
	var loyaltyMessageShopPageEnableCheckbox = document.getElementById('loyalty_message_shop_page_enable');
	var loyaltyMessageShopPageBorderRow = document.getElementById('loyalty_message_shop_page_border_row');
	var loyaltyMessageShopPageColorsRow = document.getElementById('loyalty_message_shop_page_colors_row');
	var loyaltyMessageShopPageGuestRow = document.getElementById('loyalty_message_shop_page_guest_row');

	var loyaltyMessageProductPageEnableCheckbox = document.getElementById('loyalty_message_product_page_enable');
	var loyaltyMessageProductPagePositionRow = document.getElementById('loyalty_message_product_page_position_row');
	var loyaltyMessageProductPageBorderRow = document.getElementById('loyalty_message_product_page_border_row');
	var loyaltyMessageProductPageColorRow = document.getElementById('loyalty_message_product_page_color_row');
	var loyaltyMessageProductPageGuestRow = document.getElementById('loyalty_message_product_page_guest_row');

	var loyaltyBubbleEnableCheckbox = document.getElementById('loyalty_bubble_enable');
	var loyaltyBubblePositionRow = document.getElementById('loyalty_bubble_position_row');
	var loyaltyBubblePoweredByRow = document.getElementById('loyalty_bubble_powered_by_row');
	
	var loyaltyMyAccountDisplayEnableCheckbox = document.getElementById('loyalty_my_account_display_enable');
	var loyaltyMyAccountDisplayLabelRow = document.getElementById('loyalty_my_account_display_label_row');
	var loyaltyMyAccountDisplaySlugRow = document.getElementById('loyalty_my_account_display_slug_row');

	function toggleDisplay(element, display) {
		if (!element) {
			return;
		}

		element.style.display = display ? '' : 'none';
	}

	if (loyaltyMessageShopPageEnableCheckbox) {
		loyaltyMessageShopPageEnableCheckbox.addEventListener('change', function () {
			var isChecked = this.checked;
			toggleDisplay(loyaltyMessageShopPageBorderRow, isChecked);
			toggleDisplay(loyaltyMessageShopPageColorsRow, isChecked);
			toggleDisplay(loyaltyMessageShopPageGuestRow, isChecked);
		});
	}

	if (loyaltyMessageProductPageEnableCheckbox) {
		loyaltyMessageProductPageEnableCheckbox.addEventListener('change', function () {
			var isChecked = this.checked;
			toggleDisplay(loyaltyMessageProductPagePositionRow, isChecked);
			toggleDisplay(loyaltyMessageProductPageBorderRow, isChecked);
			toggleDisplay(loyaltyMessageProductPageColorRow, isChecked);
			toggleDisplay(loyaltyMessageProductPageGuestRow, isChecked);
		});
	}

	if (loyaltyBubbleEnableCheckbox) {
		loyaltyBubbleEnableCheckbox.addEventListener('change', function () {
			var isChecked = this.checked;
			toggleDisplay(loyaltyBubblePositionRow, isChecked);
			toggleDisplay(loyaltyBubblePoweredByRow, isChecked);
		});
	}

	if (loyaltyMyAccountDisplayEnableCheckbox) {
		loyaltyMyAccountDisplayEnableCheckbox.addEventListener('change', function () {
			var isChecked = this.checked;
			toggleDisplay(loyaltyMyAccountDisplayLabelRow, isChecked);
			toggleDisplay(loyaltyMyAccountDisplaySlugRow, isChecked);
		});
	}
});
