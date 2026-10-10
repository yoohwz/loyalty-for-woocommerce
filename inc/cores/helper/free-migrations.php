<?php
/** One-time Free semantic conversions. No balance, history or role ownership writes. */
defined( 'ABSPATH' ) || exit;
class YOWCL_Free_Migrations {
    private static $errors = array();
    private static $owner = null;
    private static $transaction = false;
    const HOOK = 'loyf_migrate_feature';
    const GROUP = 'loyf-migrations';
    public static function witness( $feature ) { return 'loyf_migration_' . $feature . '_v1'; }
    public static function read( $name ) {
        global $wpdb;
        self::assert_owner();
        $rows = $wpdb->get_col( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s" . (self::transaction_active() ? ' FOR UPDATE' : ''), $name ) );
        self::assert_owner();
        if ( $wpdb->last_error || ! is_array( $rows ) || count( $rows ) > 1 ) { throw new RuntimeException( 'migration_storage_unavailable' ); }
        return $rows ? $rows[0] : null;
    }
    public static function ready( $feature ) {
        try { return '1' === self::read( self::witness( $feature ) ); } catch ( Throwable $e ) { return false; }
    }
    /** Read committed canonical policy independently of local/shared option caches. */
    public static function canonical( $feature, $required = false ) {
        try {
            if ('1'!==self::read(self::witness($feature))) {
                if ($required) { throw new RuntimeException('migration_incomplete'); }
                return array();
            }
            return self::decode(self::read(self::target($feature)));
        } catch ( Throwable $e ) {
            if ($required) { throw $e; }
            return array();
        }
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
        return __('This feature is on hold and is not active. Its saved settings are preserved while Loyalty updates the settings automatically. Check WooCommerce logs if processing cannot finish.', 'loyalty-for-woocommerce');
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
            self::$transaction=false;
            self::$owner=null;
            // Release only the original connection's lock, including after wpdb reconnects.
            try { $result=mysqli_query($db,$wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock)); if($result instanceof mysqli_result){mysqli_free_result($result);} } catch(Throwable $e) {}
        }
    }
    /** Only the original migration transaction requires current locking reads. */
    public static function transaction_active() {
        if(self::$transaction){self::assert_owner();}
        return self::$transaction;
    }
    /** Rebuild the interactive actor after serialization; the request's WP_User may be stale. */
    private static function interactive_actor($actor=null) {
        if(null===$actor){$actor=get_current_user_id();}
        if(!is_int($actor)||$actor<1||get_current_user_id()!==$actor){throw new RuntimeException('migration_resolution_denied');}
        if(!self::actor_can_manage($actor)){throw new RuntimeException('migration_resolution_denied');}
    }
    private static function actor_can_manage($actor) {
        global $wpdb;
        if(null===self::$owner){return self::locked(static function()use($actor){return self::actor_can_manage($actor);});}
        // Current locking reads also bypass an already established InnoDB snapshot.
        $read=static function($sql){$result=self::query($sql);if(!($result instanceof mysqli_result)){throw new RuntimeException('migration_storage_unavailable');}try{return mysqli_fetch_all($result,MYSQLI_ASSOC);}finally{mysqli_free_result($result);}};
        $role_rows=$read($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s FOR UPDATE",$wpdb->prefix.'user_roles'));
        $users=$read($wpdb->prepare("SELECT ID FROM {$wpdb->users} WHERE ID=%d FOR UPDATE",$actor));
        $caps_rows=$read($wpdb->prepare("SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id=%d AND meta_key=%s ORDER BY umeta_id FOR UPDATE",$actor,$wpdb->prefix.'capabilities'));
        if(count($users)!==1||count($role_rows)!==1||count($caps_rows)!==1){return false;}
        $definitions=maybe_unserialize($role_rows[0]['option_value']);$caps=maybe_unserialize($caps_rows[0]['meta_value']);
        if(!is_array($definitions)||!is_array($caps)){return false;}
        $roles=wp_roles();$saved=array($roles->roles,$roles->role_objects,$roles->role_names);
        try {
            $roles->roles=$definitions;$roles->role_objects=array();$roles->role_names=array();
            foreach($definitions as $name=>$definition){if(!is_string($name)||!is_array($definition)||!is_array($definition['capabilities']??null)||!is_string($definition['name']??null)){return false;}$roles->role_objects[$name]=new WP_Role($name,$definition['capabilities']);$roles->role_names[$name]=$definition['name'];}
            $user=new WP_User($actor);$user->caps=$caps;$user->get_role_caps();
            return user_can($user,'manage_options');
        } finally {list($roles->roles,$roles->role_objects,$roles->role_names)=$saved;}
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
        $rows = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE (option_name LIKE 'wc\\_loyalty\\_%' AND option_name <> 'wc_loyalty_db_version') OR option_name LIKE 'yowcl\\_%' OR option_name LIKE 'yol\\_%'" . (self::transaction_active() ? ' FOR UPDATE' : '') );
        if ( $wpdb->last_error || ! is_array($rows) ) { throw new RuntimeException('migration_storage_unavailable'); }
        return count($rows) > 0 || class_exists('YOWCL_Loyalty',false);
    }
    /** Authenticated pre-upgrade capture verifies actual historical executable bytes.
     * Ordinary stores without such a capture use the automatic inactive policy.
     */
    public static function capture_legacy( $feature, $nonce ) {
        if ( ! current_user_can('manage_options') || ! wp_verify_nonce($nonce,'loyf_capture_legacy_'.$feature) ) { throw new RuntimeException('migration_capture_denied'); }
        self::target($feature);
        self::locked(function() use($feature) {
            self::interactive_actor();
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
    private static function finish( $feature, $spec, $resolution=false, $interactive=false, $completion=null ) {
        global $wpdb;
        self::assert_owner(); $db=self::$owner['db'];
        $automatic=isset($spec['automatic']);
        $evidence_name=$automatic?self::automatic_name($feature):self::witness($feature).($resolution?'_resolution':'_before');$evidence_raw=null;
        if(!$completion){
            $evidence_raw=self::read($evidence_name);
            if(null===$evidence_raw || serialize(self::decode($evidence_raw))!==serialize($spec)){throw new RuntimeException('migration_malformed_evidence');}
        }
        $engine=$wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$wpdb->options));
        self::assert_owner();
        if ($wpdb->last_error || 'InnoDB'!==$engine || YOWCL_Points_Lock::has_transaction($db)) { throw new RuntimeException('migration_transaction_unavailable'); }
        self::query('START TRANSACTION');self::$transaction=true;$commit_attempted=false;
        try {
            $names=array('active_plugins',self::target($feature),self::witness($feature));
            if ($resolution && 'redemption' === $feature) { $names[]='woocommerce_currency'; $names[]='loyalty_points_using_point'; }
            if(!$completion){$names[]=$evidence_name;$names[]=self::witness($feature).'_resolution';}
            if(isset($spec['consent'])) {
                $names[]=self::witness($feature).'_resolution';
                if('legacy'===$spec['mode']){$names[]='levelup'===$feature?'loyalty_extra_levelup_points_rules':(0===strpos($feature,'email_')?'loyalty_notification_email':('redemption'===$feature?'loyalty_points_using_rules':'loyalty_extra_points_rules'));}
                if('redemption'===$feature){$names[]='woocommerce_currency';$names[]='loyalty_points_using_point';}
            }
            if ($automatic) { $names[]=self::source_name($feature);foreach(array('_before','_resolution','_background','_supersession') as $suffix){$names[]=self::witness($feature).$suffix;}if('redemption'===$feature){$names[]='woocommerce_currency';$names[]='loyalty_points_using_point';} }
            if ($completion) { $names=array_merge($names,$completion['locks']); }
            $names=array_unique($names);sort($names);$locked_evidence=null;
            foreach($names as $name) {
                $result=self::query($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s FOR UPDATE",$name));
                if (!($result instanceof mysqli_result)) { throw new RuntimeException('migration_storage_unavailable'); }
                if($name===$evidence_name){$row=mysqli_fetch_row($result);$locked_evidence=$row?$row[0]:null;}
                mysqli_free_result($result);
            }
            if (null!==self::read(self::witness($feature))) { throw new RuntimeException('migration_witness_changed'); }
            if(!$completion && ($locked_evidence!==$evidence_raw || (!$resolution && !$automatic && null!==self::read(self::witness($feature).'_resolution')))){throw new RuntimeException('migration_malformed_evidence');}
            if (!YOWCL_Free_Core::owns()) { throw new RuntimeException('migration_owner_changed'); }
            if ($resolution && !isset($spec['consent'])) {
                self::interactive_actor();
                if ('redemption' === $feature && !$completion) { self::assert_direct_redemption_context($spec); }
            }
            if (isset($spec['consent'])) {
                self::consent($feature,$spec,$interactive);
            }
            if ($automatic) { self::automatic_current($feature,$spec); }
            if ($completion) { $completion['check'](); }
            self::finish_locked($feature,$spec,$resolution);
            if ($completion) { $completion['audit'](); }
            $commit_attempted=true;self::query('COMMIT');
        } catch(Throwable $e) {
            try { mysqli_query($db,'ROLLBACK'); } catch(Throwable $ignored) {}
            if ($commit_attempted) { throw new RuntimeException('migration_completion_unknown'); }
            throw $e;
        } finally {
            self::$transaction=false;
            // Another request can refill pre-images during the transaction.
            try { self::invalidate($feature); }
            catch(Throwable $e) { if($commit_attempted){throw new RuntimeException('migration_completion_unknown');}throw $e; }
        }
    }
    private static function finish_locked( $feature, $spec, $resolution=false ) {
        $keys=isset($spec['automatic'])?self::validate_automatic($feature,$spec):self::validate_spec($feature,$spec,$resolution);
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
            self::validate_spec($feature,$spec,true);
            if ('redemption' === $feature && !isset($spec['consent'])) {
                self::assert_direct_redemption_context($spec);
            }
            return $spec;
        }
        return self::live_preview($feature,$mode);
    }
    private static function live_preview($feature,$mode) {
        if (!in_array($mode,array('canonical','legacy','disable'),true)) { throw new RuntimeException('migration_resolution_invalid'); }
        $current=self::target_settings($feature);
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
        if ('redemption'===$feature) {
            // Validate the complete proposed owned terms, including retained legacy fields.
            $terms=array_replace(self::decode($raw),$spec['patch']);
            foreach(array('points','amount','min_points','max_points','min_cart') as $key){
                if(!array_key_exists($key,$terms)){throw new RuntimeException('migration_legacy_source_missing');}
                self::points($terms[$key]);
            }
            $spec['context']=array('currency'=>get_woocommerce_currency(),'currency_option'=>self::read('woocommerce_currency'),'enabled'=>self::read('loyalty_points_using_point'));
        }
        self::validate_spec($feature,$spec,true); return $spec;
    }
    /** Bind a direct redemption confirmation to the exact currency and enablement shown. */
    private static function assert_direct_redemption_context($spec) {
        if (!is_array($spec['context'] ?? null) || array_keys($spec['context']) !== array('currency','currency_option','enabled')) {
            throw new RuntimeException('migration_resolution_stale');
        }
        $current=array(
            'currency'=>get_woocommerce_currency(),
            'currency_option'=>self::read('woocommerce_currency'),
            'enabled'=>self::read('loyalty_points_using_point')
        );
        if ($spec['context'] !== $current) { throw new RuntimeException('migration_resolution_stale'); }
        return $current;
    }
    /** A fresh direct review binds immutable evidence and currently displayed storage. */
    public static function resolution_fingerprint($feature,$spec) {
        if (!isset($spec['consent'])) {
            if ('redemption' === $feature) {
                return hash('sha256',serialize(array('spec'=>$spec,'context'=>self::assert_direct_redemption_context($spec))));
            }
            return hash('sha256',serialize($spec));
        }
        $live=array('intent'=>$spec,'target'=>self::read(self::target($feature)));
        if ('legacy'===($spec['mode']??null)) { $live['source']=self::specification($feature)['source']; }
        if ('redemption'===$feature) { $live['context']=array(get_woocommerce_currency(),self::read('woocommerce_currency'),self::read('loyalty_points_using_point')); }
        return hash('sha256',serialize($live));
    }
    private static function source_name($feature) {
        return 'levelup'===$feature?'loyalty_extra_levelup_points_rules':(0===strpos($feature,'email_')?'loyalty_notification_email':('redemption'===$feature?'loyalty_points_using_rules':'loyalty_extra_points_rules'));
    }
    private static function replacement_old($feature) {
        self::target_settings($feature);
        if (null!==self::read(self::witness($feature)) || null!==self::read(self::witness($feature).'_supersession') || null!==self::read(self::witness($feature).'_before')) { throw new RuntimeException('migration_replacement_unavailable'); }
        $raw=self::read(self::witness($feature).'_resolution');
        if (null===$raw) { throw new RuntimeException('migration_replacement_unavailable'); }
        $old=self::decode($raw);self::validate_spec($feature,$old,true);$c=self::consent_structure($feature,$old);
        if (array_diff(array_keys($old),array('source','target','patch','before','mode','consent','context')) || (!is_string($old['source']) && null!==$old['source']) || (!is_string($old['before']) && null!==$old['before'])) { throw new RuntimeException('migration_malformed_evidence'); }
        $consent_keys=array('version','feature','site','actor','issued','expires','batch','set','fingerprint');
        if('redemption'===$feature){$consent_keys=array_merge($consent_keys,array('currency','enabled'));}
        if(array_diff(array_keys($c),$consent_keys) || array_diff($consent_keys,array_keys($c))){throw new RuntimeException('migration_malformed_evidence');}
        if('legacy'===$old['mode']) {
            $frozen=self::specification($feature,$old['source'],true);
            if(null===$old['source'] || serialize($frozen['patch'])!==serialize($old['patch'])){throw new RuntimeException('migration_malformed_evidence');}
        } else {
            if($old['source']!==$old['before']){throw new RuntimeException('migration_malformed_evidence');}
            $expected=array();$old_terms=self::decode($old['before']);$old_keys=self::keys($feature);
            if('redemption'===$feature){$old_keys=array_merge($old_keys,array('points','amount'));}
            foreach($old_keys as$key){if('canonical'===$old['mode']){if(!array_key_exists($key,$old_terms)){throw new RuntimeException('migration_malformed_evidence');}$expected[$key]=$old_terms[$key];}else{$expected[$key]=('enabled'===$key || substr($key,-8)==='_enabled')?'no':('levelup_points'===$key?array():0);}}
            if(serialize($expected)!==serialize($old['patch'])){throw new RuntimeException('migration_malformed_evidence');}
        }
        if ('redemption'===$feature && (!is_array($old['context']??null) || !array_key_exists('currency_option',$old['context']) || ($old['context']['currency']??null)!==($c['currency']??null) || !array_key_exists('enabled',$old['context']) || $old['context']['enabled']!==($c['enabled']??null))) { throw new RuntimeException('migration_malformed_evidence'); }
        $keys=self::validate_spec($feature,$old,true);$current=self::read(self::target($feature));
        $post=self::encode(array_replace(self::decode($old['before']),$old['patch']),$old['before']);
        $drift=self::owned($current,$keys)!==self::owned($old['before'],$keys) && self::owned($current,$keys)!==self::owned($post,$keys);
        if ('legacy'===$old['mode']) {
            // Source drift uses only inputs owned by this feature, never shared siblings.
            $now=self::read(self::source_name($feature));$a=self::decode($old['source']);$b=self::decode($now);
            $source_keys='levelup'===$feature?array_keys($a+$b):('redemption'===$feature?array('points','amount','min_points','max_points','min_cart'):(0===strpos($feature,'email_')?array('email_level'===$feature?'level_update':'points_update'):array($feature.'_points')));
            $drift=$drift || self::owned($old['source'],$source_keys)!==self::owned($now,$source_keys);
        }
        if ('redemption'===$feature) { $drift=$drift || $c['currency']!==get_woocommerce_currency() || $old['context']['currency_option']!==self::read('woocommerce_currency') || $c['enabled']!==self::read('loyalty_points_using_point'); }
        if (!$drift) { throw new RuntimeException('migration_replacement_unavailable'); }
        return array($raw,$old);
    }
    /** Read-only proposal. No successor intent or future worker permission is created. */
    public static function replacement_preview($feature,$mode) {
        if (!current_user_can('manage_options') || !YOWCL_Free_Core::owns()) { throw new RuntimeException('migration_resolution_denied'); }
        list($raw,$old)=self::replacement_old($feature);$spec=self::live_preview($feature,$mode);
        if ('redemption'===$feature) { if(!in_array($spec['context']['enabled'],array(null,'yes','no'),true) || !is_string($spec['context']['currency']) || !preg_match('/^[A-Za-z0-9_-]{1,16}$/D',$spec['context']['currency']) || !is_string($spec['context']['currency_option']) || !preg_match('/^[A-Za-z0-9_-]{1,16}$/D',$spec['context']['currency_option'])){throw new RuntimeException('migration_replacement_unavailable');} $spec['context']['locale']=get_locale();$spec['context']['user_locale']=get_user_locale(); }
        // The exact original row and current proposal are both bound, including unknown sibling bytes.
        $fingerprint=hash('sha256',serialize(array('pending'=>$raw,'spec'=>$spec,'witness'=>null,'audit'=>null,'before'=>null)));
        return array('spec'=>$spec,'fingerprint'=>$fingerprint,'old'=>$old,'digest'=>hash('sha256',$raw));
    }
    public static function replace_pending($feature,$mode,$fingerprint,$nonce) {
        if (!in_array($feature,self::features(),true) || !in_array($mode,array('canonical','legacy','disable'),true) || !is_string($fingerprint) || !preg_match('/^[a-f0-9]{64}$/D',$fingerprint) || !current_user_can('manage_options') || !YOWCL_Free_Core::owns() || !wp_verify_nonce($nonce,'loyf_replace_'.$feature)) { throw new RuntimeException('migration_resolution_denied'); }
        $committed=false;
        try { return self::locked(function()use($feature,$mode,$fingerprint,&$committed){
            self::interactive_actor();
            if ('1'===self::read(self::witness($feature))) { self::target_settings($feature);return 'already_confirmed'; }
            $proposal=self::replacement_preview($feature,$mode);
            if (!hash_equals($proposal['fingerprint'],$fingerprint)) { throw new RuntimeException('migration_resolution_stale'); }
            $spec=$proposal['spec'];$actor=get_current_user_id();
            $locks=array('active_plugins',self::witness($feature).'_resolution',self::witness($feature).'_before',self::witness($feature).'_supersession');
            if ('legacy'===$mode || 'legacy'===$proposal['old']['mode']) { $locks[]=self::source_name($feature); }
            if ('redemption'===$feature) { $locks[]= 'woocommerce_currency';$locks[]='loyalty_points_using_point'; }
            $completion=array('locks'=>$locks,'check'=>function()use($feature,$mode,$fingerprint,$actor){
                self::interactive_actor($actor);
                if (!YOWCL_Free_Core::owns()) { throw new RuntimeException('migration_resolution_denied'); }
                $fresh=self::replacement_preview($feature,$mode);
                if (!hash_equals($fingerprint,$fresh['fingerprint'])) { throw new RuntimeException('migration_resolution_stale'); }
            },'audit'=>function()use($feature,$mode,$proposal,$actor,$spec){
                $terms=self::scoped_terms($feature,array_replace(self::decode($spec['before']),$spec['patch']));
                if(isset($terms['levelup_points'])) { $map=array();foreach($terms['levelup_points'] as $role=>$rule){$map[$role]=array('awarded'=>$rule['awarded']??0);}$terms['levelup_points']=$map; }
                $c=$proposal['old']['consent'];
                $audit=array('version'=>27,'feature'=>$feature,'original_digest'=>$proposal['digest'],'original_identity'=>array('batch'=>$c['batch'],'set'=>$c['set'],'actor'=>$c['actor']),'mode'=>$mode,'terms'=>$terms,'actor'=>$actor,'completed'=>time(),'reviewed'=>$proposal['fingerprint'],'readback'=>hash('sha256',self::owned(self::read(self::target($feature)),self::validate_spec($feature,$spec,true))),'context'=>$spec['context']??array());
                self::put(self::witness($feature).'_supersession',serialize($audit),null);
            });
            self::finish($feature,$spec,true,false,$completion);$committed=true;
            return 'confirmed';
        }); } catch(Throwable $e) { if($committed){throw new RuntimeException('migration_completion_unknown');}throw $e; }
    }
    public static function resolve( $feature, $mode, $fingerprint, $nonce ) {
        if (!current_user_can('manage_options') || !YOWCL_Free_Core::owns() || !wp_verify_nonce($nonce,'loyf_resolve_'.$feature)) { throw new RuntimeException('migration_resolution_denied'); }
        $committed=false;
        try { return self::locked(function() use($feature,$mode,$fingerprint,&$committed) {
            self::interactive_actor();
            self::target_settings($feature);
            if (self::ready($feature)) { return 'already_confirmed'; }
            if (null!==self::read(self::witness($feature))) { throw new RuntimeException('migration_malformed_witness'); }
            $spec=self::preview($feature,$mode);
            if (!hash_equals(self::resolution_fingerprint($feature,$spec),(string)$fingerprint)) { throw new RuntimeException('migration_resolution_stale'); }
            $name=self::witness($feature).'_resolution';
            self::put($name,serialize($spec),self::read($name));
            // This POST grants only same-request authority; the original pending row stays unchanged.
            self::finish($feature,$spec,true,isset($spec['consent']));$committed=true;
            return 'confirmed';
        }); } catch(Throwable $e) { if($committed){throw new RuntimeException('migration_completion_unknown');}throw $e; }
    }
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
    /** F1: only the request with actual conservative freshness proof may mutate at boot. */
    public static function boot() {
        if (YOWCL_Free_Onboarding::initial_proof()) { self::run(); }
        else {
            // Keep completed-policy cache handover without any synchronous conversion.
            try { self::locked(function(){foreach(self::features() as $feature){if(self::ready($feature)){self::invalidate($feature);}}}); }
            catch(Throwable $e) { foreach(self::features() as $feature){self::invalidate($feature);} }
        }
        add_action(self::HOOK,array(__CLASS__,'worker'),10,2);
        add_action('action_scheduler_init',array(__CLASS__,'schedule'));
        add_action('init',array(__CLASS__,'schedule'),20);
        add_action('wp_ajax_loyf_dismiss_migration',array(__CLASS__,'dismiss'));
    }
    private static function scheduler() {
        return class_exists('WooCommerce',false) && class_exists('ActionScheduler',false) && ActionScheduler::is_initialized() && function_exists('as_schedule_single_action') && function_exists('as_get_scheduled_actions');
    }
    private static function work_name($feature) { return self::witness($feature).'_automatic_background'; }
    private static function work($feature,$historical=false) {
        $raw=self::read($historical?self::witness($feature).'_background':self::work_name($feature));
        if (null===$raw) { return null; }
        $w=self::decode($raw);
        if (!is_string($w['run']??null) || !preg_match('/^[a-f0-9-]{36}$/D',$w['run']) || !is_string($w['identity']??null) || !preg_match('/^[a-f0-9]{64}$/D',$w['identity']) || !is_int($w['attempts']??null) || $w['attempts']<0 || $w['attempts']>3 || !is_int($w['updated']??null) || !is_string($w['error']??null)) { throw new RuntimeException('migration_malformed_work'); }
        return $w;
    }
    private static function store_work($feature,$work) {
        $name=self::work_name($feature); self::put($name,serialize($work),self::read($name));
    }
    /** Reuse the existing spec; this never infers provenance or writes evidence. */
    private static function authorized_spec($feature) {
        self::target_settings($feature);
        $witness=self::read(self::witness($feature));
        if ('1'===$witness) { return null; }
        if (null!==$witness) { throw new RuntimeException('migration_malformed_witness'); }
        $pending=self::read(self::witness($feature).'_resolution');
        if (null!==$pending) {
            $spec=self::decode($pending); self::validate_spec($feature,$spec,true);
            self::consent($feature,$spec);
        } else {
            if (self::premium_history()) { throw new RuntimeException('migration_origin_unresolved'); }
            $raw=self::read(self::witness($feature).'_before');
            if (null===$raw) { throw new RuntimeException('migration_origin_unresolved'); }
            $spec=self::decode($raw); self::validate_spec($feature,$spec);
            if ('legacy:3c1aac240df03101561b856cddb603b1501fa41b'!==($spec['origin']??null)) { throw new RuntimeException('migration_origin_unresolved'); }
            $frozen=self::specification($feature,$spec['source'],true);
            if (serialize($frozen['patch'])!==serialize($spec['patch']) || $frozen['target']!==$spec['target']) { throw new RuntimeException('migration_malformed_evidence'); }
        }
        $keys=self::keys($feature);
        if (null!==$pending && 'redemption'===$feature) { $keys=array_merge($keys,array('points','amount')); }
        $raw=self::read($spec['target']);
        $post=self::encode(array_replace(self::decode($spec['before']),$spec['patch']),$spec['before']);
        if (self::owned($raw,$keys)!==self::owned($spec['before'],$keys) && self::owned($raw,$keys)!==self::owned($post,$keys)) { throw new RuntimeException('migration_target_changed'); }
        return $spec;
    }
    private static function automatic_name($feature) { return self::witness($feature).'_automatic'; }
    /** Immutable observations, never renewed consent or an assertion about the last writer. */
    private static function retained($feature) {
        $result=array();
        foreach(array('_before','_resolution','_background','_supersession') as $suffix) {
            $raw=self::read(self::witness($feature).$suffix);
            $result[$suffix]=null===$raw?null:hash('sha256',$raw);
        }
        return $result;
    }
    private static function validate_automatic($feature,$spec) {
        global $wpdb;
        if (!is_array($spec) || 29!==($spec['automatic']??null) || ($spec['feature']??null)!==$feature || ($spec['site']??null)!==$wpdb->options || ($spec['target']??null)!==self::target($feature) || !array_key_exists('before',$spec) || !array_key_exists('source',$spec) || !is_array($spec['retained']??null) || !is_bool($spec['paused']??null)) { throw new RuntimeException('migration_malformed_evidence'); }
        $flag='redemption'===$feature ? null : (0===strpos($feature,'email_')?'enabled':$feature.'_enabled');
        if (($spec['patch']??null)!==($flag?array($flag=>'no'):array())) { throw new RuntimeException('migration_malformed_evidence'); }
        self::decode($spec['before']);self::decode($spec['source']);
        if (array_keys($spec['retained'])!==array('_before','_resolution','_background','_supersession')) { throw new RuntimeException('migration_malformed_evidence'); }
        foreach($spec['retained'] as $digest) { if (null!==$digest && (!is_string($digest)||!preg_match('/^[a-f0-9]{64}$/D',$digest))) { throw new RuntimeException('migration_malformed_evidence'); } }
        return 'redemption'===$feature?array('points','amount','min_points','max_points','min_cart'):self::keys($feature);
    }
    /** Select only existing authority; otherwise admit the approved non-destructive inactive policy. */
    private static function automatic_spec($feature,$admit=false) {
        self::target_settings($feature);
        $witness=self::read(self::witness($feature));
        if ('1'===$witness) { return null; }
        if (null!==$witness) { throw new RuntimeException('migration_malformed_witness'); }
        $raw=self::read(self::automatic_name($feature));
        if (null!==$raw) { $spec=self::decode($raw);self::validate_automatic($feature,$spec);return $spec; }
        if(null!==self::read(self::witness($feature).'_before') && null!==self::read(self::witness($feature).'_resolution')) { throw new RuntimeException('migration_malformed_evidence'); }
        try { return self::authorized_spec($feature); }
        catch(Throwable $e) {
            if (!in_array($e->getMessage(),array('migration_origin_unresolved','migration_resolution_pending','migration_consent_expired','migration_consent_revoked','migration_target_changed','migration_source_changed'),true)) { throw $e; }
        }
        if (self::premium_history()) { throw new RuntimeException('migration_origin_unresolved'); }
        // Corrupt records are not ambiguity and must never become successful automatic repair.
        foreach(array('_before','_resolution') as $suffix) {
            $old=self::read(self::witness($feature).$suffix);
            if (null!==$old) {
                $old=self::decode($old);self::validate_spec($feature,$old,'_resolution'===$suffix);
                if ('_resolution'===$suffix && isset($old['consent'])) { self::consent_structure($feature,$old); }
            }
        }
        if (null!==self::read(self::witness($feature).'_supersession')) { throw new RuntimeException('migration_malformed_evidence'); }
        self::work($feature,true); // Validate but never replace a former operational record.
        $legacy=self::specification($feature); // Validate readable legacy fields without adopting them.
        $before=self::read(self::target($feature));$current=self::decode($before);
        $keys='redemption'===$feature?array('points','amount','min_points','max_points','min_cart'):self::keys($feature);
        foreach($keys as $key) {
            if (!array_key_exists($key,$current)) { continue; }
            if ('enabled'===$key || substr($key,-8)==='_enabled') {
                if (!in_array($current[$key],array('yes','no'),true)) { throw new RuntimeException('migration_malformed_evidence'); }
            } elseif ('levelup_points'===$key) {
                if (!is_array($current[$key])) { throw new RuntimeException('migration_malformed_role_map'); }
                foreach($current[$key] as $role=>$rule) {
                    if (!is_string($role)||sanitize_key($role)!==$role||!is_array($rule)) { throw new RuntimeException('migration_malformed_role_map'); }
                    self::points($rule['awarded']??0);
                }
            } else { self::points($current[$key]); }
        }
        $flag='redemption'===$feature?null:(0===strpos($feature,'email_')?'enabled':$feature.'_enabled');
        $paused=false;
        if ('redemption'===$feature) { $paused=(float)($current['points']??0)>0 && (float)($current['amount']??0)>0; }
        elseif ('levelup'===$feature) { foreach(array($current['levelup_points']??array(),self::decode($legacy['source'])) as $map){foreach($map as $rule){$paused=$paused || (float)($rule['awarded']??0)>0;}} }
        elseif (0===strpos($feature,'email_')) { $paused='yes'===($current['enabled']??'no') || 'yes'===$legacy['patch']['enabled']; }
        else { $paused=(float)($current[$feature.'_points']??0)>0 || (float)($legacy['patch'][$feature.'_points']??0)>0; }
        $spec=array('automatic'=>29,'feature'=>$feature,'site'=>$GLOBALS['wpdb']->options,'source'=>$legacy['source'],'target'=>self::target($feature),'before'=>$before,'patch'=>$flag?array($flag=>'no'):array(),'retained'=>self::retained($feature),'paused'=>$paused);
        if ('redemption'===$feature) { $spec['context']=array('currency'=>get_woocommerce_currency(),'currency_option'=>self::read('woocommerce_currency'),'enabled'=>self::read('loyalty_points_using_point')); }
        self::validate_automatic($feature,$spec);
        if ($admit) { self::put(self::automatic_name($feature),serialize($spec),null); }
        return $spec;
    }
    private static function automatic_current($feature,$spec) {
        self::validate_automatic($feature,$spec);
        if (self::read(self::automatic_name($feature))!==serialize($spec) || self::retained($feature)!==$spec['retained']) { throw new RuntimeException('migration_malformed_evidence'); }
        if (self::premium_history()) { throw new RuntimeException('migration_origin_unresolved'); }
        if(self::source_name($feature)!==self::target($feature)){$now=self::read(self::source_name($feature));$source_keys='levelup'===$feature?array_keys(self::decode($spec['source'])+self::decode($now)):(0===strpos($feature,'email_')?array('email_level'===$feature?'level_update':'points_update'):array($feature.'_points'));if(self::owned($now,$source_keys)!==self::owned($spec['source'],$source_keys)){throw new RuntimeException('migration_source_changed');}}
        if ('redemption'===$feature) { self::assert_direct_redemption_context($spec); }
    }
    /** The guard applies only to this task's inactive decision, never to unrelated witnessed policies. */
    public static function new_redemption_allowed() {
        try {
            $raw=self::read(self::automatic_name('redemption'));
            if (null===$raw) { return true; }
            self::validate_automatic('redemption',self::decode($raw));
            return self::ready('redemption') && 'yes'===self::read(self::automatic_name('redemption').'_enabled');
        } catch(Throwable $e) { return false; }
    }
    /** Ordinary validated Settings saves are future configuration, not migration permission. */
    public static function configure_redemption($enabled) {
        if(!current_user_can('manage_options') || !is_string($_POST['using_point_rules_nonce']??null) || !wp_verify_nonce(wp_unslash($_POST['using_point_rules_nonce']),'save_using_point_rules') || $enabled!==isset($_POST['loyalty_points_using_point'])) { throw new RuntimeException('migration_resolution_denied'); }
        self::locked(function()use($enabled){
            global $wpdb;
            $raw=self::read(self::automatic_name('redemption'));
            if(null===$raw){return;}
            self::validate_automatic('redemption',self::decode($raw));
            $db=self::$owner['db'];$committing=false;
            $engine=$wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$wpdb->options));self::assert_owner();
            if($wpdb->last_error || 'InnoDB'!==$engine || YOWCL_Points_Lock::has_transaction($db)){throw new RuntimeException('migration_transaction_unavailable');}
            self::query('START TRANSACTION');self::$transaction=true;
            try {
                self::interactive_actor();
                if(!YOWCL_Free_Core::owns() || !self::ready('redemption')){throw new RuntimeException('migration_incomplete');}
                if(self::read(self::automatic_name('redemption'))!==$raw){throw new RuntimeException('migration_malformed_evidence');}
                $terms=self::canonical('redemption',true);
                if($enabled){foreach(array('points','amount')as$key){if((float)self::points($terms[$key]??0)<=0){throw new RuntimeException('migration_malformed_points');}}}
                if(('yes'===self::read('loyalty_points_using_point'))!==$enabled){throw new RuntimeException('migration_target_changed');}
                $name=self::automatic_name('redemption').'_enabled';self::put($name,$enabled?'yes':'no',self::read($name));
                $committing=true;self::query('COMMIT');
            } catch(Throwable $e){try{mysqli_query($db,'ROLLBACK');}catch(Throwable $ignored){}if($committing){throw new RuntimeException('migration_completion_unknown');}throw $e;}
            finally{self::$transaction=false;}
        });
    }
    /** Only newly admitted, immutable LOYF-27 consent can continue in a worker. */
    private static function consent_structure($feature,$spec) {
        global $wpdb;
        $c=$spec['consent']??null;
        if (!is_array($c) || 27!==($c['version']??null) || ($c['feature']??null)!==$feature || ($c['site']??null)!==$wpdb->options || !is_int($c['actor']??null) || $c['actor']<1 || !is_int($c['issued']??null) || !is_int($c['expires']??null) || $c['expires']<=$c['issued'] || $c['expires']-$c['issued']>86400 || !is_string($c['batch']??null) || !preg_match('/^[a-f0-9-]{36}$/D',$c['batch']) || !is_string($c['set']??null) || !preg_match('/^[a-f0-9]{64}$/D',$c['set']) || !in_array($spec['mode']??null,array('canonical','legacy','disable'),true)) { throw new RuntimeException('migration_resolution_pending'); }
        $base=$spec;unset($base['consent']);
        if (!is_string($c['fingerprint']??null) || !hash_equals(hash('sha256',serialize($base)),$c['fingerprint'])) { throw new RuntimeException('migration_malformed_evidence'); }
        if ($c['issued']>time()) { throw new RuntimeException('migration_malformed_evidence'); }
        return $c;
    }
    private static function consent($feature,$spec,$interactive=false) {
        global $wpdb;
        $c=self::consent_structure($feature,$spec);
        if ($c['issued']>time() || (!$interactive && $c['expires']<=time())) { throw new RuntimeException('migration_consent_expired'); }
        if ($interactive) { self::interactive_actor(); }
        if (!$interactive && !self::actor_can_manage($c['actor'])) { throw new RuntimeException('migration_consent_revoked'); }
        if ('redemption'===$feature && (($c['currency']??null)!==get_woocommerce_currency() || ($spec['context']['currency_option']??null)!==self::read('woocommerce_currency') || !array_key_exists('enabled',$c) || $c['enabled']!==self::read('loyalty_points_using_point'))) { throw new RuntimeException('migration_target_changed'); }
        if ('legacy'===$spec['mode']) {
            // Compare the legacy inputs actually used by this feature, not shared siblings.
            $current=self::specification($feature);$old=self::decode($spec['source']);$now=self::decode($current['source']);
            $keys='levelup'===$feature ? array_keys($old+$now) : ('redemption'===$feature ? array('points','amount','min_points','max_points','min_cart') : (0===strpos($feature,'email_') ? array('email_level'===$feature?'level_update':'points_update') : array($feature.'_points')));
            if (self::owned($current['source'],$keys)!==self::owned($spec['source'],$keys)) { throw new RuntimeException('migration_source_changed'); }
        }
    }
    public static function schedule() {
        if (!self::scheduler() || !YOWCL_Free_Core::owns()) { return; }
        foreach(self::features() as $feature) {
            try {
                self::locked(function()use($feature){
                    $spec=self::automatic_spec($feature,true);
                    if (null===$spec) { return; }
                    $id=hash('sha256',serialize($spec));$work=self::work($feature);
                    // Existing operational records never restart an exhausted or abandoned budget on boot.
                    if (null!==$work) { if($work['identity']!==$id){if(!isset($spec['automatic'])){throw new RuntimeException('migration_target_changed');}$work['identity']=$id;$work['run']=wp_generate_uuid4();$work['updated']=time();$work['error']='';self::store_work($feature,$work);if($work['attempts']<3){self::enqueue($feature,$work);}return;} if($work['attempts']<3 && (!$work['error'] || self::transient($work['error'])) && time()-$work['updated']>=900){$work['updated']=time();try{self::enqueue($feature,$work);}catch(Throwable $e){$work['attempts']++;$work['error']=$e->getMessage();}self::store_work($feature,$work);}return; }
                    $old=self::work($feature,true);
                    $work=array('run'=>wp_generate_uuid4(),'identity'=>$id,'attempts'=>$old?$old['attempts']:0,'updated'=>time(),'error'=>'');
                    self::store_work($feature,$work);
                    if($work['attempts']>=3){return;}
                    try { self::enqueue($feature,$work); } catch(Throwable $e){$work['attempts']++;$work['error']=$e->getMessage();self::store_work($feature,$work);throw $e;}
                });
            } catch(Throwable $e) { self::$errors[$feature]=$e->getMessage(); }
        }
    }
    private static function enqueue($feature,$work,$delay=0) {
        if (!self::scheduler() || !YOWCL_Free_Core::owns()) { throw new RuntimeException('migration_scheduler_unavailable'); }
        $args=array($feature,$work['run']);
        if (as_has_scheduled_action(self::HOOK,$args,self::GROUP)) { return; }
        if (!as_schedule_single_action(time()+$delay,self::HOOK,$args,self::GROUP,true)) { throw new RuntimeException('migration_schedule_failed'); }
    }
    private static function transient($code) {
        return in_array($code,array('migration_lock_unavailable','migration_ownership_lost','migration_storage_unavailable','migration_write_failed','migration_readback_failed','migration_semantics_readback_failed','migration_transaction_unavailable','migration_completion_unknown','migration_schedule_failed','migration_owner_changed'),true);
    }
    public static function worker($feature,$run) {
        if (!in_array($feature,self::features(),true) || !is_string($run) || !self::scheduler() || !YOWCL_Free_Core::owns()) { return; }
        $retry=null;
        try {
            self::locked(function()use($feature,$run,&$retry){
                $work=self::work($feature);
                if (!$work || !hash_equals($work['run'],$run) || $work['attempts']>=3) { return; }
                if ('1'===self::read(self::witness($feature))) { return; }
                $work['attempts']++;$work['updated']=time();$work['error']='';self::store_work($feature,$work);
                try {
                    if (!YOWCL_Free_Core::owns()) { throw new RuntimeException('migration_owner_changed'); }
                    $spec=self::automatic_spec($feature);
                    if (null===$spec) { return; }
                    if (!hash_equals($work['identity'],hash('sha256',serialize($spec)))) { throw new RuntimeException('migration_target_changed'); }
                    if (isset($spec['automatic'])) { self::finish($feature,$spec); }
                    elseif (isset($spec['consent'])) { self::finish($feature,$spec,true); }
                    else { self::migrate($feature); }
                } catch(Throwable $e) {
                    $work['error']=$e->getMessage();$work['updated']=time();self::store_work($feature,$work);
                    if (self::transient($work['error']) && $work['attempts']<3) { $retry=$work; }
                }
            });
        } catch(Throwable $e) {
            // Lost ownership cannot safely update retry evidence. Queue/status exposes interrupted work.
            self::$errors[$feature]=$e->getMessage();
        }
        if ($retry) {
            // A successor shares this run; the persisted attempt budget is rechecked under ownership.
            $args=array($feature,$retry['run']);
            if (!as_schedule_single_action(time()+60,self::HOOK,$args,self::GROUP,false)) { self::$errors[$feature]='migration_schedule_failed'; }
        }
    }
    private static function batch_preview($feature,$mode) {
        if (!in_array($feature,self::features(),true) || null!==self::read(self::witness($feature)) || null!==self::read(self::witness($feature).'_resolution')) { throw new RuntimeException('migration_resolution_pending'); }
        $spec=self::preview($feature,$mode);
        if ('redemption'===$feature) {
            $v=self::decode($spec['before']);
            foreach(array('points','amount') as $key) { if (!array_key_exists($key,$v)) { throw new RuntimeException('migration_canonical_incomplete'); } self::points($v[$key]); }
        }
        return $spec;
    }
    /** One POST atomically admits a vector of per-feature intents; it never completes them here. */
    public static function admit($choices,$batch,$nonce) {
        global $wpdb;
        if (!current_user_can('manage_options') || !YOWCL_Free_Core::owns() || !wp_verify_nonce($nonce,'loyf_confirm_migrations') || !is_string($batch) || !preg_match('/^[a-f0-9-]{36}$/D',$batch) || !is_array($choices) || !$choices || count($choices)>8 || array_diff(array_keys($choices),self::features())) { throw new RuntimeException('migration_resolution_denied'); }
        $ordered=array();foreach(self::features() as $feature){if(isset($choices[$feature])){$ordered[$feature]=$choices[$feature];}}
        $set=hash('sha256',serialize($ordered));$actor=get_current_user_id();
        $commit_attempted=false;
        try { self::locked(function()use($ordered,$batch,$set,$actor,$wpdb,&$commit_attempted){
            self::interactive_actor($actor);
            $specs=array();$duplicates=0;$issued=time();
            foreach($ordered as $feature=>$choice) {
                if (!is_array($choice) || array_keys($choice)!==array('mode','fingerprint') || !is_string($choice['mode']) || !in_array($choice['mode'],array('canonical','legacy','disable'),true) || !is_string($choice['fingerprint']) || !preg_match('/^[a-f0-9]{64}$/D',$choice['fingerprint'])) { throw new RuntimeException('migration_resolution_invalid'); }
                $pending=self::read(self::witness($feature).'_resolution');
                if (null!==$pending) {
                    $old=self::decode($pending);$c=$old['consent']??array();
                    if (($c['batch']??null)===$batch && ($c['set']??null)===$set && ($c['actor']??null)===$actor && ($c['site']??null)===$wpdb->options && ($c['fingerprint']??null)===$choice['fingerprint'] && ($old['mode']??null)===$choice['mode']) { $duplicates++;continue; }
                    throw new RuntimeException('migration_resolution_pending');
                }
                $spec=self::batch_preview($feature,$choice['mode']);
                if (!hash_equals(hash('sha256',serialize($spec)),$choice['fingerprint'])) { throw new RuntimeException('migration_resolution_stale'); }
                $spec['consent']=array('version'=>27,'feature'=>$feature,'site'=>$wpdb->options,'actor'=>$actor,'issued'=>$issued,'expires'=>$issued+86400,'batch'=>$batch,'set'=>$set,'fingerprint'=>$choice['fingerprint']);
                if ('redemption'===$feature) { $spec['consent']['currency']=$spec['context']['currency'];$spec['consent']['enabled']=$spec['context']['enabled']; }
                $specs[$feature]=$spec;
            }
            if ($duplicates) { if ($duplicates===count($ordered)) { return; } throw new RuntimeException('migration_resolution_pending'); }
            $engine=$wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$wpdb->options));self::assert_owner();$db=self::$owner['db'];
            if ($wpdb->last_error || 'InnoDB'!==$engine || YOWCL_Points_Lock::has_transaction($db)) { throw new RuntimeException('migration_transaction_unavailable'); }
            self::query('START TRANSACTION');self::$transaction=true;
            try {
                $names=array('active_plugins');
                foreach($specs as $feature=>$spec) {
                    $names[]=self::target($feature);$names[]=self::witness($feature);$names[]=self::witness($feature).'_resolution';
                    if ('legacy'===$spec['mode']) { $names[]='levelup'===$feature ? 'loyalty_extra_levelup_points_rules' : (0===strpos($feature,'email_') ? 'loyalty_notification_email' : ('redemption'===$feature ? 'loyalty_points_using_rules' : 'loyalty_extra_points_rules')); }
                    if ('redemption'===$feature) { $names[]='loyalty_points_using_point';$names[]='woocommerce_currency'; }
                }
                $names=array_unique($names);sort($names);
                foreach($names as $name) { $r=self::query($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s FOR UPDATE",$name));if($r instanceof mysqli_result){mysqli_free_result($r);} }
                self::interactive_actor($actor);
                if (!YOWCL_Free_Core::owns()) { throw new RuntimeException('migration_resolution_denied'); }
                foreach($specs as $feature=>$spec) {
                    $fresh=self::batch_preview($feature,$spec['mode']);
                    if (!hash_equals($spec['consent']['fingerprint'],hash('sha256',serialize($fresh)))) { throw new RuntimeException('migration_resolution_stale'); }
                    if ('redemption'===$feature && $spec['consent']['enabled']!==self::read('loyalty_points_using_point')) { throw new RuntimeException('migration_resolution_stale'); }
                }
                foreach($specs as $feature=>$spec) {
                    self::put(self::witness($feature).'_resolution',serialize($spec),null);
                }
                $commit_attempted=true;self::query('COMMIT');
            } catch(Throwable $e) { try{mysqli_query($db,'ROLLBACK');}catch(Throwable $ignored){} throw $e; }
            finally { self::$transaction=false;foreach($specs as $feature=>$spec){self::invalidate($feature);wp_cache_delete(self::witness($feature).'_resolution','options');} }
        });
        self::schedule();
        } catch(Throwable $e) { if($commit_attempted){throw new RuntimeException('migration_admission_unknown');}throw $e; }
    }
    public static function status() {
        $result=array('state'=>'completed','confirmed'=>0,'queued'=>0,'running'=>0,'held'=>0,'failed'=>0,'features'=>array(),'runs'=>array(),'paused'=>array());
        foreach(self::features() as $feature) {
            $state='held';$reason='migration_origin_unresolved';
            try {
                if ('confirmed'===self::review_state($feature)) { $state='confirmed';$reason=''; }
                else {
                    $spec=self::automatic_spec($feature);$work=self::work($feature);
                    if (!$work || !hash_equals($work['identity'],hash('sha256',serialize($spec)))) { $reason='migration_not_scheduled'; }
                    elseif (!self::scheduler()) { $reason='migration_scheduler_unavailable'; }
                    else {
                        if ($work['error'] && !self::transient($work['error'])) { $reason=$work['error']; }
                        elseif ($work['attempts']>=3) { $state='failed';$reason='migration_retry_exhausted'; }
                        else {
                            $ids=as_get_scheduled_actions(array('hook'=>self::HOOK,'args'=>array($feature,$work['run']),'group'=>self::GROUP,'status'=>array('pending','in-progress'),'per_page'=>2),'ids');
                            if ($ids && time()-$work['updated']<=900) { $state='queued';$reason='';foreach($ids as $id){if('in-progress'===ActionScheduler::store()->get_status($id)){$state='running';}} }
                            else { $reason='migration_stalled'; }
                        }
                    }
                }
                try{$w=self::work($feature);if($w){$result['runs'][]=$w['run'];}}catch(Throwable $e){if('confirmed'!==$state){throw $e;}}
            } catch(Throwable $e) { $reason=$e->getMessage();if(self::transient($reason)){$state='failed';} }
            if('confirmed'===$state){try{$a=self::read(self::automatic_name($feature));if(null!==$a){$a=self::decode($a);self::validate_automatic($feature,$a);$active='redemption'===$feature?self::new_redemption_allowed():('yes'===(self::canonical($feature,true)[0===strpos($feature,'email_')?'enabled':$feature.'_enabled']??'no'));if($a['paused']&&!$active){$result['paused'][]=$feature;}}}catch(Throwable $e){$state='failed';$reason=$e->getMessage();}}
            $result[$state]++;$result['features'][$feature]=array('state'=>$state,'reason'=>$reason);
        }
        if($result['failed']){$result['state']='failed';}elseif($result['held']){$result['state']='needs_attention';}elseif($result['running']){$result['state']='running';}elseif($result['queued']){$result['state']='queued';}
        return $result;
    }
    private static function notice_token($status) {
        $state=in_array($status['state'],array('queued','running'),true)?'active':$status['state'];
        return hash('sha256','loyf29|'.$state.'|'.serialize(array($status['runs'],$status['paused'])));
    }
    public static function dismiss() {
        if ('POST'!==($_SERVER['REQUEST_METHOD']??'') || !current_user_can('manage_options') || !YOWCL_Free_Core::owns() || !is_string($_POST['_wpnonce']??null) || !wp_verify_nonce(wp_unslash($_POST['_wpnonce']),'loyf_dismiss_migration') || !is_string($_POST['token']??null)) { wp_send_json_error(); }
        $token=self::notice_token(self::status());
        if (!hash_equals($token,wp_unslash($_POST['token']))) { wp_send_json_error(); }
        update_user_meta(get_current_user_id(),'loyf_migration_notice_dismissed',$token);
        if (get_user_meta(get_current_user_id(),'loyf_migration_notice_dismissed',true)!==$token) { wp_send_json_error(); }
        wp_send_json_success();
    }
    public static function run() {
        if ( ! YOWCL_Free_Core::owns() ) { return; }
        foreach ( self::features() as $feature ) {
            try { self::locked( function() use ( $feature ) { self::migrate( $feature ); } ); unset( self::$errors[$feature] ); }
            catch ( Throwable $e ) { self::$errors[$feature] = $e->getMessage(); }
        }
    }
    public static function held_link( $feature ) {
        return '<strong>'.esc_html__('Not active','loyalty-for-woocommerce').'</strong> '.esc_html(self::held_settings_message($feature));
    }
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
        $status=self::status();
        if (('completed'===$status['state'] && !$status['paused']) || get_user_meta(get_current_user_id(),'loyf_migration_notice_dismissed',true)===self::notice_token($status)) { return; }
        $active=in_array($status['state'],array('queued','running'),true);
        $message=$active ? __('Loyalty is updating your settings in the background. Existing points and history are unchanged.','loyalty-for-woocommerce') : __('Loyalty settings need attention. Some new rewards, redemption or emails are paused. Existing points and history are unchanged.','loyalty-for-woocommerce');
        if ('completed'===$status['state'] && $status['paused']) { $message=__('Loyalty preserved your stored settings, points and history. Future incentives, emails or new redemptions were paused because the previous configuration could not be verified. You can configure future activity in the ordinary Loyalty settings.','loyalty-for-woocommerce'); }
        elseif (!$active) { $message=__('Loyalty could not finish updating some settings. Their saved data is preserved and new activity remains paused. Check WooCommerce Scheduled Actions and logs for processing or storage problems.','loyalty-for-woocommerce'); }
        echo '<div id="loyf-migration-notice" class="notice notice-'.($active?'info':'warning').'" style="position:relative;padding-right:38px" data-token="'.esc_attr(self::notice_token($status)).'"><p>'.esc_html($message).' <a href="'.esc_url(admin_url('admin.php?page=wc-settings&tab=loyalty')).'">'.esc_html__('Loyalty settings','loyalty-for-woocommerce').'</a></p><button type="button" class="notice-dismiss" aria-label="'.esc_attr__('Dismiss this notice','loyalty-for-woocommerce').'"><span class="screen-reader-text">'.esc_html__('Dismiss this notice','loyalty-for-woocommerce').'</span></button></div>';
        $url=wp_json_encode(admin_url('admin-ajax.php'));$nonce=wp_json_encode(wp_create_nonce('loyf_dismiss_migration'));
        echo '<script>(function(){var n=document.getElementById("loyf-migration-notice");if(!n)return;n.querySelector("button").addEventListener("click",function(){var b=this;b.disabled=true;fetch('.$url.',{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:new URLSearchParams({action:"loyf_dismiss_migration",_wpnonce:'.$nonce.',token:n.dataset.token})}).then(function(r){return r.json();}).then(function(r){if(!r.success)throw new Error();n.remove();}).catch(function(){b.disabled=false;});});})();</script>';
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
