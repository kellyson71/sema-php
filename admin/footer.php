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
                const notificationTabs = Array.from(document.querySelectorAll('[data-notification-tab]'));
                const notificationPanels = Array.from(document.querySelectorAll('[data-notification-panel]'));
                // ≥641px a barra é fixa (mini ou inteira); abaixo disso vira
                // gaveta sobre o conteúdo. Casa com o @media do header.php.
                const desktopQuery = window.matchMedia('(min-width: 641px)');
                // Faixa em que o redesenho manda a barra ficar mini de forma
                // automática, independente da preferência salva.
                const miniQuery = window.matchMedia('(min-width: 641px) and (max-width: 1024px)');

                function atualizarBotaoBarra() {
                    if (!sidebarToggle) return;
                    const icon = sidebarToggle.querySelector('i');
                    let iconClass;
                    let descricao;

                    if (desktopQuery.matches) {
                        const recolhida = body.classList.contains('sidebar-collapsed');
                        iconClass = recolhida ? 'fa-angles-right' : 'fa-angles-left';
                        descricao = recolhida ? 'Expandir barra lateral' : 'Recolher barra lateral';
                        sidebarToggle.setAttribute('aria-expanded', recolhida ? 'false' : 'true');
                    } else {
                        const aberta = body.classList.contains('sidebar-open');
                        iconClass = aberta ? 'fa-xmark' : 'fa-bars';
                        descricao = aberta ? 'Fechar menu de navegação' : 'Abrir menu de navegação';
                        sidebarToggle.setAttribute('aria-expanded', aberta ? 'true' : 'false');
                    }

                    if (icon) icon.className = 'fas ' + iconClass;
                    sidebarToggle.setAttribute('aria-label', descricao);
                    sidebarToggle.setAttribute('title', descricao);
                }

                function aplicarModoBarra() {
                    if (miniQuery.matches) {
                        // Faixa do mini: recolhida sempre, mas sem sobrescrever
                        // o que a pessoa escolheu para a tela grande.
                        body.classList.add('sidebar-collapsed');
                        atualizarBotaoBarra();
                        return;
                    }
                    if (desktopQuery.matches) {
                        body.classList.toggle(
                            'sidebar-collapsed',
                            localStorage.getItem('adminSidebarCollapsed') === 'true'
                        );
                    } else {
                        // Gaveta: nunca "recolhida" — ou está aberta, ou fora da tela.
                        body.classList.remove('sidebar-collapsed');
                    }
                    atualizarBotaoBarra();
                }

                aplicarModoBarra();
                miniQuery.addEventListener('change', aplicarModoBarra);
                desktopQuery.addEventListener('change', aplicarModoBarra);

                function closeNotifications() {
                    if (!notificationSidebar) return;
                    notificationSidebar.classList.remove('active');
                    openNotificationBtn?.setAttribute('aria-expanded', 'false');
                    if (!body.classList.contains('sidebar-open')) {
                        contentOverlay?.classList.remove('active');
                    }
                }

                function openNotifications() {
                    if (!notificationSidebar) return;
                    notificationSidebar.classList.add('active');
                    openNotificationBtn?.setAttribute('aria-expanded', 'true');
                    if (!desktopQuery.matches) {
                        contentOverlay?.classList.add('active');
                    }
                }

                function closeMobileSidebar() {
                    body.classList.remove('sidebar-open');
                    if (!notificationSidebar?.classList.contains('active')) {
                        contentOverlay?.classList.remove('active');
                    }
                    atualizarBotaoBarra();
                }

                const searchReqSection = document.getElementById('searchReqSection');
                const searchReqList    = document.getElementById('searchReqList');
                const searchReqHint    = document.getElementById('searchReqHint');
                const searchRtSection  = document.getElementById('searchRtSection');
                const searchRtList     = document.getElementById('searchRtList');
                const searchRtHint     = document.getElementById('searchRtHint');
                const searchAtalhosHead= document.getElementById('searchAtalhosHead');
                const searchLoading    = document.getElementById('globalSearchLoading');

                // Máximo de atalhos de tela mostrados junto com os requerimentos.
                // A busca é para achar processo; atalho é só uma conveniência.
                const MAX_ATALHOS = 3;
                let buscaTimer = null;
                let buscaAbort = null;
                let ultimoTermoReq = '';

                function escapaHtmlBusca(txt) {
                    const d = document.createElement('div');
                    d.appendChild(document.createTextNode(String(txt == null ? '' : txt)));
                    return d.innerHTML;
                }

                // A busca da barra trata "ação", "acao" e pequenas falhas de
                // digitação como a mesma intenção. A normalização acontece só
                // para comparar; o texto original continua sendo exibido.
                function normalizaBusca(txt) {
                    return String(txt || '')
                        .normalize('NFD')
                        .replace(/[\u0300-\u036f]/g, '')
                        .toLocaleLowerCase('pt-BR')
                        .replace(/[^a-z0-9@._-]+/g, ' ')
                        .replace(/\s+/g, ' ')
                        .trim();
                }

                function distanciaBusca(a, b) {
                    if (a === b) return 0;
                    if (!a.length) return b.length;
                    if (!b.length) return a.length;
                    let anterior = Array.from({ length: b.length + 1 }, (_, i) => i);
                    for (let i = 1; i <= a.length; i++) {
                        const atual = [i];
                        for (let j = 1; j <= b.length; j++) {
                            atual[j] = Math.min(
                                atual[j - 1] + 1,
                                anterior[j] + 1,
                                anterior[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1)
                            );
                        }
                        anterior = atual;
                    }
                    return anterior[b.length];
                }

                function combinaBusca(texto, termo) {
                    const alvo = normalizaBusca(termo);
                    const base = normalizaBusca(texto);
                    if (!alvo) return true;
                    if (base.includes(alvo)) return true;
                    return alvo.split(' ').filter(Boolean).every((palavra) => {
                        if (base.includes(palavra)) return true;
                        const limite = palavra.length >= 7 ? 2 : 1;
                        return base.split(/\s+/).some((item) => item.length >= 3 && distanciaBusca(palavra, item) <= limite);
                    });
                }

                // Realça o pedaço digitado dentro do resultado — é o que faz a
                // pessoa entender por que aquele item apareceu.
                function realca(texto, termo) {
                    const base = escapaHtmlBusca(texto);
                    if (!termo) return base;
                    const alvo = escapaHtmlBusca(termo).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                    return base.replace(new RegExp('(' + alvo + ')', 'ig'), '<mark>$1</mark>');
                }

                function renderRequerimentos(dados, termo) {
                    if (!searchReqList || !searchReqSection) return 0;
                    const itens = (dados && dados.resultados) || [];

                    if (itens.length === 0) {
                        searchReqSection.classList.add('d-none');
                        searchReqList.innerHTML = '';
                        return 0;
                    }

                    searchReqList.innerHTML = itens.map((r) => `
                        <a href="${r.url}" class="search-result-item search-req" data-search-item data-req>
                            <span class="search-result-icon"><i class="fas fa-file-lines"></i></span>
                            <span class="search-result-copy">
                                <span class="search-result-title">
                                    <span class="mono">${realca(r.protocolo, termo)}</span>
                                    <span class="search-req-nome">${realca(r.nome, termo)}</span>
                                </span>
                                <span class="search-result-caption">
                                    ${escapaHtmlBusca(r.status)}${r.tipo ? ' · ' + escapaHtmlBusca(r.tipo) : ''}${r.setor ? ' · ' + escapaHtmlBusca(r.setor) : ''}
                                </span>
                            </span>
                        </a>`).join('');

                    if (searchReqHint) {
                        searchReqHint.textContent = dados.tem_mais
                            ? 'mostrando os ' + itens.length + ' mais recentes — Enter vê todos'
                            : (itens.length === 1 ? '1 encontrado' : itens.length + ' encontrados');
                    }
                    searchReqSection.classList.remove('d-none');
                    return itens.length;
                }

                function renderResponsaveisTecnicos(dados, termo) {
                    if (!searchRtList || !searchRtSection) return 0;
                    const itens = (dados && dados.resultados_rt) || [];

                    if (itens.length === 0) {
                        searchRtSection.classList.add('d-none');
                        searchRtList.innerHTML = '';
                        return 0;
                    }

                    searchRtList.innerHTML = itens.map((rt) => `
                        <a href="${rt.url}" class="search-result-item search-req" data-search-item data-rt>
                            <span class="search-result-icon"><i class="fas fa-hard-hat"></i></span>
                            <span class="search-result-copy">
                                <span class="search-result-title">
                                    <span class="search-req-nome">${realca(rt.nome, termo)}</span>
                                </span>
                                <span class="search-result-caption">
                                    ${escapaHtmlBusca(rt.conselho)} ${escapaHtmlBusca(rt.registro)} · ${rt.total_obras} obra(s)
                                </span>
                            </span>
                        </a>`).join('');

                    if (searchRtHint) {
                        searchRtHint.textContent = dados.tem_mais_rt
                            ? 'mostrando os ' + itens.length + ' mais recentes — Enter vê todos'
                            : (itens.length === 1 ? '1 encontrado' : itens.length + ' encontrados');
                    }
                    searchRtSection.classList.remove('d-none');
                    return itens.length;
                }

                // Renumera tudo o que está visível (requerimentos + atalhos) para
                // as setas percorrerem a lista inteira, e não só uma das partes.
                function reindexarBusca() {
                    if (!searchResults) return 0;
                    const visiveis = Array.from(
                        searchResults.querySelectorAll('[data-search-item]')
                    ).filter((el) => !el.classList.contains('d-none'));

                    visiveis.forEach((el, i) => {
                        el.dataset.searchIndex = String(i);
                        el.classList.toggle('is-highlighted', i === 0);
                    });
                    return visiveis.length;
                }

                function filtrarAtalhos(termo) {
                    let mostrados = 0;
                    searchItems.forEach((item) => {
                        // Sem termo, a lista de atalhos funciona como menu completo.
                        // Com termo, ela cede o palco aos requerimentos.
                        const casa = !termo || combinaBusca(item.dataset.searchText, termo);
                        const cabe = !termo || mostrados < MAX_ATALHOS;
                        const exibir = casa && cabe;
                        item.classList.toggle('d-none', !exibir);
                        if (exibir) mostrados += 1;
                    });
                    if (searchAtalhosHead) {
                        searchAtalhosHead.classList.toggle('d-none', !termo || mostrados === 0);
                    }
                    return mostrados;
                }

                function buscarRequerimentos(termo) {
                    if (buscaAbort) buscaAbort.abort();
                    buscaAbort = new AbortController();
                    searchLoading?.classList.remove('d-none');

                    fetch('<?= $adminBase ?>ajax/busca_rapida.php?q=' + encodeURIComponent(termo),
                          { signal: buscaAbort.signal, headers: { 'X-Requested-With': 'fetch' } })
                        .then((r) => (r.ok ? r.json() : null))
                        .then((dados) => {
                            searchLoading?.classList.add('d-none');
                            // Resposta de uma digitação já superada: descarta.
                            if (!dados || dados.termo !== searchInput.value.trim()) return;
                            ultimoTermoReq = termo;
                            const qtdReq = renderRequerimentos(dados, termo);
                            const qtdRt = renderResponsaveisTecnicos(dados, termo);
                            const qtdAtalhos = filtrarAtalhos(termo.toLowerCase());
                            const total = reindexarBusca();
                            searchEmpty?.classList.toggle('d-none', total > 0);
                            searchResults.classList.add('active');
                        })
                        .catch((err) => {
                            if (err.name === 'AbortError') return;
                            searchLoading?.classList.add('d-none');
                        });
                }

                function filterSearchResults() {
                    if (!searchResults || !searchInput) return;
                    const termo = searchInput.value.trim();
                    const termoBaixo = termo.toLowerCase();

                    // 1 caractere não vale consulta: devolveria meio banco.
                    if (termo.length < 2) {
                        if (buscaAbort) buscaAbort.abort();
                        clearTimeout(buscaTimer);
                        searchLoading?.classList.add('d-none');
                        searchReqSection?.classList.add('d-none');
                        searchRtSection?.classList.add('d-none');
                        ultimoTermoReq = '';
                        filtrarAtalhos(termoBaixo);
                        const total = reindexarBusca();
                        searchEmpty?.classList.toggle('d-none', total > 0);
                        searchResults.classList.toggle(
                            'active',
                            document.activeElement === searchInput || termo.length > 0
                        );
                        return;
                    }

                    // Os atalhos respondem na hora (já estão na página); o preview
                    // dos requerimentos espera a digitação parar.
                    filtrarAtalhos(termoBaixo);
                    reindexarBusca();
                    searchResults.classList.add('active');

                    clearTimeout(buscaTimer);
                    buscaTimer = setTimeout(() => buscarRequerimentos(termo), 220);
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
                        atualizarBotaoBarra();
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

                document.addEventListener('click', function(e) {
                    if (!notificationSidebar || !openNotificationBtn) return;
                    if (e.target.closest('.notification-toggle')) return;
                    if (notificationSidebar.classList.contains('active')) {
                        closeNotifications();
                    }
                });

                if (notificationTabs.length > 0) {
                    notificationTabs.forEach((tabButton) => {
                        tabButton.addEventListener('click', function() {
                            const target = tabButton.dataset.notificationTab;
                            notificationTabs.forEach((button) => button.classList.toggle('active', button === tabButton));
                            notificationPanels.forEach((panel) => panel.classList.toggle('active', panel.dataset.notificationPanel === target));
                        });
                    });
                }

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        closeNotifications();
                        closeMobileSidebar();
                        searchResults?.classList.remove('active');
                        // Escape com a busca em foco devolve o foco à página,
                        // senão o cursor fica preso no campo depois de fechar.
                        if (document.activeElement === searchInput) searchInput.blur();
                    }

                    // Atalho da busca (o "⌘K" do cabeçalho). Aceita Ctrl+K,
                    // Cmd+K e a barra "/" sozinha. Ignorado enquanto a pessoa
                    // digita em outro campo — senão "/" viraria um sequestro
                    // de foco no meio de qualquer formulário do painel.
                    if (!searchInput) return;
                    const alvo = e.target;
                    const digitando = alvo && (
                        alvo.tagName === 'INPUT' ||
                        alvo.tagName === 'TEXTAREA' ||
                        alvo.tagName === 'SELECT' ||
                        alvo.isContentEditable
                    );
                    const atalhoK = (e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K');
                    const atalhoBarra = e.key === '/' && !digitando && !e.ctrlKey && !e.metaKey && !e.altKey;

                    if (atalhoK || atalhoBarra) {
                        e.preventDefault();
                        searchInput.focus();
                        searchInput.select();
                    }
                });

                // O rótulo da tecla muda por plataforma: ⌘K no Mac, Ctrl K no
                // resto. Fica em JS porque o PHP não sabe em que máquina a
                // página vai abrir.
                const searchHint = document.getElementById('globalSearchHint');
                if (searchHint) {
                    const ehMac = /Mac|iPhone|iPad/i.test(navigator.platform || navigator.userAgent || '');
                    searchHint.textContent = ehMac ? '\u2318K' : 'Ctrl K';
                }

                if (searchInput && searchResults) {
                    searchInput.addEventListener('focus', filterSearchResults);
                    searchInput.addEventListener('input', filterSearchResults);

                    document.addEventListener('click', function(e) {
                        if (!e.target.closest('.topbar-search')) {
                            searchResults.classList.remove('active');
                        }
                    });

                    searchInput.addEventListener('keydown', function(e) {
                        const visiveis = Array.from(
                            searchResults.querySelectorAll('[data-search-item]')
                        ).filter((el) => !el.classList.contains('d-none'));

                        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                            if (visiveis.length === 0) return;
                            e.preventDefault();
                            const atual = visiveis.findIndex((el) => el.classList.contains('is-highlighted'));
                            const passo = e.key === 'ArrowDown' ? 1 : -1;
                            // Circular: de baixo volta ao topo, e vice-versa.
                            const proximo = (atual + passo + visiveis.length) % visiveis.length;
                            visiveis.forEach((el, i) => el.classList.toggle('is-highlighted', i === proximo));
                            visiveis[proximo].scrollIntoView({ block: 'nearest' });
                            return;
                        }

                        if (e.key !== 'Enter') return;
                        e.preventDefault();

                        // Enter abre o item destacado — que por padrão é o primeiro
                        // resultado, o comportamento que se espera de um preview.
                        const destacado = visiveis.find((el) => el.classList.contains('is-highlighted'));
                        if (destacado) {
                            window.location.href = destacado.getAttribute('href');
                            return;
                        }

                        // Sem nada destacado, cai na listagem completa com o termo.
                        const q = searchInput.value.trim();
                        if (q.length > 0) {
                            window.location.href = '<?= $adminBase ?>requerimentos.php?busca=' + encodeURIComponent(q) + '&status=&tipo=';
                        }
                    });

                    // Passar o mouse move o destaque, pra não haver dois "selecionados".
                    searchResults.addEventListener('mousemove', function(e) {
                        const alvo = e.target.closest('[data-search-item]');
                        if (!alvo || alvo.classList.contains('is-highlighted')) return;
                        searchResults.querySelectorAll('[data-search-item].is-highlighted')
                            .forEach((el) => el.classList.remove('is-highlighted'));
                        alvo.classList.add('is-highlighted');
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
