// Menu "mais opções" do cabeçalho (mobile) — esconde redes sociais e
// acessibilidade atrás de um botão, só aparece quando some a linha única.
(function () {
  var botao = document.getElementById('header-menu-toggle');
  var painel = document.getElementById('header-extras');
  if (!botao || !painel) return;

  function fechar() {
    painel.classList.remove('aberto');
    botao.setAttribute('aria-expanded', 'false');
  }

  botao.addEventListener('click', function (e) {
    e.stopPropagation();
    var aberto = painel.classList.toggle('aberto');
    botao.setAttribute('aria-expanded', aberto ? 'true' : 'false');
  });

  document.addEventListener('click', function (e) {
    if (!painel.contains(e.target) && e.target !== botao) fechar();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') fechar();
  });
})();

// Barra de acessibilidade — tamanho de fonte em passos e alto contraste,
// com preferência salva por 24h+ (localStorage) entre visitas.
(function () {
  var raiz = document.documentElement;
  var CHAVES = { fonte: "sema:fonte", contraste: "sema:contraste" };
  var PASSOS = [87.5, 100, 112.5, 125, 137.5];
  var PADRAO = 1;

  function ler(chave, alternativo) {
    try {
      return localStorage.getItem(chave) || alternativo;
    } catch (e) {
      return alternativo;
    }
  }

  function gravar(chave, valor) {
    try {
      localStorage.setItem(chave, valor);
    } catch (e) { /* modo privado: preferência vale só nesta página */ }
  }

  function aplicarFonte(indice) {
    raiz.style.fontSize = PASSOS[indice] + "%";
    gravar(CHAVES.fonte, String(indice));
  }

  function aplicarContraste(ligado) {
    if (ligado) {
      raiz.setAttribute("data-contraste", "alto");
    } else {
      raiz.removeAttribute("data-contraste");
    }
    gravar(CHAVES.contraste, ligado ? "alto" : "normal");
    document.querySelectorAll('[data-acao="contraste"]').forEach(function (botao) {
      botao.setAttribute("aria-pressed", ligado ? "true" : "false");
    });
  }

  // Restaura preferências salvas
  var indiceSalvo = parseInt(ler(CHAVES.fonte, String(PADRAO)), 10);
  if (isNaN(indiceSalvo) || !PASSOS[indiceSalvo]) indiceSalvo = PADRAO;
  aplicarFonte(indiceSalvo);
  aplicarContraste(ler(CHAVES.contraste, "normal") === "alto");

  document.addEventListener("click", function (evento) {
    var botao = evento.target.closest("[data-acao]");
    if (!botao) return;

    var acao = botao.getAttribute("data-acao");
    var indice = parseInt(ler(CHAVES.fonte, String(PADRAO)), 10);
    if (isNaN(indice)) indice = PADRAO;

    if (acao === "aumentar") {
      aplicarFonte(Math.min(indice + 1, PASSOS.length - 1));
    } else if (acao === "diminuir") {
      aplicarFonte(Math.max(indice - 1, 0));
    } else if (acao === "fonte-padrao") {
      aplicarFonte(PADRAO);
    } else if (acao === "contraste") {
      aplicarContraste(raiz.getAttribute("data-contraste") !== "alto");
    } else if (acao === "libras") {
      // O botão de verdade do VLibras fica escondido fora da tela — só
      // repassamos o clique pra ele abrir o widget oficial.
      var botaoVLibras = document.querySelector("[vw-access-button]");
      if (botaoVLibras) botaoVLibras.click();
    }
  });
})();

// Máscara para CPF/CNPJ (padrão de mercado — formatação posicional)
function mascara(input) {
  let v = input.value.replace(/\D/g, "").substring(0, 14);

  if (v.length <= 11) {
    // CPF: 000.000.000-00
    if (v.length > 9)      v = v.replace(/^(\d{3})(\d{3})(\d{3})(\d{1,2})$/, "$1.$2.$3-$4");
    else if (v.length > 6) v = v.replace(/^(\d{3})(\d{3})(\d{0,3})/, "$1.$2.$3");
    else if (v.length > 3) v = v.replace(/^(\d{3})(\d{0,3})/, "$1.$2");
  } else {
    // CNPJ: 00.000.000/0000-00
    if (v.length > 12)     v = v.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{1,2})$/, "$1.$2.$3/$4-$5");
    else if (v.length > 8) v = v.replace(/^(\d{2})(\d{3})(\d{3})(\d{0,4})/, "$1.$2.$3/$4");
    else if (v.length > 5) v = v.replace(/^(\d{2})(\d{3})(\d{0,3})/, "$1.$2.$3");
    else if (v.length > 2) v = v.replace(/^(\d{2})(\d{0,3})/, "$1.$2");
  }

  input.value = v;
}

