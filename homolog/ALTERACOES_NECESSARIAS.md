# ALTERAÇÕES NECESSÁRIAS NO SISTEMA SEMA-PHP
## Baseado no Diário Oficial de Pau dos Ferros - 26/11/2025

---

## 📋 RESUMO EXECUTIVO

O Diário Oficial trouxe mudanças significativas nos tipos de licenças ambientais e nos documentos exigidos. O sistema atual precisa ser atualizado para refletir os **6 novos tipos de licenciamento** estabelecidos pela legislação municipal.

---

## 🔄 PRINCIPAIS MUDANÇAS

### 1. NOVOS TIPOS DE LICENCIAMENTO AMBIENTAL

O sistema atual possui apenas 3 tipos relacionados a licenciamento ambiental:
- ✅ Licença Prévia (LP/LI)
- ✅ Licença de Operação (LO)
- ❌ Licença de Instalação (LI) - **INCOMPLETO**

**NOVOS TIPOS QUE DEVEM SER ADICIONADOS:**

1. **Licença Ambiental Única (LAU)** ⭐ NOVO
2. **Licença de Ampliação (LA)** ⭐ NOVO
3. **Licença Operacional Corretiva (LOC)** ⭐ NOVO
4. **Licença por Adesão e Compromisso (LAC)** ⭐ NOVO

---

## 📝 ALTERAÇÕES DETALHADAS POR TIPO DE LICENÇA

### 1. LICENÇA PRÉVIA (LP/LI) - ATUALIZAR

**Arquivo:** `tipos_alvara.php` - linha 263-286

**Documentos ATUAIS (incompletos):**
```
1. Documento pessoal com foto e CPF/CNPJ do requerente
2. Documento pessoal com foto e CPF/CNPJ do proprietário
3. Comprovante de residência
4. Documento do terreno
5. ART ou RRT
6. Projetos arquitetônicos
7. Projetos complementares
```

**Documentos NOVOS (conforme legislação - páginas 97-98):**
```
✅ Requerimento de Licença - Modelo SEMA
✅ Documentos da Pessoa Física ou Jurídica
✅ Documento que comprove legalidade do uso da área (com firma reconhecida)
✅ Contrato de Arrendamento (quando aplicável)
✅ Certidão da Prefeitura Municipal (máx. 2 anos) OU Alvará de Localização
✅ Certidão do DNIT/DER-RN (para rodovias federais/estaduais)
✅ Memorial Descritivo da área
✅ Planta de localização georreferenciada (impressa + digital)
✅ Descrição do sistema de abastecimento de água + outorga preventiva
✅ Descrição de resíduos sólidos
✅ Cronograma físico de implantação
✅ ART de todos os projetos (engenharia e ambiental)
✅ Publicações do Pedido de Licença em Diário Oficial ⭐ NOVO
✅ Comprovante de pagamento (boleto quitado) ⭐ NOVO
✅ Estudo Ambiental (EIA/RIMA, PCA) ⭐ NOVO
```

---

### 2. LICENÇA AMBIENTAL ÚNICA (LAU) - CRIAR ⭐

**Arquivo:** `tipos_alvara.php` - **ADICIONAR NOVO ARRAY**

**Documentos necessários (páginas 99-100):**
```
1. Requerimento de Licença - Modelo SEMA
2. Documentos da Pessoa Física ou Jurídica
3. Procuração (quando aplicável)
4. Certidão da Prefeitura Municipal (máx. 2 anos) OU Alvará de Localização
5. Documento que comprove legalidade do uso da área (com firma reconhecida)
6. Contrato de Arrendamento (quando aplicável)
7. Planta de localização georreferenciada (impressa + digital)
8. Projeto do empreendimento + Memoriais Descritivos
9. Projeto completo de tratamento de esgoto sanitário
10. Descrição do Sistema de Abastecimento d'água
11. Descrição de resíduos sólidos
12. Plano de Controle Ambiental (PCA)
13. ARTs de todos os projetos
14. Cronograma físico de implantação
15. Publicações do Pedido de Licença
16. Comprovante de pagamento (boleto quitado)
```

**Observações importantes:**
- Todas as plantas devem ser dobradas no formato A4
- Não são aceitos desenhos esquemáticos feitos à mão livre
- Fotocópias devem estar autenticadas ou acompanhadas do original

---

### 3. LICENÇA DE OPERAÇÃO (LO) - ATUALIZAR

**Arquivo:** `tipos_alvara.php` - linha 287-296

**Documentos ATUAIS:**
```
"Entre em contato com a Secretaria..."
```

