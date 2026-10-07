<?php

defined( 'ABSPATH' ) || exit;

/** Prospective provenance in the existing log. Balances remain projections of checkpoint + deltas. */
class YOWCL_Points_Allocation {
	const MAX_RECEIPT = 65536;
	const MAX_SOURCES = 256;

	public static function decode( $raw ) {
		if ( ! is_string( $raw ) || strlen( $raw ) > self::MAX_RECEIPT ) { throw new DomainException( 'allocation_receipt_required' ); }
		$value = json_decode( $raw, true );
		if ( ! is_array( $value ) || wp_json_encode( $value ) !== $raw || array_keys( $value ) !== array( 'version', 'cutoff', 'admitted_at', 'timezone', 'request', 'available', 'earning' ) || 1 !== $value['version'] || ! is_int( $value['cutoff'] ) || $value['cutoff'] < 0 || ! self::timestamp_valid( $value['admitted_at'] ) || ! self::timezone_valid( $value['timezone'] ) || ! is_array( $value['request'] ) ) { throw new DomainException( 'malformed_allocation' ); }
		$request = $value['request'];
		if ( array_keys( $request ) !== array( 'mode', 'available', 'earning', 'decision', 'expiration' ) || ! in_array( $request['mode'], array( 'strict', 'reward', 'reversal', 'clamp', 'replace', 'credit' ), true ) || ! is_int( $request['available'] ) || ! is_int( $request['earning'] ) || ! YOWCL_Ledger_V2::integer_valid( $request['available'] ) || ! YOWCL_Ledger_V2::integer_valid( $request['earning'] ) || ( null !== $request['decision'] && ! is_bool( $request['decision'] ) && ! self::timestamp_valid( $request['decision'] ) ) ) { throw new DomainException( 'malformed_allocation' ); }
		if ( null !== $request['expiration'] ) { throw new DomainException( 'unsupported_event_terms' ); }
		foreach ( array( 'available', 'earning' ) as $kind ) {
			$plan = $value[ $kind ]; if ( null === $plan ) { continue; }
			if ( ! is_array( $plan ) ) { throw new DomainException( 'malformed_allocation' ); }
			if ( 'all' === ( $plan['mode'] ?? null ) ) {
				if ( array_keys( $plan ) !== array( 'mode', 'through', 'at' ) || ! is_int( $plan['through'] ) || $plan['through'] < 0 || ! self::timestamp_valid( $plan['at'] ) ) { throw new DomainException( 'malformed_allocation' ); }
			} else {
				if ( array_keys( $plan ) !== array( 'mode', 'items' ) || ! in_array( $plan['mode'], array( 'eef', 'source' ), true ) || ! is_array( $plan['items'] ) || ! $plan['items'] || count( $plan['items'] ) > self::MAX_SOURCES || ( 'source' === $plan['mode'] && 1 !== count( $plan['items'] ) ) ) { throw new DomainException( 'malformed_allocation' ); }
				$seen = array();
				foreach ( $plan['items'] as $item ) {
					if ( ! is_array( $item ) || array_keys( $item ) !== array( 0, 1 ) || ! is_string( $item[0] ) || ( 'baseline' !== $item[0] && ! preg_match( '/^log:[1-9][0-9]{0,17}$/D', $item[0] ) ) || isset( $seen[ $item[0] ] ) || ! is_int( $item[1] ) || $item[1] <= 0 || ! YOWCL_Ledger_V2::integer_valid( $item[1] ) ) { throw new DomainException( 'malformed_allocation' ); }
					$seen[ $item[0] ] = true;
				}
			}
		}
		return $value;
	}

	public static function now() { return (int) round( microtime( true ) * 1000000 ); }

	public static function timestamp_valid( $value ) { return is_int( $value ) && $value > 0 && $value <= 253402300799999999; }

	public static function timezone_valid( $value ) {
		if ( ! is_string( $value ) || strlen( $value ) > 64 ) { return false; }
		try { new DateTimeZone( $value ); return true; } catch ( Throwable $e ) { return false; }
	}

}
