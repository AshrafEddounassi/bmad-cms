<?php
/*
Plugin Name: BMAD Docs Access
Description: Control d'accés a la documentació dels clients de BMAD. Captura els comentaris nous.
Version: 0.3
Author: Grup BMAD
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BMAD_DA_DB_VERSION', '1' );
define( 'BMAD_DA_MAX_CHARS', 500 );

// Nom de la taula pròpia (amb el prefix de WordPress: bmad_ + docs_captures).
function bmad_da_table() {
    global $wpdb;
    return $wpdb->prefix . 'docs_captures';
}

// Crea la taula amb dbDelta.
function bmad_da_install() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table   = bmad_da_table();
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        comment_id BIGINT(20) UNSIGNED NOT NULL,
        post_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        author VARCHAR(250) NOT NULL DEFAULT '',
        content VARCHAR(500) NOT NULL,
        captured_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY post_id (post_id),
        KEY user_id (user_id)
    ) $charset;";

    dbDelta( $sql );
    update_option( 'bmad_da_db_version', BMAD_DA_DB_VERSION );
}
register_activation_hook( __FILE__, 'bmad_da_install' );

// Si el plugin ja estava actiu (bind mount), l'activació no es torna a executar:
// comprovem la versió i creem la taula si cal.
function bmad_da_maybe_install() {
    if ( get_option( 'bmad_da_db_version' ) !== BMAD_DA_DB_VERSION ) {
        bmad_da_install();
    }
}
add_action( 'plugins_loaded', 'bmad_da_maybe_install' );

// Captura: s'executa cada cop que s'insereix un comentari (web o tauler).
function bmad_da_capture_comment( $comment_id, $comment ) {
    global $wpdb;

    // Només comentaris normals (no pingbacks ni trackbacks).
    if ( ! in_array( $comment->comment_type, array( '', 'comment' ), true ) ) {
        return;
    }

    $text = sanitize_textarea_field( $comment->comment_content );
    $text = mb_substr( $text, 0, BMAD_DA_MAX_CHARS ); // màxim 500 caràcters

    $wpdb->insert(
        bmad_da_table(),
        array(
            'comment_id'  => (int) $comment_id,
            'post_id'     => (int) $comment->comment_post_ID,
            'user_id'     => (int) $comment->user_id,
            'author'      => sanitize_text_field( $comment->comment_author ),
            'content'     => $text,
            'captured_at' => current_time( 'mysql' ),
        ),
        array( '%d', '%d', '%d', '%s', '%s', '%s' )
    );
}
add_action( 'wp_insert_comment', 'bmad_da_capture_comment', 10, 2 );