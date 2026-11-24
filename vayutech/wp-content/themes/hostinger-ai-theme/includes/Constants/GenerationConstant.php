<?php

namespace Hostinger\AiTheme\Constants;

defined( 'ABSPATH' ) || exit;

class GenerationConstant {
    public const META_KEY = '_hostinger_ai_generated';

    public const GENERATED_POST_TYPES = array( 'page', 'post', 'product', 'attachment', 'wp_navigation' );

    public const STATE_OPTION = 'hostinger_ai_generation_state';

    public const STATE_IN_PROGRESS = 'in_progress';

    public const STATE_COMPLETE = 'complete';

    public const TEMPLATE_PARTS_READY_OPTION = 'hostinger_ai_template_parts_ready';

    public const MAX_PAGES = 8;
}
