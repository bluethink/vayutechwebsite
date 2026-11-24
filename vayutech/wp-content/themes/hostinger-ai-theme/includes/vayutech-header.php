<?php
/** VayuTech's responsive public-site header. */
defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', function () {
    $file = get_template_directory() . '/assets/css/vayutech-header.css';
    wp_register_style( 'vayutech-header', false, array(), filemtime( $file ) );
    wp_enqueue_style( 'vayutech-header' );
    wp_add_inline_style( 'vayutech-header', file_get_contents( $file ) );
}, 30 );

// Replace the theme header, including database-customized template parts.
add_filter( 'pre_render_block', function ( $rendered, $block ) {
    if ( ! is_admin() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST )
        && ( $block['blockName'] ?? '' ) === 'core/template-part'
        && ( $block['attrs']['slug'] ?? '' ) === 'header' ) {
        return '';
    }
    return $rendered;
}, 5, 2 );

// Keep the existing page contact information consistent without editing stored content.
add_filter( 'the_content', function ( $content ) {
    if ( ! is_admin() && ! is_feed() ) {
        $content = str_replace( 'vayu.techsolution@gmail.com', 'info@vayutechsolutions.net', $content );
        // Remove the legacy contact strip; contact links now live in the header.
        $content = preg_replace(
            '~<div style="background:\#171717;color:\#fff;padding:10px 7%;font-size:13px;">\s*<div[^>]*><span>VayuTech Solutions · Noida, Uttar Pradesh</span><span>info@vayutechsolutions\.net · 24/7 support</span></div>\s*</div>~u',
            '',
            $content
        );
        // Normalize inline page accents without changing stored content or image URLs.
        $tags = new WP_HTML_Tag_Processor( $content );
        $palette = array(
            '#c54b24' => 'var(--vts-brand)',
            '#bd4924' => 'var(--vts-brand)',
            '#f54c08' => 'var(--vts-brand)',
            '#f18a5d' => 'var(--vts-brand-light)',
            '#ead6cc' => '#efd0c3',
        );
        while ( $tags->next_tag() ) {
            $style = $tags->get_attribute( 'style' );
            if ( is_string( $style ) ) {
                $style = preg_replace_callback(
                    '/#[0-9a-f]{3,8}\b/i',
                    static function ( $match ) use ( $palette ) {
                        return $palette[ strtolower( $match[0] ) ] ?? $match[0];
                    },
                    $style
                );
                $tags->set_attribute( 'style', $style );
            }
        }
        $content = $tags->get_updated_html();
    }
    return $content;
}, 20 );

add_action( 'wp_body_open', function () {
    $home = wp_make_link_relative( home_url( '/' ) );
    $logo = wp_make_link_relative( get_template_directory_uri() . '/assets/vayutech-logo.png' );
    $links = array( 'About' => 'about-us/', 'Products' => 'products/', 'Solutions' => 'solutions/', 'Contact' => 'contact/' );
    ?>
    <header class="vts-header">
        <div class="vts-header__row">
            <a class="vts-brand" href="<?php echo esc_url( $home ); ?>" aria-label="VayuTech Solutions home">
                <img src="<?php echo esc_url( $logo ); ?>" width="1952" height="806" alt="VTS Innovate — VayuTech Solutions" fetchpriority="high">
            </a>
            <nav class="vts-nav" aria-label="Main navigation">
                <?php foreach ( $links as $label => $path ) : ?>
                    <a href="<?php echo esc_url( $home . $path ); ?>"><?php echo esc_html( $label ); ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="vts-contact">
                <a class="vts-phone" href="tel:+919818774334"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3H3c-1 10 8 19 18 18v-4l-5-2-2 2c-3-1-6-4-7-7l2-2z"/></svg>+91-9818774334</a>
                <a class="vts-email" href="mailto:info@vayutechsolutions.net">info@vayutechsolutions.net</a>
            </div>
            <a class="vts-quote" href="<?php echo esc_url( $home . 'contact/' ); ?>">Send query</a>
            <details class="vts-mobile-menu">
                <summary aria-label="Toggle navigation menu"><span></span><span></span><span></span></summary>
                <nav aria-label="Mobile navigation">
                    <?php foreach ( $links as $label => $path ) : ?>
                        <a href="<?php echo esc_url( $home . $path ); ?>"><?php echo esc_html( $label ); ?></a>
                    <?php endforeach; ?>
                    <a href="tel:+919818774334">+91-9818774334</a>
                    <a class="vts-mobile-email" href="mailto:info@vayutechsolutions.net">info@vayutechsolutions.net</a>
                </nav>
            </details>
        </div>
    </header>
    <?php
} );
