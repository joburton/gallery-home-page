/**
 * Scroll inertia — momentum scrolling, or nothing whatsoever.
 *
 * Two requirements shape this entire module, and both are the reason it is ~120 lines
 * of our own rather than a library:
 *
 * 1. NEW INPUT MUST RETARGET MID-ANIMATION, IMMEDIATELY. A visitor scrolling again
 *    while momentum is still decaying must have that input take effect at once, never
 *    queued behind the current glide. This is the single detail separating momentum
 *    scrolling that feels good from momentum scrolling that feels broken. It is
 *    satisfied structurally here: `target` is a plain mutable number that every wheel
 *    event adds to, and the animation always eases toward whatever it currently holds.
 *    There is no queue to get stuck behind because there is no queue.
 *
 * 2. IT MUST DEGRADE TO GENUINELY NATIVE SCROLLING. When disabled — or when the OS
 *    asks for reduced motion — this attaches no listeners at all. Not a shortened
 *    animation: nothing. Keyboard scrolling, scrollbar dragging, find-in-page and
 *    mobile browser chrome all behave exactly as on a plain page, because nothing is
 *    intercepting them.
 *
 * Only `wheel` is ever intercepted. Touch, keyboard, scrollbar and programmatic scrolls
 * are left alone, and the animation resyncs to them rather than fighting them.
 *
 * It drives WINDOW scroll, not an inner scroller: the row must not own the viewport,
 * and on the real homepage the momentum has to carry into the rows below it.
 */

/** Wheel-delta multiplier per speed setting. */
const SPEED = { slow: 0.55, medium: 1, fast: 1.7 };

/**
 * Per-feel easing.
 *
 * `smooth` and `glide` are exponential approaches — glide uses a smaller factor so it
 * carries further. `overshoot` is a spring: it accumulates velocity and is underdamped,
 * so it passes the target slightly and settles back.
 */
const FEEL = {
  smooth: { kind: 'ease', factor: 0.14 },
  glide: { kind: 'ease', factor: 0.065 },
  overshoot: { kind: 'spring', stiffness: 0.085, damping: 0.78 },
};

/**
 * Attaches momentum scrolling, or nothing at all.
 *
 * @param {HTMLElement} root Gallery row root (read for settings; scrolling is on window).
 * @param {{enabled: boolean, speed: 'slow'|'medium'|'fast', feel: 'smooth'|'glide'|'overshoot'}} settings
 * @returns {{ destroy: () => void, isActive: () => boolean }}
 */
export function initInertia(root, settings) {
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');

  const speed = SPEED[settings.speed] ?? SPEED.medium;
  const feel = FEEL[settings.feel] ?? FEEL.smooth;

  let attached = false;
  let frame = null;
  let target = 0;
  let velocity = 0;

  const maxScroll = () =>
    Math.max(0, document.documentElement.scrollHeight - window.innerHeight);

  const stop = () => {
    if (frame !== null) cancelAnimationFrame(frame);
    frame = null;
    velocity = 0;
  };

  const step = () => {
    const current = window.scrollY;
    const distance = target - current;

    if (feel.kind === 'spring') {
      velocity += distance * feel.stiffness;
      velocity *= feel.damping;
      // Settle only when both the offset and the residual motion are spent, so the
      // rubber-band actually completes instead of being cut off at the target.
      if (Math.abs(distance) < 0.4 && Math.abs(velocity) < 0.4) {
        window.scrollTo(0, target);
        stop();
        return;
      }
      window.scrollTo(0, current + velocity);
    } else {
      if (Math.abs(distance) < 0.4) {
        window.scrollTo(0, target);
        stop();
        return;
      }
      window.scrollTo(0, current + distance * feel.factor);
    }

    frame = requestAnimationFrame(step);
  };

  const onWheel = (event) => {
    // Let the browser handle zoom and horizontal intent untouched.
    if (event.ctrlKey || event.metaKey) return;
    if (Math.abs(event.deltaX) > Math.abs(event.deltaY)) return;

    event.preventDefault();

    // THE RETARGET. If no animation is running, start from where the page actually is
    // — which may have been moved by the keyboard, the scrollbar or find-in-page since
    // the last wheel event. If one IS running, add to the existing target so the new
    // input compounds and takes effect on the very next frame.
    if (frame === null) target = window.scrollY;

    const lineHeight = 16;
    const delta = event.deltaMode === 1
      ? event.deltaY * lineHeight
      : event.deltaMode === 2
        ? event.deltaY * window.innerHeight
        : event.deltaY;

    target = Math.max(0, Math.min(maxScroll(), target + delta * speed));

    if (frame === null) frame = requestAnimationFrame(step);
  };

  // If the page is scrolled by anything other than us, abandon the animation rather
  // than dragging the visitor back to a stale target.
  const onScrollFromElsewhere = () => {
    if (frame === null) target = window.scrollY;
  };

  const onKeyOrTouch = () => stop();

  const attach = () => {
    if (attached) return;
    target = window.scrollY;
    // passive:false because preventDefault is the point.
    window.addEventListener('wheel', onWheel, { passive: false });
    window.addEventListener('keydown', onKeyOrTouch, { passive: true });
    window.addEventListener('touchstart', onKeyOrTouch, { passive: true });
    window.addEventListener('scroll', onScrollFromElsewhere, { passive: true });
    root.dataset.inertiaActive = 'true';
    attached = true;
  };

  const detach = () => {
    if (!attached) return;
    stop();
    window.removeEventListener('wheel', onWheel);
    window.removeEventListener('keydown', onKeyOrTouch);
    window.removeEventListener('touchstart', onKeyOrTouch);
    window.removeEventListener('scroll', onScrollFromElsewhere);
    delete root.dataset.inertiaActive;
    attached = false;
  };

  /** Attaches only when the row asks for inertia AND the visitor has not opted out. */
  const sync = () => {
    if (settings.enabled && !reduced.matches) attach();
    else detach();
  };

  // Re-evaluated live: a visitor turning on Reduce Motion mid-session gets native
  // scroll back immediately, without a reload.
  reduced.addEventListener('change', sync);
  sync();

  return {
    destroy() {
      reduced.removeEventListener('change', sync);
      detach();
    },
    isActive: () => attached,
  };
}
