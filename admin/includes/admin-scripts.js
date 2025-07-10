// Variáveis globais
let modoSelecaoMultipla = false;
let currentContextId = null;

// Inicialização do DOM
document.addEventListener("DOMContentLoaded", function () {
  initializeAlerts();
  initializeDataTable();
  initializeAutoMessages();
  initializeClickHandlers();
});

// Inicializar alertas
function initializeAlerts() {
  const alertaFechado = localStorage.getItem("alertaStatusFinalizado");
  const alerta = document.getElementById("alertaInformativo");

  if (!alertaFechado && alerta) {
    alerta.style.display = "block";
  }
}

// Inicializar DataTable
function initializeDataTable() {
  if (document.getElementById("requerimentosTable")) {
    new simpleDatatables.DataTable("#requerimentosTable", {
      searchable: true,
      sortable: true,
      perPage: 25,
      perPageSelect: [10, 25, 50, 100],
      labels: {
        placeholder: "Pesquisar requerimentos...",
        perPage: "registros por página",
        noRows: "Nenhum requerimento encontrado",
        info: "Mostrando {start} a {end} de {rows} requerimentos",
        noResults: "Nenhum resultado encontrado para sua pesquisa",
      },
    });
  }
}

// Auto-dismiss de mensagens
function initializeAutoMessages() {
  const successMessages = document.querySelectorAll(".success-message");
  successMessages.forEach((message) => {
    setTimeout(() => {
      message.style.opacity = "0";
      message.style.transform = "translateY(-10px)";
      setTimeout(() => message.remove(), 300);
    }, 5000);
  });
}

// Handlers de clique
function initializeClickHandlers() {
  document.addEventListener("click", function (e) {
    if (!e.target.closest("#contextMenu")) {
      hideContextMenu();
    }

    // Fechar dropdown de status se clicar fora
    if (
      !e.target.closest("[onclick*='toggleDropdownStatus']") &&
      !e.target.closest("#dropdownStatus")
    ) {
      hideAllDropdowns();
    }
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      hideContextMenu();
      hideAllDropdowns();
    }
  });
}

// Função para fechar alertas
function fecharAlerta() {
  const alerta = document.getElementById("alertaInformativo");
  if (alerta) {
    alerta.style.opacity = "0";
    alerta.style.transform = "translateY(-10px)";
    setTimeout(() => {
      alerta.style.display = "none";
    }, 300);
    localStorage.setItem("alertaStatusFinalizado", "true");
  }
}

// Context Menu Functions
function showContextMenu(event, id) {
  event.preventDefault();
  currentContextId = id;

  const contextMenu = document.getElementById("contextMenu");
  const x = event.clientX;
  const y = event.clientY;

  contextMenu.style.left = x + "px";
  contextMenu.style.top = y + "px";
  contextMenu.classList.add("show");

  // Ajustar posição se sair da tela
  setTimeout(() => {
    const rect = contextMenu.getBoundingClientRect();
    if (rect.right > window.innerWidth) {
      contextMenu.style.left = x - rect.width + "px";
    }
    if (rect.bottom > window.innerHeight) {
      contextMenu.style.top = y - rect.height + "px";
    }
  }, 10);
}

function hideContextMenu() {
  const contextMenu = document.getElementById("contextMenu");
  contextMenu.classList.remove("show");
  currentContextId = null;
}

// Context Menu Actions
function abrirRequerimentoContext() {
  if (currentContextId) {
    abrirRequerimento(currentContextId);
  }
  hideContextMenu();
}

function alterarStatusContext(status) {
  if (currentContextId) {
    alterarStatusUnico(currentContextId, status);
  }
  hideContextMenu();
}

function marcarComoLidoContext() {
  if (currentContextId) {
    marcarComoLidoUnico(currentContextId);
  }
  hideContextMenu();
}

function confirmarExclusaoContext() {
  if (currentContextId) {
    confirmarExclusaoUnica(currentContextId);
  }
  hideContextMenu();
}

// Seleção Múltipla
function ativarModoSelecao() {
  modoSelecaoMultipla = true;

  showAlert("alertaModoSelecao");

  const tabela = document.querySelector("table tbody");
  if (tabela) {
    tabela.classList.add("modo-selecao-multipla");
  }

  document.querySelectorAll(".checkbox-selecao").forEach((checkbox) => {
    checkbox.style.display = "inline-block";
  });

  const acoesMultiplas = document.getElementById("acoesMultiplas");
  acoesMultiplas.style.display = "block";
  acoesMultiplas.classList.add("acoes-multiplas-fixed");

  document.body.classList.add("body-with-fixed-bar");

  hideContextMenu();
  updateContadorSelecionados();
}

