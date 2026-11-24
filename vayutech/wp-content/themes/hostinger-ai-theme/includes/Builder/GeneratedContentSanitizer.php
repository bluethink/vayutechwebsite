<?php

namespace Hostinger\AiTheme\Builder;

defined( 'ABSPATH' ) || exit;

class GeneratedContentSanitizer {

    private const URL_KEY_PATTERN = '/(?:url|href|src|_link)$/i';

    private const IFRAME_PATTERN = '#<iframe\b[^>]*>(?:.*?</iframe>)?#is';

    private const IFRAME_OPENING_TAG_PATTERN = '#^<iframe\b([^>]*)>#i';

    private const IFRAME_HOSTS = array( 'www.google.com', 'google.com', 'maps.google.com' );

    private const IFRAME_ATTRIBUTES = array(
        'src',
        'width',
        'height',
        'loading',
        'style',
        'title',
        'class',
        'referrerpolicy',
        'allowfullscreen',
        'frameborder',
    );

    private const MAX_MARKUP_PASSES = 3;

    public function sanitize_html( string $html ): string {
        $sanitized = wp_kses( $html, $this->allowed_html(), wp_allowed_protocols() );

        return (string) preg_replace_callback( self::IFRAME_PATTERN, array( $this, 'filter_iframe' ), $sanitized );
    }

    /**
     * Parses, sanitizes and re-serializes until the markup is stable, so block delimiters hidden inside
     * HTML chunks become real blocks whose attributes are sanitized too.
     */
    public function sanitize_block_markup( string $content ): string {
        for ( $pass = 0; $pass < self::MAX_MARKUP_PASSES; $pass++ ) {
            $sanitized = serialize_blocks( $this->sanitize_blocks( parse_blocks( $content ) ) );

            if ( $sanitized === $content ) {
                return $sanitized;
            }

            $content = $sanitized;
        }

        return $content;
    }

    public function sanitize_blocks( array $blocks ): array {
        return array_map(
            fn( $block ) => is_array( $block ) ? $this->sanitize_block( $block ) : $block,
            $blocks
        );
    }

    public function sanitize_elementor( array $elements ): array {
        return array_map(
            fn( $element ) => is_array( $element ) ? $this->sanitize_elementor_element( $element ) : $element,
            $elements
        );
    }

    public function filter_iframe( array $matches ): string {
        if ( ! preg_match( self::IFRAME_OPENING_TAG_PATTERN, $matches[0], $opening_tag ) ) {
            return '';
        }

        $attributes = wp_kses_hair( $opening_tag[1], wp_allowed_protocols() );

        if ( ! isset( $attributes['src'] ) || ! $this->is_allowed_iframe_source( $attributes['src']['value'] ) ) {
            return '';
        }

        return $this->build_iframe( $attributes );
    }

    private function is_allowed_iframe_source( string $source ): bool {
        $url = wp_parse_url( html_entity_decode( $source, ENT_QUOTES ) );

        if ( ! is_array( $url ) || ( $url['scheme'] ?? '' ) !== 'https' ) {
            return false;
        }

        if ( ! in_array( strtolower( $url['host'] ?? '' ), self::IFRAME_HOSTS, true ) ) {
            return false;
        }

        return str_starts_with( $url['path'] ?? '', '/maps' );
    }

    private function build_iframe( array $attributes ): string {
        $output = '<iframe';

        foreach ( self::IFRAME_ATTRIBUTES as $name ) {
            if ( ! isset( $attributes[ $name ] ) ) {
                continue;
            }

            $output .= $this->build_iframe_attribute( $name, $attributes[ $name ] );
        }

        return $output . '></iframe>';
    }

    private function build_iframe_attribute( string $name, array $attribute ): string {
        if ( ( $attribute['vless'] ?? 'n' ) === 'y' ) {
            return ' ' . $name;
        }

        return sprintf( ' %s="%s"', $name, esc_attr( $attribute['value'] ) );
    }

    private function sanitize_block( array $block ): array {
        if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
            $block['attrs'] = $this->sanitize_values( $block['attrs'] );
        }

        if ( isset( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) ) {
            $block['innerHTML'] = $this->sanitize_html( $block['innerHTML'] );
        }

        if ( isset( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
            $block['innerContent'] = array_map(
                fn( $chunk ) => is_string( $chunk ) ? $this->sanitize_html( $chunk ) : $chunk,
                $block['innerContent']
            );
        }

        if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
            $block['innerBlocks'] = $this->sanitize_blocks( $block['innerBlocks'] );
        }

        return $block;
    }

    private function sanitize_elementor_element( array $element ): array {
        if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
            $element['settings'] = $this->sanitize_values( $element['settings'] );
        }

        if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
            $element['elements'] = $this->sanitize_elementor( $element['elements'] );
        }

        return $element;
    }

    private function sanitize_values( array $values ): array {
        foreach ( $values as $key => $value ) {
            $values[ $key ] = $this->sanitize_value( (string) $key, $value );
        }

        return $values;
    }

    private function sanitize_value( string $key, mixed $value ): mixed {
        if ( is_array( $value ) ) {
            return $this->sanitize_values( $value );
        }

        if ( ! is_string( $value ) ) {
            return $value;
        }

        if ( preg_match( self::URL_KEY_PATTERN, $key ) ) {
            return $this->sanitize_url( $value );
        }

        if ( ! str_contains( $value, '<' ) ) {
            return $value;
        }

        return $this->sanitize_html( $value );
    }

    private function sanitize_url( string $url ): string {
        $decoded = html_entity_decode( $url, ENT_QUOTES );

        if ( wp_kses_bad_protocol( $decoded, wp_allowed_protocols() ) !== $decoded ) {
            return '';
        }

        if ( str_contains( $url, '<' ) ) {
            return $this->sanitize_html( $url );
        }

        return $url;
    }

    private function allowed_html(): array {
        $allowed_html = wp_kses_allowed_html( 'post' );

        $allowed_html['iframe'] = array(
            'src'             => true,
            'width'           => true,
            'height'          => true,
            'loading'         => true,
            'style'           => true,
            'title'           => true,
            'class'           => true,
            'referrerpolicy'  => true,
            'allowfullscreen' => true,
            'frameborder'     => true,
        );

        return $allowed_html;
    }
}
