<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\AiTheme\Builder\Elementor\CssCache;
use Hostinger\AiTheme\Constants\BuilderType;
use Hostinger\AiTheme\Data\HostingerEcommerceHelper;
use Hostinger\AiTheme\Data\WebsiteTypeHelper;

defined( 'ABSPATH' ) || exit;

class WebsiteBuilder {
    /**
     * @var RequestClient
     */
    private RequestClient $request_client;
    private AffiliateBuilder $affiliate_builder;
    private ElementorBuilder $elementor_builder;
    private HostingerReachBuilder $hostinger_reach_builder;
    private WooBuilder $woo_builder;
    private HostingerEcommerceBuilder $ecommerce_builder;
    private Fonts $fonts;
    private RequestClient $wh_api_client;

    public function __construct( RequestClient $request_client, AffiliateBuilder $affiliate_builder, ElementorBuilder $elementor_builder, WooBuilder $woo_builder, HostingerReachBuilder $hostinger_reach_builder, HostingerEcommerceBuilder $ecommerce_builder, Fonts $fonts, RequestClient $wh_api_client ) {
        $this->request_client = $request_client;
        $this->affiliate_builder = $affiliate_builder;
        $this->elementor_builder = $elementor_builder;
        $this->woo_builder = $woo_builder;
        $this->hostinger_reach_builder = $hostinger_reach_builder;
        $this->ecommerce_builder = $ecommerce_builder;
        $this->fonts = $fonts;
        $this->wh_api_client = $wh_api_client;
    }

    public function init() {
        add_filter( 'wp_theme_json_data_theme', [ $this, 'update_theme_json'], 999 );
    }

    public function update_theme_json( $theme_json) {

        $hostinger_ai_version = get_option( 'hostinger_ai_version', false );

        if(empty($hostinger_ai_version)) {
            return $theme_json;
        }

		$heading_font = $this->get_theme_json_heading_typography( $theme_json );
		$body_font = $this->get_theme_json_typography( $theme_json, $heading_font );

        $new_data = array(
            'version'  => $hostinger_ai_version,
            'settings' => array(
                'color' => $this->get_theme_json_colors()
            ),
            'styles' => array(
                'color' => array(
                    'background' => 'var(--wp--preset--color--page-background)',
                    'text'       => 'var(--wp--preset--color--page-text)',
                ),
                'typography' => array(
	                'fontFamily' => $body_font,
                ),
                'elements' => array(
					'heading' => array(
						'typography' => array(
							'fontFamily' => $heading_font,
						),
					)
                )
            )
        );

        return $theme_json->update_with( $new_data );

    }

    /**
     * Get a random main font from the theme.json font family list.
     *
     * @param \WP_Theme_JSON_Data $theme_json
     * @return string The font family name.
     */
    protected function get_theme_json_heading_typography( \WP_Theme_JSON_Data $theme_json ): string {
        return $this->fonts->get_main_font( $theme_json->get_data() );
    }

	/**
	 * Get the body font based on the main font.
	 *
	 * @param \WP_Theme_JSON_Data $theme_json
	 * @param string $main_font
	 *
	 * @return string The font family name.
	 */
	protected function get_theme_json_typography( \WP_Theme_JSON_Data $theme_json, string $main_font ): string {
		return $this->fonts->get_body_font( $theme_json->get_data(), $main_font );
	}

    protected function get_theme_json_colors() {
        $hostinger_ai_colors = get_option( 'hostinger_ai_colors', false );

        if ( ! empty( $hostinger_ai_colors['color_palette'] ) ) {
            $hostinger_ai_colors = $hostinger_ai_colors['color_palette'];
        }

        if ( empty( $hostinger_ai_colors ) || ! is_array( $hostinger_ai_colors ) || empty( $hostinger_ai_colors['gradients'] ) ) {
            return array();
        }

        $hostinger_ai_colors = StyleValidator::sanitize_palette( $hostinger_ai_colors );

        $colors = array(
            'palette' => [
                [
                    'slug' => 'color1',
                    'color' => !empty($hostinger_ai_colors['color1']) ? $hostinger_ai_colors['color1'] : '',
                    'name' => 'Color 1 (Section backgrounds)'
                ],
                [
                    'slug' => 'color2',
                    'color' => !empty($hostinger_ai_colors['color2']) ? $hostinger_ai_colors['color2'] : '',
                    'name' => 'Color 2 (Section backgrounds)'
                ],
                [
                    'slug' => 'color3',
                    'color' => !empty($hostinger_ai_colors['color3']) ? $hostinger_ai_colors['color3'] : '',
                    'name' => 'Color 3 (Button background)'
                ],
                [
                    'slug' => 'light',
                    'color' => !empty($hostinger_ai_colors['light']) ? $hostinger_ai_colors['light'] : '',
                    'name' => 'Light (Text on Color 2 and Gradient)'
                ],
                [
                    'slug' => 'dark',
                    'color' => !empty($hostinger_ai_colors['dark']) ? $hostinger_ai_colors['dark'] : '',
                    'name' => 'Dark (Text on Light and Color 1)'
                ],
                [
                    'slug' => 'grey',
                    'color' => !empty($hostinger_ai_colors['grey']) ? $hostinger_ai_colors['grey'] : '',
                    'name' => 'Grey (Form borders)'
                ],
                [
                    'slug' => 'page-background',
                    'color' => !empty($hostinger_ai_colors['page_background']) ? $hostinger_ai_colors['page_background'] : '#ffffff',
                    'name' => 'Page background'
                ],
                [
                    'slug' => 'page-text',
                    'color' => !empty($hostinger_ai_colors['page_text']) ? $hostinger_ai_colors['page_text'] : '#000000',
                    'name' => 'Page text'
                ]
            ],
        );

        if ( empty( $hostinger_ai_colors['color3'] ) || empty( $hostinger_ai_colors['gradients'] ) ) {
            return $colors;
        }

        $gradient_key = array_key_first( $hostinger_ai_colors['gradients'] );

        $colors['gradients'] = array(
            array(
                'slug'     => 'gradient-one',
                'gradient' => 'linear-gradient(135deg, ' . $hostinger_ai_colors['color3'] . ' 0%, ' . $hostinger_ai_colors['gradients'][ $gradient_key ]['gradient'] . ' 100%)',
                'name'     => 'Section background gradient',
            ),
        );

        return $colors;
    }

