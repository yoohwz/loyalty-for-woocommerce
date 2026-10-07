<?php
/**
 * Loyalty points deduct email.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/loyalty-points-deduct.php.
 *
 * @package WooCommerceLoyalty\Templates\Emails
 */

defined( 'ABSPATH' ) || exit;

$email_improvements_enabled = class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) && \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled( 'email_improvements' );
$point_label_lower          = function_exists( 'mb_strtolower' ) ? mb_strtolower( $point_label, 'UTF-8' ) : strtolower( $point_label );
$points_display             = sprintf( '%1$s %2$s', number_format_i18n( (int) $points ), $point_label_lower );
$balance_display            = sprintf( '%1$s %2$s', number_format_i18n( (int) $points_balance ), $point_label_lower );
$earned_total_display       = sprintf( '%1$s %2$s', number_format_i18n( (int) $earned_points_total ), $point_label_lower );
$points_to_next_display     = sprintf( '%1$s %2$s', number_format_i18n( (int) $points_to_next_level ), $point_label_lower );
$table_text_color           = $email_palette_text_color ?? ( $membercard_text_color ?? '#1d2327' );
$table_secondary_color      = $email_palette_secondary_text_color ?? '#646970';
$table_background           = $email_palette_panel_background_color ?? ( $membercard_background_color ?? '#f6f7f7' );
$table_border               = $email_palette_border_color ?? ( $membercard_border_color ?? '#dcdcde' );
$table_accent               = $email_palette_accent_color ?? ( $current_level_color ?? $table_text_color );
$table_progress_background  = $email_palette_progress_background_color ?? '#e5e5e5';
$progress_width             = max( 0, min( 100, (int) $level_progress ) );

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php echo $email_improvements_enabled ? '<div class="email-introduction">' : ''; ?>
<p>
<?php
if ( ! empty( $customer_first_name ) ) {
	printf(
		/* translators: %s: Customer first name. */
		esc_html__( 'Hi %s,', 'loyalty-for-woocommerce' ),
		esc_html( $customer_first_name )
	);
} else {
	esc_html_e( 'Hi,', 'loyalty-for-woocommerce' );
}
?>
</p>
<p>
<?php
printf(
	/* translators: 1: Deducted points, 2: Current points balance. */
	esc_html__( '%1$s were deducted from your loyalty account. Your current balance is %2$s.', 'loyalty-for-woocommerce' ),
	esc_html( $points_display ),
	esc_html( $balance_display )
);
?>
</p>
<?php echo $email_improvements_enabled ? '</div>' : ''; ?>

