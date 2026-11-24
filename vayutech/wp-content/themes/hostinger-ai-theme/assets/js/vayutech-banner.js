(() => {
    'use strict';
    document.querySelectorAll('.vts-banner').forEach((banner) => {
        const slides = [...banner.querySelectorAll('.vts-banner__slide')];
        const dots = [...banner.querySelectorAll('.vts-banner__dot')];
        const pause = banner.querySelector('.vts-banner__pause');
        const region = banner.querySelector('.vts-banner__slides');
        const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        if (slides.length < 2 || !pause) return;
        let index = 0;
        let paused = motion.matches;
        let timer;
        const show = (next) => {
            index = (next + slides.length) % slides.length;
            slides.forEach((slide, i) => { slide.hidden = i !== index; });
            dots.forEach((dot, i) => dot.setAttribute('aria-current', String(i === index)));
        };
        const schedule = () => {
            window.clearTimeout(timer);
            const label = paused ? 'Play slideshow' : 'Pause slideshow';
            pause.setAttribute('aria-label', label);
            pause.title = label;
            pause.dataset.paused = String(paused);
            region.setAttribute('aria-live', paused ? 'polite' : 'off');
            if (!paused && !document.hidden) {
                timer = window.setTimeout(() => { show(index + 1); schedule(); }, 6500);
            }
        };
        const select = (next) => {
            show(next);
            schedule();
        };
        dots.forEach((dot, i) => dot.addEventListener('click', () => select(i)));
        pause.addEventListener('click', () => { paused = !paused; schedule(); });
        // Keyboard focus stops rotation until the user explicitly restarts it.
        banner.addEventListener('focusin', (event) => {
            if (event.target !== pause && event.target.matches(':focus-visible')) {
                paused = true;
                schedule();
            }
        });
        document.addEventListener('visibilitychange', schedule);
        motion.addEventListener('change', () => { if (motion.matches) paused = true; schedule(); });
        banner.querySelector('.vts-banner__controls').hidden = false;
        show(0);
        schedule();
    });
})();
