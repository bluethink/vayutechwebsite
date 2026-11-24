<?php

namespace Hostinger\AiTheme\GutenbergBlocks\Booking;

use DateTime;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class BookingSubmissionValidator {
    public const MAX_BODY_BYTES = 10240;
    public const MAX_LENGTHS    = array(
        'text'     => 200,
        'email'    => 254,
        'date'     => 10,
        'textarea' => 5000,
    );

    public function validate_body_size( string $body ): ?WP_Error {
        if ( strlen( $body ) <= self::MAX_BODY_BYTES ) {
            return null;
        }

        return $this->error( 'payload_too_large', __( 'Booking data is too large.', 'hostinger-ai-theme' ) );
    }

    public function validate( mixed $data, array $allowed_fields ): array|WP_Error {
        if ( empty( $data ) || ! is_array( $data ) || array_is_list( $data ) ) {
            return $this->error( 'invalid_data', __( 'Invalid data provided', 'hostinger-ai-theme' ) );
        }

        $sanitized = array();

        foreach ( $data as $key => $value ) {
            if ( $key === BookingFieldRegistry::HONEYPOT_FIELD ) {
                continue;
            }

            if ( ! is_string( $key ) || ! isset( $allowed_fields[ $key ] ) ) {
                continue;
            }

            $field_value = $this->sanitize_field( $allowed_fields[ $key ], $value );

            if ( is_wp_error( $field_value ) ) {
                return $field_value;
            }

            $sanitized[ $key ] = $field_value;
        }

        if ( ! $this->has_content( $sanitized ) ) {
            return $this->error( 'invalid_data', __( 'Invalid data provided', 'hostinger-ai-theme' ) );
        }

        return $sanitized;
    }

    public function is_honeypot_filled( mixed $data ): bool {
        if ( ! is_array( $data ) ) {
            return false;
        }

        return ! empty( $data[ BookingFieldRegistry::HONEYPOT_FIELD ] );
    }

    private function sanitize_field( string $type, mixed $value ): string|bool|WP_Error {
        if ( $type === 'privacy' ) {
            return $this->sanitize_privacy( $value );
        }

        if ( $value === null ) {
            $value = '';
        }

        if ( ! is_scalar( $value ) || is_bool( $value ) ) {
            return $this->error( 'invalid_field_value', __( 'Booking fields must be text values.', 'hostinger-ai-theme' ) );
        }

        $value = (string) $value;

        if ( mb_strlen( $value ) > ( self::MAX_LENGTHS[ $type ] ?? self::MAX_LENGTHS['text'] ) ) {
            return $this->error( 'field_too_long', __( 'A booking field is too long.', 'hostinger-ai-theme' ) );
        }

        return match ( $type ) {
            'email'    => $this->sanitize_email_value( $value ),
            'date'     => $this->sanitize_date_value( $value ),
            'textarea' => sanitize_textarea_field( $value ),
            default    => sanitize_text_field( $value ),
        };
    }

    private function sanitize_privacy( mixed $value ): bool|WP_Error {
        $consent = is_scalar( $value ) ? filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) : null;

        if ( $consent === null ) {
            return $this->error( 'invalid_field_value', __( 'Invalid privacy policy consent value.', 'hostinger-ai-theme' ) );
        }

        return $consent;
    }

    private function sanitize_email_value( string $value ): string|WP_Error {
        $email = sanitize_email( $value );

        if ( trim( $value ) !== '' && ! is_email( $email ) ) {
            return $this->error( 'invalid_email', __( 'Please enter a valid email address.', 'hostinger-ai-theme' ) );
        }

        return $email;
    }

    private function sanitize_date_value( string $value ): string|WP_Error {
        $value = trim( $value );

        if ( $value === '' ) {
            return '';
        }

        $date = DateTime::createFromFormat( '!Y-m-d', $value );

        if ( $date === false || $date->format( 'Y-m-d' ) !== $value ) {
            return $this->error( 'invalid_date', __( 'Please enter a valid date.', 'hostinger-ai-theme' ) );
        }

        return $value;
    }

    private function has_content( array $sanitized ): bool {
        foreach ( $sanitized as $value ) {
            if ( is_string( $value ) && $value !== '' ) {
                return true;
            }
        }

        return false;
    }

    private function error( string $code, string $message ): WP_Error {
        return new WP_Error( $code, $message, array( 'status' => 400 ) );
    }
}
