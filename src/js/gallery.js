/**
 * Gallery row entry point.
 *
 * Everything here is progressive enhancement. The row is fully functional with this
 * file absent: every unique tile, its alt text, its copy and its real href are already
 * in the served HTML. This adds canvas depth, momentum and motion playback — and
 * nothing that a crawler or a keyboard user depends on.
 *
 * All settings are read from data attributes the row template writes from `row.*`, so
 * the JS has no configuration of its own and no knowledge of Craft. Adding a setting
 * means adding an attribute in the template, not editing this file.
 *
 * Note there is no Alpine.js here. The brief allows it for click-routing and scroll
 * behaviour, but every routable tile is a real anchor — so click-routing needs no
 * JavaScript at all, and the scroll and motion work is a poor fit for a declarative
 * component layer. Vendoring Alpine to leave it unused would be exactly the future
 * scaffolding the project principles rule out. If a later phase needs it, it is one
 * file.
 */

import { initCanvas } from './gallery-canvas.js';
import { initInertia } from './gallery-inertia.js';
import { initMotion } from './gallery-motion.js';

/**
 * Enhances one gallery row.
 *
 * @param {HTMLElement} root Element carrying [data-gallery-row].
 * @returns {{ destroy: () => void, canvas: object, inertia: object, motion: object }}
 */
export function initGalleryRow(root) {
  const canvas = initCanvas(root);

  const inertia = initInertia(root, {
    enabled: root.dataset.inertiaEnabled === 'true',
    speed: root.dataset.inertiaSpeed,
    feel: root.dataset.inertiaFeel,
  });

  const motion = initMotion(
    root,
    Number.parseInt(root.dataset.maxConcurrentMotion ?? '0', 10),
  );

  root.dataset.enhanced = 'true';

  return {
    canvas,
    inertia,
    motion,
    destroy() {
      canvas.destroy();
      inertia.destroy();
      motion.destroy();
      delete root.dataset.enhanced;
    },
  };
}

/** Every row on the page, so more than one could coexist without special handling. */
const instances = [...document.querySelectorAll('[data-gallery-row]')].map(initGalleryRow);

// Exposed for the verification suite to inspect real state — slot counts, whether
// inertia actually attached — rather than inferring it from behaviour.
window.__gallery = instances;
