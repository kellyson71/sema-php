        </div>
        </div>

        <!-- Scripts -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

        <script>
            // Toggle sidebar on mobile
            document.addEventListener('DOMContentLoaded', function() {
                const toggleBtn = document.createElement('button');
                toggleBtn.classList.add('btn', 'btn-sm', 'btn-light', 'd-md-none', 'position-fixed');
                toggleBtn.style.top = '10px';
                toggleBtn.style.left = '10px';
                toggleBtn.style.zIndex = '1050';
                toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
                document.body.appendChild(toggleBtn);

                toggleBtn.addEventListener('click', function() {
                    document.querySelector('.sidebar').classList.toggle('active');
                    document.querySelector('.content-wrapper').classList.toggle('active');
                    document.querySelector('.topbar').classList.toggle('active');
                });

                // Fechar menu ao clicar em um item no mobile
                const menuItems = document.querySelectorAll('.sidebar-menu a');
                menuItems.forEach(item => {
                    item.addEventListener('click', function() {
                        if (window.innerWidth < 768) {
                            document.querySelector('.sidebar').classList.remove('active');
                            document.querySelector('.content-wrapper').classList.remove('active');
                            document.querySelector('.topbar').classList.remove('active');
                        }
                    });
                });
            });

            // Formatação de data
            function formatarData(data) {
                return new Date(data).toLocaleString('pt-BR');
            }

            // Função para exibir alertas
            function mostrarAlerta(mensagem, tipo = 'success') {
                const alertaDiv = document.createElement('div');
                alertaDiv.classList.add('alert', `alert-${tipo}`, 'alert-dismissible', 'fade', 'show', 'position-fixed');
                alertaDiv.setAttribute('role', 'alert');
                alertaDiv.style.top = '70px';
                alertaDiv.style.right = '20px';
                alertaDiv.style.zIndex = '1100';
                alertaDiv.innerHTML = `
                ${mensagem}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            `;
                document.body.appendChild(alertaDiv);

                // Auto fechar após 5 segundos
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alertaDiv);
                    bsAlert.close();
                }, 5000);
            }

            // Controle da sidebar de notificações
            document.addEventListener('DOMContentLoaded', function() {
                const openNotificationBtn = document.getElementById('openNotificationSidebar');
                const closeNotificationBtn = document.getElementById('closeNotificationSidebar');
                const notificationSidebar = document.getElementById('notificationSidebar');
                const contentOverlay = document.getElementById('contentOverlay');

                // Abrir a sidebar de notificações
                if (openNotificationBtn) {
                    openNotificationBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        notificationSidebar.classList.add('active');
                        contentOverlay.classList.add('active');
                        document.body.style.overflow = 'hidden'; // Impedir rolagem do body
                    });
                }

                // Fechar a sidebar de notificações
                if (closeNotificationBtn) {
                    closeNotificationBtn.addEventListener('click', function() {
                        notificationSidebar.classList.remove('active');
                        contentOverlay.classList.remove('active');
                        document.body.style.overflow = ''; // Restaurar rolagem do body
                    });
                }

                // Fechar ao clicar no overlay
                if (contentOverlay) {
                    contentOverlay.addEventListener('click', function() {
                        notificationSidebar.classList.remove('active');
                        contentOverlay.classList.remove('active');
                        document.body.style.overflow = ''; // Restaurar rolagem do body
                    });
                }

                // Fechar ao pressionar ESC
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && notificationSidebar.classList.contains('active')) {
                        notificationSidebar.classList.remove('active');
                        contentOverlay.classList.remove('active');
                        document.body.style.overflow = ''; // Restaurar rolagem do body
                    }
                });
            });
        </script>
        </body>

        </html>