<?php

namespace Hostinger\AiAssistant\Mcp;

use stdClass;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class Hooks {
    private array $active_mcp_requests = array();

    public function init(): void {
        add_filter( 'hostinger_once_per_day_events', array( $this, 'limit_triggered_amplitude_events' ) );
        add_filter( 'rest_dispatch_request', array( $this, 'start_mcp_request' ), 10, 2 );
        add_filter( 'rest_request_after_callbacks', array( $this, 'finish_mcp_request' ), 10, 3 );
    }

    /**
     * Fires only after the route permission callback passed, so MCP filters apply to authenticated MCP dispatches only.
     */
    public function start_mcp_request( mixed $dispatch_result, WP_REST_Request $request ): mixed {
        if ( ! McpRoutes::is_mcp_route( $request->get_route() ) ) {
            return $dispatch_result;
        }

        if ( empty( $this->active_mcp_requests ) ) {
            $this->add_mcp_filters();
        }

        $this->active_mcp_requests[ spl_object_id( $request ) ] = true;

        return $dispatch_result;
    }

    public function finish_mcp_request( mixed $response, array $handler, WP_REST_Request $request ): mixed {
        $request_id = spl_object_id( $request );
        if ( ! isset( $this->active_mcp_requests[ $request_id ] ) ) {
            return $response;
        }

        unset( $this->active_mcp_requests[ $request_id ] );

        if ( empty( $this->active_mcp_requests ) ) {
            $this->remove_mcp_filters();
        }

        return $response;
    }

    public function filter_product_meta_fields( WP_REST_Response $response, mixed $post, WP_REST_Request $request ): WP_REST_Response {
        if ( isset( $response->data['meta_data'] ) && is_array( $response->data['meta_data'] ) ) {
            $response->data['meta_data'] = array_values(
                array_filter(
                    $response->data['meta_data'],
                    function ( $meta ) {
                        return ! str_starts_with( $meta->key, '_uag' );
                    }
                )
            );
        }

        return $response;
    }

    public function limit_triggered_amplitude_events( array $events ): array {
        $new_events = array(
            'wordpress.chatbot.survey_filled',
        );

        return array_merge( $events, $new_events );
    }

    public function convert_content_to_blocks( stdClass $prepared_post, WP_REST_Request $request ): stdClass|WP_Error {
        if ( empty( $prepared_post->post_content ) ) {
            return $prepared_post;
        }

        $prepared_post->post_content = ContentUtils::maybe_convert_to_blocks( $prepared_post->post_content, $prepared_post->post_type ?? 'post' );

        return $prepared_post;
    }

    private function add_mcp_filters(): void {
        add_filter( 'woocommerce_rest_prepare_product_object', array( $this, 'filter_product_meta_fields' ), 10, 3 );
        add_filter( 'rest_pre_insert_post', array( $this, 'convert_content_to_blocks' ), 10, 2 );
        add_filter( 'rest_pre_insert_page', array( $this, 'convert_content_to_blocks' ), 10, 2 );
    }

    private function remove_mcp_filters(): void {
        remove_filter( 'woocommerce_rest_prepare_product_object', array( $this, 'filter_product_meta_fields' ), 10 );
        remove_filter( 'rest_pre_insert_post', array( $this, 'convert_content_to_blocks' ), 10 );
        remove_filter( 'rest_pre_insert_page', array( $this, 'convert_content_to_blocks' ), 10 );
    }
}
