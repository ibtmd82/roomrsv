(() => {
    const logout = () => {
        localStorage.clear();
        window.location.href = "login.html";
    };

    const hydrateTopNavbar = () => {
        document.querySelectorAll('span[data-key="sclientid"]').forEach((elm) => {
            elm.textContent = localStorage.getItem("clientid") || "Tài khoản";
        });

        document.querySelectorAll('[data-action="logout"]').forEach((logoutButton) => {
            logoutButton.addEventListener("click", (event) => {
                event.preventDefault();
                logout();
            });
        });
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", hydrateTopNavbar);
    } else {
        hydrateTopNavbar();
    }
})();
