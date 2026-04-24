(() => {
    const menuItems = [
        { id: "dashboard", href: "dashboard.html", icon: "fa-chart-line", label: "Thống kê" },
        { id: "booking", href: "index.html", icon: "fa-calendar-check", label: "Đặt/Thuê phòng" },
        { id: "users", href: "users.html", icon: "fa-user-friends", label: "Người dùng" },
        { id: "settings", href: "settings.html", icon: "fa-cog", label: "Cài đặt" }
    ];

    const authItems = [
        { href: "login.html", icon: "fa-lock", label: "Đăng nhập" },
        { href: "signup.html", icon: "fa-user-plus", label: "Đăng ký" }
    ];

    const renderSidebar = () => {
        const sidebar = document.getElementById("sidebar");
        if (!sidebar) {
            return;
        }
        const current = sidebar.getAttribute("data-current-page") || "";
        const menuHtml = menuItems.map((item) => {
            const activeClass = item.id === current ? ' class="active"' : "";
            return `<li${activeClass}><a href="${item.href}"><i class="fas ${item.icon}"></i>${item.label}</a></li>`;
        }).join("");
        const authHtml = authItems.map((item) => `<li><a href="${item.href}"><i class="fas ${item.icon}"></i>${item.label}</a></li>`).join("");

        sidebar.innerHTML = `
            <div class="sidebar-header">
                <img src="assets/img/bootstraper-logo.png" alt="bootraper logo" class="app-logo">
            </div>
            <ul class="list-unstyled components text-secondary">
                ${menuHtml}
                <li>
                    <a href="#authmenu" data-bs-toggle="collapse" aria-expanded="false" class="dropdown-toggle no-caret-down"><i class="fas fa-user-shield"></i>Xác thực</a>
                    <ul class="collapse list-unstyled" id="authmenu">
                        ${authHtml}
                    </ul>
                </li>
            </ul>
        `;
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", renderSidebar);
    } else {
        renderSidebar();
    }
})();
