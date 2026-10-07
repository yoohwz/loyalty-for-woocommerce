<?php
if ( ! defined( 'LOYF_RUNTIME_DISPOSABLE' ) ) { throw new RuntimeException( 'Disposable required' ); }
global $wpdb;
$user = wp_insert_user( array( 'user_login' => 'pre_cutover', 'user_email' => 'pre@example.invalid', 'user_pass' => 'disposable-only', 'role' => 'customer' ) );
update_user_meta( $user, 'user_points', '37' ); update_user_meta( $user, 'user_earning_points', '37' );
$comment = wp_insert_comment( array( 'user_id' => $user, 'comment_type' => 'review', 'comment_approved' => 1, 'comment_content' => 'Historical review' ) );
$table = $wpdb->prefix . 'yo_loyalty_points_log';
$wpdb->query( "CREATE TABLE $table (id mediumint(9) NOT NULL AUTO_INCREMENT, user_id bigint(20) NOT NULL, action varchar(255) NOT NULL, order_id bigint(20) NOT NULL, amount decimal(10,2) NOT NULL, description text NOT NULL, date datetime NOT NULL, PRIMARY KEY(id)) ENGINE=InnoDB" );
$wpdb->insert( $table, array( 'user_id' => $user, 'action' => 'sign_up_reward', 'order_id' => 0, 'amount' => 5, 'description' => 'Historical bytes', 'date' => '2020-01-01 00:00:00' ) );
add_option( 'loyf_fixture_pre', array( 'user' => $user, 'comment' => $comment, 'row' => $wpdb->get_row( "SELECT * FROM $table", ARRAY_A ) ) );
