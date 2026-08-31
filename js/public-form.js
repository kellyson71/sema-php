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
    const semLote = host.querySelector('[data-location-no-lot]');
    const semNumero = host.querySelector('[data-location-no-number]');
    const preview = host.querySelector('[data-location-preview]');
    const optional = host.dataset.locationOptional === 'true';
    if (!hidden || !rua || !bairro) return;

    if (numero) {
      numero.addEventListener('input', () => {
        const valor = numero.value.toUpperCase().replace(/[^0-9A-Z]/g, '');
        const valido = valor.match(/^\d+[A-Z]?/);
        numero.value = valido ? valido[0] : '';
      });
    }

    function montar() {
      const partes = [];
      const ruaTxt = normalizar(rua.value);
      const loteTxt = semLote?.checked ? '' : normalizar(lote?.value);
      const quadraTxt = semLote?.checked ? '' : normalizar(quadra?.value);
      const numeroRaw = normalizar(numero?.value);
      const numeroTxt = semNumero?.checked || !numeroRaw ? 'SN' : numeroRaw;
      const bairroTxt = normalizar(bairro.value);

      if (optional && !ruaTxt && !loteTxt && !quadraTxt && !numeroRaw && !bairroTxt) {
        hidden.value = '';
        if (preview) preview.textContent = 'PAU DOS FERROS/RN.';
        hidden.dispatchEvent(new Event('input', { bubbles: true }));
        return;
      }

      if (ruaTxt) partes.push(ruaTxt);
      if (loteTxt) {
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
      if (numero && numeroTxt.toUpperCase() !== 'SN') {
        const numeroNormalizado = numeroTxt.trim().toUpperCase();
        numero.value = /^\d+[A-Z]?$/.test(numeroNormalizado) ? numeroNormalizado : '';
      }
    }

    host.querySelectorAll('[data-location-field]').forEach((field) => {
      field.addEventListener('input', montar);
      field.addEventListener('change', montar);
    });
    function atualizarCamposAusencia() {
      if (semLote) {
        const ocultar = !!semLote.checked;
        if (lote) {
          lote.style.display = ocultar ? 'none' : '';
          lote.disabled = ocultar;
          if (ocultar) lote.value = '';
        }
        if (quadra) {
          quadra.style.display = ocultar ? 'none' : '';
          quadra.disabled = ocultar;
          if (ocultar) quadra.value = '';
        }
      }
      if (semNumero && numero) {
        const ocultar = !!semNumero.checked;
        numero.style.display = ocultar ? 'none' : '';
        numero.disabled = ocultar;
        if (ocultar) numero.value = '';
      }
    }

    [semLote, semNumero].filter(Boolean).forEach((field) => {
      field.addEventListener('change', () => {
        atualizarCamposAusencia();
        montar();
      });
    });
    host.SEMA_syncLocation = function () {
      hidratarDoConsolidado(true);
      atualizarCamposAusencia();
      montar();
    };
    hidratarDoConsolidado();
    atualizarCamposAusencia();
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
          </select>
        </div>
        <div class="form-grid-2">
          <input ${req} name="responsavel_tecnico_registro" placeholder="Registro profissional${mark}">
          <input ${req} name="responsavel_tecnico_numero" placeholder="Número da ART/RRT${mark}">
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
    const cadastroName = index === 0 ? 'cadastro_imobiliario' : `${prefix}[cadastro_imobiliario]`;
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
        <div class="form-grid-2">
          <label class="public-lote-area">
            Área deste lote (m²) *
            <input required type="number" inputmode="decimal" min="0.01" step="0.01"
                   name="${areaName}" placeholder="Ex.: 250,00" data-lote-area data-desmembramento-field>
          </label>
          <label class="public-lote-area">
            Cadastro imobiliário deste lote *
            <input required name="${cadastroName}" placeholder="Ex.: 1010844" data-desmembramento-field>
          </label>
        </div>
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
                <input required type="number" inputmode="decimal" min="0.01" step="0.01"
                       name="${confrontoName(rumo, 'metragem')}" placeholder="Ex.: 12,50"
                       data-confrontacao-medida data-desmembramento-field>
              </label>
              <label>
                Limite / confrontante
                <input required name="${confrontoName(rumo, 'descricao')}"
                       placeholder="Ex.: Rua das Flores ou vizinho João da Silva" data-desmembramento-field>
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
        <textarea required name="observacoes" rows="5" placeholder="Descreva o que está acontecendo, desde quando e indique um ponto de referência, se houver *"></textarea>

        <div class="form-section-label" style="margin-top:18px;">Área da denúncia <span style="color:#f87171">*</span></div>
        <div class="form-grid-2">
          <label class="public-choice-card"><input type="radio" name="setor" value="meio_ambiente" required> <i class="fas fa-leaf" aria-hidden="true"></i> Meio Ambiente</label>
          <label class="public-choice-card"><input type="radio" name="setor" value="obras_urbanismo" required> <i class="fas fa-city" aria-hidden="true"></i> Obras e Serviços Urbanos</label>
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
            <option>Institucional</option>
            <option>Muro e calçada</option>
            <option>Reforma e ampliação</option>
          </select>
          <input required type="number" min="1" step="1" name="numero_pavimentos" value="1" placeholder="Pavimentos *" data-preview-field>
        </div>
        <div class="form-grid-2">
          <input required name="area_construcao" placeholder="Área a ser construída (m²) *" data-preview-field>
          <input required name="cadastro_imobiliario" placeholder="Cadastro imobiliário (sequencial) *">
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
            Nº da Matrícula do Imóvel (RGI) *
            <input required name="matricula_imovel" placeholder="Ex.: Matrícula 12.345 (Livro 2)" data-desmembramento-field>
          </label>
          <label>
            Área total do terreno (m²) *
            <input required type="number" inputmode="decimal" min="0.01" step="0.01"
                   name="area_total_terreno" placeholder="Ex.: 500,00" data-area-total>
          </label>
        </div>
        <div class="public-calculated-area" style="margin-bottom:14px;" aria-live="polite">
          <span>Área remanescente</span>
          <strong data-area-remanescente>Informe as áreas acima</strong>
          <small data-area-lotes-resumo>Soma dos lotes: —</small>
          <input type="hidden" name="area_remanescente" data-area-remanescente-value>
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
          <input required name="cadastro_imobiliario" placeholder="Cadastro imobiliário (sequencial) *">
          <label class="public-habite-select-field">Uso da edificação *
            <select required name="habite_uso" data-habite-preview-field data-habite-other-select>
              <option value="">Selecione o uso *</option>
              <option>Residencial</option><option>Comercial</option><option>Mista</option>
              <option>Industrial</option><option>Institucional</option><option value="Outro">Outro</option>
            </select>
            <input type="text" name="habite_uso_outro" class="public-habite-other-input" placeholder="Especifique o uso da edificação *" data-habite-other-input data-habite-preview-field hidden>
          </label>
        </div>
        <div class="form-grid-2">
          <label class="public-habite-select-field">Pavimento *
            <select required name="habite_pavimento" data-habite-preview-field data-habite-other-select>
              <option value="">Selecione o pavimento *</option>
              <option>Pavimento térreo</option><option>Dois pavimentos</option><option>Três pavimentos</option>
              <option>Quatro pavimentos</option><option>Cinco pavimentos</option><option>Seis pavimentos</option>
              <option value="Outro">Outro</option>
            </select>
            <input type="text" name="habite_pavimento_outro" class="public-habite-other-input" placeholder="Especifique o pavimento *" data-habite-other-input data-habite-preview-field hidden>
          </label>
          <label class="public-habite-select-field">Tipo da construção *
            <select required name="habite_tipo_construcao" data-habite-preview-field data-habite-other-select>
              <option value="">Selecione o tipo *</option>
              <option>Casa isolada</option><option>Casa geminada</option><option>Edifício</option>
              <option>Galpão</option><option>Estabelecimento comercial</option><option value="Outro">Outro</option>
            </select>
            <input type="text" name="habite_tipo_construcao_outro" class="public-habite-other-input" placeholder="Especifique o tipo da construção *" data-habite-other-input data-habite-preview-field hidden>
          </label>
        </div>
        <div class="form-grid-2">
          ${[
            ['habite_padrao', 'Padrão construtivo', ['Baixo', 'Normal', 'Alto']],
            ['habite_estrutura', 'Estrutura', ['Alvenaria e concreto armado', 'Concreto armado', 'Metálica', 'Pré-moldada']],
            ['habite_portas', 'Portas', ['Madeira', 'Alumínio', 'Vidro', 'Ferro']],
            ['habite_janelas', 'Janelas', ['Alumínio e vidro', 'Madeira', 'Vidro', 'Ferro']],
            ['habite_piso', 'Revestimento do piso', ['Cerâmico', 'Cimentado', 'Porcelanato', 'Granito']],
            ['habite_paredes', 'Revestimento das paredes', ['Pintura', 'Cerâmica', 'Reboco e pintura']],
            ['habite_forro', 'Revestimento superior', ['Gesso', 'PVC', 'Laje', 'Madeira']],
            ['habite_cobertura', 'Cobertura', ['Telha cerâmica', 'Telha de fibrocimento', 'Laje', 'Telha metálica']]
          ].map(([name, label, options]) => `
            <label class="public-habite-select-field">${label} *
              <select required name="${name}" data-habite-preview-field data-habite-other-select>
                <option value="">Selecione *</option>
                ${options.map((option) => `<option>${option}</option>`).join('')}
                <option value="Outro">Outro</option>
              </select>
              <input type="text" name="${name}_outro" class="public-habite-other-input" placeholder="Especifique ${label.toLowerCase()} *" data-habite-other-input data-habite-preview-field hidden>
            </label>
          `).join('')}
        </div>
        <div class="form-grid-2">
          <label>Início da obra *<input required type="date" name="inicio_obra"></label>
          <label>Término da obra *<input required type="date" name="termino_obra"></label>
        </div>
        <div class="public-habite-rooms-heading">
          <strong>Ambientes — quantidade no imóvel</strong>
          <span>Informe a quantidade de cada ambiente na edificação concluída.</span>
        </div>
        <div class="form-grid-2">
          <label>Quartos
            <input type="number" min="0" name="quartos" id="habite_quartos" placeholder="Quantidade" data-habite-preview-field data-habite-room>
            <span style="display:block; font-size:.73rem; color:var(--public-muted); font-weight:500; text-transform:none; margin-top:2px;">Quantidade de quartos sem banheiro privativo</span>
          </label>
          <label>Suítes
            <input type="number" min="0" name="suites" id="habite_suites" placeholder="Quantidade" data-habite-preview-field data-habite-room>
            <span style="display:block; font-size:.73rem; color:var(--public-muted); font-weight:500; text-transform:none; margin-top:2px;">Quartos que possuem banheiro privativo</span>
          </label>
        </div>
        <div class="form-grid-2">
          <label>Banheiros sociais
            <input type="number" min="0" name="banheiros_sociais" id="habite_banheiros_sociais" placeholder="Quantidade" data-habite-preview-field data-habite-room>
            <span style="display:block; font-size:.73rem; color:var(--public-muted); font-weight:500; text-transform:none; margin-top:2px;">Não inclua os banheiros das suítes</span>
          </label>
          <label>Salas
            <input type="number" min="0" name="salas" id="habite_salas" placeholder="Quantidade" data-habite-preview-field data-habite-room>
          </label>
        </div>
        <div class="form-grid-2">
          <label>Cozinhas
            <input type="number" min="0" name="cozinhas" id="habite_cozinhas" placeholder="Quantidade" data-habite-preview-field data-habite-room>
          </label>
          <div></div>
        </div>
        <div data-habite-extra-rooms class="public-habite-extra-rooms"></div>
        <button type="button" class="public-secondary-btn public-add-room-btn" data-add-habite-room style="margin-top:6px; margin-bottom:12px;">+ Adicionar outro ambiente</button>
        <input type="hidden" name="habite_ambientes_json" data-habite-ambientes-output>
        <div class="public-preview-box" style="margin-top:14px;">
          <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:6px;">
            <span>Prévia das características:</span>
            <button type="button" class="public-secondary-btn" data-habite-regenerate style="font-size:.75rem; min-height:28px; padding:2px 10px; cursor:pointer;" title="Restaurar texto automático gerado pelos campos">
              <i class="fas fa-arrows-rotate me-1"></i>Restaurar texto automático
            </button>
          </div>
          <textarea name="especificacao" id="habite_especificacao" class="form-control" rows="4" data-habite-preview-output
                    style="display:block; width:100%; box-sizing:border-box; padding:10px 12px; font-size:.85rem; line-height:1.55; text-transform:uppercase; border:1px solid var(--public-line); border-radius:4px; font-family:inherit; color:#024287; font-weight:700; background:#fff; resize:vertical;"
                    placeholder="Preencha os campos para montar o parágrafo do laudo ou edite diretamente aqui."></textarea>
          <div style="display:flex; justify-content:space-between; align-items:center; margin-top:6px; font-size:.75rem; color:var(--public-muted);">
            <span>Você pode editar e personalizar as características diretamente na caixa acima.</span>
            <span data-habite-edit-status style="font-weight:700;"></span>
          </div>
        </div>
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
      if (['csrf_token', 'form_loaded_at'].includes(field.name)) return;
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
    if (data.banheiros_sociais === undefined && data.banheiros !== undefined) {
      data.banheiros_sociais = data.banheiros;
    }
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
      // Os lotes adicionais são campos dinâmicos: precisam existir antes de
      // receber os valores devolvidos pelo servidor.
      const lotesExtras = data.lotes && typeof data.lotes === 'object'
        ? Object.keys(data.lotes).length
        : 0;
      const addLote = form.querySelector('[data-add-lote]');
      let lotesAtuais = Math.max(0, form.querySelectorAll('[data-lote-card]').length - 1);
      while (addLote && lotesAtuais < lotesExtras) {
        addLote.click();
        lotesAtuais += 1;
      }
      setTimeout(() => {
        applyData(form, data);
        window.SEMA_PUBLIC_FORM?.restoreStep?.(cfg().returnStep || 1);
      }, 450);
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
    const denunciaModeSection = form.querySelector('.public-denuncia-mode-section');
    const anonimoWarning = form.querySelector('[data-anonimo-warning]');
    const denunciaLocationSection = form.querySelector('.public-denuncia-location-section');
    const camposDinamicosSection = form.querySelector('.public-dynamic-section');
    const camposDinamicos = form.querySelector('#campos_dinamicos');
    const documentosSection = form.querySelector('.public-docs-section');
    const documentos = form.querySelector('#documentos_necessarios');
    const infoBanner = form.querySelector('.public-info-banner');
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
    if (denunciaModeSection) tipoSection.insertAdjacentElement('afterend', denunciaModeSection);
    if (comumSection) (denunciaModeSection || tipoSection).insertAdjacentElement('afterend', comumSection);
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
      clearFormErrors();
      let firstInvalid = null;

      function markInvalid(field, message) {
        if (!field) return;
        field.classList.add('field-invalid');
        field.setAttribute('aria-invalid', 'true');
        const host = field.closest('.form-toggle') || field.closest('.form-part-4') || field.closest('.public-habite-select-field') || field.parentElement;
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
        const field = typeof selector === 'string' ? form.querySelector(selector) : selector;
        if (!field || !field.value.trim()) {
          markInvalid(field, message);
          return false;
        }
        return true;
      }

      function requireChecked(selector, hostSelector, message) {
        if (form.querySelector(selector)) return true;
        const host = form.querySelector(hostSelector);
        markInvalid(host?.querySelector('input, select, textarea') || host, message);
        return false;
      }

      const tipoAlvara = getTipo();

      // ============================================
      // ETAPA 1
      // ============================================
      if (step === 1) {
        if (!tipoAlvara) {
          const busca = document.getElementById('tipo_alvara_busca') || document.getElementById('tipo_alvara');
          markInvalid(busca, 'Selecione o tipo de solicitação.');
        } else if (isDenuncia()) {
          const anonimo = form.querySelector('input[name="anonimo"][value="1"]')?.checked;
          const identificacaoEscolhida = requireChecked('input[name="anonimo"]:checked', '.public-denuncia-mode-section', 'Escolha se deseja se identificar ou fazer uma denúncia anônima.');
          requireValue('input[name="proprietario_endereco"]', 'Informe o local da ocorrência.');
          if (identificacaoEscolhida && !anonimo) {
            requireValue('input[name="requerente[nome]"]', 'Informe seu nome ou escolha fazer uma denúncia anônima.');
            if (requireValue('input[name="requerente[email]"]', 'Informe o e-mail para receber o protocolo da denúncia.')) {
              const email = form.querySelector('input[name="requerente[email]"]');
              if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
                markInvalid(email, 'Informe um e-mail válido.');
              }
            }
          }
          requireValue('textarea[name="observacoes"]', 'Descreva a ocorrência denunciada.');
          requireChecked('input[name="setor"]:checked', 'input[name="setor"]', 'Selecione qual equipe deve analisar a denúncia.');
          requireChecked('input[name="tipos_denuncia[]"]:checked', '#tipos_denuncia_grid', 'Selecione pelo menos um tipo de ocorrência.');
          if (form.querySelector('input[name="tipos_denuncia[]"][value="outros"]')?.checked) {
            requireValue('input[name="outros_descricao"]', 'Descreva a ocorrência marcada como Outros.');
          }
        } else {
          // Requerente
          requireValue('input[name="requerente[nome]"]', 'Informe o nome do requerente.');
          const emailInput = form.querySelector('input[name="requerente[email]"]');
          const emailConfInput = form.querySelector('input[name="requerente[email_confirmacao]"]');
          if (requireValue(emailInput, 'Informe o e-mail do requerente.')) {
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value.trim())) {
              markInvalid(emailInput, 'Informe um e-mail válido.');
            }
          }
          if (requireValue(emailConfInput, 'Confirme o e-mail do requerente.')) {
            if (emailInput && emailInput.value.trim().toLowerCase() !== emailConfInput.value.trim().toLowerCase()) {
              markInvalid(emailConfInput, 'A confirmação do e-mail não coincide com o e-mail informado.');
            }
          }
          const cpfInput = form.querySelector('input[name="requerente[cpf_cnpj]"]');
          if (requireValue(cpfInput, 'Informe o CPF ou CNPJ do requerente.')) {
            const rawCpf = cpfInput.value.replace(/\D/g, '');
            if (rawCpf.length !== 11 && rawCpf.length !== 14) {
              markInvalid(cpfInput, 'Informe um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.');
            }
          }
          const telInput = form.querySelector('input[name="requerente[telefone]"]');
          if (requireValue(telInput, 'Informe o telefone do requerente.')) {
            const rawTel = telInput.value.replace(/\D/g, '');
            if (rawTel.length < 10) {
              markInvalid(telInput, 'Informe um telefone válido com DDD (mínimo 10 dígitos).');
            }
          }

          // Localização
          requireValue('input[name="endereco_objetivo"]', 'Informe a localização da obra ou objetivo.');
          requireChecked('input[name="notificado_fiscal_obras"]:checked', 'input[name="notificado_fiscal_obras"]', 'Informe se houve notificação pelo fiscal de obras.');

          // Proprietário
          const nomeProprietario = form.querySelector('input[name="proprietario[nome]"]');
          const cpfProprietario = form.querySelector('input[name="proprietario[cpf_cnpj]"]');
          if (nomeProprietario?.value.trim() || cpfProprietario?.value.trim()) {
            if (!nomeProprietario.value.trim()) markInvalid(nomeProprietario, 'Informe o nome do proprietário.');
            if (!cpfProprietario.value.trim()) {
              markInvalid(cpfProprietario, 'Informe o CPF ou CNPJ do proprietário.');
            } else {
              const rawCpfP = cpfProprietario.value.replace(/\D/g, '');
              if (rawCpfP.length !== 11 && rawCpfP.length !== 14) {
                markInvalid(cpfProprietario, 'Informe um CPF ou CNPJ válido para o proprietário.');
              }
            }
          }

          // Responsável técnico
          const rules = cfg().tipoRules?.[tipoAlvara] || {};
          const rtNome = form.querySelector('input[name="responsavel_tecnico_nome"]');
          const rtReg = form.querySelector('input[name="responsavel_tecnico_registro"]');
          const rtTipo = form.querySelector('select[name="responsavel_tecnico_tipo_documento"]');
          const rtNum = form.querySelector('input[name="responsavel_tecnico_numero"], input[name="responsavel_tecnico_art"]');
          if (rules.exige_responsavel_tecnico) {
            requireValue(rtNome, 'Informe o nome do responsável técnico.');
            requireValue(rtReg, 'Informe o registro profissional (CREA/CAU).');
            requireValue(rtTipo, 'Selecione o conselho profissional (CREA ou CAU).');
            requireValue(rtNum, 'Informe o número da ART/RRT.');
          } else if (rtNome?.value.trim() || rtReg?.value.trim() || rtNum?.value.trim()) {
            requireValue(rtNome, 'Informe o nome do responsável técnico.');
            requireValue(rtReg, 'Informe o registro profissional.');
            requireValue(rtTipo, 'Selecione o conselho profissional.');
            requireValue(rtNum, 'Informe o número da ART/RRT.');
          }

          // Checar campos HTML5 visíveis na Etapa 1
          const visibleStep1 = form.querySelectorAll('.public-secao-comum:not([hidden]) input, .public-secao-comum:not([hidden]) select, .secao-alvara:not([hidden]) input, .secao-alvara:not([hidden]) select');
          visibleStep1.forEach((f) => {
            if (!f.disabled && !f.closest('[hidden]') && !f.checkValidity()) {
              markInvalid(f, f.validationMessage || 'Preencha este campo obrigatório.');
            }
          });
        }
      }

      // ============================================
      // ETAPA 2
      // ============================================
      if (step === 2 && !isDenuncia()) {
        const rules = cfg().tipoRules?.[tipoAlvara] || {};

        if (['habite_se', 'habite_se_simples', 'habite_se_obras_publicas'].includes(tipoAlvara)) {
          requireValue('input[name="alvara_construcao_numero"]', 'Informe o número do alvará de construção de origem.');
          requireValue('input[name="area_construida"]', 'Informe a área construída.');
          requireValue('input[name="cadastro_imobiliario"]', 'Informe o cadastro imobiliário (sequencial).');
          requireValue('select[name="habite_uso"]', 'Selecione o uso da edificação.');
          requireValue('select[name="habite_pavimento"]', 'Selecione o pavimento.');
          requireValue('select[name="habite_tipo_construcao"]', 'Selecione o tipo da construção.');

          ['habite_padrao', 'habite_estrutura', 'habite_portas', 'habite_janelas', 'habite_piso', 'habite_paredes', 'habite_forro', 'habite_cobertura'].forEach((campo) => {
            requireValue(`select[name="${campo}"]`, 'Selecione esta característica da edificação.');
          });

          form.querySelectorAll('[data-habite-other-select]').forEach((sel) => {
            if (sel.value === 'Outro' || sel.value === '__outro__') {
              const parent = sel.closest('.public-habite-select-field') || sel.parentElement;
              const other = parent?.querySelector('[data-habite-other-input]');
              if (other && !other.value.trim()) {
                markInvalid(other, 'Especifique o valor para este campo marcado como Outro.');
              }
            }
          });

          const inicioInput = form.querySelector('input[name="inicio_obra"]');
          const terminoInput = form.querySelector('input[name="termino_obra"]');
          requireValue(inicioInput, 'Informe a data de início da obra.');
          requireValue(terminoInput, 'Informe a data de término da obra.');
          if (inicioInput?.value && terminoInput?.value && terminoInput.value < inicioInput.value) {
            markInvalid(terminoInput, 'A data de término da obra não pode ser anterior ao início.');
          }

          form.querySelectorAll('.public-extra-room').forEach((row, idx) => {
            const nomeInp = row.querySelector('[name*="_nome"]');
            const qtdInp = row.querySelector('[name*="_quantidade"]');
            if (!nomeInp?.value.trim()) markInvalid(nomeInp, `Informe o nome do ambiente extra ${idx + 1}.`);
            if (!qtdInp?.value || Number(qtdInp.value) <= 0) markInvalid(qtdInp, `Informe a quantidade válida para o ambiente extra ${idx + 1}.`);
          });

          requireValue('textarea[name="especificacao"]', 'Informe ou gere as características da edificação.');
        }

        if (['construcao', 'construcao_obras_publicas'].includes(tipoAlvara)) {
          requireValue('input[name="tipo_edificacao"]', 'Informe o tipo da edificação.');
          const pavInput = form.querySelector('input[name="numero_pavimentos"]');
          if (requireValue(pavInput, 'Informe a quantidade de pavimentos.')) {
            if (Number(pavInput.value) < 1) markInvalid(pavInput, 'A quantidade de pavimentos deve ser de pelo menos 1.');
          }
          requireValue('input[name="area_construcao"]', 'Informe a área a ser construída.');
          requireValue('input[name="cadastro_imobiliario"]', 'Informe o cadastro imobiliário (sequencial).');
        }

        if (tipoAlvara === 'desmembramento') {
          requireValue('input[name="matricula_imovel"]', 'Informe o número da matrícula no Cartório de Registro de Imóveis (RGI).');
          const toAreaNum = (val) => {
            if (!val) return 0;
            const str = String(val).trim().replace(/\./g, '').replace(',', '.');
            return parseFloat(str) || 0;
          };
          const areaTotalInput = form.querySelector('input[name="area_total_terreno"], input[name="area_lote"]');
          const totalTerreno = toAreaNum(areaTotalInput?.value);
          if (totalTerreno <= 0) {
            markInvalid(areaTotalInput, 'Informe a área total da porção maior do terreno.');
          }

          const lotes = form.querySelectorAll('[data-lote-card]');
          let somaLotes = 0;
          lotes.forEach((lote, idx) => {
            const cad = lote.querySelector('input[name*="cadastro_imobiliario"]');
            if (!cad?.value.trim()) markInvalid(cad, `Informe o cadastro imobiliário do Lote ${idx + 1}.`);

            const areaInp = lote.querySelector('input[name*="area"]');
            const aVal = toAreaNum(areaInp?.value);
            if (aVal <= 0) {
              markInvalid(areaInp, `Informe uma área válida para o Lote ${idx + 1}.`);
            } else {
              somaLotes += aVal;
            }

            ['norte', 'oeste', 'leste', 'sul'].forEach((rumo) => {
              const met = lote.querySelector(`input[name*="[${rumo}][metragem]"]`);
              const desc = lote.querySelector(`input[name*="[${rumo}][descricao]"]`);
              if (!met || toAreaNum(met.value) <= 0) {
                markInvalid(met, `Informe a metragem do lado ${rumo.toUpperCase()} do Lote ${idx + 1}.`);
              }
              if (!desc || !desc.value.trim()) {
                markInvalid(desc, `Informe o confrontante do lado ${rumo.toUpperCase()} do Lote ${idx + 1}.`);
              }
            });
          });

          if (totalTerreno > 0 && somaLotes > totalTerreno) {
            const primeiraArea = form.querySelector('[data-lote-card] input[name*="area"]');
            markInvalid(primeiraArea, 'A soma das áreas dos lotes não pode ser maior que a área total do terreno.');
          }
        }

        if (rules.ambiental) {
          if (rules.exige_diario_oficial) requireValue('input[name="publicacao_diario_oficial"]', 'Informe os dados da publicação em Diário Oficial.');
          if (rules.exige_ctf) requireValue('input[name="ctf_numero"]', 'Informe o número do Cadastro Técnico Federal (CTF).');
          if (rules.exige_licenca_anterior) requireValue('input[name="licenca_anterior_numero"]', 'Informe o número da licença anterior.');
          const possuiEstudo = form.querySelector('input[name="possui_estudo_ambiental"]:checked');
          if (!possuiEstudo) {
            requireChecked('input[name="possui_estudo_ambiental"]:checked', 'input[name="possui_estudo_ambiental"]', 'Informe se há estudo ambiental.');
          } else if (possuiEstudo.value === '1') {
            requireValue('input[name="tipo_estudo_ambiental"]', 'Informe o tipo de estudo ambiental.');
          }
        }

        const camposDin = form.querySelector('#campos_dinamicos');
        if (camposDin) {
          camposDin.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach((f) => {
            if (!f.disabled && !f.closest('[hidden]') && !f.checkValidity()) {
              markInvalid(f, f.validationMessage || 'Preencha este campo obrigatório.');
            }
          });
        }
      }

      // ============================================
      // ETAPA 3
      // ============================================
      if (step === 3 && !isDenuncia()) {
        const docsNecessarios = form.querySelector('#documentos_necessarios');
        if (docsNecessarios) {
          docsNecessarios.querySelectorAll('.doc-row-required, [data-required="true"]').forEach((row) => {
            const fileInput = row.querySelector('input[type="file"]');
            if (fileInput && !fileInput.disabled && (!fileInput.files || fileInput.files.length === 0)) {
              markInvalid(fileInput, 'Anexe este documento obrigatório para continuar.');
            }
          });
        }
        const declarationInput = form.querySelector('#declaracao_veracidade');
        if (declarationInput && !declarationInput.checked) {
          markInvalid(declarationInput, 'Você deve declarar a veracidade das informações para enviar.');
        }

        const arquivos = Array.from(form.querySelectorAll('input[type="file"]')).flatMap((inp) => Array.from(inp.files || []));
        const tamanhoTotal = arquivos.reduce((acc, arq) => acc + arq.size, 0);
        const limiteTotal = Number(cfg().maxUploadTotalBytes || (250 * 1024 * 1024));
        if (tamanhoTotal > limiteTotal) {
          const primeiro = form.querySelector('input[type="file"]:not([disabled])');
          markInvalid(primeiro, `A soma dos arquivos ultrapassa ${cfg().maxUploadTotalLabel || '250MB'}. Remova ou reduza algum documento.`);
        }
      }

      if (firstInvalid) {
        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstInvalid.focus({ preventScroll: true });
        if (typeof firstInvalid.reportValidity === 'function' && !firstInvalid.checkValidity()) {
          firstInvalid.reportValidity();
        }
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
      const identificacaoEscolhida = !!form.querySelector('input[name="anonimo"]:checked');
      if (stepNav) updateHidden(stepNav, denuncia);
      updateHidden(infoBanner, denuncia);
      updateHidden(tipoSection, denuncia ? false : step !== 1);
      updateHidden(denunciaModeSection, !denuncia);
      updateHidden(anonimoWarning, !denuncia || !anonima);
      updateHidden(comumSection, denuncia ? (!identificacaoEscolhida || anonima) : step !== 1);
      cadastroSections.forEach((section) => updateHidden(section, !hasService() || denuncia || step !== 1));
      updateHidden(denunciaLocationSection, !denuncia || !hasService());
      updateHidden(camposDinamicosSection, denuncia ? false : step !== 2);
      updateHidden(camposDinamicos, denuncia ? false : step !== 2);
      updateHidden(documentosSection, denuncia ? false : step !== 3);
      updateHidden(documentos, denuncia ? false : step !== 3);
      updateHidden(declaration, denuncia ? false : step !== 3);
      updateHidden(captcha, denuncia ? false : step !== 3);
      updateHidden(submit, denuncia ? false : step !== 3 || preview);
      updateHidden(actions, denuncia || (step === 3 && !preview));
      if (back) back.disabled = step === 1;

      steps.forEach((button) => {
        const number = Number(button.dataset.publicStep);
        button.classList.toggle('is-active', number === step);
        button.classList.toggle('is-done', number < step);
        button.setAttribute('aria-current', number === step ? 'step' : 'false');
      });
      if (counter) counter.textContent = denuncia ? 'Formulário único' : 'Etapa ' + step + ' de 3';

      const tipo = document.getElementById('tipo_alvara');
      if (tipo) tipo.required = false;
      denunciaModeSection?.querySelectorAll('input[name="anonimo"]').forEach((field) => {
        field.required = denuncia;
      });
      cadastroSections.forEach((section) => {
        section.querySelectorAll('[data-required]').forEach((field) => {
          field.required = !denuncia && hasService() && step === 1;
        });
      });
      if (comumSection) {
        // Na denúncia anônima o bloco de identificação some e deixa de ser
        // exigido porque os dados pessoais não serão registrados.
        comumSection.querySelectorAll('[data-required]').forEach((field) => {
          field.required = denuncia ? (identificacaoEscolhida && !anonima) : step === 1;
        });
        const cpfRequerente = comumSection.querySelector('input[name="requerente[cpf_cnpj]"]');
        const confirmacaoEmail = comumSection.querySelector('input[name="requerente[email_confirmacao]"]');
        const telefoneRequerente = comumSection.querySelector('input[name="requerente[telefone]"]');
        updateHidden(cpfRequerente, denuncia);
        updateHidden(confirmacaoEmail, denuncia);
        if (cpfRequerente) cpfRequerente.required = denuncia ? false : step === 1;
        if (confirmacaoEmail) confirmacaoEmail.required = denuncia ? false : step === 1;
        if (telefoneRequerente) {
          telefoneRequerente.required = denuncia ? false : step === 1;
          telefoneRequerente.placeholder = denuncia ? 'Telefone para contato (opcional)' : 'Digite seu Telefone *';
        }
        comumSection.classList.toggle('is-descartado', anonima);
        updateHidden(comumSection.querySelector('[data-identificacao-anonimo]'), !anonima);
        const nota = comumSection.querySelector('[data-identificacao-nota]');
        updateHidden(nota, anonima);
        if (nota) {
          nota.textContent = denuncia
            ? 'Seus dados ficam protegidos e serão usados somente se a equipe precisar entrar em contato sobre a denúncia.'
            : 'Use um e-mail que você acessa. A confirmação, o boleto e os documentos finais serão enviados para esse endereço.';
        }
      }
      setRequiredIn(responsavelComum, step === 1 && !denuncia);
      if (denunciaLocationSection) {
        denunciaLocationSection.querySelectorAll('[data-required]').forEach((field) => {
          field.required = denuncia && hasService() && step === 1;
        });
      }
      setRequiredIn(camposDinamicos, denuncia || step === 2);
      const declarationInput = declaration?.querySelector('#declaracao_veracidade');
      if (declarationInput) declarationInput.required = denuncia || step === 3;
      if (submit) submit.innerHTML = denuncia
        ? '<i class="fas fa-paper-plane"></i> Enviar denúncia'
        : '<i class="fas fa-paper-plane"></i> Enviar requerimento';
      setPreviewMode(step, denuncia ? false : preview);

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

    function restoreStep(step) {
      const target = Math.max(1, Math.min(3, Number(step) || 1));
      unlockedStep = Math.max(unlockedStep, target);
      showStep(target, { silent: true });
      const alert = form.querySelector('.alert-erro, .alert-error');
      if (alert) alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    window.SEMA_PUBLIC_FORM = { showStep, refresh, restoreStep, validateStep };

    steps.forEach((button) => button.addEventListener('click', () => {
      const target = Number(button.dataset.publicStep);
      const current = Number(form.dataset.publicCurrentStep || 1);
      if (target > current) {
        for (let s = current; s < target; s++) {
          if (!validateStep(s)) {
            showStep(s);
            return;
          }
        }
      }
      showStep(target, { preview: target > unlockedStep });
    }));
    if (back) back.addEventListener('click', () => {
      clearFormErrors();
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
    form.querySelectorAll('input[name="anonimo"]').forEach((radio) => {
      radio.addEventListener('change', refresh);
    });
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
    const tituloDocumentos = form.querySelector('.public-docs-section > .form-section-label');
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

      if (tituloDinamico) tituloDinamico.textContent = tipo === 'denuncia' ? 'Sobre a denúncia' : 'Dados específicos do serviço';
      if (tituloDocumentos) tituloDocumentos.textContent = tipo === 'denuncia' ? 'Evidências (opcional)' : 'Documentos e envio';
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
          return metragem || descricao ? `${label}: ${metragem ? `${metragem} m` : 'metragem não informada'} confrontando com ${descricao || 'confrontante não informado'}` : '';
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

      const desmembramentoDescricao = root.querySelector('[data-desmembramento-descricao]');
      const resumo = lotes.map((lote) => lote.texto).filter(Boolean).join(' ');
      if (desmembramentoDescricao && !desmembramentoDescricao.dataset.userEdited) {
        desmembramentoDescricao.dataset.programmaticUpdate = 'true';
        desmembramentoDescricao.value = resumo;
        delete desmembramentoDescricao.dataset.programmaticUpdate;
      }
    };

    total?.addEventListener('input', updateDesmembramento);
    root.addEventListener('keydown', (event) => {
      if (event.target.matches('[data-confrontacao-medida], [data-area-total], [data-lote-area]')
          && ['e', 'E', '+', '-'].includes(event.key)) {
        event.preventDefault();
      }
    });
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
    const pavimentoTexto = (valor) => {
      const n = Math.max(1, parseInt(valor || '1', 10));
      if (n === 1) return 'PAVIMENTO TÉRREO';
      if (n === 2) return 'DOIS PAVIMENTOS (TÉRREO E PRIMEIRO PAVIMENTO)';
      if (n === 3) return 'TRÊS PAVIMENTOS (TÉRREO, PRIMEIRO E SEGUNDO PAVIMENTO)';
      return `${n} PAVIMENTOS`;
    };
    const updateObraPreview = () => {
      if (!obraPreview || !obraOutput) return;
      const tipo = root.querySelector('[name="tipo_edificacao"]')?.value || 'edificação';
      const pav = root.querySelector('[name="numero_pavimentos"]')?.value || '1';
      const area = root.querySelector('[name="area_construcao"]')?.value || 'área não informada';
      const texto = `CONSTRUÇÃO DE UMA ${tipo.toUpperCase()} DE ${pavimentoTexto(pav)} COM ${area} M² DE ÁREA A SER CONSTRUÍDA.`;
      obraPreview.textContent = texto;
      obraOutput.value = texto;
    };
    root.querySelectorAll('[data-preview-field]').forEach((field) => field.addEventListener('input', updateObraPreview));
    root.querySelectorAll('[data-preview-field]').forEach((field) => field.addEventListener('change', updateObraPreview));
    updateObraPreview();

    const habiteOutput = root.querySelector('[data-habite-preview-output]');
    const habiteAmbientesOutput = root.querySelector('[data-habite-ambientes-output]');
    const habiteEditStatus = root.querySelector('[data-habite-edit-status]');
    const habiteRegenerateBtn = root.querySelector('[data-habite-regenerate]');
    let habiteManuallyEdited = false;

    if (habiteOutput) {
      habiteOutput.addEventListener('input', () => {
        habiteManuallyEdited = true;
        if (habiteEditStatus) {
          habiteEditStatus.textContent = 'Editado manualmente';
          habiteEditStatus.style.color = '#d97706';
        }
      });
    }

    if (habiteRegenerateBtn) {
      habiteRegenerateBtn.addEventListener('click', (e) => {
        e.preventDefault();
        habiteManuallyEdited = false;
        updateHabitePreview(true);
      });
    }

    const syncHabiteOther = (select) => {
      const parent = select.closest('.public-habite-select-field') || select.parentElement;
      const other = parent?.querySelector('[data-habite-other-input]');
      if (!other) return;
      const isOther = select.value === 'Outro' || select.value === '__outro__';
      other.hidden = !isOther;
      other.required = isOther;
      if (!isOther) other.value = '';
    };
    root.querySelectorAll('[data-habite-other-select]').forEach((select) => {
      select.addEventListener('change', () => {
        syncHabiteOther(select);
        const parent = select.closest('.public-habite-select-field') || select.parentElement;
        const other = parent?.querySelector('[data-habite-other-input]');
        if ((select.value === 'Outro' || select.value === '__outro__') && other) {
          other.focus();
        }
        updateHabitePreview();
      });
      syncHabiteOther(select);
    });

    const extraRooms = root.querySelector('[data-habite-extra-rooms]');
    root.querySelector('[data-add-habite-room]')?.addEventListener('click', () => {
      if (!extraRooms) return;
      const index = extraRooms.children.length + 1;
      const row = document.createElement('div');
      row.className = 'form-grid-2 public-extra-room';
      row.innerHTML = `<label>Outro ambiente<input required name="ambiente_extra_${index}_nome" placeholder="Ex.: Varanda" data-habite-preview-field></label><label>Total no imóvel<input required type="number" min="1" name="ambiente_extra_${index}_quantidade" placeholder="Quantidade" data-habite-preview-field></label><button type="button" class="public-remove-room" aria-label="Remover ambiente">Remover</button>`;
      extraRooms.appendChild(row);
      row.querySelector('.public-remove-room')?.addEventListener('click', () => {
        row.remove();
        updateHabitePreview();
      });
      row.querySelectorAll('[data-habite-preview-field]').forEach((f) => {
        f.addEventListener('input', updateHabitePreview);
        f.addEventListener('change', updateHabitePreview);
      });
      row.querySelector('input')?.focus();
    });
    const updateHabitePreview = (force = false) => {
      if (!habiteOutput) return;
      const v = (name) => root.querySelector(`[name="${name}"]`)?.value || '';
      const selectValue = (name) => {
        const sel = root.querySelector(`[name="${name}"]`);
        if (!sel) return '';
        if (sel.value === 'Outro' || sel.value === '__outro__') {
          const parent = sel.closest('.public-habite-select-field') || sel.parentElement;
          const other = parent?.querySelector('[data-habite-other-input]');
          return other?.value?.trim() || 'OUTRO';
        }
        return sel.value || '';
      };
      const quartos = Math.max(0, Number(v('quartos') || 0));
      const suites = Math.max(0, Number(v('suites') || 0));
      const banheirosSociais = Math.max(0, Number(v('banheiros_sociais') || v('banheiros') || 0));
      const salas = Math.max(0, Number(v('salas') || 0));
      const cozinhas = Math.max(0, Number(v('cozinhas') || 0));

      const totalDormitorios = quartos + suites;
      const totalBanheiros = banheirosSociais + suites;

      const quantidades = {
        quartos: quartos,
        suites: suites,
        banheiros_sociais: banheirosSociais,
        banheiros: banheirosSociais,
        salas: salas,
        cozinhas: cozinhas,
        total_dormitorios: totalDormitorios,
        total_banheiros: totalBanheiros
      };

      const numerosMasc = {
        1:'UM', 2:'DOIS', 3:'TRÊS', 4:'QUATRO', 5:'CINCO', 6:'SEIS', 7:'SETE', 8:'OITO', 9:'NOVE', 10:'DEZ',
        11:'ONZE', 12:'DOZE', 13:'TREZE', 14:'QUATORZE', 15:'QUINZE', 16:'DEZESSEIS', 17:'DEZESSETE', 18:'DEZOITO', 19:'DEZENOVE', 20:'VINTE'
      };
      const numerosFem = { ...numerosMasc, 1:'UMA', 2:'DUAS' };
      const numExtenso = (n, fem) => (fem ? numerosFem[n] : numerosMasc[n]) || String(n);

      const ambientes = [];

      // 1. Dormitórios e Suítes
      if (totalDormitorios > 0) {
        let dormTexto = `${numExtenso(totalDormitorios, false)} ${totalDormitorios === 1 ? 'DORMITÓRIO' : 'DORMITÓRIOS'}`;
        if (suites > 0) {
          dormTexto += `, SENDO ${numExtenso(suites, true)} ${suites === 1 ? 'SUÍTE' : 'SUÍTES'}`;
        }
        ambientes.push(dormTexto);
      }

      // 2. Banheiros sociais
      if (banheirosSociais > 0) {
        ambientes.push(`${numExtenso(banheirosSociais, false)} ${banheirosSociais === 1 ? 'BANHEIRO SOCIAL' : 'BANHEIROS SOCIAIS'}`);
      }

      // 3. Salas
      if (salas > 0) {
        ambientes.push(`${numExtenso(salas, true)} ${salas === 1 ? 'SALA' : 'SALAS'}`);
      }

      // 4. Cozinhas
      if (cozinhas > 0) {
        ambientes.push(`${numExtenso(cozinhas, true)} ${cozinhas === 1 ? 'COZINHA' : 'COZINHAS'}`);
      }

      // 5. Ambientes extras
      const ambientesExtras = [];
      root.querySelectorAll('.public-extra-room').forEach((row) => {
        const nome = row.querySelector('[name*="_nome"]')?.value?.trim();
        const quantidade = Number(row.querySelector('[name*="_quantidade"]')?.value || 0);
        if (nome && quantidade > 0) {
          const qTexto = numExtenso(quantidade, false);
          ambientes.push(`${qTexto} ${String(nome).toUpperCase()}`);
          ambientesExtras.push({ nome: nome, quantidade: quantidade });
        }
      });

      const listaAmbientes = ambientes.length === 0
        ? 'AMBIENTES A INFORMAR'
        : (ambientes.length === 1
            ? ambientes[0]
            : `${ambientes.slice(0, -1).join(', ')} E ${ambientes[ambientes.length - 1]}`);

      const texto = `A EDIFICAÇÃO ${(selectValue('habite_uso') || 'USO NÃO INFORMADO').toUpperCase()} COM ${(selectValue('habite_pavimento') || 'PAVIMENTO NÃO INFORMADO').toUpperCase()} COM ÁREA CONSTRUÍDA DE ${v('area_construida') || 'ÁREA NÃO INFORMADA'} M². O TIPO DA CONSTRUÇÃO É UMA ${(selectValue('habite_tipo_construcao') || 'TIPO NÃO INFORMADO').toUpperCase()} COM PADRÃO CONSTRUTIVO ${(selectValue('habite_padrao') || 'NÃO INFORMADO').toUpperCase()}, ESTRUTURA EM ${(selectValue('habite_estrutura') || 'NÃO INFORMADA').toUpperCase()}, ESQUADRIAS DE PORTAS EM ${(selectValue('habite_portas') || 'NÃO INFORMADAS').toUpperCase()} E JANELAS EM ${(selectValue('habite_janelas') || 'NÃO INFORMADAS').toUpperCase()}, REVESTIMENTO DE PISO ${(selectValue('habite_piso') || 'NÃO INFORMADO').toUpperCase()}, REVESTIMENTO DAS PAREDES EM ${(selectValue('habite_paredes') || 'NÃO INFORMADO').toUpperCase()}, REVESTIMENTO SUPERIOR DE ${(selectValue('habite_forro') || 'NÃO INFORMADO').toUpperCase()} E COBERTURA COM ${(selectValue('habite_cobertura') || 'NÃO INFORMADA').toUpperCase()}. CONSTITUÍDO POR ${listaAmbientes}.`;

      if (!habiteManuallyEdited || force) {
        habiteOutput.value = texto;
        if (habiteEditStatus) {
          habiteEditStatus.textContent = 'Gerado automaticamente';
          habiteEditStatus.style.color = '#15803d';
        }
      }
      if (habiteAmbientesOutput) {
        const payload = { ...quantidades };
        if (ambientesExtras.length > 0) {
          payload.extras = ambientesExtras;
        }
        habiteAmbientesOutput.value = JSON.stringify(payload);
      }
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
    const clearOnInteract = (e) => {
      const target = e.target;
      if (target && target.classList?.contains('field-invalid')) {
        target.classList.remove('field-invalid');
        target.removeAttribute('aria-invalid');
        const host = target.closest('.form-toggle') || target.closest('.form-part-4') || target.closest('.public-habite-select-field') || target.parentElement;
        host?.querySelector(':scope > .field-error')?.remove();
      }
    };
    form.addEventListener('input', clearOnInteract);
    form.addEventListener('change', clearOnInteract);

    form.addEventListener('submit', function (e) {
      const validator = window.SEMA_PUBLIC_FORM?.validateStep;
      if (typeof validator === 'function') {
        const isDenuncia = document.getElementById('tipo_alvara')?.value === 'denuncia';
        if (isDenuncia) {
          if (!validator(1)) {
            e.preventDefault();
            return false;
          }
        } else {
          if (!validator(1)) {
            window.SEMA_PUBLIC_FORM?.showStep(1);
            e.preventDefault();
            return false;
          }
          if (!validator(2)) {
            window.SEMA_PUBLIC_FORM?.showStep(2);
            e.preventDefault();
            return false;
          }
          if (!validator(3)) {
            window.SEMA_PUBLIC_FORM?.showStep(3);
            e.preventDefault();
            return false;
          }
        }
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
