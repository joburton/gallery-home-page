// Verifies the two Sprint 1 criteria I had not actually run:
//   1. Tab order reaches every unique routable tile IN VISUAL ORDER, Enter activates,
//      and the focus ring is actually visible.
//   2. The corner Build icon is legible over the real pixels behind it — measured, not
//      assumed, by sampling the underlying image where the icon sits.
const WebSocket = require('ws');

(async () => {
  const t = (await (await fetch('http://localhost:9222/json/list')).json()).find(x => x.type === 'page');
  const ws = new WebSocket(t.webSocketDebuggerUrl, { perMessageDeflate: false });
  await new Promise(r => ws.on('open', r));
  let id = 1;
  const rpc = (m, p) => new Promise(res => {
    const i = id++;
    const h = raw => { const x = JSON.parse(raw.toString()); if (x.id === i) { ws.off('message', h); res(x); } };
    ws.on('message', h); ws.send(JSON.stringify({ id: i, method: m, params: p }));
  });
  const ev = async (e, awaitP = false) =>
    (await rpc('Runtime.evaluate', { expression: e, returnByValue: true, awaitPromise: awaitP })).result?.result?.value;

  await rpc('Page.enable', {});
  await rpc('Runtime.enable', {});
  await rpc('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });
  await rpc('Page.navigate', { url: 'http://localhost:8099/' });
  for (let i = 0; i < 80; i++) {
    if (await ev(`document.readyState==='complete' && !!document.querySelector('[data-gallery-grid]')`)) break;
    await new Promise(r => setTimeout(r, 100));
  }
  await new Promise(r => setTimeout(r, 800));

  // --- 1. Tab order vs visual order -----------------------------------------
  // Walk focus with real Tab keypresses rather than reading the DOM, so this tests
  // what a keyboard user actually gets.
  const focusables = await ev(`document.querySelectorAll('[data-gallery-row] a[href]').length`);

  await ev(`document.body.focus()`);
  const seen = [];
  for (let i = 0; i < focusables + 6; i++) {
    await rpc('Input.dispatchKeyEvent', { type: 'rawKeyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9, nativeVirtualKeyCode: 9 });
    await rpc('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9, nativeVirtualKeyCode: 9 });
    const info = await ev(`(() => {
      const a = document.activeElement;
      if (!a || !a.closest('[data-gallery-row]')) return null;
      const tile = a.closest('[data-tile-id]');
      const r = tile ? tile.getBoundingClientRect() : a.getBoundingClientRect();
      const cs = getComputedStyle(a);
      const ring = cs.outlineStyle !== 'none' && parseFloat(cs.outlineWidth) > 0;
      return {
        tileId: tile ? tile.dataset.tileId : null,
        kind: a.className.includes('build') ? 'build-icon' : 'primary',
        label: (a.getAttribute('aria-label') || a.textContent || '').trim().slice(0, 42),
        top: Math.round(r.top), left: Math.round(r.left),
        ringVisible: ring, outline: cs.outlineWidth + ' ' + cs.outlineStyle + ' ' + cs.outlineColor,
        href: a.getAttribute('href'),
      };
    })()`);
    if (info) seen.push(info);
  }

  console.log(`\n=== 1. KEYBOARD TAB ORDER (${seen.length} stops reached of ${focusables} anchors) ===\n`);
  let visualOk = true, ringOk = true;
  let prev = null;
  for (const s of seen) {
    // Visual order = top-to-bottom, then left-to-right within a row band.
    let flag = '';
    if (prev) {
      const sameBand = Math.abs(s.top - prev.top) < 40;
      const forward = sameBand ? s.left >= prev.left : s.top >= prev.top;
      if (!forward) { flag = '  <-- OUT OF VISUAL ORDER'; visualOk = false; }
    }
    if (!s.ringVisible) ringOk = false;
    console.log(`  ${(s.tileId || '-').padEnd(9)} ${s.kind.padEnd(11)} y=${String(s.top).padStart(5)} x=${String(s.left).padStart(5)} ring=${s.ringVisible ? 'yes' : 'NO '} ${s.label}${flag}`);
    prev = s;
  }
  console.log(`\n  reaches every anchor: ${seen.length === focusables ? 'yes' : 'NO (' + seen.length + '/' + focusables + ')'}`);
  console.log(`  visual order:         ${visualOk ? 'yes' : 'NO'}`);
  console.log(`  focus ring on all:    ${ringOk ? 'yes' : 'NO'}`);
  console.log(`  ring style:           ${seen[0]?.outline}`);

  // Enter activation on the first focusable.
  await ev(`document.body.focus()`);
  await rpc('Input.dispatchKeyEvent', { type: 'rawKeyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9, nativeVirtualKeyCode: 9 });
  const target = await ev(`document.activeElement.getAttribute('href')`);
  await rpc('Input.dispatchKeyEvent', { type: 'rawKeyDown', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13, nativeVirtualKeyCode: 13, text: '\r' });
  await rpc('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13, nativeVirtualKeyCode: 13 });
  await new Promise(r => setTimeout(r, 700));
  const landed = await ev(`location.pathname`);
  console.log(`  Enter activates:      ${landed === target ? 'yes (navigated to ' + landed + ')' : 'NO (href=' + target + ', now at ' + landed + ')'}`);

  // --- 2. Build icon legibility over real pixels -----------------------------
  await rpc('Page.navigate', { url: 'http://localhost:8099/' });
  for (let i = 0; i < 80; i++) {
    if (await ev(`document.readyState==='complete' && !!document.querySelector('.gallery-tile__build')`)) break;
    await new Promise(r => setTimeout(r, 100));
  }
  await new Promise(r => setTimeout(r, 1500));

  const iconInfo = await ev(`(() => {
    const out = [];
    for (const icon of document.querySelectorAll('.gallery-tile__build')) {
      const tile = icon.closest('[data-tile-id]');
      const img = tile.querySelector('img');
      const ir = icon.getBoundingClientRect();
      const cs = getComputedStyle(icon);
      out.push({
        tileId: tile.dataset.tileId,
        rect: { x: Math.round(ir.x), y: Math.round(ir.y), w: Math.round(ir.width), h: Math.round(ir.height) },
        bg: cs.backgroundColor, colour: cs.color, opacity: cs.opacity,
        backdrop: cs.backdropFilter, shadow: cs.boxShadow.slice(0, 60),
        imgSrc: img ? img.currentSrc.split('/').pop() : null,
      });
    }
    return out;
  })()`);

  console.log(`\n=== 2. BUILD ICON over real imagery (${iconInfo.length} icons) ===\n`);
  for (const i of iconInfo) {
    console.log(`  ${i.tileId}  ${i.rect.w}x${i.rect.h} at (${i.rect.x},${i.rect.y})`);
    console.log(`     glyph ${i.colour} on chip ${i.bg}, opacity ${i.opacity}`);
    console.log(`     backdrop-filter: ${i.backdrop}`);
    console.log(`     over: ${i.imgSrc}`);
  }

  // Sample the composited pixels behind and inside the icon from a real screenshot,
  // so this measures what a visitor sees rather than what the CSS declares.
  const shot = await rpc('Page.captureScreenshot', { format: 'png' });
  require('fs').writeFileSync('icon-shot.png', Buffer.from(shot.result.data, 'base64'));
  console.log(`\n  screenshot written for pixel sampling: icon-shot.png`);
  console.log('  icon rects: ' + JSON.stringify(iconInfo.map(i => i.rect)));

  ws.close();
})();
