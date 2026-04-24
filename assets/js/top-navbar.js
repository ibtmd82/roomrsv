(() => {
    const logout = () => {
        localStorage.clear();
        window.location.href = "login.html";
    };

    const renderTopNavbar = () => {
        const topNavbarContent = document.getElementById("topNavbarContent");
        const topNavbar = document.getElementById("topNavbar");
        const target = topNavbarContent || topNavbar;
        if (!target) {
            return;
        }

        const accountId = localStorage.getItem("clientid") || "Tài khoản";
        target.innerHTML = `
            <ul class="nav navbar-nav align-items-center gap-1 me-md-1">
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
        `;

        const logoutButton = target.querySelector('[data-action="logout"]');
        if (logoutButton) {
            logoutButton.addEventListener("click", (event) => {
                event.preventDefault();
                logout();
            });
        }
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", renderTopNavbar);
    } else {
        renderTopNavbar();
    }
})();
