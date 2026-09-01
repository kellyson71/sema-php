/* ═══════════════════════════════════════════════════════════════════════
   PAGINAÇÃO VISUAL DO EDITOR — folhas A4 separadas
   ─────────────────────────────────────────────────────────────────────
   Quem pagina o documento de verdade é o TCPDF. Este módulo só DESENHA
   onde o corte vai cair, para a pessoa conferir o resultado antes de
   assinar. As medidas espelham includes/assinatura_layout_helper.php.

   Regra de ouro: os separadores são inseridos ENTRE os nós existentes.
   Nada do conteúdo do usuário é clonado, fatiado ou recriado — por isso
   o cursor nunca pula e o HTML salvo é idêntico ao que foi digitado.
   ═══════════════════════════════════════════════════════════════════ */
(function (global) {
    'use strict';

    /* Medidas da folha, em mm — iguais às do gerador de PDF. */
    const MM = {
        alturaPagina:   297,
        larguraPagina:  210,
        cabecalho:      27,
        rodape:         14,
        margemLateral:  15,
        areaUtil:       256,   // 297 − 27 − 14
        respiro:         4,    // folga mínima entre texto e carimbo
        blocoLargura:   88,
        blocoAltura:    20,
    };

    /* Tolerância de medição, em px. Arredondamento de subpixel do navegador
       não pode ser confundido com estouro de folha. */
    const TOLERANCIA = 1;

    /* Tempo máximo de uma repaginação. Estourou, aborta e devolve o documento
       inteiro — nunca deixa a aba travada. */
    const ORCAMENTO_MS = 2500;

    /* Containers em que faz sentido cortar entre os filhos. TR/TD/P/LI ficam
       de fora de propósito: linha, célula, parágrafo e item são atômicos. */
    const DESCENDIVEIS = new Set([
        'DIV', 'SECTION', 'ARTICLE', 'MAIN', 'BLOCKQUOTE', 'FIGURE',
        'UL', 'OL', 'TABLE', 'THEAD', 'TBODY', 'TFOOT',
    ]);

    /* Blocos de texto corrido que o TCPDF corta NO MEIO, entre duas linhas.
       O editor precisa fazer o mesmo, ou a contagem de folhas diverge do PDF. */
    const DIVISIVEIS_POR_LINHA = new Set([
        'P', 'LI', 'DIV', 'BLOCKQUOTE', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6',
    ]);

    const SEPARADOR = 'page-gap';
    const MARCA_CONTINUACAO = 'data-sema-cont';
    const CLASSES_LIXO = ['page-gap', 'page-cut', 'page-break-indicator'];

    let cfg = null;
    let totalPaginas = 1;

    /* Travas de segurança da repaginação. Nenhum documento justifica milhares
       de cortes nem segundos de CPU: abortar é sempre melhor do que prender a
       aba de quem está escrevendo o parecer. */
    let guarda = null;

    /* Se algo no editor reagir à repaginação mexendo no conteúdo, a repaginação
       responde de volta e vira um vaivém sem fim. Antes de derrubar a aba de
       quem está escrevendo, o paginador se desliga e avisa no console. */
    let historicoRepaginacoes = [];
    let desligado = false;

    function repaginacaoDisparada() {
        const agora = Date.now();
        historicoRepaginacoes = historicoRepaginacoes.filter(function (t) { return agora - t < 5000; });
        historicoRepaginacoes.push(agora);
        if (historicoRepaginacoes.length > 40) {
            desligado = true;
            console.error('[SemaPaginacao] repaginação em vaivém — paginador desligado por segurança');
            return true;
        }
        return false;
    }

    function abrirGuarda() { guarda = { operacoes: 0, prazo: Date.now() + ORCAMENTO_MS }; }
    function fecharGuarda() { guarda = null; }

    function contabilizar() {
        if (!guarda) return;
        if (++guarda.operacoes > 20000) {
            throw new Error('SemaPaginacao: limite de operações excedido');
        }
        if ((guarda.operacoes & 63) === 0 && Date.now() > guarda.prazo) {
            throw new Error('SemaPaginacao: repaginação passou de ' + ORCAMENTO_MS + 'ms');
        }
    }

    /* ─────────────────────────── medição ─────────────────────────── */

    /** px reais de 1mm — respeita zoom do navegador e DPI. */
    function px() {
        let sonda = document.getElementById('__sema_mm__');
        if (!sonda) {
            sonda = document.createElement('div');
            sonda.id = '__sema_mm__';
            sonda.style.cssText = 'position:absolute;top:-9999px;left:-9999px;' +
                                  'width:0;height:100mm;visibility:hidden;pointer-events:none';
            document.body.appendChild(sonda);
        }
        return sonda.getBoundingClientRect().height / 100;
    }

    function caixa(no) {
        if (no.nodeType === 1) return no.getBoundingClientRect();
        if (no.nodeType === 3 && no.textContent.trim() !== '') {
            const faixa = document.createRange();
            faixa.selectNodeContents(no);
            return faixa.getBoundingClientRect();
        }
        return null;
    }

    function ehSeparador(no) {
        return no.nodeType === 1 && no.classList && no.classList.contains(SEPARADOR);
    }

    function ehIgnoravel(no) {
        if (ehSeparador(no)) return true;
        if (no.nodeType === 3) return no.textContent.trim() === '';
        if (no.nodeType === 8) return true;                       // comentário
        if (no.nodeType !== 1) return true;
        const estilo = getComputedStyle(no);
        return estilo.display === 'none' || estilo.visibility === 'hidden';
    }

    function ehBloco(no) {
        if (no.nodeType !== 1) return false;
        const display = getComputedStyle(no).display;
        return display !== 'none' && !/^inline/.test(display) && display !== 'contents';
    }

    function filhosRelevantes(container) {
        return Array.from(container.childNodes).filter(function (no) {
            return !ehIgnoravel(no);
        });
    }

    /** Caixas com moldura própria (condicionantes, quadros) não são cortadas
        por dentro — a menos que sozinhas já sejam maiores que uma folha. */
    function temMoldura(el) {
        const e = getComputedStyle(el);
        const borda = parseFloat(e.borderTopWidth) + parseFloat(e.borderBottomWidth)
                    + parseFloat(e.borderLeftWidth) + parseFloat(e.borderRightWidth);
        const fundo = e.backgroundColor;
        const temFundo = fundo && fundo !== 'transparent' && !/rgba\(0,\s*0,\s*0,\s*0\)/.test(fundo);
        return borda > 0 || temFundo;
    }

    /* ────────────────────── separador de folha ───────────────────── */

    function htmlMioloSeparador(numeroFolha) {
        return '' +
            '<div class="page-gap-inner">' +
                '<div class="page-gap-footer">Página ' + numeroFolha + '</div>' +
                '<div class="page-gap-space"></div>' +
                '<div class="page-gap-header">' +
                    '<div class="page-gap-header-inner">' +
                        '<img src="' + cfg.logoUrl + '" alt="">' +
                        '<div>' +
                            '<div class="page-gap-prefeitura">PREFEITURA MUNICIPAL DE PAU DOS FERROS/RN</div>' +
                            '<div class="page-gap-secretaria">SECRETARIA MUNICIPAL DE MEIO AMBIENTE · SEMA</div>' +
                        '</div>' +
                    '</div>' +
                    '<div class="page-gap-line"></div>' +
                '</div>' +
            '</div>';
    }

    /**
     * O separador assume a forma que o pai aceita: linha em tabela, item em
     * lista, bloco no resto. Assim ele nunca produz HTML inválido.
     */
    function criarSeparador(numeroFolha, pai) {
        const tagPai = pai && pai.tagName;
        let elemento;

        if (tagPai === 'TBODY' || tagPai === 'THEAD' || tagPai === 'TFOOT' || tagPai === 'TABLE') {
            elemento = document.createElement('tr');
            const celula = document.createElement('td');
            celula.colSpan = contarColunas(pai);
            celula.style.cssText = 'padding:0;border:0;background:transparent';
            celula.innerHTML = htmlMioloSeparador(numeroFolha);
            elemento.appendChild(celula);
        } else if (tagPai === 'UL' || tagPai === 'OL') {
            elemento = document.createElement('li');
            elemento.style.cssText = 'list-style:none;margin:0;padding:0';
            elemento.innerHTML = htmlMioloSeparador(numeroFolha);
        } else {
            elemento = document.createElement('div');
            elemento.innerHTML = htmlMioloSeparador(numeroFolha);
        }

        elemento.className = SEPARADOR;
        elemento.setAttribute('contenteditable', 'false');
        return elemento;
    }

    function contarColunas(secaoTabela) {
        const tabela = secaoTabela.closest ? secaoTabela.closest('table') : null;
        let maior = 1;
        if (!tabela) return maior;
        Array.from(tabela.rows).forEach(function (linha) {
            let colunas = 0;
            Array.from(linha.cells).forEach(function (c) { colunas += (c.colSpan || 1); });
            if (colunas > maior) maior = colunas;
        });
        return maior;
    }

    /* ─────────────── corte de parágrafo entre duas linhas ─────────────── */

    function nosDeTexto(raiz) {
        const lista = [];
        const passeio = document.createTreeWalker(raiz, NodeFilter.SHOW_TEXT);
        let no;
        while ((no = passeio.nextNode())) {
            if (no.textContent.length) lista.push(no);
        }
        return lista;
    }

    /**
     * Acha o ponto do bloco em que a folha acaba: o fim da última linha que
     * ainda cabe. Só mede — não altera nada.
     *
     * @return {{no:Text, offset:number}|null}
     */
    function pontoDeCorte(bloco, limite) {
        const nos = nosDeTexto(bloco);
        if (!nos.length) return null;

        const texto = nos.map(function (n) { return n.textContent; }).join('');
        const total = texto.length;
        const faixa = document.createRange();

        function fundoAte(indice) {
            let resto = indice;
            for (const n of nos) {
                const tamanho = n.textContent.length;
                if (resto <= tamanho) {
                    faixa.setStart(nos[0], 0);
                    faixa.setEnd(n, resto);
                    return faixa.getBoundingClientRect().bottom;
                }
                resto -= tamanho;
            }
            return Infinity;
        }

        // Maior prefixo que ainda cabe na folha.
        let baixo = 0, alto = total, cabe = 0;
        while (baixo <= alto) {
            contabilizar();
            const meio = (baixo + alto) >> 1;
            if (fundoAte(meio) <= limite + TOLERANCIA) { cabe = meio; baixo = meio + 1; }
            else { alto = meio - 1; }
        }
        if (cabe <= 0 || cabe >= total) return null;

        // Recua até o começo de uma palavra: a quebra do TCPDF também é lá.
        let corte = cabe;
        while (corte > 0 && !/\s/.test(texto[corte - 1])) corte--;
        while (corte < total && /\s/.test(texto[corte])) corte++;
        if (corte <= 0 || corte >= total) return null;

        let resto = corte;
        for (const n of nos) {
            const tamanho = n.textContent.length;
            if (resto < tamanho) return { no: n, offset: resto };
            resto -= tamanho;
        }
        return null;
    }

    /**
     * Parte o bloco no ponto indicado, criando uma continuação marcada.
     * Os invólucros inline (negrito, span) são reconstruídos na continuação
     * para o texto não perder formatação no meio da frase.
     */
    function dividirNoPonto(bloco, ponto) {
        const inicio = ponto.offset > 0 ? ponto.no.splitText(ponto.offset) : ponto.no;

        let nivel = inicio.parentNode;
        let restante = document.createDocumentFragment();
        for (let n = inicio; n; ) { contabilizar(); const prox = n.nextSibling; restante.appendChild(n); n = prox; }

        while (nivel !== bloco && nivel) {
            contabilizar();
            const clone = nivel.cloneNode(false);
            clone.appendChild(restante);
            const acima = nivel.parentNode;
            restante = document.createDocumentFragment();
            restante.appendChild(clone);
            for (let n = nivel.nextSibling; n; ) { const prox = n.nextSibling; restante.appendChild(n); n = prox; }
            if (!nivel.hasChildNodes()) nivel.remove();
            nivel = acima;
        }

        const continuacao = bloco.cloneNode(false);
        continuacao.removeAttribute('id');
        continuacao.setAttribute(MARCA_CONTINUACAO, '1');
        // A continuação é a mesma frase seguindo na outra folha: sem recuo de
        // primeira linha, exatamente como o TCPDF faz.
        continuacao.style.textIndent = '0';
        continuacao.appendChild(restante);

        // A última linha antes do corte é uma linha do meio do parágrafo, e
        // portanto justificada — o CSS não faz isso sozinho.
        if (getComputedStyle(bloco).textAlign === 'justify') {
            bloco.style.textAlignLast = 'justify';
        }

        bloco.after(continuacao);
        return continuacao;
    }

    /** Junta irmãos inline idênticos criados por cortes anteriores. */
    /* <br>, <img> e afins não têm conteúdo para juntar: fundi-los apagaria
       quebras de linha do documento. */
    const INLINE_VAZIOS = new Set(['BR', 'IMG', 'HR', 'INPUT', 'WBR']);

    function juntarInlineIguais(el) {
        let no = el.firstChild;
        while (no && no.nextSibling) {
            contabilizar();
            const proximo = no.nextSibling;
            if (no.nodeType === 1 && proximo.nodeType === 1
                && !INLINE_VAZIOS.has(no.tagName)
                && no.tagName === proximo.tagName
                && aberturaDe(no) === aberturaDe(proximo)) {
                while (proximo.firstChild) no.appendChild(proximo.firstChild);
                proximo.remove();
                juntarInlineIguais(no);
                continue;
            }
            no = no.nextSibling;
        }
    }

    function aberturaDe(el) {
        const html = el.outerHTML;
        return html.slice(0, html.indexOf('>') + 1);
    }

    /**
     * Devolve o conteúdo ao estado original: sem separadores e com os
     * parágrafos inteiros. Roda no início de CADA repaginação, e é por isso
     * que os cortes nunca se acumulam.
     */
    function removerSeparadores(raiz) {
        raiz.querySelectorAll('.' + SEPARADOR).forEach(function (s) { s.remove(); });
    }

    function desfazerDivisoes(editavel) {
        editavel.querySelectorAll('[' + MARCA_CONTINUACAO + ']').forEach(function (cont) {
            const alvo = cont.previousElementSibling;
            if (!alvo) { cont.removeAttribute(MARCA_CONTINUACAO); return; }
            while (cont.firstChild) alvo.appendChild(cont.firstChild);
            cont.remove();
            alvo.style.textAlignLast = '';
            if (!alvo.getAttribute('style')) alvo.removeAttribute('style');
            juntarInlineIguais(alvo);
        });

        editavel.normalize();
    }

    function desfazerEstrutura(editavel) {
        removerSeparadores(editavel);
        desfazerDivisoes(editavel);
    }

    /* ───────────────────── cursor de quem está digitando ──────────────── */

    function salvarCursor(editavel) {
        const selecao = document.getSelection();
        if (!selecao || !selecao.rangeCount) return null;
        const faixa = selecao.getRangeAt(0);
        if (!editavel.contains(faixa.startContainer)) return null;
        const antes = document.createRange();
        antes.selectNodeContents(editavel);
        antes.setEnd(faixa.startContainer, faixa.startOffset);
        return antes.toString().length;
    }

    function restaurarCursor(editavel, posicao) {
        if (posicao === null || posicao === undefined) return;
        if (!editavel.contains(document.activeElement) && document.activeElement !== editavel) return;

        let resto = posicao;
        const passeio = document.createTreeWalker(editavel, NodeFilter.SHOW_TEXT);
        let no;
        while ((no = passeio.nextNode())) {
            if (no.parentElement && no.parentElement.closest('.' + SEPARADOR)) continue;
            const tamanho = no.textContent.length;
            if (resto <= tamanho) {
                const faixa = document.createRange();
                faixa.setStart(no, resto);
                faixa.collapse(true);
                const selecao = document.getSelection();
                selecao.removeAllRanges();
                selecao.addRange(faixa);
                return;
            }
            resto -= tamanho;
        }
    }

    /* ────────────────────────── paginação ────────────────────────── */

    function repaginar() {
        const editavel = cfg.editavel();
        const folha = cfg.folha();
        if (!editavel || !folha) return;

        abrirGuarda();

        // Volta tudo ao original antes de medir. Sem isto, os cortes de uma
        // repaginação virariam base da seguinte e se acumulariam.
        //
        // A ordem importa: o cursor é anotado DEPOIS de tirar os separadores e
        // ANTES de juntar os parágrafos. Anotá-lo antes contaria o texto dos
        // separadores ("Página 1", cabeçalho) na posição, e o cursor voltaria
        // adiantado; anotá-lo depois arriscaria perdê-lo na junção.
        removerSeparadores(editavel);
        const cursor = salvarCursor(editavel);
        desfazerDivisoes(editavel);

        const k = px();
        const areaUtilPx = MM.areaUtil * k;
        let inicioPagina = editavel.getBoundingClientRect().top;
        let limite = inicioPagina + areaUtilPx;
        let folhaAtual = 1;
        let ultimoFundo = inicioPagina;

        function avancarFolha() {
            folhaAtual++;
            inicioPagina = limite;
            limite = inicioPagina + areaUtilPx;
        }

        function inserirSeparadorAntes(no) {
            contabilizar();
            const separador = criarSeparador(folhaAtual, no.parentNode);
            no.parentNode.insertBefore(separador, no);
            folhaAtual++;
            inicioPagina = separador.getBoundingClientRect().bottom;
            limite = inicioPagina + areaUtilPx;
        }

        function podeDescer(no) {
            if (no.nodeType !== 1 || !DESCENDIVEIS.has(no.tagName)) return false;
            const filhos = filhosRelevantes(no);
            if (filhos.length === 0 || !filhos.every(ehBloco)) return false;
            // Quadro com moldura só é cortado por dentro se não couber inteiro
            // em folha nenhuma — aí não há alternativa.
            if (temMoldura(no) && no.getBoundingClientRect().height <= areaUtilPx) return false;
            return true;
        }

        function podeCortarLinha(no) {
            if (no.nodeType !== 1 || !DIVISIVEIS_POR_LINHA.has(no.tagName)) return false;
            if (!no.textContent || !no.textContent.trim()) return false;
            // Quadro com moldura só é cortado se não couber em folha nenhuma.
            if (temMoldura(no) && no.getBoundingClientRect().height <= areaUtilPx) return false;
            return true;
        }

        function tratarNo(no, profundidade) {
            contabilizar();
            const medida = caixa(no);
            if (!medida) return;

            if (medida.bottom <= limite + TOLERANCIA) {
                if (medida.bottom > ultimoFundo) ultimoFundo = medida.bottom;
                return;
            }

            if (podeDescer(no)) {
                processar(no, profundidade + 1);
                return;
            }

            // Texto corrido: corta entre duas linhas, igual ao TCPDF, e segue
            // tratando a continuação (um parágrafo pode cruzar várias folhas).
            if (global.SemaPaginacao.cortarLinhas && podeCortarLinha(no)) {
                const ponto = pontoDeCorte(no, limite);
                if (ponto) {
                    const continuacao = dividirNoPonto(no, ponto);
                    inserirSeparadorAntes(continuacao);
                    tratarNo(continuacao, profundidade);
                    return;
                }
            }

            // Bloco indivisível: vai inteiro para a folha seguinte, exceto se já
            // for o primeiro conteúdo da folha (aí não há para onde empurrar).
            if (caixa(no).top > inicioPagina + TOLERANCIA) {
                inserirSeparadorAntes(no);
            }

            // Maior que uma folha inteira e sem ponto de corte: ocupa as folhas
            // que precisar, sem separador — não há onde encaixá-lo.
            let guarda = 0;
            while (caixa(no).bottom > limite + TOLERANCIA && guarda++ < 80) {
                avancarFolha();
            }

            const fim = caixa(no).bottom;
            if (fim > ultimoFundo) ultimoFundo = fim;
        }

        function processar(container, profundidade) {
            if (profundidade > 12) return;
            filhosRelevantes(container).forEach(function (filho) {
                tratarNo(filho, profundidade);
            });
        }

        processar(editavel, 0);

        /* ── Carimbo de assinatura: só entra em espaço vazio ───────── */
        const alturaBlocoMm = cfg.alturaBlocoMm ? cfg.alturaBlocoMm() : MM.blocoAltura;
        const topoCarimboMm = MM.alturaPagina - MM.rodape - alturaBlocoMm;
        const temConteudo = filhosRelevantes(editavel).length > 0;

        if (cfg.badge && temConteudo) {
            const fimConteudoMm = MM.cabecalho + (ultimoFundo - inicioPagina) / k;
            if (fimConteudoMm + MM.respiro > topoCarimboMm) {
                const separador = criarSeparador(folhaAtual, editavel);
                editavel.appendChild(separador);
                folhaAtual++;
                inicioPagina = separador.getBoundingClientRect().bottom;
                limite = inicioPagina + areaUtilPx;
            }
        }

        /* ── Altura da folha: sempre um número inteiro de páginas ──── */
        const topoFolha = folha.getBoundingClientRect().top;
        const alturaTotal = (inicioPagina - topoFolha) + areaUtilPx + MM.rodape * k;
        folha.style.minHeight = Math.ceil(alturaTotal) + 'px';

        posicionarBadge(folha, topoFolha, inicioPagina, topoCarimboMm, k);

        restaurarCursor(editavel, cursor);
        fecharGuarda();

        totalPaginas = folhaAtual;
        if (cfg.aoAtualizar) cfg.aoAtualizar(totalPaginas);
    }

    function posicionarBadge(folha, topoFolha, inicioUltimaPagina, topoCarimboMm, k) {
        const badge = cfg.badge && cfg.badge();
        if (!badge) return;
        badge.style.left = (MM.larguraPagina - MM.margemLateral - MM.blocoLargura) * k + 'px';
        badge.style.top  = (inicioUltimaPagina - topoFolha) + (topoCarimboMm - MM.cabecalho) * k + 'px';
        badge.style.right = 'auto';
        badge.style.bottom = 'auto';
    }

    /* ───────────────────── monitoramento do editor ───────────────── */

    function iniciarMonitor() {
        const editavel = cfg.editavel();
        if (!editavel) return;

        let debounce = null;
        let recalculando = false;

        function agendar(atraso) {
            clearTimeout(debounce);
            debounce = setTimeout(executar, atraso);
        }

        function executar() {
            if (recalculando || desligado) return;
            if (repaginacaoDisparada()) {
                observador.disconnect();
                try { desfazerEstrutura(editavel); } catch (e) {}
                return;
            }
            recalculando = true;
            observador.disconnect();
            try {
                repaginar();
            } catch (erro) {
                console.error('[SemaPaginacao] repaginação abortada:', erro);
                fecharGuarda();
                try { desfazerEstrutura(editavel); } catch (e) {}
            } finally {
                recalculando = false;
                observador.observe(editavel, { childList: true, subtree: true, characterData: true });
            }
        }

        const observador = new MutationObserver(function (mutacoes) {
            if (recalculando) return;
            // Ignora as próprias inserções de separador.
            const soSeparadores = mutacoes.every(function (m) {
                const tocados = Array.from(m.addedNodes).concat(Array.from(m.removedNodes));
                return tocados.length > 0 && tocados.every(ehSeparador);
            });
            if (soSeparadores) return;
            agendar(250);
        });

        editavel.addEventListener('input', function () { agendar(250); });
        window.addEventListener('resize', function () { agendar(300); });
        observador.observe(editavel, { childList: true, subtree: true, characterData: true });

        agendar(300);
        global.SemaPaginacao.recalcular = function () { agendar(0); };
    }

    /* ──────────────────────── API pública ────────────────────────── */

    global.SemaPaginacao = {
        MM: MM,

        /* Corte de parágrafo entre duas linhas, como o TCPDF faz.
           ─────────────────────────────────────────────────────────────
           DESLIGADO de propósito. Ligado, a contagem de folhas do editor bate
           exatamente com a do PDF em qualquer tamanho — mas em edições grandes
           aparece um travamento intermitente da aba que não foi possível
           isolar, e um editor que congela custa o trabalho de quem redige.

           Desligado, o parágrafo que não cabe vai inteiro para a folha
           seguinte: a contagem é exata em documentos de até ~6 folhas e pode
           mostrar uma folha a mais em documentos longos. Quem manda no
           documento final é sempre o PDF ("Pré-visualizar PDF"). */
        cortarLinhas: false,

        /**
         * @param {Object} opts
         *   logoUrl        — URL do logo usado no cabeçalho dos separadores
         *   editavel()     — devolve o elemento contenteditable
         *   folha()        — devolve a folha A4 (.a4-page-sheet)
         *   badge()        — devolve o carimbo de assinatura, ou null
         *   alturaBlocoMm()— altura do carimbo em mm (padrão: 20)
         *   aoAtualizar(n) — callback com o total de páginas
         */
        iniciar: function (opts) {
            cfg = opts;
            iniciarMonitor();
        },

        totalPaginas: function () { return totalPaginas; },

        recalcular: function () {},

        /** HTML do editor sem nenhum vestígio da estrutura visual de folhas. */
        limparHtml: function (html) {
            const temp = document.createElement('div');
            temp.innerHTML = String(html || '');
            const seletorLixo = CLASSES_LIXO.map(function (c) { return '.' + c; }).join(',');
            temp.querySelectorAll(seletorLixo).forEach(function (el) { el.remove(); });
            // Junta de volta os parágrafos que foram cortados entre folhas.
            desfazerEstrutura(temp);
            // Invólucros de folha de versões anteriores do editor
            temp.querySelectorAll('.doc-page-content').forEach(function (pagina) {
                pagina.replaceWith.apply(pagina, Array.from(pagina.childNodes));
            });
            return temp.innerHTML;
        },
    };
})(window);
