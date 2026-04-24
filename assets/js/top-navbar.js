(() => {
    const logout = () => {
        localStorage.clear();
        window.location.href = "login.html";
    };

    const renderTopNavbar = () => {
        const topNavbar = document.getElementById("topNavbar");
        if (!topNavbar) {
            return;
        }
        const accountId = localStorage.getItem("clientid") || "Tài khoản";
        topNavbar.innerHTML = `
            <nav class="navbar navbar-expand-lg navbar-white bg-white">
                <div class="container-fluid px-2 px-md-3">
                    <button type="button" id="sidebarCollapse" class="btn btn-light">
                        <i class="fas fa-bars"></i><span></span>
                    </button>
                    <div class="top-navbar-content ms-auto d-flex justify-content-end align-items-center">
                        <ul class="nav navbar-nav align-items-center flex-row flex-nowrap gap-1 me-md-1 mb-0">
                            <li class="nav-item dropdown">
                                <div class="nav-dropdown">
                                    <a href="#" id="navQuickLinks" class="nav-item nav-link dropdown-toggle text-secondary" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-link"></i> <span>Liên kết nhanh</span> <i style="font-size: .8em;" class="fas fa-caret-down"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end nav-link-menu" aria-labelledby="navQuickLinks">
                                        <ul class="nav-list">
                                            <li><a href="#" class="dropdown-item"><i class="fas fa-list"></i> Nhật ký truy cập</a></li>
                                            <div class="dropdown-divider"></div>
                                            <li><a href="#" class="dropdown-item"><i class="fas fa-database"></i> Sao lưu</a></li>
                                            <div class="dropdown-divider"></div>
                                            <li><a href="#" class="dropdown-item"><i class="fas fa-cloud-download-alt"></i> Cập nhật</a></li>
                                            <div class="dropdown-divider"></div>
                                            <li><a href="#" class="dropdown-item"><i class="fas fa-user-shield"></i> Vai trò</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </li>
                            <li class="nav-item dropdown">
                                <div class="nav-dropdown">
                                    <a href="#" id="navUserMenu" class="nav-item nav-link dropdown-toggle text-secondary pe-0" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-user"></i> <span data-key="sclientid">${accountId}</span> <i style="font-size: .8em;" class="fas fa-caret-down"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end nav-link-menu" aria-labelledby="navUserMenu">
                                        <ul class="nav-list">
                                            <li><a href="users.html" class="dropdown-item"><i class="fas fa-address-card"></i> Hồ sơ</a></li>
                                            <li><a href="settings.html" class="dropdown-item"><i class="fas fa-cog"></i> Cài đặt</a></li>
                                            <div class="dropdown-divider"></div>
                                            <li><a href="#" class="dropdown-item" data-action="logout"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
        `;
        const logoutButton = topNavbar.querySelector('[data-action="logout"]');
        if (logoutButton) {
            logoutButton.addEventListener("click", (event) => {
                event.preventDefault();
                logout();
            });
        }

        const sidebarToggleButton = topNavbar.querySelector("#sidebarCollapse");
        if (sidebarToggleButton) {
            let lastTouchAt = 0;
            const toggleSidebar = (event) => {
                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                const sidebar = document.getElementById("sidebar");
                const body = document.getElementById("body");
                if (sidebar) {
                    sidebar.classList.toggle("active");
                }
                if (body) {
                    body.classList.toggle("active");
                }
            };
            sidebarToggleButton.addEventListener("touchend", (event) => {
                lastTouchAt = Date.now();
                toggleSidebar(event);
            }, { passive: false });
            sidebarToggleButton.addEventListener("click", (event) => {
                if (Date.now() - lastTouchAt < 500) {
                    event.preventDefault();
                    event.stopPropagation();
                    return;
                }
                toggleSidebar(event);
            });
        }
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", renderTopNavbar);
    } else {
        renderTopNavbar();
    }
})();