// Máscara para telefone
function handlePhone(event) {
  let value = event.target.value.replace(/\D/g, "");

  if (value.length <= 10) {
    // Formato: (00) 0000-0000
    value = value.replace(/(\d{2})(\d)/, "($1) $2");
    value = value.replace(/(\d{4})(\d)/, "$1-$2");
  } else {
    // Formato: (00) 00000-0000
    value = value.replace(/(\d{2})(\d)/, "($1) $2");
    value = value.replace(/(\d{5})(\d)/, "$1-$2");
  }

  event.target.value = value;
}

// Combobox de tipo de solicitação (busca + categorias) no lugar do
// <select> nativo. O <select> continua no DOM (escondido) porque é ele
// quem o formulário envia de fato, e o resto do JS já escuta o "change"
// dele — a gente só precisa manter os dois sincronizados.
(function () {
  var select = document.getElementById('tipo_alvara');
  var input = document.getElementById('tipo_alvara_busca');
  var lista = document.getElementById('tipo_alvara_lista');
  var wrapper = document.getElementById('combobox-tipo');
  var atalhos = document.getElementById('categoria-atalhos');
  var linkMudar = document.getElementById('mudar-tipo-link');
  var btnMudar = document.getElementById('btn-mudar-tipo');
  if (!select || !input || !lista || !window.SEMA_TIPOS_ALVARA) return;

  var dados = window.SEMA_TIPOS_ALVARA;
  var itensAtivos = [];
  var indiceAtivo = -1;

  function normalizar(s) {
    return (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  }

  function renderLista(filtro, categoriaFiltro) {
    lista.innerHTML = '';
    itensAtivos = [];
    var termo = normalizar(filtro || '');

    dados.forEach(function (cat) {
      if (categoriaFiltro && cat.slug !== categoriaFiltro) return;
      var tiposFiltrados = cat.tipos.filter(function (t) {
        return !termo || normalizar(t.nome).indexOf(termo) !== -1;
      });
      if (!tiposFiltrados.length) return;

      var grupoLi = document.createElement('li');
      grupoLi.className = 'combobox-grupo';
      grupoLi.textContent = cat.nome;
      grupoLi.setAttribute('role', 'presentation');
      lista.appendChild(grupoLi);

      tiposFiltrados.forEach(function (t) {
        var li = document.createElement('li');
        li.setAttribute('role', 'option');
        li.className = 'combobox-opcao' + (t.desabilitado ? ' desabilitado' : '');
        li.textContent = t.nome;
        li.dataset.slug = t.slug;
        if (!t.desabilitado) {
          li.addEventListener('click', function () { selecionar(t.slug); });
          itensAtivos.push({ slug: t.slug, nome: t.nome, li: li });
        }
        lista.appendChild(li);
      });
    });

    if (!itensAtivos.length) {
      var vazio = document.createElement('li');
      vazio.className = 'combobox-vazio';
      vazio.textContent = 'Nenhum tipo encontrado com esse termo.';
      lista.appendChild(vazio);
    }
    indiceAtivo = -1;
  }

  function abrir(categoriaFiltro) {
    // Se já existe uma seleção, reabrir mostra a lista inteira (não filtrada
    // pelo próprio texto escolhido) e seleciona o texto, pra dar pra trocar
    // na hora — igual um <select> nativo, que sempre reabre do zero.
    var filtro = select.value ? '' : input.value;
    renderLista(filtro, categoriaFiltro);
    lista.hidden = false;
    input.setAttribute('aria-expanded', 'true');
    wrapper.classList.add('aberto');
    if (select.value) input.select();
    window.requestAnimationFrame(function () {
      wrapper.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    });
  }

  function fechar() {
    lista.hidden = true;
    input.setAttribute('aria-expanded', 'false');
    wrapper.classList.remove('aberto');
    indiceAtivo = -1;
  }

  function selecionar(slug) {
    select.value = slug;
    select.dispatchEvent(new Event('change'));
    fechar();
  }

  function destacar(indice) {
    itensAtivos.forEach(function (it) { it.li.classList.remove('ativo'); });
    indiceAtivo = indice;
    if (indice >= 0 && itensAtivos[indice]) {
      itensAtivos[indice].li.classList.add('ativo');
      itensAtivos[indice].li.scrollIntoView({ block: 'nearest' });
    }
  }

  input.addEventListener('focus', function () { abrir(); });
  // No celular, tocar de novo no campo já preenchido às vezes não dispara
  // "focus" (o navegador entende que ele já estava focado) — sem isso o
  // usuário fica sem conseguir trocar a seleção. O clique garante reabrir.
  input.addEventListener('click', function () { abrir(); });
  input.addEventListener('input', function () {
    // Digitar é sempre busca de verdade, mesmo que já exista uma seleção
    // (select.value só é limpo de fato ao escolher outro item ou usar
    // "mudar tipo de solicitação").
    renderLista(input.value, undefined);
    lista.hidden = false;
    input.setAttribute('aria-expanded', 'true');
    wrapper.classList.add('aberto');
  });

  input.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (lista.hidden) { abrir(); return; }
      destacar(Math.min(indiceAtivo + 1, itensAtivos.length - 1));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      destacar(Math.max(indiceAtivo - 1, 0));
    } else if (e.key === 'Enter') {
      if (!lista.hidden && indiceAtivo >= 0 && itensAtivos[indiceAtivo]) {
        e.preventDefault();
        selecionar(itensAtivos[indiceAtivo].slug);
      }
    } else if (e.key === 'Escape') {
      fechar();
    }
  });

  document.addEventListener('click', function (e) {
    if (!wrapper.contains(e.target) && !(atalhos && atalhos.contains(e.target))) fechar();
  });

  if (atalhos) {
    atalhos.querySelectorAll('.categoria-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        input.value = '';
        input.focus();
        abrir(btn.dataset.categoria);
      });
    });
  }

  if (btnMudar) {
    btnMudar.addEventListener('click', function (e) {
      e.preventDefault();
      select.value = '';
      select.dispatchEvent(new Event('change'));
      input.value = '';
      input.focus();
    });
  }

  // Mantém o input de busca e os atalhos de categoria sincronizados com o
  // valor real do select — inclusive quando ele muda por fora (restauração
  // de rascunho, etc.), já que tudo passa pelo evento "change".
  select.addEventListener('change', function () {
    if (select.value) {
      var achado = null;
      dados.forEach(function (cat) {
        cat.tipos.forEach(function (t) {
          if (t.slug === select.value) achado = t;
        });
      });
      input.value = achado ? achado.nome : '';
      if (atalhos) atalhos.style.display = 'none';
      if (linkMudar) linkMudar.style.display = 'block';
    } else {
      input.value = '';
      if (atalhos) atalhos.style.display = 'flex';
      if (linkMudar) linkMudar.style.display = 'none';
    }
  });
})();

// Mostra o nome do arquivo escolhido em cada campo de upload (documentos
// necessários e evidências de denúncia). Delegado no container, porque
// esses inputs são recriados via AJAX toda vez que o tipo muda.
(function () {
  document.addEventListener('change', function (evento) {
    var input = evento.target;
    if (input.type !== 'file') return;
    var container = input.closest('.file-input-container');
    if (!container) return;

    var status = container.querySelector('.file-input-status .nome');
    var arquivos = Array.from(input.files || []);

    if (!arquivos.length) {
      container.classList.remove('tem-arquivo');
      if (status) status.textContent = '';
      return;
    }

    container.classList.add('tem-arquivo');
    if (status) {
      status.textContent = arquivos.length === 1
        ? arquivos[0].name
        : arquivos.length + ' arquivos selecionados';
    }
  });
})();
