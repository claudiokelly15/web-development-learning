// Owner-authorized isolated headless CDP acceptance. Never attach to a user's browser/profile.
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {spawn,execFileSync}=require('node:child_process');
const assert=require('node:assert/strict');
const http=require('node:http');
const origin='http://127.0.0.2:18762';
const anchor=path.join(__dirname,'.staging');
const state=JSON.parse(fs.readFileSync(path.join(anchor,'server.json')));
assert.match(state.runtime,/^run-[a-f0-9]{32}$/);
const root=path.join(anchor,state.runtime);
const handoff=JSON.parse(fs.readFileSync(path.join(root,'browser-acceptance-handoff.json')));
const php='C:/Users/hp/.config/herd/bin/php85/php.exe';
function control(op,...args){return execFileSync(php,['-d','auto_prepend_file=','-d','auto_append_file=','-d','allow_url_fopen=0',path.join(__dirname,'staging-control.php'),op,state.runtime,...args],{encoding:'utf8',stdio:['ignore','pipe','pipe']}).trim();}
const credentials=actor=>JSON.parse(fs.readFileSync(path.join(root,`account-${actor}.json`)));
async function main(){
  const originDenials=[];
  for(const supplied of [null,'null','https://fictional.example.test']){
    const status=await new Promise((resolve,reject)=>{const req=http.request(origin+'/login',{method:'POST',headers:{'Content-Length':'0',...(supplied===null?{}:{Origin:supplied})}},res=>{res.resume();res.on('end',()=>resolve(res.statusCode));});req.setTimeout(15000,()=>req.destroy(Error('Origin denial timeout')));req.on('error',reject);req.end();});
    assert.equal(status,403,'Missing/null/foreign Origin remains denied');originDenials.push({origin:supplied,status});
  }
  const chrome=['C:/Program Files/Google/Chrome/Application/chrome.exe','C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(fs.existsSync);
  assert.ok(chrome,'Existing installed Chromium required');
  const owned=fs.mkdtempSync(path.join(os.tmpdir(),'xlipa-r23-browser-'));
  const profile=path.join(owned,'profile');
  const evidence=path.join(__dirname,'../.recovery','r23-browser-'+Date.now());fs.mkdirSync(evidence);
  const browser=spawn(chrome,['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-background-networking','--disable-component-update','--disable-sync','--remote-debugging-address=127.0.0.1','--remote-debugging-port=0',`--user-data-dir=${profile}`,'about:blank'],{windowsHide:true,stdio:'ignore'});
  let socket,send,command;const results=[],blocked=[],loginRequests=[];
  try{
    const portFile=path.join(profile,'DevToolsActivePort');
    for(let n=0;n<150&&!fs.existsSync(portFile);n++)await new Promise(r=>setTimeout(r,100));
    const [port,endpoint]=fs.readFileSync(portFile,'utf8').trim().split(/\r?\n/);assert.match(port,/^\d+$/);assert.match(endpoint,/^\/devtools\/browser\/[a-f0-9-]+$/);
    socket=new WebSocket(`ws://127.0.0.1:${port}${endpoint}`);
    await new Promise((resolve,reject)=>{socket.addEventListener('open',resolve,{once:true});socket.addEventListener('error',reject,{once:true});});
    let id=0;const pending=new Map();
    socket.addEventListener('message',event=>{const d=JSON.parse(event.data),p=pending.get(d.id);if(p){pending.delete(d.id);clearTimeout(p.timer);d.error?p.reject(Error(d.error.message)):p.resolve(d.result);}});
    send=(method,params={},sessionId)=>new Promise((resolve,reject)=>{const key=++id;const timer=setTimeout(()=>{pending.delete(key);reject(Error(method+' timeout'));},20000);pending.set(key,{resolve,reject,timer});socket.send(JSON.stringify({id:key,method,params,...(sessionId?{sessionId}:{})}));});
    const {targetId}=await send('Target.createTarget',{url:'about:blank'});
    const {sessionId}=await send('Target.attachToTarget',{targetId,flatten:true});
    command=(method,params={})=>send(method,params,sessionId);
    await command('Page.enable');await command('Network.enable');
    socket.addEventListener('message',event=>{
      const d=JSON.parse(event.data);if(d.method!=='Fetch.requestPaused')return;
      const request=d.params.request,url=new URL(request.url);
      if(url.origin===origin&&url.pathname==='/login')loginRequests.push({method:request.method,origin:request.headers.Origin??request.headers.origin??null,referer:request.headers.Referer??null});
      const allowed=url.origin===origin&&(['GET','HEAD'].includes(request.method)||(request.method==='POST'&&['/login','/logout'].includes(url.pathname)));
      if(!allowed)blocked.push({origin:url.origin,method:request.method});
      command(allowed?'Fetch.continueRequest':'Fetch.failRequest',allowed?{requestId:d.params.requestId}:{requestId:d.params.requestId,errorReason:'BlockedByClient'}).catch(()=>{});
    });
    await command('Fetch.enable',{patterns:[{urlPattern:'*',requestStage:'Request'}]});
    const evaluate=async(expression)=>{const r=await command('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});assert.ok(!r.exceptionDetails,'Browser evaluation succeeds');return r.result.value;};
    async function ready(expected){for(let n=0;n<600;n++){const data=await evaluate('({url:location.href,ready:document.readyState})');if(data.ready==='complete'&&(!expected||data.url===origin+expected))return;await new Promise(r=>setTimeout(r,100));}const diagnostic=await evaluate('({path:location.pathname,origin:location.origin,title:document.title,text:document.body.textContent.slice(0,200),notice:document.querySelector("#notice")?.textContent,valid:document.querySelector("form")?.checkValidity()})');fs.writeFileSync(path.join(evidence,'navigation-diagnostic.json'),JSON.stringify({expected,diagnostic,blocked,loginRequests},null,2));throw Error('Expected local navigation did not complete: '+expected);}
    async function navigate(route){assert.ok(route.startsWith('/'));await command('Page.navigate',{url:origin+route});await ready(route);}
    async function login(actor,verify=false){
      const a=credentials(actor);await command('Network.clearBrowserCookies');await navigate('/xlipasignin.html');
      await evaluate(`(()=>{document.querySelector('input[name=email]').value=${JSON.stringify(a.email)};document.querySelector('input[name=password]').value=${JSON.stringify(a.password)};document.querySelector('input[name=password]').form.requestSubmit();})()`);
      await ready(verify?'/email/verify':'/account');
      if(verify){const link=new URL(control('verify-link',a.email));assert.equal(link.origin,origin);await command('Page.navigate',{url:link.href});await ready('/account');}
      assert.equal(await evaluate('location.origin'),origin);
    }
    const owner=credentials(handoff.owner.actor),chief=credentials(handoff.chief.actor);
    control('grant-workflow',owner.email,'onboarding.application.read');control('grant-workflow',owner.email,'onboarding.verification.read');
    control('grant-decision-read',chief.email,handoff.owner.application);
    const draft=JSON.parse(control('account',`r23-responsive-${Date.now()}@example.test`,'merchant'));
    const groups=[
      {role:'merchant',actor:handoff.owner.actor,pages:[`/merchants/${handoff.owner.merchant}/dashboard`,`/merchants/${handoff.owner.merchant}/onboarding`,`/merchants/${handoff.owner.merchant}/onboarding/complete`,`/merchants/${handoff.owner.merchant}/verification`,`/merchants/${handoff.owner.merchant}/applications/integrated`]},
      {role:'chief',actor:handoff.chief.actor,pages:['/admin/chief',`/admin/applications/${handoff.owner.application}`,`/admin/applications/${handoff.owner.application}/decision`]},
      {role:'staff',actor:handoff.staff.actor,pages:['/admin/staff']},
      {role:'draft',actor:draft.actor,draft:true,pages:[`/merchants/${draft.merchant}/onboarding/complete`,`/merchants/${draft.merchant}/verification`,`/merchants/${draft.merchant}/applications/integrated`]},
    ];
    for(const group of groups){
      await login(group.actor,!!group.draft);
      if(group.draft){const a=credentials(group.actor);control('prepare-name',a.email,'Fictional Responsive Business');control('grant-workflow',a.email,'onboarding.verification.read');control('grant-workflow',a.email,'onboarding.application.read');}
      for(const route of group.pages){
        await navigate(route);
        for(const [width,height] of [[390,844],[768,1024],[1440,1000]]){
          await command('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<500});
          const metrics=await evaluate(`(async()=>{await document.fonts.ready;await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));const fields=[...document.querySelectorAll('input:not([type=hidden]),textarea,select')];return {url:location.pathname,width:innerWidth,scroll:document.documentElement.scrollWidth,title:document.title,staging:!!document.querySelector('[data-xlipa-staging]'),brokenImages:[...document.images].filter(i=>!i.complete||!i.naturalWidth).length,styles:[...document.querySelectorAll('link[rel=stylesheet]')].every(l=>!!l.sheet),unlabelled:fields.filter(i=>!i.labels?.length&&!i.getAttribute('aria-label')&&!i.getAttribute('aria-labelledby')).length,sensitive:document.body.textContent.includes('FICTIONAL-ABCDEFGH'),forms:document.forms.length};})()`);
          assert.equal(metrics.url,route);assert.ok(metrics.staging);assert.ok(metrics.scroll<=width+1,`${route}: horizontal overflow ${metrics.scroll} at ${width}`);assert.equal(metrics.brokenImages,0);assert.ok(metrics.styles);assert.equal(metrics.unlabelled,0);assert.equal(metrics.sensitive,false);
          await command('Input.dispatchKeyEvent',{type:'keyDown',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});await command('Input.dispatchKeyEvent',{type:'keyUp',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});
          metrics.keyboardTarget=await evaluate('document.activeElement?.tagName');assert.ok(['A','BUTTON','INPUT','SELECT','TEXTAREA'].includes(metrics.keyboardTarget));
          const shot=await command('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
          const file=`${group.role}-${group.pages.indexOf(route)}-${width}.png`;fs.writeFileSync(path.join(evidence,file),Buffer.from(shot.data,'base64'));
          results.push({role:group.role,route,width,height,...metrics,screenshot:file});
        }
      }
    }
    const report={status:'PASS',runtime:state.runtime,origin,method:'Owned headless Chrome, fresh temporary profile, ephemeral loopback CDP, exact-origin GET/HEAD and login/logout POST only',pages:groups.reduce((n,g)=>n+g.pages.length,0),viewportChecks:results.length,originDenials,blocked,results};
    fs.writeFileSync(path.join(evidence,'qa.json'),JSON.stringify(report,null,2));
    fs.writeFileSync(path.join(root,'browser-qa-location.json'),JSON.stringify({evidence,status:'PASS',viewportChecks:results.length}));
    process.stdout.write(JSON.stringify({status:'PASS',pages:report.pages,viewportChecks:results.length,evidence})+'\n');
  }finally{
    if(send&&socket?.readyState===WebSocket.OPEN){await send('Browser.close').catch(()=>{});socket.close();}
    if(browser.exitCode===null)browser.kill();
  }
}
main().catch(error=>{process.stderr.write(String(error)+'\n');process.exitCode=1;});
