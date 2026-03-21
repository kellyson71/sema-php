# Análise do Fluxo Administrativo Atual

Este documento detalha o funcionamento interno do sistema SEMA-PHP, desde o recebimento de um protocolo até a finalização pelo Secretário.

## 1. Entrada do Protocolo (Cidadão)

O fluxo inicia quando um munícipe preenche o formulário em `index.php`.

- Um registro é criado na tabela `requerimentos` com o status inicial **"Pendente"**.
- O sistema gera um número de protocolo único para acompanhamento.

## 2. Triagem e Visualização (Painel Admin)

Os administradores/operadores visualizam os novos pedidos em `admin/requerimentos.php`.

- Ao abrir um requerimento pela primeira vez (`visualizar_requerimento.php`), o sistema marca o campo `visualizado = 1`.
- O status geralmente é alterado manualmente para **"Em análise"**.

## 3. Ações Administrativas Disponíveis

Na tela de visualização do requerimento, as seguintes ações estão disponíveis:

- **Atualizar Status**: Permite mover o processo entre Pendente, Em análise, Aprovado, Reprovado, Cancelado, etc.
- **Indeferir Processo**: Finaliza o processo negativamente e envia e-mail automático ao requerente via `EmailService`.
- **Gerar Documento**: Direciona para `gerar_documento.php`, onde o técnico preenche um template (Parecer/Alvará) e realiza a primeira assinatura (técnica).
- **Apto a gerar alvará**: Status que indica que a parte técnica foi concluída e o documento está aguardando o Secretário.

## 4. Fluxo do Secretário (Aprovação Final)

O Secretário possui uma interface dedicada em `admin/secretario_dashboard.php`.

- **Filtro**: Ele visualiza apenas processos com status **"Apto a gerar alvará"** (pendentes) ou **"Alvará Emitido"** (concluídos).
- **Revisão**: Em `admin/revisao_secretario.php`, o Secretário visualiza o PDF gerado pelo técnico.
- **Assinatura**: O Secretário aplica sua assinatura digital. Isso altera o status para **"Alvará Emitido"**.
- **Envio de Protocolo**: Após a emissão, o operador pode enviar o "Protocolo Oficial da Prefeitura" via e-mail, o que marca o processo como **"Finalizado"**.

---

_Documentação gerada para suprir a necessidade de análise de fluxo solicitada pelo usuário._
