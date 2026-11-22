const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const {spawn} = require('node:child_process');
const assert = require('node:assert/strict');
async function main() {
  const base = 'http://127.0.0.1:18761';
  const home = await fetch(base).then(r => r.text());
  const requested = (process.env.XLIPA_QA_PATHS || '').split(',').filter(Boolean);
  const urls = [...new Set([...home.matchAll(/href="(\/[a-z-]*)"/g)].map(m => m[1]))].filter(url => !requested.length || requested.includes(url));
  const assetPaths = new Set();
  const destinations = new Set();
  for (const url of urls) {
    const response = await fetch(base+url);
    assert.equal(response.status, 200, url);
    const html = await response.text();
    assert.ok(!/xl_test_[a-f0-9-]{36}\.[a-f0-9]{64}/.test(html), 'No credential secrets: '+url);
    const p9Headings = {'/merchant-api':'Merchant API v1','/api-credentials':'API credential management','/qr-payments':'QR checkout associations','/pay-buttons':'Secure Pay Buttons','/merchant-webhooks':'Signed merchant webhooks','/integration-docs':'Merchant API v1','/chief-api-governance':'Chief Admin API governance'};
    if (p9Headings[url]) assert.ok(html.includes(p9Headings[url]), 'P9 content: '+url);
    if (url === '/qr-payments') assert.equal((html.match(/aria-label="XLipa checkout QR code"/g)||[]).length, 2, 'Dynamic and reusable QR previews');
    if (url === '/pay-buttons') assert.ok(html.includes('rel="noopener noreferrer"'), 'Safe button link');
    if (url === '/merchant-webhooks') assert.ok(html.includes('Delivered') && html.includes('Pending'), 'Actual outbox delivery states');
    assert.ok(!/<script\b|https?:\/\/xlipa\.test/i.test(html), 'No executable scripts or ordinary-site URLs: '+url);
    assert.ok(!/@(?:include|extends|section|if|else|endif|yield)\b|\{\{/.test(html), 'No uncompiled Blade directives: '+url);
    for(const match of html.matchAll(/(?:href|src)="(\/assets\/[^"]+)"/g)) assetPaths.add(match[1]);
    for(const match of html.matchAll(/href="(\/[^"]*)"/g)) destinations.add(match[1].split('#')[0]);
  }
  for (const asset of assetPaths) assert.equal((await fetch(base+asset)).status, 200, asset);
  for (const destination of destinations) assert.equal((await fetch(base+destination)).status, 200, destination);
  assert.equal((await fetch(base+'/wallet', {method:'POST'})).status, 405);
  assert.equal((await fetch(base+'/.env')).status, 404);
  assert.equal((await fetch(base+'/backend/database/database.sqlite')).status, 404);
  const chrome = ['C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync);
  assert.ok(chrome, 'Existing Chromium required');
  const owned = fs.mkdtempSync(path.join(os.tmpdir(), 'xlipa-demo-qa-'));
  const evidence = path.join(__dirname, '../.recovery/ui-ux-evidence-'+Date.now());
  fs.mkdirSync(evidence);
  const profile = path.join(owned, 'profile');
  const browser = spawn(chrome, ['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-background-networking','--disable-component-update','--disable-sync','--remote-debugging-port=0',`--user-data-dir=${profile}`,'about:blank'], {windowsHide:true,stdio:'ignore'});
  let socket;
  try {
    const portFile = path.join(profile, 'DevToolsActivePort');
    for(let n=0; n<150 && !fs.existsSync(portFile); n++) await new Promise(r=>setTimeout(r,100));
    const [port, endpoint] = fs.readFileSync(portFile,'utf8').trim().split(/\r?\n/);
    socket = new WebSocket(`ws://127.0.0.1:${port}${endpoint}`);
    await new Promise((resolve,reject)=>{socket.addEventListener('open',resolve,{once:true});socket.addEventListener('error',reject,{once:true});});
    let id=0;
    const pending=new Map();
    socket.addEventListener('message', event=>{const data=JSON.parse(event.data);if(pending.has(data.id)){const p=pending.get(data.id);pending.delete(data.id);clearTimeout(p.timer);data.error?p.reject(Error(JSON.stringify(data.error))):p.resolve(data.result);}});
    const send=(method,params={},sessionId)=>new Promise((resolve,reject)=>{const key=++id;const timer=setTimeout(()=>reject(Error(method+' timed out')),15000);pending.set(key,{resolve,reject,timer});socket.send(JSON.stringify({id:key,method,params,...(sessionId?{sessionId}:{})}));});
    const {targetId}=await send('Target.createTarget',{url:'about:blank'});
    const {sessionId}=await send('Target.attachToTarget',{targetId,flatten:true});
    const command=(method,params)=>send(method,params,sessionId);
    await command('Page.enable');
    await command('Network.enable');
    socket.addEventListener('message', event=>{
      const message=JSON.parse(event.data);
      if(message.method!=='Fetch.requestPaused') return;
      const allowed=new URL(message.params.request.url).origin===base && ['GET','HEAD'].includes(message.params.request.method);
      command(allowed?'Fetch.continueRequest':'Fetch.failRequest',allowed?{requestId:message.params.requestId}:{requestId:message.params.requestId,errorReason:'BlockedByClient'}).catch(()=>{});
    });
    await command('Fetch.enable',{patterns:[{urlPattern:'*',requestStage:'Request'}]});
    const results=[];
    for (const url of urls) {
      await command('Page.navigate',{url:base+url});
      for(let n=0;n<50;n++) {const r=await command('Runtime.evaluate',{expression:'document.readyState'});if(r.result.value==='complete')break;await new Promise(r=>setTimeout(r,100));}
      const loaded=await command('Runtime.evaluate',{expression:'location.href',returnByValue:true});
      assert.equal(loaded.result.value,base+url,'Only the requested demo URL may be captured');
      for (const width of [1440,1366,1920,1024,768,430,390,360]) {
        await command('Emulation.setDeviceMetricsOverride',{width,height:1000,deviceScaleFactor:1,mobile:false});
        const {result}=await command('Runtime.evaluate',{expression:`(async()=>{await document.fonts.ready;await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));return {width:innerWidth,scroll:document.documentElement.scrollWidth,heading:document.querySelector('h1')?.textContent,brokenImages:[...document.images].filter(i=>!i.complete||i.naturalWidth===0).length,enabledFields:[...document.querySelectorAll('form input,form button,form select,form textarea')].filter(i=>!i.matches(':disabled')).length,styles:[...document.querySelectorAll('link[rel=stylesheet]')].every(l=>!!l.sheet),banner:!!document.querySelector('.demo-bar')};})()`,awaitPromise:true,returnByValue:true});

        if(url==='/merchant-dashboard') {
          const presentation=await command('Runtime.evaluate',{expression:`(()=>{const mobile=innerWidth<=760;const list=document.querySelector('.premium-mobile-payments');const toggle=document.querySelector('.premium-menu-button');const bars=[...document.querySelectorAll('.premium-breakdown-row .premium-bar')];const r=toggle.getBoundingClientRect();return {mobileItems:list.querySelectorAll('li').length,mobileVisible:getComputedStyle(list).display!=='none',menuVisible:!mobile||document.elementFromPoint(r.x+r.width/2,r.y+r.height/2)?.closest('.premium-menu-button,.premium-nav-toggle')!==null,barsVisible:bars.every(b=>getComputedStyle(b).display!=='none'&&b.getBoundingClientRect().width>40),drilldowns:list.querySelectorAll('a').length};})()`,returnByValue:true});
          if(width<=760){assert.ok(presentation.result.value.mobileVisible,'Mobile transaction list is visible');assert.ok(presentation.result.value.menuVisible,'Mobile menu is not covered');assert.ok(presentation.result.value.mobileItems>0 && presentation.result.value.drilldowns>=presentation.result.value.mobileItems,'Mobile drilldowns preserved');}
          if(width===1440)assert.ok(presentation.result.value.barsVisible,'Readable desktop comparison bars restored');
        }
        if(url==='/payment-links') {
          const disclosure=await command('Runtime.evaluate',{expression:`(()=>{const item=document.querySelector('.premium-link-item');const create=document.querySelector('.premium-link-create');item.open=true;const reference=!!item.querySelector('.product-link-details');item.open=false;return {count:document.querySelectorAll('.premium-link-item').length,reference,create:!!create.querySelector('form')};})()`,returnByValue:true});
          assert.ok(disclosure.result.value.count>0&&disclosure.result.value.reference&&disclosure.result.value.create,'Expandable links retain references, actions and creation');
        }
        if(url==='/pay-buttons') {const copy=await command('Runtime.evaluate',{expression:`document.querySelectorAll('.payment-card')[1].querySelector('p').textContent`,returnByValue:true});assert.ok(copy.result.value.includes('Pay Buttons open')&&!copy.result.value.includes('codes'),'Pay Button introduction uses button terminology');}
        results.push({url,...result.value});
        assert.ok(result.value.banner,url);
        assert.equal(result.value.enabledFields,0,url);
        assert.equal(result.value.brokenImages,0,url);
        assert.ok(result.value.styles,url);
        assert.ok(result.value.scroll<=width+1,`${url} overflow ${result.value.scroll} at ${width}`);
        if (['/merchant-api','/api-credentials','/qr-payments','/pay-buttons','/merchant-webhooks','/integration-docs','/chief-api-governance'].includes(url)) {
          const integration = await command('Runtime.evaluate',{expression:`(()=>{const n=document.querySelector('.developer-tool-nav');const cards=[...document.querySelectorAll('.payment-card')];const c=cards[1];c.scrollIntoView({block:'start',behavior:'instant'});return {nav:n?{display:getComputedStyle(n).display,position:getComputedStyle(n).position,height:n.getBoundingClientRect().height,links:n.querySelectorAll('a').length}:null,content:c.querySelector('h2')?.textContent,qr:[...c.querySelectorAll('svg')].map(s=>({width:s.getBoundingClientRect().width,height:s.getBoundingClientRect().height}))};})()`,returnByValue:true});
          if(url!=='/chief-api-governance') {
            assert.equal(integration.result.value.nav.position,'static','P9 navigation stays in document flow');
            assert.ok(integration.result.value.nav.height>=44,'P9 navigation visible');
            assert.equal(integration.result.value.nav.links,6,'All P9 tools reachable');
          }
          if(url==='/qr-payments') for(const size of integration.result.value.qr) assert.ok(size.width>=200 && size.height>=200,'Readable QR size');
          const integrationShot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
          fs.writeFileSync(path.join(evidence,url.slice(1)+'-content-'+width+'.png'),Buffer.from(integrationShot.data,'base64'));
          await command('Runtime.evaluate',{expression:"scrollTo({top:0,behavior:'instant'})"});
        }
        if (['/wallet','/settlement-withdrawals','/chief-financial-oversight'].includes(url)) {
          const metrics = await command('Runtime.evaluate',{expression:`(()=>Object.fromEntries([...document.querySelectorAll('.product-kpi')].map(c=>[c.querySelector('h2')?.textContent,c.querySelector('strong')?.textContent])))()`,returnByValue:true});
          assert.equal(metrics.result.value['Simulated available balance'],'TZS 8,000');
          assert.equal(metrics.result.value['Reserved withdrawals'],'TZS 2,000');
          assert.equal(metrics.result.value['Operational available balance'],'TZS 0');
          for (const [label,slug] of [['Simulated available balance','funds-position'],['Withdrawal requests and fictional payouts','payout-history']]) {
            await command('Runtime.evaluate',{expression:`(()=>{const h=[...document.querySelectorAll('h2')].find(h=>h.textContent===${JSON.stringify(label)});h.scrollIntoView({block:'start',behavior:'instant'});})()`});
            const fundsShot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
            fs.writeFileSync(path.join(evidence,url.slice(1)+'-'+slug+'-'+width+'.png'),Buffer.from(fundsShot.data,'base64'));
          }
          await command('Runtime.evaluate',{expression:"(async()=>{scrollTo({top:0,behavior:'instant'});await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));})()",awaitPromise:true});
        }
        if (['/forgot-password','/reset-password','/email-verification','/merchant-settings'].includes(url)) {
          const accountLayout = await command('Runtime.evaluate',{expression:`(()=>{const nav=document.querySelector('.legal-header>.legal-nav');const heading=document.querySelector('h1');const footer=document.querySelector('.product-footer');return {navigationInFlow:!nav||getComputedStyle(nav).position==='static',headingClear:!nav||heading.getBoundingClientRect().top>=nav.getBoundingClientRect().bottom,footerClear:getComputedStyle(footer).backgroundColor==='rgba(0, 0, 0, 0)'};})()`,returnByValue:true});
          assert.ok(accountLayout.result.value.navigationInFlow, url+' account navigation stays in flow');
          assert.ok(accountLayout.result.value.headingClear, url+' heading is not covered');
          assert.ok(accountLayout.result.value.footerClear, url+' footer uses the light card background');
        }
        if (url === '/merchant-dashboard') {
          const hierarchy = await command('Runtime.evaluate',{expression:`(()=>({kpis:document.querySelectorAll('[data-primary-kpi]').length,balance:document.querySelectorAll('.premium-balance').length,actions:document.querySelectorAll('.premium-quick a,.premium-quick button').length,analytics:document.querySelectorAll('.premium-analytics>section').length}))()`,returnByValue:true});
          assert.deepEqual(hierarchy.result.value,{kpis:3,balance:1,actions:4,analytics:3});
          if(width<=760) {
            const menuState=await command('Runtime.evaluate',{expression:`(()=>{const t=document.getElementById('merchant-menu-toggle');t.click();const visible=getComputedStyle(document.querySelector('.premium-primary-navigation')).display!=='none';t.click();return {visible,dock:getComputedStyle(document.querySelector('.premium-mobile-dock')).display};})()`,returnByValue:true});
            assert.ok(menuState.result.value.visible,'Native mobile menu opens without scripts');
            assert.equal(menuState.result.value.dock,'flex','Mobile navigation dock is visible');
          }
          for (const [section, heading] of [['traffic-source', 'Traffic Sources'], ['payment-method', 'Payment Methods']]) {
            const content = await command('Runtime.evaluate',{expression:`(()=>{const h=document.getElementById(${JSON.stringify(section+'-title')});h.closest('section').scrollIntoView({block:'start',behavior:'instant'});return h.textContent.trim();})()`,returnByValue:true});
            assert.equal(content.result.value, heading);
            const analyticsShot = await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
            fs.writeFileSync(path.join(evidence,section+'-'+width+'.png'),Buffer.from(analyticsShot.data,'base64'));
          }
          await command('Runtime.evaluate',{expression:"(async()=>{scrollTo({top:0,behavior:'instant'});await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));})()",awaitPromise:true});
        }
        if (['/merchant-dashboard','/chief-dashboard','/staff-dashboard'].includes(url) || [1440,390].includes(width)) {
          const shot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
          fs.writeFileSync(path.join(evidence,(url.slice(1)||'demo-home')+'-'+width+'.png'),Buffer.from(shot.data,'base64'));
          {
            const layout=await command('Page.getLayoutMetrics');
            const full=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:true,clip:{x:0,y:0,width,height:Math.ceil(layout.cssContentSize.height),scale:1}});
            fs.writeFileSync(path.join(evidence,url.slice(1)+'-full-'+width+'.png'),Buffer.from(full.data,'base64'));
          }
        }
      }
      if (url === '/merchant-dashboard') {
        await command('Runtime.evaluate',{expression:"document.querySelector('#analytics').scrollIntoView({block:'start',behavior:'instant'})"});
        const shot = await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
        fs.writeFileSync(path.join(evidence,'merchant-analytics-mobile.png'),Buffer.from(shot.data,'base64'));
        await command('Runtime.evaluate',{expression:"document.querySelector('.product-date-windows').open=true;document.querySelector('.product-date-windows').scrollIntoView({block:'start',behavior:'instant'})"});
        const windows = await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
        fs.writeFileSync(path.join(evidence,'merchant-date-windows-mobile.png'),Buffer.from(windows.data,'base64'));
      }
    }
    await command('Page.navigate',{url:base+'/merchant-dashboard'});
    for(let n=0;n<50;n++){const r=await command('Runtime.evaluate',{expression:"!!document.querySelector('.demo-picker summary') && document.readyState==='complete'"});if(r.result.value)break;await new Promise(r=>setTimeout(r,100));}
    await command('Page.bringToFront');
    await command('Runtime.evaluate',{expression:"document.querySelector('.demo-picker summary').focus()"});
    await command('Input.dispatchKeyEvent',{type:'rawKeyDown',key:' ',code:'Space',windowsVirtualKeyCode:32,nativeVirtualKeyCode:32});
    await command('Input.dispatchKeyEvent',{type:'char',key:' ',code:'Space',text:' ',unmodifiedText:' '});
    await command('Input.dispatchKeyEvent',{type:'keyUp',key:' ',code:'Space',windowsVirtualKeyCode:32,nativeVirtualKeyCode:32});
    const menu = await command('Runtime.evaluate',{expression:"(()=>{const p=document.querySelector('.demo-picker');const r=p.querySelector('.demo-menu').getBoundingClientRect();const s=getComputedStyle(p.querySelector('summary'));return {open:p.open,left:r.left,right:r.right,viewport:innerWidth,outline:s.outlineStyle}})()",returnByValue:true});
    assert.ok(menu.result.value.open,'Keyboard opens preview menu');
    assert.ok(menu.result.value.left>=0 && menu.result.value.right<=menu.result.value.viewport,'Mobile preview menu fits');
    assert.notEqual(menu.result.value.outline,'none','Visible keyboard focus');
    const menuShot = await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
    fs.writeFileSync(path.join(evidence,'preview-menu-mobile.png'),Buffer.from(menuShot.data,'base64'));
    await command('Runtime.evaluate',{expression:"document.querySelector('.demo-menu a[href=\"/wallet\"]').click()"});
    for(let n=0;n<50;n++){const r=await command('Runtime.evaluate',{expression:"location.pathname==='/wallet' && document.readyState==='complete'"});if(r.result.value)break;await new Promise(r=>setTimeout(r,100));}
    const navigation = await command('Runtime.evaluate',{expression:"location.pathname",returnByValue:true});
    assert.equal(navigation.result.value,'/wallet','Preview link navigates to wallet');
    const report = {browserExecutable:chrome,method:'Headless Chrome; fresh temporary profile; owned ephemeral CDP endpoint only; demo-origin GET/HEAD allowlist; CSS scale 1; full-page captures',httpPages:urls.length,assetCount:assetPaths.size,navigationTargets:destinations.size,browserChecks:results.length,keyboardMenu:true,actualWalletNavigation:true,security:'POST 405; secrets/database paths 404',screenshots:evidence,results};
    fs.writeFileSync(path.join(evidence,'qa.json'),JSON.stringify(report,null,2));
    console.log(JSON.stringify({...report,results:undefined},null,2));
    await send('Browser.close');
  } finally { if(socket)socket.close(); if(browser.exitCode===null)browser.kill(); }
}
main().catch(error=>{console.error(error);process.exitCode=1;});
