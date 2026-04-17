        </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script>
            function formatarData(data) {
                return new Date(data).toLocaleString('pt-BR');
            }

            function mostrarAlerta(mensagem, tipo = 'success') {
                const alertaDiv = document.createElement('div');
                alertaDiv.classList.add('alert', `alert-${tipo}`, 'alert-dismissible', 'fade', 'show', 'position-fixed');
                alertaDiv.setAttribute('role', 'alert');
                alertaDiv.style.top = '96px';
                alertaDiv.style.right = '20px';
                alertaDiv.style.zIndex = '1100';
                alertaDiv.innerHTML = `
                    ${mensagem}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                `;
                document.body.appendChild(alertaDiv);

                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alertaDiv);
                    bsAlert.close();
                }, 5000);
            }

            document.addEventListener('DOMContentLoaded', function() {
                const body = document.body;
                const sidebarToggle = document.getElementById('sidebarToggle');
                const sidebar = document.getElementById('adminSidebar');
                const notificationSidebar = document.getElementById('notificationSidebar');
                const openNotificationBtn = document.getElementById('openNotificationSidebar');
                const closeNotificationBtn = document.getElementById('closeNotificationSidebar');
                const contentOverlay = document.getElementById('contentOverlay');
                const searchInput = document.getElementById('globalSearchInput');
                const searchResults = document.getElementById('globalSearchResults');
                const searchEmpty = document.getElementById('globalSearchEmpty');
                const searchItems = searchResults ? Array.from(searchResults.querySelectorAll('[data-search-item]')) : [];

                const desktopQuery = window.matchMedia('(min-width: 992px)');
                const collapsedState = localStorage.getItem('adminSidebarCollapsed');

                if (desktopQuery.matches && collapsedState === 'true') {
                    body.classList.add('sidebar-collapsed');
                }

                function closeNotifications() {
                    if (!notificationSidebar) return;
                    notificationSidebar.classList.remove('active');
                    if (!body.classList.contains('sidebar-open')) {
                        contentOverlay?.classList.remove('active');
                    }
                    body.style.overflow = '';
                }

                function openNotifications() {
                    if (!notificationSidebar) return;
                    notificationSidebar.classList.add('active');
                    contentOverlay?.classList.add('active');
                    body.style.overflow = 'hidden';
                }

                function closeMobileSidebar() {
                    body.classList.remove('sidebar-open');
                    if (!notificationSidebar?.classList.contains('active')) {
                        contentOverlay?.classList.remove('active');
                    }
                }

                function filterSearchResults() {
                    if (!searchResults || !searchInput) return;
                    const term = searchInput.value.trim().toLowerCase();
                    let visibleCount = 0;

                    searchItems.forEach((item, index) => {
                        const matches = !term || item.dataset.searchText.includes(term);
                        item.classList.toggle('d-none', !matches);
                        item.classList.toggle('is-highlighted', matches && visibleCount === 0);
                        if (matches) {
                            item.dataset.searchIndex = String(visibleCount);
                            visibleCount += 1;
                        }
                    });

                    if (searchEmpty) {
                        searchEmpty.classList.toggle('d-none', visibleCount > 0);
                    }

                    searchResults.classList.toggle('active', document.activeElement === searchInput || term.length > 0);
                }

                if (sidebarToggle) {
                    sidebarToggle.addEventListener('click', function() {
                        if (desktopQuery.matches) {
                            body.classList.toggle('sidebar-collapsed');
                            localStorage.setItem('adminSidebarCollapsed', body.classList.contains('sidebar-collapsed') ? 'true' : 'false');
                        } else {
                            body.classList.toggle('sidebar-open');
                            contentOverlay?.classList.toggle('active', body.classList.contains('sidebar-open'));
                        }
                    });
                }

                if (openNotificationBtn) {
                    openNotificationBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        if (notificationSidebar?.classList.contains('active')) closeNotifications();
                        else openNotifications();
                    });
                }

                if (closeNotificationBtn) {
                    closeNotificationBtn.addEventListener('click', closeNotifications);
                }

                if (contentOverlay) {
                    contentOverlay.addEventListener('click', function() {
                        closeNotifications();
                        closeMobileSidebar();
                    });
                }

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        closeNotifications();
                        closeMobileSidebar();
                        searchResults?.classList.remove('active');
                    }

                    if (e.key === '/' && searchInput && document.activeElement !== searchInput) {
                        const tagName = document.activeElement ? document.activeElement.tagName : '';
                        if (!['INPUT', 'TEXTAREA'].includes(tagName)) {
                            e.preventDefault();
                            searchInput.focus();
                            searchInput.select();
                        }
                    }
                });

                if (searchInput && searchResults) {
                    searchInput.addEventListener('focus', filterSearchResults);
                    searchInput.addEventListener('input', filterSearchResults);

                    document.addEventListener('click', function(e) {
                        if (!e.target.closest('.topbar-search')) {
                            searchResults.classList.remove('active');
                        }
                    });

                    searchInput.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') {
                            const firstVisible = searchItems.find(item => !item.classList.contains('d-none'));
                            if (firstVisible) {
                                window.location.href = firstVisible.getAttribute('href');
                            }
                        }
                    });
                }

                if (sidebar) {
                    sidebar.querySelectorAll('a').forEach(item => {
                        item.addEventListener('click', function() {
                            if (!desktopQuery.matches) {
                                closeMobileSidebar();
                            }
                        });
                    });
                }
            });
        </script>
    </body>
</html>
