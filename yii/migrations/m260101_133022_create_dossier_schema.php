<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Class m260101_133022_create_dossier_schema
 */
class m260101_133022_create_dossier_schema extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // 1. Company
        $this->execute(<<<SQL
            CREATE TABLE company (
                id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ticker          VARCHAR(20) NOT NULL,
                exchange        VARCHAR(20) NULL,
                name            VARCHAR(255) NULL,
                sector          VARCHAR(100) NULL,
                industry        VARCHAR(100) NULL,
                currency        CHAR(3) NULL,
                fiscal_year_end TINYINT UNSIGNED NULL,

                financials_collected_at     DATETIME NULL,
                quarters_collected_at       DATETIME NULL,
                valuation_collected_at      DATETIME NULL,
                profile_collected_at        DATETIME NULL,

                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                UNIQUE KEY uk_ticker (ticker),
                KEY idx_exchange (exchange),
                KEY idx_sector (sector)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        // 2. Annual Financial
        $this->execute(<<<SQL
            CREATE TABLE annual_financial (
                id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id      BIGINT UNSIGNED NOT NULL,
                fiscal_year     SMALLINT UNSIGNED NOT NULL,
                period_end_date DATE NOT NULL,

                revenue         DECIMAL(20,2) NULL,
                cost_of_revenue DECIMAL(20,2) NULL,
                gross_profit    DECIMAL(20,2) NULL,
                operating_income DECIMAL(20,2) NULL,
                ebitda          DECIMAL(20,2) NULL,
                net_income      DECIMAL(20,2) NULL,
                eps             DECIMAL(10,4) NULL,

                operating_cash_flow  DECIMAL(20,2) NULL,
                capex               DECIMAL(20,2) NULL,
                free_cash_flow      DECIMAL(20,2) NULL,
                dividends_paid      DECIMAL(20,2) NULL,

                total_assets        DECIMAL(20,2) NULL,
                total_liabilities   DECIMAL(20,2) NULL,
                total_equity        DECIMAL(20,2) NULL,
                total_debt          DECIMAL(20,2) NULL,
                cash_and_equivalents DECIMAL(20,2) NULL,
                net_debt            DECIMAL(20,2) NULL,

                shares_outstanding  BIGINT UNSIGNED NULL,

                currency        CHAR(3) NOT NULL,
                source_adapter  VARCHAR(50) NOT NULL,
                source_url      VARCHAR(500) NULL,
                collected_at    DATETIME NOT NULL,
                version         TINYINT UNSIGNED NOT NULL DEFAULT 1,
                is_current      BOOLEAN NOT NULL DEFAULT TRUE,

                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY (company_id) REFERENCES company(id) ON DELETE CASCADE,
                UNIQUE KEY uk_company_year_version (company_id, fiscal_year, version),
                KEY idx_company_current (company_id, is_current, fiscal_year DESC),
                KEY idx_collected (collected_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        // 3. Quarterly Financial
        $this->execute(<<<SQL
            CREATE TABLE quarterly_financial (
                id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id      BIGINT UNSIGNED NOT NULL,
                fiscal_year     SMALLINT UNSIGNED NOT NULL,
                fiscal_quarter  TINYINT UNSIGNED NOT NULL,
                period_end_date DATE NOT NULL,

                revenue         DECIMAL(20,2) NULL,
                gross_profit    DECIMAL(20,2) NULL,
                operating_income DECIMAL(20,2) NULL,
                ebitda          DECIMAL(20,2) NULL,
                net_income      DECIMAL(20,2) NULL,
                eps             DECIMAL(10,4) NULL,

                operating_cash_flow DECIMAL(20,2) NULL,
                capex              DECIMAL(20,2) NULL,
                free_cash_flow     DECIMAL(20,2) NULL,

                currency        CHAR(3) NOT NULL,
                source_adapter  VARCHAR(50) NOT NULL,
                source_url      VARCHAR(500) NULL,
                collected_at    DATETIME NOT NULL,
                version         TINYINT UNSIGNED NOT NULL DEFAULT 1,
                is_current      BOOLEAN NOT NULL DEFAULT TRUE,

                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY (company_id) REFERENCES company(id) ON DELETE CASCADE,
                UNIQUE KEY uk_company_quarter_version (company_id, fiscal_year, fiscal_quarter, version),
                KEY idx_company_current (company_id, is_current, fiscal_year DESC, fiscal_quarter DESC),
                KEY idx_period_end (period_end_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        // 4. TTM Financial
        $this->execute(<<<SQL
            CREATE TABLE ttm_financial (
                id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id      BIGINT UNSIGNED NOT NULL,
                as_of_date      DATE NOT NULL,

                revenue         DECIMAL(20,2) NULL,
                gross_profit    DECIMAL(20,2) NULL,
                operating_income DECIMAL(20,2) NULL,
                ebitda          DECIMAL(20,2) NULL,
                net_income      DECIMAL(20,2) NULL,

                operating_cash_flow DECIMAL(20,2) NULL,
                capex              DECIMAL(20,2) NULL,
                free_cash_flow     DECIMAL(20,2) NULL,

                q1_period_end   DATE NULL,
                q2_period_end   DATE NULL,
                q3_period_end   DATE NULL,
                q4_period_end   DATE NULL,

                currency        CHAR(3) NOT NULL,
                calculated_at   DATETIME NOT NULL,

                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY (company_id) REFERENCES company(id) ON DELETE CASCADE,
                UNIQUE KEY uk_company_date (company_id, as_of_date),
                KEY idx_company_recent (company_id, as_of_date DESC)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        // 5. Valuation Snapshot
        $this->execute(<<<SQL
            CREATE TABLE valuation_snapshot (
                id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id      BIGINT UNSIGNED NOT NULL,
                snapshot_date   DATE NOT NULL,

                price           DECIMAL(12,4) NULL,
                market_cap      DECIMAL(20,2) NULL,
                enterprise_value DECIMAL(20,2) NULL,
                shares_outstanding BIGINT UNSIGNED NULL,

                trailing_pe     DECIMAL(10,4) NULL,
                forward_pe      DECIMAL(10,4) NULL,
                peg_ratio       DECIMAL(10,4) NULL,
                price_to_book   DECIMAL(10,4) NULL,
                price_to_sales  DECIMAL(10,4) NULL,
                ev_to_ebitda    DECIMAL(10,4) NULL,
                ev_to_revenue   DECIMAL(10,4) NULL,

                dividend_yield  DECIMAL(8,4) NULL,
                fcf_yield       DECIMAL(8,4) NULL,
                earnings_yield  DECIMAL(8,4) NULL,

                net_debt_to_ebitda DECIMAL(10,4) NULL,

                retention_tier  ENUM('daily', 'weekly', 'monthly') NOT NULL DEFAULT 'daily',

                currency        CHAR(3) NOT NULL,
                source_adapter  VARCHAR(50) NOT NULL,
                collected_at    DATETIME NOT NULL,

                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                FOREIGN KEY (company_id) REFERENCES company(id) ON DELETE CASCADE,
                UNIQUE KEY uk_company_date (company_id, snapshot_date),
                KEY idx_snapshot_date (snapshot_date),
                KEY idx_company_recent (company_id, snapshot_date DESC),
                KEY idx_retention (retention_tier, snapshot_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        // 6. Price History
        $this->execute(<<<SQL
            CREATE TABLE price_history (
                id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                symbol          VARCHAR(20) NOT NULL,
                symbol_type     ENUM('stock', 'commodity', 'index', 'etf', 'fx') NOT NULL,
                price_date      DATE NOT NULL,

                open            DECIMAL(12,4) NULL,
                high            DECIMAL(12,4) NULL,
                low             DECIMAL(12,4) NULL,
                close           DECIMAL(12,4) NOT NULL,
                adjusted_close  DECIMAL(12,4) NULL,
                volume          BIGINT UNSIGNED NULL,

                currency        CHAR(3) NOT NULL DEFAULT 'USD',
                source_adapter  VARCHAR(50) NOT NULL,
                collected_at    DATETIME NOT NULL,

                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                UNIQUE KEY uk_symbol_date (symbol, price_date),
                KEY idx_symbol_recent (symbol, price_date DESC),
                KEY idx_date (price_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        // 7. FX Rate
        $this->execute(<<<SQL
            CREATE TABLE fx_rate (
                id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                base_currency   CHAR(3) NOT NULL,
                quote_currency  CHAR(3) NOT NULL,
                rate_date       DATE NOT NULL,
                rate            DECIMAL(12,6) NOT NULL,

                source_adapter  VARCHAR(50) NOT NULL DEFAULT 'ecb',
                collected_at    DATETIME NOT NULL,

                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                UNIQUE KEY uk_pair_date (base_currency, quote_currency, rate_date),
                KEY idx_quote_date (quote_currency, rate_date DESC)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        // 8. Macro Indicator
        $this->execute(<<<SQL
            CREATE TABLE macro_indicator (
                id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                indicator_key   VARCHAR(100) NOT NULL,
                indicator_date  DATE NOT NULL,

                value           DECIMAL(20,4) NOT NULL,
                unit            VARCHAR(50) NOT NULL,

                source_adapter  VARCHAR(50) NOT NULL,
                source_url      VARCHAR(500) NULL,
                collected_at    DATETIME NOT NULL,

                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                UNIQUE KEY uk_indicator_date (indicator_key, indicator_date),
                KEY idx_indicator_recent (indicator_key, indicator_date DESC)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        // 9. Collection Attempt
        $this->execute(<<<SQL
            CREATE TABLE collection_attempt (
                id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                entity_type     ENUM('company', 'price', 'fx', 'macro') NOT NULL,
                entity_id       BIGINT UNSIGNED NULL,
                data_type       VARCHAR(50) NOT NULL,

                source_adapter  VARCHAR(50) NOT NULL,
                source_url      VARCHAR(500) NOT NULL,

                outcome         ENUM('success', 'not_found', 'rate_limited', 'blocked', 'error') NOT NULL,
                http_status     SMALLINT UNSIGNED NULL,
                error_message   VARCHAR(500) NULL,

                attempted_at    DATETIME NOT NULL,
                duration_ms     INT UNSIGNED NULL,

                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

                KEY idx_entity (entity_type, entity_id, attempted_at DESC),
                KEY idx_adapter_outcome (source_adapter, outcome, attempted_at),
                KEY idx_attempted (attempted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);

        // 10. Data Gap
        $this->execute(<<<SQL
            CREATE TABLE data_gap (
                id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                company_id      BIGINT UNSIGNED NOT NULL,
                data_type       VARCHAR(50) NOT NULL,

                gap_reason      ENUM('not_found', 'not_reported', 'private', 'delisted') NOT NULL,
                first_detected  DATETIME NOT NULL,
                last_checked    DATETIME NOT NULL,
                check_count     INT UNSIGNED NOT NULL DEFAULT 1,

                notes           VARCHAR(500) NULL,

                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                FOREIGN KEY (company_id) REFERENCES company(id) ON DELETE CASCADE,
                UNIQUE KEY uk_company_gap (company_id, data_type),
                KEY idx_last_checked (last_checked)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Drop in reverse order to respect foreign keys
        $this->execute("DROP TABLE IF EXISTS data_gap");
        $this->execute("DROP TABLE IF EXISTS collection_attempt");
        $this->execute("DROP TABLE IF EXISTS macro_indicator");
        $this->execute("DROP TABLE IF EXISTS fx_rate");
        $this->execute("DROP TABLE IF EXISTS price_history");
        $this->execute("DROP TABLE IF EXISTS valuation_snapshot");
        $this->execute("DROP TABLE IF EXISTS ttm_financial");
        $this->execute("DROP TABLE IF EXISTS quarterly_financial");
        $this->execute("DROP TABLE IF EXISTS annual_financial");
        $this->execute("DROP TABLE IF EXISTS company");
    }
}