    /**
     * @param string $description
     *
     * @return bool
     */
    public function generate_colors( string $description ): bool {
        return $this->make_colors( $description )->generate_colors();
    }

    public function generate_color_options( string $description ): array {
        return $this->make_colors( $description )->generate_color_options();
    }

    public function generate_font_options(): array {
        $this->fonts->setWhApiClient( $this->wh_api_client );

        return $this->fonts->generate_font_options();
    }

    public function set_colors( string $description, array $colors_palette): bool {
        return $this->make_colors( $description )->set_colors( $colors_palette );
    }

    private function make_colors( string $description ): Colors {
        $colors = new Colors( $description );
        $colors->setRequestClient( $this->request_client );
        $colors->setWhApiClient( $this->wh_api_client );

        return $colors;
    }

    public function enable_plugins(): array {
        $plugins = array(
            'elementor'   => array(
                'builder'          => $this->elementor_builder,
                'enabled_callback' => fn() => $this->elementor_builder->is_plugin_active(),
            ),
            'woocommerce' => array(
                'builder'          => $this->woo_builder,
                'enabled_callback' => fn() => get_option( 'hostinger_ai_woo', false ),
            ),
            'affiliate'   => array(
                'builder'          => $this->affiliate_builder,
                'enabled_callback' => fn() => get_option( 'hostinger_ai_affiliate', false ),
            ),
            'reach'       => array(
                'builder'          => $this->hostinger_reach_builder,
                'enabled_callback' => fn() => true,
            ),
            'ecommerce'   => array(
                'builder'          => $this->ecommerce_builder,
                'enabled_callback' => fn() => HostingerEcommerceHelper::is_active(),
            ),
        );

        $results = array();

        foreach ( $plugins as $name => $config ) {
            $results[ $name ] = $this->process_plugin_boot( $config['builder'], $config['enabled_callback'] );
        }

        return $results;
    }

    private function process_plugin_boot( AbstractPluginBuilder $builder, callable $enabled_callback ): array {
        $result = $builder->boot();

        if ( is_wp_error( $result ) ) {
            return array( 'status' => 'failed', 'error' => $result->get_error_message() );
        }

        if ( $result === true && $enabled_callback() ) {
            return array( 'status' => 'enabled', 'error' => null );
        }

        return array( 'status' => 'skipped', 'error' => null );
    }

    public static function reconcile_plugins( array $new_website_type, string $current_builder_type, string $new_builder_type ): void {
        $plugins = self::get_plugins_to_deactivate( $new_website_type, $current_builder_type, $new_builder_type );

        foreach ( $plugins as $plugin_file ) {
            self::deactivate_plugin( $plugin_file );
        }
    }

    private static function get_plugins_to_deactivate( array $new_website_type, string $current_builder_type, string $new_builder_type ): array {
        $plugins = array();

        if ( $current_builder_type === BuilderType::ELEMENTOR && $new_builder_type === BuilderType::GUTENBERG ) {
            $plugins[] = 'elementor/elementor.php';
        }

        if ( ! in_array( 'affiliate-marketing', $new_website_type, true ) ) {
            $plugins[] = 'hostinger-affiliate-plugin/hostinger-affiliate-plugin.php';
        }

        if ( ! WebsiteTypeHelper::contains( $new_website_type, 'online store' ) ) {
            $plugins[] = 'woocommerce/woocommerce.php';
        }

        return $plugins;
    }

    private static function deactivate_plugin( string $plugin_file ): void {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if ( ! is_plugin_active( $plugin_file ) ) {
            return;
        }

        deactivate_plugins( $plugin_file );

        if ( is_plugin_active( $plugin_file ) ) {
            error_log( 'Hostinger AI Theme: Failed to deactivate plugin ' . $plugin_file );
        }
    }

    public static function clear_elementor_cache(): void {
        $builder_type = get_option( 'hostinger_ai_builder_type', '' );

        if( $builder_type !== BuilderType::ELEMENTOR ) {
            return;
        }

        CssCache::clear();
    }
}
