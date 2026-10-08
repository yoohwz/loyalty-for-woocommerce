(function () {
    'use strict';
    function run() {
        var shells = Array.from(document.querySelectorAll('[data-loyf-component]'));
        if (!shells.length || !window.loyfCustomerComponents) { return; }
        var config = window.loyfCustomerComponents, active = true;
        window.addEventListener('pagehide', function () { active = false; shells.forEach(function (shell) { shell.querySelector('.loyf-customer-content').textContent = config.failure; }); });
        function fail() { shells.forEach(function (shell) { shell.querySelector('.loyf-customer-content').textContent = config.failure; }); }
        fetch(config.url + '?action=rest-nonce', { credentials: 'same-origin', cache: 'no-store' }).then(function (response) {
            if (!response.ok) { throw new Error('authentication'); } return response.text();
        }).then(function (nonce) {
            if (!/^[a-z0-9]{10}$/.test(nonce)) { throw new Error('nonce'); }
            return fetch(config.url, { method: 'POST', credentials: 'same-origin', cache: 'no-store', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: (function () { var body = new URLSearchParams({ action: 'loyf_customer_components', nonce: nonce }); new Set(shells.map(function (shell) { return shell.getAttribute('data-loyf-component'); })).forEach(function (kind) { body.append('kinds[]', kind); }); return body.toString(); }()) });
        }).then(function (response) { return response.json(); }).then(function (result) {
            if (!active) { return; }
            if (!result.success) { if (result.data && typeof result.data.message === 'string') { shells.forEach(function (shell) { shell.querySelector('.loyf-customer-content').textContent = result.data.message; }); return; } throw new Error('reader'); }
            shells.forEach(function (shell, index) {
                var markup = result.data[shell.getAttribute('data-loyf-component')];
                if (typeof markup !== 'string') { throw new Error('component'); }
                // Only escaped markup from the current-user server presenter is accepted.
                shell.querySelector('.loyf-customer-content').innerHTML = markup;
                var copy = shell.querySelector('[data-loyf-referral-copy]'), input = shell.querySelector('input');
                if (copy && input) { var previous = input.id; input.id += '-' + index; copy.setAttribute('data-loyf-referral-copy', input.id); shell.querySelectorAll('label').forEach(function (label) { if (label.htmlFor === previous) { label.htmlFor = input.id; } }); }
            });
        }).catch(fail);
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', run); } else { run(); }
    // Browser back/forward cache may retain a prior authenticated viewer's DOM.
    window.addEventListener('pageshow', function (event) { if (event.persisted) { window.location.reload(); } });
}());
