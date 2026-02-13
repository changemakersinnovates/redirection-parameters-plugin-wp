<?php
/*
Plugin Name: Redirection Params & Logging Enhancer
Plugin URI: https://cmtrk.ca/
Description: Ensures query parameters from the incoming request are preserved when redirecting and ensures the full target URL (with parameters) is logged.
Dependencies: Redirection Plugin
Version: 0.1.0
Author: Copilot with direction from ChangeMakers Digital
Text Domain: redirection-params
*/

// Avoid direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Merge query parameters from $source_url into $target_url without overwriting existing target params.
 * Preserves fragments and array-style params. Basic safe merge suitable for most redirects.
 *
 * @param string $target_url
 * @param string $source_url
 * @return string
 */
function rparams_merge_queries( $target_url, $source_url ) {
    if ( empty( $target_url ) || empty( $source_url ) ) {
        return $target_url;
    }

    // Parse target
    $t_comp = wp_parse_url( $target_url );
    $s_comp = wp_parse_url( $source_url );

    $t_query = [];
    $s_query = [];

    if ( isset( $t_comp['query'] ) ) {
        wp_parse_str( $t_comp['query'], $t_query );
    }

    if ( isset( $s_comp['query'] ) ) {
        wp_parse_str( $s_comp['query'], $s_query );
    }

    if ( empty( $s_query ) ) {
        return $target_url; // nothing to add
    }

    // Add any keys from source that aren't present in target
    foreach ( $s_query as $k => $v ) {
        if ( ! array_key_exists( $k, $t_query ) ) {
            $t_query[ $k ] = $v;
        }
    }

    // Build final query
    $new_query = http_build_query( $t_query );

    // Reconstruct URL: scheme://host/path?query#fragment (but keep whatever was in target for host/path)
    $result = '';

    if ( isset( $t_comp['scheme'] ) ) {
        $result .= $t_comp['scheme'] . '://';
    }

    if ( isset( $t_comp['host'] ) ) {
        $result .= $t_comp['host'];
        if ( isset( $t_comp['port'] ) ) {
            $result .= ':' . $t_comp['port'];
        }
    }

    if ( isset( $t_comp['path'] ) ) {
        $result .= $t_comp['path'];
    }

    if ( $new_query !== '' ) {
        $result .= ( strpos( $result, '?' ) === false ? '?' : '&' ) . $new_query;
    }

    if ( isset( $t_comp['fragment'] ) ) {
        $result .= '#' . $t_comp['fragment'];
    }

    // If target didn't include a host/scheme (relative URL), fallback to using original target with query appended
    if ( empty( $t_comp['host'] ) && empty( $t_comp['scheme'] ) ) {
        // base is relative: take original target path and append query/fragment
        $base = $t_comp['path'] ?? '';
        $out = $base;
        if ( $new_query !== '' ) {
            $out .= ( strpos( $base, '?' ) === false ? '?' : '&' ) . $new_query;
        }
        if ( isset( $t_comp['fragment'] ) ) {
            $out .= '#' . $t_comp['fragment'];
        }

        return $out;
    }

    return $result;
}

/**
 * Filter to ensure query params from the incoming request are added to the target URL when appropriate.
 * Runs on the `redirection_url_target` filter provided by the Redirection plugin.
 *
 * @param string $target_url Current target URL determined by Redirection.
 * @param string $source_url The source URL pattern (stored redirect source).
 * @return string
 */
function rparams_redirection_url_target( $target_url, $source_url ) {
    // If target empty or not a string, nothing to do
    if ( ! is_string( $target_url ) || $target_url === '' ) {
        return $target_url;
    }

    if ( class_exists( 'Redirection_Request' ) ) {
        $requested = Redirection_Request::get_request_url();
    } else {
        // Fallback to server REQUEST_URI
        $requested = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( $_SERVER['REQUEST_URI'] ) : '';
    }

    if ( empty( $requested ) ) {
        return $target_url;
    }

    // Merge params from the requested URL into the target without overwriting target params
    $merged = rparams_merge_queries( $target_url, $requested );

    return $merged;
}

/**
 * Filter log data before insertion to ensure `sent_to` contains the full URL including params.
 * This runs on `redirection_log_data`.
 *
 * @param array $insert Insert array for log record.
 * @return array
 */
function rparams_redirection_log_data( $insert ) {
    // If sent_to already includes a query, keep as-is. Otherwise attempt to append incoming query params.
    if ( empty( $insert ) || ! is_array( $insert ) ) {
        return $insert;
    }

    if ( ! isset( $insert['sent_to'] ) || empty( $insert['sent_to'] ) ) {
        return $insert;
    }

    if ( class_exists( 'Redirection_Request' ) ) {
        $requested = Redirection_Request::get_request_url();
    } else {
        $requested = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( $_SERVER['REQUEST_URI'] ) : '';
    }

    if ( empty( $requested ) ) {
        return $insert;
    }

    // Merge, but ensure not to override any existing sent_to params
    $insert['sent_to'] = rparams_merge_queries( $insert['sent_to'], $requested );

    return $insert;
}

add_filter( 'redirection_url_target', 'rparams_redirection_url_target', 10, 2 );
add_filter( 'redirection_log_data', 'rparams_redirection_log_data', 10, 1 );

/**
 * Display an admin notice if the Redirection plugin is not active.
 * Only shows for users who can activate plugins (administrators).
 */
function rparams_admin_notice_redirection_missing() {
    if ( ! is_admin() ) {
        return;
    }

    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }

    // Prefer checking for a Redirection class/constant first
    if ( class_exists( 'Red_Item' ) || defined( 'REDIRECTION_FILE' ) ) {
        return; // Redirection appears active
    }

    // Fallback to is_plugin_active check
    if ( ! function_exists( 'is_plugin_active' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if ( is_plugin_active( 'redirection/redirection.php' ) ) {
        return; // active
    }

    $message = esc_html__( 'Redirection Params & Logging Enhancer requires the Redirection plugin to be active. Please activate or install "Redirection" to enable full functionality.', 'redirection-params' );

    echo '<div class="notice notice-warning is-dismissible"><p>' . $message . '</p></div>';
}

add_action( 'admin_notices', 'rparams_admin_notice_redirection_missing' );
