START TRANSACTION;

-- Sector: Energy
INSERT INTO sector (slug, name) VALUES ('energy', 'Energy')
ON DUPLICATE KEY UPDATE name = VALUES(name);
SET @sector_id := (SELECT id FROM sector WHERE slug = 'energy');

-- Collection policy for Global Energy Supermajors
-- Note: This is for Phase 2/3 development with supermajors-testdata.
-- For live collection, use us-energy-majors (FMP free tier compatible).
-- margin_proxy is NULL because GLOBAL_REFINING_MARGIN has no source mapping.
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
    source_priorities,
    created_by,
    created_at,
    updated_at
) VALUES (
    'global-energy-supermajors',
    'Global Energy Supermajors',
    NULL,
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
    JSON_ARRAY(
        JSON_OBJECT('key','total_production_kboed','unit','number','required',FALSE),
        JSON_OBJECT('key','lng_liquefaction_volumes','unit','number','required',FALSE)
    ),
    'BRENT',
    NULL,
    'XLE',
    JSON_ARRAY('natural_gas', 'brent_crude', 'rig_count', 'oil_inventory'),
    NULL,
    JSON_OBJECT(
        'valuation', JSON_ARRAY('fmp', 'yahoo_finance', 'stockanalysis'),
        'financials', JSON_ARRAY('fmp', 'yahoo_finance'),
        'quarters', JSON_ARRAY('fmp', 'yahoo_finance'),
        'macro', JSON_ARRAY('eia_inventory', 'ecb'),
        'benchmarks', JSON_ARRAY('yahoo_finance', 'fmp')
    ),
    'admin',
    '2025-12-31 10:05:21',
    '2025-12-31 22:25:03'
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
    'global-energy-supermajors',
    'Global Energy Supermajors',
    NULL,
    @policy_id,
    1,
    'admin',
    'claude-code',
    '2025-12-31 10:05:21',
    '2025-12-31 22:25:03'
);
SET @industry_id := LAST_INSERT_ID();

-- Companies (with industry_id FK instead of sector text)
INSERT INTO company (ticker, exchange, name, industry_id, currency, fiscal_year_end) VALUES
('SHEL', 'NYSE', 'Shell plc', @industry_id, 'USD', 12),
('XOM',  'NYSE', 'Exxon Mobil Corporation', @industry_id, 'USD', 12),
('COP',  'NYSE', 'ConocoPhillips', @industry_id, 'USD', 12),
('CVX',  'NYSE', 'Chevron Corporation', @industry_id, 'USD', 12),
('TTE',  'NYSE', 'TotalEnergies SE', @industry_id, 'USD', 12)
ON DUPLICATE KEY UPDATE name = VALUES(name), industry_id = VALUES(industry_id);

COMMIT;