**Documentos NOVOS (conforme legislação - página 101):**
```
1. Requerimento de Licença - Modelo SEMA
2. Documentos da Pessoa Física ou Jurídica
3. Procuração (quando aplicável)
4. Licença Anterior ⭐ IMPORTANTE
5. Relatório de Atendimento a condicionantes da licença anterior ⭐ IMPORTANTE
6. Inscrição e regularidade no Cadastro Técnico Federal (CTF) ⭐ NOVO
7. Cópia da publicação do pedido de LO
8. Comprovante de pagamento (boleto quitado)
```

---

### 4. LICENÇA DE AMPLIAÇÃO (LA) - CRIAR ⭐

**Arquivo:** `tipos_alvara.php` - **ADICIONAR NOVO ARRAY**

**Documentos necessários (páginas 102-103):**
```
1. Requerimento de Licença - Modelo SEMA
2. Licença anterior
3. Documento que comprove legalidade do uso da área (com firma reconhecida)
4. Contrato de Arrendamento (quando aplicável)
5. Certidão da Prefeitura Municipal (máx. 2 anos) OU Alvará de Localização
6. Projeto do empreendimento referente à alteração/modificação/ampliação
7. Cronograma físico de implantação
8. ARTs de todos os projetos
9. Publicações do Pedido de Licença
10. Comprovante de pagamento (boleto quitado)
```

**Observações especiais:**
- Se a nova área não foi analisada na LP, apresentar todos os documentos da LP
- SEMA pode solicitar Estudo Ambiental (EIA/RIMA, RCA, RAS, PCA, PRAD)

---

### 5. LICENÇA OPERACIONAL CORRETIVA (LOC) - CRIAR ⭐

**Arquivo:** `tipos_alvara.php` - **ADICIONAR NOVO ARRAY**

**Documentos necessários (páginas 103-104):**
```
1. Requerimento de Licença - Modelo SEMA
2. Documentos da Pessoa Física ou Jurídica
3. Documento que comprove legalidade do uso da área (com firma reconhecida)
4. Planta de localização georreferenciada (impressa + digital)
5. Projeto do empreendimento e layout das instalações
6. Projeto completo de tratamento de esgoto sanitário
7. Descrição do Sistema de Abastecimento d'água
8. Descrição de resíduos sólidos
9. Relatório de Atendimento a Condicionantes
10. Relatório de Controle Ambiental
11. Plano de Controle Ambiental
12. ARTs de todos os projetos
13. Cronograma físico de implantação
14. Publicações do Pedido de Licença
```

---

### 6. LICENÇA POR ADESÃO E COMPROMISSO (LAC) - CRIAR ⭐

**Arquivo:** `tipos_alvara.php` - **ADICIONAR NOVO ARRAY**

**Documentos necessários (páginas 104-105):**
```
1. Requerimento de Licença - Modelo SEMA
2. Documentos da Pessoa Física ou Jurídica
3. Procuração (quando aplicável)
4. Certidão da Prefeitura Municipal (máx. 2 anos) OU Alvará de Localização
5. Documento que comprove legalidade do uso da área (com firma reconhecida)
6. Contrato de Arrendamento (quando aplicável)
7. Relatório de Caracterização do Empreendimento (RCE) ⭐ ESPECÍFICO
8. ARTs de todos os projetos
9. Comprovante de pagamento (boleto quitado)
```

**Nota:** Para atividades de caráter temporário ou sem instalações permanentes.

---

## 🔧 ALTERAÇÕES NO CÓDIGO

### 1. Atualizar `index.php`

**Linha 168-184:** Adicionar novos tipos de licença no `<select>`

```html
<!-- ADICIONAR APÓS linha 183 -->
<option value="licenca_ambiental_unica">Licença Ambiental Única (LAU)</option>
<option value="licenca_ampliacao">Licença de Ampliação (LA)</option>
<option value="licenca_operacional_corretiva">Licença Operacional Corretiva (LOC)</option>
<option value="licenca_adesao_compromisso">Licença por Adesão e Compromisso (LAC)</option>
```

### 2. Atualizar `tipos_alvara.php`

**Adicionar 4 novos arrays completos:**
- `licenca_ambiental_unica`
- `licenca_ampliacao`
- `licenca_operacional_corretiva`
- `licenca_adesao_compromisso`

**Atualizar arrays existentes:**
- `licenca_previa` (adicionar documentos faltantes)
- `licenca_operacao` (substituir mensagem genérica por lista completa)

### 3. Criar novos campos no banco de dados

