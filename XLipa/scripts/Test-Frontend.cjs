// Test-only DOM execution. jsdom does not provide browser layout or visual QA.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM, VirtualConsole } = require('../.recovery/phase2-tools/node_modules/jsdom');

const frontend = path.resolve(__dirname, '../frontend');
const pages = fs.readdirSync(frontend).filter(name => name.endsWith('.html'));
assert.equal(pages.length, 10, 'Ten XLipa pages must remain active');
let checks = 0;

for (const page of pages) {
  const errors = [];
  const virtualConsole = new VirtualConsole();
  virtualConsole.on('jsdomError', error => errors.push(error.message));
  const html = fs.readFileSync(path.join(frontend, page), 'utf8');
  assert.doesNotMatch(html, /bongopay|bongofey/i);
  assert.equal((html.match(/<script\b/g) || []).length, (html.match(/<\/script>/g) || []).length);

  // External resources are not enabled, so no third-party scripts/assets run.
  const dom = new JSDOM(html, { url: `http://xlipa.test/${page}`, runScripts: 'dangerously', virtualConsole });
  try {
    const { document, Event, KeyboardEvent } = dom.window;
    const toggle = document.querySelector('.burger');
    if (toggle) {
      const menu = document.querySelector('header nav');
      toggle.click();
      assert.equal(toggle.getAttribute('aria-expanded'), 'true', `${page}: menu opens`);
      assert.ok(menu.classList.contains('open'));
      document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
      assert.equal(toggle.getAttribute('aria-expanded'), 'false', `${page}: Escape closes menu`);
      assert.ok(!menu.classList.contains('open'));
      toggle.click();
      const link = menu.querySelector('a');
      link.addEventListener('click', event => event.preventDefault(), { once: true });
      link.click();
      assert.equal(toggle.getAttribute('aria-expanded'), 'false', `${page}: link closes menu`);
      checks += 3;
    }

    if (page === 'xlipasignin.html') {
      const password = document.getElementById('password');
      const reveal = document.querySelector('.reveal');
      reveal.click();
      assert.equal(password.type, 'text');
      assert.equal(reveal.getAttribute('aria-pressed'), 'true');
      reveal.click();
      assert.equal(password.type, 'password');
      const form = document.getElementById('signInForm');
      assert.equal(form.getAttribute('method'), 'post');
      assert.match(form.getAttribute('action'), /login.store/);
      assert.match(document.getElementById('forgotPassword').getAttribute('href'), /password.request/);
      checks += 3;
    }

    if (page === 'xlipacreateaccount.html') {
      const form = document.getElementById('createForm');
      assert.equal(form.getAttribute('method'), 'post');
      assert.match(form.getAttribute('action'), /register.store/);
      assert.equal(document.getElementById('password').minLength, 12);
      assert.equal(document.getElementById('confirmPassword').name, 'password_confirmation');
      assert.ok(form.querySelector('input[name="terms"][required]'));
      const submit = new Event('submit', { bubbles: true, cancelable: true });
      form.dispatchEvent(submit);
      assert.equal(submit.defaultPrevented, false, 'Registration submits to the server');
      checks += 2;
      const button = form.querySelector('button[type="submit"]');
      assert.equal(button.disabled, false, 'Invalid form does not lock submission');
      for (const [name, value] of Object.entries({ fullName: 'Synthetic Owner', businessName: 'Synthetic Business', email: 'owner@example.test', phone: '+255700000000', password: 'SyntheticPassword12', password_confirmation: 'SyntheticPassword12' })) {
        form.elements.namedItem(name).value = value;
      }
      form.elements.namedItem('terms').checked = true;
      const first = new Event('submit', { bubbles: true, cancelable: true });
      form.dispatchEvent(first);
      assert.equal(first.defaultPrevented, false, 'First valid submit remains native POST');
      assert.equal(button.disabled, true);
      assert.equal(form.getAttribute('aria-busy'), 'true');
      const duplicate = new Event('submit', { bubbles: true, cancelable: true });
      form.dispatchEvent(duplicate);
      assert.equal(duplicate.defaultPrevented, true, 'Duplicate submit is suppressed');
      dom.window.dispatchEvent(new Event('pageshow'));
      assert.equal(button.disabled, false, 'Back navigation restores submit');
      const retry = new Event('submit', { bubbles: true, cancelable: true });
      form.dispatchEvent(retry);
      assert.equal(retry.defaultPrevented, false, 'Restored page can submit again');
      checks += 7;
    }

    if (page === 'contact.html') {
      const form = document.getElementById('contactForm');
      // Invalid submission must remain local; valid mailto transmission is not tested.
      const submit = new Event('submit', { bubbles: true, cancelable: true });
      form.dispatchEvent(submit);
      assert.ok(submit.defaultPrevented);
      assert.equal(dom.window.location.href, 'http://xlipa.test/contact.html');
      checks++;
    }

    assert.deepEqual(errors, [], `${page}: no DOM script errors`);
  } finally {
    dom.window.close();
  }
}

console.log(JSON.stringify({ pages: pages.length, interactionChecks: checks, domScriptErrors: 0 }));
