# Validação das Mudanças no Licenciamento Ambiental

Documento de consolidação das mudanças informadas para o licenciamento ambiental da SEMA, com foco em validação com o cliente e tradução direta para implementação no sistema.

## 1. Validação da Estrutura

As mudanças estão corretas em conteúdo, mas o agrupamento pode ficar mais claro se separado por regra de negócio:

- **Enquadramento e competência**
  - Consulta prévia
  - Classificação por porte e tipo
  - Encaminhamento ao IDEMA
- **Cadastro da solicitação**
  - Localização do empreendimento
- **Tipos de licença**
  - Tipos mantidos
  - Tipos removidos
- **Documentação e formalização**
  - Requerimento padrão
  - Assinatura física ou digital
- **Upload e protocolo**
  - Limite de arquivos
  - Anexos complementares
  - Pagamento do boleto
- **Documentos gerados pelo sistema**
  - Geração automática
  - Timbres e modelos

## 2. Pontos que Provavelmente Estão Faltando

- Definição da **base legal exata** usada no enquadramento: número da resolução, tabela e data de vigência.
- Regra de **transição para processos antigos** já abertos em tipos que serão removidos.
- Definição de **quais tipos permanecem disponíveis** após a retirada de `LP` isolada e `LI/LO`.
- Critério para **bloqueio ou apenas orientação** na consulta de enquadramento.
- Regra de **tratamento para estudos acima de 10MB**: upload ampliado, fracionamento, link externo ou entrega fora do sistema.
- Regra de **quem pode anexar arquivos em respostas**: requerente, analista ou ambos.
- Critério de **momento do bloqueio por pagamento**: protocolo inicial, resposta de exigência ou ambos.
- Lista de **documentos que serão gerados automaticamente**.
- Definição do formato da **localização**: link do Google Maps, coordenadas ou ambos.

## 3. Ambiguidades para Confirmar com o Cliente

- `Uso de tabela da resolução vigente`: confirmar qual resolução será a referência oficial.
- `Campo opcional` de localização: confirmar se é opcional para todos os tipos ou apenas em casos específicos.
- `Remoção de LP (isolada)`: confirmar se será removida do cadastro novo, ocultada na interface ou extinta juridicamente.
- `Remoção de LI/LO`: confirmar se vale apenas para novos protocolos ou também para processos em andamento.
- `Assinatura física ou digital`: confirmar se é escolha livre ou se depende do tipo de licença.
- `Estudos ambientais acima de 10MB`: confirmar se o sistema passará a aceitar arquivos maiores ou apenas instruirá outro meio de envio.
- `Permitir anexos em respostas`: confirmar em quais etapas e para quais perfis.
- `Envio condicionado ao pagamento do boleto`: confirmar se o bloqueio ocorre antes do protocolo ou antes da análise.
- `Geração automática de documentos`: confirmar quais documentos entram nessa regra.
- `Atualização de timbres e modelos`: confirmar quais modelos mudam e se há texto normativo novo.

## 4. Tradução para Sistema

### 4.1 Consulta de Enquadramento
- **Descrição**
  - Incluir etapa de consulta antes do licenciamento.
  - Classificar o empreendimento por tipo e porte.
  - Aplicar a tabela da resolução vigente.
  - Encaminhar ao IDEMA quando ultrapassar o limite municipal.
- **Impacto no sistema**
  - **Frontend**: tela ou bloco inicial com perguntas de enquadramento e resultado visível.
  - **Backend**: regra de classificação, validação de limite municipal e bloqueio/aviso de encaminhamento.
  - **Banco**: campos para tipo, porte, resultado do enquadramento e indicação de competência municipal/estadual.
  - **Fluxo**: passa a existir uma etapa anterior ao protocolo do licenciamento.
- **Obrigatoriedade**
  - **Obrigatório**, se a consulta passar a ser exigência formal antes do protocolo.
  - **Depende de validação** para definir a tabela oficial e se o resultado bloqueia ou apenas orienta.
- **Prioridade**
  - **Essencial**
- **Pendente de confirmação**
  - Qual resolução será usada.
  - Se o resultado da consulta impede abertura do processo municipal.

### 4.2 Localização do Empreendimento
- **Descrição**
  - Incluir campo de localização com referência do Google Maps.
