-- Migration: situação da denúncia padronizada (2026-09-25)
-- A coluna era varchar livre. Normaliza variações antigas e passa a aceitar só os
-- três valores usados pelo painel (constante DENUNCIA_SITUACOES em includes/denuncia_filters.php).

UPDATE denuncias SET status = 'Pendente'
 WHERE status IS NULL OR TRIM(status) = '' OR LOWER(TRIM(status)) = 'pendente';

UPDATE denuncias SET status = 'Em Análise'
 WHERE LOWER(TRIM(status)) IN ('em análise', 'em analise', 'em_analise');

UPDATE denuncias SET status = 'Concluída'
 WHERE LOWER(TRIM(status)) IN ('concluída', 'concluida', 'concluído', 'concluido', 'finalizado', 'finalizada');

ALTER TABLE denuncias
  MODIFY status ENUM('Pendente', 'Em Análise', 'Concluída') NOT NULL DEFAULT 'Pendente';
