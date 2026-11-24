<?php
/** VayuTech's responsive public-site header. */
defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', function () {
    $file = get_template_directory() . '/assets/css/vayutech-header.css';
    wp_register_style( 'vayutech-header', false, array(), filemtime( $file ) );
    wp_enqueue_style( 'vayutech-header' );
    wp_add_inline_style( 'vayutech-header', file_get_contents( $file ) );
}, 30 );

// Use the brand favicon on public pages without changing WordPress settings.
add_action( 'wp_head', function () {
    remove_action( 'wp_head', 'wp_site_icon', 99 );
    $favicon_file = get_template_directory() . '/assets/vayutech-favicon.svg';
    $favicon = add_query_arg(
        'ver',
        filemtime( $favicon_file ),
        wp_make_link_relative( get_template_directory_uri() . '/assets/vayutech-favicon.svg' )
    );
    echo '<link rel="icon" type="image/svg+xml" sizes="any" href="' . esc_url( $favicon ) . '">' . "\n";
} );

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
        // Replace only the homepage statistics strip; keep stored page content intact.
        if ( is_front_page() && is_main_query() && in_the_loop() ) {
            $content = preg_replace_callback( '~<section\b[^>]*>(?:(?!</section>).)*</section>~is', static function ( $match ) {
                foreach ( array( 'YTD turnover', 'Support capability', 'Delivery reach', 'Founded' ) as $label ) {
                    if ( strpos( $match[0], $label ) === false ) {
                        return $match[0];
                    }
                }
                $base = wp_make_link_relative( get_template_directory_uri() . '/assets/brand-strip/' );
                $brands = array(
                    array( 'mikrotik.svg', 'MikroTik', 51 ),
                    array( 'Cambium-Networks-logo.svg', 'Cambium Networks', 51 ),
                    array( 'grandstream3.png', 'Grandstream', 80 ),
                    array( 'Mimosa-logo.svg', 'Mimosa', 51 ),
                    array( 'tp-link.svg', 'TP-Link', 51 ),
                    array( 'optitera3.png', 'Optitera', 51 ),
                    array( 'fortinet-logo.svg', 'Fortinet', 28 ),
                    array( 'rad-logo.webp', 'RAD', 51 ),
                    array( 'RADWIN-website.png', 'RADWIN', 28 ),
                    array( 'ubiquiti3.png', 'Ubiquiti', 71 ),
                    array( 'airpro.png', 'AirPro', 51 ),
                    array( 'cisco1.png', 'Cisco', 51 ),
                    array( 'maipu3.png', 'Maipu', 80 ),
                    array( 'junipro.png', 'Juniper', 29 ),
                    array( 'digisol.png', 'Digisol', 58 ),
                );
                $html = '<section class="vts-logo-strip logo-strip" aria-label="Networking brands"><div class="vts-logo-track">';
                for ( $copy = 0; $copy < 2; $copy++ ) {
                    $html .= '<div class="vts-logo-group"' . ( $copy ? ' aria-hidden="true"' : '' ) . '>';
                    foreach ( $brands as $brand ) {
                        $html .= '<img src="' . esc_url( $base . $brand[0] ) . '" alt="' . esc_attr( $copy ? '' : $brand[1] ) . '" style="--logo-height:' . (int) $brand[2] . 'px" decoding="async">';
                    }
                    $html .= '</div>';
                }
                return $html . '</div></section>';
            }, $content );
            $content = preg_replace_callback( '~<section\b[^>]*>(?:(?!</section>).)*</section>~is', static function ( $match ) {
                if ( strpos( $match[0], 'ABOUT OUR COMPANY' ) === false || strpos( $match[0], 'Technology infrastructure with a practical point of view.' ) === false ) {
                    return $match[0];
                }
                $image = esc_url( wp_make_link_relative( get_template_directory_uri() . '/assets/images/vayutech-about.png' ) );
                return <<<HTML
<section class="vts-about" id="section-about" aria-labelledby="vts-about-title">
    <div class="vts-about__grid">
        <figure class="vts-about__image">
            <img src="{$image}" alt="Orange optical fiber cables connected to network equipment" width="1122" height="1402" loading="lazy" decoding="async">
        </figure>
        <div class="vts-about__content">
            <p class="vts-about__eyebrow">ABOUT US</p>
            <h2 id="vts-about-title">Empowering connectivity.<br>Enabling innovation.</h2>
            <p><strong>Vayutech Solutions</strong> is one of India's fastest-growing and most promising emerging distribution brands in the optical fiber networking sector. Specializing in network design and solutions, we are also deeply involved in <strong>system integration and infrastructure projects</strong>, backed by state-of-the-art manufacturing facilities located in the Delhi-NCR region.</p>
            <p>Driven by innovation, VTS was founded by a team of industry professionals with extensive expertise in product design and sales.</p>
            <details class="vts-about__details">
                <summary><span class="vts-about__more">Read more</span><span class="vts-about__less">Read less</span></summary>
                <div class="vts-about__expanded">
                    <p>At VTS, we understand the critical importance of our customers’ time and investments. That’s why we provide <strong>round-the-clock service</strong> and continuously strive to enhance our offerings, ensuring smooth and uninterrupted network operations.</p>
                    <p>We are proud contributors to the Startup India initiative and are registered under MSME.</p>
                    <p>VTS has established itself as a <strong>leading technology enterprise</strong>, delivering reliable and affordable solutions in:</p>
                    <ul>
                        <li><strong>Optical Fiber Networks</strong></li>
                        <li><strong>Wireless Communication Systems</strong></li>
                        <li><strong>Infrastructure and Network Integration</strong></li>
                        <li><strong>Security and Surveillance Solutions</strong></li>
                    </ul>
                    <p>Our focus is on crafting <strong>high-quality, high-performance networking solutions</strong> tailored to the needs of both large enterprises and small-scale deployments. Every product we sell is <strong>meticulously engineered</strong> to deliver the ideal balance of <strong>performance, durability, and cost-effectiveness</strong>.</p>
                    <p><strong>We are VTS</strong> – empowering connectivity, enabling innovation, and building a digitally stronger India.</p>
                </div>
            </details>
        </div>
    </div>
</section>
HTML;
            }, $content );
        }
    }
    return $content;
}, 20 );

add_action( 'wp_body_open', function () {
    $home = wp_make_link_relative( home_url( '/' ) );
    $logo = wp_make_link_relative( get_template_directory_uri() . '/assets/vayutech-logo.png' );
    $links = array( 'About' => 'about-us/', 'Products' => 'products/', 'Services' => 'solutions/', 'Contact' => 'contact/' );
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
