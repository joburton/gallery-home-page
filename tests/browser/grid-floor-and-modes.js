// Verifies the two coupled/mode-dependent acceptance criteria:
//   1. the two-column floor holds at EVERY gutter value (gap and minmax share a source)
//   2. masonry staggers rows while 2x2 tiles still render 2x2, on the same grid
const WebSocket = require('ws');

(async () => {
  const targets = await (await fetch('http://localhost:9222/json/list')).json();
  const page = targets.find((t) => t.type === 'page');
  const ws = new WebSocket(page.webSocketDebuggerUrl, { perMessageDeflate: false });
  await new Promise((r) => ws.on('open', r));
  let id = 1;
  const rpc = (method, params) => new Promise((resolve) => {
    const myId = id++;
    const h = (raw) => { const m = JSON.parse(raw.toString()); if (m.id === myId) { ws.off('message', h); resolve(m); } };
    ws.on('message', h);
    ws.send(JSON.stringify({ id: myId, method, params }));
  });
  const evaluate = async (expr) => (await rpc('Runtime.evaluate', { expression: expr, returnByValue: true })).result?.result?.value;

  await rpc('Page.enable', {});
  await rpc('Runtime.enable', {});

  const go = async (url, width) => {
    await rpc('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width < 500 });
    await rpc('Page.navigate', { url });
    for (let i = 0; i < 60; i++) {
      if (await evaluate(`document.readyState==='complete' && !!document.querySelector('[data-gallery-grid]')`)) break;
      await new Promise((r) => setTimeout(r, 100));
    }
    await new Promise((r) => setTimeout(r, 200));
  };

  const colProbe = `(() => {
    const g = document.querySelector('[data-gallery-grid]');
    const cs = getComputedStyle(g);
    return { cols: cs.gridTemplateColumns.split(' ').filter(Boolean).length,
             gap: cs.gap, scrollW: document.documentElement.scrollWidth, innerW: innerWidth };
  })()`;

  console.log('--- two-column floor at 320px across gutter values ---');
  console.log('gap  cols  computed-gap  h-overflow');
  for (const gap of [0, 1, 4, 8, 16, 32, 64]) {
    await go(`http://localhost:8099/?gap=${gap}`, 320);
    const d = await evaluate(colProbe);
    const ov = d.scrollW <= d.innerW ? 'none' : `${d.scrollW - d.innerW}px`;
    console.log(`${String(gap).padStart(3)}  ${String(d.cols).padStart(4)}  ${d.gap.padStart(12)}  ${ov.padStart(10)}`);
  }

  const modeProbe = `(() => {
    const g = document.querySelector('[data-gallery-grid]');
    const tiles = [...g.children];
    // Distinct top offsets tell us whether rows align (swiss) or stagger (masonry).
    const tops = new Set(tiles.map(t => Math.round(t.getBoundingClientRect().top)));
    const ones = tiles.filter(t => t.dataset.tileSize === '1x1');
    const heights = new Set(ones.map(t => Math.round(t.getBoundingClientRect().height)));
    const big = tiles.find(t => t.dataset.tileSize === '2x2');
    const br = big.getBoundingClientRect();
    const one = ones[0].getBoundingClientRect();
    // Empty cells: total occupied area vs grid area is a proxy; instead check that no
    // tile's left edge falls outside a known column start.
    return {
      distinctTops: tops.size,
      distinct1x1Heights: heights.size,
      bigW: Math.round(br.width), bigH: Math.round(br.height),
      oneW: Math.round(one.width),
      gridH: Math.round(g.getBoundingClientRect().height),
      engine: getComputedStyle(g).display + '/' + getComputedStyle(g).gridAutoFlow,
    };
  })()`;

  console.log('\n--- layout modes at 1440px (same grid engine) ---');
  for (const mode of ['swiss', 'masonry']) {
    await go(`http://localhost:8099/?layout=${mode}`, 1440);
    const d = await evaluate(modeProbe);
    console.log(`${mode.padEnd(8)} engine=${d.engine}  distinct-row-tops=${d.distinctTops}  distinct-1x1-heights=${d.distinct1x1Heights}  2x2=${d.bigW}x${d.bigH}  1x1w=${d.oneW}  gridH=${d.gridH}`);
  }

  ws.close();
})();
