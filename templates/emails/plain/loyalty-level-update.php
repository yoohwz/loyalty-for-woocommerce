<?php
/**
 * Loyalty level update email plain text.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/plain/loyalty-level-update.php.
 *
 * @package WooCommerceLoyalty\Templates\Emails\Plain
 */

defined( 'ABSPATH' ) || exit;

$point_label_lower = function_exists( 'mb_strtolower' ) ? mb_strtolower( $point_label, 'UTF-8' ) : strtolower( $point_label );
$earned_display    = sprintf( '%1$s %2$s', number_format_i18n( (int) $new_earning_points ), $point_label_lower );
$balance_display   = sprintf( '%1$s %2$s', number_format_i18n( (int) $points_balance ), $point_label_lower );
$points_to_next    = sprintf( '%1$s %2$s', number_format_i18n( (int) $points_to_next_level ), $point_label_lower );
$display_level     = '' !== $level_name ? $level_name : $current_level_name;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

if ( ! empty( $customer_first_name ) ) {
	printf(
		/* translators: %s: Customer first name. */
		esc_html__( 'Hi %s,', 'loyalty-for-woocommerce' ),
		esc_html( $customer_first_name )
	);
	echo "\n\n";
} else {
	echo esc_html__( 'Hi,', 'loyalty-for-woocommerce' ) . "\n\n";
}

echo esc_html__( 'Your loyalty level has changed.', 'loyalty-for-woocommerce' ) . "\n\n";
echo esc_html__( 'New level:', 'loyalty-for-woocommerce' ) . ' ' . esc_html( $display_level ) . "\n";
echo esc_html__( 'Total earned:', 'loyalty-for-woocommerce' ) . ' ' . esc_html( $earned_display ) . "\n";
echo esc_html__( 'Current balance:', 'loyalty-for-woocommerce' ) . ' ' . esc_html( $balance_display ) . "\n";

if ( ! empty( $next_level_name ) && $points_to_next_level > 0 ) {
	printf(
		/* translators: 1: Points needed, 2: Next loyalty level. */
		esc_html__( 'Earn %1$s more to reach %2$s.', 'loyalty-for-woocommerce' ),
		esc_html( $points_to_next ),
		esc_html( $next_level_name )
	);
	echo "\n";
} elseif ( ! empty( $is_highest_level ) ) {
	echo esc_html__( 'You are at the highest loyalty level.', 'loyalty-for-woocommerce' ) . "\n";
}

if ( ! empty( $points_url ) ) {
	printf(
		/* translators: %s: My account points page label. */
		"\n" . esc_html__( 'View %s:', 'loyalty-for-woocommerce' ) . ' ' . esc_url( $points_url ) . "\n",
		esc_html( $points_page_label )
	);
}

echo "\n----------------------------------------\n\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n----------------------------------------\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
