# Funcionalidade de Arquivamento de Requerimentos

## Descrição

Esta funcionalidade permite "excluir" requerimentos de forma segura, movendo-os para um arquivo separado ao invés de deletá-los permanentemente do banco de dados.

## Como Funciona

### 1. Arquivamento de Requerimentos

#### Onde encontrar:
- Na página de visualização de requerimento (`admin/visualizar_requerimento.php`)
- Disponível para todos os status de requerimento
- Botão "Arquivar Processo" na seção de ações administrativas

#### Como usar:
1. Acesse um requerimento específico
2. Localize a seção "Ações Administrativas"
3. Preencha o motivo do arquivamento no campo "Motivo do Arquivamento"
4. Clique em "Arquivar Processo"
5. Confirme a ação no modal que aparece

#### O que acontece:
- O requerimento é copiado para a tabela `requerimentos_arquivados`
- Todos os dados são preservados (requerente, proprietário, documentos, etc.)
- O processo é removido da lista principal de requerimentos
- Um registro é criado no histórico de ações
- O usuário é redirecionado para a lista principal com mensagem de sucesso

### 2. Visualização de Requerimentos Arquivados

#### Onde encontrar:
- Menu lateral: "Arquivados"
- URL direta: `admin/requerimentos_arquivados.php`

#### Funcionalidades:
- Listagem de todos os requerimentos arquivados
- Filtros por status, tipo de alvará e busca por texto
- Estatísticas dos arquivados
- Paginação
- Visualização de detalhes via modal
- Restauração de processos

### 3. Restauração de Requerimentos

#### Como usar:
1. Acesse a página "Arquivados"
2. Localize o requerimento desejado
3. Clique no botão de "Restaurar" (ícone de desfazer)
4. Confirme a ação

#### O que acontece:
- O requerimento é recriado na tabela principal
- Requerente e proprietário são restaurados ou recriados se necessário
- Um novo registro é criado no histórico
- O processo é removido da tabela de arquivados
- Redirecionamento com mensagem de sucesso

## Estrutura Técnica

### Tabela de Arquivados

```sql
requerimentos_arquivados:
- id (auto increment)
- requerimento_id (ID original)
- protocolo
- tipo_alvara
- requerente_id
- proprietario_id
- endereco_objetivo
- status
- observacoes
- data_envio
- data_atualizacao
- data_arquivamento (timestamp)
- admin_arquivamento (quem arquivou)
- motivo_arquivamento (motivo informado)
- requerente_nome (dados desnormalizados)
- requerente_email
- requerente_cpf_cnpj
- requerente_telefone
- proprietario_nome
- proprietario_cpf_cnpj
```

### Arquivos Modificados/Criados

#### Novos Arquivos:
- `database/criar_tabela_arquivados.sql` - Script para criar a tabela
- `admin/requerimentos_arquivados.php` - Página de listagem
- `admin/ajax/detalhes_arquivado.php` - Modal de detalhes
- `admin/ajax/restaurar_processo.php` - Script de restauração
- `docs/FUNCIONALIDADE_ARQUIVAMENTO.md` - Esta documentação

#### Arquivos Modificados:
- `admin/visualizar_requerimento.php` - Adicionado botão e lógica de arquivamento
- `admin/header.php` - Adicionado link no menu lateral
- `admin/requerimentos.php` - Adicionada mensagem de sucesso

## Recursos de Segurança

### 1. Preservação de Dados
- Todos os dados são copiados antes da exclusão
- Dados desnormalizados para garantir integridade
- Histórico de quem e quando arquivou

### 2. Validações
- Verificação de permissões de administrador
- Validação de motivo obrigatório
- Confirmação via modal antes da ação

### 3. Recuperação
- Possibilidade de restaurar processos arquivados
- Recriação automática de relacionamentos se necessário
- Prevenção de duplicação de protocolos

## Vantagens

1. **Segurança**: Dados nunca são perdidos permanentemente
2. **Organização**: Lista principal fica mais limpa
3. **Auditoria**: Histórico completo de arquivamentos
4. **Flexibilidade**: Possibilidade de restaurar se necessário
5. **Performance**: Menos registros na tabela principal

## Instalação

1. Execute o script SQL:
```bash
mysql -u usuario -p sema_db < database/criar_tabela_arquivados.sql
```

2. Verifique se todos os arquivos foram criados/modificados corretamente

3. Teste a funcionalidade:
   - Acesse um requerimento
   - Teste o arquivamento
   - Verifique na página de arquivados
   - Teste a restauração

## Considerações

- O arquivamento não afeta documentos físicos uploaded
- Processo arquivado não aparece em relatórios normais
- Administradores podem ver e restaurar qualquer processo arquivado
- Recomenda-se criar backup antes de usar em produção

---

**Data de Implementação**: 16/01/2025  
**Versão**: 1.0  
**Autor**: Sistema SEMA 