<?php
/** Free admin navigation, read-only overview and administrator CSV read action. */
defined( 'ABSPATH' ) || exit;
class YOWCL_Free_Reader_Admin {
    public static function boot() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ), 40 );
        add_action( 'admin_post_loyf_export_customers', array( __CLASS__, 'export' ) );
    }
    public static function menu() {
        if ( ! YOWCL_Free_Core::owns() ) { return; }
        add_menu_page( __( 'Loyalty overview', 'loyalty-for-woocommerce' ), __( 'Loyalty', 'loyalty-for-woocommerce' ), 'manage_options', 'loyf-overview', array( __CLASS__, 'overview' ), 'dashicons-awards', 56 );
        add_submenu_page( 'loyf-overview', __( 'Loyalty overview', 'loyalty-for-woocommerce' ), __( 'Overview', 'loyalty-for-woocommerce' ), 'manage_options', 'loyf-overview', array( __CLASS__, 'overview' ) );
        add_submenu_page( 'loyf-overview', __( 'Loyalty campaigns', 'loyalty-for-woocommerce' ), __( 'Campaigns', 'loyalty-for-woocommerce' ), 'manage_options', 'loyf-campaigns-preview', array( __CLASS__, 'campaigns_preview' ) );
        add_submenu_page( 'loyf-overview', __( 'Points history', 'loyalty-for-woocommerce' ), __( 'Points history', 'loyalty-for-woocommerce' ), 'manage_options', 'loyf-history-preview', array( __CLASS__, 'history_preview' ) );
        $emails_hook = add_submenu_page( 'loyf-overview', __( 'Loyalty emails', 'loyalty-for-woocommerce' ), __( 'Emails', 'loyalty-for-woocommerce' ), 'manage_options', 'loyf-emails', array( __CLASS__, 'emails' ) );
        if ( $emails_hook ) { add_action( 'load-' . $emails_hook, array( __CLASS__, 'emails' ) ); }
        $settings_hook = add_submenu_page( 'loyf-overview', __( 'Loyalty settings', 'loyalty-for-woocommerce' ), __( 'Settings', 'loyalty-for-woocommerce' ), 'manage_options', 'loyf-settings', array( __CLASS__, 'settings' ) );
        if ( $settings_hook ) { add_action( 'load-' . $settings_hook, array( __CLASS__, 'settings' ) ); }
    }
    public static function settings() {
        if ( ! self::allowed() ) { wp_die( esc_html__( 'Loyalty settings are unavailable.', 'loyalty-for-woocommerce' ), '', array( 'response' => 403 ) ); }
        wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=loyalty' ) );
        exit;
    }
    public static function emails() {
        if ( ! self::allowed() ) { wp_die( esc_html__( 'Loyalty settings are unavailable.', 'loyalty-for-woocommerce' ), '', array( 'response' => 403 ) ); }
        wp_safe_redirect( admin_url( 'admin.php?page=wc-settings&tab=email&email_group=loyalty' ) );
        exit;
    }
    public static function allowed() { return current_user_can( 'manage_options' ) && YOWCL_Free_Core::owns(); }
    public static function overview() {
        if ( ! self::allowed() ) { wp_die( esc_html__( 'Loyalty overview is unavailable.', 'loyalty-for-woocommerce' ), '', array( 'response'=>403 ) ); }
        $recent = isset( $_GET['period'] ) && is_string( $_GET['period'] ) && '30' === $_GET['period'];
        $unavailable = __( 'Unavailable', 'loyalty-for-woocommerce' );
        $members = $stock = $flows = null;
        try { YOWCL_Free_Readers::snapshot( static function () use ( $recent, &$members, &$stock, &$flows ) {
            try { $members = YOWCL_Free_Readers::member_count(); } catch ( Throwable $e ) {}
            try { $stock = YOWCL_Free_Readers::outstanding(); } catch ( Throwable $e ) {}
            try { $flows = YOWCL_Free_Readers::flows( $recent ); } catch ( Throwable $e ) {}
        } ); } catch ( Throwable $e ) { $members = $stock = $flows = null; }
        echo '<div class="wrap loyf-overview"><div class="loyf-admin-header"><div><h1>' . esc_html__( 'Loyalty overview', 'loyalty-for-woocommerce' ) . '</h1><p>' . esc_html__( 'Free loyalty program · Read-only overview', 'loyalty-for-woocommerce' ) . '</p></div><div class="loyf-admin-actions"><a class="button" href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=loyalty' ) ) . '">' . esc_html__( 'Settings', 'loyalty-for-woocommerce' ) . '</a><a class="button" href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=loyalty&section=tools' ) ) . '">' . esc_html__( 'Tools', 'loyalty-for-woocommerce' ) . '</a></div></div>';
        echo '<form method="get" class="loyf-overview-filters"><input type="hidden" name="page" value="loyf-overview"><label for="loyf-period">' . esc_html__( 'Flow period', 'loyalty-for-woocommerce' ) . '</label> <select id="loyf-period" name="period"><option value="lifetime"' . selected( $recent, false, false ) . '>' . esc_html__( 'Lifetime', 'loyalty-for-woocommerce' ) . '</option><option value="30"' . selected( $recent, true, false ) . '>' . esc_html__( 'Recent 30 days', 'loyalty-for-woocommerce' ) . '</option></select> <button class="button">' . esc_html__( 'View', 'loyalty-for-woocommerce' ) . '</button></form>';
        $metrics = array(
            array( __( 'Current eligible loyalty members', 'loyalty-for-woocommerce' ), $members ?? $unavailable, __( 'Current users with a saved Loyalty role.', 'loyalty-for-woocommerce' ) ),
            array( __( 'Known gross points issued', 'loyalty-for-woocommerce' ), $flows['issued'] ?? $unavailable, __( 'Proven awards in the selected flow period.', 'loyalty-for-woocommerce' ) ),
            array( __( 'Known gross points redeemed', 'loyalty-for-woocommerce' ), $flows['redeemed'] ?? $unavailable, __( 'Gross funded checkout debit in the selected flow period.', 'loyalty-for-woocommerce' ) ),
            array( __( 'Current available points outstanding', 'loyalty-for-woocommerce' ), $stock['value'] ?? $unavailable, __( 'Stored balances now, including held balances.', 'loyalty-for-woocommerce' ) ),
            array( __( 'Distinct proven funded orders', 'loyalty-for-woocommerce' ), $flows['orders'] ?? $unavailable, __( 'Orders with proven funding in the selected flow period.', 'loyalty-for-woocommerce' ) ),
        );
        echo '<div class="loyf-overview-grid">';
        foreach ( $metrics as $metric ) {
            echo '<section class="loyf-overview-card"><h2>' . esc_html( $metric[0] ) . '</h2><strong>' . esc_html( (string) $metric[1] ) . '</strong><p>' . esc_html( $metric[2] ) . '</p></section>';
        }
        echo '</div><section class="loyf-overview-panel"><h2>' . esc_html__( 'Data coverage', 'loyalty-for-woocommerce' ) . '</h2><p>';
        echo esc_html__( 'Members and stock cover current users admitted by saved Loyalty roles. Stock is stored balance as of now, including held balances; it is not historical spendable value.', 'loyalty-for-woocommerce' ) . '</p><p>' . esc_html__( 'Flows cover proven canonical events across users. Awards exclude imports, returns and reversals. Redeemed is gross funded checkout debit; returns do not reduce it. No redemption share is shown because a matching qualifying order denominator is not established.', 'loyalty-for-woocommerce' ) . '</p>';
        /* translators: Number of accounts with held balances. */
        if ( $stock ) { echo '<p>' . esc_html( sprintf( __( 'Accounts with held balances: %d.', 'loyalty-for-woocommerce' ), $stock['held'] ) ) . '</p>'; }
        if ( $flows ) {
            /* translators: Number of unproven historical rows. */
            echo '<p>' . esc_html( sprintf( __( 'Excluded unproven legacy rows: %d.', 'loyalty-for-woocommerce' ), $flows['legacy'] ) ) . '</p>';
            /* translators: Number of debits without checkout funding proof. */
            echo '<p>' . esc_html( sprintf( __( 'Excluded debits without native checkout funding proof: %d.', 'loyalty-for-woocommerce' ), $flows['unproven_debits'] ) ) . '</p>';
            if ( $recent ) { echo '<p>' . esc_html( wp_date( 'Y-m-d H:i:s', intdiv( $flows['start'], 1000000 ), new DateTimeZone( $flows['timezone'] ) ) . ' – ' . wp_date( 'Y-m-d H:i:s', intdiv( $flows['end'], 1000000 ), new DateTimeZone( $flows['timezone'] ) ) . ' (' . $flows['timezone'] . ')' ) . '</p>'; }
        }
        echo '<p>' . esc_html__( 'Unavailable means failed or incomplete evidence, ambiguous metadata, unsupported precision, missing time proof, or the safe read limit. No value is repaired on this page.', 'loyalty-for-woocommerce' ) . '</p></section>';
        self::premium_card( 'overview' );
        echo '</div>';
    }
    /** Administrator-only discovery; no customer data, settings or remote requests. */
    public static function premium_card( $context ) {
        if ( ! self::allowed() || apply_filters( 'yoswc_loyalty_is_premium', false ) ) { return; }
        $cards = array(
            'referrals' => array(
                __( 'Grow your referral program', 'loyalty-for-woocommerce' ),
                __( 'Free includes Referral Lite with fixed referrer points.', 'loyalty-for-woocommerce' ),
                __( 'Compare Free & Premium', 'loyalty-for-woocommerce' ),
                __( 'Premium adds referral coupons, referee rewards and flexible rules by loyalty level.', 'loyalty-for-woocommerce' ),
                __( 'Explore referral options', 'loyalty-for-woocommerce' ),
            ),
            'emails' => array(
                __( 'Advanced Loyalty Emails', 'loyalty-for-woocommerce' ),
                __( 'Your three Free loyalty emails remain managed by WooCommerce.', 'loyalty-for-woocommerce' ),
                __( 'Explore advanced emails', 'loyalty-for-woocommerce' ),
                __( 'Premium offers expiring-points reminders, referral notifications and points statements.', 'loyalty-for-woocommerce' ),
                __( 'Explore Premium emails', 'loyalty-for-woocommerce' ),
            ),
            'overview' => array(
                __( 'Grow your loyalty program', 'loyalty-for-woocommerce' ),
                __( 'Explore campaigns, automations and advanced insights for your next stage.', 'loyalty-for-woocommerce' ),
                __( 'Explore program options', 'loyalty-for-woocommerce' ),
                __( 'See illustrative previews of time-limited campaigns and site-wide points history.', 'loyalty-for-woocommerce' ),
                __( 'Explore Premium options', 'loyalty-for-woocommerce' ),
            ),
            'tools' => array(
                __( 'Advanced points workflows', 'loyalty-for-woocommerce' ),
                __( 'Free includes customer CSV import and export.', 'loyalty-for-woocommerce' ),
                __( 'Explore advanced bulk workflows', 'loyalty-for-woocommerce' ),
                __( 'Premium provides points reconciliation and additional bulk workflows for larger programs.', 'loyalty-for-woocommerce' ),
                __( 'Explore points reconciliation', 'loyalty-for-woocommerce' ),
            ),
            'customization' => array(
                __( 'More member experiences', 'loyalty-for-woocommerce' ),
                __( 'Build on your Free My Account points display.', 'loyalty-for-woocommerce' ),
                __( 'Explore member experiences', 'loyalty-for-woocommerce' ),
                __( 'Premium offers product redemption and advanced member experiences.', 'loyalty-for-woocommerce' ),
                __( 'Explore Premium member options', 'loyalty-for-woocommerce' ),
            ),
        );
        if ( ! isset( $cards[$context] ) ) { return; }
        $card = $cards[$context];
        echo '<section class="loyf-premium-card" data-loyf-premium="' . esc_attr( $context ) . '"><h2>' . esc_html( $card[0] ) . '</h2><p>' . esc_html( $card[1] ) . '</p><details><summary>' . esc_html( $card[2] ) . '</summary><p>' . esc_html( $card[3] ) . '</p>';
        if ( 'overview' === $context ) {
            echo '<p class="loyf-admin-actions"><a class="button" href="' . esc_url( admin_url( 'admin.php?page=loyf-campaigns-preview' ) ) . '">' . esc_html__( 'Campaigns preview', 'loyalty-for-woocommerce' ) . '</a><a class="button" href="' . esc_url( admin_url( 'admin.php?page=loyf-history-preview' ) ) . '">' . esc_html__( 'Points history preview', 'loyalty-for-woocommerce' ) . '</a></p>';
        }
        self::premium_link( $card[4] );
        echo '</details></section>';
    }
    private static function premium_link( $label ) {
        echo '<p><a class="button" href="' . esc_url( apply_filters( 'yoswc_loyalty_premium_url', 'https://yoohw.com/product/woocommerce-loyalty-points-and-rewards/' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $label ) . ' ↗</a></p>';
    }
    public static function campaigns_preview() {
        if ( ! self::allowed() ) { wp_die( esc_html__( 'Loyalty preview is unavailable.', 'loyalty-for-woocommerce' ), '', array( 'response' => 403 ) ); }
        ?>
        <div class="wrap loyf-preview">
            <h1><?php esc_html_e( 'Loyalty campaigns', 'loyalty-for-woocommerce' ); ?></h1>
            <p class="loyf-preview-label"><?php esc_html_e( 'Premium preview · Example only', 'loyalty-for-woocommerce' ); ?></p>
            <p><?php esc_html_e( 'Run time-limited promotions with targeted point rewards in the separate Premium add-on.', 'loyalty-for-woocommerce' ); ?></p>
            <div class="loyf-campaign-examples">
                <details class="loyf-campaign-example" open>
                    <summary><span><?php esc_html_e( 'Double-points weekend', 'loyalty-for-woocommerce' ); ?></span><span class="loyf-example-badge"><?php esc_html_e( 'Example only', 'loyalty-for-woocommerce' ); ?></span></summary>
                    <div class="loyf-campaign-body">
                        <section><h2><?php esc_html_e( 'Campaign details', 'loyalty-for-woocommerce' ); ?></h2><dl class="loyf-example-fields"><div><dt><?php esc_html_e( 'Campaign name', 'loyalty-for-woocommerce' ); ?></dt><dd><?php esc_html_e( 'Double-points weekend', 'loyalty-for-woocommerce' ); ?></dd></div><div><dt><?php esc_html_e( 'Priority', 'loyalty-for-woocommerce' ); ?></dt><dd>10</dd></div></dl></section>
                        <section><h2><?php esc_html_e( 'Reward', 'loyalty-for-woocommerce' ); ?></h2><dl class="loyf-example-fields"><div><dt><?php esc_html_e( 'Reward type', 'loyalty-for-woocommerce' ); ?></dt><dd><?php esc_html_e( 'Multiplier', 'loyalty-for-woocommerce' ); ?></dd></div><div><dt><?php esc_html_e( 'Multiplier', 'loyalty-for-woocommerce' ); ?></dt><dd>2×</dd></div></dl></section>
                        <section><h2><?php esc_html_e( 'Schedule & limits', 'loyalty-for-woocommerce' ); ?></h2><dl class="loyf-example-fields"><div><dt><?php esc_html_e( 'Promotional period', 'loyalty-for-woocommerce' ); ?></dt><dd><?php esc_html_e( 'A selected weekend', 'loyalty-for-woocommerce' ); ?></dd></div><div><dt><?php esc_html_e( 'Days', 'loyalty-for-woocommerce' ); ?></dt><dd><?php esc_html_e( 'Friday · Saturday · Sunday', 'loyalty-for-woocommerce' ); ?></dd></div></dl></section>
                        <section><h2><?php esc_html_e( 'Targeting', 'loyalty-for-woocommerce' ); ?></h2><dl class="loyf-example-fields"><div><dt><?php esc_html_e( 'Products / categories', 'loyalty-for-woocommerce' ); ?></dt><dd><?php esc_html_e( 'All eligible purchases', 'loyalty-for-woocommerce' ); ?></dd></div><div><dt><?php esc_html_e( 'Members', 'loyalty-for-woocommerce' ); ?></dt><dd><?php esc_html_e( 'Eligible loyalty members', 'loyalty-for-woocommerce' ); ?></dd></div></dl></section>
                    </div>
                </details>
                <details class="loyf-campaign-example">
                    <summary><span><?php esc_html_e( 'Seasonal member rewards', 'loyalty-for-woocommerce' ); ?></span><span class="loyf-example-badge"><?php esc_html_e( 'Example only', 'loyalty-for-woocommerce' ); ?></span></summary>
                    <div class="loyf-campaign-body">
                        <section><h2><?php esc_html_e( 'Campaign details', 'loyalty-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Example only: offer a category bonus during a selected promotional period.', 'loyalty-for-woocommerce' ); ?></p></section>
                        <section><h2><?php esc_html_e( 'Reward', 'loyalty-for-woocommerce' ); ?></h2><dl class="loyf-example-fields"><div><dt><?php esc_html_e( 'Reward type', 'loyalty-for-woocommerce' ); ?></dt><dd><?php esc_html_e( 'Fixed bonus points', 'loyalty-for-woocommerce' ); ?></dd></div><div><dt><?php esc_html_e( 'Bonus points', 'loyalty-for-woocommerce' ); ?></dt><dd>50</dd></div></dl></section>
                        <section><h2><?php esc_html_e( 'Schedule & limits', 'loyalty-for-woocommerce' ); ?></h2><p><?php esc_html_e( 'Example: a selected seasonal week.', 'loyalty-for-woocommerce' ); ?></p></section>
                        <section><h2><?php esc_html_e( 'Targeting', 'loyalty-for-woocommerce' ); ?></h2><dl class="loyf-example-fields"><div><dt><?php esc_html_e( 'Products / categories', 'loyalty-for-woocommerce' ); ?></dt><dd><?php esc_html_e( 'A selected product category', 'loyalty-for-woocommerce' ); ?></dd></div><div><dt><?php esc_html_e( 'Members', 'loyalty-for-woocommerce' ); ?></dt><dd><?php esc_html_e( 'A selected loyalty level or eligible segment', 'loyalty-for-woocommerce' ); ?></dd></div></dl></section>
                    </div>
                </details>
            </div>
            <p><?php esc_html_e( 'These illustrations do not create campaigns or change your Free rewards.', 'loyalty-for-woocommerce' ); ?></p>
            <?php self::premium_link( __( 'Explore Loyalty Campaigns in Premium', 'loyalty-for-woocommerce' ) ); ?>
        </div>
        <?php
    }
    public static function history_preview() {
        if ( ! self::allowed() ) { wp_die( esc_html__( 'Loyalty preview is unavailable.', 'loyalty-for-woocommerce' ), '', array( 'response' => 403 ) ); }
        ?>
        <div class="wrap loyf-preview">
            <h1><?php esc_html_e( 'Points history', 'loyalty-for-woocommerce' ); ?></h1>
            <p class="loyf-preview-label"><?php esc_html_e( 'Premium preview · Example only', 'loyalty-for-woocommerce' ); ?></p>
            <p><?php esc_html_e( 'Explore site-wide point activity in the separate Premium add-on, with filters by customer, action, order, date and description.', 'loyalty-for-woocommerce' ); ?></p>
            <section class="loyf-history-filters">
                <h2><?php esc_html_e( 'Filter preview · Illustrative criteria', 'loyalty-for-woocommerce' ); ?></h2>
                <dl class="loyf-example-fields">
                    <div><dt><?php esc_html_e( 'Customer', 'loyalty-for-woocommerce' ); ?></dt><dd><?php esc_html_e( 'Sample members', 'loyalty-for-woocommerce' ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Action', 'loyalty-for-woocommerce' ); ?></dt><dd><?php esc_html_e( 'All sample actions', 'loyalty-for-woocommerce' ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Order', 'loyalty-for-woocommerce' ); ?></dt><dd><?php esc_html_e( 'Sample orders', 'loyalty-for-woocommerce' ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Date range', 'loyalty-for-woocommerce' ); ?></dt><dd><?php esc_html_e( 'Example period', 'loyalty-for-woocommerce' ); ?></dd></div>
                    <div><dt><?php esc_html_e( 'Description', 'loyalty-for-woocommerce' ); ?></dt><dd><?php esc_html_e( 'Example activity', 'loyalty-for-woocommerce' ); ?></dd></div>
                </dl>
                <p class="description"><?php esc_html_e( 'This static preview shows the Premium filter layout; it does not search your store.', 'loyalty-for-woocommerce' ); ?></p>
            </section>
            <div class="loyf-preview-table" role="region" aria-label="<?php esc_attr_e( 'Illustrative points history', 'loyalty-for-woocommerce' ); ?>" tabindex="0">
                <table class="widefat striped">
                    <caption><?php esc_html_e( 'Sample rows only — no store or customer records are shown.', 'loyalty-for-woocommerce' ); ?></caption>
                    <thead><tr>
                        <?php foreach ( array( __( 'Date', 'loyalty-for-woocommerce' ), __( 'Customer', 'loyalty-for-woocommerce' ), __( 'Action', 'loyalty-for-woocommerce' ), __( 'Amount', 'loyalty-for-woocommerce' ), __( 'Order', 'loyalty-for-woocommerce' ), __( 'Description', 'loyalty-for-woocommerce' ), __( 'Expires', 'loyalty-for-woocommerce' ) ) as $label ) { echo '<th scope="col">' . esc_html( $label ) . '</th>'; } ?>
                    </tr></thead>
                    <tbody><tr>
                        <td><?php esc_html_e( 'Example date', 'loyalty-for-woocommerce' ); ?></td><td><?php esc_html_e( 'Sample member A', 'loyalty-for-woocommerce' ); ?></td><td><?php esc_html_e( 'Purchase reward', 'loyalty-for-woocommerce' ); ?></td><td>+100</td><td><?php esc_html_e( 'Sample order A', 'loyalty-for-woocommerce' ); ?></td><td><?php esc_html_e( 'Illustrative purchase points', 'loyalty-for-woocommerce' ); ?></td><td><?php esc_html_e( 'Example expiry', 'loyalty-for-woocommerce' ); ?></td>
                    </tr><tr>
                        <td><?php esc_html_e( 'Example date', 'loyalty-for-woocommerce' ); ?></td><td><?php esc_html_e( 'Sample member B', 'loyalty-for-woocommerce' ); ?></td><td><?php esc_html_e( 'Redemption', 'loyalty-for-woocommerce' ); ?></td><td>−50</td><td><?php esc_html_e( 'Sample order B', 'loyalty-for-woocommerce' ); ?></td><td><?php esc_html_e( 'Illustrative use of points', 'loyalty-for-woocommerce' ); ?></td><td>—</td>
                    </tr></tbody>
                </table>
            </div>
            <p><?php esc_html_e( 'Free customer My Points history continues to show each customer’s own records.', 'loyalty-for-woocommerce' ); ?></p>
            <?php self::premium_link( __( 'Explore advanced Points history in Premium', 'loyalty-for-woocommerce' ) ); ?>
        </div>
        <?php
    }
    public static function form() {
        if ( ! self::allowed() ) { return; }
        echo '<h2>' . esc_html__( 'Export customer loyalty state', 'loyalty-for-woocommerce' ) . '</h2><p>' . esc_html__( 'Current eligible customers only; user ID, email, exact stored balances and Loyalty level. Maximum 10,000 customers. This CSV is not an import template. A final complete record is required; an error record or missing footer means incomplete. Exact numbers and unsafe spreadsheet text are exported as tab-prefixed text.', 'loyalty-for-woocommerce' ) . '</p>';
        wp_nonce_field( 'loyf_export_customers', 'loyf_export_nonce' );
        // Woo settings already owns the outer form; HTML button overrides avoid invalid nested forms.
        echo '<button type="submit" name="action" value="loyf_export_customers" formmethod="post" formaction="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="button">' . esc_html__( 'Download customer CSV', 'loyalty-for-woocommerce' ) . '</button>';
    }
    /** CSV text guard is intentional display escaping; numeric metadata is kept exact. */
    public static function csv_cell( $value ) {
        $value = (string) $value;
        if ( preg_match( '/^[\s\x00-\x1f\x7f]*[=+@\-＝＋－＠]/u', $value ) || preg_match( '/[\x00-\x1f\x7f]/', $value ) ) { $value = "\t" . $value; }
        return $value;
    }
    public static function csv_row( $stream, array $row, array $exact_columns = array() ) {
        $cells = array();
        foreach ( $row as $index => $value ) { $cells[] = in_array( $index, $exact_columns, true ) && YOWCL_Free_Readers::decimal( (string) $value ) ? "\t" . $value : self::csv_cell( $value ); }
        if ( false === fputcsv( $stream, $cells, ',', '"', '' ) ) { throw new RuntimeException( 'reader_export_write_failed' ); }
    }
    /** Caller supplies the stream; endpoint supplies authority and private response headers. */
    public static function stream( $stream ) {
        global $wpdb;
        $count = YOWCL_Free_Readers::member_count(); $cursor = 0; $written = 0;
        self::csv_row( $stream, array( 'record_type','user_id','email','available_points','earned_points','loyalty_level','balance_state' ) );
        try {
            while ( $written < $count ) {
                $where = YOWCL_Free_Readers::population();
                $rows = YOWCL_Free_Readers::query( "SELECT u.ID,u.user_email FROM {$wpdb->users} u WHERE u.ID > " . (int) $cursor . " AND $where ORDER BY u.ID LIMIT " . YOWCL_Free_Readers::BATCH );
                if ( ! $rows ) { throw new RuntimeException( 'reader_export_incomplete' ); }
                foreach ( $rows as $row ) { if ( ! preg_match( '/^[1-9][0-9]{0,17}$/D', $row['ID'] ) ) { throw new RuntimeException( 'reader_user_id_unavailable' ); } }
                $ids = array_map( 'intval', array_column( $rows, 'ID' ) );
                $meta = YOWCL_Free_Readers::meta( $ids );
                foreach ( $rows as $row ) {
                    $id = (int) $row['ID'];
                    if ( ! YOWCL_Free_Readers::member_valid( $meta[$id] ) ) { throw new RuntimeException( 'reader_membership_unavailable' ); }
                    $available = YOWCL_Free_Readers::state( $meta[$id]['user_points'] ?? array() ); $earned = YOWCL_Free_Readers::state( $meta[$id]['user_earning_points'] ?? array() );
                    $level = YOWCL_Free_Readers::level( $id, $meta[$id] );
                    $state = null === $available['value'] || null === $earned['value'] ? 'unavailable' : ( YOWCL_Free_Readers::economic_hold( $meta[$id], $available, $earned ) ? 'held' : 'stored' );
                    self::csv_row( $stream, array( 'customer',(string) $id,$row['user_email'],$available['value'] ?? 'Unavailable',$earned['value'] ?? 'Unavailable',$level ?? 'Unavailable',$state ), array( 1,3,4 ) );
                    $cursor = $id; $written++;
                    if ( $written > $count || $written > YOWCL_Free_Readers::MEMBER_LIMIT ) { throw new RuntimeException( 'reader_export_limit' ); }
                }
                fflush( $stream );
            }
            YOWCL_Free_Readers::connection();
            self::csv_row( $stream, array( 'complete',(string) $written,'','','','','complete' ) );
        } catch ( Throwable $e ) {
            self::csv_row( $stream, array( 'error',(string) $written,'','','','','Export incomplete; retry after review.' ) );
        }
    }
    public static function export() {
        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! self::allowed() || ! isset( $_POST['loyf_export_nonce'] ) || ! is_string( $_POST['loyf_export_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['loyf_export_nonce'] ), 'loyf_export_customers' ) ) { wp_die( esc_html__( 'Export access denied.', 'loyalty-for-woocommerce' ), '', array( 'response'=>403 ) ); }
        try { YOWCL_Free_Readers::snapshot( static function () {
            YOWCL_Free_Readers::member_count(); // Preflight the safe bound before a download begins.
            $stream = fopen( 'php://output', 'wb' );
            if ( false === $stream ) { throw new RuntimeException( 'reader_export_stream_unavailable' ); }
            nocache_headers(); header( 'Cache-Control: private, no-store, max-age=0' ); header( 'X-Content-Type-Options: nosniff' ); header( 'Content-Type: text/csv; charset=UTF-8' ); header( 'Content-Disposition: attachment; filename="loyalty-customers.csv"' );
            try { self::stream( $stream ); } finally { fclose( $stream ); }
        } ); } catch ( Throwable $e ) { wp_die( esc_html__( 'Export unavailable. No complete customer export was produced.', 'loyalty-for-woocommerce' ), '', array( 'response'=>503 ) ); }
        exit;
    }
}
