// Static snapshot server only: never boots Laravel or opens a database.
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const [directory, portText, token] = process.argv.slice(2);
const root = path.resolve(directory);
if (path.dirname(root) !== __dirname || !/^demo-export-[a-f0-9]{32}$/.test(path.basename(root)) || !/^[a-f0-9]{32}$/.test(token)) throw Error('Invalid owned export');
const port = Number(portText);
if (!Number.isInteger(port) || port < 1024 || port > 65535) throw Error('Invalid port');
const pages = JSON.parse(fs.readFileSync(path.join(root, 'pages.json'), 'utf8'));
const titles = Object.keys(pages);
const routes = new Map(Object.entries(pages).map(([name, data]) => [new URL(data.url, 'http://xlipa.test').pathname, '/'+name]));
routes.set('/xlipahomepage.html', '/public-home');
const esc = value => value.replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
const groups = {
  'Public website': titles.filter(n=>['public-home','about','contact','developers','terms','privacy','disclaimer','prohibited-businesses'].includes(n)),
  'Account and authentication': titles.filter(n=>['sign-in','registration','forgot-password','reset-password','email-verification','merchant-settings'].includes(n)),
  'Merchant workspace': titles.filter(n=>['merchant-dashboard','business-profile','business-name-completion','verification','applications','payment-links','wallet','merchant-analytics','settlement-withdrawals','transactions','transaction-detail'].includes(n)),
  'Customer experience': titles.filter(n=>n.startsWith('checkout-') || n==='customer-checkout'),
  'Platform administration': titles.filter(n=>['chief-dashboard','chief-financial-oversight','application-review','decision-history','platform-access','staff-dashboard','staff-access'].includes(n)),
  'Payment details': titles.filter(n=>n.startsWith('transaction-') && n!=='transaction-detail'),
};
const label = n => ({'public-home':'Homepage','business-profile':'Business Profile','merchant-settings':'Account settings','decision-history':'Application decision history','checkout-unresolved':'Checkout · outcome unknown'}[n] || n.replaceAll('-', ' ').replace(/^./, s=>s.toUpperCase()));
const directoryLinks = Object.entries(groups).map(([group,names])=>`<section><h2>${group}</h2>${names.map(n=>`<a href="/${n}">${label(n)}</a>`).join('')}</section>`).join('');
const nav = `<div class="demo-bar" aria-label="Demo navigation"><a class="demo-brand" href="/">XLipa<span>.</span></a><span class="demo-indicator">Fictional · read-only</span><a href="/merchant-dashboard">Merchant</a><a href="/chief-dashboard">Chief</a><a href="/staff-dashboard">Staff</a><details class="demo-picker"><summary>Browse previews</summary><div class="demo-menu">${directoryLinks}<a href="/features">Feature availability</a><p>Preview available means a read-only page. Isolated functionality runs only in tests. Live use is operationally disabled. Planned features are not yet implemented.</p></div></details></div>`;
const css = `.demo-bar{position:relative;z-index:10000;display:flex;align-items:center;gap:20px;padding:9px 24px;min-height:48px;background:#10245a;color:#e6efff;font:12px/1.5 system-ui}.demo-bar a{color:inherit;text-decoration:none}.demo-bar a:hover{text-decoration:underline}.demo-brand{font-size:19px;font-weight:750;letter-spacing:-.8px}.demo-brand span{color:#8cb9ff}.demo-indicator{font-size:10px;color:#c9dcff;border:1px solid #536a98;padding:3px 8px;border-radius:20px;margin-right:auto}.demo-picker summary{cursor:pointer;color:#fff;font-weight:600}.demo-menu{position:absolute;right:20px;top:48px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px;width:min(900px,calc(100% - 40px));max-height:75vh;overflow:auto;background:#fff;border:1px solid #dce5f3;border-radius:14px;box-shadow:0 16px 60px #071a3540;padding:24px;color:#10245a}.demo-menu h2{font-size:11px;text-transform:uppercase;letter-spacing:1px;margin:0 0 10px}.demo-menu a{display:block;padding:6px 0;font-size:12px}.demo-menu p{grid-column:1/-1;font-size:11px;color:#52647c}.demo-home{max-width:1180px;margin:auto;padding:50px 24px;font:15px/1.7 system-ui}.demo-home h1{font-size:clamp(30px,4vw,46px);letter-spacing:-1.5px;color:#10245a;line-height:1.15;margin:12px 0 20px}.demo-home>p{max-width:750px;color:#52647c}.demo-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px;margin:32px 0}.demo-grid section{border:1px solid #dce5f3;border-radius:16px;padding:24px;background:white}.demo-grid h2{font-size:17px;color:#10245a;margin-bottom:16px}.demo-grid a{display:block;padding:6px 0;color:#2464a8;font-size:13px;text-decoration:none}.demo-grid a:hover{text-decoration:underline}.demo-status{display:inline-block;background:#edf4ff;color:#225b9b;padding:4px 9px;border-radius:6px;font-size:11px;font-weight:650}.demo-home table{width:100%;border-collapse:collapse;margin-top:24px;font-size:13px}.demo-home td,.demo-home th{text-align:left;padding:14px;border-bottom:1px solid #dce5f3;vertical-align:top}.demo-home th{background:#edf4ff}[data-safe-preview] fieldset{min-width:0;border:0;padding:0}[data-safe-preview] form>fieldset>legend{font-size:11px;color:#536985;margin-bottom:14px}[data-safe-preview] .burger{display:none!important}[data-safe-preview] header .nav{flex-wrap:wrap;gap:12px}[data-safe-preview] #menu{display:flex!important;position:static;width:auto;max-width:100%;box-shadow:none;background:transparent;padding:0;gap:16px}[data-safe-preview] #menu ul{display:flex!important;flex-direction:row!important;align-items:center!important;flex-wrap:wrap;gap:14px!important;margin:0!important;padding:0!important} [data-safe-preview] #menu ul li{margin:0!important} [data-safe-preview] #menu ul a{padding:5px 0!important;min-height:30px!important}[data-safe-preview] .product-demo-nav-state{font-size:9px;color:#2863a5} @media(max-width:760px){.demo-bar{gap:12px;padding:9px 14px;flex-wrap:wrap}.demo-bar>a:not(.demo-brand){display:none}.demo-indicator{font-size:9px}.demo-menu{grid-template-columns:1fr 1fr;right:10px;width:calc(100% - 20px);padding:18px}.demo-grid{grid-template-columns:1fr}.demo-home{padding:30px 20px}.demo-home table{font-size:11px}.demo-home td,.demo-home th{padding:10px 7px}[data-safe-preview] #menu{flex-wrap:wrap!important;gap:10px!important}[data-safe-preview] #menu ul{font-size:12px;gap:12px!important}[data-safe-preview] .nav-actions{gap:10px!important}}`;
const assets = new Map();
const rendered = new Map();
for (const [name, data] of Object.entries(pages)) {
  if (data.file !== name+'.html' || !/^[a-z-]+$/.test(name)) throw Error('Invalid page');
  let html = fs.readFileSync(path.join(root, data.file), 'utf8');
  html = html.replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi, '');
  html = html.replace(/\s+on[a-z]+="[^"]*"/gi, '');
  html = html.replace(/\s(?:href|src)="([^"]*)"/gi, (whole, value) => {
    if (value.startsWith('#')) return whole;
    const url = new URL(value, 'http://xlipa.test');
    if (url.pathname.startsWith('/assets/') && /^\/assets\/(?:payment-logos\/)?[a-zA-Z0-9_.-]+\.(css|svg|png|jpg|jpeg|webp|woff2?)$/.test(url.pathname)) {
      const file = path.join(__dirname, '../backend/public', url.pathname);
      if (fs.existsSync(file)) assets.set(url.pathname, fs.readFileSync(file));
      return whole.replace(value, url.pathname);
    }
    if (url.pathname === '/') return whole.replace(value, '/public-home'+url.hash);
    if (url.pathname === '/admin/access') return whole.replace(value, name.startsWith('staff-') ? '/staff-access' : '/platform-access');
    return whole.replace(value, (routes.get(url.pathname) || '/unavailable')+url.hash);
  });
  html = html.replace(/<form\b([^>]*)>/gi, (_, attributes) => {
    const visual = [...attributes.matchAll(/\b(class|id|aria-label|aria-labelledby)="([^"]*)"/g)].map(match => `${match[1]}="${match[2]}"`).join(' ');
    return `<form ${visual} action="/unavailable" method="get"><fieldset disabled><legend>Read-only preview</legend>`;
  }).replace(/<\/form>/gi, '</fieldset></form>');
  html = html.replace(/https?:\/\/xlipa\.test(\/[^<\s"']*)?/g, (_, suffix) => `http://127.0.0.1:${port}${routes.get(new URL(suffix || '/', 'http://xlipa.test').pathname) || '/unavailable'}`);
  html = html.replace(/Isolated functionality<\/span>/g, 'Preview available</span>');
  html = html.replace(/<a href="[^"]*"([^>]*)><span>Checkout<\/span>/g, '<a href="/customer-checkout"$1><span>Checkout</span>');
  html = html.replace(/<head([^>]*)>/i, `<head$1><style>${css}</style>`).replace(/<body([^>]*)>/i, `<body$1 data-safe-preview>${nav}`);
  rendered.set('/'+name, html);
}
const shell = body => `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>XLipa safe demo</title><link rel="icon" href="/assets/xlipa-favicon.svg"><style>${css}body{margin:0;background:#f5f8fc}*{box-sizing:border-box}a:focus-visible,summary:focus-visible{outline:3px solid #75adf6;outline-offset:4px}</style></head><body>${nav}<main class="demo-home">${body}</main></body></html>`;
rendered.set('/', shell(`<span class="demo-status">XLIPA PRODUCT PREVIEW</span><h1>One platform.<br>A clearer view of your business.</h1><p>Explore the public website, merchant workspace and role-specific administration. Every preview uses XLipa views and fictional data. No password is needed; forms and browser writes are disabled.</p><div class="demo-grid">${directoryLinks}</div><p><a href="/features">Read the complete feature availability guide →</a></p>`));
rendered.set('/features', shell(`<span class="demo-status">FEATURE AVAILABILITY</span><h1>Know what you can inspect.</h1><p><strong>Preview available</strong> means a page can be inspected here. <strong>Isolated functionality</strong> means existing services execute only in safe tests. <strong>Operationally disabled</strong> means live use is not authorized. <strong>Not yet implemented</strong> means a capability does not exist.</p><table><thead><tr><th>Area</th><th>What exists</th><th>Live availability</th></tr></thead><tbody><tr><td>Onboarding and applications</td><td>Read-only business, verification and application pages. Completion, submission and decisions demonstrated in isolated tests.</td><td>Operationally disabled writes; no live OTP or NIN.</td></tr><tr><td>Payments, links, checkout</td><td>Preview available. Fictional confirmed, pending, failed and unresolved records from real isolated services.</td><td>Operationally disabled. No live Selcom integration.</td></tr><tr><td>Wallet and transactions</td><td>Preview available. Ledger-derived fictional obligations; withdrawable remains TZS 0. No double counting.</td><td>Operational financial access disabled; settlement unproven.</td></tr><tr><td>Analytics</td><td>Calculated traffic-source and authenticated synthetic payment-method tables, reconciled percentages, cross-dimension totals, EAT ranges and source trends.</td><td>Isolated preview only. Operational analytics and file exports are unavailable.</td></tr><tr><td>Withdrawals and settlements</td><td>Independent fictional settlement, immutable ledger lifecycle journals, reservations, masked destinations, failed and unresolved payouts and reconciled fictional receipts.</td><td>Isolated CLI services only. Browser forms and operational payouts remain disabled.</td></tr><tr><td>Chief and Staff administration</td><td>Role-specific previews, Chief redacted application review, decision history and independently granted fictional financial oversight. Staff authorization boundaries retained.</td><td>No operational decision writes. Dedicated merchant management, risk, fees, disputes, staff management and system settings are not yet implemented.</td></tr><tr><td>Developer tools</td><td>Public product guide and availability notes.</td><td>Production keys, documented payment endpoints and webhook delivery are not yet implemented.</td></tr><tr><td>Account and support</td><td>Account, sign-in, registration, email verification, password recovery and contact previews.</td><td>Demo forms disabled. Notification center, preferences and assigned-task tools are not yet implemented.</td></tr></tbody></table>`));
rendered.set('/unavailable', shell('<h1>Unavailable in this visual demo</h1><p>This destination or operation has no exported read-only page. Use the demo navigation. No operation has been executed.</p>'));
const mime = {'.css':'text/css','.svg':'image/svg+xml','.png':'image/png','.jpg':'image/jpeg','.jpeg':'image/jpeg','.webp':'image/webp','.woff':'font/woff','.woff2':'font/woff2'};
http.createServer((req, res) => {
  if (req.headers.host !== `127.0.0.1:${port}` || req.socket.remoteAddress !== '127.0.0.1') { res.writeHead(403).end(); return; }
  res.setHeader('Cache-Control', 'no-store');
  res.setHeader('X-Content-Type-Options', 'nosniff');
  res.setHeader('Referrer-Policy', 'no-referrer');
  res.setHeader('X-Robots-Tag', 'noindex, nofollow');
  res.setHeader('Content-Security-Policy', "default-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; form-action 'none'; frame-ancestors 'none'; base-uri 'none'");
  if (!['GET','HEAD'].includes(req.method)) { res.writeHead(405, {'Allow':'GET, HEAD'}).end(); return; }
  if (req.url === '/__demo-health') { res.writeHead(200, {'Content-Type':'application/json'}).end(JSON.stringify({token, pages:titles.length, mode:'static-visual-only'})); return; }
  const url = new URL(req.url, `http://127.0.0.1:${port}`);
  const body = rendered.get(url.pathname) || assets.get(url.pathname);
  if (!body) { res.writeHead(404).end('Not found'); return; }
  res.writeHead(200, {'Content-Type': rendered.has(url.pathname) ? 'text/html; charset=utf-8' : mime[path.extname(url.pathname)]});
  res.end(req.method === 'HEAD' ? undefined : body);
}).listen(port, '127.0.0.1', () => process.stdout.write(`XLipa visual demo http://127.0.0.1:${port}/\n`));
