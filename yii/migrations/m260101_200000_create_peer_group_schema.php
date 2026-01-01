<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Creates the peer group and collection policy schema.
 *
 * See docs/design/restructure_industry_config.md for full design.
 */
class m260101_200000_create_peer_group_schema extends Migration
{
    public function safeUp(): void
    {
        // 1. Collection Policy - Reusable collection rules
        $this->execute(<<<'SQL'
            CREATE TABLE collection_policy (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(100) NOT NULL UNIQUE,
                name VARCHAR(100) NOT NULL,
                description VARCHAR(500) NULL,

                -- Data requirements
                history_years TINYINT UNSIGNED NOT NULL DEFAULT 5,
                quarters_to_fetch TINYINT UNSIGNED NOT NULL DEFAULT 8,

                -- Metric definitions (JSON arrays)
                valuation_metrics JSON NOT NULL,
                annual_financial_metrics JSON NULL,
                quarterly_financial_metrics JSON NULL,
                operational_metrics JSON NULL,

                -- Macro requirements
                commodity_benchmark VARCHAR(50) NULL,
                margin_proxy VARCHAR(50) NULL,
                sector_index VARCHAR(50) NULL,
                required_indicators JSON NULL,
                optional_indicators JSON NULL,

                -- Sector default behavior
                is_default_for_sector VARCHAR(100) NULL,

                created_by VARCHAR(100) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uk_sector_default (is_default_for_sector)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // 2. Industry Peer Group - Group identity
        $this->execute(<<<'SQL'
            CREATE TABLE industry_peer_group (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(100) NOT NULL UNIQUE,
                name VARCHAR(255) NOT NULL,
                description VARCHAR(500) NULL,
                sector VARCHAR(100) NOT NULL,

                policy_id BIGINT UNSIGNED NULL,

                is_active BOOLEAN NOT NULL DEFAULT TRUE,

                created_by VARCHAR(100) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (policy_id) REFERENCES collection_policy(id) ON DELETE SET NULL,
                INDEX idx_sector (sector),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        // 3. Peer Group Membership - Many-to-many link
        $this->execute(<<<'SQL'
            CREATE TABLE industry_peer_group_member (
                peer_group_id BIGINT UNSIGNED NOT NULL,
                company_id BIGINT UNSIGNED NOT NULL,

                is_focal BOOLEAN NOT NULL DEFAULT FALSE,
                display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,

                added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                added_by VARCHAR(100) NULL,

                PRIMARY KEY (peer_group_id, company_id),
                FOREIGN KEY (peer_group_id) REFERENCES industry_peer_group(id) ON DELETE CASCADE,
                FOREIGN KEY (company_id) REFERENCES company(id) ON DELETE CASCADE,
                INDEX idx_company (company_id),
                INDEX idx_focal (peer_group_id, is_focal)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function safeDown(): void
    {
        $this->execute('DROP TABLE IF EXISTS industry_peer_group_member');
        $this->execute('DROP TABLE IF EXISTS industry_peer_group');
        $this->execute('DROP TABLE IF EXISTS collection_policy');
    }
}
