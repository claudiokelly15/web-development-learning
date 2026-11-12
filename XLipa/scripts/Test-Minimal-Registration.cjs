// Offline responsive QA of actual PHPUnit-rendered MINIMAL-REGISTRATION views. No Laravel server.
const fs = require('node:fs');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const { spawn } = require('node:child_process');
const assert = require('node:assert/strict');

async function main() {
  const fixture = fs.realpathSync(path.resolve(process.argv[2] || ''));
  const parent = fs.realpathSync(path.join(__dirname, '../backend/storage/framework/testing'));
  assert.equal(path.dirname(fixture), parent, 'Only isolated test exports are allowed');
  assert.match(path.basename(fixture), /^minimal-visual-[a-f0-9]{20}$/);
  const marker = JSON.parse(fs.readFileSync(path.join(fixture, 'fixture.json'), 'utf8'));
  assert.deepEqual(marker, { synthetic: true, feature: 'MINIMAL-REGISTRATION' });
  for (const page of ['signup', 'account', 'dashboard', 'onboarding', 'applications', 'verification']) assert.ok(fs.statSync(path.join(fixture, `${page}.html`)).isFile());
  const chrome = ['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync);
  assert.ok(chrome, 'Existing Chrome/Edge required; no browser installation occurs');
  const profile = path.join(fixture, 'browser-profile');
  fs.mkdirSync(profile);
  const browser = spawn(chrome, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--disable-background-networking', '--disable-component-update', '--disable-sync', '--host-resolver-rules=MAP * ~NOTFOUND', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], { windowsHide: true, stdio: 'ignore' });
  let socket;
  try {
    const portFile = path.join(profile, 'DevToolsActivePort');
    const deadline = Date.now() + 15000;
    while (!fs.existsSync(portFile) && browser.exitCode === null && Date.now() < deadline) await new Promise(resolve => setTimeout(resolve, 100));
    assert.ok(fs.existsSync(portFile), 'Isolated browser did not start');
    const [port, endpoint] = fs.readFileSync(portFile, 'utf8').trim().split(/\r?\n/);
    assert.match(port, /^\d+$/);
    assert.match(endpoint, /^\/devtools\/browser\/[a-z0-9-]+$/);
    socket = new WebSocket(`ws://127.0.0.1:${port}${endpoint}`);
    await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, { once: true }); socket.addEventListener('error', reject, { once: true }); });
    let nextId = 0;
    const pending = new Map();
    const httpRequests = [];
    const pageErrors = [];
    socket.addEventListener('message', event => {
      const message = JSON.parse(event.data);
      if (pending.has(message.id)) {
        const call = pending.get(message.id);
        pending.delete(message.id);
        clearTimeout(call.timer);
        message.error ? call.reject(new Error(JSON.stringify(message.error))) : call.resolve(message.result);
      } else if (message.method === 'Network.requestWillBeSent' && /^https?:/.test(message.params.request.url)) {
        httpRequests.push(message.params.request.url);
      } else if (message.method === 'Runtime.exceptionThrown') {
        pageErrors.push(message.params.exceptionDetails.text);
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
    const results = [];
    for (const page of ['signup', 'account', 'dashboard', 'onboarding', 'applications', 'verification']) {
      await command('Page.navigate', { url: pathToFileURL(path.join(fixture, `${page}.html`)).href });
      for (const [name, width, height] of [['desktop', 1440, 1100], ['tablet', 820, 1180], ['mobile', 390, 844]]) {
        await command('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: false });
        const measured = await command('Runtime.evaluate', { expression: `(async () => {
          await document.fonts.ready;
          await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
          const skip = document.querySelector('.dash-skip');
          if (skip) skip.focus();
          const keyboardSkip = !skip || (document.activeElement === skip && skip.getBoundingClientRect().top >= 0);
          if (skip) skip.blur();
          const boxes = ['main', '.legal-content', 'form', '.dash-workspace', '.field'].flatMap(selector => [...document.querySelectorAll(selector)].map(node => {
            const box = node.getBoundingClientRect(); return { selector, left: box.left, right: box.right };
          }));
          return { width: innerWidth, documentWidth: document.documentElement.scrollWidth, keyboardSkip,
            heading: document.querySelector('h1').textContent,
            headingColor: getComputedStyle(document.querySelector('h1')).color,
            label: document.body.textContent.includes('Business name not provided'),
            nameOptional: document.querySelector('[name=businessName]') ? !document.querySelector('[name=businessName]').required : null,
            required: ['fullName', 'email', 'phone', 'password', 'password_confirmation', 'terms'].every(name => document.querySelector('[name='+name+']')?.required),
            workspaceLabel: document.querySelector('.dash-workspace strong')?.textContent.trim(), boxes };        })()`, awaitPromise: true, returnByValue: true });
        assert.ok(!measured.exceptionDetails, 'Layout measurement failed');
        const data = measured.result.value;
        assert.equal(data.width, width);
        assert.ok(data.documentWidth <= width, `${page}/${name}: page overflow`);
        assert.ok(data.keyboardSkip, 'Keyboard skip link must be visible on focus');
        if (page === 'signup') {
          assert.equal(data.nameOptional, true);
          assert.equal(data.required, true);
        } else {
          assert.equal(data.label, true, `${page}: missing unnamed-merchant label`);
          if (page === 'dashboard') assert.equal(data.workspaceLabel, 'Business name not provided');
        }
        for (const box of data.boxes) assert.ok(box.left >= 0 && box.right <= width + 1, `${page}/${name}: clipped ${box.selector}`);
        const { cssContentSize } = await command('Page.getLayoutMetrics');
        const pageHeight = Math.ceil(cssContentSize.height);
        const screenshot = await command('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true, clip: { x: 0, y: 0, width, height: pageHeight, scale: 1 } });
        fs.writeFileSync(path.join(fixture, `${page}-${name}.png`), Buffer.from(screenshot.data, 'base64'));
        results.push({ page, viewport: name, width, height: pageHeight, horizontalOverflow: false, keyboardSkip: true });
      }
    }
    assert.deepEqual(httpRequests, [], 'Offline fixture attempted HTTP');
    assert.deepEqual(pageErrors, [], 'Browser page exception');
    const output = { result: 'passed', syntheticOnly: true, results, httpRequests: 0, pageErrors: 0 };
    fs.writeFileSync(path.join(fixture, 'browser-results.json'), JSON.stringify(output, null, 2));
    console.log(JSON.stringify({ ...output, artifacts: fixture }));
    await send('Browser.close');
  } finally {
    if (socket) socket.close();
    if (browser.exitCode === null) browser.kill();
    await new Promise(resolve => { if (browser.exitCode !== null) resolve(); else { browser.once('exit', resolve); setTimeout(resolve, 3000); } });
    assert.equal(path.dirname(profile), fixture);
    if (fs.existsSync(profile)) {
      assert.equal(fs.realpathSync(profile), profile);
      fs.rmSync(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
    }
  }
}

main().catch(error => { console.error(error.message); process.exitCode = 1; });
