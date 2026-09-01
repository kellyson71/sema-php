/**
 * Métricas do formulário público (PostHog).
 *
 * PRIVACIDADE — regra inegociável deste arquivo: nenhum evento daqui carrega
 * valor digitado pelo cidadão. Só saem nomes de campo, contagens, tempos,
 * extensões e tamanhos de arquivo. Nome, CPF, e-mail, endereço, descrição de
 * denúncia e nome de arquivo (que costuma conter o nome da pessoa) nunca são
 * enviados. Se for preciso acrescentar uma propriedade nova, ela tem que
 * passar por essa mesma régua.
 *
 * O snippet do PostHog (includes/posthog.php) já enfileira chamadas feitas
 * antes da biblioteca terminar de carregar, então basta chamar posthog.capture
 * sem esperar por nada.
 */
(function () {
  'use strict';

  const AMOSTRA_MAX_CAMPOS = 12; // teto por evento, pra não estourar a propriedade

  function podeCapturar() {
    return typeof window.posthog !== 'undefined' && typeof window.posthog.capture === 'function';
  }

  function agora() {
    return typeof performance !== 'undefined' && performance.now ? performance.now() : Date.now();
  }

  function segundos(desdeMs) {
    return Math.round(((agora() - desdeMs) / 1000) * 10) / 10;
  }

  const estado = {
    inicio: agora(),
    inicioEtapa: agora(),
    etapaAtual: 1,
    etapaMaxima: 1,
    tipoAlvara: '',
    servicoEscolhidoEm: null,
    enviado: false,
    // Por etapa: quantas vezes a validação barrou e quanto tempo foi gasto.
    falhasPorEtapa: { 1: 0, 2: 0, 3: 0 },
    tempoPorEtapa: { 1: 0, 2: 0, 3: 0 },
    documentosAnexados: 0,
    documentosRejeitados: 0,
  };

  function propsBase(extra) {
    return Object.assign({
      tipo_alvara: estado.tipoAlvara || 'nao_escolhido',
      etapa_atual: estado.etapaAtual,
      segundos_total: segundos(estado.inicio),
    }, extra || {});
  }

  function capturar(evento, props) {
    if (!podeCapturar()) return;
    try {
      window.posthog.capture(evento, propsBase(props));
    } catch (e) {
      /* métrica nunca pode quebrar o formulário */
    }
  }

  /** Fecha a contagem de tempo da etapa em que o cidadão estava. */
  function fecharEtapa(etapa) {
    const gasto = segundos(estado.inicioEtapa);
    estado.tempoPorEtapa[etapa] = Math.round((estado.tempoPorEtapa[etapa] + gasto) * 10) / 10;
    estado.inicioEtapa = agora();
    return gasto;
  }

  const api = {
    /** Página do formulário aberta. */
    iniciou(info) {
      capturar('form_iniciado', {
        rascunho_restaurado: !!(info && info.rascunhoRestaurado),
        voltou_de_erro: !!(info && info.voltouDeErro),
      });
    },

    /** Cidadão escolheu (ou trocou) o tipo de solicitação. */
    escolheuServico(tipo) {
      const anterior = estado.tipoAlvara;
      estado.tipoAlvara = tipo || '';
      if (!tipo || tipo === anterior) return;
      const primeiraEscolha = estado.servicoEscolhidoEm === null;
      if (primeiraEscolha) estado.servicoEscolhidoEm = agora();
      capturar('form_servico_selecionado', {
        primeira_escolha: primeiraEscolha,
        servico_anterior: anterior || null,
        segundos_ate_escolher: segundos(estado.inicio),
      });
    },

    /** Etapa validada com sucesso — o cidadão avançou. */
    avancouEtapa(de, para) {
      const gasto = fecharEtapa(de);
      estado.etapaAtual = para;
      estado.etapaMaxima = Math.max(estado.etapaMaxima, para);
      capturar('form_etapa_concluida', {
        etapa: de,
        proxima_etapa: para,
        segundos_na_etapa: gasto,
        segundos_acumulados_na_etapa: estado.tempoPorEtapa[de],
        falhas_de_validacao_na_etapa: estado.falhasPorEtapa[de] || 0,
      });
    },

    /** Voltou para uma etapa anterior (botão Voltar ou clique na trilha). */
    voltouEtapa(de, para) {
      fecharEtapa(de);
      estado.etapaAtual = para;
      capturar('form_etapa_voltou', { de_etapa: de, para_etapa: para });
    },

    /**
     * Validação barrou o avanço.
     * @param {number} etapa
     * @param {string[]} campos nomes dos campos inválidos — nunca os valores
     */
    validacaoFalhou(etapa, campos) {
      estado.falhasPorEtapa[etapa] = (estado.falhasPorEtapa[etapa] || 0) + 1;
      const lista = Array.isArray(campos) ? campos.filter(Boolean) : [];
      capturar('form_validacao_falhou', {
        etapa: etapa,
        campos: lista.slice(0, AMOSTRA_MAX_CAMPOS),
        total_campos_invalidos: lista.length,
        tentativa_numero: estado.falhasPorEtapa[etapa],
        segundos_na_etapa: segundos(estado.inicioEtapa),
      });
    },

    /** Documento aceito pela validação local. */
    documentoAnexado(info) {
      estado.documentosAnexados += 1;
      capturar('form_documento_anexado', {
        campo: (info && info.campo) || null,
        extensao: (info && info.extensao) || null,
        tamanho_kb: info && typeof info.tamanhoBytes === 'number'
          ? Math.round(info.tamanhoBytes / 1024)
          : null,
        total_anexados: estado.documentosAnexados,
      });
    },

    /** Documento recusado na validação local (tipo ou tamanho). */
    documentoRejeitado(info) {
      estado.documentosRejeitados += 1;
      capturar('form_documento_rejeitado', {
        campo: (info && info.campo) || null,
        extensao: (info && info.extensao) || null,
        tamanho_kb: info && typeof info.tamanhoBytes === 'number'
          ? Math.round(info.tamanhoBytes / 1024)
          : null,
        motivo: (info && info.motivo) || null,
        total_rejeitados: estado.documentosRejeitados,
      });
    },

    /** Envio barrado pela revalidação final. */
    envioBloqueado(etapa, campos) {
      const lista = Array.isArray(campos) ? campos.filter(Boolean) : [];
      capturar('form_envio_bloqueado', {
        etapa_que_barrou: etapa,
        campos: lista.slice(0, AMOSTRA_MAX_CAMPOS),
        total_campos_invalidos: lista.length,
      });
    },

    /** Formulário enviado de fato. */
    enviou() {
      if (estado.enviado) return;
      estado.enviado = true;
      fecharEtapa(estado.etapaAtual);
      const falhas = (estado.falhasPorEtapa[1] || 0) + (estado.falhasPorEtapa[2] || 0) + (estado.falhasPorEtapa[3] || 0);
      capturar('form_enviado', {
        segundos_etapa_1: estado.tempoPorEtapa[1],
        segundos_etapa_2: estado.tempoPorEtapa[2],
        segundos_etapa_3: estado.tempoPorEtapa[3],
        total_falhas_de_validacao: falhas,
        documentos_anexados: estado.documentosAnexados,
        documentos_rejeitados: estado.documentosRejeitados,
        segundos_ate_escolher_servico: estado.servicoEscolhidoEm !== null
          ? Math.round(((estado.servicoEscolhidoEm - estado.inicio) / 1000) * 10) / 10
          : null,
      });
    },

    /** Saiu da página sem enviar. Roda no pagehide para sobreviver ao unload. */
    abandonou() {
      if (estado.enviado) return;
      estado.enviado = true; // não duplica se pagehide disparar mais de uma vez
      capturar('form_abandonado', {
        etapa_alcancada: estado.etapaMaxima,
        segundos_etapa_1: estado.tempoPorEtapa[1],
        segundos_etapa_2: estado.tempoPorEtapa[2],
        segundos_etapa_3: estado.tempoPorEtapa[3],
        escolheu_servico: estado.servicoEscolhidoEm !== null,
        documentos_anexados: estado.documentosAnexados,
        total_falhas_de_validacao:
          (estado.falhasPorEtapa[1] || 0) + (estado.falhasPorEtapa[2] || 0) + (estado.falhasPorEtapa[3] || 0),
      });
    },
  };

  window.SEMA_FORM_METRICS = api;

  // Abandono: pagehide cobre fechar a aba, navegar pra fora e o back/forward
  // cache do Safari/iOS, onde o unload não dispara.
  window.addEventListener('pagehide', function () {
    api.abandonou();
  });
})();
