<?php

namespace Hostinger\AiTheme\Builder;

use Hostinger\WpHelper\Requests\Client;
use Hostinger\WpHelper\Utils as Helper;
use Hostinger\AiTheme\Rest\Endpoints;

defined( 'ABSPATH' ) || exit;

class PageTypeDeterminer {
    use LlmJsonTrait;

    private const FALLBACK_PAGE_TYPE = 'home';

    private const PAGE_TYPES = [
        'store'     => 'sells or promotes physical or digital products, or is about an ecommerce store',
        'bookings'  => 'presents a place to stay, a venue or an appointment that visitors reserve',
        'menu'      => 'lists food or drinks',
        'blog'      => 'publishes articles or news',
        'courses'   => 'teaches through lessons, training, exam preparation or study material',
        'careers'   => 'lists jobs',
        'events'    => 'announces events',
        'portfolio' => 'shows past work or projects',
        'gallery'   => 'is mainly photos or videos',
        'pricing'   => 'is mainly plans and prices',
        'faq'       => 'is mainly questions and answers',
        'contact'   => 'is mainly a contact form or contact details',
        'team'      => 'is mainly about the people behind the business',
        'about'     => 'tells who a business, person or organisation is or their story',
        'services'  => 'describes what a business offers',
    ];

    private const GENERIC_PAGE_TYPES = [
        'page',
        'webpage',
        'website',
        'site',
        'content',
        'information',
        'informational',
        'detail',
        'details',
        'general',
        'generic',
        'other',
        'unknown',
        'design',
    ];

    private Client $client;
    private DomainResolver $domain_resolver;

    public function __construct( Client $client, ?DomainResolver $domain_resolver = null, ?Helper $helper = null ) {
        $this->client = $client;
        $helper = $helper ?? new Helper();
        $this->domain_resolver = $domain_resolver ?? new DomainResolver( $helper );
    }

    public function determine_page_type( string $description ): array {
        $system_message = [
            'role'    => 'system',
            'content' => $this->get_system_prompt(),
        ];

        $user_message = [
            'role'    => 'user',
            'content' => $description,
        ];

        $domain = $this->domain_resolver->get_current_domain();

        $request_body = [
            'domain'   => $domain,
            'messages' => [ $system_message, $user_message ],
        ];

        $response = $this->client->post( Endpoints::GENERATE_BLOCKS_ENDPOINT, json_encode( $request_body ) );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'error'   => $response->get_error_message(),
            ];
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            return [
                'success' => false,
                'error'   => 'Invalid response from AI service',
            ];
        }

        $body         = json_decode( wp_remote_retrieve_body( $response ), true );
        $content_data = $this->decode_llm_json( $body['data']['content'] ?? null );
        $page_type    = $this->normalize_page_type( $content_data['pageType'] ?? '' );

        if ( empty( $page_type ) ) {
            return [
                'success' => false,
                'error'   => 'No page type determined',
            ];
        }

        return [
            'success'  => true,
            'pageType' => $page_type,
        ];
    }

    private function get_system_prompt(): string {
        $types = '';
        foreach ( self::PAGE_TYPES as $type => $test ) {
            $types .= "- {$type}: the page {$test}\n";
        }

        return 'Classify the web page the user describes. For a homepage or a whole website, classify its main content. '
            . 'Go through this list in order and answer with the first type that fits; when two types fit, the one higher in the list wins:' . "\n"
            . $types
            . '- ' . self::FALLBACK_PAGE_TYPE . ': only when no type above fits at all, such as a form, a tool or a request that only asks for design changes' . "\n"
            . 'The description can be in any language; always answer with one of the lowercase English types above.' . "\n"
            . 'Output only this JSON object: {"pageType": "<type>"}';
    }

    private function normalize_page_type( mixed $page_type ): string {
        if ( ! is_string( $page_type ) || trim( $page_type ) === '' ) {
            return '';
        }

        $words = preg_split( '/[\s_]+/u', mb_strtolower( trim( $page_type ) ), -1, PREG_SPLIT_NO_EMPTY );
        $words = array_map( static fn( string $word ): string => preg_replace( '/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $word ), $words );
        $words = array_values( array_diff( array_filter( $words, 'strlen' ), self::GENERIC_PAGE_TYPES ) );

        return $words[0] ?? self::FALLBACK_PAGE_TYPE;
    }
}
