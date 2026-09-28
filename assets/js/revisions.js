(() => {
  const token = document.querySelector('meta[name="csrf-token"]')?.content;
  if (window.jQuery && token) jQuery.ajaxSetup({headers: {'X-CSRF-Token': token}});
  const originalFetch = window.fetch;
  window.fetch = (resource, options = {}) => {
    const url = new URL(typeof resource === 'string' ? resource : resource.url, location.href);
    if (token && url.origin === location.origin) {
      options.headers = new Headers(options.headers || (resource instanceof Request ? resource.headers : undefined));
      options.headers.set('X-CSRF-Token', token);
    }
    return originalFetch(resource, options);
  };
  document.querySelectorAll('[data-search-table]').forEach(input => {
    input.addEventListener('input', () => {
      const term = input.value.toLowerCase();
      document.querySelectorAll(input.dataset.searchTable + ' tbody tr').forEach(row => { row.hidden = !row.textContent.toLowerCase().includes(term); });
    });
  });
})();
