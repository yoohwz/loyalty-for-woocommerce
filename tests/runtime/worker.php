<?php
if ( ! defined( 'LOYF_RUNTIME_DISPOSABLE' ) || ! LOYF_RUNTIME_DISPOSABLE ) { throw new RuntimeException( 'Disposable required' ); }
$user = (int) getenv( 'LOYF_WORKER_USER' ); $key = getenv( 'LOYF_WORKER_KEY' ); $barrier = getenv( 'LOYF_WORKER_BARRIER' ); $index = (int) getenv( 'LOYF_WORKER_INDEX' );
get_user_meta( $user, 'user_points', true ); get_user_meta( $user, 'user_earning_points', true );
file_put_contents( $barrier . '.' . $index, 'ready' ); $until = microtime( true ) + 10;
while ( ! file_exists( $barrier . '.' . ( 1 - $index ) ) ) { if ( microtime( true ) > $until ) { throw new RuntimeException( 'Worker barrier timeout' ); } usleep( 10000 ); }
add_action( 'yowcl_transaction_test_checkpoint', function ( $step ) { if ( 'event_inserted' === $step ) { usleep( 250000 ); } } );
for ( $try = 0; $try < 30; $try++ ) { $result = YOWCL_Points_Transaction::apply( $user, -30, 0, $key ); if ( 'busy' !== $result['status'] ) { break; } usleep( 100000 ); }
echo json_encode( $result );
