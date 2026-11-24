<?php

namespace Hostinger\AiTheme\GutenbergBlocks\Booking;

defined( 'ABSPATH' ) || exit;

class BookingRateLimiter {
    public const TRANSIENT_PREFIX = 'hostinger_booking_rate_';
    public const MAX_ATTEMPTS     = 5;
    public const WINDOW_SECONDS   = 600;

    public function is_limited( string $client_ip ): bool {
        return $this->get_window( $client_ip )['count'] >= $this->get_max_attempts();
    }

    /**
     * Counts attempts in a fixed window: retrying does not push the window's expiry further out.
     */
    public function record_attempt( string $client_ip ): void {
        $window          = $this->get_window( $client_ip );
        $window['count'] = $window['count'] + 1;

        set_transient( $this->get_transient_key( $client_ip ), $window, max( 1, $window['expires'] - time() ) );
    }

    public function get_client_ip(): string {
        $remote_address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        $remote_address = filter_var( $remote_address, FILTER_VALIDATE_IP ) !== false ? $remote_address : 'unknown';

        return (string) apply_filters( 'hostinger_booking_rate_limit_client_ip', $remote_address );
    }

    private function get_window( string $client_ip ): array {
        $window = get_transient( $this->get_transient_key( $client_ip ) );

        if ( ! is_array( $window ) || ! isset( $window['count'], $window['expires'] ) || (int) $window['expires'] <= time() ) {
            return array(
                'count'   => 0,
                'expires' => time() + $this->get_window_seconds(),
            );
        }

        return array(
            'count'   => (int) $window['count'],
            'expires' => (int) $window['expires'],
        );
    }

    private function get_max_attempts(): int {
        return max( 1, (int) apply_filters( 'hostinger_booking_rate_limit_max_attempts', self::MAX_ATTEMPTS ) );
    }

    private function get_window_seconds(): int {
        return max( 1, (int) apply_filters( 'hostinger_booking_rate_limit_window_seconds', self::WINDOW_SECONDS ) );
    }

    private function get_transient_key( string $client_ip ): string {
        return self::TRANSIENT_PREFIX . md5( $client_ip );
    }
}
