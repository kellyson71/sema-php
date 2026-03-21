# Fluxo de Assinatura Digital (Novo Sistema)

Este documento detalha o funcionamento técnico do novo módulo de **Assinatura Digital** do sistema SEMA-PHP, implementado para garantir maior agilidade, flexibilidade na visualização e segurança jurídica.

## 1. Geração e Persistência de Documentos

O novo fluxo abandona a dependência exclusiva de binários PDF imediatos, priorizando a persistência do conteúdo fonte:

- **Templates HTML**: Os documentos são gerados a partir de templates HTML (localizados em `admin/templates/`), permitindo customização rica via CSS.
- **Armazenamento Híbrido**:
    - **Banco de Dados**: A tabela `assinaturas_digitais` armazena os metadados (quem assinou, cargo, CPF, hash do conteúdo, timestamp e IP).
    - **Sistema de Arquivos**: O conteúdo HTML assinado é salvo em disco (ex: `admin/pareceres/{id_requerimento}/...html`). Isso permite que o documento seja re-gerado fielmente a qualquer momento.
- **Redundância JSON**: Cada documento gera um arquivo `.json` lateral contendo o "snapshot" completo do HTML e das assinaturas, garantindo que o documento possa ser recuperado mesmo em falhas de banco de dados.

## 2. O Processo de Assinatura

Quando um administrador (Técnico ou Secretário) assina um documento:

1. **Captura de Dados**: O sistema captura o conteúdo atual do editor e os dados do assinante da sessão.
2. **Criptografia**: É gerado um hash SHA-256 do conteúdo.
3. **Registro**: Uma entrada é criada em `assinaturas_digitais`. O `documento_id` (UUID único) é gerado para identificar este ato administrativo.
4. **QR Code**: (Opcional) Se a biblioteca estiver disponível, um QR Code é gerado apontando para `/consultar/verificar.php?id={documento_id}` para validação pública.

## 3. Visualização Dinâmica (`parecer_viewer.php`)

O visualizador foi otimizado para velocidade e compatibilidade:

- **Casca Iframe**: O visualizador agora é uma interface leve (Shell) que utiliza um `iframe` para carregar o documento.
- **Renderização Inline**: O `iframe` chama o script `redownload_pdf.php?id={id}&inline=1`.
- **Leitor Nativo**: O sistema detecta se o arquivo em disco é um PDF ou HTML. Se for PDF, ele instrui o navegador a usar o plugin nativo (Chrome/Safari/Firefox), garantindo perfeição visual e rapidez.

## 4. Gestão e Controle (`visualizar_requerimento.php`)

Os documentos assinados são gerenciados diretamente na tela do processo:

- **Listagem de Ações Administrativas**: Todos os pareceres e alvarás associados ao protocolo aparecem listados com:
    - **Ícone de Visualização**: Abre o visualizador dinâmico.
    - **Ícone de Download**: Força o download do PDF oficial (gerado sob demanda se necessário).
    - **Exclusão Segura**: Permite remover um parecer, limpando automaticamente o registro no BD e os arquivos físicos associados (`.html`, `.json`, `.pdf`).

## 5. Script de Entrega (`redownload_pdf.php`)

Este é o motor central de distribuição:

- **Resolução de Caminhos**: Possui lógica inteligente para encontrar arquivos mesmo se o sistema de pastas for alterado (procura na raiz, em `admin/`, etc).
- **Geração On-the-fly**: Se apenas o HTML estiver disponível, ele utiliza a biblioteca de PDF para gerar o binário assinado no momento do download, garantindo que o usuário sempre receba um PDF pronto para baixar.

---

_Documentação atualizada em Março de 2026._
