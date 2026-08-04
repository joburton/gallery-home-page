// Does page weight grow with minCanvasDepth? Before clone-parking it would have, since
// every repeat carried a real src. This is the check that the padding is genuinely free.
const WebSocket = require('ws');
(async () => {
  const t = (await (await fetch('http://localhost:9222/json/list')).json()).find(x => x.type === 'page');
  const ws = new WebSocket(t.webSocketDebuggerUrl, { perMessageDeflate: false });
  await new Promise(r => ws.on('open', r));
  let id = 1;
  const rpc = (m, p) => new Promise(res => { const i = id++; const h = raw => { const x = JSON.parse(raw.toString()); if (x.id === i) { ws.off('message', h); res(x); } }; ws.on('message', h); ws.send(JSON.stringify({ id: i, method: m, params: p })); });
  const ev = async e => (await rpc('Runtime.evaluate', { expression: e, returnByValue: true })).result?.result?.value;
  await rpc('Page.enable', {}); await rpc('Runtime.enable', {}); await rpc('Network.enable', {});
  await rpc('Emulation.setDeviceMetricsOverride', { width: 375, height: 667, deviceScaleFactor: 2, mobile: true });

  console.log('  depth  tiles-in-grid  canvas-screens  images-fetched  image-bytes');
  for (const depth of [2, 5, 10, 15, 25]) {
    await rpc('Network.clearBrowserCache', {});
    await rpc('Network.setCacheDisabled', { cacheDisabled: true });
    await rpc('Page.navigate', { url: 'about:blank' });
    await new Promise(r => setTimeout(r, 200));
    await rpc('Page.navigate', { url: `http://localhost:8099/?depth=${depth}` });
    for (let i = 0; i < 150; i++) { if (await ev(`document.readyState==='complete' && !!document.querySelector('[data-enhanced]')`)) break; await new Promise(r => setTimeout(r, 100)); }
    await new Promise(r => setTimeout(r, 2600));
    const r = await ev(`(() => {
      const res = performance.getEntriesByType('resource').filter(e => e.name.includes('/transforms/'));
      return { tiles: document.querySelectorAll('[data-gallery-grid] > *').length,
               screens: +(document.documentElement.scrollHeight/innerHeight).toFixed(1),
               n: res.length, bytes: res.reduce((s,e)=>s+e.transferSize,0) };
    })()`);
    console.log(`  ${String(depth).padStart(5)}  ${String(r.tiles).padStart(13)}  ${String(r.screens).padStart(14)}  ${String(r.n).padStart(14)}  ${r.bytes.toLocaleString().padStart(11)}`);
  }
  ws.close();
})();
