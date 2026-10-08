<?php
/** One-time Free semantic conversions. No balance, history or role ownership writes. */
defined( 'ABSPATH' ) || exit;
class YOWCL_Free_Migrations {
    private static $errors = array();
    private static $owner = null;
    public static function witness( $feature ) { return 'loyf_migration_' . $feature . '_v1'; }
    public static function read( $name ) {
        global $wpdb;
        self::assert_owner();
        $rows = $wpdb->get_col( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
        self::assert_owner();
        if ( $wpdb->last_error || ! is_array( $rows ) || count( $rows ) > 1 ) { throw new RuntimeException( 'migration_storage_unavailable' ); }
        return $rows ? $rows[0] : null;
    }
    public static function ready( $feature ) {
        try { return '1' === self::read( self::witness( $feature ) ); } catch ( Throwable $e ) { return false; }
    }
    private static function decode( $raw ) {
        if ( null === $raw ) { return array(); }
        $value = maybe_unserialize( maybe_unserialize( $raw ) );
        if ( ! is_array( $value ) ) { throw new RuntimeException( 'migration_malformed_option' ); }
        return $value;
    }
    /** Preserve an existing legacy serialized wrapper while merging only owned fields. */
    private static function encode( $value, $raw ) {
        $old = null === $raw ? null : maybe_unserialize( $raw );
        return maybe_serialize( is_string( $old ) && is_serialized( $old ) ? serialize( $value ) : $value );
    }
    /** Bind mutation/transaction SQL to the original named-lock connection.
     * wpdb may reconnect and replay a query; a new connection cannot inherit this owner.
     */
    private static function assert_owner() {
        global $wpdb;
        if (null===self::$owner) { return; }
        $owner=self::$owner;
        try {
            if ($wpdb->options!==$owner['table'] || $wpdb->dbh!==$owner['db'] || (string)mysqli_thread_id($owner['db'])!==$owner['id']) { throw new RuntimeException(); }
            $result=mysqli_query($owner['db'],$wpdb->prepare('SELECT IS_USED_LOCK(%s)',$owner['name']));
            if (!($result instanceof mysqli_result)) { throw new RuntimeException(); }
            try { $row=mysqli_fetch_row($result); } finally { mysqli_free_result($result); }
            if (!$row || (string)$row[0]!==$owner['id']) { throw new RuntimeException(); }
        } catch(Throwable $e) { throw new RuntimeException('migration_ownership_lost'); }
    }
    private static function query( $sql ) {
        if (null===self::$owner) { throw new RuntimeException('migration_ownership_lost'); }
        self::assert_owner();
        $sql=apply_filters('query',$sql); // Retain WordPress query observers/fault fixtures.
        self::assert_owner();
        try { $result=mysqli_query(self::$owner['db'],$sql); }
        catch(Throwable $e) { throw new RuntimeException('migration_write_failed'); }
        if (false===$result) { throw new RuntimeException('migration_write_failed'); }
        self::assert_owner(); return $result;
    }
    private static function put( $name, $raw, $before ) {
        global $wpdb;
        if ($raw!==$before) {
            $sql=null===$before
                ? $wpdb->prepare("INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",$name,$raw)
                : $wpdb->prepare("UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s",$raw,$name,$before);
            self::query($sql);
            if (1!==mysqli_affected_rows(self::$owner['db'])) { throw new RuntimeException('migration_write_failed'); }
            wp_cache_delete($name,'options'); wp_cache_delete('alloptions','options'); wp_cache_delete('notoptions','options');
        }
        if (self::read($name)!==$raw) { throw new RuntimeException('migration_readback_failed'); }
    }
    public static function locked( $callback ) {
        global $wpdb;
        // Native onboarding/First Purchase services share the already held options owner.
        // A nested call must neither reacquire nor release that owner's named lock.
        if (null!==self::$owner) {
            self::assert_owner();
            try { return $callback(); } finally { self::assert_owner(); }
        }
        $lock='loyf-options:'.md5($wpdb->options);
        if ('1'!==(string)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)',$lock))) { throw new RuntimeException('migration_lock_unavailable'); }
        $db=$wpdb->dbh;
        try {
            if (!($db instanceof mysqli)) { throw new RuntimeException('migration_ownership_lost'); }
            self::$owner=array('db'=>$db,'id'=>(string)mysqli_thread_id($db),'name'=>$lock,'table'=>$wpdb->options);
            self::assert_owner(); return $callback();
        } finally {
            self::$owner=null;
            // Release only the original connection's lock, including after wpdb reconnects.
            try { $result=mysqli_query($db,$wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock)); if($result instanceof mysqli_result){mysqli_free_result($result);} } catch(Throwable $e) {}
        }
    }
    private static function points( $value ) {
        if ( '' === $value ) { return $value; }
        if ( ! is_scalar( $value ) || ! is_numeric( $value ) || ! is_finite( (float) $value ) || (float) $value < 0 || (float) $value > 2147483647 ) { throw new RuntimeException( 'migration_malformed_points' ); }
        return $value;
    }
    private static function specification( $feature, $frozen_source = null, $frozen = false ) {
        $account = 'loyalty_extra_points_rules';
        $merged = 'loyalty_extra_reviews_gamification_rules';
        if ( in_array( $feature, array( 'signup', 'login', 'review' ), true ) ) {
            $raw = $frozen ? $frozen_source : self::read( $account );
            $source = self::decode( $raw );
            $points = self::points( $source[$feature . '_points'] ?? 0 );
            return array( 'source' => $raw, 'target' => 'review' === $feature ? $merged : $account, 'patch' => array( $feature . '_points' => $points, $feature . '_enabled' => (float) $points > 0 ? 'yes' : 'no' ) );
        }
        if ( 'levelup' === $feature ) {
            $raw = $frozen ? $frozen_source : self::read( 'loyalty_extra_levelup_points_rules' );
            $map = self::decode( $raw );
            $enabled = false;
            foreach ( $map as $role => $rule ) {
                if ( ! is_string( $role ) || sanitize_key( $role ) !== $role || ! is_array( $rule ) ) { throw new RuntimeException( 'migration_malformed_role_map' ); }
                $points = self::points( $rule['awarded'] ?? 0 );
                $enabled = $enabled || (float) $points > 0;
            }
            return array( 'source' => $raw, 'target' => $merged, 'patch' => array( 'levelup_points' => $map, 'levelup_enabled' => $enabled ? 'yes' : 'no' ) );
        }
        if ( 'redemption' === $feature ) {
            $raw = $frozen ? $frozen_source : self::read( 'loyalty_points_using_rules' );
            $rules = self::decode( $raw );
            $patch = array();
            foreach ( array( 'min_points', 'max_points', 'min_cart' ) as $key ) { if ( ! array_key_exists( $key, $rules ) ) { $patch[$key] = ''; } }
            return array( 'source' => $raw, 'target' => 'loyalty_points_using_rules', 'patch' => $patch );
        }
        $ids = array( 'email_reward' => 'points_reward', 'email_deduct' => 'points_deduct', 'email_level' => 'level_update' );
        if ( ! isset( $ids[$feature] ) ) { throw new RuntimeException( 'migration_unknown_feature' ); }
        $raw = $frozen ? $frozen_source : self::read( 'loyalty_notification_email' );
        $source = self::decode( $raw );
        $key = 'email_level' === $feature ? 'level_update' : 'points_update';
        if ( isset( $source[$key] ) && ! is_scalar( $source[$key] ) ) { throw new RuntimeException( 'migration_malformed_email' ); }
        // Exactly the old Free sender's !empty semantics, including disabled preferences.
        return array( 'source' => $raw, 'target' => 'woocommerce_yowcl_loyalty_' . $ids[$feature] . '_settings', 'patch' => array( 'enabled' => ! empty( $source[$key] ) ? 'yes' : 'no' ) );
    }
    public static function features() {
        return array( 'signup','login','review','levelup','redemption','email_reward','email_deduct','email_level' );
    }
    private static function target( $feature ) {
        $targets = array( 'signup'=>'loyalty_extra_points_rules','login'=>'loyalty_extra_points_rules','review'=>'loyalty_extra_reviews_gamification_rules','levelup'=>'loyalty_extra_reviews_gamification_rules','redemption'=>'loyalty_points_using_rules','email_reward'=>'woocommerce_yowcl_loyalty_points_reward_settings','email_deduct'=>'woocommerce_yowcl_loyalty_points_deduct_settings','email_level'=>'woocommerce_yowcl_loyalty_level_update_settings' );
        if ( ! isset( $targets[$feature] ) ) { throw new RuntimeException( 'migration_unknown_feature' ); }
        return $targets[$feature];
    }
    private static function keys( $feature ) {
        return 'redemption' === $feature ? array('min_points','max_points','min_cart') : (0 === strpos($feature,'email_') ? array('enabled') : array($feature.'_points',$feature.'_enabled'));
    }
    /** These are positive Premium-history signals, never a last-writer assertion. */
    private static function premium_history() {
        global $wpdb;
        $rows = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE (option_name LIKE 'wc\\_loyalty\\_%' AND option_name <> 'wc_loyalty_db_version') OR option_name LIKE 'yowcl\\_%' OR option_name LIKE 'yol\\_%'" );
        if ( $wpdb->last_error || ! is_array($rows) ) { throw new RuntimeException('migration_storage_unavailable'); }
        return count($rows) > 0 || class_exists('YOWCL_Loyalty',false);
    }
    /** Authenticated pre-upgrade capture verifies actual historical executable bytes.
     * Ordinary stores without such a capture use explicit per-feature resolution.
     */
    public static function capture_legacy( $feature, $nonce ) {
        if ( ! current_user_can('manage_options') || ! wp_verify_nonce($nonce,'loyf_capture_legacy_'.$feature) ) { throw new RuntimeException('migration_capture_denied'); }
        self::target($feature);
        self::locked(function() use($feature) {
            if ( self::premium_history() || self::read('wc_loyalty_db_version') !== null || self::read('loyf_core_cutover_v1') !== null ) { throw new RuntimeException('migration_origin_unresolved'); }
            $active = self::decode(self::read('active_plugins'));
            if ( ! in_array(YOSWC_LOYALTY_PLUGIN_BASENAME,$active,true) || in_array('wc-loyalty/wc-loyalty.php',$active,true) || is_multisite() ) { throw new RuntimeException('migration_origin_unresolved'); }
            $files = array();
            foreach ( array('loyalty-for-woocommerce.php','readme.txt','changelog.txt','license.txt','css','img','inc','js','languages','templates') as $entry ) {
                $path = YOSWC_LOYALTY_PLUGIN_DIR.$entry;
                if ( is_link($path) ) { throw new RuntimeException('migration_origin_unresolved'); }
                if ( is_file($path) ) { $files[$entry]=hash_file('sha256',$path); }
                elseif ( is_dir($path) ) {
                    foreach ( new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS)) as $file ) {
                        if ( $file->isLink() || ! $file->isFile() ) { throw new RuntimeException('migration_origin_unresolved'); }
                        $relative=substr($file->getPathname(),strlen(YOSWC_LOYALTY_PLUGIN_DIR));
                        $files[$relative]=hash_file('sha256',$file->getPathname());
                    }
                }
            }
            ksort($files); $bytes='';
            foreach($files as $path=>$hash) { $bytes.=$path."\0".$hash."\n"; }
            // Exact immutable Free3c1aac2 distribution, all50 product files.
            if ( count($files)!==50 || hash('sha256',$bytes)!=='eb5f98189fffbeb0da825ba5bab75af8967ade0f0b88260fdc7bd47eb5496034' ) { throw new RuntimeException('migration_origin_unresolved'); }
            if ( null!==self::read(self::witness($feature)) || null!==self::read(self::witness($feature).'_before') ) { throw new RuntimeException('migration_evidence_exists'); }
            $spec=self::specification($feature); $spec['before']=self::read($spec['target']);
            self::decode($spec['before']);
            $spec['origin']='legacy:3c1aac240df03101561b856cddb603b1501fa41b';
            self::put(self::witness($feature).'_before',serialize($spec),null);
        });
    }
    private static function owned( $raw, $keys ) {
        $value=self::decode($raw); $pair=array();
        foreach($keys as $key) { $pair[$key]=array_key_exists($key,$value) ? array(true,$value[$key]) : array(false); }
        return serialize($pair);
    }
    private static function validate_spec( $feature, $spec, $resolution=false ) {
        if ( ! is_array($spec) || !isset($spec['target'],$spec['patch']) || !array_key_exists('before',$spec) || !array_key_exists('source',$spec) || $spec['target']!==self::target($feature) || !is_array($spec['patch']) ) { throw new RuntimeException('migration_malformed_evidence'); }
        $keys=self::keys($feature);
        if ($resolution && 'redemption'===$feature) { $keys=array_merge($keys,array('points','amount')); }
        if ( array_diff(array_keys($spec['patch']),$keys) || ('redemption'!==$feature && count($spec['patch'])!==count($keys)) ) { throw new RuntimeException('migration_malformed_evidence'); }
        self::decode($spec['source']); self::decode($spec['before']);
        foreach($spec['patch'] as $key=>$value) {
            if ('enabled'===$key || substr($key,-8)==='_enabled') {
                if (!in_array($value,array('yes','no'),true)) { throw new RuntimeException('migration_malformed_evidence'); }
            } elseif ('levelup_points'===$key) {
                if (!is_array($value)) { throw new RuntimeException('migration_malformed_evidence'); }
                foreach($value as $role=>$rule) {
                    if (!is_string($role) || sanitize_key($role)!==$role || !is_array($rule)) { throw new RuntimeException('migration_malformed_evidence'); }
                    self::points($rule['awarded']??0);
                }
            } else { self::points($value); }
        }
        return $keys;
    }
    private static function finish( $feature, $spec, $resolution=false ) {
        global $wpdb;
        self::assert_owner(); $db=self::$owner['db'];
        $engine=$wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$wpdb->options));
        self::assert_owner();
        if ($wpdb->last_error || 'InnoDB'!==$engine || YOWCL_Points_Lock::has_transaction($db)) { throw new RuntimeException('migration_transaction_unavailable'); }
        self::query('START TRANSACTION');
        try {
            foreach(array(self::target($feature),self::witness($feature)) as $name) {
                $result=self::query($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s FOR UPDATE",$name));
                if (!($result instanceof mysqli_result)) { throw new RuntimeException('migration_storage_unavailable'); }
                mysqli_free_result($result);
            }
            if (null!==self::read(self::witness($feature))) { throw new RuntimeException('migration_witness_changed'); }
            self::finish_locked($feature,$spec,$resolution);
            self::query('COMMIT');
        } catch(Throwable $e) {
            try { mysqli_query($db,'ROLLBACK'); } catch(Throwable $ignored) {}
            wp_cache_flush(); throw $e;
        }
    }
    private static function finish_locked( $feature, $spec, $resolution=false ) {
        $keys=self::validate_spec($feature,$spec,$resolution);
        $raw=self::read($spec['target']); $current=self::decode($raw);
        $post=self::encode(array_replace(self::decode($spec['before']),$spec['patch']),$spec['before']);
        $pair=self::owned($raw,$keys);
        if ($pair!==self::owned($spec['before'],$keys) && $pair!==self::owned($post,$keys)) { throw new RuntimeException('migration_target_changed'); }
        if ($pair!==self::owned($post,$keys)) { self::put($spec['target'],self::encode(array_replace($current,$spec['patch']),$raw),$raw); }
        if (self::owned(self::read($spec['target']),$keys)!==self::owned($post,$keys)) { throw new RuntimeException('migration_semantics_readback_failed'); }
        self::put(self::witness($feature),'1',null);
    }
    private static function migrate( $feature ) {
        $witness=self::read(self::witness($feature));
        if ('1'===$witness) { return; }
        if (null!==$witness) { throw new RuntimeException('migration_malformed_witness'); }
        if (null!==self::read(self::witness($feature).'_resolution')) { throw new RuntimeException('migration_resolution_pending'); }
        if (self::premium_history()) { throw new RuntimeException('migration_origin_unresolved'); }
        $name=self::witness($feature).'_before'; $raw=self::read($name);
        if (null===$raw) {
            $proof=YOWCL_Free_Onboarding::initial_proof();
            if (!$proof) { throw new RuntimeException('migration_origin_unresolved'); }
            $spec=self::specification($feature); $spec['before']=self::read($spec['target']);
            $spec['origin']='fresh:'.hash('sha256',$proof);
            self::validate_spec($feature,$spec); self::put($name,serialize($spec),null);
        } else { $spec=self::decode($raw); }
        $origin=$spec['origin']??'';
        $fresh=YOWCL_Free_Onboarding::initial_proof();
        if ('legacy:3c1aac240df03101561b856cddb603b1501fa41b'!==$origin && (!$fresh || 'fresh:'.hash('sha256',$fresh)!==$origin)) { throw new RuntimeException('migration_origin_unresolved'); }
        $frozen=self::specification($feature,$spec['source'],true);
        if (serialize($frozen['patch'])!==serialize($spec['patch']) || $frozen['target']!==$spec['target']) { throw new RuntimeException('migration_malformed_evidence'); }
        self::finish($feature,$spec);
    }
    /** Preview terms bind the single feature and exact stored source/target to POST. */
    public static function preview( $feature, $mode ) {
        self::target($feature);
        if (!in_array($mode,array('canonical','legacy','disable'),true)) { throw new RuntimeException('migration_resolution_invalid'); }
        $pending=self::read(self::witness($feature).'_resolution');
        if (null!==$pending) {
            $spec=self::decode($pending);
            if (($spec['mode']??null)!==$mode) { throw new RuntimeException('migration_resolution_pending'); }
            self::validate_spec($feature,$spec,true); return $spec;
        }
        $target=self::target($feature); $raw=self::read($target); $current=self::decode($raw);
        if ('legacy'===$mode) {
            $spec=self::specification($feature);
            // Absence may never become an authorized legacy OFF choice.
            if (null===$spec['source']) { throw new RuntimeException('migration_legacy_source_missing'); }
            $source=self::decode($spec['source']);
            if (in_array($feature,array('signup','login','review'),true) && !array_key_exists($feature.'_points',$source)) { throw new RuntimeException('migration_legacy_source_missing'); }
            if (0===strpos($feature,'email_') && !array_key_exists('email_level'===$feature?'level_update':'points_update',$source)) { throw new RuntimeException('migration_legacy_source_missing'); }
        } else {
            $patch=array(); $keys=self::keys($feature);
            if ('redemption'===$feature) { $keys=array_merge($keys,array('points','amount')); }
            foreach($keys as $key) {
                if ('canonical'===$mode) {
                    if (!array_key_exists($key,$current)) { throw new RuntimeException('migration_canonical_incomplete'); }
                    $patch[$key]=$current[$key];
                } else { $patch[$key]=('enabled'===$key || substr($key,-8)==='_enabled') ? 'no' : ('levelup_points'===$key?array():0); }
            }
            $spec=array('source'=>$raw,'target'=>$target,'patch'=>$patch);
        }
        $spec['before']=$raw; $spec['mode']=$mode;
        self::validate_spec($feature,$spec,true); return $spec;
    }
    public static function resolve( $feature, $mode, $fingerprint, $nonce ) {
        if (!current_user_can('manage_options') || !YOWCL_Free_Core::owns() || !wp_verify_nonce($nonce,'loyf_resolve_'.$feature)) { throw new RuntimeException('migration_resolution_denied'); }
        self::locked(function() use($feature,$mode,$fingerprint) {
            if (self::ready($feature)) { return; }
            if (null!==self::read(self::witness($feature))) { throw new RuntimeException('migration_malformed_witness'); }
            $spec=self::preview($feature,$mode);
            if (!hash_equals(hash('sha256',serialize($spec)),(string)$fingerprint)) { throw new RuntimeException('migration_resolution_stale'); }
            $name=self::witness($feature).'_resolution';
            self::put($name,serialize($spec),self::read($name));
            self::finish($feature,$spec,true);
        });
    }
    public static function handle_resolution() {
        $feature=is_string($_POST['feature']??null)?sanitize_key(wp_unslash($_POST['feature'])):'';
        $mode=is_string($_POST['mode']??null)?sanitize_key(wp_unslash($_POST['mode'])):'';
        $fingerprint=is_string($_POST['fingerprint']??null)?sanitize_text_field(wp_unslash($_POST['fingerprint'])):'';
        $nonce=is_string($_POST['_wpnonce']??null)?sanitize_text_field(wp_unslash($_POST['_wpnonce'])):'';
        $method=is_string($_SERVER['REQUEST_METHOD']??null)?sanitize_key(wp_unslash($_SERVER['REQUEST_METHOD'])):'';
        if ('post'!==$method || !current_user_can('manage_options') || !YOWCL_Free_Core::owns() || !in_array($feature,self::features(),true) || !in_array($mode,array('canonical','legacy','disable'),true) || !preg_match('/^[a-f0-9]{64}$/D',$fingerprint) || !wp_verify_nonce($nonce,'loyf_resolve_'.$feature)) { wp_die('migration_resolution_denied'); }
        try { self::resolve($feature,$mode,$fingerprint,$nonce); }
        catch(Throwable $e) { wp_die(esc_html($e->getMessage())); }
        wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=loyalty')); exit;
    }
    /** Completed historical evidence is for manual assessment, never rollback authority. */
    public static function historical_evidence( $feature ) {
        if (!current_user_can('manage_options') || !self::ready($feature)) { return null; }
        $raw=self::read(self::witness($feature).'_before');
        if (null===$raw) { return null; }
        $spec=self::decode($raw);
        if (isset($spec['origin'])) { return null; }
        $keys=self::validate_spec($feature,$spec);
        $select=static function($raw)use($keys) {
            $value=self::decode($raw); $terms=array();
            foreach($keys as $key) {
                if (!array_key_exists($key,$value)) { continue; }
                if ('levelup_points'===$key && is_array($value[$key])) {
                    $terms[$key]=array();
                    foreach($value[$key] as $role=>$rule) {
                        if (is_string($role) && sanitize_key($role)===$role && is_array($rule) && is_scalar($rule['awarded']??null)) { $terms[$key][$role]=array('awarded'=>$rule['awarded']); }
                    }
                } elseif(is_scalar($value[$key])) { $terms[$key]=$value[$key]; }
            }
            return $terms;
        };
        try { $current=$select(self::read(self::target($feature))); } catch(Throwable $e) { $current=__('Unavailable','loyalty-for-woocommerce'); }
        return array('before'=>$select($spec['before']),'migration'=>$select(serialize($spec['patch'])),'current'=>$current);
    }
    public static function run() {
        if ( ! YOWCL_Free_Core::owns() ) { return; }
        foreach ( self::features() as $feature ) {
            try { self::locked( function() use ( $feature ) { self::migrate( $feature ); } ); unset( self::$errors[$feature] ); }
            catch ( Throwable $e ) { self::$errors[$feature] = $e->getMessage(); }
        }
    }
    public static function notices() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        foreach ( self::$errors as $feature => $code ) {
            /* translators: 1: feature name, 2: migration diagnostic code. */
            echo '<div class="notice notice-error"><p>' . esc_html( sprintf( __( 'Loyalty compatibility migration needs attention (%1$s: %2$s). This feature is held until storage/settings are repaired and migration succeeds.', 'loyalty-for-woocommerce' ), $feature, $code ) ) . '</p>';
            foreach(array('canonical'=>__('Keep current canonical','loyalty-for-woocommerce'),'legacy'=>__('Adopt legacy Free semantics','loyalty-for-woocommerce'),'disable'=>__('Disable using Free settings','loyalty-for-woocommerce')) as $mode=>$label) {
                try {
                    $spec=self::preview($feature,$mode);
                    echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
                    echo '<p>'.esc_html($label).' — '.esc_html(wp_json_encode($spec['patch'])).'</p>';
                    foreach(array('action'=>'loyf_resolve_migration','feature'=>$feature,'mode'=>$mode,'fingerprint'=>hash('sha256',serialize($spec))) as $key=>$value) { echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'">'; }
                    wp_nonce_field('loyf_resolve_'.$feature);
                    echo '<button type="submit" class="button">'.esc_html($label).'</button></form>';
                } catch(Throwable $e) { /* Unavailable choices grant no authority. */ }
            }
            echo '</div>';
        }
        foreach(self::features() as $feature) {
            try { $evidence=self::historical_evidence($feature); } catch(Throwable $e) { continue; }
            if (null===$evidence) { continue; }
            echo '<div class="notice notice-info"><details><summary>'.esc_html__('Historical Loyalty migration evidence','loyalty-for-woocommerce').' — '.esc_html($feature).'</summary><p>'.esc_html__('These stored terms do not prove which edition last wrote them. Review the before, migration and current terms manually. No automatic restore is performed.','loyalty-for-woocommerce').'</p><pre>'.esc_html(wp_json_encode($evidence)).'</pre></details></div>';
        }
    }
    /** Canonical merchant saves cannot race or precede unfinished initial migration. */
    public static function save( $feature, $target, $patch ) {
        self::locked( function() use ( $feature, $target, $patch ) {
            if ( ! self::ready( $feature ) ) { throw new RuntimeException( 'migration_incomplete' ); }
            $raw = self::read( $target );
            self::put( $target, self::encode( array_replace( self::decode( $raw ), $patch ), $raw ), $raw );
        } );
    }
    /** Old uncommitted cart evidence is cleared; canonical attempts/returns stay untouched. */
    public static function session() {
        if ( ! WC()->session || null !== WC()->session->get( 'loyf_funded_selection' ) ) { return; }
        if ( null === WC()->session->get( 'yoswc_loyalty_applied_points' ) && null === WC()->session->get( 'yoswc_loyalty_discount_amount' ) ) { return; }
        WC()->session->__unset( 'yoswc_loyalty_applied_points' );
        WC()->session->__unset( 'yoswc_loyalty_discount_amount' );
        wc_add_notice( __( 'Please reapply loyalty points after the upgrade. Your points balance has not changed.', 'loyalty-for-woocommerce' ), 'notice' );
    }
}
