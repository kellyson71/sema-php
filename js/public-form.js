(function () {
  const DRAFT_KEY = 'sema:public_form_draft:v1';
  const COOKIE_KEY = 'sema:cookie_notice';

  function cfg() {
    return window.SEMA_FORM_CONFIG || {};
  }

  function byName(name, root = document) {
    return root.querySelector(`[name="${CSS.escape(name)}"]`);
  }

  function fieldValue(data, name) {
    if (!data || typeof data !== 'object') return '';
    if (Object.prototype.hasOwnProperty.call(data, name)) return data[name] || '';
    return name.split(/\[|\]/).filter(Boolean).reduce((acc, key) => {
      return acc && typeof acc === 'object' ? acc[key] : '';
    }, data) || '';
  }

  function normalizar(valor) {
    return String(valor || '').trim().replace(/\s+/g, ' ').toUpperCase();
  }

  function setRequiredIn(container, enabled) {
    if (!container) return;
    container.querySelectorAll('input, select, textarea').forEach((field) => {
      if (field.type === 'hidden' || field.id === 'tipo_alvara') return;
      if (field.hasAttribute('required')) field.dataset.publicWasRequired = 'true';
      if (field.dataset.publicWasRequired === 'true' || field.dataset.required === 'true') {
        field.required = !!enabled;
      }
    });
  }

  function setupEmailConfirmation(form) {
    const email = form.querySelector('input[name="requerente[email]"]');
    const confirmation = form.querySelector('input[name="requerente[email_confirmacao]"]');
    if (!email || !confirmation) return;

    const validate = () => {
      const original = email.value.trim().toLowerCase();
      const repeated = confirmation.value.trim().toLowerCase();
      confirmation.setCustomValidity(repeated && original !== repeated ? 'Os e-mails informados não coincidem.' : '');
    };

    email.addEventListener('input', validate);
    confirmation.addEventListener('input', validate);
    email.addEventListener('change', validate);
    confirmation.addEventListener('change', validate);
    validate();
  }

  window.SEMA_buildEnderecoPauDosFerros = function (host) {
    if (!host) return;
    if (host.dataset.locationReady === 'true') {
      if (typeof host.SEMA_syncLocation === 'function') host.SEMA_syncLocation();
      return;
    }
    host.dataset.locationReady = 'true';

    const hidden = host.querySelector('[data-location-output]');
    const rua = host.querySelector('[data-location-field="rua"]');
    const lote = host.querySelector('[data-location-field="lote"]');
    const quadra = host.querySelector('[data-location-field="quadra"]');
    const numero = host.querySelector('[data-location-field="numero"]');
    const bairro = host.querySelector('[data-location-field="bairro"]');
    const preview = host.querySelector('[data-location-preview]');
    const optional = host.dataset.locationOptional === 'true';
    if (!hidden || !rua || !bairro) return;

    function montar() {
      const partes = [];
      const ruaTxt = normalizar(rua.value);
      const loteTxt = normalizar(lote?.value);
      const quadraTxt = normalizar(quadra?.value);
      const numeroRaw = normalizar(numero?.value);
      const numeroTxt = numeroRaw || 'SN';
      const bairroTxt = normalizar(bairro.value);

      if (optional && !ruaTxt && !loteTxt && !quadraTxt && !numeroRaw && !bairroTxt) {
        hidden.value = '';
        if (preview) preview.textContent = 'PAU DOS FERROS/RN.';
        hidden.dispatchEvent(new Event('input', { bubbles: true }));
        return;
      }

      if (ruaTxt) partes.push(ruaTxt);
      if (loteTxt || quadraTxt) {
        const loteQuadra = [
          loteTxt ? 'LOTE ' + loteTxt : '',
          quadraTxt ? 'QUADRA ' + quadraTxt : ''
        ].filter(Boolean).join(', ');
        partes.push('(' + loteQuadra + ')');
      }
      partes.push(numeroTxt);
      if (bairroTxt) partes.push('BAIRRO ' + bairroTxt);
      partes.push('PAU DOS FERROS/RN.');

      const endereco = partes.join(', ').replace('), ' + numeroTxt, ') ' + numeroTxt);
      hidden.value = endereco;
      if (preview) preview.textContent = endereco;
      hidden.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function hidratarDoConsolidado(force = false) {
      if (!hidden.value || (!force && (rua.value || bairro.value))) return;
      const valor = hidden.value.trim();
      const match = valor.match(/^(.+?)(?:,\s*\((.*?)\))?\s+([^,]+),\s+BAIRRO\s+(.+?),\s+PAU DOS FERROS\/RN\.?$/i);
      if (!match) {
        if (preview) preview.textContent = valor;
        return;
      }
      rua.value = match[1] || '';
      const loteQuadra = match[2] || '';
      const numeroTxt = match[3] || '';
      bairro.value = match[4] || '';
      const loteMatch = loteQuadra.match(/LOTE\s+([^,]+)/i);
      const quadraMatch = loteQuadra.match(/QUADRA\s+(.+)$/i);
      if (lote && loteMatch) lote.value = loteMatch[1].trim();
      if (quadra && quadraMatch) quadra.value = quadraMatch[1].trim();
      if (numero && numeroTxt.toUpperCase() !== 'SN') numero.value = numeroTxt.trim();
    }

    host.querySelectorAll('[data-location-field]').forEach((field) => {
      field.addEventListener('input', montar);
      field.addEventListener('change', montar);
    });
    host.SEMA_syncLocation = function () {
      hidratarDoConsolidado(true);
      montar();
    };
    hidratarDoConsolidado();
    montar();
  };

  window.SEMA_initLocationComposers = function (root) {
    (root || document).querySelectorAll('[data-location-composer]').forEach(window.SEMA_buildEnderecoPauDosFerros);
  };

  function locationTemplate(key) {
    return cfg().locationTemplates?.[key] || '';
  }

  function renderResponsavelTecnico(required = false) {
    const req = required ? 'required' : '';
    const mark = required ? ' *' : '';
    return `
      <div class="public-subsection">
        <div class="form-section-label">Responsável técnico${required ? ' <span style="color:#f87171">*</span>' : ', caso tenha'}</div>
        <p class="public-field-note">Informe os dados do profissional quando houver responsável técnico vinculado ao serviço.</p>
        <div class="form-grid-2">
          <input ${req} name="responsavel_tecnico_nome" placeholder="Nome do responsável técnico${mark}">
          <select ${req} name="responsavel_tecnico_tipo_documento">
            <option value="">Conselho / documento${mark}</option>
            <option value="CREA">CREA</option>
            <option value="CAU">CAU</option>
            <option value="ART">ART</option>
            <option value="RRT">RRT</option>
            <option value="TRT">TRT</option>
          </select>
        </div>
        <div class="form-grid-2">
          <input ${req} name="responsavel_tecnico_registro" placeholder="Registro profissional${mark}">
          <input ${req} name="responsavel_tecnico_numero" placeholder="Número da ART/RRT/TRT${mark}">
        </div>
        <div class="form-grid-2">
          <input type="email" name="responsavel_tecnico_email" placeholder="E-mail do responsável técnico">
          <input name="responsavel_tecnico_telefone" placeholder="Telefone do responsável técnico">
        </div>
      </div>
    `;
  }

  function renderDesmembramentoLote(index, label) {
    const prefix = index === 0 ? '' : `lotes[${index}]`;
    const areaName = index === 0 ? 'area_lote' : `${prefix}[area]`;
    const confrontoName = (rumo, campo) => index === 0
      ? `confrontacao_${rumo}_${campo}`
      : `${prefix}[confrontacoes][${rumo}][${campo}]`;
    return `
      <section class="public-lote-card" data-lote-card data-lote-index="${index}">
        <div class="public-lote-heading">
          <div>
            <span class="public-lote-kicker">Desmembramento</span>
            <h3>${label}</h3>
          </div>
          ${index > 0 ? '<button type="button" class="public-remove-lote" data-remove-lote aria-label="Remover este lote">Remover lote</button>' : ''}
        </div>
        <label class="public-lote-area">
          Área deste lote (m²) *
          <input required name="${areaName}" placeholder="Ex.: 250,00" data-lote-area data-desmembramento-field>
        </label>
        <div class="public-section-heading-small">Confrontações</div>
        <div class="public-confrontacoes-grid">
          ${[
            ['norte', 'Norte'],
            ['oeste', 'Oeste'],
            ['leste', 'Leste'],
            ['sul', 'Sul']
          ].map(([rumo, rumoLabel]) => `
            <fieldset class="public-confrontacao-card" data-confrontacao-card>
              <legend>${rumoLabel}</legend>
              <label>
                Medida (m)
                <input required name="${confrontoName(rumo, 'metragem')}" placeholder="Ex.: 12,50" data-desmembramento-field>
              </label>
              <label>
                Limite / confrontante
                <input required name="${confrontoName(rumo, 'descricao')}" placeholder="Ex.: Rua Adelino Aires, lote 12 ou vizinho" data-desmembramento-field>
              </label>
            </fieldset>
          `).join('')}
        </div>
      </section>
    `;
  }

  function renderDynamicFields(tipo) {
    const tipoRules = cfg().tipoRules || {};
    const currentRules = tipoRules[tipo] || {};
    const enquadramentoOptionsHtml = cfg().enquadramentoOptionsHtml || '';

    if (tipo === 'denuncia') {
      return `
        <div class="form-section-label" style="margin-top:0;">Denúncia e envio</div>
        <textarea required name="observacoes" rows="5" placeholder="Descreva o que está acontecendo, desde quando e onde exatamente *"></textarea>

        <div class="public-choice-intro">
          <strong>Como você quer registrar?</strong>
          <p>A identificação informada na etapa 1 só é usada se você escolher acompanhar.</p>
        </div>
        <div class="form-grid-2">
          <label class="form-toggle public-choice-card">
            <span><input type="radio" name="anonimo" value="0" checked> Quero acompanhar</span>
            <small>Você recebe um protocolo para acompanhar a denúncia e ver as medidas tomadas. A equipe usa seus dados para retorno.</small>
          </label>
          <label class="form-toggle public-choice-card">
            <span><input type="radio" name="anonimo" id="chk_anonimo" value="1"> Denúncia anônima</span>
            <small>Seus dados não são registrados. Não há protocolo de acompanhamento nem retorno sobre a denúncia.</small>
          </label>
        </div>

        <div class="form-section-label" style="margin-top:18px;">Qual equipe deve analisar? <span style="color:#f87171">*</span></div>
        <div class="form-grid-2">
          <label class="public-choice-card"><input type="radio" name="setor" value="meio_ambiente" required> Meio Ambiente (SEMA)</label>
          <label class="public-choice-card"><input type="radio" name="setor" value="obras_urbanismo" required> Obras e Serviços Urbanos</label>
        </div>

        <div class="form-section-label" style="margin-top:18px;">Tipo de ocorrência <span style="color:#f87171">*</span></div>
        <div class="public-check-grid" id="tipos_denuncia_grid">
          ${[
            ['obstrucao_via', 'Obstrução de via'],
            ['terreno_sujo', 'Terreno sujo'],
            ['terreno_baldio', 'Terreno baldio'],
            ['esgoto_via', 'Esgoto em via pública'],
            ['construcao_irregular', 'Construção irregular'],
            ['entulho_construcao', 'Entulho em construção civil'],
            ['entulho_via', 'Entulho em via pública'],
            ['outros', 'Outros']
          ].map(([val, label]) => `<label class="public-choice-card"><input type="checkbox" name="tipos_denuncia[]" value="${val}" onchange="toggleOutros(this)"> ${label}</label>`).join('')}
        </div>
        <div id="bloco_outros" style="display:none;margin-top:10px;">
          <input name="outros_descricao" placeholder="Descreva a ocorrência marcada como Outros *" style="width:100%">
        </div>

      `;
    }

    if (tipo === 'construcao' || tipo === 'construcao_obras_publicas') {
      return `
        <div class="form-grid-2">
          <select required name="tipo_edificacao" data-preview-field>
            <option value="">Tipo de edificação *</option>
            <option>Residencial unifamiliar</option>
            <option>Residencial multifamiliar</option>
            <option>Comercial</option>
            <option>Mista</option>
            <option>Industrial</option>
            <option>Muro e calçada</option>
            <option>Reforma e ampliação</option>
          </select>
          <input required type="number" min="1" step="1" name="numero_pavimentos" value="1" placeholder="Pavimentos *" data-preview-field>
        </div>
        <div class="form-grid-2">
          <input required name="area_construcao" placeholder="Área a ser construída (m²) *" data-preview-field>
          <input name="cadastro_imobiliario" placeholder="Cadastro imobiliário">
        </div>
        <div class="form-grid-2">
          <label>Início previsto<input type="date" name="inicio_obra"></label>
          <label>Término previsto<input type="date" name="termino_obra"></label>
        </div>
        <input type="hidden" name="especificacao" data-service-preview-output>
        <div class="public-preview-box"><span>Prévia da especificação:</span><strong data-service-preview>Selecione os dados da obra para gerar a frase.</strong></div>
      `;
    }

    if (tipo === 'desmembramento') {
      return `
        <div class="form-grid-2">
          <label>
            Área total do terreno (m²) *
            <input required name="area_total_terreno" placeholder="Ex.: 500,00" data-area-total>
          </label>
          <div class="public-calculated-area" aria-live="polite">
            <span>Área remanescente</span>
            <strong data-area-remanescente>Informe as áreas acima</strong>
            <small data-area-lotes-resumo>Soma dos lotes: —</small>
            <input type="hidden" name="area_remanescente" data-area-remanescente-value>
          </div>
        </div>
        <div class="public-lotes-heading">
          <div>
            <div class="form-section-label" style="margin-top:18px;">Lotes do desmembramento</div>
          </div>
        </div>
        <div data-lotes-list>${renderDesmembramentoLote(0, 'Lote 1')}</div>
        <div class="public-extra-lotes" data-extra-lotes></div>
        <button type="button" class="public-secondary-btn public-add-lote-btn" data-add-lote><span aria-hidden="true">+</span> Adicionar lote</button>
        <label class="public-description-field">
          Descrição complementar do desmembramento *
          <textarea required name="descricao_atividade" placeholder="Acrescente alguma informação relevante sobre o desmembramento." rows="3" data-desmembramento-descricao></textarea>
        </label>
        <div class="public-preview-box public-desmembramento-resumo"><span>Resumo</span><strong data-desmembramento-preview>Preencha os lotes para visualizar.</strong></div>
        <div class="public-desmembramento-warning" data-desmembramento-warning role="alert" hidden></div>
      `;
    }

    if (tipo === 'habite_se' || tipo === 'habite_se_simples' || tipo === 'habite_se_obras_publicas') {
      return `
        <div class="form-grid-2">
          <input required name="alvara_construcao_numero" placeholder="Número do alvará de construção de origem *">
          <input required name="area_construida" placeholder="Área construída (m²) *" data-habite-preview-field>
        </div>
        <div class="form-grid-2">
          ${[
            ['tipo_construcao', 'Tipo de construção', ['Alvenaria', 'Concreto armado', 'Estrutura metálica', 'Mista']],
            ['estrutura', 'Estrutura', ['Convencional', 'Pré-moldada', 'Metálica']],
            ['piso', 'Piso', ['Cerâmico', 'Cimentado', 'Porcelanato']],
            ['cobertura', 'Cobertura', ['Telha cerâmica', 'Telha fibrocimento', 'Laje', 'Metálica']]
          ].map(([name, label, options]) => `
            <label class="public-habite-select-field">${label} *
              <select required name="${name}" data-habite-preview-field data-habite-other-select>
                <option value="">Selecione *</option>
                ${options.map((option) => `<option>${option}</option>`).join('')}
                <option value="__outro__">Outro</option>
              </select>
              <input type="text" name="${name}_outro" placeholder="Descreva outro ${label.toLowerCase()}" data-habite-preview-field data-habite-other-input hidden>
            </label>
          `).join('')}
        </div>
        <div class="public-habite-rooms-heading">
          <strong>Ambientes — quantidade total no imóvel</strong>
          <span>Informe quantos existem na edificação concluída.</span>
        </div>
        <div class="form-grid-2">
          <label>Quartos — total no imóvel<input type="number" min="0" name="quartos" placeholder="Quantidade" data-habite-preview-field data-habite-room></label>
          <label>Suítes — total no imóvel<input type="number" min="0" name="suites" placeholder="Quantidade" data-habite-preview-field data-habite-room></label>
        </div>
        <div class="form-grid-2">
          <label>Banheiros — total no imóvel<input type="number" min="0" name="banheiros" placeholder="Quantidade" data-habite-preview-field data-habite-room></label>
          <label>Salas — total no imóvel<input type="number" min="0" name="salas" placeholder="Quantidade" data-habite-preview-field data-habite-room></label>
        </div>
        <div class="form-grid-2">
          <label>Cozinhas — total no imóvel<input type="number" min="0" name="cozinhas" placeholder="Quantidade" data-habite-preview-field data-habite-room></label>
          <div></div>
        </div>
        <button type="button" class="public-secondary-btn public-add-room-btn" data-add-habite-room>+ Adicionar outro ambiente</button>
        <div class="public-extra-rooms" data-habite-extra-rooms></div>
        <input type="hidden" name="especificacao" data-habite-preview-output>
        <div class="public-preview-box"><span>Prévia das características:</span><strong data-habite-preview>Preencha os campos para montar o parágrafo do laudo.</strong></div>
      `;
    }

    if (currentRules.ambiental || tipo === 'licenca_operacao' || tipo === 'licenca_instalacao_operacao' || tipo === 'licenca_operacional_corretiva') {
      return `
        <div class="form-grid-2">
          <input required name="descricao_atividade" placeholder="Atividade ou finalidade *">
          <input name="area_empreendimento" placeholder="Área do empreendimento (m²/hectares)">
        </div>
        <div style="background:rgba(255,255,255,0.08); border-radius:8px; padding:14px 16px; margin-bottom:12px; border-left:4px solid #009640;">
          <div style="font-weight:600; color:rgba(255,255,255,0.95); margin-bottom:8px; font-size:0.95rem;">Enquadramento Ambiental</div>
          <select ${currentRules.ambiental ? 'required' : ''} name="enquadramento_atividade" style="padding:10px; border:1px solid #ddd; border-radius:4px; width:100%; margin-bottom:8px;">
            <option value="" hidden>Selecione a atividade do empreendimento${currentRules.ambiental ? ' *' : ''}</option>
            ${enquadramentoOptionsHtml}
          </select>
        </div>
        <div class="form-grid-2">
          <input ${currentRules.exige_ctf ? 'required' : ''} name="ctf_numero" placeholder="Nº do CTF/IBAMA ${currentRules.exige_ctf ? '*' : '(quando exigido)'}">
          <input ${currentRules.exige_licenca_anterior ? 'required' : ''} name="licenca_anterior_numero" placeholder="Licença anterior ${currentRules.exige_licenca_anterior ? '*' : '(quando exigida)'}">
        </div>
        <div class="form-grid-2">
          <input ${currentRules.exige_diario_oficial ? 'required' : ''} name="publicacao_diario_oficial" placeholder="Publicação em Diário Oficial${currentRules.exige_diario_oficial ? ' *' : ' (se aplicável)'}">
          <input name="localizacao_google_maps" placeholder="Link do Google Maps (opcional)">
        </div>
        <div class="public-environment-study">
          <div class="form-section-label">Estudo ambiental</div>
          <p class="public-field-note">Informe se o empreendimento possui estudo ambiental ou se o estudo será apresentado posteriormente, conforme orientação técnica.</p>
          <div class="form-grid-2">
            <label class="public-choice-card"><input type="radio" name="possui_estudo_ambiental" value="1" required> Sim, possui estudo</label>
            <label class="public-choice-card"><input type="radio" name="possui_estudo_ambiental" value="0" required> Não possui no momento</label>
          </div>
          <label class="public-study-type-field">
            Tipo de estudo ambiental
            <select name="tipo_estudo_ambiental" data-study-type>
              <option value="">Selecione quando aplicável</option>
              <option>EIA/RIMA</option>
              <option>PCA — Plano de Controle Ambiental</option>
              <option>RCA — Relatório de Controle Ambiental</option>
              <option>RAS — Relatório Ambiental Simplificado</option>
              <option>PRAD — Plano de Recuperação de Área Degradada</option>
              <option>Plano de Gerenciamento de Resíduos</option>
              <option value="__outro__">Outro</option>
            </select>
            <input type="text" name="tipo_estudo_ambiental_outro" placeholder="Descreva o estudo" data-study-other hidden>
          </label>
        </div>
      `;
    }

    return `
      <div class="form-grid-2">
        <input required name="descricao_atividade" placeholder="Atividade ou finalidade *">
        <input name="area_empreendimento" placeholder="Área do empreendimento (se aplicável)">
      </div>
    `;
  }

  function clearFormErrors() {
    document.querySelectorAll('.field-invalid').forEach((el) => {
      el.classList.remove('field-invalid');
      el.removeAttribute('aria-invalid');
    });
    document.querySelectorAll('.field-error').forEach((el) => el.remove());
  }
  window.clearFormErrors = clearFormErrors;

  function validarArquivoUpload(input) {
    clearUploadMessage(input);
    if (!input.files || input.files.length === 0) return true;

    const tipoSelecionado = document.querySelector('select[name="tipo_alvara"]')?.value || '';
    const isDenuncia = tipoSelecionado === 'denuncia';
    const denunciaConfig = cfg().denunciaUpload || {};
    const tipoConfig = cfg().tipoRules?.[tipoSelecionado] || {};
    const limiteBytes = isDenuncia ? denunciaConfig.maxBytes : (tipoConfig.limite_upload || (100 * 1024 * 1024));
    const limiteLabel = isDenuncia ? denunciaConfig.maxLabel : (tipoConfig.limite_upload_label || '100MB');
    const extensoesPermitidas = isDenuncia ? denunciaConfig.allowedExtensions : ['pdf'];
    const tiposPermitidos = isDenuncia ? denunciaConfig.allowedTypes : ['application/pdf'];

    for (const file of Array.from(input.files)) {
      const ext = file.name.toLowerCase().split('.').pop();
      if (!extensoesPermitidas.includes(ext) || (file.type && !tiposPermitidos.includes(file.type))) {
        input.value = '';
        setUploadMessage(input, isDenuncia ? 'Envie apenas JPG, PNG, PDF, MP4 ou MOV.' : 'Envie apenas arquivos em PDF.');
        return false;
      }
      if (file.size > limiteBytes) {
        input.value = '';
        setUploadMessage(input, 'O arquivo "' + file.name + '" ultrapassa o limite de ' + limiteLabel + '.');
        return false;
      }
    }

    setUploadMessage(input, Array.from(input.files).map((file) => file.name).join(', '), true);
    return true;
  }
  window.validarArquivoUpload = validarArquivoUpload;

  function clearUploadMessage(input) {
    const current = input.parentElement?.querySelector('.upload-feedback');
    if (current) current.remove();
    input.classList.remove('field-invalid');
  }

  function setUploadMessage(input, message, success = false) {
    clearUploadMessage(input);
    const el = document.createElement('div');
    el.className = success ? 'upload-feedback upload-feedback-ok' : 'upload-feedback upload-feedback-error';
    el.textContent = message;
    input.parentElement?.appendChild(el);
    if (!success) input.classList.add('field-invalid');
  }

  window.toggleOutros = function (checkbox) {
    if (checkbox.value !== 'outros') return;
    const bloco = document.getElementById('bloco_outros');
    if (!bloco) return;
    bloco.style.display = checkbox.checked ? '' : 'none';
    const input = bloco.querySelector('input');
    if (input) input.required = checkbox.checked;
  };

  function collectDraft(form) {
    const data = {};
    form.querySelectorAll('input, select, textarea').forEach((field) => {
      if (!field.name || field.type === 'file') return;
      if (['csrf_token', 'form_loaded_at', 'site_empresa'].includes(field.name)) return;
      if (field.type === 'checkbox') {
        if (field.name.endsWith('[]')) {
          data[field.name] = data[field.name] || [];
          if (field.checked) data[field.name].push(field.value);
        } else {
          data[field.name] = field.checked;
        }
        return;
      }
      if (field.type === 'radio') {
        if (field.checked) data[field.name] = field.value;
        return;
      }
      data[field.name] = field.value;
    });
    return data;
  }

  function saveDraft(form) {
    try {
      localStorage.setItem(DRAFT_KEY, JSON.stringify(collectDraft(form)));
    } catch (e) {}
  }

  function getDraft() {
    try {
      return JSON.parse(localStorage.getItem(DRAFT_KEY) || '{}') || {};
    } catch (e) {
      return {};
    }
  }

  function applyData(form, data) {
    if (!data || typeof data !== 'object') return;
    form.querySelectorAll('input, select, textarea').forEach((field) => {
      if (!field.name || field.type === 'file') return;
      const direct = Object.prototype.hasOwnProperty.call(data, field.name) ? data[field.name] : fieldValue(data, field.name);
      if (direct === undefined || direct === null || direct === '') return;
      if (field.type === 'checkbox') {
        field.checked = Array.isArray(direct) ? direct.includes(field.value) : !!direct;
      } else if (field.type === 'radio') {
        field.checked = String(direct) === String(field.value);
      } else {
        field.value = direct;
      }
      field.dispatchEvent(new Event('change', { bubbles: true }));
    });
    window.SEMA_initLocationComposers?.(form);
  }

  function restoreData(form) {
    const serverData = cfg().formData || {};
    const draft = cfg().hasServerFormData ? {} : getDraft();
    const data = Object.keys(serverData).length ? serverData : draft;
    if (!Object.keys(data).length) return;
    applyData(form, data);
    const tipo = byName('tipo_alvara', form);
    if (tipo && (data.tipo_alvara || data['tipo_alvara'])) {
      tipo.value = data.tipo_alvara || data['tipo_alvara'];
      tipo.dispatchEvent(new Event('change', { bubbles: true }));
      window.SEMA_syncTipoCombobox?.(tipo.value);
      setTimeout(() => applyData(form, data), 450);
    }
  }

  function initDraft(form) {
    let timer = null;
    const schedule = () => {
      clearTimeout(timer);
      timer = setTimeout(() => saveDraft(form), 300);
    };
    form.addEventListener('input', schedule);
    form.addEventListener('change', schedule);
  }

  function setupWizard(form) {
    const tipoSection = form.querySelector('.form-section-alvara');
    const cadastroSections = Array.from(form.querySelectorAll('.secao-alvara:not(.public-rt-section)'));
    // Identificação é pedida em todos os serviços, então não entra no rodízio
    // por tipo: fica visível na etapa 1 mesmo antes de escolher a solicitação.
    const comumSection = form.querySelector('.public-secao-comum');
    const responsavelComum = form.querySelector('#responsavel-tecnico-comum');
    const denunciaLocationSection = form.querySelector('.public-denuncia-location-section');
    const camposDinamicosSection = form.querySelector('.public-dynamic-section');
    const camposDinamicos = form.querySelector('#campos_dinamicos');
    const documentosSection = form.querySelector('.public-docs-section');
    const documentos = form.querySelector('#documentos_necessarios');
    const declaration = form.querySelector('.form-part-4');
    const captcha = form.querySelector('.captcha');
    const submit = form.querySelector('#botao');
    const actions = form.querySelector('.public-wizard-actions');
    const back = form.querySelector('#public-step-back');
    const next = form.querySelector('#public-step-next');
    const steps = Array.from(form.querySelectorAll('[data-public-step]'));
    const counter = form.querySelector('.public-step-counter');
    if (!tipoSection || !steps.length) return;
    let unlockedStep = 1;

    tipoSection.parentNode.insertBefore(tipoSection, cadastroSections[0] || tipoSection);
    if (comumSection) tipoSection.insertAdjacentElement('afterend', comumSection);
    const previewNotice = document.createElement('div');
    previewNotice.className = 'public-step-preview-notice';
    previewNotice.hidden = true;
    previewNotice.setAttribute('role', 'status');
    previewNotice.innerHTML = '<i class="fas fa-eye" aria-hidden="true"></i><span>Você está apenas visualizando esta etapa. Para editar, avance pelo botão Continuar.</span>';
    const stepNav = form.querySelector('.public-step-nav');
    stepNav?.insertAdjacentElement('afterend', previewNotice);

    function getTipo() {
      return document.getElementById('tipo_alvara')?.value || '';
    }

    function isDenuncia() {
      return getTipo() === 'denuncia';
    }

    function hasService() {
      return getTipo() !== '';
    }

    function isAnonima() {
      return isDenuncia() && !!form.querySelector('input[name="anonimo"][value="1"]:checked');
    }

    function syncResponsibleBlock() {
      if (!responsavelComum) return;
      const tipo = getTipo();
      const rules = cfg().tipoRules?.[tipo] || {};
      responsavelComum.innerHTML = tipo && tipo !== 'denuncia'
        ? renderResponsavelTecnico(!!rules.exige_responsavel_tecnico)
        : '';
    }

    function validateStep(step) {
      const tipo = document.getElementById('tipo_alvara');
      if (step === 1 && !tipo.value) {
        const busca = document.getElementById('tipo_alvara_busca') || tipo;
        busca.focus();
        if (typeof busca.reportValidity === 'function') busca.reportValidity();
        return false;
      }
      const scope = step >= 1 && step <= 3 ? form : null;
      if (!scope) return true;
      const invalid = Array.from(scope.querySelectorAll('input, select, textarea')).find((field) => !field.disabled && !field.closest('[hidden]') && !field.checkValidity());
      if (invalid) {
        invalid.reportValidity();
        invalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
      }
      return true;
    }

    function updateHidden(el, hidden) {
      if (!el) return;
      el.hidden = hidden;
      el.setAttribute('aria-hidden', hidden ? 'true' : 'false');
    }

    function setReadonlyIn(container, readonly) {
      if (!container) return;
      container.querySelectorAll('input, select, textarea, button').forEach((field) => {
        if (field.type === 'hidden') return;
        if (field.dataset.publicReadonlyManaged !== 'true') {
          field.dataset.publicReadonlyManaged = 'true';
          field.dataset.publicOriginalDisabled = field.disabled ? 'true' : 'false';
          field.dataset.publicOriginalReadonly = field.readOnly ? 'true' : 'false';
        }
        if (readonly) {
          if (field.matches('input:not([type="file"]):not([type="checkbox"]):not([type="radio"]), textarea')) {
            field.readOnly = true;
          } else {
            field.disabled = true;
          }
          return;
        }
        field.disabled = field.dataset.publicOriginalDisabled === 'true';
        field.readOnly = field.dataset.publicOriginalReadonly === 'true';
      });
    }

    function setPreviewMode(step, preview) {
      form.dataset.publicPreviewStep = preview ? String(step) : '';
      previewNotice.hidden = !preview;
      [tipoSection, comumSection, ...cadastroSections, denunciaLocationSection, camposDinamicosSection, camposDinamicos, documentosSection, documentos, declaration, captcha]
        .forEach((section) => setReadonlyIn(section, preview && !section?.hidden));
      steps.forEach((button) => button.classList.toggle('is-preview', preview && Number(button.dataset.publicStep) === step));
      if (next) next.disabled = preview;
    }

    function showStep(step, options = {}) {
      step = Math.max(1, Math.min(3, Number(step) || 1));
      if (step > 1 && !hasService()) step = 1;
      const preview = !!options.preview || step > unlockedStep;
      form.dataset.publicCurrentStep = String(step);
      form.dataset.publicTipo = isDenuncia() ? 'denuncia' : 'alvara';

      const denuncia = isDenuncia();
      const anonima = isAnonima();
      updateHidden(tipoSection, step !== 1);
      updateHidden(comumSection, step !== 1);
      cadastroSections.forEach((section) => updateHidden(section, !hasService() || denuncia || step !== 1));
      updateHidden(denunciaLocationSection, !hasService() || !denuncia || step !== 1);
      updateHidden(camposDinamicosSection, step !== 2);
      updateHidden(camposDinamicos, step !== 2);
      updateHidden(documentosSection, step !== 3);
      updateHidden(documentos, step !== 3);
      updateHidden(declaration, step !== 3);
      updateHidden(captcha, step !== 3);
      updateHidden(submit, step !== 3 || preview);
      updateHidden(actions, step === 3 && !preview);
      if (back) back.disabled = step === 1;

      steps.forEach((button) => {
        const number = Number(button.dataset.publicStep);
        button.classList.toggle('is-active', number === step);
        button.classList.toggle('is-done', number < step);
        button.setAttribute('aria-current', number === step ? 'step' : 'false');
      });
      if (counter) counter.textContent = 'Etapa ' + step + ' de 3';

      const tipo = document.getElementById('tipo_alvara');
      if (tipo) tipo.required = false;
      cadastroSections.forEach((section) => {
        section.querySelectorAll('[data-required]').forEach((field) => {
          field.required = !denuncia && hasService() && step === 1;
        });
      });
      if (comumSection) {
        // Na denúncia anônima os dados continuam na tela, mas deixam de ser
        // exigidos porque não serão registrados.
        comumSection.querySelectorAll('[data-required]').forEach((field) => {
          field.required = step === 1 && !anonima;
        });
        comumSection.classList.toggle('is-descartado', anonima);
        updateHidden(comumSection.querySelector('[data-identificacao-anonimo]'), !anonima);
        const nota = comumSection.querySelector('[data-identificacao-nota]');
        updateHidden(nota, anonima);
        if (nota) {
          nota.textContent = denuncia
            ? 'Use um e-mail e telefone que você acessa. É por eles que a equipe entra em contato sobre a denúncia.'
            : 'Use um e-mail que você acessa. A confirmação, o boleto e os documentos finais serão enviados para esse endereço.';
        }
      }
      setRequiredIn(responsavelComum, step === 1 && !denuncia);
      if (denunciaLocationSection) {
        denunciaLocationSection.querySelectorAll('[data-required]').forEach((field) => {
          field.required = denuncia && hasService() && step === 1;
        });
      }
      setRequiredIn(camposDinamicos, step === 2);
      const declarationInput = declaration?.querySelector('#declaracao_veracidade');
      if (declarationInput) declarationInput.required = step === 3;
      setPreviewMode(step, preview);

      if (!options.silent) {
        window.scrollTo({ top: Math.max(0, form.offsetTop - 20), behavior: 'smooth' });
        setTimeout(() => {
          const activePanel = step === 1 ? tipoSection : (step === 2 ? camposDinamicosSection : documentosSection);
          const target = activePanel?.querySelector('input:not([type="hidden"]), select, textarea, button:not([disabled])');
          if (target && typeof target.focus === 'function') target.focus({ preventScroll: true });
        }, 180);
      }
    }

    function refresh() {
      showStep(Number(form.dataset.publicCurrentStep || 1), { silent: true });
    }

    window.SEMA_PUBLIC_FORM = { showStep, refresh };

    steps.forEach((button) => button.addEventListener('click', () => {
      const target = Number(button.dataset.publicStep);
      showStep(target, { preview: target > unlockedStep });
    }));
    if (back) back.addEventListener('click', () => {
      const current = Number(form.dataset.publicCurrentStep || 1);
      const preview = form.dataset.publicPreviewStep;
      showStep(preview ? unlockedStep : current - 1);
    });
    if (next) next.addEventListener('click', () => {
      const current = Number(form.dataset.publicCurrentStep || 1);
      if (!validateStep(current)) return;
      unlockedStep = Math.max(unlockedStep, Math.min(3, current + 1));
      showStep(current + 1);
    });
    const tipo = document.getElementById('tipo_alvara');
    if (tipo) tipo.addEventListener('change', () => {
      unlockedStep = 1;
      syncResponsibleBlock();
      setTimeout(() => showStep(1, { silent: true }), 0);
    });
    syncResponsibleBlock();
    window.SEMA_initLocationComposers?.(form);
    setTimeout(() => showStep(1, { silent: true }), 0);
  }

  function setupDynamicFields(form) {
    const documentosDiv = document.getElementById('documentos_necessarios');
    const tipoAlvaraSelect = document.getElementById('tipo_alvara');
    const secoesAlvara = document.querySelectorAll('.secao-alvara');
    const declaracaoVeracidade = document.getElementById('declaracao_veracidade');
    const camposDinamicos = document.getElementById('campos_dinamicos');
    const tituloDinamico = document.getElementById('public-dynamic-title');
    if (documentosDiv) documentosDiv.innerHTML = '';

    function ativarModoAlvara() {
      secoesAlvara.forEach((s) => {
        s.querySelectorAll('[data-required]').forEach((el) => { el.required = true; });
      });
      if (declaracaoVeracidade) declaracaoVeracidade.required = true;
      form.action = 'processar_formulario.php';
    }

    function ativarModoDenuncia() {
      secoesAlvara.forEach((s) => {
        s.querySelectorAll('[data-required]').forEach((el) => { el.required = false; });
      });
      if (declaracaoVeracidade) declaracaoVeracidade.required = false;
      form.action = 'processar_denuncia_publica.php';
    }

    secoesAlvara.forEach((s) => s.querySelectorAll('[data-required]').forEach((el) => { el.required = true; }));
    if (!tipoAlvaraSelect || !camposDinamicos || !documentosDiv) return;

    tipoAlvaraSelect.addEventListener('change', function () {
      const tipo = this.value;
      if (tipo === 'denuncia') ativarModoDenuncia();
      else ativarModoAlvara();

      if (tipo === '') {
        camposDinamicos.innerHTML = '';
        ativarModoAlvara();
        documentosDiv.innerHTML = '';
        return;
      }

      documentosDiv.innerHTML = `<div class="mensagem-carregando"><div class="spinner-border" role="status" style="width:3rem;height:3rem;color:#009640;margin-bottom:15px;"></div><p>Carregando informações...</p></div>`;
      fetch('scripts/obter_documentos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'tipo=' + encodeURIComponent(tipo)
      })
        .then((response) => response.text())
        .then((data) => {
          documentosDiv.innerHTML = data;
          documentosDiv.querySelectorAll('input[type="file"]').forEach((input) => {
            input.setAttribute('form', 'form');
            input.addEventListener('change', function () { validarArquivoUpload(this); });
          });
        })
        .catch((error) => {
          console.error('Erro:', error);
          documentosDiv.innerHTML = `<div class="mensagem-erro"><i class="fas fa-exclamation-triangle"></i><p>Não foi possível carregar os documentos necessários. Por favor, tente novamente.</p></div>`;
        });

      if (tituloDinamico) tituloDinamico.textContent = tipo === 'denuncia' ? 'Dados da denúncia' : 'Dados específicos do serviço';
      camposDinamicos.innerHTML = renderDynamicFields(tipo);
      window.SEMA_initLocationComposers?.(camposDinamicos);
      setupDynamicFieldBehaviors(camposDinamicos);
    });
  }

  function setupDynamicFieldBehaviors(root) {
    const anonimoRadios = root.querySelectorAll('input[name="anonimo"]');
    if (anonimoRadios.length) {
      const syncAnonimo = () => {
        // A identificação mora na etapa 1 (bloco comum a todos os serviços);
        // aqui só reavaliamos se ela ainda é exigida.
        window.SEMA_PUBLIC_FORM?.refresh?.();
      };
      anonimoRadios.forEach((radio) => radio.addEventListener('change', syncAnonimo));
      syncAnonimo();
    }

    root.querySelectorAll('input[type="file"]').forEach((input) => {
      input.addEventListener('change', function () { validarArquivoUpload(this); });
    });

    root.querySelectorAll('input[name="tipos_denuncia[]"][value="outros"]').forEach((checkbox) => window.toggleOutros(checkbox));

    const estudoRadios = root.querySelectorAll('input[name="possui_estudo_ambiental"]');
    const tipoEstudoInput = root.querySelector('input[name="tipo_estudo_ambiental"]');
    const tipoEstudoSelect = root.querySelector('select[name="tipo_estudo_ambiental"]');
    const outroEstudoInput = root.querySelector('[data-study-other]');
    const syncOutroEstudo = () => {
      if (!tipoEstudoSelect || !outroEstudoInput) return;
      outroEstudoInput.hidden = tipoEstudoSelect.value !== '__outro__';
      outroEstudoInput.required = tipoEstudoSelect.value === '__outro__';
      if (tipoEstudoSelect.value !== '__outro__') outroEstudoInput.value = '';
    };
    tipoEstudoSelect?.addEventListener('change', syncOutroEstudo);
    syncOutroEstudo();
    if (estudoRadios.length && tipoEstudoInput) {
      estudoRadios.forEach((radio) => {
        radio.addEventListener('change', () => {
          if (radio.value === '1' && radio.checked) tipoEstudoInput.setAttribute('required', 'required');
          else if (radio.value === '0' && radio.checked) tipoEstudoInput.removeAttribute('required');
        });
      });
    }
    if (estudoRadios.length && tipoEstudoSelect) {
      estudoRadios.forEach((radio) => radio.addEventListener('change', () => {
        tipoEstudoSelect.required = radio.value === '1' && radio.checked;
        if (radio.value === '0' && radio.checked) {
          tipoEstudoSelect.value = '';
          syncOutroEstudo();
        }
      }));
    }

    const total = root.querySelector('[data-area-total]');
    const remText = root.querySelector('[data-area-remanescente]');
    const remValue = root.querySelector('[data-area-remanescente-value]');
    const lotesResumo = root.querySelector('[data-area-lotes-resumo]');
    const parseArea = (value) => {
      const text = String(value || '').trim();
      if (!text) return 0;
      return Number(text.includes(',') ? text.replace(/\./g, '').replace(',', '.') : text) || 0;
    };
    const formatArea = (value) => value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const getLoteCards = () => Array.from(root.querySelectorAll('[data-lote-card]'));

    const updateDesmembramento = () => {
      const cards = getLoteCards();
      const totalArea = parseArea(total?.value);
      const lotes = cards.map((card, index) => {
        const areaField = card.querySelector('[data-lote-area]');
        const area = parseArea(areaField?.value);
        const lados = [
          ['norte', 'Norte'], ['oeste', 'Oeste'], ['leste', 'Leste'], ['sul', 'Sul']
        ].map(([rumo, label]) => {
          const metragem = card.querySelector(`[name*="[${rumo}][metragem]"], [name="confrontacao_${rumo}_metragem"]`)?.value.trim();
          const descricao = card.querySelector(`[name*="[${rumo}][descricao]"], [name="confrontacao_${rumo}_descricao"]`)?.value.trim();
          return metragem || descricao ? `${label}: ${metragem || 'metragem não informada'} confrontando com ${descricao || 'confrontante não informado'}` : '';
        }).filter(Boolean);
        const texto = lados.length ? lados.join('; ') + '.' : 'Preencha as confrontações para visualizar.';
        return { area, texto: lados.length ? `Lote ${index + 1}${area ? ` (${formatArea(area)} m²)`: ''}: ${texto}` : '' };
      });
      const somaLotes = lotes.reduce((sum, lote) => sum + lote.area, 0);
      const diferenca = totalArea - somaLotes;
      const remanescente = Math.max(0, diferenca);
      if (remText) remText.textContent = totalArea && somaLotes ? `${formatArea(remanescente)} m²` : '—';
      if (lotesResumo) lotesResumo.textContent = somaLotes ? `Soma dos lotes: ${formatArea(somaLotes)} m²` : 'Soma dos lotes: —';
      if (remValue) remValue.value = totalArea && somaLotes ? remanescente.toFixed(2).replace('.', ',') : '';
      const warning = root.querySelector('[data-desmembramento-warning]');
      if (warning) {
        const excesso = somaLotes - totalArea;
        const areaTotalmenteDistribuida = totalArea > 0 && somaLotes > 0 && Math.abs(diferenca) < 0.0001;
        warning.hidden = !(totalArea && somaLotes && (excesso > 0 || areaTotalmenteDistribuida));
        warning.classList.toggle('is-info', areaTotalmenteDistribuida && excesso <= 0);
        warning.textContent = warning.hidden ? '' : areaTotalmenteDistribuida
          ? 'Atenção: os lotes utilizam 100% da área do terreno. A área remanescente será zero.'
          : `Atenção: há uma inconsistência nos dados. A soma dos lotes (${formatArea(somaLotes)} m²) ultrapassa a área total (${formatArea(totalArea)} m²) em ${formatArea(excesso)} m². Revise os valores antes de enviar.`;
      }

      const desmembramentoPreview = root.querySelector('[data-desmembramento-preview]');
      const desmembramentoDescricao = root.querySelector('[data-desmembramento-descricao]');
      const resumo = lotes.map((lote) => lote.texto).filter(Boolean).join(' ');
      if (desmembramentoPreview) desmembramentoPreview.textContent = resumo || 'Preencha os lotes para visualizar.';
      if (desmembramentoDescricao && !desmembramentoDescricao.dataset.userEdited) {
        desmembramentoDescricao.dataset.programmaticUpdate = 'true';
        desmembramentoDescricao.value = resumo;
        delete desmembramentoDescricao.dataset.programmaticUpdate;
      }
    };

    total?.addEventListener('input', updateDesmembramento);
    root.addEventListener('input', (event) => {
      if (event.target.matches('[data-desmembramento-field], [data-lote-area]')) updateDesmembramento();
    });
    root.addEventListener('change', (event) => {
      if (event.target.matches('[data-desmembramento-field], [data-lote-area]')) updateDesmembramento();
    });

    const addLote = root.querySelector('[data-add-lote]');
    const lotesHost = root.querySelector('[data-extra-lotes]');
    addLote?.addEventListener('click', () => {
      const idx = getLoteCards().length;
      const wrap = document.createElement('div');
      wrap.innerHTML = renderDesmembramentoLote(idx, `Lote ${idx + 1}`);
      const card = wrap.firstElementChild;
      lotesHost.appendChild(card);
      card.querySelector('[data-remove-lote]')?.addEventListener('click', () => {
        card.remove();
        updateDesmembramento();
      });
      card.querySelector('[data-lote-area]')?.focus();
      updateDesmembramento();
    });

    const desmembramentoDescricao = root.querySelector('[data-desmembramento-descricao]');
    desmembramentoDescricao?.addEventListener('input', () => {
      if (desmembramentoDescricao.dataset.programmaticUpdate === 'true') return;
      desmembramentoDescricao.dataset.userEdited = desmembramentoDescricao.value.trim() ? 'true' : '';
    });
    updateDesmembramento();

    const obraPreview = root.querySelector('[data-service-preview]');
    const obraOutput = root.querySelector('[data-service-preview-output]');
    const updateObraPreview = () => {
      if (!obraPreview || !obraOutput) return;
      const tipo = root.querySelector('[name="tipo_edificacao"]')?.value || 'edificação';
      const pav = root.querySelector('[name="numero_pavimentos"]')?.value || '1';
      const area = root.querySelector('[name="area_construcao"]')?.value || 'área não informada';
      const texto = `Alvará para ${tipo.toLowerCase()}, com ${pav} pavimento(s), área a construir de ${area} m².`;
      obraPreview.textContent = texto;
      obraOutput.value = texto;
    };
    root.querySelectorAll('[data-preview-field]').forEach((field) => field.addEventListener('input', updateObraPreview));
    root.querySelectorAll('[data-preview-field]').forEach((field) => field.addEventListener('change', updateObraPreview));
    updateObraPreview();

    const habitePreview = root.querySelector('[data-habite-preview]');
    const habiteOutput = root.querySelector('[data-habite-preview-output]');
    const syncHabiteOther = (select) => {
      const other = select.parentElement?.querySelector('[data-habite-other-input]');
      if (!other) return;
      other.hidden = select.value !== '__outro__';
      other.required = select.value === '__outro__';
      if (select.value !== '__outro__') other.value = '';
    };
    root.querySelectorAll('[data-habite-other-select]').forEach((select) => {
      select.addEventListener('change', () => {
        syncHabiteOther(select);
        updateHabitePreview();
      });
      syncHabiteOther(select);
    });

    const extraRooms = root.querySelector('[data-habite-extra-rooms]');
    root.querySelector('[data-add-habite-room]')?.addEventListener('click', () => {
      const index = extraRooms.children.length + 1;
      const row = document.createElement('div');
      row.className = 'form-grid-2 public-extra-room';
      row.innerHTML = `<label>Outro ambiente<input required name="ambiente_extra_${index}_nome" placeholder="Ex.: Varanda" data-habite-preview-field></label><label>Total no imóvel<input required type="number" min="0" name="ambiente_extra_${index}_quantidade" placeholder="Quantidade" data-habite-preview-field></label><button type="button" class="public-remove-room" aria-label="Remover ambiente">Remover</button>`;
      extraRooms.appendChild(row);
      row.querySelector('.public-remove-room')?.addEventListener('click', () => {
        row.remove();
        updateHabitePreview();
      });
      row.querySelector('input')?.focus();
    });
    const updateHabitePreview = () => {
      if (!habitePreview || !habiteOutput) return;
      const v = (name) => root.querySelector(`[name="${name}"]`)?.value || '';
      const selectValue = (name) => v(name) === '__outro__' ? (v(`${name}_outro`) || 'outro não informado') : v(name);
      const ambientes = [...root.querySelectorAll('[data-habite-room]')].map((field) => field.value && `${field.closest('label')?.textContent.split('—')[0].trim() || field.name}: ${field.value}`).filter(Boolean);
      root.querySelectorAll('.public-extra-room').forEach((row) => {
        const nome = row.querySelector('[name*="_nome"]')?.value;
        const quantidade = row.querySelector('[name*="_quantidade"]')?.value;
        if (nome && quantidade) ambientes.push(`${nome}: ${quantidade}`);
      });
      const texto = `Edificação em ${selectValue('tipo_construcao') || 'tipo não informado'}, estrutura ${selectValue('estrutura') || 'não informada'}, piso ${selectValue('piso') || 'não informado'}, cobertura ${selectValue('cobertura') || 'não informada'}${ambientes.length ? ', contendo (quantidade total no imóvel): ' + ambientes.join(', ') : ''}.`;
      habitePreview.textContent = texto;
      habiteOutput.value = texto;
    };
    root.addEventListener('input', (event) => {
      if (event.target.matches('[data-habite-preview-field]')) updateHabitePreview();
    });
    root.addEventListener('change', (event) => {
      if (event.target.matches('[data-habite-preview-field]')) updateHabitePreview();
    });
    root.querySelectorAll('[data-habite-preview-field]').forEach((field) => {
      field.addEventListener('input', updateHabitePreview);
      field.addEventListener('change', updateHabitePreview);
    });
    updateHabitePreview();
  }

  function setupSubmitValidation(form) {
    form.addEventListener('submit', function (e) {
      clearFormErrors();
      const tipoAlvara = document.getElementById('tipo_alvara').value;
      let firstInvalid = null;

      function markInvalid(field, message) {
        if (!field) return;
        field.classList.add('field-invalid');
        field.setAttribute('aria-invalid', 'true');
        const host = field.closest('.form-toggle') || field.closest('.form-part-4') || field.parentElement;
        let error = host.querySelector(':scope > .field-error');
        if (!error) {
          error = document.createElement('div');
          error.className = 'field-error';
          host.appendChild(error);
        }
        error.textContent = message;
        if (!firstInvalid) firstInvalid = field;
      }

      function requireValue(selector, message) {
        const field = document.querySelector(selector);
        if (!field || !field.value.trim()) {
          markInvalid(field, message);
          return false;
        }
        return true;
      }

      function requireChecked(selector, hostSelector, message) {
        if (document.querySelector(selector)) return true;
        const host = document.querySelector(hostSelector);
        markInvalid(host?.querySelector('input, select, textarea') || host, message);
        return false;
      }

      function validarEmailRequerente() {
        const email = document.querySelector('input[name="requerente[email]"]');
        const emailConfirmacao = document.querySelector('input[name="requerente[email_confirmacao]"]');
        if (email?.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) markInvalid(email, 'Informe um e-mail válido.');
        if (email?.value.trim().toLowerCase() !== emailConfirmacao?.value.trim().toLowerCase()) {
          markInvalid(emailConfirmacao, 'Os e-mails informados não coincidem.');
        }
      }

      if (!tipoAlvara) markInvalid(document.getElementById('tipo_alvara'), 'Selecione o tipo de solicitação.');

      if (tipoAlvara === 'denuncia') {
        const anonimo = document.querySelector('input[name="anonimo"][value="1"]')?.checked;
        requireValue('input[name="proprietario_endereco"]', 'Informe o local da ocorrência.');
        if (!anonimo) {
          requireValue('input[name="requerente[nome]"]', 'Informe seu nome ou escolha denúncia anônima na etapa 2.');
          requireValue('input[name="requerente[email]"]', 'Informe o e-mail para receber o protocolo da denúncia.');
          requireValue('input[name="requerente[telefone]"]', 'Informe o telefone para contato.');
          requireValue('input[name="requerente[email_confirmacao]"]', 'Confirme o e-mail.');
          validarEmailRequerente();
        }
        requireValue('textarea[name="observacoes"]', 'Descreva a ocorrência denunciada.');
        requireChecked('input[name="setor"]:checked', 'input[name="setor"]', 'Selecione qual equipe deve analisar a denúncia.');
        requireChecked('input[name="tipos_denuncia[]"]:checked', '#tipos_denuncia_grid', 'Selecione pelo menos um tipo de ocorrência.');
        if (document.querySelector('input[name="tipos_denuncia[]"][value="outros"]')?.checked) {
          requireValue('input[name="outros_descricao"]', 'Descreva a ocorrência marcada como Outros.');
        }
      } else {
        requireValue('input[name="requerente[nome]"]', 'Informe o nome do requerente.');
        requireValue('input[name="requerente[email]"]', 'Informe o e-mail do requerente.');
        requireValue('input[name="requerente[email_confirmacao]"]', 'Confirme o e-mail do requerente.');
        requireValue('input[name="requerente[cpf_cnpj]"]', 'Informe o CPF ou CNPJ do requerente.');
        requireValue('input[name="requerente[telefone]"]', 'Informe o telefone do requerente.');
        requireValue('input[name="endereco_objetivo"]', 'Informe a localização da obra ou objetivo.');
        requireChecked('input[name="notificado_fiscal_obras"]:checked', 'input[name="notificado_fiscal_obras"]', 'Informe se houve notificação pelo fiscal de obras.');

        validarEmailRequerente();

        const nomeProprietario = document.querySelector('input[name="proprietario[nome]"]');
        const cpfProprietario = document.querySelector('input[name="proprietario[cpf_cnpj]"]');
        if (nomeProprietario?.value.trim() || cpfProprietario?.value.trim()) {
          if (!nomeProprietario.value.trim()) markInvalid(nomeProprietario, 'Informe o nome do proprietário.');
          if (!cpfProprietario.value.trim()) markInvalid(cpfProprietario, 'Informe o CPF ou CNPJ do proprietário.');
        }

        const rules = cfg().tipoRules?.[tipoAlvara] || {};
        if (rules.ambiental) {
          if (rules.exige_diario_oficial) requireValue('input[name="publicacao_diario_oficial"]', 'Informe os dados da publicação em Diário Oficial.');
          if (rules.exige_ctf) requireValue('input[name="ctf_numero"]', 'Informe o número do Cadastro Técnico Federal.');
          if (rules.exige_licenca_anterior) requireValue('input[name="licenca_anterior_numero"]', 'Informe o número da licença anterior.');
          const possuiEstudo = document.querySelector('input[name="possui_estudo_ambiental"]:checked');
          if (!possuiEstudo) requireChecked('input[name="possui_estudo_ambiental"]:checked', 'input[name="possui_estudo_ambiental"]', 'Informe se há estudo ambiental.');
          else if (possuiEstudo.value === '1') requireValue('input[name="tipo_estudo_ambiental"]', 'Informe o tipo de estudo ambiental.');
        }
      }

      if (firstInvalid) {
        e.preventDefault();
        if (firstInvalid.closest('.public-denuncia-location-section') || firstInvalid.closest('.secao-alvara') || firstInvalid.closest('.public-secao-comum') || firstInvalid.closest('.form-section-alvara')) window.SEMA_PUBLIC_FORM?.showStep(1);
        else if (firstInvalid.closest('#campos_dinamicos') || firstInvalid.closest('.public-dynamic-section')) window.SEMA_PUBLIC_FORM?.showStep(2);
        else if (firstInvalid.closest('.documentos-container')) window.SEMA_PUBLIC_FORM?.showStep(3);
        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (typeof firstInvalid.focus === 'function') firstInvalid.focus({ preventScroll: true });
        return false;
      }

      const loading = document.getElementById('loading');
      const botao = document.getElementById('botao');
      if (loading) loading.style.display = 'flex';
      if (botao) botao.disabled = true;
    });
  }

  function setupCookieNotice() {
    const notice = document.getElementById('public-cookie-notice');
    const accept = document.getElementById('public-cookie-accept');
    if (!notice || !accept) return;
    let accepted = false;
    try {
      accepted = localStorage.getItem(COOKIE_KEY) === 'accepted';
    } catch (e) {}
    if (!accepted) notice.hidden = false;
    accept.addEventListener('click', () => {
      try {
        localStorage.setItem(COOKIE_KEY, 'accepted');
      } catch (e) {}
      notice.hidden = true;
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('form');
    setupCookieNotice();
    if (!form) return;
    setupDynamicFields(form);
    setupEmailConfirmation(form);
    setupWizard(form);
    restoreData(form);
    initDraft(form);
    setupSubmitValidation(form);
  });
})();
