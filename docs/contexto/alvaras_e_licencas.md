# Alvarás e Licenciamentos

Este documento detalha as regras de negócio para cada tipo de solicitação disponível no sistema, conforme definido no arquivo dinâmico `tipos_alvara.php`.

## Estrutura de Tipos

Atualmente o sistema processa os seguintes tipos de documentos:

### 🏗️ Construção e Habitação

- **Alvará de Construção, Reforma e/ou Ampliação**: Focado em obras civis.
- **Alvará de Habite-se e Legalização**: Para regularização de obras concluídas.
- **Alvará de Habite-se**: Para obras com alvará de construção vigente.

### 🏢 Comercial e Serviços

- **Alvará de Funcionamento**: Para estabelecimentos comerciais (PF e PJ).
- **Alvará Próvisório para Parques e Circos**: Requer ARTs específicas de segurança.
- **Licenciamento de Transporte**: Alternativo e Escolar (exige certidões criminais e negativas).

### 📐 Urbanismo

- **Alvará de Desmembramento e Remembramento**: Divisão ou união de lotes.
- **Alvará de Demolição**: Para remoção de estruturas existentes.
- **Alvará de Loteamento**: Regras complexas que envolvem IDEMA, COSERN e CAERN.
- **Certidão de Uso e Ocupação do Solo**: Para fins de licenciamento estadual (IDEMA).

### 🌿 Ambiental (Licenças)

- **LP (Licença Prévia)**: Fase de planejamento.
- **LP/LI (Instalação)**: Planejamento + Autorização de início de obra.
- **LI/LO (Operação)**: Autorização para funcionamento após instalação.
- **LO (Operação)**: Renovação ou licença de operação direta.
- **LAU (Licença Ambiental Única)**: Para empreendimentos de baixo impacto.
- **LOC (Licença Operacional Corretiva)**: Para regularizar atividades já em curso.
- **Autorização de Supressão Vegetal**: Corte de árvores ou limpeza de terreno.

## Regras de Documentação

- **Limites**: Nenhum arquivo enviado pode ultrapassar **10MB**.
- **Obrigatoriedade**: O sistema valida campos obrigatórios com base no tipo selecionado.
- **Relacionamentos**: Proprietários e Requerentes podem ser a mesma pessoa ou distintas, o sistema trata ambos os cadastros.
