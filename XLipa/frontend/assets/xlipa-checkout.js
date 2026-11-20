document.querySelectorAll('[data-payment-submit]').forEach((form) => {
  form.addEventListener('submit', () => {
    const button = form.querySelector('button[type="submit"]');
    const progress = form.querySelector('[data-payment-progress]');
    form.setAttribute('aria-busy', 'true');
    button.disabled = true;
    button.textContent = button.dataset.loadingLabel;
    progress.textContent = 'Request in progress. If the response is lost, check status before taking further action.';
  });
});
window.addEventListener('pageshow', () => {
  document.querySelectorAll('form[aria-busy="true"]').forEach((form) => {
    form.querySelector('[data-payment-progress]').textContent = 'The previous outcome is unresolved. Open the status or merchant history page before taking further action.';
  });
});
