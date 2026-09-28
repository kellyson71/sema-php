-- Correção de dados: status 'Pendente' gravado pelo Setor 2 antes do vocabulário próprio (2026-09-27)
-- Até o commit 43b4ac8 o Setor 2 só tinha o 'Pendente' genérico, o mesmo do Setor 1, e o
-- processo aparecia como pendência do cidadão sem ser. Levantamento em produção em 27/09/2026:
-- só estes dois processos do Setor 2 ainda estavam 'Pendente'.
--
--   977  (20260731085340822) Fabricio mudou para 'Pendente' em 02/09 ("Preparando emissão de
--        alvará"). Antes disso estava 'Finalizado' (Julia, 05/08, protocolo oficial enviado).
--        Volta para o status de antes. É a única vez que o Fabricio marcou 'Pendente'.
--   1081 (20260908140730619) Isabely marcou 'Pendente' em 14/09 ("Aguardando o parecer técnico
--        AMBIENTAL") -> status equivalente do vocabulário novo: 'Aguardando parecer técnico'.
-- As condições no WHERE só deixam mudar se o processo ainda estiver como no levantamento.

UPDATE requerimentos SET status = 'Finalizado'
 WHERE id = 977 AND setor_atual = 'setor2' AND status = 'Pendente';

UPDATE requerimentos SET status = 'Aguardando parecer técnico'
 WHERE id = 1081 AND setor_atual = 'setor2' AND status = 'Pendente';

INSERT INTO historico_acoes (admin_id, requerimento_id, acao)
SELECT NULL, id, CASE id
    WHEN 977 THEN 'Correção: status ''Pendente'' marcado por engano em 02/09 desfeito; processo volta a ''Finalizado'', como estava antes'
    ELSE 'Correção: status ''Pendente'' do Setor 2 trocado por ''Aguardando parecer técnico'' (o Setor 2 passou a ter status próprio de espera)'
  END
  FROM requerimentos
 WHERE (id = 977 AND status = 'Finalizado') OR (id = 1081 AND status = 'Aguardando parecer técnico');
