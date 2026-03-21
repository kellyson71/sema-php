# Visão Geral do Projeto - SEMA-PHP

## O que é o SEMA-PHP?

O **SEMA-PHP** é um sistema de gestão de requerimentos e processos para a **Secretaria Municipal de Meio Ambiente (SEMA)** da cidade de **Pau dos Ferros/RN**.

O sistema foi desenvolvido para digitalizar o fluxo de solicitação de alvarás, licenciamentos ambientais e denúncias, eliminando a necessidade de processos físicos e facilitando o acompanhamento tanto para o cidadão quanto para a administração pública.

## Objetivos Principais

- **Acessibilidade**: Permitir que o cidadão solicite alvarás de qualquer lugar.
- **Transparência**: Prover um histórico claro de todas as ações tomadas em um processo.
- **Eficiência**: Automatizar notificações por e-mail e geração de documentos (pareceres).
- **Segurança**: Implementar autenticação multifator (2FA) e assinatura digital para validade jurídica.

## Público-Alvo

1. **Cidadão/Requerente**: Pessoa física ou jurídica que necessita de autorização ambiental ou licença de construção.
2. **Administradores/Operadores**: Funcionários da SEMA que analisam os documentos e emitem pareceres.
3. **Secretário**: Responsável pela assinatura final e deferimento/indeferimento de processos críticos.

## Localização dos Arquivos Principais

- **Frontend do Cidadão**: `index.php` (Formulário de requerimento).
- **Painel Administrativo**: Pasta `admin/`.
- **Configurações de Regra de Negócio**: `tipos_alvara.php`.
