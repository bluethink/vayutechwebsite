<?php

namespace Hostinger\AiAssistant\Mcp\Abilities\Tools;

use Hostinger\AiAssistant\Mcp\Abilities\AbilitiesRegistry;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class WooProductsBulkDeleteTool {
    public const TOOL_NAME = 'hostinger-ai/wc-products-bulk-delete';

    public const MAX_PRODUCTS = 100;

    private const PRODUCTS_ROUTE = '/wc/v3/products/';

    public function register(): void {
        if ( ! $this->is_woocommerce_active() ) {
            return;
        }

        wp_register_ability(
            self::TOOL_NAME,
            array(
                'label'               => __( 'Bulk Delete WooCommerce Products', 'hostinger-ai-assistant' ),
                'description'         => sprintf(
                    'Delete several WooCommerce products in one call, up to %d per call. Use when asked to remove more than one product, instead of calling `hostinger-ai/wc-products-delete` for each one; send the whole selection in a single call rather than splitting it into small batches. Products are moved to the trash and can be restored, unless force is true, which deletes them permanently and cannot be undone; only use force when the user explicitly asks for permanent deletion. Each product is processed independently, so one failure does not stop the rest: the response lists deleted products and failed products with their reasons, and "success" is true only when no product failed. Needs `ids` from `hostinger-ai/wc-products-search`.',
                    self::MAX_PRODUCTS
                ),
                'category'            => AbilitiesRegistry::CATEGORY,
                'input_schema'        => array(
                    'type'       => 'object',
                    'properties' => array(
                        'ids'   => array(
                            'type'        => 'array',
                            'description' => 'IDs of the products to delete.',
                            'minItems'    => 1,
                            'maxItems'    => self::MAX_PRODUCTS,
                            'items'       => array(
                                'type' => 'integer',
                            ),
                        ),
                        'force' => array(
                            'type'        => 'boolean',
                            'description' => 'Delete the products permanently instead of moving them to the trash.',
                            'default'     => false,
                        ),
                    ),
                    'required'   => array( 'ids' ),
                ),
                'execute_callback'    => array( $this, 'execute' ),
                'permission_callback' => array( $this, 'check_permission' ),
                'meta'                => array(
                    'show_in_rest' => true,
                    'mcp'          => array(
                        'public' => true,
                        'type'   => 'tool',
                    ),
                    'annotations'  => array(
                        'readOnlyHint'    => false,
                        'destructiveHint' => true,
                        'idempotentHint'  => true,
                        'openWorldHint'   => false,
                    ),
                ),
            )
        );
    }

    public function check_permission(): bool {
        return current_user_can( 'manage_options' );
    }

    public function execute( array $input ): WP_Error|array {
        $ids = $input['ids'] ?? array();

        if ( ! is_array( $ids ) || empty( $ids ) ) {
            return new WP_Error(
                'wc_products_bulk_delete_empty',
                __( 'Provide at least one product ID to delete.', 'hostinger-ai-assistant' ),
                array( 'status' => 400 )
            );
        }

        $ids = array_values( array_unique( array_map( 'intval', $ids ) ) );

        if ( count( $ids ) > self::MAX_PRODUCTS ) {
            return new WP_Error(
                'wc_products_bulk_delete_limit_exceeded',
                sprintf(
                    /* translators: %d: maximum number of products per call */
                    __( 'A bulk delete accepts at most %d products per call.', 'hostinger-ai-assistant' ),
                    self::MAX_PRODUCTS
                ),
                array( 'status' => 400 )
            );
        }

        $force   = ! empty( $input['force'] );
        $deleted = array();
        $failed  = array();

        foreach ( $ids as $product_id ) {
            $outcome = $this->delete_product( $product_id, $force );

            if ( isset( $outcome['error'] ) ) {
                $failed[] = $outcome['error'];
                continue;
            }

            $deleted[] = $outcome['deleted'];
        }

        return array(
            'success' => empty( $failed ),
            'deleted' => $deleted,
            'failed'  => $failed,
        );
    }

    protected function is_woocommerce_active(): bool {
        return class_exists( 'WooCommerce' );
    }

    private function delete_product( int $product_id, bool $force ): array {
        if ( $product_id <= 0 ) {
            return array(
                'error' => array(
                    'id'      => $product_id,
                    'code'    => 'wc_products_bulk_delete_invalid_id',
                    'message' => __( 'Product ID must be a positive integer.', 'hostinger-ai-assistant' ),
                ),
            );
        }

        $request = new WP_REST_Request( 'DELETE', self::PRODUCTS_ROUTE . $product_id );
        $request->set_param( 'force', $force );

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $error = $response->as_error();

            return array(
                'error' => array(
                    'id'      => $product_id,
                    'code'    => $error->get_error_code(),
                    'message' => $error->get_error_message(),
                ),
            );
        }

        $data = $response->get_data();

        return array(
            'deleted' => array(
                'id'     => $product_id,
                'name'   => is_array( $data ) ? ( $data['name'] ?? '' ) : '',
                'status' => $force ? 'deleted' : 'trash',
            ),
        );
    }
}
