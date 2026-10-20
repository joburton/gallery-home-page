/**
 * Motion tiles — plays only what is onscreen, and never more than the cap.
 *
 * A deep canvas can hold many motion tiles, so playback is rationed rather than
 * assumed: adding motion tiles in Craft must not be able to sink the page. Beyond
 * row.maxConcurrentMotion, tiles hold their poster frame until a slot frees up.
 *
 * Slots go to the MOST VISIBLE candidates rather than to whichever appears first in the
 * DOM, so the cap degrades predictably — what you are looking at plays.
 *
 * Under prefers-reduced-motion nothing autoplays at all. Momentum scrolling and
 * autoplaying video are both known motion-sensitivity triggers, and the brief treats
 * this as a hard requirement rather than a nicety.
 */

/**
 * Starts onscreen-only, capped motion playback.
 *
 * @param {HTMLElement} root Gallery row root.
 * @param {number} maxConcurrent From row.maxConcurrentMotion.
 * @returns {{ destroy: () => void, playing: () => number }}
 */
export function initMotion(root, maxConcurrent) {
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');

  // Padding repeats keep their <source> and data-playback (see gallery-canvas.js), so a
  // repeat of a motion tile is a real candidate once onscreen — not just the canonical
  // instance. They still compete for the same maxConcurrentMotion pool below, ranked by
  // visibility like everything else.
  const videos = () => [...root.querySelectorAll('video[data-playback]')];

  /** @type {Map<HTMLVideoElement, number>} element -> intersection ratio */
  const visible = new Map();
  const playing = new Set();
  let observer = null;
  let scheduled = false;

  const pause = (video) => {
    if (!video.paused) video.pause();
    playing.delete(video);
    delete video.dataset.motionPlaying;
  };

  const play = (video) => {
    playing.add(video);
    video.dataset.motionPlaying = 'true';
    // A rejected play() is normal (autoplay policy, or the element left the DOM), so
    // it must not surface as an unhandled rejection.
    video.play().catch(() => {
      playing.delete(video);
      delete video.dataset.motionPlaying;
    });
  };

  /**
   * Reconciles what is playing against what should be.
   *
   * Recomputed wholesale rather than incrementally: with a cap, one tile leaving the
   * viewport can promote a different one, so incremental updates drift.
   */
  const reconcile = () => {
    scheduled = false;

    if (reduced.matches) {
      for (const video of [...playing]) pause(video);
      return;
    }

    const candidates = [...visible.entries()]
      .filter(([video, ratio]) => ratio > 0 && video.dataset.playback === 'autoplay')
      .sort((a, b) => b[1] - a[1])
      .slice(0, Math.max(0, maxConcurrent))
      .map(([video]) => video);

    const wanted = new Set(candidates);

    for (const video of [...playing]) if (!wanted.has(video)) pause(video);
    for (const video of wanted) if (!playing.has(video)) play(video);
  };

  const schedule = () => {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(reconcile);
  };

  const observe = () => {
    observer?.disconnect();
    visible.clear();

    observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          visible.set(entry.target, entry.isIntersecting ? entry.intersectionRatio : 0);
        }
        schedule();
      },
      // Several thresholds so "most visible" is meaningful rather than binary.
      { threshold: [0, 0.1, 0.25, 0.5, 0.75, 1] },
    );

    for (const video of videos()) observer.observe(video);
  };

  // A click-playback tile is started by the visitor, so it bypasses the autoplay rule
  // — but it still counts against the cap and still pauses when it leaves the viewport.
  const onClick = (event) => {
    const video = event.target.closest?.('video[data-playback="click"]');
    if (!video) return;
    if (video.paused) play(video); else pause(video);
  };

  const onReducedChange = () => schedule();

  observe();
  root.addEventListener('click', onClick);
  reduced.addEventListener('change', onReducedChange);

  return {
    destroy() {
      observer?.disconnect();
      root.removeEventListener('click', onClick);
      reduced.removeEventListener('change', onReducedChange);
      for (const video of [...playing]) pause(video);
    },
    playing: () => playing.size,
  };
}
