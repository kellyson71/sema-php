# Fluxo de assinatura digital (levantamento rapido)

## Onde o modal e definido
- `admin/visualizar_requerimento.php`: o card "Gerar Parecer Tecnico" abre o modal `#parecerModal`, que contem todo o wizard de assinatura.
- Nao ha outro arquivo web exibindo este modal; os handlers JS estao inline no mesmo PHP.

## Passo a passo no front (wizard do modal)
- Etapa 1: `abrirModalParecer()` mostra o modal e carrega templates via POST para `admin/parecer_handler.php` (`action: listar_templates`).
- Etapa 2: `carregarTemplateParaEdicao()` busca o HTML do template (`action: carregar_template`), preenche variaveis do requerimento e inicializa o TinyMCE para edicao.
- Etapa 3: `irParaAssinatura()` esconde o editor e exibe a aba de assinatura com duas opcoes:
  - `SignaturePad` em canvas para desenhar.
  - Assinatura digitada com rotacao de fontes; preview atualiza via `atualizarPreviewAssinatura()`.
  - Sempre pede senha do admin e opcao de memorizar na sessao; validacao feita via `action: validar_senha`.
- Etapa 4 (templates A4 ou licenca_previa_projeto): `prepararEtapaPosicionamento()` gera preview A4 com fundo do template, mostra bloco arrastavel (QR + nome/cargo) e armazena coordenadas em porcentagem. Confirmacao chama `confirmarPosicaoEGerarPdf()`.
- Etapa direta para outros templates: `validarEGerarPdf()` pula o posicionamento e ja envia para o handler de assinatura.

## Back-end que recebe a assinatura
- `admin/parecer_handler.php`:
  - Gera PDFs de parecer (`action: gerar_pdf`) sem assinatura.
  - Assina e gera PDF final (`action: gerar_pdf_com_assinatura` ou `_posicionada`), recebendo o HTML final, tipo de template, dados do admin, tipo/dados da assinatura e coordenadas quando houver.
  - Valida a senha do admin (`action: validar_senha`) usando `password_verify` na tabela `administradores`.
- `includes/assinatura_digital_service.php`:
  - Gera/parsa chaves RSA 2048 (`includes/keys/private.pem` e `public.pem`).
  - Calcula hash SHA-256 do PDF, assina com RSA, grava em `assinaturas_digitais` e salva JSON de metadados ao lado do PDF.
- `includes/qrcode_service.php`: cria QR code apontando para `consultar/verificar.php?id={documento_id}`.

## Saidas e armazenamento
- PDFs e JSONs ficam em `uploads/pareceres/{requerimento_id}/` com `documento_id` e hash registrados na tabela `assinaturas_digitais` (schema em `database/assinaturas_digitais.sql`).
- Visualizacao de documentos assinados: `admin/parecer_viewer.php` reconstrui o bloco `#area-assinatura` a partir do JSON/HTML salvo e verifica integridade.
- Docs anteriores sobre o tema: `docs/ASSINATURA_DIGITAL.md` e changelogs em `docs/changelog/*assinatura*`.

## Pontos de atencao/sugestoes iniciais
- Centralizar o JS do modal em arquivo dedicado para reduzir o PHP inline e facilitar testes.
- Garantir tratamento quando a chave RSA for recriada (avisar que assinaturas antigas nao validam com chaves novas).
- Cobrir multassinatura ou aprovacao em cadeia, caso seja requisito futuro.
- Adicionar logs/alertas UX para falhas de carregamento de template ou de geracao de QR na etapa de preview.
