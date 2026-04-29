(() => {
    const menuItems = [
        { id: "dashboard", href: "dashboard.html", icon: "fa-chart-pie", label: "Thống kê đặt phòng" },
        { id: "booking", href: "index.html", icon: "fa-calendar-check", label: "Đặt/Thuê phòng" },
        { id: "transport", href: "transport.html", icon: "fa-shuttle-van", label: "Đặt xe" },
        { id: "transport-dashboard", href: "transport_dashboard.html", icon: "fa-chart-bar", label: "Thống kê đặt xe" },
        { id: "users", href: "users.html", icon: "fa-user-friends", label: "Người dùng" },
        { id: "settings", href: "settings.html", icon: "fa-cog", label: "Cài đặt" }
    ];

    const authItems = [
        { href: "login.html", icon: "fa-lock", label: "Đăng nhập" },
        { href: "signup.html", icon: "fa-user-plus", label: "Đăng ký" }
    ];

    const STORAGE_KEY = "roomrsv_sidebar_module_settings";

    const normalizeSettings = (data) => ({
        roomModuleEnabled: data && data.roomModuleEnabled !== false,
        transportModuleEnabled: data && data.transportModuleEnabled !== false,
        transportDashboardEnabled: data && data.transportModuleEnabled !== false
    });

    const getCachedModuleSettings = () => {
        try {
            if (window.__sidebarModuleSettings) {
                return normalizeSettings(window.__sidebarModuleSettings);
            }
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return null;
            }
            return normalizeSettings(JSON.parse(raw));
        } catch (e) {
            return null;
        }
    };

    const getModuleSettings = async () => {
        if (window.__sidebarModuleSettings) {
            return normalizeSettings(window.__sidebarModuleSettings);
        }
        try {
            const res = await fetch("backend_settings_get.php");
            if (!res.ok) {
                return { roomModuleEnabled: true, transportModuleEnabled: true, transportDashboardEnabled: true };
            }
            const data = await res.json();
            window.__sidebarModuleSettings = data;
            localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
            return normalizeSettings(data);
        } catch (e) {
            return { roomModuleEnabled: true, transportModuleEnabled: true, transportDashboardEnabled: true };
        }
    };

    const renderSidebar = (moduleSettings) => {
        const sidebar = document.getElementById("sidebar");
        if (!sidebar) {
            return;
        }
        const settings = moduleSettings || { roomModuleEnabled: true, transportModuleEnabled: true, transportDashboardEnabled: true };
        const current = sidebar.getAttribute("data-current-page") || "";
        const filteredItems = menuItems.filter((item) => {
            if ((item.id === "dashboard" || item.id === "booking") && !settings.roomModuleEnabled) {
                return false;
            }
            if (item.id === "transport" && !settings.transportModuleEnabled) {
                return false;
            }
            if (item.id === "transport-dashboard" && !settings.transportModuleEnabled) {
                return false;
            }
            return true;
        });
        const menuHtml = filteredItems.map((item) => {
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

    const initSidebar = () => {
        // Render immediately (no network wait) to match booking page UX.
        renderSidebar(getCachedModuleSettings() || { roomModuleEnabled: true, transportModuleEnabled: true, transportDashboardEnabled: true });
        // Refresh in background and re-render only if settings differ.
        getModuleSettings().then((fresh) => {
            renderSidebar(fresh);
        });
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initSidebar);
    } else {
        initSidebar();
    }
})();
