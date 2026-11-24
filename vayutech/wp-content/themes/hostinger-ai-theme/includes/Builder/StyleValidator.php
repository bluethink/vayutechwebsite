<?php

namespace Hostinger\AiTheme\Builder;

defined( 'ABSPATH' ) || exit;

class StyleValidator {
    public const PALETTE_COLOR_KEYS = array(
        'color1',
        'color2',
        'color3',
        'light',
        'dark',
        'grey',
        'page_background',
        'page_text',
        'pageBackground',
        'pageText',
    );

    private const HEX_COLOR_PATTERN = '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/';

    private const THEME_COLOR_PATTERN = '/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/';

    private const GRADIENT_STOPS_PATTERN = '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})(?:\s*,\s*#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}))*$/';

    private const FONT_FAMILY_PATTERN = '/^\s*(?:[A-Za-z0-9-][A-Za-z0-9 _-]*|"[A-Za-z0-9 _-]+"|\'[A-Za-z0-9 _-]+\')\s*(?:,\s*(?:[A-Za-z0-9-][A-Za-z0-9 _-]*|"[A-Za-z0-9 _-]+"|\'[A-Za-z0-9 _-]+\')\s*)*$/';

    private const FONT_PRESET_PATTERN = '/^(?:var:preset\|font-family\|[a-z0-9-]+|var\(--wp--preset--font-family--[a-z0-9-]+\))$/';

    private const SAFE_CSS_VALUE_PATTERN = '/^[\p{L}\p{N}\s#%.,:|()\'"_+*\/-]*$/u';

    private const SLUG_PATTERN = '/^[a-z0-9-]+$/';

    private const THEME_JSON_KEY_PATTERN = '/^[A-Za-z0-9_:\/-]+$/';

    private const ELEMENTOR_ID_PATTERN = '/^[A-Za-z0-9]+$/';

    private const MAX_VALUE_LENGTH = 500;

    private const THEME_JSON_ROOT_KEYS = array( 'version', 'settings', 'styles' );

    private const THEME_JSON_DROPPED_KEYS = array( 'css', 'fontFace', 'backgroundImage' );

    private const CSS_FUNCTIONS = array(
        'var',
        'calc',
        'clamp',
        'min',
        'max',
        'rgb',
        'rgba',
        'hsl',
        'hsla',
        'color-mix',
        'linear-gradient',
        'radial-gradient',
        'conic-gradient',
        'repeating-linear-gradient',
        'repeating-radial-gradient',
        'cubic-bezier',
        'minmax',
        'repeat',
        'fit-content',
    );

    private const ELEMENTOR_SIZE_UNITS = array( 'px', 'em', 'rem', '%', 'vw', 'vh' );

    private const ELEMENTOR_SIZE_KEYS = array(
        'typography_font_size',
        'typography_line_height',
        'typography_letter_spacing',
        'typography_word_spacing',
    );

    private const ELEMENTOR_CHOICE_KEYS = array(
        'typography_font_weight'     => array( '', '100', '200', '300', '400', '500', '600', '700', '800', '900', 'normal', 'bold' ),
        'typography_text_transform'  => array( '', 'uppercase', 'lowercase', 'capitalize', 'none' ),
        'typography_font_style'      => array( '', 'normal', 'italic', 'oblique' ),
        'typography_text_decoration' => array( '', 'underline', 'overline', 'line-through', 'none' ),
    );

    private const ELEMENTOR_DEVICE_SUFFIXES = array( '', '_tablet', '_mobile' );

    public static function sanitize_hex_color( mixed $value ): string {
        if ( ! is_string( $value ) || ! preg_match( self::HEX_COLOR_PATTERN, trim( $value ) ) ) {
            return '';
        }

        return trim( $value );
    }

    public static function sanitize_theme_color( mixed $value ): string {
        if ( ! is_string( $value ) || ! preg_match( self::THEME_COLOR_PATTERN, trim( $value ) ) ) {
            return '';
        }

        return trim( $value );
    }

    public static function sanitize_gradient_stops( mixed $value ): string {
        if ( ! is_string( $value ) || ! preg_match( self::GRADIENT_STOPS_PATTERN, trim( $value ) ) ) {
            return '';
        }

        return trim( $value );
    }

    public static function sanitize_palette( array $palette ): array {
        foreach ( $palette as $key => $value ) {
            if ( $key === 'gradients' ) {
                $palette[ $key ] = self::sanitize_gradients( $value );

                continue;
            }

            if ( $key === 'color_palette' && is_array( $value ) ) {
                $palette[ $key ] = self::sanitize_palette( $value );

                continue;
            }

            if ( ! in_array( $key, self::PALETTE_COLOR_KEYS, true ) ) {
                continue;
            }

            $color = self::sanitize_hex_color( $value );

            if ( $color === '' ) {
                unset( $palette[ $key ] );

                continue;
            }

            $palette[ $key ] = $color;
        }

        return $palette;
    }

