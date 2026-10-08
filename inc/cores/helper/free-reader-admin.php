<?php
/** Small Woo-native overview and separate administrator CSV read action. */
defined( 'ABSPATH' ) || exit;
class YOWCL_Free_Reader_Admin {
    public static function boot() {
        add_action( 'admin_menu', static function () { add_submenu_page( 'woocommerce', __( 'Loyalty overview', 'loyalty-for-woocommerce' ), __( 'Loyalty overview', 'loyalty-for-woocommerce' ), 'manage_options', 'loyf-overview', array( __CLASS__, 'overview' ) ); } );
        add_action( 'admin_post_loyf_export_customers', array( __CLASS__, 'export' ) );
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
        echo '<div class="wrap"><h1>' . esc_html__( 'Loyalty overview', 'loyalty-for-woocommerce' ) . '</h1><form method="get"><input type="hidden" name="page" value="loyf-overview"><label for="loyf-period">' . esc_html__( 'Flow period', 'loyalty-for-woocommerce' ) . '</label> <select id="loyf-period" name="period"><option value="lifetime"' . selected( $recent, false, false ) . '>' . esc_html__( 'Lifetime', 'loyalty-for-woocommerce' ) . '</option><option value="30"' . selected( $recent, true, false ) . '>' . esc_html__( 'Recent 30 days', 'loyalty-for-woocommerce' ) . '</option></select> <button class="button">' . esc_html__( 'View', 'loyalty-for-woocommerce' ) . '</button></form>';
        $metrics = array( __( 'Current eligible loyalty members', 'loyalty-for-woocommerce' )=>$members ?? $unavailable, __( 'Known gross points issued', 'loyalty-for-woocommerce' )=>$flows['issued'] ?? $unavailable, __( 'Known gross points redeemed', 'loyalty-for-woocommerce' )=>$flows['redeemed'] ?? $unavailable, __( 'Current available points outstanding', 'loyalty-for-woocommerce' )=>$stock['value'] ?? $unavailable, __( 'Distinct proven funded orders', 'loyalty-for-woocommerce' )=>$flows['orders'] ?? $unavailable );
        echo '<table class="widefat striped"><tbody>';
        foreach ( $metrics as $label => $value ) { echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( (string) $value ) . '</td></tr>'; }
        echo '</tbody></table><p>' . esc_html__( 'Members and stock cover current users admitted by saved Loyalty roles. Stock is stored balance as of now, including held balances; it is not historical spendable value.', 'loyalty-for-woocommerce' ) . '</p><p>' . esc_html__( 'Flows cover proven canonical events across users. Awards exclude imports, returns and reversals. Redeemed is gross funded checkout debit; returns do not reduce it. No redemption share is shown because a matching qualifying order denominator is not established.', 'loyalty-for-woocommerce' ) . '</p>';
        if ( $stock ) { echo '<p>' . esc_html( sprintf( __( 'Accounts with held balances: %d.', 'loyalty-for-woocommerce' ), $stock['held'] ) ) . '</p>'; }
        if ( $flows ) {
            echo '<p>' . esc_html( sprintf( __( 'Excluded unproven legacy rows: %d.', 'loyalty-for-woocommerce' ), $flows['legacy'] ) ) . '</p>';
            echo '<p>' . esc_html( sprintf( __( 'Excluded debits without native checkout funding proof: %d.', 'loyalty-for-woocommerce' ), $flows['unproven_debits'] ) ) . '</p>';
            if ( $recent ) { echo '<p>' . esc_html( wp_date( 'Y-m-d H:i:s', intdiv( $flows['start'], 1000000 ), new DateTimeZone( $flows['timezone'] ) ) . ' – ' . wp_date( 'Y-m-d H:i:s', intdiv( $flows['end'], 1000000 ), new DateTimeZone( $flows['timezone'] ) ) . ' (' . $flows['timezone'] . ')' ) . '</p>'; }
        }
        echo '<p>' . esc_html__( 'Unavailable means failed or incomplete evidence, ambiguous metadata, unsupported precision, missing time proof, or the safe read limit. No value is repaired on this page.', 'loyalty-for-woocommerce' ) . '</p></div>';
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
