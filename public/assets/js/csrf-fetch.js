// Attach the applicant CSRF token to mutating same-origin requests.
(function () {
    if (window.__nchireApplicantCsrfFetch) return;
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!token) return;
    const nativeFetch = window.fetch.bind(window);
    window.__nchireApplicantCsrfFetch = true;
    window.fetch = function (input, init) {
        init = init || {};
        const method = (init.method || (input && input.method) || 'GET').toUpperCase();
        if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
            const headers = new Headers(init.headers || (input instanceof Request ? input.headers : undefined));
            headers.set('X-CSRF-Token', token);
            init.headers = headers;
        }
        return nativeFetch(input, init);
    };
})();
