-- Corrige a view que pode permanecer como stand-in após importação de dump.
CREATE OR REPLACE VIEW `estatisticas_email` AS
SELECT
    DATE(`data_envio`) AS `data`,
    `status`,
    `eh_teste`,
    COUNT(*) AS `total`,
    COUNT(DISTINCT `email_destino`) AS `emails_unicos`
FROM `email_logs`
WHERE `data_envio` >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(`data_envio`), `status`, `eh_teste`
ORDER BY `data` DESC, `status` ASC;
