// Initial-viewport budget, split into a deterministic part and a heuristic part.
//
// "How many images did the browser fetch" is NOT stable: Chrome sizes its lazy-loading
// threshold from its own connection estimate, so the same page fetches a different
// number of tiles run to run. Reporting that alone produced a figure that did not
// reproduce. What IS stable is the cost of the tiles actually inside the first viewport,
// so that is reported as the budget, with the opportunistic total alongside it.
// Node's built-in WebSocket via a small shim — no npm install needed.
const WebSocket = require('./ws-compat');

const run = async (profile, label) => {
  const t = (await (await fetch('http://localhost:9222/json/list')).json()).find(x => x.type === 'page');
  const ws = new WebSocket(t.webSocketDebuggerUrl, { perMessageDeflate: false });
  await new Promise(r => ws.on('open', r));
  let id = 1;
  const rpc = (m, p) => new Promise(res => { const i = id++; const h = raw => { const x = JSON.parse(raw.toString()); if (x.id === i) { ws.off('message', h); res(x); } }; ws.on('message', h); ws.send(JSON.stringify({ id: i, method: m, params: p })); });
  const ev = async e => (await rpc('Runtime.evaluate', { expression: e, returnByValue: true })).result?.result?.value;
  await rpc('Page.enable', {}); await rpc('Runtime.enable', {}); await rpc('Network.enable', {});
  await rpc('Emulation.setDeviceMetricsOverride', { width: profile.w, height: profile.h, deviceScaleFactor: profile.dsf, mobile: profile.mobile });
  await rpc('Network.clearBrowserCache', {});
  await rpc('Network.setCacheDisabled', { cacheDisabled: true });
  await rpc('Page.navigate', { url: 'about:blank' });
  await new Promise(r => setTimeout(r, 200));
  await rpc('Page.navigate', { url: 'http://localhost:8099/?depth=15' });
  for (let i = 0; i < 120; i++) { if (await ev(`document.readyState==='complete'`)) break; await new Promise(r => setTimeout(r, 100)); }
  await new Promise(r => setTimeout(r, 2500));

  const out = await ev(`(() => {
    const res = performance.getEntriesByType('resource');
    const nav = performance.getEntriesByType('navigation')[0];
    const byExt = (pred) => res.filter(pred).reduce((s,e)=>s+e.transferSize,0);

    // Tiles whose box intersects the first viewport at load position.
    const inFold = [...document.querySelectorAll('[data-gallery-grid] > *')].filter(el => {
      const r = el.getBoundingClientRect();
      return r.top < innerHeight && r.bottom > 0;
    });
    const foldImgs = inFold.flatMap(el => [...el.querySelectorAll('img[src]')]).map(i => i.currentSrc.split('/').pop());
    const foldBytes = res.filter(e => foldImgs.some(f => e.name.endsWith(f))).reduce((s,e)=>s+e.transferSize,0);

    const allImgBytes = byExt(e => e.name.includes('/transforms/'));
    const allImgCount = res.filter(e => e.name.includes('/transforms/')).length;

    return {
      doc: nav.transferSize,
      css: byExt(e => e.name.endsWith('.css')),
      js: byExt(e => e.name.endsWith('.js')),
      jsCount: res.filter(e => e.name.endsWith('.js')).length,
      video: byExt(e => e.name.endsWith('.mp4')),
      foldTiles: inFold.length, foldImgCount: foldImgs.length, foldBytes,
      allImgCount, allImgBytes,
    };
  })()`);
  ws.close();

  const deterministic = out.doc + out.css + out.js + out.video + out.foldBytes;
  const opportunistic = out.doc + out.css + out.js + out.video + out.allImgBytes;
  console.log(`\n  ${label}`);
  console.log(`    document ${String(out.doc).padStart(7)}   css ${String(out.css).padStart(6)}   js ${String(out.js).padStart(6)} (${out.jsCount} modules)   video ${out.video}`);
  console.log(`    tiles intersecting first viewport: ${out.foldTiles} (${out.foldImgCount} images, ${out.foldBytes.toLocaleString()} bytes)`);
  console.log(`    BUDGET (first viewport):      ${(deterministic/1024).toFixed(1)} KB`);
  console.log(`    browser also prefetched:      ${out.allImgCount} images total -> ${(opportunistic/1024).toFixed(1)} KB (heuristic, varies run to run)`);
  return { deterministic, opportunistic, allImgCount: out.allImgCount };
};

(async () => {
  console.log('=== INITIAL-VIEWPORT BUDGET, depth=15, cache disabled ===');
  const m = [];
  for (let i = 0; i < 3; i++) m.push(await run({ w: 375, h: 667, dsf: 2, mobile: true }, `mobile 375x667  run ${i + 1}`));
  const d = await run({ w: 1440, h: 900, dsf: 1, mobile: false }, 'desktop 1440x900');
  console.log(`\n  mobile budget across 3 runs: ${m.map(x => (x.deterministic/1024).toFixed(1)).join(' / ')} KB  (stable)`);
  console.log(`  mobile prefetch across 3 runs: ${m.map(x => x.allImgCount).join(' / ')} images  (varies)`);
})();
