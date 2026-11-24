<?php

namespace Hostinger\AiTheme\GutenbergBlocks\Booking;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class Booking {
    public const OPTION_NAME      = 'hostinger_booking_submissions';
    public const MAX_SUBMISSIONS  = 500;
    public const PER_PAGE         = 50;
    public const MAX_EXTRA_FIELDS = 10;
    public const MAX_CELL_LENGTH  = 500;

    private BookingFieldRegistry $field_registry;
    private BookingSubmissionValidator $validator;
    private BookingRateLimiter $rate_limiter;

    public function __construct(
        ?BookingFieldRegistry $field_registry = null,
        ?BookingSubmissionValidator $validator = null,
        ?BookingRateLimiter $rate_limiter = null
    ) {
        $this->field_registry = $field_registry ?? new BookingFieldRegistry();
        $this->validator      = $validator ?? new BookingSubmissionValidator();
        $this->rate_limiter   = $rate_limiter ?? new BookingRateLimiter();

        add_action( 'init', array( $this, 'register_block' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
        add_action( 'admin_menu', array( $this, 'maybe_add_admin_menu' ) );
        $this->field_registry->register_hooks();
    }

    public function register_block() {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        register_block_type(
            __DIR__ . '/block.json',
            array(
                'render_callback' => array( $this, 'render_block' ),
            )
        );
    }

    public function register_rest_routes() {
        register_rest_route(
            'hostinger/v1',
            '/booking-submissions',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_booking_submission' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public function handle_booking_submission( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $client_ip = $this->rate_limiter->get_client_ip();

        if ( $this->rate_limiter->is_limited( $client_ip ) ) {
            return new WP_Error(
                'too_many_requests',
                __( 'Too many booking requests. Please try again later.', 'hostinger-ai-theme' ),
                array( 'status' => 429 )
            );
        }

        $this->rate_limiter->record_attempt( $client_ip );

        $size_error = $this->validator->validate_body_size( (string) $request->get_body() );

        if ( $size_error !== null ) {
            return $size_error;
        }

        $data = $request->get_json_params();

        if ( $this->validator->is_honeypot_filled( $data ) ) {
            return $this->success_response();
        }

        $sanitized = $this->validator->validate( $data, $this->field_registry->get_allowed_fields() );

        if ( is_wp_error( $sanitized ) ) {
            return $sanitized;
        }

        $this->store_submission( $sanitized );

        return $this->success_response();
    }

    public function maybe_add_admin_menu() {
        $submissions = get_option( self::OPTION_NAME, array() );

        if ( ! empty( $submissions ) ) {
            add_menu_page(
                'Bookings',
                'Bookings',
                'manage_options',
                'hostinger-bookings',
                array( $this, 'render_admin_page' ),
                'dashicons-calendar-alt',
                30
            );
        }
    }

    public function render_admin_page(): void {
        $submissions = $this->get_valid_submissions();

        if ( empty( $submissions ) ) {
            $this->render_empty_admin_page();
            return;
        }

        $total_pages  = max( 1, (int) ceil( count( $submissions ) / self::PER_PAGE ) );
        $current_page = min( $total_pages, max( 1, absint( $_GET['paged'] ?? 1 ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page_rows    = array_slice( array_reverse( $submissions ), ( $current_page - 1 ) * self::PER_PAGE, self::PER_PAGE );
        $columns      = $this->field_registry->get_allowed_fields();
        $has_extra    = $this->rows_have_extra_fields( $page_rows, $columns );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Bookings', 'hostinger-ai-theme' ); ?></h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <th><?php echo esc_html__( 'Date', 'hostinger-ai-theme' ); ?></th>
                    <?php foreach ( array_keys( $columns ) as $column ) : ?>
                        <th><?php echo esc_html( $this->get_column_label( $column ) ); ?></th>
                    <?php endforeach; ?>
                    <?php if ( $has_extra ) : ?>
                        <th><?php echo esc_html__( 'Other', 'hostinger-ai-theme' ); ?></th>
                    <?php endif; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach ( $page_rows as $submission ) : ?>
                    <tr>
                        <td><?php echo esc_html( $this->format_cell( $submission['timestamp'] ?? '' ) ); ?></td>
                        <?php foreach ( array_keys( $columns ) as $column ) : ?>
                            <td><?php echo esc_html( $this->format_cell( $submission['data'][ $column ] ?? '' ) ); ?></td>
                        <?php endforeach; ?>
                        <?php if ( $has_extra ) : ?>
                            <td><?php echo esc_html( $this->format_extra_fields( $submission['data'], $columns ) ); ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            echo wp_kses_post(
                (string) paginate_links(
                    array(
                        'base'    => add_query_arg( 'paged', '%#%' ),
                        'format'  => '',
                        'current' => $current_page,
                        'total'   => $total_pages,
                    )
                )
            );
            ?>
        </div>
        <?php
    }

    public function render_block( $attributes, $content, $block ) {
        ob_start();
        require __DIR__ . '/render.php';

        return ob_get_clean();
    }

    private function render_empty_admin_page(): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Bookings', 'hostinger-ai-theme' ); ?></h1>
            <p><?php echo esc_html__( 'No bookings yet.', 'hostinger-ai-theme' ); ?></p>
        </div>
        <?php
    }

    private function store_submission( array $data ): void {
        $submissions   = get_option( self::OPTION_NAME, array() );
        $submissions   = is_array( $submissions ) ? $submissions : array();
        $submissions[] = array(
            'data'      => $data,
            'timestamp' => current_time( 'mysql' ),
        );

        update_option( self::OPTION_NAME, array_values( array_slice( $submissions, -self::MAX_SUBMISSIONS ) ) );
    }

    private function success_response(): WP_REST_Response {
        return new WP_REST_Response(
            array(
                'message' => 'Booking submitted successfully',
                'status'  => 'success',
            ),
            200
        );
    }

    private function get_valid_submissions(): array {
        $submissions = get_option( self::OPTION_NAME, array() );

        if ( ! is_array( $submissions ) ) {
            return array();
        }

        return array_values(
            array_filter(
                $submissions,
                static fn( $submission ): bool => is_array( $submission ) && isset( $submission['data'] ) && is_array( $submission['data'] )
            )
        );
    }

    private function rows_have_extra_fields( array $rows, array $columns ): bool {
        foreach ( $rows as $row ) {
            if ( ! empty( array_diff_key( $row['data'], $columns ) ) ) {
                return true;
            }
        }

        return false;
    }

    private function format_extra_fields( array $data, array $columns ): string {
        $extra_fields = array_slice( array_diff_key( $data, $columns ), 0, self::MAX_EXTRA_FIELDS, true );
        $parts        = array();

        foreach ( $extra_fields as $key => $value ) {
            $parts[] = $this->format_cell( (string) $key ) . ': ' . $this->format_cell( $value );
        }

        return implode( '; ', $parts );
    }

    private function format_cell( mixed $value ): string {
        if ( ! is_scalar( $value ) ) {
            return '';
        }

        $value = (string) $value;

        if ( mb_strlen( $value ) <= self::MAX_CELL_LENGTH ) {
            return $value;
        }

        return mb_substr( $value, 0, self::MAX_CELL_LENGTH ) . '…';
    }

    private function get_column_label( string $column ): string {
        return ucfirst( str_replace( array( '-', '_' ), ' ', urldecode( $column ) ) );
    }
}
