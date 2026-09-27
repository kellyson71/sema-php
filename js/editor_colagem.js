/**
 * editor_colagem.js — colagem de conteúdo do Word e inserção de imagens no editor.
 *
 * Ao colar do Word, o HTML da área de transferência traz as imagens como
 * <img src="file:///C:/Users/.../clip_image004.gif"> — um arquivo temporário no
 * computador de quem colou. O navegador não envia esse arquivo, então a imagem
 * sumia do documento (processo 932, 09/2026: 9 fotos/fluxogramas perdidos).
 * O Word também coloca na área de transferência a versão RTF, com as imagens de
 * verdade em hexadecimal ({\pict ...\pngblip ...}). Daqui elas são extraídas na
 * mesma ordem e trocadas no HTML. A que não der para recuperar vira um aviso
 * visível no editor (.img-colagem-pendente) — o PDF não leva esse aviso.
 *
 * Fotos grandes são reduzidas (lado maior até 1600 px, JPEG) antes de entrar
 * no documento, para o HTML não ficar com dezenas de MB.
 */
(function (global) {
    'use strict';

    const LADO_MAXIMO = 1600;
    const QUALIDADE_JPEG = 0.85;
    const LIMITE_SEM_REDUZIR = 300 * 1024; // imagens pequenas entram como vieram

    const CABECALHO_PICT = /{\\pict[\s\S]+?\\bliptag-?\d+(\\blipupi-?\d+)?({\\\*\\blipuid\s?[\da-fA-F]+)?[\s}]*?/;
    const PICT = new RegExp('(?:(' + CABECALHO_PICT.source + '))([\\da-fA-F\\s]+)\\}', 'g');

    /** Imagens PNG/JPEG do RTF do Word, na ordem do documento. */
    function imagensDoRtf(rtf) {
        const achadas = [];
        for (const grupo of (rtf || '').match(PICT) || []) {
            const tipo = grupo.includes('\\pngblip') ? 'image/png'
                : grupo.includes('\\jpegblip') ? 'image/jpeg' : null;
            if (!tipo) continue; // WMF/EMF: versão alternativa que o Word manda junto
            const hex = grupo.replace(CABECALHO_PICT, '').replace(/[^\da-fA-F]/g, '');
            if (hex.length < 2) continue;
            let bin = '';
            for (let i = 0; i + 1 < hex.length; i += 2) {
                bin += String.fromCharCode(parseInt(hex.substr(i, 2), 16));
            }
            achadas.push('data:' + tipo + ';base64,' + btoa(bin));
        }
        return achadas;
    }

    function ehImagemLocal(src) {
        return /^(file:|blob:)/i.test(src || '') || /^[a-z]:[\\/]/i.test(src || '');
    }

    function avisoImagemPerdida() {
        const aviso = document.createElement('p');
        aviso.className = 'img-colagem-pendente';
        aviso.setAttribute('contenteditable', 'false');
        aviso.style.cssText = 'border:1px dashed #b45309;color:#b45309;background:#fff7ed;padding:6px 10px;font-size:10pt;';
        aviso.textContent = '[Imagem do Word não veio na colagem — insira pelo botão Imagem e apague este aviso]';
        return aviso;
    }

    /** Reduz a imagem se for grande. Devolve sempre um data URI. */
    function reduzirImagem(dataUrl) {
        return new Promise(function (resolve) {
            const tamanho = Math.round((dataUrl.length - dataUrl.indexOf(',') - 1) * 3 / 4);
            if (tamanho <= LIMITE_SEM_REDUZIR && !/^data:image\/(bmp|tiff)/i.test(dataUrl)) {
                resolve(dataUrl);
                return;
            }
            const img = new Image();
            img.onload = function () {
                const escala = Math.min(1, LADO_MAXIMO / Math.max(img.naturalWidth, img.naturalHeight));
                const canvas = document.createElement('canvas');
                canvas.width = Math.max(1, Math.round(img.naturalWidth * escala));
                canvas.height = Math.max(1, Math.round(img.naturalHeight * escala));
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#ffffff'; // JPEG não tem transparência
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                const reduzida = canvas.toDataURL('image/jpeg', QUALIDADE_JPEG);
                resolve(reduzida.length < dataUrl.length ? reduzida : dataUrl);
            };
            img.onerror = function () { resolve(dataUrl); };
            img.src = dataUrl;
        });
    }

    function arquivoParaDataUrl(arquivo) {
        return new Promise(function (resolve, reject) {
            const leitor = new FileReader();
            leitor.onload = function () { resolve(String(leitor.result)); };
            leitor.onerror = reject;
            leitor.readAsDataURL(arquivo);
        });
    }

    /**
     * Troca as imagens locais do HTML colado pelas imagens do RTF.
     * Só troca quando a quantidade bate — trocar fora de ordem poria a foto
     * errada no lugar errado; nesse caso todas viram aviso.
     *
     * @return {Promise<{html: string, recuperadas: number, perdidas: number}>}
     */
    async function processarColagemWord(html, rtf) {
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const locais = Array.from(doc.querySelectorAll('img')).filter(function (img) {
            return ehImagemLocal(img.getAttribute('src'));
        });
        if (!locais.length) {
            return { html: html, recuperadas: 0, perdidas: 0 };
        }

        const doRtf = imagensDoRtf(rtf);
        const bate = doRtf.length === locais.length;
        let recuperadas = 0;
        for (let i = 0; i < locais.length; i++) {
            const img = locais[i];
            if (bate) {
                img.setAttribute('src', await reduzirImagem(doRtf[i]));
                img.removeAttribute('v:shapes');
                recuperadas++;
            } else {
                img.replaceWith(avisoImagemPerdida());
            }
        }
        return { html: doc.body.innerHTML, recuperadas: recuperadas, perdidas: locais.length - recuperadas };
    }

    /**
     * Liga no Summernote: colagem do Word com imagens e botão/colagem de imagem
     * com redução. Chamar no onInit.
     */
    function ligar($editor, opcoes) {
        opcoes = opcoes || {};
        const editavel = $editor.next('.note-editor').find('.note-editable')[0]
            || document.querySelector('.note-editable');
        if (!editavel || editavel.dataset.colagemLigada) return;
        editavel.dataset.colagemLigada = '1';

        // Fase de captura: roda antes do tratamento do próprio Summernote.
        editavel.addEventListener('paste', function (e) {
            const dados = e.clipboardData;
            const html = dados && dados.getData('text/html');
            if (!html || !/<img[^>]+src=["']?(file:|blob:|[a-z]:\\)/i.test(html)) return;

            e.preventDefault();
            e.stopImmediatePropagation();
            const rtf = dados.getData('text/rtf');
            $editor.summernote('saveRange');
            processarColagemWord(html, rtf).then(function (r) {
                $editor.summernote('restoreRange');
                $editor.summernote('pasteHTML', r.html);
                if (typeof opcoes.aoColar === 'function') opcoes.aoColar(r);
            });
        }, true);
    }

    /** onImageUpload do Summernote: insere as imagens escolhidas/coladas já reduzidas. */
    async function inserirArquivos($editor, arquivos) {
        for (const arquivo of Array.from(arquivos || [])) {
            if (!/^image\/(png|jpe?g|gif|webp|bmp)$/i.test(arquivo.type)) continue;
            const dataUrl = await reduzirImagem(await arquivoParaDataUrl(arquivo));
            $editor.summernote('insertImage', dataUrl, function ($img) {
                $img.css('width', '');
                $img.attr('style', 'max-width:100%;');
            });
        }
    }

    global.SemaColagem = {
        imagensDoRtf: imagensDoRtf,
        processarColagemWord: processarColagemWord,
        reduzirImagem: reduzirImagem,
        inserirArquivos: inserirArquivos,
        ligar: ligar,
    };
})(window);
