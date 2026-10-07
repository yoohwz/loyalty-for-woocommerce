// Execute shipped handlers against unknown replies, edited terms and late callbacks.
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
for (const file of ['users-points-modal.js', 'user-profile-points-modal.js']) {
  const bindings = new Map(), values = new Map(), stored = new Map(), requests = [];
  let sequence = 0, alerts = 0;
  const document = {}, window = {location: {origin: 'https://fixture.invalid'}};
  class Element {
    constructor(key) { this.key = key; }
    ready(fn) { fn(query); return this; }
    on(event, selector, fn) { if (typeof selector === 'function') { fn = selector; selector = this.key; } bindings.set(event + '|' + selector, fn); return this; }
    val(value) { if (value === undefined) return values.get(this.key) || ''; values.set(this.key, value); return this; }
    data(name, value) { if (value === undefined) return name === 'user-id' ? 7 : undefined; return this; }
    show() { return this; } hide() { return this; } text() { return this; } is() { return false; }
  }
  function query(selector) { if (selector instanceof Element) return selector; return new Element(selector === document ? 'document' : selector === window ? 'window' : selector); }
  query.ajax = options => {
    if (options.data.action === 'get_user_name') options.success({success: true, data: {username: 'Fixture'}});
    else requests.push(options);
  };
  const context = {
    jQuery: query, document, window, console, Uint8Array, JSON,
    ajax_object: {user_id: 7, security: 'actor-nonce', ajaxurl: '/admin-ajax.php'},
    crypto: {randomUUID: () => '00000000-0000-4000-8000-' + String(++sequence).padStart(12, '0')},
    sessionStorage: {getItem: key => stored.get(key) || null, setItem: (key, val) => stored.set(key, val), removeItem: key => stored.delete(key)},
    alert: () => { alerts++; }, location: {reload() {}}
  };
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../js', file), 'utf8'), context, {filename: file});
  const target = new Element('target');
  const click = selector => { const fn = bindings.get('click|' + selector); assert.equal(typeof fn, 'function', selector); fn.call(target, {preventDefault() {}}); };
  click(file.startsWith('users-') ? '.reward-points' : '#reward-points');
  values.set('#points-amount', '4.5'); values.set('#points-description', 'Original'); click('#submit-points');
  assert.equal(requests.length, 0); assert.equal(stored.size, 0, 'Invalid input must not reserve an operation');
  values.set('#points-amount', '4'); click('#submit-points');
  const first = requests.at(-1); first.error(); click('#submit-points'); const retry = requests.at(-1);
  assert.equal(first.data.operation_id, retry.data.operation_id, 'Unknown reply retains identity');
  const count = requests.length; values.set('#points-amount', '5'); click('#submit-points');
  assert.equal(requests.length, count, 'Edited terms cannot become another unknown operation');
  values.set('#points-amount', '4'); first.success({success: true, data: {message: 'Committed'}});
  values.set('#points-amount', '4'); values.set('#points-description', 'Original'); click('#submit-points');
  const next = requests.at(-1); assert.notEqual(next.data.operation_id, first.data.operation_id);
  retry.success({success: true, data: {message: 'Old reply'}});
  assert.equal(JSON.parse([...stored.values()][0]).id, next.data.operation_id, 'Late reply cannot clear a newer operation');
  assert.ok(alerts > 0);
}
console.log('Admin replay handlers PASS');