function cancelarSelecaoMultipla() {
  modoSelecaoMultipla = false;

  hideAlert("alertaModoSelecao");

  const tabela = document.querySelector("table tbody");
  if (tabela) {
    tabela.classList.remove("modo-selecao-multipla");
  }

  document.querySelectorAll(".checkbox-selecao").forEach((checkbox) => {
    checkbox.style.display = "none";
    checkbox.checked = false;
  });

  const acoesMultiplas = document.getElementById("acoesMultiplas");
  acoesMultiplas.style.display = "none";
  acoesMultiplas.classList.remove("acoes-multiplas-fixed");

  document.body.classList.remove("body-with-fixed-bar");

  updateContadorSelecionados();
}

function updateContadorSelecionados() {
  const checkboxes = document.querySelectorAll(".checkbox-selecao:checked");
  const count = checkboxes.length;
  const contador = document.getElementById("contadorSelecionados");

  contador.textContent = `${count} ${
    count === 1 ? "item selecionado" : "itens selecionados"
  }`;
}

function toggleCheckboxById(id) {
  const checkbox = document.querySelector(`input[data-id="${id}"]`);
  if (checkbox) {
    checkbox.checked = !checkbox.checked;
    updateContadorSelecionados();
  }
}

// Navegação
function abrirRequerimento(id) {
  if (modoSelecaoMultipla) {
    toggleCheckboxById(id);
    return;
  }
  window.location.href = `visualizar_requerimento.php?id=${id}`;
}

// Ações unitárias
function alterarStatusUnico(id, status) {
  if (confirm(`Alterar status para "${status}"?`)) {
    executarAcaoEmMassa("alterar_status", [id], { status: status });
  }
}

function marcarComoLidoUnico(id) {
  executarAcaoEmMassa("marcar_lido", [id]);
}

function confirmarExclusaoUnica(id) {
  if (
    confirm(
      "Tem certeza que deseja excluir este requerimento? Esta ação não pode ser desfeita."
    )
  ) {
    executarAcaoEmMassa("excluir", [id]);
  }
}

// Ações múltiplas
function alterarStatusMultiplo(status) {
  const selecionados = getSelecionadosMultiplos();
  if (selecionados.length === 0) {
    alert("Selecione pelo menos um requerimento.");
    return;
  }

  if (
    confirm(
      `Alterar status de ${selecionados.length} requerimento(s) para "${status}"?`
    )
  ) {
    executarAcaoEmMassa("alterar_status", selecionados, { status: status });
  }
}

function confirmarExclusaoMultipla() {
  const selecionados = getSelecionadosMultiplos();
  if (selecionados.length === 0) {
    alert("Selecione pelo menos um requerimento.");
    return;
  }

  if (
    confirm(
      `Tem certeza que deseja excluir ${selecionados.length} requerimento(s)? Esta ação não pode ser desfeita.`
    )
  ) {
    executarAcaoEmMassa("excluir", selecionados);
  }
}

function getSelecionadosMultiplos() {
  const checkboxes = document.querySelectorAll(".checkbox-selecao:checked");
  return Array.from(checkboxes).map((cb) => cb.dataset.id);
}

// Executar ações em massa
function executarAcaoEmMassa(acao, ids, dados = {}) {
  const form = document.createElement("form");
  form.method = "POST";
  form.action = "acoes_massa.php";

  // Adicionar ação
  const acaoInput = document.createElement("input");
  acaoInput.type = "hidden";
  acaoInput.name = "acao";
  acaoInput.value = acao;
  form.appendChild(acaoInput);

  // Adicionar IDs
  ids.forEach((id) => {
    const idInput = document.createElement("input");
    idInput.type = "hidden";
    idInput.name = "ids[]";
    idInput.value = id;
    form.appendChild(idInput);
  });

  // Adicionar dados extras
  Object.keys(dados).forEach((key) => {
    const dataInput = document.createElement("input");
    dataInput.type = "hidden";
    dataInput.name = key;
    dataInput.value = dados[key];
    form.appendChild(dataInput);
  });

  document.body.appendChild(form);
  form.submit();
}

// Controle do dropdown de status
function toggleDropdownStatus() {
  const dropdown = document.getElementById("dropdownStatus");
  const isVisible = dropdown.style.display !== "none";

  // Fechar outros dropdowns se houver
  hideAllDropdowns();

  if (!isVisible) {
    dropdown.style.display = "block";
  }
}

function hideAllDropdowns() {
  const dropdowns = document.querySelectorAll('[id*="dropdown"]');
  dropdowns.forEach((dropdown) => {
    dropdown.style.display = "none";
  });
}

// Utilidades de alertas
function showAlert(alertId) {
  const alert = document.getElementById(alertId);
  if (alert) {
    alert.style.display = "block";
  }
}

function hideAlert(alertId) {
  const alert = document.getElementById(alertId);
  if (alert) {
    alert.style.display = "none";
  }
}
