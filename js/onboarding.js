(function () {
    'use strict';
    const form = document.getElementById('loyf-quick-start');
    if (!form) return;
    const steps = Array.from(form.querySelectorAll('[data-loyf-step]'));
    const back = form.querySelector('[data-loyf-back]');
    const next = form.querySelector('[data-loyf-next]');
    const skip = form.querySelector('[data-loyf-skip]');
    const launch = form.querySelector('[data-loyf-launch]');
    const summary = form.querySelector('[data-loyf-summary]');
    let step = 0;
    function field(name) { return form.elements.namedItem(name); }
    function sample() {
        summary.replaceChildren();
        const currency = field('currency').value;
        const points = Number(field('earn_points').value);
        const amount = Number(field('earn_amount').value);
        const raw = 100 / amount * points;
        const earned = field('rounding').value === 'round_up' ? Math.ceil(raw) : Math.floor(raw);
        const paragraph = function (text) { const p = document.createElement('p'); p.textContent = text; summary.appendChild(p); };
        paragraph(Number.isFinite(earned) && earned > 0 && earned <= 99999999 && amount > 0 && Number.isInteger(points)
            ? loyfOnboarding.example.replace('%1$s', '100 ' + currency).replace('%2$s', String(earned))
            : loyfOnboarding.invalid);
        if (field('redeem').checked) {
            const p = Number(field('redeem_points').value);
            const a = Number(field('redeem_amount').value);
            paragraph(p > 0 && Number.isInteger(p) && a > 0 && Number.isFinite(a)
                ? loyfOnboarding.redeem.replace('%1$s', String(p)).replace('%2$s', String(a) + ' ' + currency)
                : loyfOnboarding.invalid);
        }
        paragraph(loyfOnboarding.off);
        // Show every merchant choice without HTML interpolation or a server write.
        const choices = document.createElement('dl');
        steps.slice(0, 5).forEach(function (section) {
            section.querySelectorAll('input:not([type="hidden"]),select').forEach(function (input) {
                const label = input.labels && input.labels[0];
                if (!label) return;
                const dt = document.createElement('dt'); dt.textContent = label.textContent.trim();
                const dd = document.createElement('dd'); dd.textContent = input.type === 'checkbox' ? (input.checked ? '✓' : '—') : input.value;
                choices.append(dt, dd);
            });
        });
        summary.appendChild(choices);
    }
    function show(focus) {
        steps.forEach(function (section, i) { section.hidden = i !== step; });
        back.hidden = step === 0;
        next.hidden = step === steps.length - 1;
        skip.hidden = step !== 3;
        launch.hidden = step !== steps.length - 1;
        if (step === steps.length - 1) sample();
        if (focus) steps[step].querySelector('h2').focus();
    }
    back.addEventListener('click', function () { step = Math.max(0, step - 1); show(true); });
    next.addEventListener('click', function () { step = Math.min(steps.length - 1, step + 1); show(true); });
    skip.addEventListener('click', function () { field('first').checked = false; field('referral').checked = false; step = 4; show(true); });
    form.addEventListener('submit', function (event) {
        if (event.submitter && event.submitter.value === 'dismiss') return;
        if (step !== steps.length - 1) { event.preventDefault(); step = steps.length - 1; show(true); }
    });
    show(false);
}());
