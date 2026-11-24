<?php

namespace Hostinger\AiTheme;

use Hostinger\AiTheme\Admin\Menu;
use Hostinger\AiTheme\Builder\Helper;

defined( 'ABSPATH' ) || exit;

class Redirects {
    /**
     * @var string
     */
    private string $platform;
    public const PLATFORM_CONTENT_CREATOR     = 'ai-website-generation';
    public const RETIRED_AI_BUILDER_MENU_SLUG = 'hostinger-ai-website-creation-v2';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'redirect_retired_builder_page' ) );
        add_action( 'admin_init', array( $this, 'redirect_to_builder' ) );

        if ( ! isset( $_GET['platform'] ) ) {
            return;
        }

        $this->platform = sanitize_text_field( $_GET['platform'] );
        $this->login_redirect();
    }

    /**
     * @return void
     */
    public function redirect_to_builder(): void {
        if ( wp_doing_ajax() || defined( 'REST_REQUEST' ) ) {
            return;
        }

        if ( $this->current_page() === Menu::AI_BUILDER_MENU_SLUG ) {
            return;
        }

        if ( Helper::has_finished_builder_onboarding() ) {
            return;
        }

        wp_safe_redirect( $this->builder_url() );
        exit;
    }

    public function redirect_retired_builder_page(): void {
        if ( $this->current_page() !== self::RETIRED_AI_BUILDER_MENU_SLUG ) {
            return;
        }

        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

        wp_safe_redirect( add_query_arg( 'page', Menu::AI_BUILDER_MENU_SLUG, $request_uri ) );
        exit;
    }

    /**
     * @return void
     */
    private function login_redirect(): void {
        if ( $this->platform === self::PLATFORM_CONTENT_CREATOR ) {
            add_action(
                'init',
                function () {
                    wp_safe_redirect( $this->builder_url() );
                    exit;
                }
            );
        }
    }

    /**
     * @return string
     */
    private function current_page(): string {
        return isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
    }

    /**
     * @return string
     */
    private function builder_url(): string {
        return admin_url( 'admin.php?page=' . Menu::AI_BUILDER_MENU_SLUG );
    }
}
