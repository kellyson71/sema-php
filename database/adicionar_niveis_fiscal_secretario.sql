-- Migração: Adicionar novos níveis de usuário para Fiscal de Serviços Urbanos
-- Data: 2026-03-06
-- Descrição: Expande o ENUM 'nivel' na tabela administradores para incluir
--            os papéis 'analista', 'fiscal' e 'secretario' conforme o
--            novo fluxo de trabalho de 3 setores do sistema SEMA-PHP.

ALTER TABLE administradores
    MODIFY COLUMN nivel ENUM('admin', 'operador', 'analista', 'fiscal', 'secretario') DEFAULT 'operador';