    public static function sanitize_font_family( mixed $value ): string {
        if ( ! is_string( $value ) || strlen( $value ) > self::MAX_VALUE_LENGTH ) {
            return '';
        }

        if ( ! preg_match( self::FONT_FAMILY_PATTERN, $value ) ) {
            return '';
        }

        return trim( $value );
    }

    public static function sanitize_theme_font_family( mixed $value ): string {
        if ( is_string( $value ) && preg_match( self::FONT_PRESET_PATTERN, $value ) ) {
            return $value;
        }

        return self::sanitize_font_family( $value );
    }

    public static function is_safe_css_value( string $value ): bool {
        if ( strlen( $value ) > self::MAX_VALUE_LENGTH || ! preg_match( self::SAFE_CSS_VALUE_PATTERN, $value ) ) {
            return false;
        }

        if ( str_contains( $value, '/*' ) || str_contains( $value, '*/' ) ) {
            return false;
        }

        if ( substr_count( $value, '"' ) % 2 !== 0 || substr_count( $value, "'" ) % 2 !== 0 ) {
            return false;
        }

        return self::has_balanced_parentheses( $value ) && self::uses_allowed_css_functions( $value );
    }

    public static function sanitize_theme_json( array $theme_json ): array {
        $sanitized = array();

        foreach ( $theme_json as $key => $value ) {
            if ( ! in_array( $key, self::THEME_JSON_ROOT_KEYS, true ) ) {
                continue;
            }

            if ( $key === 'version' ) {
                if ( is_int( $value ) ) {
                    $sanitized[ $key ] = $value;
                }

                continue;
            }

            if ( is_array( $value ) ) {
                $sanitized[ $key ] = self::sanitize_theme_json_node( $value );
            }
        }

        if ( isset( $sanitized['settings']['color']['palette'] ) ) {
            $sanitized['settings']['color']['palette'] = self::sanitize_palette_presets( $sanitized['settings']['color']['palette'] );
        }

        if ( isset( $sanitized['settings']['typography']['fontFamilies'] ) ) {
            $sanitized['settings']['typography']['fontFamilies'] = self::sanitize_font_family_presets( $sanitized['settings']['typography']['fontFamilies'] );
        }

        return $sanitized;
    }

    public static function sanitize_elementor_typography( array $entries ): array {
        $sanitized = array();

        foreach ( $entries as $entry ) {
            if ( ! is_array( $entry ) ) {
                continue;
            }

            $sanitized_entry = self::sanitize_elementor_typography_entry( $entry );

            if ( ! empty( $sanitized_entry ) ) {
                $sanitized[] = $sanitized_entry;
            }
        }

        return $sanitized;
    }

    private static function sanitize_gradients( mixed $gradients ): array {
        if ( ! is_array( $gradients ) ) {
            return array();
        }

        $sanitized = array();

        foreach ( $gradients as $gradient_id => $gradient ) {
            if ( ! preg_match( '/^[A-Za-z0-9_-]+$/', (string) $gradient_id ) || ! is_array( $gradient ) ) {
                continue;
            }

            $stops = self::sanitize_gradient_stops( $gradient['gradient'] ?? null );

            if ( $stops === '' ) {
                continue;
            }

            $sanitized[ $gradient_id ] = array_merge( $gradient, array( 'gradient' => $stops ) );
        }

        return $sanitized;
    }

    private static function sanitize_theme_json_node( array $node ): array {
        $sanitized = array();

        foreach ( $node as $key => $value ) {
            if ( is_string( $key ) && ( in_array( $key, self::THEME_JSON_DROPPED_KEYS, true ) || ! preg_match( self::THEME_JSON_KEY_PATTERN, $key ) ) ) {
                continue;
            }

            $sanitized_value = self::sanitize_theme_json_value( (string) $key, $value );

            if ( $sanitized_value !== null ) {
                $sanitized[ $key ] = $sanitized_value;
            }
        }

        if ( array_is_list( $node ) ) {
            return array_values( $sanitized );
        }

        return $sanitized;
    }

    private static function sanitize_theme_json_value( string $key, mixed $value ): mixed {
        if ( is_array( $value ) ) {
            return self::sanitize_theme_json_node( $value );
        }

        if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
            return $value;
        }

        if ( ! is_string( $value ) ) {
            return null;
        }

        if ( $key === 'fontFamily' ) {
            $font_family = self::sanitize_theme_font_family( $value );

            return $font_family === '' ? null : $value;
        }

