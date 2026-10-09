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
    /** Read committed canonical policy independently of local/shared option caches. */
    public static function canonical( $feature ) {
        try { return self::ready($feature) ? self::decode(self::read(self::target($feature))) : array(); }
        catch ( Throwable $e ) { return array(); }
    }
    /** Readability never grants witness or repair authority. Missing targets remain supported. */
    public static function readable( $feature ) {
        try { self::target_settings($feature); return true; } catch (Throwable $e) { return false; }
    }
    private static function target_settings( $feature ) {
        $raw=self::read(self::target($feature));
        try { return self::decode($raw); }
        catch (Throwable $e) { throw new RuntimeException('migration_opaque_target'); }
    }
    public static function held_settings_message( $feature = null ) {
        if (null!==$feature) {
            try { self::target_settings($feature); }
            catch (Throwable $e) { if ('migration_opaque_target'===$e->getMessage()) { return self::opaque_message($feature); } }
        }
        return __('This feature is on hold and is not active. Its saved settings are preserved until an administrator explicitly reviews and confirms them in Migration Review.', 'loyalty-for-woocommerce');
    }
    private static function opaque_message( $feature ) {
        /* translators: Canonical option container name, never its stored contents. */
        return sprintf(__('The saved Loyalty settings container (%s) has an unsupported format. Its original data was preserved. Restore or repair the original complete container using verified backups before changing this feature.', 'loyalty-for-woocommerce'),self::target($feature));
    }
    private static function invalidate( $feature ) {
        wp_cache_delete(self::target($feature),'options');
        wp_cache_delete(self::witness($feature),'options');
        wp_cache_delete('alloptions','options');
        wp_cache_delete('notoptions','options');
    }
    private static function decode( $raw ) {
        if ( null === $raw ) { return array(); }
        $value=$raw;
        for ($i=0;$i<2;$i++) {
            if (is_string($value) && preg_match('/^(?:O|C):/', $value)) { throw new RuntimeException('migration_malformed_option'); }
            $value=maybe_unserialize($value);
        }
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
            throw $e;
        } finally {
            // Another request can refill pre-images during the transaction.
            self::invalidate($feature);
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
        self::target_settings($feature);
        if ('1'===$witness) { self::invalidate($feature); return; }
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
        $current=self::target_settings($feature);
        $witness=self::read(self::witness($feature));
        if (null!==$witness && '1'!==$witness) { throw new RuntimeException('migration_malformed_witness'); }
        $pending=self::read(self::witness($feature).'_resolution');
        if (null!==$pending) {
            $spec=self::decode($pending);
            if (($spec['mode']??null)!==$mode) { throw new RuntimeException('migration_resolution_pending'); }
            self::validate_spec($feature,$spec,true); return $spec;
        }
        $target=self::target($feature); $raw=self::read($target);
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
        return self::locked(function() use($feature,$mode,$fingerprint) {
            self::target_settings($feature);
            if (self::ready($feature)) { return 'already_confirmed'; }
            if (null!==self::read(self::witness($feature))) { throw new RuntimeException('migration_malformed_witness'); }
            $spec=self::preview($feature,$mode);
            if (!hash_equals(hash('sha256',serialize($spec)),(string)$fingerprint)) { throw new RuntimeException('migration_resolution_stale'); }
            $name=self::witness($feature).'_resolution';
            self::put($name,serialize($spec),self::read($name));
            self::finish($feature,$spec,true);
            return 'confirmed';
        });
    }
    public static function handle_resolution() {
        $feature=is_string($_POST['feature']??null)?sanitize_key(wp_unslash($_POST['feature'])):'';
        $mode=is_string($_POST['mode']??null)?sanitize_key(wp_unslash($_POST['mode'])):'';
        $fingerprint=is_string($_POST['fingerprint']??null)?sanitize_text_field(wp_unslash($_POST['fingerprint'])):'';
        $nonce=is_string($_POST['_wpnonce']??null)?sanitize_text_field(wp_unslash($_POST['_wpnonce'])):'';
        $method=is_string($_SERVER['REQUEST_METHOD']??null)?sanitize_key(wp_unslash($_SERVER['REQUEST_METHOD'])):'';
        if ('post'!==$method || !current_user_can('manage_options') || !YOWCL_Free_Core::owns() || !in_array($feature,self::features(),true) || !in_array($mode,array('canonical','legacy','disable'),true) || !preg_match('/^[a-f0-9]{64}$/D',$fingerprint) || !wp_verify_nonce($nonce,'loyf_resolve_'.$feature)) { wp_die('migration_resolution_denied'); }
        try { $decision=self::resolve($feature,$mode,$fingerprint,$nonce); }
        catch(Throwable $e) {
            $code=in_array($e->getMessage(),array('migration_resolution_stale','migration_target_changed'),true)?$e->getMessage():'unconfirmed';
            wp_safe_redirect(add_query_arg(array('feature'=>$feature,'result'=>$code,'choice'=>$mode),admin_url('admin.php?page=loyf-migration-review')).'#loyf-review-'.$feature); exit;
        }
        $result='confirmed'===self::review_state($feature)?$decision:'unconfirmed';
        wp_safe_redirect(add_query_arg(array('feature'=>$feature,'result'=>$result),admin_url('admin.php?page=loyf-migration-review')).'#loyf-review-'.$feature); exit;
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
    public static function review_url( $feature = '' ) {
        return admin_url('admin.php?page=loyf-migration-review') . (in_array($feature,self::features(),true) ? '#loyf-review-'.$feature : '');
    }
    public static function held_link( $feature ) {
        return '<strong>'.esc_html__('On hold / not active','loyalty-for-woocommerce').'</strong> '.esc_html__('New actions for this feature are paused. Saved data is preserved.','loyalty-for-woocommerce').' <a href="'.esc_url(self::review_url($feature)).'">'.esc_html__('Review this setting','loyalty-for-woocommerce').'</a>';
    }
    /** Derive all UI states from the existing rows; storage failures never imply readiness. */
    public static function review_state( $feature ) {
        try {
            self::target_settings($feature);
            $witness=self::read(self::witness($feature));
            if ('1'===$witness) { return 'confirmed'; }
            if (null!==$witness) { return 'manual'; }
            $pending=self::read(self::witness($feature).'_resolution');
            if (null!==$pending) {
                $spec=self::decode($pending); self::validate_spec($feature,$spec,true);
                if (!in_array($spec['mode']??null,array('canonical','legacy','disable'),true)) { return 'manual'; }
                return 'pending';
            }
            return 'review';
        } catch(Throwable $e) { return in_array($e->getMessage(),array('migration_opaque_target','migration_malformed_option','migration_malformed_evidence','migration_malformed_points','migration_malformed_role_map','migration_malformed_email'),true) ? 'manual' : 'unavailable'; }
    }
    public static function has_holds() {
        foreach(self::features() as $feature) { if ('confirmed'!==self::review_state($feature)) { return true; } }
        return false;
    }
    public static function notices() {
        if (!current_user_can('manage_options') || !YOWCL_Free_Core::owns()) { return; }
        $screen=function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || !in_array($screen->id,array('woocommerce_page_wc-settings','dashboard','plugins'),true)) { return; }
        $tab=is_string($_GET['tab']??null)?sanitize_key(wp_unslash($_GET['tab'])):'';
        if ('woocommerce_page_wc-settings'===$screen->id && !in_array($tab,array('loyalty','email'),true)) { return; }
        $count=0; foreach(self::features() as $feature) { if ('confirmed'!==self::review_state($feature)) { $count++; } }
        if (!$count) { return; }
        /* translators: Number of held Loyalty features. */
        echo '<div class="notice notice-warning"><p><strong>'.esc_html(sprintf(_n('%s Loyalty setting needs review','%s Loyalty settings need review',$count,'loyalty-for-woocommerce'),number_format_i18n($count))).'</strong> '.esc_html__('Some new rewards, redemption or emails are paused. Existing balances and history are unchanged.','loyalty-for-woocommerce').' <a href="'.esc_url(self::review_url()).'">'.esc_html__('Review Loyalty settings','loyalty-for-woocommerce').'</a></p></div>';
    }
    public static function register_review() {
        add_submenu_page('woocommerce',__('Loyalty Migration Review','loyalty-for-woocommerce'),__('Loyalty Migration Review','loyalty-for-woocommerce'),'manage_options','loyf-migration-review',array(__CLASS__,'render_review'));
    }
    private static function feature_labels() {
        return array('signup'=>__('Sign-up reward','loyalty-for-woocommerce'),'login'=>__('Daily login reward','loyalty-for-woocommerce'),'review'=>__('Product review reward','loyalty-for-woocommerce'),'levelup'=>__('Level-up reward','loyalty-for-woocommerce'),'redemption'=>__('Points redemption','loyalty-for-woocommerce'),'email_reward'=>__('Points earned email','loyalty-for-woocommerce'),'email_deduct'=>__('Points deducted email','loyalty-for-woocommerce'),'email_level'=>__('Level update email','loyalty-for-woocommerce'));
    }
    /** Presentation only. Never write formatted numbers back to stored terms. */
    public static function display_number( $value ) {
        if (''===$value) { return __('Not configured','loyalty-for-woocommerce'); }
        if (!is_scalar($value) || !is_numeric($value) || !is_finite((float)$value)) { return __('Unsupported value','loyalty-for-woocommerce'); }
        return (string)$value;
    }
    private static function scoped_terms( $feature, $value ) {
        $keys=self::keys($feature);
        if ('redemption'===$feature) { $keys=array_merge($keys,array('points','amount')); }
        return array_intersect_key($value,array_flip($keys));
    }
    private static function terms_html( $feature, $terms ) {
        $labels=array('enabled'=>__('Delivery','loyalty-for-woocommerce'),'points'=>__('Exchange points','loyalty-for-woocommerce'),'amount'=>__('Discount amount','loyalty-for-woocommerce'),'min_points'=>__('Minimum points','loyalty-for-woocommerce'),'max_points'=>__('Maximum points','loyalty-for-woocommerce'),'min_cart'=>__('Minimum cart amount','loyalty-for-woocommerce'));
        $keys=self::keys($feature);
        if ('redemption'===$feature) { $keys=array_merge(array('points','amount'),$keys); }
        $html='<ul>';
        foreach($keys as $key) {
            $label=$labels[$key]??(substr($key,-8)==='_enabled' ? __('Future rewards','loyalty-for-woocommerce') : __('Reward points','loyalty-for-woocommerce'));
            if (!array_key_exists($key,$terms)) { $text=__('Absent / unknown','loyalty-for-woocommerce'); }
            elseif ('enabled'===$key || substr($key,-8)==='_enabled') { $text='yes'===$terms[$key] ? __('Enabled after confirmation','loyalty-for-woocommerce') : ('no'===$terms[$key] ? __('Disabled','loyalty-for-woocommerce') : __('Unsupported value','loyalty-for-woocommerce')); }
            elseif ('levelup_points'===$key) {
                $html.='<li>'.esc_html__('Per-role reward points','loyalty-for-woocommerce').'<ul>';
                if (!is_array($terms[$key])) { $html.='<li>'.esc_html__('Unsupported value','loyalty-for-woocommerce').'</li>'; }
                elseif (!$terms[$key]) { $html.='<li>'.esc_html__('No configured role rewards','loyalty-for-woocommerce').'</li>'; }
                else { foreach($terms[$key] as $role=>$rule) { if (!is_string($role) || sanitize_key($role)!==$role) { continue; } $roles=wp_roles()->roles; $role_label=is_string($roles[$role]['name']??null)?$roles[$role]['name']:$role; $html.='<li>'.esc_html($role_label).' : <strong>'.esc_html(self::display_number(is_array($rule)?($rule['awarded']??''):null)).'</strong></li>'; } }
                $html.='</ul></li>'; continue;
            } else { $text=self::display_number($terms[$key]); if (('amount'===$key || 'min_cart'===$key) && ''!==$terms[$key]) { if (''!==$terms[$key] && is_scalar($terms[$key]) && is_numeric($terms[$key])) { $text=wc_format_decimal($terms[$key],wc_get_price_decimals(),true); } $text.=' '.get_woocommerce_currency(); } }
            $html.='<li>'.esc_html($label).': <strong>'.esc_html($text).'</strong></li>';
        }
        return $html.'</ul>';
    }
    private static function resolution_message( $code ) {
        if ('migration_resolution_stale'===$code || 'migration_target_changed'===$code) { return __('The reviewed settings changed. Nothing was confirmed by this request. Reload and review the current terms before choosing again.','loyalty-for-woocommerce'); }
        return __('The decision could not be confirmed. Saved data and any pending choice are preserved. Reload and review this item; retry only the pending choice when available. For unsupported data, ask a qualified administrator to review verified backups.','loyalty-for-woocommerce');
    }
    public static function render_review() {
        if (!current_user_can('manage_options') || !YOWCL_Free_Core::owns()) { wp_die('migration_resolution_denied'); }
        $labels=self::feature_labels();
        $states=array('review'=>__('Needs review','loyalty-for-woocommerce'),'confirmed'=>__('Confirmed','loyalty-for-woocommerce'),'pending'=>__('Incomplete / pending','loyalty-for-woocommerce'),'manual'=>__('Unsupported data / manual repair','loyalty-for-woocommerce'),'unavailable'=>__('Unavailable','loyalty-for-woocommerce'));
        echo '<div class="wrap"><h1>'.esc_html__('Loyalty Migration Review','loyalty-for-woocommerce').'</h1><p>'.esc_html__('Review one feature at a time. Your explicit choice authorizes future rewards, redemption or email delivery only. Existing points, history and historical evidence are not changed. An update without verified pre-upgrade evidence cannot establish which edition last wrote these settings.','loyalty-for-woocommerce').'</p><p><a href="'.esc_url(admin_url('admin.php?page=wc-settings&tab=loyalty')).'">'.esc_html__('Return to Loyalty settings','loyalty-for-woocommerce').'</a></p>';
        $result=is_string($_GET['result']??null)?sanitize_key(wp_unslash($_GET['result'])):'';
        $selected=is_string($_GET['feature']??null)?sanitize_key(wp_unslash($_GET['feature'])):'';
        $attempt=is_string($_GET['choice']??null)?sanitize_key(wp_unslash($_GET['choice'])):'';
        foreach($labels as $feature=>$label) {
            if ('signup'===$feature || 'redemption'===$feature || 'email_reward'===$feature) { echo '<h2>'.esc_html('signup'===$feature ? __('Rewards','loyalty-for-woocommerce') : ('redemption'===$feature ? __('Redemption','loyalty-for-woocommerce') : __('Email delivery','loyalty-for-woocommerce'))).'</h2>'; }
            $state=self::review_state($feature); $current=array();
            echo '<section id="loyf-review-'.esc_attr($feature).'" class="card" style="max-width:100%;box-sizing:border-box" tabindex="-1" aria-labelledby="loyf-title-'.esc_attr($feature).'"><h3 id="loyf-title-'.esc_attr($feature).'">'.esc_html($label).'</h3><p role="status"><strong>'.esc_html($states[$state]).'</strong></p>';
            if ($selected===$feature && $result) {
                $ok='confirmed'===$state && in_array($result,array('confirmed','already_confirmed'),true);
                echo '<div role="'.($ok?'status':'alert').'" class="notice notice-'.($ok?'success':'error').' inline"><p>'.esc_html($ok ? ('already_confirmed'===$result ? __('This feature was already confirmed. No new choice was applied. Review the current terms below.','loyalty-for-woocommerce') : __('Confirmed from current server storage. Review the next item or return to settings to edit this feature.','loyalty-for-woocommerce')) : self::resolution_message($result)).'</p></div>';
            }
            try { $current=self::target_settings($feature); echo '<h4>'.esc_html__('Current canonical terms','loyalty-for-woocommerce').'</h4>'.self::terms_html($feature,self::scoped_terms($feature,$current)); }
            catch(Throwable $e) { echo '<p>'.esc_html(self::held_settings_message($feature)).'</p>'; }
            if ('confirmed'!==$state) {
                try {
                    $legacy=self::preview($feature,'legacy');
                    echo '<h4>'.esc_html__('Valid legacy terms for comparison','loyalty-for-woocommerce').'</h4>'.self::terms_html($feature,self::scoped_terms($feature,array_replace(self::decode($legacy['before']),$legacy['patch'])));
                } catch(Throwable $e) { echo '<p>'.esc_html__('Valid legacy terms are absent, unsupported or unavailable for the pending choice. This is not a zero or disabled setting.','loyalty-for-woocommerce').'</p>'; }
                echo '<p>'.esc_html__('On hold / not active. Compare the terms below before confirming. Zero values or disabled choices stop future activity for this feature. Other ready settings can be saved independently.','loyalty-for-woocommerce').'</p>';
                foreach(array('canonical'=>__('Keep current','loyalty-for-woocommerce'),'legacy'=>__('Use proven legacy','loyalty-for-woocommerce'),'disable'=>__('Disable','loyalty-for-woocommerce')) as $mode=>$choice) {
                    try {
                        if (in_array($state,array('manual','unavailable'),true)) { continue; }
                        $spec=self::preview($feature,$mode);
                        $after=array_replace(self::decode($spec['before']),$spec['patch']);
                        echo '<details'.($selected===$feature && $attempt===$mode ? ' open' : '').'><summary>'.esc_html($choice).'</summary><p>'.esc_html('legacy'===$mode ? __('These are valid historical Free terms, not proof of the last writer. Choose them only after reviewing this comparison.','loyalty-for-woocommerce') : __('This is an explicit decision about future settings.','loyalty-for-woocommerce')).'</p>'.self::terms_html($feature,self::scoped_terms($feature,$after));
                        if ('legacy'===$mode && !$spec['patch']) { echo '<p>'.esc_html__('No setting values will change; confirmation authorizes the displayed existing redemption terms.','loyalty-for-woocommerce').'</p>'; }
                        if (self::scoped_terms($feature,$current??array())!==self::scoped_terms($feature,$after)) { echo '<p><strong>'.esc_html__('The displayed terms differ from the current canonical terms. Check each amount and role before confirming.','loyalty-for-woocommerce').'</strong></p>'; }
                        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
                        foreach(array('action'=>'loyf_resolve_migration','feature'=>$feature,'mode'=>$mode,'fingerprint'=>hash('sha256',serialize($spec))) as $key=>$value) { echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'">'; }
                        echo '<input type="hidden" name="_wpnonce" value="'.esc_attr(wp_create_nonce('loyf_resolve_'.$feature)).'">';
                        /* translators: Explicit feature choice. */
                        echo '<p><button class="button" type="submit">'.esc_html(sprintf(__('Confirm: %s','loyalty-for-woocommerce'),$choice)).'</button></p></form></details>';
                    } catch(Throwable $e) { echo '<p>'.esc_html($choice).' — '.esc_html__('Unavailable for the current data or pending choice.','loyalty-for-woocommerce').'</p>'; }
                }
                echo '<p><a href="'.esc_url(self::review_url($feature)).'">'.esc_html__('Reload and review this item','loyalty-for-woocommerce').'</a></p>';
            }
            if ('confirmed'===$state) {
                $sections=array('email_reward'=>'yowcl_wc_email_loyalty_points_reward','email_deduct'=>'yowcl_wc_email_loyalty_points_deduct','email_level'=>'yowcl_wc_email_loyalty_level_update');
                $edit=isset($sections[$feature]) ? admin_url('admin.php?page=wc-settings&tab=email&section='.$sections[$feature]) : admin_url('admin.php?page=wc-settings&tab=loyalty&section='.('redemption'===$feature?'general':'extra_points'));
                echo '<p><a href="'.esc_url($edit).'">'.esc_html__('Edit confirmed settings','loyalty-for-woocommerce').'</a></p>';
            }
            try { $history=self::historical_evidence($feature); if (null!==$history) { echo '<details><summary>'.esc_html__('Historical Loyalty migration evidence','loyalty-for-woocommerce').'</summary><p>'.esc_html__('Read-only evidence; this does not grant rollback authority or prove which edition last wrote these terms.','loyalty-for-woocommerce').'</p>'; foreach($history as $kind=>$terms) { $titles=array('before'=>__('Before migration','loyalty-for-woocommerce'),'migration'=>__('Migration terms','loyalty-for-woocommerce'),'current'=>__('Current terms','loyalty-for-woocommerce')); echo '<h4>'.esc_html($titles[$kind]??__('Evidence','loyalty-for-woocommerce')).'</h4>'.(is_array($terms)?self::terms_html($feature,$terms):esc_html__('Unavailable','loyalty-for-woocommerce')); } echo '</details>'; } } catch(Throwable $e) { echo '<p>'.esc_html__('Historical evidence unavailable.','loyalty-for-woocommerce').'</p>'; }
            echo '</section>';
        }
        echo '</div><script>document.addEventListener("DOMContentLoaded",function(){var id=window.location.hash.slice(1);if(/^loyf-review-(signup|login|review|levelup|redemption|email_reward|email_deduct|email_level)$/.test(id)){var item=document.getElementById(id);if(item){item.focus();}}});</script>';
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

add_action('admin_menu',array('YOWCL_Free_Migrations','register_review'));
