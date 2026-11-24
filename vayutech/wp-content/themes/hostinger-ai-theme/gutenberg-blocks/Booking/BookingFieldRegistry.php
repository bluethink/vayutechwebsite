<?php

namespace Hostinger\AiTheme\GutenbergBlocks\Booking;

use WP_Post;

defined( 'ABSPATH' ) || exit;

class BookingFieldRegistry {
    public const BLOCK_NAME     = 'hostinger-ai-theme/booking-block';
    public const OPTION_NAME    = 'hostinger_booking_form_fields';
    public const PRIVACY_FIELD  = 'privacy_policy';
    public const HONEYPOT_FIELD = 'website_url';
    public const MAX_FIELDS     = 30;
    public const MAX_KEY_LENGTH = 200;
    public const MAX_SCAN_POSTS = 100;
    public const FIELD_TYPES    = array( 'text', 'email', 'date', 'textarea', 'privacy' );
    public const DEFAULT_FIELDS = array(
        'name'           => 'text',
        'email'          => 'email',
        'date'           => 'date',
        'message'        => 'textarea',
        'privacy_policy' => 'privacy',
    );

    public function register_hooks(): void {
        add_action( 'save_post', array( $this, 'remember_post_fields' ), 10, 2 );
    }

    public function get_allowed_fields(): array {
        $stored_fields = get_option( self::OPTION_NAME, null );

        if ( is_array( $stored_fields ) ) {
            return array_merge( self::DEFAULT_FIELDS, $this->filter_fields( $stored_fields ) );
        }

        $discovered_fields = $this->discover_fields_from_content();
        update_option( self::OPTION_NAME, $discovered_fields );

        return array_merge( self::DEFAULT_FIELDS, $discovered_fields );
    }

    public function remember_post_fields( int $post_id, WP_Post $post ): void {
        if ( wp_is_post_revision( $post_id ) || ! has_block( self::BLOCK_NAME, $post->post_content ) ) {
            return;
        }

        $post_fields   = $this->extract_fields_from_blocks( parse_blocks( $post->post_content ) );
        $stored_fields = get_option( self::OPTION_NAME, null );
        $stored_fields = is_array( $stored_fields ) ? $stored_fields : $this->discover_fields_from_content();
        $older_fields  = array_diff_key( $stored_fields, $post_fields );

        update_option( self::OPTION_NAME, $this->filter_fields( array_merge( $older_fields, $post_fields ) ) );
    }

    public function extract_fields_from_blocks( array $blocks ): array {
        $fields = array();

        foreach ( $blocks as $block ) {
            if ( ! is_array( $block ) ) {
                continue;
            }

            if ( ( $block['blockName'] ?? '' ) === self::BLOCK_NAME ) {
                $fields = array_merge( $fields, $this->extract_fields_from_attributes( $block['attrs'] ?? array() ) );
            }

            if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
                $fields = array_merge( $fields, $this->extract_fields_from_blocks( $block['innerBlocks'] ) );
            }
        }

        return $this->filter_fields( $fields );
    }

    private function extract_fields_from_attributes( array $attributes ): array {
        $form_fields = $attributes['formFields'] ?? array();

        if ( ! is_array( $form_fields ) ) {
            return array();
        }

        $fields = array();

        foreach ( $form_fields as $form_field ) {
            if ( ! is_array( $form_field ) ) {
                continue;
            }

            $type = isset( $form_field['type'] ) && is_string( $form_field['type'] ) ? $form_field['type'] : 'text';

            if ( $type === 'privacy' ) {
                $fields[ self::PRIVACY_FIELD ] = 'privacy';
                continue;
            }

            $label = isset( $form_field['label'] ) && is_string( $form_field['label'] ) ? $form_field['label'] : '';
            $key   = sanitize_title( $label );

            if ( $key === '' ) {
                continue;
            }

            $fields[ $key ] = in_array( $type, self::FIELD_TYPES, true ) ? $type : 'text';
        }

        return $fields;
    }

    private function discover_fields_from_content(): array {
        global $wpdb;

        $contents = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT post_content FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_type != 'revision' LIMIT %d",
                '%' . $wpdb->esc_like( '<!-- wp:' . self::BLOCK_NAME ) . '%',
                self::MAX_SCAN_POSTS
            )
        );

        $fields = array();

        foreach ( (array) $contents as $content ) {
            $fields = array_merge( $fields, $this->extract_fields_from_blocks( parse_blocks( (string) $content ) ) );
        }

        return $this->filter_fields( $fields );
    }

    private function filter_fields( array $fields ): array {
        $filtered = array();

        foreach ( $fields as $key => $type ) {
            if ( ! is_string( $key ) || $key === self::HONEYPOT_FIELD || ! in_array( $type, self::FIELD_TYPES, true ) ) {
                continue;
            }

            if ( strlen( $key ) > self::MAX_KEY_LENGTH || preg_match( '/^[a-z0-9_%-]+$/', $key ) !== 1 ) {
                continue;
            }

            $filtered[ $key ] = $type;
        }

        return array_slice( $filtered, -self::MAX_FIELDS, null, true );
    }
}