<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 0 0 24px;">
	<tr>
		<td style="background: <?php echo esc_attr( $table_background ); ?>; border: 1px solid <?php echo esc_attr( $table_border ); ?>; border-radius: 8px; padding: 22px;">
			<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
				<tr>
					<?php if ( ! empty( $point_icon_url ) ) : ?>
						<td width="52" valign="top" style="padding: 0 16px 0 0;">
							<img src="<?php echo esc_url( $point_icon_url ); ?>" width="44" height="44" alt="<?php echo esc_attr( $point_label ); ?>" style="border: 0; display: block; height: 44px; max-width: 44px; width: 44px;" />
						</td>
					<?php endif; ?>
					<td valign="top" style="padding: 0;">
						<p style="color: <?php echo esc_attr( $table_secondary_color ); ?>; font-size: 13px; line-height: 18px; margin: 0 0 6px;"><?php esc_html_e( 'Deducted', 'loyalty-for-woocommerce' ); ?></p>
						<p style="color: <?php echo esc_attr( $table_accent ); ?>; font-size: 30px; font-weight: 700; line-height: 36px; margin: 0;"><?php echo esc_html( '-' . $points_display ); ?></p>
					</td>
					<td valign="top" align="right" style="padding: 0 0 0 16px;">
						<p style="color: <?php echo esc_attr( $table_secondary_color ); ?>; font-size: 13px; line-height: 18px; margin: 0 0 6px;"><?php esc_html_e( 'Balance', 'loyalty-for-woocommerce' ); ?></p>
						<p style="color: <?php echo esc_attr( $table_text_color ); ?>; font-size: 18px; font-weight: 700; line-height: 24px; margin: 0;"><?php echo esc_html( $balance_display ); ?></p>
					</td>
				</tr>
			</table>

			<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="border-top: 1px solid <?php echo esc_attr( $table_border ); ?>; margin: 18px 0 0; padding: 0;">
				<tr>
					<td style="color: <?php echo esc_attr( $table_secondary_color ); ?>; font-size: 14px; line-height: 20px; padding: 16px 0 0;"><?php esc_html_e( 'Current level', 'loyalty-for-woocommerce' ); ?></td>
					<td align="right" style="color: <?php echo esc_attr( $table_text_color ); ?>; font-size: 14px; line-height: 20px; padding: 16px 0 0;">
						<?php if ( ! empty( $current_level_icon_url ) ) : ?>
							<img src="<?php echo esc_url( $current_level_icon_url ); ?>" width="24" height="24" alt="<?php echo esc_attr( $current_level_name ); ?>" style="border: 0; display: inline-block; height: 24px; margin: 0 8px 0 0; max-width: 24px; vertical-align: middle; width: 24px;" />
						<?php endif; ?>
						<strong style="color: <?php echo esc_attr( $table_accent ); ?>;"><?php echo esc_html( $current_level_name ); ?></strong>
					</td>
				</tr>
				<tr>
					<td style="color: <?php echo esc_attr( $table_secondary_color ); ?>; font-size: 14px; line-height: 20px; padding: 10px 0 0;"><?php esc_html_e( 'Total earned', 'loyalty-for-woocommerce' ); ?></td>
					<td align="right" style="color: <?php echo esc_attr( $table_text_color ); ?>; font-size: 14px; line-height: 20px; padding: 10px 0 0;"><strong><?php echo esc_html( $earned_total_display ); ?></strong></td>
				</tr>
				<?php if ( ! empty( $next_level_name ) && $points_to_next_level > 0 ) : ?>
					<tr>
						<td colspan="2" style="padding: 16px 0 0;">
							<p style="color: <?php echo esc_attr( $table_secondary_color ); ?>; font-size: 13px; line-height: 19px; margin: 0 0 8px;">
							<?php
							printf(
								/* translators: 1: Points needed, 2: Next loyalty level. */
								esc_html__( 'Earn %1$s more to reach %2$s.', 'loyalty-for-woocommerce' ),
								esc_html( $points_to_next_display ),
								esc_html( $next_level_name )
							);
							?>
							</p>
							<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
								<tr>
									<td style="background: <?php echo esc_attr( $table_progress_background ); ?>; border-radius: 999px; height: 6px; line-height: 6px;">
										<div style="background: <?php echo esc_attr( $table_accent ); ?>; border-radius: 999px; font-size: 0; height: 6px; line-height: 6px; width: <?php echo esc_attr( $progress_width ); ?>%;">&nbsp;</div>
									</td>
								</tr>
							</table>
						</td>
					</tr>
				<?php elseif ( ! empty( $is_highest_level ) ) : ?>
					<tr>
						<td colspan="2" style="color: <?php echo esc_attr( $table_secondary_color ); ?>; font-size: 13px; line-height: 19px; padding: 16px 0 0;">
							<?php esc_html_e( 'You are at the highest loyalty level.', 'loyalty-for-woocommerce' ); ?>
						</td>
					</tr>
				<?php endif; ?>
			</table>
		</td>
	</tr>
</table>

<?php if ( ! empty( $order_number ) ) : ?>
	<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 0 0 24px;">
		<tr>
			<td style="border-top: 1px solid <?php echo esc_attr( $table_border ); ?>; padding: 14px 0; color: <?php echo esc_attr( $table_secondary_color ); ?>;"><?php esc_html_e( 'Related order', 'loyalty-for-woocommerce' ); ?></td>
			<td align="right" style="border-top: 1px solid <?php echo esc_attr( $table_border ); ?>; color: <?php echo esc_attr( $table_text_color ); ?>; padding: 14px 0;"><strong><?php echo esc_html( '#' . $order_number ); ?></strong></td>
		</tr>
	</table>
<?php endif; ?>

<?php if ( ! empty( $points_url ) ) : ?>
	<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 0 24px;">
		<tr>
			<td>
				<a class="button" href="<?php echo esc_url( $points_url ); ?>">
					<?php
					printf(
						/* translators: %s: My account points page label. */
						esc_html__( 'View %s', 'loyalty-for-woocommerce' ),
						esc_html( $points_page_label )
					);
					?>
				</a>
			</td>
		</tr>
	</table>
<?php endif; ?>

<?php
if ( $additional_content ) {
	echo $email_improvements_enabled ? '<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation"><tr><td class="email-additional-content">' : '';
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
	echo $email_improvements_enabled ? '</td></tr></table>' : '';
}

do_action( 'woocommerce_email_footer', $email );
