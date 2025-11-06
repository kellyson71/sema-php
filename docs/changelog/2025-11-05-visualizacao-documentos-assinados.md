# 2025-11-05 - Visualização de Documentos Assinados

## O que foi implementado

Nova página administrativa para visualizar e gerenciar todos os documentos assinados digitalmente no sistema.

## Mudanças Principais

### 1. Nova Página de Documentos Assinados

- Arquivo `admin/documentos_assinados.php` criado
- Lista completa de todos os documentos assinados
- Exibe informações rápidas: quem assinou, CPF, cargo, data/hora
- Mostra tipo de assinatura (desenho ou texto)
- Exibe protocolo do requerimento relacionado quando disponível
- **Mostra nome do requerente** que enviou o protocolo relacionado ao documento assinado

### 2. Design Profissional e Moderno

- Interface com cards ao invés de tabela tradicional
- Layout responsivo e adaptável a diferentes tamanhos de tela
- Efeitos hover suaves nos cards
- Gradientes e cores profissionais
- Avatar com inicial do assinante
- Badges visuais para protocolo e tipo de assinatura
- Headers com gradiente usando cores do tema

### 3. Funcionalidades de Busca e Filtro

- Campo de busca por ID do documento, protocolo, requerente, assinante ou nome do arquivo
- Filtro por assinante específico
- Paginação com 25 itens por página
- Limpeza rápida de filtros
- Contador visual de documentos

### 4. Integração com Visualizador

- Botão "Visualizar Documento" que abre o documento no `parecer_viewer.php`
- Abre em nova aba para visualização e impressão
- Link direto para o requerimento relacionado quando disponível
- Botão "Verificar Autenticidade" que abre a página pública de verificação

### 5. Informações Detalhadas

Cada card exibe:

- ID único do documento (truncado para visualização)
- Protocolo do requerimento com badge destacado
- Nome do requerente que enviou o protocolo
- Informações do assinante (nome, CPF, cargo) com avatar
- Data e hora da assinatura
- Tipo de documento
- Tipo de assinatura (desenho ou texto) com badges distintos

### 6. Menu Lateral Atualizado

- Nova opção na sidebar: "Documentos Assinados"
- Ícone `fa-file-signature` para identificação visual
- Título no topbar atualizado automaticamente

## Arquivos Modificados

1. `admin/header.php` - Adicionado item no menu lateral
2. `admin/documentos_assinados.php` - Novo arquivo criado com design profissional

## Estrutura da Listagem

Cada documento é exibido em um card contendo:

- Header com ID do documento e badges de protocolo/tipo
- Grid de informações (Requerente, Data, Tipo de Documento)
- Seção do assinante com avatar e detalhes
- Área de ações com botões para visualizar, ver requerimento e verificar autenticidade

## Benefícios

- Acesso rápido a todos os documentos assinados
- Interface moderna e profissional
- Facilita auditoria e rastreabilidade
- Integração completa com o sistema existente
- Visualização clara do requerente relacionado a cada documento
- Design responsivo para uso em diferentes dispositivos
