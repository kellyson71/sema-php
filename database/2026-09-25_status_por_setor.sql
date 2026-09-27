-- Migration: status próprio por setor (2026-09-25)
-- O Setor 2 (fiscal) usava o mesmo 'Pendente' genérico do Setor 1 pra sinalizar
-- que um processo estava parado esperando algo — o Setor 1 via "Pendente" na
-- fila dele e não tinha como saber se era pendência do cidadão ou coisa do
-- Setor 2. Agora o Setor 2 tem vocabulário próprio (ver admin/helpers.php,
-- adminStatusSetor2()) em vez de reaproveitar 'Pendente'.

ALTER TABLE `requerimentos`
  MODIFY COLUMN `status` ENUM(
    'Pendente',
    'Em análise',
    'Aguardando Fiscalização',
    'Aprovado',
    'Reprovado',
    'Cancelado',
    'Indeferido',
    'Finalizado',
    'Apto a gerar alvará',
    'Alvará Emitido',
    'Aguardando boleto',
    'Boleto pago',
    'Aguardando Secretaria',
    'Devolvido pela Secretaria',
    'Documento Final Enviado',
    'Aguardando complementação',
    'Aguardando visita técnica',
    'Aguardando parecer técnico'
  ) NOT NULL DEFAULT 'Pendente';
