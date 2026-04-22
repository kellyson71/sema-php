<?php

if (!function_exists('ensureAdminNotificationTables')) {
    function ensureAdminNotificationTables(PDO $pdo): void
    {
        static $initialized = false;
        if ($initialized) {
            return;
        }

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS admin_notifications (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    tipo VARCHAR(50) NOT NULL,
                    titulo VARCHAR(255) NOT NULL,
                    descricao TEXT NOT NULL,
                    link_url VARCHAR(255) DEFAULT NULL,
                    requerimento_id INTEGER DEFAULT NULL,
                    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (requerimento_id) REFERENCES requerimentos(id) ON DELETE CASCADE
                )
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS admin_notification_reads (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    notification_id INTEGER NOT NULL,
                    admin_id INTEGER NOT NULL,
                    lida_em DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(notification_id, admin_id),
                    FOREIGN KEY (notification_id) REFERENCES admin_notifications(id) ON DELETE CASCADE,
                    FOREIGN KEY (admin_id) REFERENCES administradores(id) ON DELETE CASCADE
                )
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS requerimento_pagamento_historico (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    requerimento_id INTEGER NOT NULL,
                    documento_id INTEGER DEFAULT NULL,
                    instrucoes TEXT DEFAULT NULL,
                    enviado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
                    admin_envio_id INTEGER DEFAULT NULL,
                    FOREIGN KEY (requerimento_id) REFERENCES requerimentos(id) ON DELETE CASCADE,
                    FOREIGN KEY (documento_id) REFERENCES documentos(id) ON DELETE SET NULL,
                    FOREIGN KEY (admin_envio_id) REFERENCES administradores(id) ON DELETE SET NULL
                )
            ");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_admin_notifications_criado_em ON admin_notifications (criado_em DESC)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_admin_notifications_requerimento ON admin_notifications (requerimento_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_admin_notification_reads_admin ON admin_notification_reads (admin_id, notification_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pagamento_historico_requerimento ON requerimento_pagamento_historico (requerimento_id, enviado_em DESC)");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS admin_notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tipo VARCHAR(50) NOT NULL,
                    titulo VARCHAR(255) NOT NULL,
                    descricao TEXT NOT NULL,
                    link_url VARCHAR(255) NULL,
                    requerimento_id INT NULL,
                    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_admin_notifications_criado_em (criado_em),
                    INDEX idx_admin_notifications_requerimento (requerimento_id),
                    CONSTRAINT fk_admin_notifications_requerimento
                        FOREIGN KEY (requerimento_id) REFERENCES requerimentos(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS admin_notification_reads (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    notification_id INT NOT NULL,
                    admin_id INT NOT NULL,
                    lida_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_notification_admin (notification_id, admin_id),
                    KEY idx_admin_notification_reads_admin (admin_id, notification_id),
                    CONSTRAINT fk_admin_notification_reads_notification
                        FOREIGN KEY (notification_id) REFERENCES admin_notifications(id) ON DELETE CASCADE,
                    CONSTRAINT fk_admin_notification_reads_admin
                        FOREIGN KEY (admin_id) REFERENCES administradores(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS requerimento_pagamento_historico (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    requerimento_id INT NOT NULL,
                    documento_id INT NULL,
                    instrucoes TEXT NULL,
                    enviado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    admin_envio_id INT NULL,
                    KEY idx_pagamento_historico_requerimento (requerimento_id, enviado_em),
                    CONSTRAINT fk_pagamento_historico_requerimento
                        FOREIGN KEY (requerimento_id) REFERENCES requerimentos(id) ON DELETE CASCADE,
                    CONSTRAINT fk_pagamento_historico_documento
                        FOREIGN KEY (documento_id) REFERENCES documentos(id) ON DELETE SET NULL,
                    CONSTRAINT fk_pagamento_historico_admin
                        FOREIGN KEY (admin_envio_id) REFERENCES administradores(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $initialized = true;
    }
}

if (!function_exists('adminNotificationIcon')) {
    function adminNotificationIcon(string $tipo): string
    {
        return match ($tipo) {
            'novo_protocolo' => 'fa-inbox',
            'boleto_enviado' => 'fa-file-invoice-dollar',
            'comprovante_enviado' => 'fa-money-check-dollar',
            'indeferido' => 'fa-ban',
            default => 'fa-bell',
        };
    }
}

if (!function_exists('adminNotificationTipoNome')) {
    function adminNotificationTipoNome(string $slug): string
    {
        if (function_exists('nomeAlvara')) {
            return nomeAlvara($slug);
        }

        return ucwords(str_replace('_', ' ', $slug));
    }
}

if (!function_exists('adminNotificationAccent')) {
    function adminNotificationAccent(string $tipo): string
    {
        return match ($tipo) {
            'novo_protocolo' => 'accent-blue',
            'boleto_enviado' => 'accent-amber',
            'comprovante_enviado' => 'accent-teal',
            'indeferido' => 'accent-slate',
            default => 'accent-green',
        };
    }
}

if (!function_exists('createAdminNotification')) {
    function createAdminNotification(PDO $pdo, array $data): int
    {
        ensureAdminNotificationTables($pdo);

        $stmt = $pdo->prepare("
            INSERT INTO admin_notifications (tipo, titulo, descricao, link_url, requerimento_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['tipo'],
            $data['titulo'],
            $data['descricao'],
            $data['link_url'] ?? null,
            $data['requerimento_id'] ?? null,
        ]);

        return (int) $pdo->lastInsertId();
    }
}

if (!function_exists('createAdminNotificationForRequerimento')) {
    function createAdminNotificationForRequerimento(PDO $pdo, int $requerimentoId, string $tipo, array $override = []): ?int
    {
        ensureAdminNotificationTables($pdo);

        $stmt = $pdo->prepare("
            SELECT r.id, r.protocolo, r.status, r.tipo_alvara, req.nome AS requerente
            FROM requerimentos r
            JOIN requerentes req ON req.id = r.requerente_id
            WHERE r.id = ?
            LIMIT 1
        ");
        $stmt->execute([$requerimentoId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $titulo = $override['titulo'] ?? '';
        $descricao = $override['descricao'] ?? '';

        if ($titulo === '' || $descricao === '') {
            switch ($tipo) {
                case 'novo_protocolo':
                    $titulo = $titulo !== '' ? $titulo : 'Novo protocolo recebido';
                    $descricao = $descricao !== '' ? $descricao : sprintf(
                        '#%s · %s · %s',
                        $row['protocolo'],
                        $row['requerente'],
                        adminNotificationTipoNome($row['tipo_alvara'])
                    );
                    break;
                case 'boleto_enviado':
                    $titulo = $titulo !== '' ? $titulo : 'Boleto enviado';
                    $descricao = $descricao !== '' ? $descricao : sprintf(
                        '#%s · %s agora aguarda pagamento do boleto.',
                        $row['protocolo'],
                        $row['requerente']
                    );
                    break;
                case 'comprovante_enviado':
                    $titulo = $titulo !== '' ? $titulo : 'Comprovante enviado';
                    $descricao = $descricao !== '' ? $descricao : sprintf(
                        '#%s · %s enviou comprovante e aguarda conferência.',
                        $row['protocolo'],
                        $row['requerente']
                    );
                    break;
                case 'indeferido':
                    $titulo = $titulo !== '' ? $titulo : 'Processo indeferido';
                    $descricao = $descricao !== '' ? $descricao : sprintf(
                        '#%s · %s foi marcado como indeferido.',
                        $row['protocolo'],
                        $row['requerente']
                    );
                    break;
                default:
                    $titulo = $titulo !== '' ? $titulo : 'Atualização de processo';
                    $descricao = $descricao !== '' ? $descricao : sprintf(
                        '#%s · %s',
                        $row['protocolo'],
                        $row['requerente']
                    );
                    break;
            }
        }

        return createAdminNotification($pdo, [
            'tipo' => $tipo,
            'titulo' => $titulo,
            'descricao' => $descricao,
            'link_url' => $override['link_url'] ?? ('visualizar_requerimento.php?id=' . $row['id']),
            'requerimento_id' => $row['id'],
        ]);
    }
}

if (!function_exists('markAdminNotificationAsRead')) {
    function markAdminNotificationAsRead(PDO $pdo, int $notificationId, int $adminId): void
    {
        ensureAdminNotificationTables($pdo);

        $stmt = $pdo->prepare("
            SELECT 1
            FROM admin_notification_reads
            WHERE notification_id = ? AND admin_id = ?
            LIMIT 1
        ");
        $stmt->execute([$notificationId, $adminId]);
        if ($stmt->fetchColumn()) {
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO admin_notification_reads (notification_id, admin_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$notificationId, $adminId]);
    }
}

if (!function_exists('markAllAdminNotificationsAsRead')) {
    function markAllAdminNotificationsAsRead(PDO $pdo, int $adminId): int
    {
        ensureAdminNotificationTables($pdo);

        $stmt = $pdo->prepare("
            SELECT n.id
            FROM admin_notifications n
            LEFT JOIN admin_notification_reads r
                ON r.notification_id = n.id AND r.admin_id = ?
            WHERE r.id IS NULL
            ORDER BY n.criado_em DESC
        ");
        $stmt->execute([$adminId]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($ids as $notificationId) {
            markAdminNotificationAsRead($pdo, (int) $notificationId, $adminId);
        }

        return count($ids);
    }
}

if (!function_exists('fetchAdminNotificationCounts')) {
    function fetchAdminNotificationCounts(PDO $pdo, int $adminId): array
    {
        ensureAdminNotificationTables($pdo);

        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN r.id IS NULL THEN 1 ELSE 0 END) AS unread_count,
                SUM(CASE WHEN r.id IS NOT NULL THEN 1 ELSE 0 END) AS read_count
            FROM admin_notifications n
            LEFT JOIN admin_notification_reads r
                ON r.notification_id = n.id AND r.admin_id = ?
        ");
        $stmt->execute([$adminId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'unread' => (int) ($row['unread_count'] ?? 0),
            'read' => (int) ($row['read_count'] ?? 0),
        ];
    }
}

if (!function_exists('fetchAdminNotifications')) {
    function fetchAdminNotifications(PDO $pdo, int $adminId, string $tab = 'all', int $limit = 20, int $offset = 0): array
    {
        ensureAdminNotificationTables($pdo);

        $limit = max(1, (int) $limit);
        $offset = max(0, (int) $offset);

        $where = '';
        if ($tab === 'unread') {
            $where = 'WHERE r.id IS NULL';
        } elseif ($tab === 'read') {
            $where = 'WHERE r.id IS NOT NULL';
        }

        $stmt = $pdo->prepare("
            SELECT
                n.*,
                r.lida_em,
                CASE WHEN r.id IS NULL THEN 0 ELSE 1 END AS foi_lida
            FROM admin_notifications n
            LEFT JOIN admin_notification_reads r
                ON r.notification_id = n.id AND r.admin_id = ?
            {$where}
            ORDER BY
                CASE WHEN r.id IS NULL THEN 0 ELSE 1 END ASC,
                n.criado_em DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute([$adminId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($items as &$item) {
            $item['icon'] = adminNotificationIcon($item['tipo']);
            $item['accent_class'] = adminNotificationAccent($item['tipo']);
            $item['foi_lida'] = (int) $item['foi_lida'] === 1;
            $item['destino'] = $item['link_url'] ?: (($item['requerimento_id']) ? 'visualizar_requerimento.php?id=' . $item['requerimento_id'] : 'notificacoes.php');
        }
        unset($item);

        return $items;
    }
}

if (!function_exists('countAdminNotificationsByTab')) {
    function countAdminNotificationsByTab(PDO $pdo, int $adminId, string $tab = 'all'): int
    {
        $counts = fetchAdminNotificationCounts($pdo, $adminId);

        return match ($tab) {
            'unread' => $counts['unread'],
            'read' => $counts['read'],
            default => $counts['total'],
        };
    }
}

if (!function_exists('findAdminNotificationById')) {
    function findAdminNotificationById(PDO $pdo, int $notificationId, int $adminId): ?array
    {
        ensureAdminNotificationTables($pdo);

        $stmt = $pdo->prepare("
            SELECT
                n.*,
                r.lida_em,
                CASE WHEN r.id IS NULL THEN 0 ELSE 1 END AS foi_lida
            FROM admin_notifications n
            LEFT JOIN admin_notification_reads r
                ON r.notification_id = n.id AND r.admin_id = ?
            WHERE n.id = ?
            LIMIT 1
        ");
        $stmt->execute([$adminId, $notificationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['foi_lida'] = (int) $row['foi_lida'] === 1;
        $row['destino'] = $row['link_url'] ?: (($row['requerimento_id']) ? 'visualizar_requerimento.php?id=' . $row['requerimento_id'] : 'notificacoes.php');

        return $row;
    }
}
