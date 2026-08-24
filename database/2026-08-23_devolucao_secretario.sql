-- Devolução do Secretário (Setor 3 → Setor 2).
--
-- Estas duas colunas já eram lidas e gravadas pelo código —
-- admin/fluxo_setor_handler.php grava o motivo, admin/requerimentos.php e
-- admin/visualizar_requerimento.php leem — mas nunca existiu migration que as
-- criasse. Em qualquer banco montado a partir do dump (é o caso do ambiente
-- Docker), a listagem de requerimentos morria com:
--   SQLSTATE[42S22]: Unknown column 'r.motivo_devolucao'
--
-- devolvido_por aponta para administradores.id (int(11) signed, sem UNSIGNED —
-- tem que casar com a coluna referenciada). Fica NULL quando a devolução veio
-- pelo fluxo novo, que não exige identificar quem devolveu: o
-- visualizar_requerimento.php trata esse caso e mostra o banner assim mesmo.

ALTER TABLE requerimentos
  ADD COLUMN IF NOT EXISTS motivo_devolucao TEXT NULL
      COMMENT 'Motivo escrito pelo Secretário ao devolver o processo ao Setor 2',
  ADD COLUMN IF NOT EXISTS devolvido_por INT(11) NULL
      COMMENT 'administradores.id de quem devolveu; NULL no retorno via fluxo novo';