- **Impacto no sistema**
  - **Frontend**: novo campo no formulário.
  - **Backend**: validação do formato informado.
  - **Banco**: nova coluna para link ou coordenadas.
  - **Fluxo**: sem mudança relevante, apenas enriquecimento do cadastro.
- **Obrigatoriedade**
  - **Depende de validação**, porque o levantamento informa que o campo é opcional.
- **Prioridade**
  - **Importante**
- **Pendente de confirmação**
  - Se será aceito link, coordenada ou ambos.
  - Em quais tipos o campo será obrigatório ou opcional.

### 4.3 Tipos de Licença
- **Descrição**
  - Remover `LP` isolada.
  - Remover `LI/LO`.
- **Impacto no sistema**
  - **Frontend**: retirar opções do formulário para novos pedidos.
  - **Backend**: impedir criação de novos protocolos nesses tipos.
  - **Banco**: sem necessidade obrigatória de nova estrutura, mas pode exigir marcação de legado.
  - **Fluxo**: altera os tipos aceitos para abertura de processo.
- **Obrigatoriedade**
  - **Obrigatório**, se a mudança já estiver decidida.
  - **Depende de validação** para regra de transição dos processos antigos.
- **Prioridade**
  - **Essencial**
- **Pendente de confirmação**
  - O que acontece com protocolos já existentes nesses tipos.
  - Quais tipos substituem oficialmente os removidos.

### 4.4 Documentação e Assinatura
- **Descrição**
  - Tornar obrigatório o requerimento padrão.
  - Aceitar assinatura física ou digital.
- **Impacto no sistema**
  - **Frontend**: informar o documento obrigatório e o tipo de assinatura aceito.
  - **Backend**: validar presença do requerimento e regra da assinatura.
  - **Banco**: pode exigir campo para tipo de assinatura ou status do documento assinado.
  - **Fluxo**: o protocolo só segue se a documentação mínima estiver completa.
- **Obrigatoriedade**
  - **Obrigatório** para o requerimento padrão.
  - **Depende de validação** para critério de aceite da assinatura física ou digital.
- **Prioridade**
  - **Essencial**
- **Pendente de confirmação**
  - Se a assinatura física exige upload do documento assinado.
  - Se todos os tipos aceitam assinatura digital.

### 4.5 Upload de Arquivos
- **Descrição**
  - Tratar estudos ambientais acima de 10MB.
  - Permitir anexos em respostas.
  - Condicionar envio ao pagamento do boleto.
- **Impacto no sistema**
  - **Frontend**: mensagens de limite, área de anexos complementares e bloqueio visual sem pagamento.
  - **Backend**: validação de tamanho, controle de anexos por etapa e bloqueio de envio sem quitação.
  - **Banco**: vínculo de anexos complementares, marcação de pagamento e registro do tipo de anexo.
  - **Fluxo**: o protocolo e as respostas passam a depender de novas validações.
- **Obrigatoriedade**
  - **Obrigatório** para a regra de pagamento, se já for decisão fechada.
  - **Depende de validação** para estudos acima de 10MB e anexos em respostas.
- **Prioridade**
  - **Pagamento do boleto**: **Essencial**
  - **Anexos em respostas**: **Importante**
  - **Estudos acima de 10MB**: **Importante**
- **Pendente de confirmação**
  - Se o limite de 10MB será ampliado ou mantido com exceção operacional.
  - Quem pode anexar respostas e em qual etapa.
  - Em que momento o boleto quitado passa a ser obrigatório.

### 4.6 Geração de Documentos
- **Descrição**
  - Gerar documentos automaticamente.
  - Atualizar timbres e modelos.
- **Impacto no sistema**
  - **Frontend**: exibição dos modelos corretos e eventual acionamento automático.
  - **Backend**: seleção do template certo e preenchimento automático de variáveis.
  - **Banco**: em regra sem impacto estrutural obrigatório, salvo versionamento de modelo.
  - **Fluxo**: padroniza a emissão documental.
- **Obrigatoriedade**
  - **Depende de validação** para saber quais documentos entram em automação.
  - **Obrigatório** para atualização de timbres e modelos se a identidade oficial já mudou.
- **Prioridade**
  - **Geração automática**: **Importante**
  - **Timbres e modelos**: **Pode esperar**, salvo exigência legal imediata
