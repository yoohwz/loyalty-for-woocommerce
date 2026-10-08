<?php
/** First-install navigation only. Existing settings and value producers remain authoritative. */
defined( 'ABSPATH' ) || exit;
class YOWCL_Free_Onboarding {
    const OPTION = 'loyf_onboarding_v1';
    private static $initial = false;
    private static $blocked = false;
    private static function read( $name ) {
        global $wpdb;
        $rows = $wpdb->get_col( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", $name ) );
        if ( $wpdb->last_error || count( $rows ) > 1 ) { throw new RuntimeException( 'onboarding_read_failed' ); }
        return $rows ? $rows[0] : null;
    }
    public static function state() {
        try { $s = maybe_unserialize( self::read( self::OPTION ) ); }
        catch ( Throwable $e ) { return array( 'status'=>'review' ); }
        return is_array( $s ) && 1 === ( $s['version'] ?? null ) && in_array( $s['status'] ?? '', array( 'proven','fresh','started','complete','dismissed','review' ), true ) ? $s : array( 'status'=>'review' );
    }
    private static function exists( $sql ) {
        global $wpdb;
        $value = $wpdb->get_var( $sql );
        if ( $wpdb->last_error ) { throw new RuntimeException( 'onboarding_assessment_failed' ); }
        return null !== $value;
    }
    /** A second bootstrap cannot attribute an unfinished first request's writes. */
    private static function retire_proof( $raw ) {
        global $wpdb;
        $s = maybe_unserialize( $raw );
        if ( ! is_array( $s ) || 'proven' !== ( $s['status'] ?? '' ) ) { return; }
        $review = serialize( array( 'version'=>1,'status'=>'review' ) );
        if ( false === $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND BINARY option_value=BINARY %s",$review,self::OPTION,$raw ) ) ) { self::$blocked = true; }
        self::invalidate();
    }
    /** Called before cutover, schema, migrations, version and customization defaults. */
    public static function assess() {
        global $wpdb;
        try {
            $existing = self::read( self::OPTION );
            if ( null !== $existing ) { self::retire_proof( $existing ); return ! self::$blocked; }
            // A running installation with erased/incomplete witnesses is not a first activation.
            $active = maybe_unserialize( self::read( 'active_plugins' ) );
            if ( ! is_array( $active ) || is_multisite() || in_array( YOSWC_LOYALTY_PLUGIN_BASENAME, $active, true ) ) { throw new RuntimeException( 'onboarding_prior_installation' ); }
            $prefixes = array( 'loyalty_', 'loyf_', 'yoswc_loyalty', 'yowcl_', 'yol_', 'wc_loyalty', 'woocommerce_yowcl_loyalty_', 'yoswc_role_owner_' );
            $where = array(); foreach ( $prefixes as $p ) { $where[] = $wpdb->prepare( 'option_name LIKE %s', $wpdb->esc_like( $p ) . '%' ); }
            if ( self::exists( "SELECT 1 FROM {$wpdb->options} WHERE " . implode( ' OR ', $where ) . ' LIMIT 1' ) ) { throw new RuntimeException( 'onboarding_prior_options' ); }
            // Even an empty legacy schema is evidence of an earlier bootstrap.
            if ( self::exists( $wpdb->prepare( 'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $wpdb->prefix . 'yo_loyalty_points_log' ) ) ) { throw new RuntimeException( 'onboarding_prior_schema' ); }
            $meta = array( 'user_points','user_earning_points','first_purchase_rewarded','_points_awarded','_points_deducted','_used_points','_used_points_discount','_yoswc_role_claims' );
            foreach ( array( $wpdb->usermeta, $wpdb->postmeta, $wpdb->commentmeta ) as $table ) {
                $where = array(); foreach ( $meta as $m ) { $where[] = $wpdb->prepare( 'meta_key=%s', $m ); }
                foreach ( array( 'loyalty_','_loyalty_','yoswc_loyalty','_yoswc_','yo_loyalty_','yowcl_','yol_','_yol_','_yo_','_yowcl_','loyf_','_loyf_' ) as $p ) { $where[] = $wpdb->prepare( 'meta_key LIKE %s', $wpdb->esc_like( $p ) . '%' ); }
                if ( self::exists( "SELECT 1 FROM {$table} WHERE " . implode( ' OR ', $where ) . ' LIMIT 1' ) ) { throw new RuntimeException( 'onboarding_prior_markers' ); }
            }
            foreach ( array( 'wc_orders_meta'=>'meta_key', 'actionscheduler_actions'=>'hook' ) as $suffix=>$column ) {
                $table = $wpdb->prefix . $suffix;
                if ( ! self::exists( $wpdb->prepare( 'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) ) ) { continue; }
                $where = array(); foreach ( array( 'loyalty_','_loyalty_','yoswc_loyalty','_yoswc_','yo_loyalty_','yowcl_','yol_','_yol_','_yo_','_yowcl_','loyf_','_loyf_' ) as $p ) { $where[] = $wpdb->prepare( "$column LIKE %s", $wpdb->esc_like( $p ) . '%' ); }
                foreach ( array( '_points_awarded','_points_deducted','_used_points','_used_points_discount' ) as $m ) { $where[] = $wpdb->prepare( "$column=%s", $m ); }
                if ( self::exists( "SELECT 1 FROM {$table} WHERE " . implode( ' OR ', $where ) . ' LIMIT 1' ) ) { throw new RuntimeException( 'onboarding_prior_orders' ); }
            }
            $groups = $wpdb->prefix . 'actionscheduler_groups';
            if ( self::exists( $wpdb->prepare( 'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$groups ) ) && self::exists( $wpdb->prepare( "SELECT 1 FROM {$groups} WHERE slug LIKE %s OR slug LIKE %s LIMIT 1",'yowcl-%','loyf-%' ) ) ) { throw new RuntimeException( 'onboarding_prior_schedules' ); }
            $cron_raw = self::read( 'cron' ); $cron = null === $cron_raw ? array() : @unserialize( $cron_raw,array( 'allowed_classes'=>false ) );
            if ( ! is_array( $cron ) || count( $cron ) > 10000 ) { throw new RuntimeException( 'onboarding_schedule_coverage_uncertain' ); }
            foreach ( $cron as $time=>$hooks ) {
                if ( 'version' === $time ) { continue; }
                if ( ! is_array( $hooks ) ) { throw new RuntimeException( 'onboarding_schedule_coverage_uncertain' ); }
                foreach ( array_keys( $hooks ) as $hook ) { if ( ! is_string( $hook ) || preg_match( '/^(?:yowcl_|yoswc_loyalty|loyf_|yol_|yo_loyalty_|loyalty_)/',$hook ) ) { throw new RuntimeException( 'onboarding_prior_schedules' ); } }
            }
            // Unattributed custom roles cannot prove a clean Loyalty history.
            $roles = maybe_unserialize( self::read( $wpdb->prefix . 'user_roles' ) );
            if ( ! is_array( $roles ) || array_diff( array_keys( $roles ), array( 'administrator','editor','author','contributor','subscriber','customer','shop_manager' ) ) ) { throw new RuntimeException( 'onboarding_roles_uncertain' ); }
            $caps = $wpdb->get_results( $wpdb->prepare( "SELECT user_id,meta_value FROM {$wpdb->usermeta} WHERE meta_key=%s LIMIT 10001",$wpdb->prefix . 'capabilities' ),ARRAY_A );
            if ( $wpdb->last_error || ! is_array( $caps ) || count( $caps ) > 10000 ) { throw new RuntimeException( 'onboarding_role_coverage_uncertain' ); }
            $seen = array();
            foreach ( $caps as $row ) {
                $value = @unserialize( $row['meta_value'],array( 'allowed_classes'=>false ) );
                if ( isset( $seen[$row['user_id']] ) || ! is_array( $value ) || array_diff( array_keys( $value ),array_keys( $roles ) ) ) { throw new RuntimeException( 'onboarding_role_assignment_uncertain' ); }
                foreach ( $value as $enabled ) { if ( ! is_bool( $enabled ) ) { throw new RuntimeException( 'onboarding_role_assignment_uncertain' ); } }
                $seen[$row['user_id']] = true;
            }
            $s = array( 'version'=>1, 'status'=>'proven', 'id'=>wp_generate_uuid4() );
            $raw = serialize( $s );
            if ( add_option( self::OPTION, $s, '', false ) && self::read( self::OPTION ) === $raw ) { self::$initial = $raw; }
            else { self::retire_proof( self::read( self::OPTION ) ); }
        } catch ( Throwable $e ) {
            if ( in_array( $e->getMessage(),array( 'onboarding_read_failed','onboarding_assessment_failed' ),true ) ) { self::$blocked = true; }
            add_option( self::OPTION, array( 'version'=>1,'status'=>'review' ), '', false );
        }
        return ! self::$blocked;
    }
    public static function names() {
        return array( 'loyalty_levels_roles','loyalty_levels_rules','loyalty_points_earning_rules','loyalty_points_earning_option','loyalty_points_rounding','loyalty_points_earning_status','loyalty_points_deduction_status','loyalty_points_using_point','loyalty_points_using_rules','loyalty_customization_loyalty_bubble','loyalty_customization_my_account','loyalty_extra_purchase_points_rules','loyf_first_purchase_epoch_v1','loyf_referral_lite_v1' );
    }
    private static function program_snapshot( $lock = false ) {
        global $wpdb;
        $where = array();
        foreach ( array( 'loyalty_','loyf_','yoswc_loyalty','yowcl_','yol_','wc_loyalty','woocommerce_yowcl_loyalty_','yoswc_role_owner_' ) as $prefix ) { $where[] = $wpdb->prepare( 'option_name LIKE %s',$wpdb->esc_like( $prefix ) . '%' ); }
        $rows = $wpdb->get_results( "SELECT option_name,option_value FROM {$wpdb->options} WHERE (" . implode( ' OR ',$where ) . ') ORDER BY option_name' . ( $lock ? ' FOR UPDATE' : '' ),ARRAY_A );
        if ( $wpdb->last_error || ! is_array( $rows ) ) { throw new RuntimeException( 'onboarding_program_unavailable' ); }
        $values = array();
        foreach ( $rows as $row ) { if ( in_array( $row['option_name'],array( self::OPTION,'yoswc_loyalty_subscription_pushed' ),true ) ) { continue; } if ( isset( $values[$row['option_name']] ) ) { throw new RuntimeException( 'onboarding_program_ambiguous' ); } $values[$row['option_name']] = $row['option_value']; }
        return $values;
    }
    private static function unchanged( $s,$lock=true ) {
        $actual = self::program_snapshot( $lock ); $before = $s['program'];
        $seeds = $s['seed']; $seeds['yoswc_loyalty_version'] = YOSWC_LOYALTY_VERSION;
        foreach ( $seeds as $name=>$raw ) { if ( ! array_key_exists( $name,$before ) && isset( $actual[$name] ) && $actual[$name] === $raw ) { $before[$name] = $raw; } }
        ksort( $before );
        if ( $actual !== $before ) { throw new DomainException( 'onboarding_settings_changed' ); }
    }
    private static function snapshot() {
        $values = array(); foreach ( self::names() as $n ) { $values[$n] = self::read( $n ); } return $values;
    }
    /** Only the request that proved freshness may freeze the self-seeded baseline. */
    public static function baseline() {
        if ( ! self::$initial ) { return; }
        try {
            global $wpdb;
            if ( self::read( self::OPTION ) !== self::$initial ) { return; }
            $s = maybe_unserialize( self::$initial );
            foreach ( array( 'signup','login','review','levelup','redemption','email_reward','email_deduct','email_level' ) as $feature ) { if ( ! YOWCL_Free_Migrations::ready( $feature ) ) { return; } }
            $s['before'] = self::snapshot();
            $s['cutover'] = self::read( YOWCL_Free_Core::CUTOVER );
            $s['program'] = self::program_snapshot();
            // Existing admin_init seeding may occur after an activation sandbox or WP-CLI boot.
            $s['seed'] = array_map( 'serialize',YOSWC_Loyalty_Settings_Customization::default_message_settings() );
            $s['status'] = 'fresh';
            YOWCL_Core_Rewards::checkpoint( 'onboarding_baseline_snapshot',$s['id'] );
            // A competing bootstrap may retire the proof while the snapshot is read.
            $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s AND BINARY option_value=BINARY %s",serialize( $s ),self::OPTION,self::$initial ) );
            self::invalidate();
        } catch ( Throwable $e ) { /* A missing baseline always renders review-only. */ }
    }
    public static function writable( $s = null ) {
        $s = $s ?? self::state();
        if ( 'fresh' !== $s['status'] || ! is_string( $s['id'] ?? null ) || ! preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$s['id'] ) || ! is_array( $s['before'] ?? null ) || array_keys( $s['before'] ) !== self::names() || ! is_array( $s['seed'] ?? null ) || ! is_string( $s['cutover'] ?? null ) || ! is_array( $s['program'] ?? null ) ) { return false; }
        foreach ( $s['program'] as $name=>$raw ) { if ( ! is_string( $name ) || ! is_string( $raw ) ) { return false; } }
        foreach ( $s['before'] as $raw ) { if ( null !== $raw && ! is_string( $raw ) ) { return false; } }
        $cutover = maybe_unserialize( $s['cutover'] );
        $valid = is_array( $cutover ) && array_keys( $cutover ) === array( 'version','users','comments' ) && 1 === $cutover['version'] && is_int( $cutover['users'] ) && $cutover['users'] >= 0 && is_int( $cutover['comments'] ) && $cutover['comments'] >= 0 && class_exists( 'YOSWC_Loyalty_Settings_Customization',false ) && $s['seed'] === array_map( 'serialize',YOSWC_Loyalty_Settings_Customization::default_message_settings() );
        if ( ! $valid ) { return false; }
        try {
            global $wpdb; self::unchanged( $s,false );
            return ! self::exists( "SELECT 1 FROM {$wpdb->usermeta} WHERE meta_key IN ('user_points','user_earning_points','first_purchase_rewarded') LIMIT 1" ) && ! self::exists( "SELECT 1 FROM {$wpdb->prefix}yo_loyalty_points_log LIMIT 1" );
        } catch ( Throwable $e ) { return false; }
    }
    private static function invalidate() {
        foreach ( array_merge( self::names(), array( self::OPTION,'alloptions','notoptions' ) ) as $n ) { wp_cache_delete( $n, 'options' ); }
    }
    private static function put( $db, $name, $value ) {
        global $wpdb;
        $raw = maybe_serialize( $value );
        YOWCL_Points_Lock::query( $db, $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'no') ON DUPLICATE KEY UPDATE option_value=VALUES(option_value)", $name, $raw ) );
        if ( YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", $name ) ) !== $raw ) { throw new RuntimeException( 'onboarding_write_failed' ); }
    }
    private static function scalar( $input, $key ) {
        if ( ! isset( $input[$key] ) || ! is_string( $input[$key] ) ) { throw new DomainException( 'onboarding_invalid_input' ); } return $input[$key];
    }
    private static function amount( $value ) {
        $dp = wc_get_price_decimals();
        if ( $dp < 0 || $dp > 8 || ! preg_match( '/^[0-9]{1,8}(?:\.[0-9]{1,8})?$/D', $value ) || ( false !== strpos( $value, '.' ) && strlen( substr( strrchr( $value, '.' ), 1 ) ) > $dp ) || (float) $value <= 0 || ! is_finite( (float) $value ) ) { throw new DomainException( 'onboarding_amount_invalid' ); }
        return wc_format_decimal( $value, $dp );
    }
    public static function terms( $input ) {
        $p = YOWCL_Free_First_Purchase::points( self::scalar( $input,'earn_points' ) );
        if ( $p < 1 || 'customer' !== self::scalar( $input,'role' ) || ! get_role( 'customer' ) ) { throw new DomainException( 'onboarding_program_invalid' ); }
        $round = self::scalar( $input,'rounding' ); if ( ! in_array( $round,array( 'round_down','round_up' ),true ) ) { throw new DomainException( 'onboarding_rounding_invalid' ); }
        if ( self::scalar( $input,'currency' ) !== get_woocommerce_currency() ) { throw new DomainException( 'onboarding_currency_changed' ); }
        $t = array( 'points'=>$p,'amount'=>self::amount( self::scalar( $input,'earn_amount' ) ),'rounding'=>$round,'currency'=>get_woocommerce_currency() );
        $statuses = wc_get_order_statuses();
        foreach ( array( 'earn_status'=>array( 'wc-processing','wc-completed' ), 'deduct_status'=>array( 'wc-failed','wc-cancelled','wc-refunded' ) ) as $key=>$allowed ) {
            $values = $input[$key] ?? array();
            if ( ! is_array( $values ) || count( array_filter( $values,'is_string' ) ) !== count( $values ) ) { throw new DomainException( 'onboarding_status_invalid' ); }
            if ( ! is_array( $values ) || ! $values || array_diff( $values,$allowed ) || array_diff( $values,array_keys( $statuses ) ) || count( $values ) !== count( array_unique( $values ) ) ) { throw new DomainException( 'onboarding_status_invalid' ); } $t[$key] = array_values( $values );
        }
        $t['options'] = array(); foreach ( array( 'coupons','taxes' ) as $key ) { if ( isset( $input[$key] ) ) { if ( 'yes' !== $input[$key] ) { throw new DomainException( 'onboarding_option_invalid' ); } $t['options'][] = $key; } }
        foreach ( array( 'redeem','first','referral','bubble','account' ) as $key ) { $t[$key] = isset( $input[$key] ); if ( $t[$key] && 'yes' !== $input[$key] ) { throw new DomainException( 'onboarding_flag_invalid' ); } }
        if ( $t['redeem'] ) {
            $t['redeem_points'] = YOWCL_Free_First_Purchase::points( self::scalar( $input,'redeem_points' ) );
            if ( $t['redeem_points'] < 1 ) { throw new DomainException( 'onboarding_redemption_invalid' ); } $t['redeem_amount'] = self::amount( self::scalar( $input,'redeem_amount' ) );
        }
        foreach ( array( 'first','referral' ) as $key ) { if ( $t[$key] ) { $t[$key . '_points'] = YOWCL_Free_First_Purchase::points( self::scalar( $input,$key . '_points' ) ); if ( $t[$key . '_points'] < 1 ) { throw new DomainException( 'onboarding_bonus_invalid' ); } } }
        return $t;
    }
    /** Consume the sole Launch before any business writes. An interrupted Launch is review-only. */
    public static function launch( $input ) {
        if ( ! current_user_can( 'manage_options' ) || ! YOWCL_Free_Core::owns() ) { throw new DomainException( 'onboarding_save_denied' ); }
        $previous = self::state();
        if ( 'complete' === $previous['status'] && is_string( $input['attempt'] ?? null ) && ( $previous['id'] ?? null ) === $input['attempt'] ) { return; }
        if ( ! class_exists( 'YOWCL_Free_Migrations',false ) ) { throw new DomainException( 'onboarding_review_required' ); }
        foreach ( array( 'signup','login','review','levelup','redemption','email_reward','email_deduct','email_level' ) as $f ) { if ( ! YOWCL_Free_Migrations::ready( $f ) ) { throw new DomainException( 'onboarding_migration_incomplete' ); } }
        global $wpdb;
        if ( self::exists( "SELECT 1 FROM {$wpdb->usermeta} WHERE meta_key IN ('user_points','user_earning_points','first_purchase_rewarded') LIMIT 1" ) || self::exists( "SELECT 1 FROM {$wpdb->prefix}yo_loyalty_points_log LIMIT 1" ) ) { throw new DomainException( 'onboarding_program_already_used' ); }
        $t = self::terms( $input ); $id = self::scalar( $input,'attempt' );
        return YOWCL_Free_Migrations::locked( static function () use ( $t,$id ) {
            global $wpdb;
            $original = $wpdb; $original->flush();
            $guard = ( new ReflectionClass( 'YOWCL_Reward_Transaction_WPDB' ) )->newInstanceWithoutConstructor();
            foreach ( ( new ReflectionObject( $original ) )->getProperties() as $property ) { if ( ! $property->isStatic() ) { $property->setAccessible( true ); $property->setValue( $guard,$property->getValue( $original ) ); } }
            $wpdb = $guard;
            try { return self::persist( $t,$id ); }
            finally { try { $guard->flush(); } finally { $wpdb = $original; } }
        } );
    }
    private static function persist( $t,$id ) {
            global $wpdb; $db = $wpdb->dbh; $started = false;
            $s = self::state();
            if ( 'complete' === $s['status'] && ( $s['id'] ?? '' ) === $id ) { return; }
            if ( ! self::writable( $s ) || $s['id'] !== $id || self::read( YOWCL_Free_Core::CUTOVER ) !== $s['cutover'] ) { throw new DomainException( 'onboarding_review_required' ); }
            if ( ! ( $db instanceof mysqli ) || YOWCL_Points_Lock::has_transaction( $db ) || 'InnoDB' !== YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $wpdb->options ) ) ) { throw new RuntimeException( 'onboarding_storage_required' ); }
            try {
                YOWCL_Points_Lock::query( $db,'START TRANSACTION' ); $started = true;
                self::unchanged( $s );
                foreach ( self::names() as $n ) {
                    $raw = YOWCL_Points_Lock::scalar( $db,$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s FOR UPDATE",$n ) );
                    if ( $raw !== $s['before'][$n] && ! ( null === $s['before'][$n] && isset( $s['seed'][$n] ) && $raw === $s['seed'][$n] ) ) { throw new DomainException( 'onboarding_settings_changed' ); }
                }
                $s['status'] = 'started'; $s['terms'] = $t; self::put( $db,self::OPTION,$s );
                // This durable consumption precedes business commits, including unknown outcomes.
                YOWCL_Points_Lock::query( $db,'COMMIT' ); $started = false;
                self::invalidate(); YOWCL_Core_Rewards::checkpoint( 'onboarding_started',$id );
                YOWCL_Points_Lock::query( $db,'START TRANSACTION' ); $started = true;
                // Recheck rows: native Woo settings edits do not acquire the options mutex.
                self::unchanged( $s );
                foreach ( self::names() as $n ) {
                    $raw = YOWCL_Points_Lock::scalar( $db,$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s FOR UPDATE",$n ) );
                    if ( $raw !== $s['before'][$n] && ! ( null === $s['before'][$n] && isset( $s['seed'][$n] ) && $raw === $s['seed'][$n] ) ) { throw new DomainException( 'onboarding_settings_changed' ); }
                }
                $writes = array(
                    'loyalty_levels_roles'=>array( 'customer' ),'loyalty_levels_rules'=>array( 'customer'=>array( 'from'=>'0' ) ),
                    'loyalty_points_earning_rules'=>array( 'customer'=>array( 'points'=>(string) $t['points'],'amount'=>$t['amount'] ) ),
                    'loyalty_points_rounding'=>$t['rounding'],'loyalty_points_earning_option'=>$t['options'],
                    'loyalty_points_earning_status'=>$t['earn_status'],'loyalty_points_deduction_status'=>$t['deduct_status'],
                );
                if ( $t['redeem'] ) {
                    $rules = maybe_unserialize( maybe_unserialize( self::read( 'loyalty_points_using_rules' ) ) );
                    if ( ! is_array( $rules ) ) { throw new RuntimeException( 'onboarding_redemption_unavailable' ); }
                    $writes['loyalty_points_using_rules'] = array_replace( $rules,array( 'points'=>$t['redeem_points'],'amount'=>$t['redeem_amount'] ) ); $writes['loyalty_points_using_point'] = 'yes';
                }
                foreach ( array( 'bubble'=>'loyalty_customization_loyalty_bubble','account'=>'loyalty_customization_my_account' ) as $k=>$n ) {
                    $current = maybe_unserialize( self::read( $n ) );
                    if ( null === $current ) { $current = maybe_unserialize( $s['seed'][$n] ); }
                    if ( ! is_array( $current ) ) { throw new RuntimeException( 'onboarding_display_unavailable' ); }
                    $current['bubble' === $k ? 'enabled' : 'my_account'] = $t[$k]; $writes[$n] = $current;
                }
                foreach ( $writes as $n=>$value ) { self::put( $db,$n,$value ); YOWCL_Core_Rewards::checkpoint( 'onboarding_settings_write',$n ); }
                if ( $wpdb->dbh !== $db ) { throw new RuntimeException( 'onboarding_connection_lost' ); }
                YOWCL_Points_Lock::query( $db,'COMMIT' ); $started = false; self::invalidate(); YOWCL_Core_Rewards::checkpoint( 'onboarding_baseline_committed',$id );
                if ( $t['account'] ) { add_rewrite_endpoint( 'my-points',EP_ROOT | EP_PAGES ); flush_rewrite_rules( false ); }
                // Native services retain their own transaction/epoch/ownership semantics.
                // The borrowed native WPDB guard forbids reconnect/replay after lock loss.
                if ( $t['first'] ) { YOWCL_Free_First_Purchase::save( true,(string) $t['first_points'] ); }
                if ( $t['referral'] ) { YOWCL_Core_Rewards::checkpoint( 'onboarding_referral_save',$id ); YOWCL_Free_Referral::save( true,(string) $t['referral_points'] ); YOWCL_Core_Rewards::checkpoint( 'onboarding_referral_saved',$id ); }
                foreach ( $writes as $n=>$value ) { if ( self::read( $n ) !== maybe_serialize( $value ) ) { throw new RuntimeException( 'onboarding_settings_changed' ); } }
                if ( $t['first'] ) { $c = YOWCL_Free_First_Purchase::configuration(); if ( ! $c['effective'] || ! $c['ready'] || $c['points'] !== $t['first_points'] ) { throw new RuntimeException( 'onboarding_first_unconfirmed' ); } }
                if ( $t['referral'] ) { $raw = maybe_unserialize( self::read( YOWCL_Free_Referral::OPTION ) ); if ( $raw !== array( 'enabled'=>'yes','points'=>$t['referral_points'] ) ) { throw new RuntimeException( 'onboarding_referral_unconfirmed' ); } }
                YOWCL_Core_Rewards::checkpoint( 'onboarding_before_complete',$id );
                $s['status'] = 'complete'; self::put( $db,self::OPTION,$s );
            } finally {
                if ( $started ) { try { YOWCL_Points_Lock::query( $db,'ROLLBACK' ); } catch ( Throwable $ignored ) {} }
                self::invalidate();
            }
    }
    public static function boot() {
        add_action( 'admin_menu',static function () { add_submenu_page( 'woocommerce',__( 'Review your loyalty setup','loyalty-for-woocommerce' ),__( 'Loyalty setup','loyalty-for-woocommerce' ),'manage_woocommerce','loyf-setup',array( __CLASS__,'render' ) ); } );
        add_action( 'admin_post_loyf_onboarding',array( __CLASS__,'post' ) );
        add_action( 'admin_notices',static function () {
            if ( current_user_can( 'manage_options' ) && self::writable() ) { echo '<div class="notice notice-info"><p>' . esc_html__( 'Set up your loyalty program when you are ready. Your choices are saved only when you select Launch.','loyalty-for-woocommerce' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=loyf-setup' ) ) . '">' . esc_html__( 'Quick Start','loyalty-for-woocommerce' ) . '</a></p></div>'; }
        } );
    }
    public static function post() {
        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'manage_options' ) || ! YOWCL_Free_Core::owns() || ! is_string( $_POST['_wpnonce'] ?? null ) || ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ),'loyf_onboarding' ) ) { wp_die( 'onboarding_save_denied', '',array( 'response'=>403 ) ); }
        try {
            $input = wp_unslash( $_POST );
            if ( 'dismiss' === ( $input['intent'] ?? '' ) ) {
                YOWCL_Free_Migrations::locked( static function () { $s = self::state(); if ( self::writable( $s ) ) { $s['status'] = 'dismissed'; update_option( self::OPTION,$s,false ); if ( self::read( self::OPTION ) !== serialize( $s ) ) { throw new RuntimeException( 'onboarding_exit_unconfirmed' ); } } } );
            } elseif ( 'launch' === ( $input['intent'] ?? '' ) ) { self::launch( $input ); }
            else { throw new DomainException( 'onboarding_invalid_action' ); }
            wp_safe_redirect( admin_url( 'admin.php?page=loyf-setup' ) ); exit;
        } catch ( Throwable $e ) { wp_die( esc_html( $e->getMessage() ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=loyf-setup' ) ) . '">Review your loyalty setup</a>', '',array( 'response'=>409 ) ); }
    }
    public static function render() {
        if ( ! current_user_can( 'manage_woocommerce' ) || ! YOWCL_Free_Core::owns() ) { wp_die( 'onboarding_view_denied' ); }
        $s = self::state(); $fresh = current_user_can( 'manage_options' ) && self::writable( $s );
        echo '<div class="wrap"><h1>' . esc_html__( 'Review your loyalty setup','loyalty-for-woocommerce' ) . '</h1>';
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=loyalty' ) ) . '">' . esc_html__( 'Loyalty settings','loyalty-for-woocommerce' ) . '</a></p>';
        if ( ! $fresh ) {
            $text = 'complete' === $s['status'] ? __( 'Quick Start completed. Current settings below may include later edits.','loyalty-for-woocommerce' ) : __( 'Review only. Use the existing Loyalty settings screens to change your program. An interrupted Launch may have saved some of your selected settings; it will not run again.','loyalty-for-woocommerce' );
            echo '<p>' . esc_html( $text ) . '</p>';
            if ( 'complete' === $s['status'] && is_array( $s['terms'] ?? null ) ) {
                $t = $s['terms'];
                if ( is_numeric( $t['amount'] ?? null ) && (float) $t['amount'] > 0 && is_numeric( $t['points'] ?? null ) ) {
                    $raw = 100 / (float) $t['amount'] * (float) $t['points']; $earned = 'round_up' === ( $t['rounding'] ?? '' ) ? ceil( $raw ) : floor( $raw );
                    if ( is_finite( $earned ) && $earned > 0 && $earned <= 99999999 ) { echo '<p>' . esc_html( sprintf( __( 'Illustrative Launch example, subtotal 100 %1$s without taxes or coupons: %2$s points. This is a saved-choice example, not a reward promise.','loyalty-for-woocommerce' ),$t['currency'],(string) $earned ) ) . '</p>'; }
                }
                if ( ! empty( $t['redeem'] ) && is_scalar( $t['redeem_points'] ?? null ) && is_scalar( $t['redeem_amount'] ?? null ) ) { echo '<p>' . esc_html( sprintf( __( 'Illustrative Launch redemption: %1$s points for %2$s %3$s, subject to the cart subtotal.','loyalty-for-woocommerce' ),$t['redeem_points'],$t['redeem_amount'],$t['currency'] ) ) . '</p>'; }
            }
            echo '<table class="widefat"><thead><tr><th>' . esc_html__( 'Setting','loyalty-for-woocommerce' ) . '</th><th>' . esc_html__( 'Current saved value','loyalty-for-woocommerce' ) . '</th></tr></thead><tbody>';
            foreach ( self::names() as $n ) { try { $raw = self::read( $n ); $text = null === $raw ? __( 'Not configured','loyalty-for-woocommerce' ) : wp_json_encode( maybe_unserialize( maybe_unserialize( $raw ) ) ); } catch ( Throwable $e ) { $text = __( 'Unavailable. Review storage before saving.','loyalty-for-woocommerce' ); } echo '<tr><th scope="row">' . esc_html( $n ) . '</th><td>' . esc_html( $text ) . '</td></tr>'; }
            echo '</tbody></table></div>'; return;
        }
        echo '<p>' . esc_html__( 'Preview only until Launch. Suggestions are illustrative; no points or orders are created.','loyalty-for-woocommerce' ) . '</p><form id="loyf-quick-start" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        wp_nonce_field( 'loyf_onboarding' );
        echo '<input type="hidden" name="action" value="loyf_onboarding"><input type="hidden" name="attempt" value="' . esc_attr( $s['id'] ) . '"><input type="hidden" name="currency" value="' . esc_attr( get_woocommerce_currency() ) . '">';
        $headings = array( __( 'Program','loyalty-for-woocommerce' ),__( 'Earn points','loyalty-for-woocommerce' ),__( 'Redeem points','loyalty-for-woocommerce' ),__( 'Optional growth','loyalty-for-woocommerce' ),__( 'Customer display','loyalty-for-woocommerce' ),__( 'Launch summary','loyalty-for-woocommerce' ) );
        foreach ( $headings as $i=>$heading ) {
            echo '<section data-loyf-step="' . esc_attr( $i ) . '"><h2 tabindex="-1">' . esc_html( ( $i + 1 ) . '. ' . $heading ) . '</h2>';
            if ( 0 === $i ) { echo '<p>' . esc_html__( 'Start with the existing Customer level at zero points. Only customers qualify; no customer roles are reassigned. Additional levels remain available in Loyalty settings.','loyalty-for-woocommerce' ) . '</p><input type="hidden" name="role" value="customer">'; }
            if ( 1 === $i ) {
                self::number( 'earn_points',__( 'Points earned','loyalty-for-woocommerce' ),'1' ); self::number( 'earn_amount',sprintf( __( 'Per subtotal amount (%s)','loyalty-for-woocommerce' ),get_woocommerce_currency() ),'1',true );
                echo '<p><label for="loyf-rounding">' . esc_html__( 'Rounding','loyalty-for-woocommerce' ) . '</label> <select name="rounding" id="loyf-rounding"><option value="round_down">' . esc_html__( 'Round down','loyalty-for-woocommerce' ) . '</option><option value="round_up">' . esc_html__( 'Round up','loyalty-for-woocommerce' ) . '</option></select></p>';
                foreach ( array( 'wc-processing','wc-completed' ) as $status ) { self::check( 'earn_status[]',$status, wc_get_order_status_name( $status ),true ); }
                self::check( 'coupons','yes',__( 'Use price after coupons','loyalty-for-woocommerce' ) ); self::check( 'taxes','yes',__( 'Subtract order taxes (existing Free calculation)','loyalty-for-woocommerce' ) );
                echo '<p>' . esc_html__( 'Reverse purchase points on the selected terminal statuses:','loyalty-for-woocommerce' ) . '</p>';
                foreach ( array( 'wc-failed','wc-cancelled','wc-refunded' ) as $status ) { self::check( 'deduct_status[]',$status,wc_get_order_status_name( $status ),true ); }
            }
            if ( 2 === $i ) { self::check( 'redeem','yes',__( 'Enable points redemption','loyalty-for-woocommerce' ) ); self::number( 'redeem_points',__( 'Points exchanged','loyalty-for-woocommerce' ),'100' ); self::number( 'redeem_amount',sprintf( __( 'Discount amount (%s)','loyalty-for-woocommerce' ),get_woocommerce_currency() ),'1',true ); }
            if ( 3 === $i ) { self::check( 'first','yes',__( 'Enable First Purchase bonus (orders created after Launch only)','loyalty-for-woocommerce' ) ); self::number( 'first_points',__( 'First Purchase points','loyalty-for-woocommerce' ),'0' ); self::check( 'referral','yes',__( 'Enable Referral Lite (registered customer, first order, link referrer only)','loyalty-for-woocommerce' ) ); self::number( 'referral_points',__( 'Referrer points','loyalty-for-woocommerce' ),'0' ); }
            if ( 4 === $i ) { self::check( 'bubble','yes',__( 'Show Loyalty bubble','loyalty-for-woocommerce' ),true ); self::check( 'account','yes',__( 'Show My Points in My Account','loyalty-for-woocommerce' ),true ); }
            if ( 5 === $i ) { echo '<p>' . esc_html__( 'Launch saves only your selected Free settings. If saving is interrupted, review the current settings before using the existing settings screens; Quick Start will not overwrite them on retry.','loyalty-for-woocommerce' ) . '</p><div data-loyf-summary role="status" aria-live="polite"></div>'; }
            echo '</section>';
        }
        echo '<p><button type="button" class="button" data-loyf-back hidden>' . esc_html__( 'Back','loyalty-for-woocommerce' ) . '</button> <button type="button" class="button button-primary" data-loyf-next hidden>' . esc_html__( 'Next','loyalty-for-woocommerce' ) . '</button> <button type="button" class="button" data-loyf-skip hidden>' . esc_html__( 'Skip optional growth','loyalty-for-woocommerce' ) . '</button> <button type="submit" name="intent" value="launch" class="button button-primary" data-loyf-launch>' . esc_html__( 'Launch / Save','loyalty-for-woocommerce' ) . '</button> <button type="submit" name="intent" value="dismiss" class="button" formnovalidate>' . esc_html__( 'Exit Quick Start','loyalty-for-woocommerce' ) . '</button></p></form></div>';
        wp_enqueue_script( 'loyf-onboarding',plugins_url( '../../../js/onboarding.js',__FILE__ ),array(),hash_file( 'sha256',dirname( __DIR__,3 ) . '/js/onboarding.js' ),true );
        wp_localize_script( 'loyf-onboarding','loyfOnboarding',array( 'example'=>__( 'Illustrative order subtotal of %1$s: %2$s points.','loyalty-for-woocommerce' ),'redeem'=>__( 'Illustrative redemption of %1$s points: %2$s discount, subject to the cart subtotal.','loyalty-for-woocommerce' ),'off'=>__( 'Optional rewards and redemption remain off unless selected.','loyalty-for-woocommerce' ),'invalid'=>__( 'Review the conversion amounts before Launch.','loyalty-for-woocommerce' ) ) );
    }
    private static function check( $name,$value,$label,$checked=false ) { echo '<p><label><input type="checkbox" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . ( $checked ? ' checked' : '' ) . '> ' . esc_html( $label ) . '</label></p>'; }
    private static function number( $name,$label,$value,$amount=false ) { echo '<p><label for="loyf-' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label> <input id="loyf-' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" type="number" min="0" max="99999999" step="' . esc_attr( $amount ? pow( 10,-wc_get_price_decimals() ) : 1 ) . '" value="' . esc_attr( $value ) . '"></p>'; }
}
