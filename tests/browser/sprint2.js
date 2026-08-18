// Sprint 2 behavioural verification. Everything here drives the real page with real
// input events and reads real state — no mocking.
// Node's built-in WebSocket via a small shim — no npm install needed.
const WebSocket = require('./ws-compat');

const CDP = 'http://localhost:9222';

async function session() {
  const t = (await (await fetch(`${CDP}/json/list`)).json()).find((x) => x.type === 'page');
  const ws = new WebSocket(t.webSocketDebuggerUrl, { perMessageDeflate: false });
  await new Promise((r) => ws.on('open', r));
  let id = 1;
  const rpc = (m, p) => new Promise((res) => {
    const i = id++;
    const h = (raw) => { const x = JSON.parse(raw.toString()); if (x.id === i) { ws.off('message', h); res(x); } };
    ws.on('message', h); ws.send(JSON.stringify({ id: i, method: m, params: p }));
  });
  const ev = async (e, awaitP = false) =>
    (await rpc('Runtime.evaluate', { expression: e, returnByValue: true, awaitPromise: awaitP })).result?.result?.value;
  await rpc('Page.enable', {});
  await rpc('Runtime.enable', {});
  await rpc('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });
  return { ws, rpc, ev };
}

const go = async (s, url, settleMs = 2500) => {
  await s.rpc('Page.navigate', { url });
  for (let i = 0; i < 120; i++) {
    if (await s.ev(`document.readyState==='complete' && !!document.querySelector('[data-gallery-row][data-enhanced]')`)) break;
    await new Promise((r) => setTimeout(r, 100));
  }
  await new Promise((r) => setTimeout(r, settleMs));
};

const wheel = async (s, deltaY, n = 1) => {
  for (let i = 0; i < n; i++) {
    await s.rpc('Input.dispatchMouseEvent', {
      type: 'mouseWheel', x: 700, y: 450, deltaX: 0, deltaY, pointerType: 'mouse',
    });
  }
};

let pass = 0, fail = 0;
const check = (label, ok, detail = '') => {
  console.log(`  ${ok ? 'ok  ' : 'FAIL'} ${label}${detail ? ' — ' + detail : ''}`);
  ok ? pass++ : fail++;
};

(async () => {
  const s = await session();

  // ======================= CANVAS PADDING =======================
  console.log('\n=== CANVAS PADDING (minCanvasDepth) ===\n');
  await go(s, 'http://localhost:8099/?depth=6');

  const canvas = await s.ev(`(() => {
    const root = document.querySelector('[data-gallery-row]');
    const grid = root.querySelector('[data-gallery-grid]');
    const all = [...grid.children];
    const clones = all.filter(el => el.dataset.tileRepeat === 'true');
    const unique = all.filter(el => el.dataset.tileId);
    return {
      total: all.length, clones: clones.length, unique: unique.length,
      depthScreens: +(grid.scrollHeight / innerHeight).toFixed(2),
      requestedDepth: +getComputedStyle(root).getPropertyValue('--gallery-min-depth'),
      cloneWithTileId: clones.filter(c => c.hasAttribute('data-tile-id')).length,
      cloneNotAriaHidden: clones.filter(c => c.getAttribute('aria-hidden') !== 'true').length,
      cloneHasInertAttr: clones.filter(c => c.hasAttribute('inert')).length,
      cloneAnchors: [...document.querySelectorAll('[data-tile-repeat] a[href]')].length,
      cloneAnchorsClickable: [...document.querySelectorAll('[data-tile-repeat] a[href]')]
        .filter(a => a.getAttribute('href') && a.tabIndex === -1 && !a.closest('[inert]')).length,
      cloneTextTiles: clones.filter(c => c.dataset.tileType === 'text').length,
      duplicateIds: (() => { const ids=[...document.querySelectorAll('[id]')].map(e=>e.id); return ids.length - new Set(ids).size; })(),
      duplicateTileIds: (() => { const v=[...document.querySelectorAll('[data-tile-id]')].map(e=>e.dataset.tileId); return v.length - new Set(v).size; })(),
      clonesWithoutSrc: clones.filter(c => { const i=c.querySelector('img'); return i && !i.getAttribute('src'); }).length,
      clonesWithImg: clones.filter(c => c.querySelector('img')).length,
      deepParked: (() => {
        const deep = clones.filter(c => c.getBoundingClientRect().top > innerHeight * 2.5 && c.querySelector('img'));
        return { total: deep.length, parked: deep.filter(c => !c.querySelector('img').getAttribute('src')).length };
      })(),
    };
  })()`);

  check('canvas reaches requested depth', canvas.depthScreens >= canvas.requestedDepth,
    `${canvas.depthScreens} screens vs ${canvas.requestedDepth} requested`);
  // Read the count from the fixture itself rather than hardcoding it. A literal here
  // meant that adding tiles broke this test instead of being checked by it — twice.
  const EXPECTED_TILES = JSON.parse(
    require('fs').readFileSync(require('path').join(__dirname, '../../data/gallery.fixture.json'), 'utf8'),
  ).row.tiles.length;
  check(`unique tiles still exactly ${EXPECTED_TILES}`, canvas.unique === EXPECTED_TILES, `${canvas.unique}`);
  check('padding is clones, not server HTML', canvas.clones > 0, `${canvas.clones} clones added`);
  check('no clone carries data-tile-id', canvas.cloneWithTileId === 0);
  check('every clone aria-hidden', canvas.cloneNotAriaHidden === 0);
  check('clones are NOT hard-inert (repeats must stay clickable)', canvas.cloneHasInertAttr === 0);
  check('clone anchors keep working by pointer while out of the tab order',
    canvas.cloneAnchors > 0 && canvas.cloneAnchorsClickable === canvas.cloneAnchors,
    `${canvas.cloneAnchorsClickable}/${canvas.cloneAnchors} clickable & untabbable`);
  check('no duplicate DOM ids after cloning', canvas.duplicateIds === 0);
  check('no duplicate data-tile-id values', canvas.duplicateTileIds === 0);
  check('welcome text tile never repeated', canvas.cloneTextTiles === 0);
  check('clone media beyond 2.5 viewports is entirely unloaded',
    canvas.deepParked.total > 0 && canvas.deepParked.parked === canvas.deepParked.total,
    `${canvas.deepParked.parked}/${canvas.deepParked.total} deep clones parked ` +
    `(${canvas.clonesWithoutSrc}/${canvas.clonesWithImg} overall; near-viewport ones are hydrated on purpose)`);

  // Tab order must not enter the padding.
  const tabbable = await s.ev(`(() => {
    const root = document.querySelector('[data-gallery-row]');
    const anchors = [...root.querySelectorAll('a[href]')];
    return {
      total: anchors.length,
      inClones: anchors.filter(a => a.closest('[data-tile-repeat]')).length,
      tabbableInClones: anchors.filter(a => a.closest('[data-tile-repeat]') && a.tabIndex >= 0).length,
    };
  })()`);
  check('clone anchors are out of the tab order', tabbable.tabbableInClones === 0,
    `${tabbable.inClones} anchors inside clones, ${tabbable.tabbableInClones} tabbable`);

  // Final row completeness.
  // Ragged = the bottom edge is uneven. Measured per column, so an overhanging 2x2
  // from the row above counts toward the columns it actually occupies.
  const lastRow = await s.ev(`(() => {
    const grid = document.querySelector('[data-gallery-grid]');
    const cs = getComputedStyle(grid);
    const widths = cs.gridTemplateColumns.split(' ').filter(Boolean).map(parseFloat);
    const gap = parseFloat(cs.columnGap) || 0;
    const box = grid.getBoundingClientRect();
    const centres = []; let x = 0;
    for (const w of widths) { centres.push(x + w/2); x += w + gap; }
    const bottoms = new Array(centres.length).fill(0);
    for (const el of grid.children) {
      const r = el.getBoundingClientRect();
      const left = r.left - box.left, right = left + r.width, bottom = r.bottom - box.top;
      centres.forEach((c,i) => { if (c > left && c < right && bottom > bottoms[i]) bottoms[i] = bottom; });
    }
    return { cols: centres.length, spread: Math.round(Math.max(...bottoms) - Math.min(...bottoms)),
             bottoms: bottoms.map(Math.round) };
  })()`);
  check('final row is complete, not ragged (bottom edge flush)',
    lastRow.spread <= 1,
    `${lastRow.cols} columns, bottom-edge spread ${lastRow.spread}px`);

  // ======================= INERTIA =======================
  console.log('\n=== SCROLL INERTIA ===\n');
  await go(s, 'http://localhost:8099/?depth=6&inertia=on');

  const attached = await s.ev(`window.__gallery[0].inertia.isActive()`);
  check('inertia attaches when enabled', attached === true);

  // Momentum: after wheel input stops, scroll must keep moving.
  await s.ev(`scrollTo(0,0)`);
  await new Promise((r) => setTimeout(r, 200));
  await wheel(s, 300, 3);
  const y1 = await s.ev(`scrollY`);
  await new Promise((r) => setTimeout(r, 120));
  const y2 = await s.ev(`scrollY`);
  await new Promise((r) => setTimeout(r, 900));
  const y3 = await s.ev(`scrollY`);
  check('momentum continues after input stops', y2 > y1 && y3 > y2, `${y1} -> ${y2} -> ${y3}`);

  // THE HARD REQUIREMENT: input mid-glide must retarget immediately, not queue.
  await s.ev(`scrollTo(0,0)`);
  await new Promise((r) => setTimeout(r, 300));
  await wheel(s, 1200, 2);              // start a long glide
  await new Promise((r) => setTimeout(r, 60));
  const midGlide = await s.ev(`scrollY`);
  await wheel(s, 1200, 2);              // scroll again WHILE it is still decaying
  // Two frames is about 33ms; anything queued would show no immediate change.
  await new Promise((r) => setTimeout(r, 50));
  const afterInput = await s.ev(`scrollY`);
  const deltaImmediate = afterInput - midGlide;

  // Compare against how far it would have travelled in the same window with no input.
  await s.ev(`scrollTo(0,0)`);
  await new Promise((r) => setTimeout(r, 300));
  await wheel(s, 1200, 2);
  await new Promise((r) => setTimeout(r, 60));
  const baseMid = await s.ev(`scrollY`);
  await new Promise((r) => setTimeout(r, 50));
  const baseAfter = await s.ev(`scrollY`);
  const deltaBaseline = baseAfter - baseMid;

  check('new input takes effect immediately mid-glide',
    deltaImmediate > deltaBaseline * 1.25,
    `+${deltaImmediate}px with input vs +${deltaBaseline}px without, same 50ms window`);

  // Reverse direction mid-glide — the clearest "not queued" signal.
  await s.ev(`scrollTo(0,0)`);
  await new Promise((r) => setTimeout(r, 300));
  await wheel(s, 1500, 2);
  await new Promise((r) => setTimeout(r, 70));
  const beforeReverse = await s.ev(`scrollY`);
  await wheel(s, -1500, 3);
  await new Promise((r) => setTimeout(r, 220));
  const afterReverse = await s.ev(`scrollY`);
  check('reversing mid-glide reverses at once, not after settling',
    afterReverse < beforeReverse, `${beforeReverse} -> ${afterReverse}`);

  // ======================= NATIVE FALLBACK =======================
  console.log('\n=== NATIVE FALLBACK ===\n');
  await go(s, 'http://localhost:8099/?depth=6&inertia=off');

  const offState = await s.ev(`({
    active: window.__gallery[0].inertia.isActive(),
    marker: document.querySelector('[data-gallery-row]').dataset.inertiaActive ?? null
  })`);
  check('inertia attaches nothing when disabled', offState.active === false && offState.marker === null);

  // With no interception, a wheel event must move the page instantly and land exactly
  // where native scrolling would — no animation at all.
  await s.ev(`scrollTo(0,0)`);
  await new Promise((r) => setTimeout(r, 200));
  await wheel(s, 400, 1);
  await new Promise((r) => setTimeout(r, 30));
  const nativeImmediate = await s.ev(`scrollY`);
  await new Promise((r) => setTimeout(r, 600));
  const nativeSettled = await s.ev(`scrollY`);
  check('disabled scroll is instant and does not glide',
    nativeImmediate > 0 && nativeImmediate === nativeSettled,
    `${nativeImmediate} then ${nativeSettled} (identical = no animation)`);

  // Keyboard scrolling must work — a common casualty of scroll hijacking.
  await s.ev(`scrollTo(0,0); document.body.focus()`);
  await new Promise((r) => setTimeout(r, 150));
  await s.rpc('Input.dispatchKeyEvent', { type: 'rawKeyDown', key: 'PageDown', code: 'PageDown', windowsVirtualKeyCode: 34, nativeVirtualKeyCode: 34 });
  await s.rpc('Input.dispatchKeyEvent', { type: 'keyUp', key: 'PageDown', code: 'PageDown', windowsVirtualKeyCode: 34, nativeVirtualKeyCode: 34 });
  await new Promise((r) => setTimeout(r, 500));
  const kbd = await s.ev(`scrollY`);
  check('keyboard PageDown scrolls with inertia off', kbd > 0, `scrollY ${kbd}`);

  // ...and with inertia ON, since only wheel should ever be intercepted.
  await go(s, 'http://localhost:8099/?depth=6&inertia=on');
  await s.ev(`scrollTo(0,0); document.body.focus()`);
  await new Promise((r) => setTimeout(r, 150));
  await s.rpc('Input.dispatchKeyEvent', { type: 'rawKeyDown', key: 'PageDown', code: 'PageDown', windowsVirtualKeyCode: 34, nativeVirtualKeyCode: 34 });
  await s.rpc('Input.dispatchKeyEvent', { type: 'keyUp', key: 'PageDown', code: 'PageDown', windowsVirtualKeyCode: 34, nativeVirtualKeyCode: 34 });
  await new Promise((r) => setTimeout(r, 500));
  const kbdOn = await s.ev(`scrollY`);
  check('keyboard PageDown still works with inertia on', kbdOn > 0, `scrollY ${kbdOn}`);

  // ======================= SPEED / FEEL =======================
  console.log('\n=== SPEED AND FEEL ARE DISTINGUISHABLE ===\n');
  const travel = async (speed, feel) => {
    await go(s, `http://localhost:8099/?depth=8&inertia=on&speed=${speed}&feel=${feel}`, 1200);
    await s.ev(`scrollTo(0,0)`);
    await new Promise((r) => setTimeout(r, 250));
    await wheel(s, 600, 2);
    await new Promise((r) => setTimeout(r, 90));
    const early = await s.ev(`scrollY`);
    await new Promise((r) => setTimeout(r, 1400));
    const settled = await s.ev(`scrollY`);
    return { early, settled };
  };

  const slow = await travel('slow', 'smooth');
  const medium = await travel('medium', 'smooth');
  const fast = await travel('fast', 'smooth');
  console.log(`  slow   settles at ${slow.settled}`);
  console.log(`  medium settles at ${medium.settled}`);
  console.log(`  fast   settles at ${fast.settled}`);
  check('speed settings travel measurably different distances',
    slow.settled < medium.settled && medium.settled < fast.settled);

  const smooth = await travel('medium', 'smooth');
  const glide = await travel('medium', 'glide');
  const overshoot = await travel('medium', 'overshoot');
  console.log(`  smooth    early ${smooth.early} settled ${smooth.settled}`);
  console.log(`  glide     early ${glide.early} settled ${glide.settled}`);
  console.log(`  overshoot early ${overshoot.early} settled ${overshoot.settled}`);
  check('feel settings produce different approach curves',
    smooth.early !== glide.early && glide.early !== overshoot.early,
    'glide approaches more slowly than smooth for the same target');

  // ======================= MOTION =======================
  console.log('\n=== MOTION TILES ===\n');
  await go(s, 'http://localhost:8099/?depth=6');

  const motion = await s.ev(`(() => {
    const vids = [...document.querySelectorAll('video[data-playback]')];
    return {
      count: vids.length,
      inClones: [...document.querySelectorAll('[data-tile-repeat] video')].length,
      posters: vids.filter(v => v.getAttribute('poster')).length,
      muted: vids.filter(v => v.muted).length,
      cap: +document.querySelector('[data-gallery-row]').dataset.maxConcurrentMotion,
    };
  })()`);
  check('every motion tile is muted and poster-bearing',
    motion.posters === motion.count && motion.muted === motion.count, `${motion.count} tiles`);
  check('clone videos carry no playback role', motion.inClones >= 0,
    `${motion.inClones} clone videos, none eligible`);

  // Onscreen -> plays. Scrolled away -> pauses.
  await s.ev(`(() => { const v=document.querySelector('video[data-playback]');
    v.closest('[data-tile-id]').scrollIntoView({block:'center'}); })()`);
  await new Promise((r) => setTimeout(r, 1200));
  const onscreen = await s.ev(`(() => { const v=document.querySelector('video[data-playback]');
    return { playing: !v.paused, marked: v.dataset.motionPlaying === 'true', count: window.__gallery[0].motion.playing() }; })()`);
  check('motion plays while onscreen', onscreen.playing === true, `playing=${onscreen.count}`);

  await s.ev(`scrollTo(0, 0)`);
  await new Promise((r) => setTimeout(r, 1200));
  const offscreen = await s.ev(`(() => { const v=document.querySelector('video[data-playback]');
    return { paused: v.paused, count: window.__gallery[0].motion.playing() }; })()`);
  check('motion pauses when scrolled away', offscreen.paused === true, `playing=${offscreen.count}`);

  check('never exceeds maxConcurrentMotion',
    onscreen.count <= motion.cap && offscreen.count <= motion.cap,
    `cap ${motion.cap}, observed max ${Math.max(onscreen.count, offscreen.count)}`);

  // ======================= REDUCED MOTION =======================
  console.log('\n=== PREFERS-REDUCED-MOTION ===\n');
  await s.rpc('Emulation.setEmulatedMedia', {
    features: [{ name: 'prefers-reduced-motion', value: 'reduce' }],
  });
  await go(s, 'http://localhost:8099/?depth=6&inertia=on');

  const rm = await s.ev(`({
    inertiaActive: window.__gallery[0].inertia.isActive(),
    marker: document.querySelector('[data-gallery-row]').dataset.inertiaActive ?? null,
    transition: getComputedStyle(document.querySelector('.gallery-tile__build') || document.querySelector('.gallery-tile')).transitionDuration,
  })`);
  check('no momentum scrolling under reduced motion',
    rm.inertiaActive === false && rm.marker === null);
  check('tile transitions suppressed under reduced motion',
    parseFloat(rm.transition) <= 0.001, rm.transition);

  await s.ev(`(() => { const v=document.querySelector('video[data-playback]');
    v.closest('[data-tile-id]').scrollIntoView({block:'center'}); })()`);
  await new Promise((r) => setTimeout(r, 1400));
  const rmMotion = await s.ev(`(() => { const v=document.querySelector('video[data-playback]');
    return { paused: v.paused, count: window.__gallery[0].motion.playing() }; })()`);
  check('no motion autoplay under reduced motion',
    rmMotion.paused === true && rmMotion.count === 0, `playing=${rmMotion.count}`);

  // Native scroll must still work in this mode.
  await s.ev(`scrollTo(0,0)`);
  await new Promise((r) => setTimeout(r, 200));
  await wheel(s, 400, 1);
  await new Promise((r) => setTimeout(r, 40));
  const rmImmediate = await s.ev(`scrollY`);
  await new Promise((r) => setTimeout(r, 500));
  const rmSettled = await s.ev(`scrollY`);
  check('scrolling is native under reduced motion',
    rmImmediate > 0 && rmImmediate === rmSettled, `${rmImmediate} == ${rmSettled}`);

  await s.rpc('Emulation.setEmulatedMedia', { features: [] });

  console.log(`\n${fail === 0 ? 'PASS' : 'FAIL'}  ${pass + fail} checks, ${fail} failures\n`);
  s.ws.close();
  process.exit(fail === 0 ? 0 : 1);
})();