**Tabela `requerimentos`** - Adicionar colunas:
```sql
ALTER TABLE requerimentos ADD COLUMN ctf_numero VARCHAR(50) NULL COMMENT 'Cadastro Técnico Federal';
ALTER TABLE requerimentos ADD COLUMN licenca_anterior_numero VARCHAR(50) NULL COMMENT 'Número da licença anterior';
ALTER TABLE requerimentos ADD COLUMN possui_estudo_ambiental BOOLEAN DEFAULT FALSE;
ALTER TABLE requerimentos ADD COLUMN tipo_estudo_ambiental VARCHAR(50) NULL COMMENT 'EIA/RIMA, PCA, etc';
```

### 4. Atualizar formulário de upload

**Novos documentos obrigatórios para TODOS os tipos:**
- ✅ Publicação em Diário Oficial
- ✅ Comprovante de pagamento (boleto)
- ✅ Certidão da Prefeitura Municipal (máx. 2 anos)

### 5. Validações adicionais

**Implementar validações:**
- Verificar data da Certidão Municipal (não pode ter mais de 2 anos)
- Validar formato de ARTs/RRTs
- Verificar se fotocópias estão autenticadas
- Validar plantas no formato A4

---

## 📄 DOCUMENTOS NÃO-TÉCNICOS (página 106)

**Atualizar validação de documentos pessoais:**

**Pessoa Física:**
- CPF e Carteira de Identidade
- Se estrangeiro: Carteira de Identidade de Estrangeiro (Polícia Federal)

**Pessoa Jurídica:**
- CNPJ
- Ato Constitutivo registrado na Junta Comercial
- RG e CPF dos sócios
- Comprovante de endereço pessoal e da empresa

**Procuração:**
- Instrumento público OU particular com firma reconhecida
- Cópia dos documentos do procurador

**Responsáveis Técnicos:**
- Cópias dos CPFs
- Registros nos Conselhos de Classe
- ARTs/RRTs devidamente registradas

---

## ⚠️ OBSERVAÇÕES IMPORTANTES

### Regras Gerais (aplicam-se a TODOS os tipos):

1. **ARTs/RRTs obrigatórias** para todos os projetos e estudos
2. **Plantas dobradas no formato A4** - não aceitar desenhos à mão livre
3. **Fotocópias autenticadas** ou acompanhadas do original
4. **SEMA pode solicitar documentos adicionais** a qualquer momento
5. **Certidão Municipal válida por 2 anos** no máximo
6. **Publicação em Diário Oficial** obrigatória
7. **Comprovante de pagamento** obrigatório

### Atualizações de Valores:

- Preços atualizados pelo **IPCA acumulado nos últimos 12 meses**
- Renovações de LO e LAU: **mesmo valor da licença original**
- Ampliações: **novo processo de licenciamento completo**
- Desconto de **50% para obras de resíduos sólidos** (entidades privadas)

---

## 🎯 PRIORIDADES DE IMPLEMENTAÇÃO

### ALTA PRIORIDADE:
1. ✅ Adicionar os 4 novos tipos de licença no sistema
2. ✅ Atualizar documentação da LP e LO
3. ✅ Implementar validação de Certidão Municipal (2 anos)
4. ✅ Adicionar campos para publicação e comprovante de pagamento

### MÉDIA PRIORIDADE:
5. ✅ Criar campos no banco para CTF e licença anterior
6. ✅ Implementar validação de ARTs/RRTs
7. ✅ Adicionar observações sobre autenticação de documentos

### BAIXA PRIORIDADE:
8. ✅ Implementar sistema de cálculo de taxas com IPCA
9. ✅ Criar alertas para documentos com prazo de validade
10. ✅ Gerar relatórios de conformidade documental

---

## 📊 IMPACTO NO SISTEMA

**Arquivos que precisam ser modificados:**
- ✏️ `index.php` (adicionar opções no select)
- ✏️ `tipos_alvara.php` (adicionar 4 novos arrays + atualizar 2 existentes)
- ✏️ `processar_formulario.php` (validar novos campos)
- ✏️ `database/schema.sql` (adicionar novas colunas)
- ✏️ `admin/visualizar_requerimento.php` (exibir novos campos)

**Estimativa de esforço:**
- Desenvolvimento: 8-12 horas
- Testes: 4-6 horas
- Documentação: 2-3 horas
- **Total: 14-21 horas**

---

## 🔗 REFERÊNCIAS

- Diário Oficial do Município de Pau dos Ferros - 26/11/2025 (páginas 97-106)
- Lei Complementar nº 380, de 26.12.2008
- Lei Complementar nº 336, de 12.12.2006
- Resolução nº 02/2014 do CONEMA

---

**Data do documento:** 05/12/2025  
**Responsável pela análise:** Sistema SEMA-PHP  
**Status:** Aguardando implementação
