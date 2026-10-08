<?php
require __DIR__ . '/onboarding-fixture.php';
wp_set_current_user( get_user_by( 'login','loyf_admin' )->ID );
$barrier=getenv( 'LOYF11_BARRIER' ); $input=loyf11_input();
$_POST=array( 'earning_point_rules_nonce'=>wp_create_nonce( 'save_earning_point_rules' ),'loyalty_earning_points'=>array( 'customer'=>'7' ),'loyalty_earning_amount'=>array( 'customer'=>'5' ) );
file_put_contents( $barrier . '.ready','ready' );
$until=microtime(true)+15; while ( ! file_exists( $barrier . '.go' ) && microtime(true)<$until ) { usleep(20000); }
loyf_assert( file_exists( $barrier . '.go' ),'Worker barrier timeout' );
if ( 'merchant-race' === getenv( 'LOYF11_CASE' ) ) { ( new YOSWC_Loyalty_Settings() )->save_earning_point_rules(); }
else { YOWCL_Free_Onboarding::launch( $input ); }
file_put_contents( $barrier . '.done','done' );
echo "Native onboarding worker PASS\n";
