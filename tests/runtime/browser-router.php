<?php
// Disposable, frozen browser fixtures may reuse public static bytes within a context.
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$file = realpath( $_SERVER['DOCUMENT_ROOT'] . rawurldecode( $path ) );
$root = realpath( $_SERVER['DOCUMENT_ROOT'] ) . DIRECTORY_SEPARATOR;
$types = array(
    'css' => 'text/css', 'js' => 'application/javascript', 'svg' => 'image/svg+xml',
    'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
    'gif' => 'image/gif', 'webp' => 'image/webp', 'ico' => 'image/x-icon',
    'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'otf' => 'font/otf',
);
$relative = $file && 0 === strpos( $file, $root ) ? substr( $file, strlen( $root ) ) : '';
$extension = $file ? strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) : '';
if ( in_array( $_SERVER['REQUEST_METHOD'], array( 'GET', 'HEAD' ), true )
    && preg_match( '#^(wp-includes/|wp-admin/(css|js|images)/|wp-content/(plugins|themes)/)#', $relative )
    && $file && is_file( $file ) && 0 === strpos( $file, $root ) && isset( $types[ $extension ] ) ) {
    header( 'Content-Type: ' . $types[ $extension ] );
    header( 'Cache-Control: public, max-age=3600' );
    header( 'Content-Length: ' . filesize( $file ) );
    if ( 'GET' === $_SERVER['REQUEST_METHOD'] ) { readfile( $file ); }
    return true;
}
if ( is_file( $_SERVER['DOCUMENT_ROOT'] . $path ) ) { return false; }
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';
