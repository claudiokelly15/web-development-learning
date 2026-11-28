const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const crypto = require('node:crypto');
const {execFileSync} = require('node:child_process');
const assert = require('node:assert/strict');
const anchor = path.join(__dirname, '.staging');
const state = JSON.parse(fs.readFileSync(path.join(anchor, 'server.json')));
const root = path.join(anchor, state.runtime);
const origin = 'http://127.0.0.2:18762';
const php = 'C:/Users/hp/.config/herd/bin/php85/php.exe';
const evidence = [];
function check(value, label) { assert.ok(value, label); evidence.push(label); process.stdout.write(JSON.stringify({check:label,status:'PASS'})+'\n'); }
function control(operation, ...args) {
  return execFileSync(php, ['-d','auto_prepend_file=','-d','auto_append_file=','-d','allow_url_fopen=0',path.join(__dirname,'staging-control.php'),operation,state.runtime,...args], {encoding:'utf8',stdio:['ignore','pipe','pipe']}).trim();
}
class Browser {
  cookies = new Map();
  async request(url, data, accept = 'application/json') {
    const target = new URL(url, origin); assert.equal(target.origin, origin);
    const body = data ? new URLSearchParams(data).toString() : '';
    const result = await new Promise((resolve,reject) => {
      const req=http.request(target,{method:data?'POST':'GET',headers:{Cookie:[...this.cookies].map(([k,v])=>`${k}=${v}`).join('; '),...(data?{'Content-Type':'application/x-www-form-urlencoded','Content-Length':Buffer.byteLength(body),Origin:origin}:{}),Accept:accept}},res=>{
        let text='';res.setEncoding('utf8');res.on('data',c=>text+=c);res.on('end',()=>resolve({status:res.statusCode,text,headers:res.headers}));
      });req.setTimeout(60000,()=>req.destroy(new Error('Local HTTP timeout')));req.on('error',reject);req.end(body);
    });
    for(const cookie of result.headers['set-cookie']||[]) {const pair=cookie.split(';')[0],i=pair.indexOf('=');this.cookies.set(pair.slice(0,i),pair.slice(i+1));}
    return result;
  }
  async form(url) {
    const response=await this.request(url,undefined,'text/html');assert.equal(response.status,200,`GET ${url}: ${response.text.slice(0,200)}`);
    const token=response.text.match(/name="_token"[^>]*value="([^"]+)"/);assert.ok(token,'Real Laravel CSRF');
    return {response,token:token[1]};
  }
}
function financial(op,...args) {
  return JSON.parse(execFileSync(php,['-d','auto_prepend_file=','-d','auto_append_file=','-d','allow_url_fopen=0',path.join(__dirname,'financial-control.php'),state.runtime,op,...args],{encoding:'utf8',stdio:['ignore','pipe','pipe']}));
}
(async()=>{
  const handoff=JSON.parse(fs.readFileSync(path.join(root,'browser-acceptance-handoff.json')));
  const owner=JSON.parse(fs.readFileSync(path.join(root,`account-${handoff.owner.actor}.json`)));
  const merchant=handoff.owner.merchant, base=`/merchants/${merchant}/payment-products`;
  const browser=new Browser(), login=await browser.form('/xlipasignin.html');
  check((await browser.request('/login',{_token:login.token,email:owner.email,password:owner.password})).status===302,'Ordinary payment owner login');
  const csrf=(await browser.form('/account')).token;
  const grant=(action,id='',ttl='600')=>financial('grant',owner.email,action,id,ttl);
  const post=(url,data={})=>browser.request(url,{_token:csrf,...data});
  if(process.argv.includes('--finish')) {
    const coreLog=path.join(__dirname,'../.recovery/r24-payments-core-accepted.log');
    const coreBytes=fs.readFileSync(coreLog);
    const coreText=coreBytes[0]===255&&coreBytes[1]===254?coreBytes.toString('utf16le'):coreBytes.toString('utf8');
    const prior=coreText.replace(/^\uFEFF/,'').split(/\r?\n/).filter(line=>line.startsWith('{"check":')).map(line=>JSON.parse(line));
    for(const label of ['Exactly one sealed double-entry journal','Fictional postings balance exactly','Merchant sees persisted posted transaction and zero withdrawable funds','Revoked financial capability rejected']) {
      assert.ok(prior.some(record=>record.check===label&&record.status==='PASS'),'Recover completed core acceptance: '+label);
    }
    evidence.push(...prior.map(record=>record.check));
    const totals=financial('status');
    assert.equal(totals.counts.ledger_journals,1);assert.equal(totals.counts.ledger_postings,2);assert.equal(totals.counts.ledger_seals,1);
    assert.ok(totals.balanced_postings.every(row=>row.amount===12500));
    grant('r24.products.read');
    const history=JSON.parse((await browser.request(base)).text);
    const link=history.links.find(row=>row.state==='active'&&row.description==='Fictional R24 responsive checkout');assert.ok(link);
    grant('r24.widgets.create',link.id);
    const response=await post(base+`/links/${link.id}/widgets`,{kind:'button',mode:'reusable'});assert.equal(response.status,200,response.text);
    const widget=JSON.parse(response.text);
    financial('checkout',owner.email,link.public_token);
    const checkout='/fictional-checkout/'+link.public_token, customer=new Browser();let limited=false;
    for(let n=0;n<60;n++) {
      const read=await customer.request(checkout,undefined,'text/html');
      assert.ok([200,429].includes(read.status),'Fresh checkout throttle probe HTTP '+read.status);
      if(read.status===429){limited=true;break;}
    }
    check(limited,'Guest checkout is rate limited across ordinary HTTP requests');
    financial('checkout',owner.email,link.public_token);
    grant('r24.widgets.read',widget.id);
    fs.writeFileSync(path.join(root,'payment-acceptance.json'),JSON.stringify({status:'PASS',runtime:state.runtime,checks:evidence.length,evidence,totals,continuedFrom:coreLog},null,2));
    fs.writeFileSync(path.join(root,'payment-browser-handoff.json'),JSON.stringify({actor:handoff.owner.actor,merchant,link,widget,checkout}));
    process.stdout.write(JSON.stringify({status:'PASS',checks:evidence.length,runtime:state.runtime,continued:true})+'\n');return;
  }
  const previous=JSON.parse(fs.readFileSync(path.join(root,'capabilities.json')))[`${handoff.owner.actor}|r24.products.read|${merchant}`];
  if(previous){control('revoke',previous.value.id);}
  check((await browser.request(base)).status===403,'Approval alone grants no financial authority');
  grant('r24.products.read');
  let form=await browser.form(base);
  let requestKey=()=>form.response.text.match(/name="request_key" value="([^"]+)"/)[1];
  grant('r24.links.create');
  check((await browser.request(base+'/links',{_token:'invalid'})).status===419,'Financial write requires the actual Laravel CSRF token');
  check((await post(base+'/links',{amount_tzs:'12.5',currency:'TZS',description:'Fictional',request_key:requestKey()})).status===422,'Fractional amounts rejected');
  async function create(description,expires) {
    form=await browser.form(base);grant('r24.links.create');
    const response=await post(base+'/links',{amount_tzs:'12500',currency:'TZS',description,request_key:requestKey(),...(expires?{expires_at:String(expires)}:{})});
    assert.equal(response.status,200,response.text);return JSON.parse(response.text);
  }
  const link=await create('Fictional R24 checkout');
  check(link.amount_tzs===12500,'Persisted fixed integer TZS link');
  check((await post(base+'/links',{amount_tzs:'12500',currency:'TZS',description:'Fictional',request_key:requestKey()})).status===403,'Consumed create command rejected');
  const customer=financial('checkout',owner.email,link.public_token), guest=new Browser();
  const checkout=new URL(customer.url).pathname;
  const open=await guest.form(checkout),nonce=open.response.text.match(/name="request_nonce" value="([^"]+)"/)[1];
  const submit=(extra={})=>guest.request(checkout,{_token:open.token,request_nonce:nonce,...extra});
  check((await submit({request_nonce:'0'.repeat(64)})).status===409,'Wrong customer nonce cannot dispatch a payment');
  check((await submit({amount_tzs:'1'})).status===422,'Guest cannot override fixed amount');
  let started=await submit();assert.equal(started.status,200,started.text);
  const attempt=JSON.parse(started.text), reference=attempt.payment_reference;
  check(attempt.status==='pending'&&!!reference,'Guest checkout creates persisted pending payment');
  check(JSON.parse((await submit()).text).payment_reference===reference,'Guest duplicate returns original payment without redispatch');
  const otherGuest=new Browser();
  check((await otherGuest.request(checkout)).status===403,'Checkout grant is bound to one customer session');
  grant('r24.ledger.post',attempt.attempt_id);
  check((await post(base+`/attempts/${attempt.attempt_id}/post`)).status===409,'Pending payment cannot post ledger');
  grant('r24.payment.read',reference);
  check((await browser.request(base+`/payments/${reference}`)).status===200,'Private payment status uses separate scope');
  financial('event',owner.email,reference,'ok','1');
  const callback=base+`/payments/${reference}/mock-confirmation`;
  const grants=JSON.parse(fs.readFileSync(path.join(root,'capabilities.json')));
  const eventFile=path.join(root,'mock-event-'+grants[`${handoff.owner.actor}|r24.mock.event|${merchant}`].value.command+'.json');
  const eventOriginal=fs.readFileSync(eventFile);
  try {
    const forged=JSON.parse(eventOriginal);forged.signature='0'.repeat(64);fs.writeFileSync(eventFile,JSON.stringify(forged));
    check((await post(callback)).status===401,'Forged mock-provider signature cannot confirm payment');
  } finally {fs.writeFileSync(eventFile,eventOriginal);}
  financial('event-repeat',owner.email,reference);
  let confirmed=await post(callback);assert.equal(confirmed.status,200,confirmed.text);
  check((await post(callback)).status===403,'Mock command cannot replay');
  financial('event-repeat',owner.email,reference);
  check((await post(callback)).status===200,'Separately authorized duplicate signed event is idempotent');
  const status=await guest.request(checkout+'?attempt='+attempt.attempt_id);
  check(JSON.parse(status.text).status==='confirmed','Customer status reflects persisted mock confirmation');
  grant('r24.ledger.post',attempt.attempt_id);
  const posted=await post(base+`/attempts/${attempt.attempt_id}/post`);assert.equal(posted.status,200,posted.text);
  grant('r24.ledger.reconcile',attempt.attempt_id);
  check((await post(base+`/attempts/${attempt.attempt_id}/reconcile`)).status===200,'Independent reconciliation resolves original sealed journal');
  grant('r24.transactions.read');
  for(const [result,expected] of [['declined','failed'],['uncertain','unknown']]) {
    grant('r24.payment.create');
    const created=await post(base+'/payments',{amount_tzs:'500',currency:'TZS'});assert.equal(created.status,200,created.text);
    const ref=JSON.parse(created.text).reference;
    financial('event',owner.email,ref,result,'1');
    const response=await post(base+`/payments/${ref}/mock-confirmation`);assert.equal(response.status,200,response.text);
    grant('r24.payment.read',ref);
    const read=await browser.request(base+'/payments/'+ref);assert.equal(read.status,200,read.text);
    check(JSON.parse(read.text).status===expected,'Ordinary persisted '+expected+' mock outcome');
  }
  const history=await browser.request(base+'/transactions');assert.equal(history.status,200,history.text);
  const data=JSON.parse(history.text);
  check(data.transactions.some(t=>t.payment_reference===reference&&t.accounting_status==='posted'&&t.withdrawable_tzs===0),'Merchant sees persisted posted transaction and zero withdrawable funds');
  grant('r24.widgets.create',link.id);
  const widgetResponse=await post(base+`/links/${link.id}/widgets`,{kind:'qr',mode:'reusable'});assert.equal(widgetResponse.status,200,widgetResponse.text);
  const widget=JSON.parse(widgetResponse.text);grant('r24.widgets.read',widget.id);
  check((await browser.request(base+'/widgets/'+widget.id)).status===200,'Scoped QR and button reference same checkout');
  grant('r24.links.disable',link.id);
  check((await post(base+`/links/${link.id}/disable`)).status===200,'Owner disables future checkout');
  check((await guest.request(checkout)).status===410,'Disabled link cannot open new checkout');
  check(JSON.parse((await guest.request(checkout+'?attempt='+attempt.attempt_id)).text).status==='confirmed','Historical customer status remains available after disable');
  const preview=await create('Fictional R24 responsive checkout');
  grant('r24.widgets.create',preview.id);
  const previewWidget=JSON.parse((await post(base+`/links/${preview.id}/widgets`,{kind:'button',mode:'reusable'})).text);
  financial('checkout',owner.email,preview.public_token);
  grant('r24.products.read','','0');
  await new Promise(resolve=>setTimeout(resolve,1100));
  check((await browser.request(base)).status===403,'Expired financial capability rejected');
  grant('r24.products.read');
  const revoked=grant('r24.transactions.read');control('revoke',revoked.capability);
  check((await browser.request(base+'/transactions')).status===403,'Revoked financial capability rejected');
  grant('r24.transactions.read');
  check((await browser.request(`/merchants/${handoff.rejected.merchant}/payment-products`)).status===403,'Merchant cannot borrow another tenant financial scope');
  for(const role of ['chief','staff']) {
    const credentials=JSON.parse(fs.readFileSync(path.join(root,`account-${handoff[role].actor}.json`)));
    const outsider=new Browser(),login=await outsider.form('/xlipasignin.html');
    check((await outsider.request('/login',{_token:login.token,email:credentials.email,password:credentials.password})).status===302,'Ordinary '+role+' login for financial denial');
    check((await outsider.request(base)).status===403,role+' has no merchant financial authority');
    assert.throws(()=>financial('grant',credentials.email,'r24.products.read'));
    evidence.push('Custodian refuses financial grant to '+role);
  }
  for(const route of ['/register',`/merchants/${merchant}/withdrawals`,`/merchants/${merchant}/reports`,'/api/payments','/webhooks/selcom']) {
    check((await browser.request(route)).status===403,'Operational route denied: '+route);
  }
  const totals=financial('status');
  check(totals.counts.ledger_journals===1&&totals.counts.ledger_seals===1&&totals.counts.ledger_postings===2,'Exactly one sealed double-entry journal');
  check(totals.balanced_postings.length===2&&totals.balanced_postings.every(p=>p.amount===12500),'Fictional postings balance exactly');
  check(totals.counts.api_credentials===0&&totals.counts.merchant_webhook_deliveries===0&&totals.counts.funds_withdrawals===0&&!totals.operational_store_bound,'API, webhooks, withdrawals and operational bindings remain disabled');
  financial('checkout',owner.email,preview.public_token);
  const rateCustomer=new Browser();let limited=false;
  for(let n=0;n<60;n++){const read=await rateCustomer.request('/fictional-checkout/'+preview.public_token);assert.ok([200,429].includes(read.status),'Fresh throttle probe '+read.status);if(read.status===429){limited=true;break;}}
  check(limited,'Guest checkout is rate limited across ordinary HTTP requests');
  financial('checkout',owner.email,preview.public_token);
  fs.writeFileSync(path.join(root,'payment-acceptance.json'),JSON.stringify({status:'PASS',runtime:state.runtime,checks:evidence.length,evidence,totals},null,2));
  fs.writeFileSync(path.join(root,'payment-browser-handoff.json'),JSON.stringify({actor:handoff.owner.actor,merchant,link:preview,widget:previewWidget,attempt,checkout:'/fictional-checkout/'+preview.public_token,customerStatus:checkout+'?attempt='+attempt.attempt_id,customerCookies:[...guest.cookies].map(([name,value])=>({name,value,url:origin}))}));
  process.stdout.write(JSON.stringify({status:'PASS',checks:evidence.length,runtime:state.runtime})+'\n');
})().catch(error=>{process.stderr.write(String(error)+'\n');process.exitCode=1;});
