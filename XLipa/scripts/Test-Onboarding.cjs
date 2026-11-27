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
function check(value, label) { assert.ok(value, label); evidence.push(label); }
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
      });req.setTimeout(15000,()=>req.destroy(new Error('Local HTTP timeout')));req.on('error',reject);req.end(body);
    });
    for(const cookie of result.headers['set-cookie']||[]) {const pair=cookie.split(';')[0],i=pair.indexOf('=');this.cookies.set(pair.slice(0,i),pair.slice(i+1));}
    return result;
  }
  async form(url) {
    const response=await this.request(url);assert.equal(response.status,200,`GET ${url}`);
    const token=response.text.match(/name="_token"[^>]*value="([^"]+)"/);assert.ok(token,'Real Laravel CSRF');
    return {response,token:token[1]};
  }
}
async function account(label,role='merchant') {
  const email=`r23-${label}-${crypto.randomBytes(4).toString('hex')}@example.test`;
  const issued=JSON.parse(control('account',email,role));
  const account=JSON.parse(fs.readFileSync(path.join(root,`account-${issued.actor}.json`)));
  const browser=new Browser(),form=await browser.form('/xlipasignin.html');
  check((await browser.request('/login',{_token:form.token,email,password:account.password})).status===302,'Ordinary fictional login');
  const link=control('verify-link',email);
  check((await browser.request(link)).status===302,'Ordinary signed email verification');
  return {...account,...issued,email,browser};
}
(async()=>{
  const health=JSON.parse((await new Browser().request('/staging/status')).text);
  check(health.sapi==='cli-server' && !health.console && !health.testing,'Actual ordinary web runtime');
  check(health.schema_ready && health.financial==='denied','Accepted financial schema remains ready and denied');
  const owner=await account('owner');
  const headers=await owner.browser.request('/account');
  check(headers.headers['referrer-policy']==='same-origin','Staging browser forms preserve same-origin Origin without relaxing request admission');
  const dashboard=await owner.browser.request(`/merchants/${owner.merchant}/dashboard`);
  check(dashboard.status===200,'Unnamed merchant dashboard before OTP or identity');
  control('prepare-name',owner.email,'Fictional R23 Business');
  const route=`/merchants/${owner.merchant}/onboarding/complete`;
  const form=await owner.browser.form(route);
  const command=form.response.text.match(/name="command" value="([a-f0-9]{32})"/)[1];
  const result=await owner.browser.request(route,{_token:form.token,command,business_name:'Fictional R23 Business'});
  check(result.status===200 && JSON.parse(result.text).status==='completed',`Existing business-name service committed through ordinary route (HTTP ${result.status}, ${result.text.slice(0,300)})`);
  check((await owner.browser.request(route,{_token:form.token,command,business_name:'Fictional R23 Business'})).status===403,'Consumed business-name command cannot replay');
  const persisted=await owner.browser.request(`/merchants/${owner.merchant}/dashboard`);
  check(persisted.status===200 && persisted.text.includes('Fictional R23 Business'),'Persisted business name visible through ordinary dashboard');
  async function verifyAndSubmit(a, negative = false) {
    const verification=`/merchants/${a.merchant}/verification`;
    control('grant-workflow',a.email,'onboarding.verification.read');
    let form=await a.browser.form(verification);
    check(!form.response.text.includes('Capture and delivery are unavailable'),'Fictional verification service ready through signed scoped read');
    let version=form.response.text.match(/name="context_version" value="([0-9]+)"/)[1];
    control('grant-workflow',a.email,'onboarding.contact');
    let response=await a.browser.request(verification+'/contact',{_token:form.token,context_version:version,password:a.password,phone:'+255600000000'});
    check(response.status===302,'Ordinary encrypted fictional contact capture');
    form=await a.browser.form(verification);
    const requestId=form.response.text.match(/name="request_id" value="([a-f0-9-]+)"/)[1];
    control('grant-workflow',a.email,'onboarding.otp.request');
    response=await a.browser.request(verification+'/request',{_token:form.token,request_id:requestId});
    check(response.status===302,'Ordinary fictional OTP request');
    const capture=JSON.parse(fs.readFileSync(path.join(root,`otp-${requestId}.json`)));
    form=await a.browser.form(verification);
    const challenge=form.response.text.match(/name="challenge" value="([A-Za-z0-9]{26})"/)[1];
    control('grant-workflow',a.email,'onboarding.otp.verify');
    response=await a.browser.request(verification+'/code',{_token:form.token,challenge,code:capture.code});
    check(response.status===302,'Ordinary fictional OTP verification using private local capture');
    form=await a.browser.form(verification);
    check(form.response.text.includes('Simulated test proof only'),'Simulated proof is never presented as genuine possession');
    version=form.response.text.match(/name="context_version" value="([0-9]+)"/)[1];
    control('grant-workflow',a.email,'onboarding.identity');
    response=await a.browser.request(verification+'/identity',{_token:form.token,context_version:version,password:a.password,nin:'FICTIONAL-ABCDEFGH',subject_claim:'individual_owner',declaration:'1'});
    check(response.status===302,'Ordinary write-only encrypted synthetic identity declaration');
    form=await a.browser.form(verification);
    check(!form.response.text.includes('FICTIONAL-ABCDEFGH') && !form.response.text.includes(capture.code),'Identity and OTP absent from rendered status');
    control('attest-proof',a.email);
    control('grant-workflow',a.email,'onboarding.application.read');
    const applicationRoute=`/merchants/${a.merchant}/applications/integrated`;
    form=await a.browser.form(applicationRoute);
    const application=form.response.text.match(/data-application="([A-Za-z0-9]{26})"/)[1];
    control('prepare-submission',a.email);
    if (negative) {
      const grants=JSON.parse(fs.readFileSync(path.join(root,'capabilities.json')));
      const submitted=grants[`${a.actor}|onboarding.submit|${a.merchant}`].value;
      const proofPath=path.join(root,`proof-${submitted.expected.proof_id}.json`);
      const original=fs.readFileSync(proofPath);
      try {
        const forged=JSON.parse(original);forged.value.lineage='0'.repeat(64);fs.writeFileSync(proofPath,JSON.stringify(forged));
        const denied=await a.browser.request(applicationRoute+'/submit',{_token:form.token});
        check(denied.status===503,'Forged proof attestation rejected by the actual submission service');
        check((await a.browser.request(applicationRoute)).text.includes('draft'),'Rejected forged evidence leaves application unsubmitted');
      } finally {fs.writeFileSync(proofPath,original);}
      control('prepare-submission',a.email);
    }
    response=await a.browser.request(applicationRoute+'/submit',{_token:form.token});
    check(response.status===200 && JSON.parse(response.text).event==='application.submitted',`Existing application service through ordinary HTTP (${response.status})`);
    check((await a.browser.request(applicationRoute+'/submit',{_token:form.token})).status===403,'Submission command replay denied');
    check((await a.browser.request(applicationRoute)).text.includes('pending_review'),'Persisted submitted status visible through ordinary application');
    control('prepare-submission-resolution',a.email);
    const resolved=await a.browser.request(applicationRoute+'/resolve',{_token:form.token});
    check(resolved.status===200 && JSON.parse(resolved.text).submission_request_id===JSON.parse(response.text).submission_request_id,'Separately authorized submission resolution returns the original immutable event without replay');
    return application;
  }
  const application=await verifyAndSubmit(owner,true);
  const chief=await account('chief','chief_admin');
  check((await chief.browser.request(`/admin/applications/${application}`)).status===200,'Chief opens actual submitted application through existing review controller');
  control('grant-decision-read',chief.email,application);
  let review=await chief.browser.form(`/admin/applications/${application}/decision`);
  control('prepare-decision',chief.email,application,'approved');
  check((await chief.browser.request(`/admin/applications/${application}/decision`,{_token:review.token})).status===503,'Chief role and command without independent assurance denied');
  control('prepare-decision',chief.email,application,'approved');
  control('assure-chief',chief.email,application);
  let decision=await chief.browser.request(`/admin/applications/${application}/decision`,{_token:review.token});
  check(decision.status===200 && JSON.parse(decision.text).status==='approved',`Existing Chief decision service through ordinary HTTP (${decision.status})`);
  check((await chief.browser.request(`/admin/applications/${application}/decision`,{_token:review.token})).status===403,'Decision command replay denied');
  check((await owner.browser.request(`/merchants/${owner.merchant}/applications/integrated`)).text.includes('approved'),'Merchant sees persisted approval');
  check((await owner.browser.request(`/merchants/${owner.merchant}/reports`)).status===403,'Financial actions remain disabled after approval');
  const staff=await account('staff','admin');
  check((await staff.browser.request('/admin/staff')).status===200,'Staff retains its approved workspace');
  check((await staff.browser.request(`/admin/applications/${application}/decision`,{_token:(await staff.browser.form('/admin/staff')).token})).status===403,'Staff cannot decide Chief applications');
  check((await chief.browser.request(`/merchants/${owner.merchant}/onboarding/complete`)).status===404,'Chief does not gain merchant ownership');
  const rejected=await account('rejected');
  control('prepare-name',rejected.email,'Fictional Rejection Business');
  let rejectedForm=await rejected.browser.form(`/merchants/${rejected.merchant}/onboarding/complete`);
  const rejectedCommand=rejectedForm.response.text.match(/name="command" value="([a-f0-9]{32})"/)[1];
  check((await rejected.browser.request(`/merchants/${rejected.merchant}/onboarding/complete`,{_token:rejectedForm.token,command:rejectedCommand,business_name:'Fictional Rejection Business'})).status===200,'Separate fictional application completes its own business name');
  const secondApplication=await verifyAndSubmit(rejected);
  check((await owner.browser.request(`/merchants/${rejected.merchant}/onboarding/complete`)).status===404,'Merchant A cannot read Merchant B private completion');
  check((await owner.browser.request(`/merchants/${rejected.merchant}/verification/contact`,{_token:form.token,context_version:'0',password:owner.password,phone:'+255600000000'})).status===403,'Merchant A cannot mutate Merchant B verification');
  control('grant-decision-read',chief.email,secondApplication);
  review=await chief.browser.form(`/admin/applications/${secondApplication}/decision`);
  control('prepare-decision',chief.email,secondApplication,'rejected','fictional_incomplete_requirements');
  control('assure-chief',chief.email,secondApplication);
  decision=await chief.browser.request(`/admin/applications/${secondApplication}/decision`,{_token:review.token});
  check(decision.status===200 && JSON.parse(decision.text).status==='rejected','Separate application receives an immutable authorized rejection');
  check((await rejected.browser.request(`/merchants/${rejected.merchant}/applications/integrated`)).text.includes('rejected'),'Merchant sees persisted rejection');
  const revoked=JSON.parse(control('grant-workflow',rejected.email,'onboarding.application.read'));
  control('revoke',revoked.capability);
  check((await rejected.browser.request(`/merchants/${rejected.merchant}/applications/integrated`)).status===403,'Revoked actual workflow read capability denied');
  const logout=await owner.browser.form(`/merchants/${owner.merchant}/applications/integrated`);
  check((await owner.browser.request('/logout',{_token:logout.token})).status===302,'Ordinary logout invalidates the onboarding session');
  check((await owner.browser.request(`/merchants/${owner.merchant}/dashboard`)).status===401,'Logged-out JSON session cannot reopen the dashboard');
  const htmlDenial=await owner.browser.request(`/merchants/${owner.merchant}/dashboard`,undefined,'text/html');
  check(htmlDenial.status===302 && htmlDenial.headers.location.endsWith('/xlipasignin.html'),'Logged-out HTML session redirects to ordinary login');
  fs.writeFileSync(path.join(root,'browser-acceptance-handoff.json'),JSON.stringify({owner:{actor:owner.actor,merchant:owner.merchant,application},chief:{actor:chief.actor},staff:{actor:staff.actor},rejected:{actor:rejected.actor,merchant:rejected.merchant,application:secondApplication}}));
  fs.writeFileSync(path.join(root,'onboarding-acceptance.json'),JSON.stringify({runtime:state.runtime,origin,checks:evidence.length,evidence},null,2));
  process.stdout.write(JSON.stringify({status:'PASS',checks:evidence.length,runtime:state.runtime})+'\n');
})().catch(error=>{process.stderr.write(String(error)+'\n');process.exitCode=1;});
