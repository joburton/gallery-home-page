/**
 * Canvas padding — pads the grid to its configured minimum depth with inert clones.
 *
 * The server renders each unique tile exactly once; that is what crawlers and the
 * SEO/LLM principles depend on. Everything this module adds is decoration: no
 * data-tile-id, aria-hidden, inert, absent from the tab order, and carrying no media
 * URL until it approaches the viewport.
 *
 * That last part matters more than it looks. Measured on this fixture, loading="lazy"
 * alone deferred nothing at 1440px — Chrome's lazy threshold is generous enough that it
 * fetched all 31 derivatives. At fifteen screens of repeats that would be hundreds of
 * requests, so clones start with no src at all and are hydrated by an observer.
 */

/** Elements per-clone that must never be reachable or identifiable. */
const IDENTITY_ATTRS = ['data-tile-id', 'id', 'data-pinned'];

/**
 * Pads the canvas to the configured minimum depth and completes the final row.
 *
 * @param {HTMLElement} root The gallery row root element.
 * @returns {{ destroy: () => void }} Removes all clones and listeners.
 */
export function initCanvas(root) {
  const grid = root.querySelector('[data-gallery-grid]');
  if (!grid) return { destroy() {} };

  const minDepth = parseFloat(
    getComputedStyle(root).getPropertyValue('--gallery-min-depth'),
  );
  if (!Number.isFinite(minDepth) || minDepth <= 0) return { destroy() {} };

  // Only media tiles are repeated. Repeating the welcome text tile would print the
  // same heading and CTA down the page, which reads as a mistake rather than as
  // padding — and its copy is content, not texture.
  const sources = [...grid.children].filter(
    (el) => el.dataset.tileType !== 'text' && el.dataset.tileId,
  );
  if (sources.length === 0) return { destroy() {} };

  /** Hard ceiling on clones, so a large minCanvasDepth cannot produce a runaway DOM. */
  const MAX_CLONES = 600;

  let clones = [];
  let hydrator = null;

  /**
   * Strips a clone's identity and defers its media, while keeping it clickable.
   *
   * The requirement is precise: repeats carry no data-tile-id, are aria-hidden, and are
   * removed from the tab order. It does NOT say they stop working.
   *
   * The HTML `inert` attribute would satisfy all three and is tempting, but it also
   * makes the element unclickable — and on a fifteen-screen canvas the repeats are most
   * of what a visitor sees and clicks. Inert repeats would mean the majority of the
   * gallery silently did nothing on click, including its Build icons, which is exactly
   * the dead interactive element the brief forbids.
   *
   * So: aria-hidden plus tabindex="-1" on every anchor. Out of the tab order and out of
   * the accessibility tree (assistive tech gets the one canonical instance), but a mouse
   * or touch click still routes normally.
   */
  const makeInert = (el) => {
    el.setAttribute('aria-hidden', 'true');
    el.dataset.tileRepeat = 'true';

    for (const attr of IDENTITY_ATTRS) el.removeAttribute(attr);
    for (const node of el.querySelectorAll('[id]')) node.removeAttribute('id');

    // Not tabbable, but still activatable by pointer. aria-hidden is only an
    // accessibility fault when it hides *tabbable* content, which this is not.
    for (const a of el.querySelectorAll('a[href]')) a.setAttribute('tabindex', '-1');

    for (const img of el.querySelectorAll('img')) {
      img.loading = 'lazy';
      img.removeAttribute('fetchpriority');
      // Park the URLs; the observer restores them near the viewport.
      if (img.src) { img.dataset.src = img.getAttribute('src'); img.removeAttribute('src'); }
      if (img.srcset) { img.dataset.srcset = img.getAttribute('srcset'); img.removeAttribute('srcset'); }
      img.alt = '';
    }

    for (const video of el.querySelectorAll('video')) {
      // Clones never play. Motion playback is capped, and spending slots on
      // decoration would starve the real tiles.
      video.removeAttribute('data-playback');
      video.querySelectorAll('source').forEach((s) => s.remove());
      video.removeAttribute('autoplay');
    }

    return el;
  };

  /**
   * How far down each column the content reaches.
   *
   * "Not ragged" means the bottom edge is flush — every column ends at the same depth.
   * Counting tiles in the last row instead undercounts, because a 2x2 that starts in
   * the row above still occupies two columns of the final row without appearing to
   * belong to it. Measuring the bottom edge per column sidesteps span arithmetic
   * entirely, and works for any future tile size.
   *
   * @returns {number[]} Bottom offset per column, in grid-relative pixels.
   */
  const columnBottoms = () => {
    const cs = getComputedStyle(grid);
    const widths = cs.gridTemplateColumns.split(' ').filter(Boolean).map(parseFloat);
    const gap = parseFloat(cs.columnGap) || 0;
    const gridBox = grid.getBoundingClientRect();

    // x-centre of each column, so a tile is attributed to every column it covers.
    const centres = [];
    let x = 0;
    for (const w of widths) {
      centres.push(x + w / 2);
      x += w + gap;
    }

    const bottoms = new Array(centres.length).fill(0);
    for (const el of grid.children) {
      const r = el.getBoundingClientRect();
      const left = r.left - gridBox.left;
      const right = left + r.width;
      const bottom = r.bottom - gridBox.top;
      centres.forEach((c, i) => {
        if (c > left && c < right && bottom > bottoms[i]) bottoms[i] = bottom;
      });
    }
    return bottoms;
  };

  /** True when every column ends at the same depth, within a pixel of rounding. */
  const isFlush = () => {
    const bottoms = columnBottoms();
    return Math.max(...bottoms) - Math.min(...bottoms) <= 1;
  };

  /**
   * @param {number} index Rotates through the source tiles.
   * @param {boolean} onlySingle Restrict to 1x1 sources — a 2x2 cannot fill a
   *   one-column gap, so the row-completion pass needs single cells.
   */
  const appendClone = (index, onlySingle = false) => {
    const pool = onlySingle
      ? sources.filter((el) => el.dataset.tileSize === '1x1')
      : sources;
    if (pool.length === 0) return null;

    const clone = makeInert(pool[index % pool.length].cloneNode(true));
    grid.appendChild(clone);
    clones.push(clone);
    return clone;
  };

  const clearClones = () => {
    for (const c of clones) c.remove();
    clones = [];
  };

  /**
   * Grows the canvas to the target depth, then tops up until the last row is whole.
   *
   * Row completeness is detected by observation rather than arithmetic: appending a
   * tile that starts a NEW row band means the previous row was already full, so that
   * last tile is removed again. Computing it instead would mean replicating the
   * browser's dense-packing decisions, which vary with column count and tile spans.
   */
  const fill = () => {
    clearClones();

    const target = minDepth * window.innerHeight;
    let i = 0;

    while (grid.scrollHeight < target && clones.length < MAX_CLONES) {
      appendClone(i++);
    }

    if (clones.length >= MAX_CLONES) {
      // Never truncate silently — a capped canvas that looks intentional is worse
      // than one that says it was capped.
      console.warn(
        `[gallery] canvas padding hit the ${MAX_CLONES}-clone ceiling at ` +
        `${(grid.scrollHeight / window.innerHeight).toFixed(1)} screens ` +
        `(minCanvasDepth requested ${minDepth}). Reduce minCanvasDepth or add tiles.`,
      );
    }

    // Complete the final row: append single cells until the bottom edge is flush.
    // Dense packing places each one in the shallowest gap, so this converges rather
     // than simply extending one column.
    const columns = columnBottoms().length;
    const attempts = columns * 3;
    for (let guard = 0; guard < attempts && !isFlush(); guard += 1) {
      if (!appendClone(i++, true)) break;
    }

    if (!isFlush()) {
      console.warn(
        '[gallery] could not flush the final row within ' +
        `${attempts} single-cell tiles — the last row may read as ragged.`,
      );
    }

    hydrate();
  };

  /**
   * Restores parked media URLs as clones approach the viewport.
   *
   * rootMargin is one viewport, so a tile decodes just before it is needed rather
   * than at load time.
   */
  const hydrate = () => {
    hydrator?.disconnect();
    hydrator = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (!entry.isIntersecting) continue;
          const img = entry.target;
          if (img.dataset.srcset) { img.setAttribute('srcset', img.dataset.srcset); delete img.dataset.srcset; }
          if (img.dataset.src) { img.setAttribute('src', img.dataset.src); delete img.dataset.src; }
          hydrator.unobserve(img);
        }
      },
      { rootMargin: '100% 0px' },
    );

    for (const clone of clones) {
      for (const img of clone.querySelectorAll('img[data-src]')) hydrator.observe(img);
    }
  };

  // Column count and viewport height both change the required padding, so recompute
  // on resize — debounced, because refilling is a layout-heavy operation.
  let resizeTimer = null;
  const onResize = () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(fill, 200);
  };

  fill();
  window.addEventListener('resize', onResize);

  return {
    destroy() {
      window.removeEventListener('resize', onResize);
      clearTimeout(resizeTimer);
      hydrator?.disconnect();
      clearClones();
    },
  };
}