- **Pendente de confirmação**
  - Quais documentos serão automáticos.
  - Quais modelos e timbres precisam ser substituídos.

## 5. Classificação Final de Prioridade

- **Essencial**
  - Consulta de enquadramento
  - Encaminhamento ao IDEMA por limite de competência
  - Remoção de `LP` isolada
  - Remoção de `LI/LO`
  - Requerimento padrão obrigatório
  - Bloqueio de envio sem pagamento
- **Importante**
  - Campo de localização
  - Aceite de assinatura física ou digital
  - Anexos em respostas
  - Tratamento para estudos acima de 10MB
  - Geração automática de documentos
- **Pode esperar**
  - Atualização visual de timbres
  - Ajustes de modelos sem mudança de regra legal

## 6. Lista Final Limpa para Validação com o Cliente

### Atualizações no Fluxo de Licenciamento Ambiental

#### 1. Consulta de Enquadramento
- Inclusão de consulta prévia antes da abertura do licenciamento.
- Classificação do empreendimento por tipo e porte, com base na tabela da resolução vigente.
- Definição automática da competência municipal ou encaminhamento ao IDEMA quando ultrapassar o limite local.
- **Impacto no sistema**: frontend, backend, banco e fluxo.
- **Status**: obrigatório, com pendência de validação da norma e da regra de bloqueio.
- **Prioridade**: essencial.
- **Confirmar com o cliente**: qual resolução será usada e se a consulta bloqueia a abertura do processo.

#### 2. Localização do Empreendimento
- Inclusão de campo de localização do empreendimento com referência do Google Maps.
- Definição da regra de uso do campo como obrigatório ou opcional, conforme o tipo de solicitação.
- **Impacto no sistema**: frontend, backend e banco.
- **Status**: depende de validação.
- **Prioridade**: importante.
- **Confirmar com o cliente**: formato aceito e regra de obrigatoriedade.

#### 3. Tipos de Licença
- Retirada da `LP` isolada para novos protocolos.
- Retirada da `LI/LO` para novos protocolos.
- Definição de tratamento para processos antigos já cadastrados nesses tipos.
- **Impacto no sistema**: frontend, backend e fluxo.
- **Status**: obrigatório, com pendência de validação da regra de transição.
- **Prioridade**: essencial.
- **Confirmar com o cliente**: quais tipos substituem os removidos e como ficam os processos legados.

#### 4. Documentação e Assinatura
- Exigência de requerimento padrão como documento obrigatório.
- Aceite de assinatura física ou digital, conforme regra a ser confirmada.
- **Impacto no sistema**: frontend, backend, banco e fluxo.
- **Status**: requerimento obrigatório; assinatura depende de validação.
- **Prioridade**: essencial.
- **Confirmar com o cliente**: regra de aceite da assinatura e necessidade de upload do documento assinado.

#### 5. Upload de Arquivos
- Definição de tratamento específico para estudos ambientais com tamanho acima de 10MB.
- Permissão de anexar arquivos em respostas e complementações.
- Bloqueio do envio do processo enquanto o boleto não estiver pago.
- **Impacto no sistema**: frontend, backend, banco e fluxo.
- **Status**: pagamento obrigatório; demais itens dependem de validação.
- **Prioridade**: essencial para pagamento; importante para os demais.
- **Confirmar com o cliente**: limite de arquivo, forma de envio excepcional e perfis autorizados a anexar respostas.

#### 6. Geração de Documentos
- Geração automática dos documentos definidos pela SEMA.
- Atualização de timbres e modelos oficiais.
- **Impacto no sistema**: frontend, backend e fluxo.
- **Status**: depende de validação, exceto se a troca de modelo já for determinação formal.
- **Prioridade**: importante para automação; pode esperar para ajuste visual sem efeito legal.
- **Confirmar com o cliente**: quais documentos serão automáticos e quais modelos devem ser atualizados.

## 7. Observações de Validação

- O sistema atual já possui tipos ambientais cadastrados, inclusive `LP`, `LI/LO`, `LO`, `LAU`, `LA` e `LOC`.
- O sistema atual já trabalha com limite de arquivo de `10MB`.
- O sistema atual já possui geração documental, templates e assinatura digital.
- Por isso, parte das mudanças não é criação do zero; em alguns casos é revisão de regra, bloqueio de uso ou ajuste de validação.