        return self::is_safe_css_value( $value ) ? $value : null;
    }

    private static function sanitize_palette_presets( mixed $palette ): array {
        if ( ! is_array( $palette ) ) {
            return array();
        }

        $sanitized = array();

        foreach ( $palette as $entry ) {
            if ( ! is_array( $entry ) || ! self::is_valid_slug( $entry['slug'] ?? null ) ) {
                continue;
            }

            if ( self::sanitize_theme_color( $entry['color'] ?? null ) === '' ) {
                continue;
            }

            $sanitized[] = $entry;
        }

        return $sanitized;
    }

    private static function sanitize_font_family_presets( mixed $font_families ): array {
        if ( ! is_array( $font_families ) ) {
            return array();
        }

        $sanitized = array();

        foreach ( $font_families as $entry ) {
            if ( ! is_array( $entry ) || ! self::is_valid_slug( $entry['slug'] ?? null ) ) {
                continue;
            }

            if ( self::sanitize_font_family( $entry['fontFamily'] ?? null ) === '' ) {
                continue;
            }

            $sanitized[] = $entry;
        }

        return $sanitized;
    }

    private static function sanitize_elementor_typography_entry( array $entry ): array {
        $id = $entry['_id'] ?? null;

        if ( ! ( is_string( $id ) || is_int( $id ) ) || ! preg_match( self::ELEMENTOR_ID_PATTERN, (string) $id ) ) {
            return array();
        }

        $sanitized = array();

        foreach ( $entry as $key => $value ) {
            $sanitized_value = self::sanitize_elementor_typography_value( (string) $key, $value );

            if ( $sanitized_value !== null ) {
                $sanitized[ $key ] = $sanitized_value;
            }
        }

        return $sanitized;
    }

    private static function sanitize_elementor_typography_value( string $key, mixed $value ): mixed {
        if ( $key === '_id' ) {
            return $value;
        }

        if ( $key === 'title' ) {
            return is_string( $value ) && self::is_safe_css_value( $value ) ? $value : null;
        }

        if ( $key === 'typography_typography' ) {
            return $value === 'custom' ? $value : null;
        }

        if ( $key === 'typography_font_family' ) {
            $font_family = self::sanitize_font_family( $value );

            return $font_family === '' ? null : $font_family;
        }

        $base_key = self::strip_device_suffix( $key );

        if ( in_array( $base_key, self::ELEMENTOR_SIZE_KEYS, true ) ) {
            return self::sanitize_elementor_size( $value );
        }

        if ( isset( self::ELEMENTOR_CHOICE_KEYS[ $base_key ] ) ) {
            return is_string( $value ) && in_array( $value, self::ELEMENTOR_CHOICE_KEYS[ $base_key ], true ) ? $value : null;
        }

        return null;
    }

    private static function sanitize_elementor_size( mixed $value ): ?array {
        if ( ! is_array( $value ) ) {
            return null;
        }

        $sanitized = array();

        foreach ( $value as $key => $item ) {
            if ( $key === 'unit' ) {
                if ( ! in_array( $item, self::ELEMENTOR_SIZE_UNITS, true ) ) {
                    return null;
                }

                $sanitized[ $key ] = $item;

                continue;
            }

            if ( $key === 'size' ) {
                if ( ! self::is_elementor_size_number( $item ) ) {
                    return null;
                }

                $sanitized[ $key ] = $item;

                continue;
            }

            if ( $key === 'sizes' ) {
                $sanitized[ $key ] = array();
            }
        }

        return $sanitized;
    }

    private static function is_elementor_size_number( mixed $value ): bool {
        if ( is_int( $value ) || is_float( $value ) || $value === '' ) {
            return true;
        }

        return is_string( $value ) && (bool) preg_match( '/^-?\d+(?:\.\d+)?$/', $value );
    }

    private static function strip_device_suffix( string $key ): string {
        foreach ( self::ELEMENTOR_DEVICE_SUFFIXES as $suffix ) {
            if ( $suffix !== '' && str_ends_with( $key, $suffix ) ) {
                return substr( $key, 0, -strlen( $suffix ) );
            }
        }

        return $key;
    }

    private static function is_valid_slug( mixed $slug ): bool {
        return is_string( $slug ) && (bool) preg_match( self::SLUG_PATTERN, $slug );
    }

    private static function has_balanced_parentheses( string $value ): bool {
        $depth = 0;

        foreach ( str_split( $value ) as $character ) {
            if ( $character === '(' ) {
                ++$depth;
            }

            if ( $character === ')' ) {
                --$depth;
            }

            if ( $depth < 0 ) {
                return false;
            }
        }

        return $depth === 0;
    }

    private static function uses_allowed_css_functions( string $value ): bool {
        if ( ! preg_match_all( '/([\p{L}\p{N}_-]*)\s*\(/u', $value, $matches ) ) {
            return true;
        }

        foreach ( $matches[1] as $function_name ) {
            if ( $function_name === '' ) {
                continue;
            }

            if ( ! in_array( strtolower( $function_name ), self::CSS_FUNCTIONS, true ) ) {
                return false;
            }
        }

        return true;
    }
}
