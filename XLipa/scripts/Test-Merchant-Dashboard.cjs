// Offline QA of a PHPUnit-exported response. Never opens the Laravel site.
const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const { spawn } = require('node:child_process');
const assert = require('node:assert/strict');

async function main() {
  const fixture = fs.realpathSync(path.resolve(process.argv[2] || ''));
  const allowedParent = fs.realpathSync(path.join(__dirname, '../backend/storage/framework/testing'));
  assert.equal(path.dirname(fixture), allowedParent, 'Only isolated test exports are allowed');
  assert.match(path.basename(fixture), /^pd01-visual-[a-f0-9]{20}$/);
  assert.deepEqual(JSON.parse(fs.readFileSync(path.join(fixture, 'fixture.json'), 'utf8')), { synthetic: true, feature: 'PD-01' });
  const html = path.join(fixture, 'dashboard.html');
  assert.ok(fs.statSync(html).isFile());
  const chrome = ['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync);
  assert.ok(chrome, 'An existing browser is required; this script installs nothing');
  const profile = path.join(fixture, 'browser-profile');
  fs.mkdirSync(profile);
  const browser = spawn(chrome, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--disable-background-networking', '--disable-component-update', '--disable-sync', '--host-resolver-rules=MAP * ~NOTFOUND', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
  let socket;
  try {
    const portFile = path.join(profile, 'DevToolsActivePort');
    const deadline = Date.now() + 15000;
    while (!fs.existsSync(portFile) && browser.exitCode === null && Date.now() < deadline) {
      await new Promise(resolve => setTimeout(resolve, 100));
    }
    assert.ok(fs.existsSync(portFile), 'Browser did not start its isolated debugging endpoint');
    const [port, endpoint] = fs.readFileSync(portFile, 'utf8').trim().split(/\r?\n/);
    assert.match(port, /^\d+$/);
    assert.match(endpoint, /^\/devtools\/browser\/[a-z0-9-]+$/);
    socket = new WebSocket(`ws://127.0.0.1:${port}${endpoint}`);
    await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, { once: true }); socket.addEventListener('error', reject, { once: true }); });
    let nextId = 0;
    const pending = new Map();
    const networkRequests = [];
    const pageErrors = [];
    socket.addEventListener('message', event => {
      const data = JSON.parse(event.data);
      if (pending.has(data.id)) {
        const call = pending.get(data.id);
        pending.delete(data.id);
        clearTimeout(call.timer);
        data.error ? call.reject(new Error(JSON.stringify(data.error))) : call.resolve(data.result);
      } else if (data.method === 'Network.requestWillBeSent' && /^https?:/.test(data.params.request.url)) {
        networkRequests.push(data.params.request.url);
      } else if (data.method === 'Runtime.exceptionThrown') {
        pageErrors.push(data.params.exceptionDetails.text);
      }
    });
    const send = (method, params = {}, sessionId) => new Promise((resolve, reject) => {
      const id = ++nextId;
      const timer = setTimeout(() => { pending.delete(id); reject(new Error(`Browser command timed out: ${method}`)); }, 15000);
      pending.set(id, { resolve, reject, timer });
      socket.send(JSON.stringify({ id, method, params, ...(sessionId ? { sessionId } : {}) }));
    });
    const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
    const command = (method, params) => send(method, params, sessionId);
    await command('Page.enable');
    await command('Runtime.enable');
    await command('Network.enable');
    await command('Network.setBlockedURLs', { urls: ['http://*', 'https://*'] });
    await command('Page.navigate', { url: pathToFileURL(html).href });
    const results = [];
    for (const [name, width, height] of [['desktop', 1440, 1050], ['tablet', 820, 1180], ['mobile', 390, 844]]) {
      await command('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: false });
      const result = await command('Runtime.evaluate', { expression: `(async () => {
        await document.fonts.ready;
        await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
        const skip = document.querySelector('.dash-skip');
        skip.focus();
        const skipAccessible = document.activeElement === skip && skip.getBoundingClientRect().top >= 0;
        skip.blur();
        const selectors = ['.dash-hero', '.dash-panel', '.dash-resource', '.dash-facts', '.dash-detail-grid'];
        const boxes = selectors.flatMap(selector => [...document.querySelectorAll(selector)].map(node => {
          const box = node.getBoundingClientRect();
          return { selector, left: box.left, right: box.right };
        }));
        const navBox = document.querySelector('.dash-navigation').getBoundingClientRect();
        const currentBox = document.querySelector('[aria-current="page"]').getBoundingClientRect();
        return { viewport: innerWidth, documentWidth: document.documentElement.scrollWidth, skipAccessible,
          navigationVisible: navBox.height > 0 && currentBox.width > 0 && currentBox.left >= 0 && currentBox.right <= innerWidth,
          headingColor: getComputedStyle(document.querySelector('h1')).color,
            sidebarBackground: getComputedStyle(document.querySelector('.dash-sidebar')).backgroundColor,
            heroBackground: getComputedStyle(document.querySelector('.dash-hero')).backgroundColor,
          footerBackground: getComputedStyle(document.querySelector('.dash-footer')).backgroundColor,
          disabledSections: document.querySelectorAll('nav button[disabled][aria-disabled="true"]').length,
          heading: document.querySelector('h1').textContent, boxes };
      })()`, awaitPromise: true, returnByValue: true });
      assert.ok(!result.exceptionDetails, 'Layout measurement failed');
      const measurements = result.result.value;
      assert.equal(measurements.viewport, width);
      assert.ok(measurements.documentWidth <= width, `${name}: page overflows horizontally`);
      assert.ok(measurements.skipAccessible, `${name}: keyboard skip link is hidden`);
      assert.ok(measurements.navigationVisible, `${name}: navigation is hidden or clipped`);
      assert.equal(measurements.sidebarBackground, 'rgb(255, 255, 255)');
      assert.equal(measurements.heroBackground, 'rgb(237, 244, 255)');
      assert.equal(measurements.headingColor, 'rgb(7, 26, 53)', `${name}: heading theme overridden`);
      assert.equal(measurements.footerBackground, 'rgba(0, 0, 0, 0)', `${name}: footer theme overridden`);
      assert.equal(measurements.disabledSections, 9);
      assert.equal(measurements.heading, 'Overview.');
      for (const box of measurements.boxes) {
        assert.ok(box.left >= 0 && box.right <= width + 1, `${name}: clipped ${box.selector}`);
      }
      const metrics = await command('Page.getLayoutMetrics');
      const size = metrics.cssContentSize;
      const shot = await command('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true, clip: { x: 0, y: 0, width, height: Math.ceil(size.height), scale: 1 } });
      fs.writeFileSync(path.join(fixture, `${name}.png`), Buffer.from(shot.data, 'base64'));
      results.push({ name, width, height: Math.ceil(size.height), horizontalOverflow: false, keyboardSkip: true, navigationVisible: true, disabledSections: 9 });
    }
    assert.deepEqual(networkRequests, [], 'Offline fixture attempted an HTTP request');
    assert.deepEqual(pageErrors, [], 'Browser reported a page exception');
    fs.writeFileSync(path.join(fixture, 'browser-results.json'), JSON.stringify({ syntheticOnly: true, results, httpRequests: 0, pageErrors: 0 }, null, 2));
    console.log(JSON.stringify({ result: 'passed', syntheticOnly: true, results, httpRequests: 0, pageErrors: 0, artifacts: fixture }));
    await send('Browser.close');
  } finally {
    if (socket) socket.close();
    if (browser.exitCode === null) browser.kill();
    await new Promise(resolve => { if (browser.exitCode !== null) resolve(); else { browser.once('exit', resolve); setTimeout(resolve, 3000); } });
    // Delete only the exact profile created above, under the verified fixture root.
    assert.equal(path.dirname(profile), fixture);
    if (fs.existsSync(profile)) {
      assert.equal(fs.realpathSync(profile), profile);
      fs.rmSync(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
    }
  }
}

main().catch(error => { console.error(error.message); process.exitCode = 1; });
