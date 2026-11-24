<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\EasyOnboarding\Dto\WooSetupParameters;
use Hostinger\EasyOnboarding\WooCommerce\SetupHandler;
use WC_Countries;

defined( 'ABSPATH' ) || exit;

class WooBuilder extends AbstractPluginBuilder {
    private const PLUGIN_FILE = 'woocommerce/woocommerce.php';
    private const PLUGIN_NAME = 'WooCommerce';
    private const DOWNLOAD_URL = 'https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip';

    /**
     * Central state/region codes for countries that require a state in WooCommerce.
     * Falls back to first available state if country not listed here.
     */
    private const COUNTRY_CENTRAL_STATES = [
        'AL' => 'AL-11',     // Albania - Tirana.
        'DZ' => 'DZ-16',     // Algeria - Algiers.
        'AO' => 'LUA',       // Angola - Luanda.
        'AR' => 'C',         // Argentina - Buenos Aires City.
        'AU' => 'ACT',       // Australia - Australian Capital Territory (Canberra).
        'BD' => 'BD-13',     // Bangladesh - Dhaka.
        'BJ' => 'OU',        // Benin - Ouémé (Porto-Novo).
        'BO' => 'BO-L',      // Bolivia - La Paz.
        'BR' => 'DF',        // Brazil - Distrito Federal (Brasília).
        'BG' => 'BG-22',     // Bulgaria - Sofia.
        'CA' => 'ON',        // Canada - Ontario (Ottawa).
        'CL' => 'CL-RM',     // Chile - Región Metropolitana de Santiago.
        'CN' => 'CN2',       // China - Beijing.
        'CO' => 'CO-DC',     // Colombia - Capital District (Bogotá).
        'CR' => 'CR-SJ',     // Costa Rica - San José.
        'HR' => 'HR-21',     // Croatia - Zagreb City.
        'DO' => 'DO-01',     // Dominican Republic - Distrito Nacional (Santo Domingo).
        'EC' => 'EC-P',      // Ecuador - Pichincha (Quito).
        'EG' => 'EGC',       // Egypt - Cairo.
        'SV' => 'SV-SS',     // El Salvador - San Salvador.
        'DE' => 'DE-BE',     // Germany - Berlin.
        'GH' => 'AA',        // Ghana - Greater Accra.
        'GR' => 'I',         // Greece - Attica (Athens).
        'GT' => 'GT-GU',     // Guatemala - Guatemala.
        'HN' => 'HN-FM',     // Honduras - Francisco Morazán (Tegucigalpa).
        'HK' => 'HONG KONG', // Hong Kong - Hong Kong Island.
        'HU' => 'BU',        // Hungary - Budapest.
        'IN' => 'DL',        // India - Delhi.
        'ID' => 'JK',        // Indonesia - DKI Jakarta.
        'IR' => 'THR',       // Iran - Tehran.
        'IE' => 'D',         // Ireland - Dublin.
        'IT' => 'RM',        // Italy - Roma.
        'JM' => 'JM-01',     // Jamaica - Kingston.
        'JP' => 'JP13',      // Japan - Tokyo.
        'KE' => 'KE30',      // Kenya - Nairobi County.
        'KN' => 'KN03',      // Saint Kitts and Nevis - Saint George Basseterre.
        'LA' => 'VT',        // Laos - Vientiane.
        'LR' => 'MO',        // Liberia - Montserrado (Monrovia).
        'MY' => 'KUL',       // Malaysia - Kuala Lumpur.
        'MX' => 'DF',        // Mexico - Ciudad de México.
        'MD' => 'C',         // Moldova - Chișinău.
        'MA' => 'marab',     // Morocco - Rabat.
        'MZ' => 'MZMPM',     // Mozambique - Maputo.
        'NA' => 'KH',        // Namibia - Khomas (Windhoek).
        'NP' => 'BAG',       // Nepal - Bagmati (Kathmandu).
        'NZ' => 'WGN',       // New Zealand - Wellington.
        'NI' => 'NI-MN',     // Nicaragua - Managua.
        'NG' => 'FC',        // Nigeria - Abuja (Federal Capital).
        'PK' => 'IS',        // Pakistan - Islamabad Capital Territory.
        'PA' => 'PA-8',      // Panama - Panamá.
        'PY' => 'PY-ASU',    // Paraguay - Asunción.
        'PE' => 'LIM',       // Peru - Lima.
        'PH' => '00',        // Philippines - Metro Manila.
        'RO' => 'B',         // Romania - București.
        'SN' => 'SNDK',      // Senegal - Dakar.
        'RS' => 'RS00',      // Serbia - Belgrade.
        'ZA' => 'GP',        // South Africa - Gauteng (Pretoria).
        'ES' => 'M',         // Spain - Madrid.
        'TZ' => 'TZ03',      // Tanzania - Dodoma.
        'TH' => 'TH-10',     // Thailand - Bangkok.
        'TR' => 'TR06',      // Türkiye - Ankara.
        'UG' => 'UG102',     // Uganda - Kampala.
        'UA' => 'UA30',      // Ukraine - Kyiv.
        'US' => 'DC',        // United States - District of Columbia (Washington D.C.).
        'UY' => 'UY-MO',     // Uruguay - Montevideo.
        'VE' => 'VE-A',      // Venezuela - Capital (Caracas).
        'ZM' => 'ZM-09',     // Zambia - Lusaka.
    ];

    protected function get_plugin_file(): string {
        return self::PLUGIN_FILE;
    }

    protected function get_plugin_name(): string {
        return self::PLUGIN_NAME;
    }

    protected function get_download_url(): string {
        return self::DOWNLOAD_URL;
    }

    protected function get_error_code(): string {
        return 'woocommerce';
    }

    protected function is_enabled(): bool {
        return get_option( 'hostinger_ai_woo', false );
    }

    protected function after_activation(): void {
        $this->setup_localization();
    }

    private function setup_localization(): void {
        if ( ! class_exists( 'Hostinger\EasyOnboarding\WooCommerce\SetupHandler' ) ) {
            return;
        }

        $parameters = array(
            'store_name' => get_option( 'hostinger_ai_brand_name', '' ),
            'industry' => '',
            'store_location' => $this->get_store_location(),
            'business_email' => get_option( 'admin_email' ),
        );

        $woo_parameters = WooSetupParameters::from_array( $parameters );
        $setup_handler = new SetupHandler( $woo_parameters );
        $setup_handler->setup();

        update_option( 'woocommerce_coming_soon', 'no' );
    }

    private function get_store_location(): string {
        $country = get_option( 'hostinger_country', 'US' );
        if ( ! class_exists( 'WC_Countries' ) ) {
            return $country;
        }

        $wc_countries = new WC_Countries();
        $states       = $wc_countries->get_states( $country );
        if ( empty( $states ) ) {
            return $country;
        }

        $state = self::COUNTRY_CENTRAL_STATES[ $country ] ?? array_key_first( $states );
        if ( ! isset( $states[ $state ] ) ) {
            $state = array_key_first( $states );
        }

        return $country . ':' . $state;
    }
}
