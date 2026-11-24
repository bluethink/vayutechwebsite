<?php

namespace Hostinger\AiTheme\Builder;

use WP_Error;

defined( 'ABSPATH' ) || exit;

abstract class AbstractPluginBuilder {
    use PluginInstaller;

    abstract protected function get_plugin_file(): string;

    abstract protected function get_plugin_name(): string;

    abstract protected function get_download_url(): string;

    abstract protected function get_error_code(): string;

    protected function is_enabled(): bool {
        return true;
    }

    protected function after_installation(): void {
    }

    protected function after_activation(): void {
    }

    public function boot(): bool|WP_Error {
        if ( ! $this->is_enabled() ) {
            return true;
        }

        return $this->force_boot();
    }

    public function force_boot(): bool|WP_Error {
        $enable_result = $this->enable_plugin();
        if ( is_wp_error( $enable_result ) ) {
            return $enable_result;
        }

        if ( ! $this->is_plugin_active() ) {
            return new WP_Error(
                $this->get_error_code() . '_not_active',
                sprintf(
                    '%s (%s)',
                    sprintf(
                        /* translators: %s: plugin name */
                        __( '%s is not active after installation.', 'hostinger-ai-theme' ),
                        $this->get_plugin_name()
                    ),
                    $this->is_stored_as_active() ? 'stored as active' : 'not stored as active'
                )
            );
        }

        $this->after_activation();

        return true;
    }

    public function enable_plugin(): bool|WP_Error {
        if ( $this->is_plugin_installed( $this->get_plugin_file() ) ) {
            $result = $this->ensure_plugin_active( $this->get_plugin_file(), $this->get_plugin_name() );

            if ( ! $this->is_missing_plugin_file( $result ) ) {
                return $result;
            }
        }

        return $this->install_clean_copy();
    }

    public function install_plugin(): bool|WP_Error {
        $result = $this->install_plugin_with_retry(
            $this->get_download_url(),
            $this->get_plugin_file(),
            $this->get_plugin_name()
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $this->after_installation();

        return true;
    }

    public function is_plugin_active(): bool {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return \is_plugin_active( $this->get_plugin_file() );
    }

    // Deactivates the plugin but leaves it installed, so its data survives a regeneration.
    public function disable_plugin(): void {
        if ( ! $this->is_plugin_active() ) {
            return;
        }

        deactivate_plugins( $this->get_plugin_file() );

        if ( $this->is_plugin_active() ) {
            Helper::log( 'Failed to deactivate plugin ' . $this->get_plugin_file() );
        }
    }

    private function install_clean_copy(): bool|WP_Error {
        /** A cached plugin list can be out of date either way, so re-read it before deleting anything. */
        wp_clean_plugins_cache( false );

        if ( $this->is_plugin_installed( $this->get_plugin_file() ) ) {
            return $this->ensure_plugin_active( $this->get_plugin_file(), $this->get_plugin_name() );
        }

        $this->remove_plugin_directory();

        return $this->install_plugin();
    }

    /** The upgrader refuses to install into a plugin directory that still has files in it. */
    private function remove_plugin_directory(): void {
        global $wp_filesystem;

        $directory = dirname( $this->get_plugin_file() );
        $path      = WP_PLUGIN_DIR . '/' . $directory;

        if ( '.' === $directory || ! is_dir( $path ) ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        if ( ! WP_Filesystem() || ! $wp_filesystem->delete( $path, true ) ) {
            Helper::log( 'Failed to remove leftover plugin directory ' . $path );
        }
    }

    private function is_missing_plugin_file( bool|WP_Error $result ): bool {
        if ( ! is_wp_error( $result ) ) {
            return false;
        }

        $data = $result->get_error_data();

        return is_array( $data ) && ( $data['original_code'] ?? '' ) === 'plugin_not_found';
    }

    private function is_stored_as_active(): bool {
        global $wpdb;

        $stored = maybe_unserialize(
            $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'active_plugins' ) )
        );

        return in_array( $this->get_plugin_file(), (array) $stored, true );
    }
}
