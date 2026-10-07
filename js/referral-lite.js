document.addEventListener('click', async function (event) {
  const button = event.target.closest('[data-loyf-referral-copy]');
  if (!button) return;
  const input = document.getElementById(button.dataset.loyfReferralCopy);
  const status = button.parentElement.querySelector('[role="status"]');
  if (!input) return;
  try {
    if (navigator.clipboard && window.isSecureContext) await navigator.clipboard.writeText(input.value);
    else { input.focus(); input.select(); if (!document.execCommand('copy')) throw new Error('copy_failed'); }
    if (status) status.textContent = button.dataset.copied;
  } catch (error) { input.focus(); input.select(); if (status) status.textContent = button.dataset.failed; }
});
