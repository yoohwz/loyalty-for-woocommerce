<?php
/** Bounded, read-only Free value consumers. No accounting writes or repair. */
defined( 'ABSPATH' ) || exit;
class YOWCL_Free_Readers {
    const LOG_LIMIT = 5000;
    const MEMBER_LIMIT = 10000;
    const BATCH = 200;
    const BYTE_LIMIT = 4194304;
    private static $snapshot_db = null;
    private static $snapshot_id = null;
    private static $snapshot_options = array();
    public static function connection() {
        global $wpdb;
        if ( null !== self::$snapshot_db && ( $wpdb->dbh !== self::$snapshot_db || self::$snapshot_id !== (string) YOWCL_Points_Lock::scalar( self::$snapshot_db, 'SELECT CONNECTION_ID()' ) ) ) { throw new RuntimeException( 'reader_snapshot_lost' ); }
    }
    public static function snapshot( $callback ) {
        global $wpdb;
        $db = $wpdb->dbh;
        if ( ! ( $db instanceof mysqli ) || null !== self::$snapshot_db || YOWCL_Points_Lock::has_transaction( $db ) ) { throw new RuntimeException( 'reader_snapshot_unavailable' ); }
        $tables = array( $wpdb->users,$wpdb->usermeta,$wpdb->options,YOWCL_Points_Log::table_name() );
        foreach ( $tables as $table ) { if ( 'InnoDB' !== YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) ) ) { throw new RuntimeException( 'reader_snapshot_storage_unavailable' ); } }
        self::$snapshot_id = (string) YOWCL_Points_Lock::scalar( $db, 'SELECT CONNECTION_ID()' );
        YOWCL_Points_Lock::query( $db, 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ' );
        YOWCL_Points_Lock::query( $db, 'START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY' ); self::$snapshot_db = $db; self::$snapshot_options = array();
        try { self::connection(); $value = $callback(); self::connection(); return $value; }
        finally { self::$snapshot_db = null; self::$snapshot_id = null; self::$snapshot_options = array(); try { YOWCL_Points_Lock::query( $db, 'ROLLBACK' ); } catch ( Throwable $ignored ) {} }
    }
    public static function query( $sql ) {
        global $wpdb;
        self::connection();
        $result = $wpdb->get_results( $sql, ARRAY_A );
        self::connection();
        if ( $wpdb->last_error || ! is_array( $result ) ) { throw new RuntimeException( 'reader_query_unavailable' ); }
        return $result;
    }
    public static function option( $name, $default = array() ) {
        global $wpdb;
        if ( null !== self::$snapshot_db && array_key_exists( $name, self::$snapshot_options ) ) { return self::$snapshot_options[$name]; }
        $rows = self::query( $wpdb->prepare( "SELECT IF(OCTET_LENGTH(option_value)<=262144,option_value,NULL) option_value FROM {$wpdb->options} WHERE option_name=%s LIMIT 2", $name ) );
        if ( count( $rows ) > 1 || ( $rows && null === $rows[0]['option_value'] ) ) { throw new RuntimeException( 'reader_option_unavailable' ); }
        $value = $rows ? maybe_unserialize( $rows[0]['option_value'] ) : $default;
        if ( null !== self::$snapshot_db ) { self::$snapshot_options[$name] = $value; }
        return $value;
    }
    public static function roles() {
        $roles = maybe_unserialize( self::option( 'loyalty_levels_roles', array() ) );
        if ( ! is_array( $roles ) || count( $roles ) > 100 ) { throw new RuntimeException( 'reader_roles_unavailable' ); }
        foreach ( $roles as $role ) { if ( ! is_string( $role ) || '' === $role || sanitize_key( $role ) !== $role || ! get_role( $role ) ) { throw new RuntimeException( 'reader_roles_unavailable' ); } }
        return array_values( array_unique( $roles ) );
    }
    /** Same role admission expression as native WP_User_Query, scoped to this site's capabilities. */
    public static function population( $potential = false ) {
        global $wpdb;
        $clauses = array();
        foreach ( self::roles() as $role ) { $clauses[] = $wpdb->prepare( 'BINARY c.meta_value LIKE %s', '%' . $wpdb->esc_like( '"' . $role . '";' . ( $potential ? '' : 'b:' ) ) . '%' ); }
        if ( ! $clauses ) { return '0=1'; }
        return $wpdb->prepare( "EXISTS (SELECT 1 FROM {$wpdb->usermeta} c WHERE c.user_id=u.ID AND c.meta_key=%s AND (", $wpdb->get_blog_prefix() . 'capabilities' ) . implode( ' OR ', $clauses ) . '))';
    }
    /** Set-based proof for the native role-query candidate population. Unknown capability shapes are not counted as exact. */
    public static function membership_proof() {
        global $wpdb;
        $definitions = wp_roles()->roles; $roles = array_keys( $definitions );
        foreach ( $definitions as $definition ) { if ( is_array( $definition['capabilities'] ?? null ) ) { $roles = array_merge( $roles, array_keys( $definition['capabilities'] ) ); } }
        $roles = array_values( array_unique( array_filter( $roles, static function ( $key ) { return is_string( $key ) && '' !== $key && sanitize_key( $key ) === $key; } ) ) );
        if ( count( $roles ) > 500 ) { throw new RuntimeException( 'reader_capability_shape_unavailable' ); }
        $terms = array(); $unique = array();
        foreach ( $roles as $role ) {
            if ( sanitize_key( $role ) !== $role ) { throw new RuntimeException( 'reader_capability_shape_unavailable' ); }
            $term = 's:' . strlen( $role ) . ':"' . $role . '";b:';
            $terms[] = $term . '[01];';
            $unique[] = $wpdb->prepare( "(LENGTH(c.meta_value)-LENGTH(REPLACE(c.meta_value,%s,'')))/%d <= 1", $term, strlen( $term ) );
        }
        $pattern = '^a:[0-9]+:[{](' . implode( '|', $terms ) . ')*[}]$';
        $where = self::population( true );
        $shape = $wpdb->prepare( '(CONVERT(c.meta_value USING utf8mb4) COLLATE utf8mb4_bin) REGEXP %s', $pattern );
        $size = "CAST(SUBSTRING_INDEX(SUBSTRING(c.meta_value,3),':',1) AS UNSIGNED) = (LENGTH(c.meta_value)-LENGTH(REPLACE(c.meta_value,';b:','')))/3";
        $sql = $wpdb->prepare( "SELECT COUNT(*) n FROM {$wpdb->users} u WHERE $where AND ((SELECT COUNT(*) FROM {$wpdb->usermeta} d WHERE d.user_id=u.ID AND d.meta_key=%s) <> 1 OR EXISTS (SELECT 1 FROM {$wpdb->usermeta} c WHERE c.user_id=u.ID AND c.meta_key=%s AND NOT ($shape AND $size AND ", $wpdb->get_blog_prefix() . 'capabilities', $wpdb->get_blog_prefix() . 'capabilities' ) . implode( ' AND ', $unique ) . ')))';
        if ( (int) self::query( $sql )[0]['n'] ) { throw new RuntimeException( 'reader_membership_unavailable' ); }
    }
    public static function member_count() {
        global $wpdb;
        $where = self::population();
        $count = self::query( "SELECT COUNT(*) n FROM (SELECT u.ID FROM {$wpdb->users} u WHERE $where LIMIT " . ( self::MEMBER_LIMIT + 1 ) . ') eligible' )[0]['n'];
        if ( (int) $count > self::MEMBER_LIMIT ) { throw new RuntimeException( 'reader_member_limit' ); }
        self::membership_proof();
        return (int) $count;
    }
    /** Preserve spelling. Unsupported aggregate precision is not normalized. */
    public static function decimal( $value ) { return is_string( $value ) && strlen( $value ) <= 256 && preg_match( '/^[0-9]+(?:\.[0-9]+)?$/D', $value ); }
    public static function state( $values ) {
        return 1 === count( $values ) && self::decimal( $values[0] ) ? array( 'value'=>$values[0], 'status'=>'stored' ) : array( 'value'=>null, 'status'=>'unavailable' );
    }
    public static function meta( array $ids ) {
        global $wpdb;
        if ( ! $ids || count( $ids ) > self::BATCH ) { throw new RuntimeException( 'reader_batch_invalid' ); }
        $keys = array( 'user_points','user_earning_points','_loyf_economic_hold',YOWCL_Ledger_V2::CHECKPOINT_META,YOWCL_Helper_Roles::LOYALTY_LEVEL_META,$wpdb->get_blog_prefix() . 'capabilities' );
        $sql = "SELECT user_id,meta_key,meta_value FROM {$wpdb->usermeta} WHERE user_id IN (" . implode( ',', array_map( 'absint', $ids ) ) . ') AND meta_key IN (' . $wpdb->prepare( implode( ',', array_fill( 0, count( $keys ), '%s' ) ), $keys ) . ') ORDER BY umeta_id LIMIT 2001';
        $where = substr( $sql, strpos( $sql, ' WHERE ' ), strpos( $sql, ' ORDER BY ' ) - strpos( $sql, ' WHERE ' ) );
        $size = self::query( "SELECT COUNT(*) n,COALESCE(SUM(OCTET_LENGTH(meta_value)),0) bytes FROM {$wpdb->usermeta}" . $where )[0];
        if ( (int) $size['n'] > 2000 || (int) $size['bytes'] > self::BYTE_LIMIT ) { throw new RuntimeException( 'reader_metadata_limit' ); }
        $rows = self::query( $sql );
        if ( count( $rows ) !== (int) $size['n'] ) { throw new RuntimeException( 'reader_metadata_changed' ); }
        $meta = array_fill_keys( $ids, array() );
        foreach ( $rows as $row ) { $meta[$row['user_id']][$row['meta_key']][] = $row['meta_value']; }
        return $meta;
    }
    public static function member_valid( array $meta ) {
        global $wpdb;
        $values = $meta[$wpdb->get_blog_prefix() . 'capabilities'] ?? array();
        if ( 1 !== count( $values ) ) { return false; }
        $caps = maybe_unserialize( $values[0] );
        if ( ! is_array( $caps ) ) { return false; }
        foreach ( self::roles() as $role ) { if ( array_key_exists( $role, $caps ) && is_bool( $caps[$role] ) ) { return true; } }
        return false;
    }
    public static function level( $id, array $meta ) {
        if ( PHP_INT_SIZE < 8 ) { return null; }
        $rules = self::option( 'loyalty_levels_rules', array() );
        if ( ! is_array( $rules ) || count( $rules ) > 100 ) { return null; }
        $rules = array_merge( array( 'customer'=>array( 'from'=>0 ) ), $rules );
        foreach ( $rules as $slug => $rule ) {
            // The native helper compares floats. Restrict thresholds to its exact integer range.
            if ( ! is_array( $rule ) ) { return null; }
            $from = $rule['from'] ?? null;
            if ( ! is_scalar( $from ) || ! preg_match( '/^(0|[1-9][0-9]{0,14})$/D', (string) $from ) || ! get_role( $slug ) ) { return null; }
        }
        $stored = $meta[YOWCL_Helper_Roles::LOYALTY_LEVEL_META] ?? array();
        if ( count( $stored ) > 1 || ( $stored && ! isset( $rules[$stored[0]] ) ) ) { return null; }
        // Snapshot adaptation of the accepted highest-role helper: stored level first, then highest assigned threshold.
        // Do not load unrelated user metadata or trust a stale persistent object cache.
        global $wpdb;
        $caps = maybe_unserialize( $meta[$wpdb->get_blog_prefix() . 'capabilities'][0] ?? '' );
        $expected = $stored ? $stored[0] : ''; $highest = -1;
        if ( ! $stored && is_array( $caps ) ) { foreach ( $rules as $slug => $rule ) { if ( array_key_exists( $slug, $caps ) && is_bool( $caps[$slug] ) && (int) $rule['from'] > $highest ) { $highest = (int) $rule['from']; $expected = $slug; } } }
        return in_array( $expected, self::roles(), true ) && isset( $rules[$expected] ) ? $expected : null;
    }
    public static function economic_hold( array $meta, array $available, array $earned ) {
        return PHP_INT_SIZE < 8 || ! empty( $meta['_loyf_economic_hold'] ) || ! empty( $meta[YOWCL_Ledger_V2::CHECKPOINT_META] ) ||
            null === $available['value'] || null === $earned['value'] || ! YOWCL_Ledger_V2::integer_valid( $available['value'], 99999999 ) || ! YOWCL_Ledger_V2::integer_valid( $earned['value'] );
    }
    public static function customer( $id ) {
        $meta = self::meta( array( (int) $id ) )[$id];
        if ( ! self::member_valid( $meta ) ) { throw new RuntimeException( 'reader_customer_not_enrolled' ); }
         $available = self::state( $meta['user_points'] ?? array() ); $earned = self::state( $meta['user_earning_points'] ?? array() );
        return array( 'available'=>$available, 'earned'=>$earned, 'held'=>self::economic_hold( $meta, $available, $earned ), 'level'=>self::level( $id, $meta ), 'levels'=>self::option( 'loyalty_levels_rules', array() ) );
    }
    public static function outstanding() {
        global $wpdb;
        self::member_count();
        $where = self::population();
        // Validate and sum in one statement; never sum an arbitrary duplicate winner.
        $sql = "SELECT COUNT(*) members, SUM(IF(b.n=1 AND b.v REGEXP '^[0-9]{1,30}([.][0-9]{1,30})?$' AND b.v NOT REGEXP '[^0-9.]',0,1)) invalid, SUM(CAST(IF(b.n=1 AND b.v REGEXP '^[0-9]{1,30}([.][0-9]{1,30})?$' AND b.v NOT REGEXP '[^0-9.]',b.v,'0') AS DECIMAL(65,30))) total, SUM(IF(h.n IS NULL AND b.n=1 AND b.v REGEXP '^(0|[1-9][0-9]{0,7})$' AND b.v NOT REGEXP '[^0-9]' AND e.n=1 AND e.v REGEXP '^(0|[1-9][0-9]{0,17})$' AND e.v NOT REGEXP '[^0-9]',0,1)) held FROM {$wpdb->users} u LEFT JOIN (SELECT user_id,COUNT(*) n,MIN(meta_value) v FROM {$wpdb->usermeta} WHERE meta_key='user_points' GROUP BY user_id) b ON b.user_id=u.ID LEFT JOIN (SELECT user_id,COUNT(*) n,MIN(meta_value) v FROM {$wpdb->usermeta} WHERE meta_key='user_earning_points' GROUP BY user_id) e ON e.user_id=u.ID LEFT JOIN (SELECT user_id,COUNT(*) n FROM {$wpdb->usermeta} WHERE meta_key IN ('_loyf_economic_hold','_yowcl_ledger_v2_checkpoint') GROUP BY user_id) h ON h.user_id=u.ID WHERE $where";
        $row = self::query( $sql )[0];
        if ( (int) $row['members'] > self::MEMBER_LIMIT || (int) $row['invalid'] ) { throw new RuntimeException( 'reader_stock_unavailable' ); }
        $value = $row['total'] ?? '0';
        $value = strpos( $value, '.' ) === false ? $value : rtrim( rtrim( $value, '0' ), '.' );
        return array( 'value'=>$value, 'held'=>(int) $row['held'] );
    }
    public static function flows( $recent = false ) {
        if ( PHP_INT_SIZE < 8 ) { throw new RuntimeException( 'reader_integer_precision_unavailable' ); }
        $table = YOWCL_Points_Log::table_name();
        $fields = 'id,user_id,order_id,action,event_key,available_delta,earning_delta,ledger_version,source_event_key,allocation_receipt';
        $size = self::query( 'SELECT COUNT(*) n,COALESCE(SUM(COALESCE(OCTET_LENGTH(allocation_receipt),0)+COALESCE(OCTET_LENGTH(event_key),0)+COALESCE(OCTET_LENGTH(source_event_key),0)+COALESCE(OCTET_LENGTH(action),0)),0) bytes FROM (SELECT ' . $fields . ' FROM ' . $table . ' ORDER BY id LIMIT ' . ( self::LOG_LIMIT + 1 ) . ') evidence' )[0];
        if ( (int) $size['n'] > self::LOG_LIMIT || (int) $size['bytes'] > self::BYTE_LIMIT ) { throw new RuntimeException( 'reader_log_limit' ); }
        $rows = self::query( 'SELECT ' . $fields . ' FROM ' . $table . ' ORDER BY id LIMIT ' . ( self::LOG_LIMIT + 1 ) );
        if ( count( $rows ) !== (int) $size['n'] ) { throw new RuntimeException( 'reader_evidence_changed' ); }
        $by_key = array();
        foreach ( $rows as $row ) {
            if ( null !== $row['event_key'] ) { if ( isset( $by_key[$row['event_key']] ) ) { throw new RuntimeException( 'reader_duplicate_event' ); } $by_key[$row['event_key']] = $row; }
        }
        $now = current_datetime(); $end = (int) $now->format( 'U' ) * 1000000 + (int) $now->format( 'u' );
        $from = $now->sub( new DateInterval( 'P30D' ) );
        $start = $from->getTimestamp() * 1000000 + (int) $from->format( 'u' );
        $issued = 0; $redeemed = 0; $orders = array(); $legacy = 0; $unproven_debits = 0;
        $awards = array_merge( YOWCL_Points_Transaction::REWARD_ACTIONS, array( 'admin_reward' ) );
        foreach ( $rows as $row ) {
            $calls = 0;
            $proof = YOWCL_Ledger_V2::inspect( $row, static function ( $key ) use ( $by_key, &$calls ) { if ( ++$calls > 100 ) { throw new RuntimeException( 'reader_source_limit' ); } return $by_key[$key] ?? null; } );
            $advertised = false;
            foreach ( array( 'event_key','available_delta','earning_delta','ledger_version','source_event_key','allocation_receipt' ) as $field ) { $advertised = $advertised || null !== $row[$field]; }
            if ( ! $advertised ) { $legacy++; continue; }
            if ( ! in_array( $proof['kind'], array( 'v2','transaction_pre_v2' ), true ) ) { throw new RuntimeException( 'reader_proof_unavailable' ); }
            $award = in_array( $row['action'], $awards, true ) && $proof['available_delta'] > 0 && null === $row['source_event_key'];
            $claim = 'points_used' === $row['action'] && preg_match( '/^checkout_redeem:([a-f0-9-]{36})$/D', $row['event_key'], $match ) && YOWCL_Order_Redemption::valid_id( $match[1] ) && (int) $row['order_id'] > 0;
            $debit = $claim && $proof['available_delta'] < 0 && 0 === $proof['earning_delta'] && null === $row['source_event_key'];
            if ( $claim && ! $debit ) { throw new RuntimeException( 'reader_redemption_proof_unavailable' ); }
            if ( $debit && null !== $row['allocation_receipt'] ) {
                $receipt = YOWCL_Points_Allocation::decode( $row['allocation_receipt'] );
                if ( 'strict' !== $receipt['request']['mode'] || $receipt['request']['available'] !== $proof['available_delta'] || 0 !== $receipt['request']['earning'] ) { throw new RuntimeException( 'reader_redemption_proof_unavailable' ); }
            }
            if ( 'points_used' === $row['action'] && ! $debit ) { $unproven_debits++; }
            if ( ! $award && ! $debit ) { continue; }
            if ( $recent ) {
                if ( null === $row['allocation_receipt'] ) { throw new RuntimeException( 'reader_time_unavailable' ); }
                $receipt = YOWCL_Points_Allocation::decode( $row['allocation_receipt'] );
                if ( $receipt['admitted_at'] < $start || $receipt['admitted_at'] > $end ) { continue; }
            }
            if ( $award ) { $issued += $proof['available_delta']; }
            if ( $debit ) { $redeemed -= $proof['available_delta']; $orders[(string) $row['order_id']] = true; }
        }
        return array( 'issued'=>(string) $issued,'redeemed'=>(string) $redeemed,'orders'=>count( $orders ),'legacy'=>$legacy,'unproven_debits'=>$unproven_debits,'start'=>$start,'end'=>$end,'timezone'=>$now->getTimezone()->getName() );
    }
}
