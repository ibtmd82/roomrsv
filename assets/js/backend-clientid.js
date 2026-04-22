(function () {
    "use strict";

    if (window.__clientIdHeaderInterceptorInstalled) {
        return;
    }
    window.__clientIdHeaderInterceptorInstalled = true;

    function getClientId() {
        return localStorage.getItem("clientid");
    }

    function isPhpRequest(url) {
        if (!url) {
            return false;
        }
        try {
            var resolved = new URL(url, window.location.href);
            return resolved.pathname.toLowerCase().endsWith(".php");
        } catch (e) {
            return false;
        }
    }

    if (window.fetch) {
        var originalFetch = window.fetch.bind(window);
        window.fetch = function (input, init) {
            var requestUrl = typeof input === "string" ? input : (input && input.url ? input.url : "");
            var clientId = getClientId();

            if (!isPhpRequest(requestUrl) || !clientId) {
                return originalFetch(input, init);
            }

            var finalInit = init ? Object.assign({}, init) : {};
            var headers = new Headers(finalInit.headers || (typeof input !== "string" && input ? input.headers : undefined));
            headers.set("clientid", clientId);
            finalInit.headers = headers;

            return originalFetch(input, finalInit);
        };
    }

    if (window.XMLHttpRequest) {
        var originalOpen = XMLHttpRequest.prototype.open;
        var originalSend = XMLHttpRequest.prototype.send;

        XMLHttpRequest.prototype.open = function (method, url) {
            this.__needsClientIdHeader = isPhpRequest(url);
            return originalOpen.apply(this, arguments);
        };

        XMLHttpRequest.prototype.send = function () {
            if (this.__needsClientIdHeader) {
                var clientId = getClientId();
                if (clientId) {
                    try {
                        this.setRequestHeader("clientid", clientId);
                    } catch (e) {
                        // Ignore if request headers can no longer be modified.
                    }
                }
            }
            return originalSend.apply(this, arguments);
        };
    }
})();
