<?php

namespace Hostinger\AiTheme\Builder;

defined( 'ABSPATH' ) || exit;

class LegacyEngineCleanup {
    public const DONE_OPTION = 'hostinger_ai_legacy_engine_cleanup_done';

    public const EXPERIMENT_OPTIONS = array(
        'hostinger_ai_theme_new_engine_experiment',
        'hostinger_ai_theme_new_engine_experiment_v2',
        'hostinger_ai_theme_new_engine_experiment_v3',
    );

    private GenerationJobStore $jobs;

    public function __construct( GenerationJobStore $jobs ) {
        $this->jobs = $jobs;
    }

    public function maybe_run(): void {
        if ( get_option( self::DONE_OPTION ) ) {
            return;
        }

        foreach ( self::EXPERIMENT_OPTIONS as $option ) {
            delete_option( $option );
        }

        $this->reset_stale_generation_state();

        update_option( self::DONE_OPTION, 1 );
    }

    private function reset_stale_generation_state(): void {
        if ( ! GenerationState::is_in_progress() || $this->is_generation_job_running() ) {
            return;
        }

        GenerationState::reset();
    }

    private function is_generation_job_running(): bool {
        return $this->jobs->is_live( $this->jobs->get() ) || ! $this->jobs->start_claim_abandoned();
    }
}
