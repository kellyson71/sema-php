<?php

if (!function_exists('ensureAdminReleaseReadTable')) {
    function ensureAdminReleaseReadTable(PDO $pdo): void
    {
        static $initialized = false;
        if ($initialized) {
            return;
        }

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS admin_release_reads (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    admin_id INTEGER NOT NULL,
                    version VARCHAR(50) NOT NULL,
                    lida_em DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(admin_id, version),
                    FOREIGN KEY (admin_id) REFERENCES administradores(id) ON DELETE CASCADE
                )
            ");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_admin_release_reads_admin ON admin_release_reads (admin_id, version)");
        } else {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS admin_release_reads (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    admin_id INT NOT NULL,
                    version VARCHAR(50) NOT NULL,
                    lida_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_admin_release_read (admin_id, version),
                    KEY idx_admin_release_reads_admin (admin_id, version),
                    CONSTRAINT fk_admin_release_reads_admin
                        FOREIGN KEY (admin_id) REFERENCES administradores(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        $initialized = true;
    }
}

if (!function_exists('hasAdminSeenReleaseVersion')) {
    function hasAdminSeenReleaseVersion(PDO $pdo, int $adminId, string $version): bool
    {
        ensureAdminReleaseReadTable($pdo);

        $stmt = $pdo->prepare("
            SELECT 1
            FROM admin_release_reads
            WHERE admin_id = ? AND version = ?
            LIMIT 1
        ");
        $stmt->execute([$adminId, $version]);

        return (bool) $stmt->fetchColumn();
    }
}

if (!function_exists('markAdminReleaseVersionAsSeen')) {
    function markAdminReleaseVersionAsSeen(PDO $pdo, int $adminId, string $version): void
    {
        ensureAdminReleaseReadTable($pdo);

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("
                INSERT OR IGNORE INTO admin_release_reads (admin_id, version)
                VALUES (?, ?)
            ");
            $stmt->execute([$adminId, $version]);
            return;
        }

        $stmt = $pdo->prepare("
            INSERT INTO admin_release_reads (admin_id, version)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE lida_em = lida_em
        ");
        $stmt->execute([$adminId, $version]);
    }
}
