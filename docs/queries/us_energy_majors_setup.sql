START TRANSACTION;

-- Sector: Energy (may already exist from oil_majors)
INSERT INTO sector (slug, name) VALUES ('energy', 'Energy')
ON DUPLICATE KEY UPDATE name = VALUES(name);
SET @sector_id := (SELECT id FROM sector WHERE slug = 'energy');

-- Collection policy for US Energy Majors
-- Note: margin_proxy is NULL because CRACK_SPREAD has no source mapping.
-- If margin proxy is needed, use WTI or BRENT (both mapped to Yahoo Finance).
INSERT INTO collection_policy (
    slug,
    name,
    description,
    history_years,
    quarters_to_fetch,
    valuation_metrics,
    annual_financial_metrics,
    quarterly_financial_metrics,
    operational_metrics,
    commodity_benchmark,
    margin_proxy,
    sector_index,
    required_indicators,
    optional_indicators,
    created_by,
    created_at,
    updated_at
) VALUES (
    'us-energy-majors',
    'US Energy Majors',
    'US-listed energy companies for FMP free tier compatibility',
    5,
    5,
    JSON_ARRAY(
        JSON_OBJECT('key','market_cap','unit','currency','required',TRUE),
        JSON_OBJECT('key','fwd_pe','unit','ratio','required',TRUE),
        JSON_OBJECT('key','ev_ebitda','unit','ratio','required',TRUE),
        JSON_OBJECT('key','trailing_pe','unit','ratio','required',FALSE),
        JSON_OBJECT('key','fcf_yield','unit','percent','required',TRUE),
        JSON_OBJECT('key','div_yield','unit','percent','required',TRUE)
    ),
    JSON_ARRAY(
        JSON_OBJECT('key','revenue','unit','currency','required',TRUE),
        JSON_OBJECT('key','ebitda','unit','currency','required',TRUE),
        JSON_OBJECT('key','net_income','unit','currency','required',TRUE),
        JSON_OBJECT('key','free_cash_flow','unit','currency','required',TRUE),
        JSON_OBJECT('key','total_assets','unit','currency','required',TRUE),
        JSON_OBJECT('key','total_liabilities','unit','currency','required',TRUE),
        JSON_OBJECT('key','total_equity','unit','currency','required',TRUE),
        JSON_OBJECT('key','total_debt','unit','currency','required',TRUE),
        JSON_OBJECT('key','cash_and_equivalents','unit','currency','required',TRUE),
        JSON_OBJECT('key','net_debt','unit','currency','required',FALSE)
    ),
    JSON_ARRAY(
        JSON_OBJECT('key','revenue','unit','currency','required',TRUE),
        JSON_OBJECT('key','ebitda','unit','currency','required',FALSE),
        JSON_OBJECT('key','net_income','unit','currency','required',FALSE),
        JSON_OBJECT('key','free_cash_flow','unit','currency','required',FALSE)
    ),
    JSON_ARRAY(),
    'WTI',
    NULL,
    'XLE',
    JSON_ARRAY('natural_gas', 'wti_crude', 'rig_count', 'oil_inventory'),
    NULL,
    'admin',
    NOW(),
    NOW()
);
SET @policy_id := LAST_INSERT_ID();

-- Industry (replaces peer group)
INSERT INTO industry (
    sector_id,
    slug,
    name,
    description,
    policy_id,
    is_active,
    created_by,
    updated_by,
    created_at,
    updated_at
) VALUES (
    @sector_id,
    'us-energy-majors',
    'US Energy Majors',
    'Major US-listed energy companies (FMP free tier compatible)',
    @policy_id,
    1,
    'admin',
    'admin',
    NOW(),
    NOW()
);
SET @industry_id := LAST_INSERT_ID();

-- Companies (all US-listed, FMP free tier compatible)
INSERT INTO company (ticker, exchange, name, industry_id, currency, fiscal_year_end) VALUES
('EOG', 'NYSE', 'EOG Resources Inc', @industry_id, 'USD', 12),
('OXY', 'NYSE', 'Occidental Petroleum Corporation', @industry_id, 'USD', 12),
('PSX', 'NYSE', 'Phillips 66', @industry_id, 'USD', 12),
('MPC', 'NYSE', 'Marathon Petroleum Corporation', @industry_id, 'USD', 12),
('VLO', 'NYSE', 'Valero Energy Corporation', @industry_id, 'USD', 12)
ON DUPLICATE KEY UPDATE name = VALUES(name), industry_id = VALUES(industry_id);

COMMIT;
