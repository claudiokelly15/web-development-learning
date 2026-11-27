const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const crypto = require('node:crypto');
const {execFileSync} = require('node:child_process');
const assert = require('node:assert/strict');

const anchor = path.join(__dirname, '.staging');
const state = JSON.parse(fs.readFileSync(path.join(anchor, 'server.json')));
const root = path.join(anchor, state.runtime);
const php = process.env.XLIPA_PHP || 'C:/Users/hp/.config/herd/bin/php85/php.exe';
const origin = 'http://127.0.0.2:18762';
const evidence = [];
function control(operation, ...args) {
  return execFileSync(php, ['-d','auto_prepend_file=','-d','auto_append_file=','-d','allow_url_fopen=0', path.join(__dirname,'staging-control.php'),operation,state.runtime,...args], {encoding:'utf8', stdio:['ignore','pipe','pipe']}).trim();
}
class Browser {
  cookies = new Map();
  async request(url, data, headers = {}) {
    const target = new URL(url, origin);
    assert.equal(target.origin, origin, 'Never follow an operational/external URL');
    const body = data ? new URLSearchParams(data).toString() : '';
    const response = await new Promise((resolve,reject) => {
      const req = http.request(target, {method:data?'POST':'GET',headers:{Cookie:[...this.cookies].map(([k,v])=>`${k}=${v}`).join('; '), ...(data?{'Content-Type':'application/x-www-form-urlencoded', 'Content-Length':Buffer.byteLength(body), Origin:origin}:{}), ...headers}}, res=>{
        let text=''; res.setEncoding('utf8'); res.on('data',chunk=>text+=chunk); res.on('end',()=>resolve({status:res.statusCode,headers:res.headers,text}));
      });
      req.setTimeout(15000,()=>req.destroy(new Error('Staging HTTP timeout'))); req.on('error',reject); req.end(body);
    });
    for(const cookie of response.headers['set-cookie']||[]) {
      const pair=cookie.split(';')[0]; const i=pair.indexOf('='); this.cookies.set(pair.slice(0,i),pair.slice(i+1));
    }
    return response;
  }
  async csrf(page='/xlipasignin.html') {
    const response=await this.request(page); assert.equal(response.status,200,`CSRF form ${page}`);
    const token=response.text.match(/name="_token"[^>]*value="([^"]+)"/);
    assert.ok(token,'Real Laravel CSRF field'); return token[1];
  }
}
function check(condition,message) {assert.ok(condition,message); evidence.push(message);}
function account(label,role='merchant') {
  const email=`r21-${label}-${crypto.randomBytes(3).toString('hex')}@example.test`;
  const provisioned=JSON.parse(control('account',email,role));
  const credentials=JSON.parse(fs.readFileSync(path.join(root,`account-${provisioned.actor}.json`)));
  return {...credentials,...provisioned,browser:new Browser()};
}
async function login(a,verify=true) {
  const csrf=await a.browser.csrf(); const before=new Map(a.browser.cookies);
  const response=await a.browser.request('/login',{_token:csrf,email:a.email,password:a.password});
  check(response.status===302,'Ordinary login redirects');
  const session=[...a.browser.cookies.keys()].find(k=>k.startsWith('xlipa_r21_'));
  check(!!session && before.get(session)!==a.browser.cookies.get(session),'Dedicated session rotates on login');
  check(response.headers['set-cookie'].some(c=>c.includes('httponly')&&c.includes('samesite=strict')),'HttpOnly and SameSite Strict cookie');
  if(verify) {
    const link=control('verify-link',a.email);
    check((await a.browser.request(link)).status===302,'Ordinary signed fictional email verification');
  }
}
async function main() {
  const guest=new Browser();
  let response=await guest.request('/staging/status');
  check(response.status===200,'Dedicated staging origin is healthy');
  const health=JSON.parse(response.text);
  check(health.runtime===state.runtime && health.sapi==='cli-server'&&!health.console&&!health.testing,'Actual web SAPI; no CLI/testing spoof');
  check(health.schema_ready===true && health.financial_schema==='xlipa-financial-schema-v1' && health.financial==='denied','Financial schema ready while financial actions remain denied');
  const profile=JSON.parse(control('financial-status'));
  check(profile.status==='ready' && profile.steps.length===8 && profile.objects.table>30,'Custodian verified full financial schema catalogue and dependency ledger');
  const repeated=JSON.parse(control('financial-provision'));
  check(repeated.repeated===true && repeated.profile===profile.profile,'Repeated trusted provisioning is an independently validated no-op');
  const ticket=path.join(root,'provisioning.json');const savedTicket=fs.readFileSync(ticket);
  const financialPath=path.join(root,'financial.sqlite');
  const fingerprint=crypto.createHash('sha256').update(fs.readFileSync(financialPath)).digest('hex');
  try {
    const wrong=JSON.parse(savedTicket);wrong.value.store='untrusted-store';fs.writeFileSync(ticket,JSON.stringify(wrong));
    let denied=false;try {control('financial-provision');} catch {denied=true;}
    check(denied && fingerprint===crypto.createHash('sha256').update(fs.readFileSync(financialPath)).digest('hex'),'Invalid provisioning signature denied before changing the disposable financial store');
  } finally {fs.writeFileSync(ticket,savedTicket);}
  const safety=JSON.parse(control('safety'));
  check(safety.denied.length===3 && safety.mail==='array-only' && safety.financial==='unbound','Provider, webhook, OTP HTTP denied; mail captured; finance unbound');
  const owner=account('owner'); const foreign=account('foreign'); const chief=account('chief','chief_admin'); const staff=account('staff','admin');
  await login(owner,false);
  response=await owner.browser.request(`/merchants/${owner.merchant}/dashboard`);
  check(response.status===302 && response.headers.location?.includes('/email/verify'),`Unverified account cannot access dashboard (${response.status}, ${response.headers.location||'no redirect'})`);
  const link=control('verify-link',owner.email);
  const bad=new URL(link); bad.searchParams.set('signature','0'.repeat(64));
  check((await owner.browser.request(bad.toString())).status===403,'Tampered signed verification denied');
  check((await owner.browser.request(link)).status===302,'Valid local fictional verification accepted');
  response=await owner.browser.request(`/merchants/${owner.merchant}/dashboard`);
  check(response.status===200 && response.text.includes('Business name not provided'),'Existing dashboard renders actual unnamed staging merchant');
  check(response.text.includes('Fictional disposable staging'),'Staging indicator is visible');
  check(response.text.includes('unavailable')||response.text.includes('Unavailable'),'Unintegrated services remain unavailable');
  check((await owner.browser.request(`/merchants/${foreign.merchant}/dashboard`)).status===403,'Foreign tenant denied');
  check((await owner.browser.request('/admin/chief')).status===403,'Merchant cannot read Chief workspace');
  await login(chief);
  check((await chief.browser.request('/admin/chief')).status===200,'Existing Chief workspace renders through ordinary controller');
  check((await chief.browser.request(`/merchants/${owner.merchant}/dashboard`)).status===403,'Chief is not merchant owner');
  await login(staff);
  check((await staff.browser.request('/admin/staff')).status===200,'Existing Staff workspace renders through ordinary controller');
  check((await staff.browser.request('/admin/chief')).status===403,'Staff denied Chief workspace');
  check((await owner.browser.request('/staging/admission')).status===403,'Missing independent capability denied');
  const grant=JSON.parse(control('grant-probe',owner.email));
  check((await owner.browser.request('/staging/admission')).status===200,'Narrow independent signed probe admitted');
  control('revoke',grant.capability);
  check((await owner.browser.request('/staging/admission')).status===403,'Durably revoked capability denied');
  const command=crypto.randomBytes(16).toString('hex'); control('grant-probe',owner.email,command);
  let token=await owner.browser.csrf('/account');
  check((await owner.browser.request('/staging/command',{_token:token,command})).status===200,'One-shot foundation command consumed without financial effect');
  check((await owner.browser.request('/staging/command',{_token:token,command})).status===403,'Duplicate command denied');
  check((await owner.browser.request('/login',{email:owner.email,password:owner.password})).status===419,'Real web CSRF enforcement');
  check((await guest.request('/login',{email:owner.email,password:owner.password},{Origin:'https://example.test'})).status===403,'Foreign Origin denied before authentication');
  for(const route of [`/merchants/${owner.merchant}/verification/identity`,`/merchants/${owner.merchant}/reports`,'/api/payments','/webhooks','/register','/staging/financial-provision']) {
    check((await owner.browser.request(route,{_token:token})).status===403,`Future/identity/financial surface denied: ${route.split('/').pop()}`);
  }
  check((await guest.request('/login',{email:'real@example.com',password:'not-used'})).status===403,'Real contact input denied');
  check((await guest.request('/staging/status?APP_ENV=testing&XLIPA_SYNTHETIC_ONLY=1')).status===200,'Request flags do not change the admitted web runtime');
  const alias=path.join(root,'junction-probe');
  const target=path.join(root,'junction-target');fs.mkdirSync(target);fs.writeFileSync(path.join(target,'canary'),'synthetic-only');
  try {
    fs.symlinkSync(target,alias,'junction');
    let denied=false;
    try {
      execFileSync(php,['-d','auto_prepend_file=','-d','auto_append_file=','-r','require $argv[1]; App\\Staging\\StagingIdentity::path($argv[2]);',path.join(__dirname,'../backend/vendor/autoload.php'),alias.replaceAll('\\','/')],{stdio:'pipe'});
    } catch {denied=true;}
    check(denied && fs.readFileSync(path.join(target,'canary'),'utf8')==='synthetic-only','Windows junction rejected before canary access');
  } finally {
    if(fs.existsSync(alias))fs.rmdirSync(alias);
    fs.unlinkSync(path.join(target,'canary'));fs.rmdirSync(target);
  }
  response=await guest.request('/staging/status',undefined,{Host:'xlipa.test'});
  check(response.status===503,'Wrong host denied before Laravel boot');
  // Signed manifest tampering and ownership loss must fail before a canary can be opened.
  const marker=path.join(root,'owner.json'); const savedMarker=fs.readFileSync(marker);
  const manifest=path.join(root,'manifest.json'); const savedManifest=fs.readFileSync(manifest);
  const canary=path.join(root,'canary'); fs.writeFileSync(canary,'synthetic must remain untouched'); const canaryBefore=fs.readFileSync(canary);
  try {
    fs.renameSync(marker,marker+'.held');
    check((await guest.request('/staging/status')).status===503,'Missing ownership marker denied');
    fs.renameSync(marker+'.held',marker);
    const changed=JSON.parse(savedManifest); changed.value.root=path.join(root,'..','canary'); fs.writeFileSync(manifest,JSON.stringify(changed));
    check((await guest.request('/staging/status')).status===503,'Tampered target manifest denied');
    check(fs.readFileSync(canary).equals(canaryBefore),'Denied synthetic canary unchanged');
  } finally {
    if(fs.existsSync(marker+'.held'))fs.renameSync(marker+'.held',marker);
    fs.writeFileSync(marker,savedMarker);fs.writeFileSync(manifest,savedManifest);fs.unlinkSync(canary);
  }
  token=await owner.browser.csrf('/account'); const oldCookies=new Map(owner.browser.cookies);
  check((await owner.browser.request('/logout',{_token:token})).status===302,'Ordinary logout succeeds');
  const replay=new Browser();replay.cookies=oldCookies;
  check((await replay.request('/account')).status===302,'Old session cannot be reused after logout');
  await login(foreign);
  control('invalidate',foreign.email);
  check((await foreign.browser.request('/account')).status===302,'Changed auth_version invalidates existing session');
  const suspended=account('suspended');await login(suspended);control('suspend',suspended.email);
  check((await suspended.browser.request('/account')).status===302,'Suspended account loses existing session');
  const revoked=account('revoked');await login(revoked);control('revoke-membership',revoked.email);
  check((await revoked.browser.request(`/merchants/${revoked.merchant}/dashboard`)).status===404,'Revoked membership denied by existing tenant resolver');
  const reset=account('reset');await login(reset);const resetOld=new Map(reset.browser.cookies);
  control('reset-token',reset.email);const captured=JSON.parse(fs.readFileSync(path.join(root,`reset-${reset.actor}.json`)));
  const resetBrowser=new Browser();token=await resetBrowser.csrf('/forgot-password');
  const newPassword='Fictional-reset-'+crypto.randomBytes(10).toString('hex');
  response=await resetBrowser.request('/reset-password',{_token:token,token:captured.token,email:reset.email,password:newPassword,password_confirmation:newPassword});
  check(response.status===302 && response.headers.location.endsWith('/xlipasignin.html'),'Existing foundation password reset completes through ordinary controller');
  const oldReset=new Browser();oldReset.cookies=resetOld;
  check((await oldReset.request('/account')).status===302,'Password reset invalidates prior auth_version');
  reset.password=newPassword;reset.browser=new Browser();await login(reset,false);
  check((await reset.browser.request('/account')).status===200,'New password authenticates after reset');
  check((await reset.browser.request(`/merchants/${reset.merchant}/dashboard`)).status===403,'Password reset invalidates prior signed staging assurance');
  fs.writeFileSync(path.join(root,'http-acceptance.json'),JSON.stringify({runtime:state.runtime,origin,checks:evidence.length,evidence},null,2));
  console.log(JSON.stringify({status:'PASS',origin,checks:evidence.length,evidence:path.join(root,'http-acceptance.json')}));
}
main().catch(error=>{console.error(error.stack);process.exitCode=1;});
