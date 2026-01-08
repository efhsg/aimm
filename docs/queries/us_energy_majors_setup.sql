START TRANSACTION;

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
    is_default_for_sector,
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
        JSON_OBJECT('key','market_cap','unit','currency','required',TRUE,'required_scope','all'),
        JSON_OBJECT('key','fwd_pe','unit','ratio','required',TRUE,'required_scope','all'),
        JSON_OBJECT('key','ev_ebitda','unit','ratio','required',TRUE,'required_scope','all'),
        JSON_OBJECT('key','trailing_pe','unit','ratio','required',FALSE),
        JSON_OBJECT('key','fcf_yield','unit','percent','required',TRUE,'required_scope','focal'),
        JSON_OBJECT('key','div_yield','unit','percent','required',TRUE,'required_scope','all')
    ),
    JSON_ARRAY(
        JSON_OBJECT('key','revenue','unit','currency','required',TRUE,'required_scope','all'),
        JSON_OBJECT('key','ebitda','unit','currency','required',TRUE,'required_scope','all'),
        JSON_OBJECT('key','net_income','unit','currency','required',TRUE,'required_scope','focal'),
        JSON_OBJECT('key','free_cash_flow','unit','currency','required',TRUE,'required_scope','focal'),
        JSON_OBJECT('key','total_assets','unit','currency','required',TRUE,'required_scope','all'),
        JSON_OBJECT('key','total_liabilities','unit','currency','required',TRUE,'required_scope','all'),
        JSON_OBJECT('key','total_equity','unit','currency','required',TRUE,'required_scope','all'),
        JSON_OBJECT('key','total_debt','unit','currency','required',TRUE,'required_scope','all'),
        JSON_OBJECT('key','cash_and_equivalents','unit','currency','required',TRUE,'required_scope','all'),
        JSON_OBJECT('key','net_debt','unit','currency','required',FALSE)
    ),
    JSON_ARRAY(
        JSON_OBJECT('key','revenue','unit','currency','required',TRUE,'required_scope','focal'),
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
    NULL,
    'admin',
    NOW(),
    NOW()
);
SET @policy_id := LAST_INSERT_ID();

-- Peer group
INSERT INTO industry_peer_group (
    slug,
    name,
    description,
    sector,
    policy_id,
    is_active,
    created_by,
    updated_by,
    created_at,
    updated_at
) VALUES (
    'us-energy-majors',
    'US Energy Majors',
    'Major US-listed energy companies (FMP free tier compatible)',
    'Energy',
    @policy_id,
    1,
    'admin',
    'admin',
    NOW(),
    NOW()
);
SET @peer_group_id := LAST_INSERT_ID();

-- Companies (all US-listed, FMP free tier compatible)
INSERT INTO company (ticker, exchange, name, sector, currency, fiscal_year_end) VALUES
('XOM', 'NYSE', 'Exxon Mobil Corporation', 'Energy', 'USD', 12),
('CVX', 'NYSE', 'Chevron Corporation', 'Energy', 'USD', 12),
('COP', 'NYSE', 'ConocoPhillips', 'Energy', 'USD', 12),
('EOG', 'NYSE', 'EOG Resources Inc', 'Energy', 'USD', 12),
('OXY', 'NYSE', 'Occidental Petroleum Corporation', 'Energy', 'USD', 12)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Company IDs
SET @company_xom := (SELECT id FROM company WHERE ticker = 'XOM');
SET @company_cvx := (SELECT id FROM company WHERE ticker = 'CVX');
SET @company_cop := (SELECT id FROM company WHERE ticker = 'COP');
SET @company_eog := (SELECT id FROM company WHERE ticker = 'EOG');
SET @company_oxy := (SELECT id FROM company WHERE ticker = 'OXY');

-- Peer group members (XOM as focal)
INSERT INTO industry_peer_group_member (
    peer_group_id,
    company_id,
    is_focal,
    display_order,
    added_at,
    added_by
) VALUES
(@peer_group_id, @company_xom, 1, 0, NOW(), 'admin'),
(@peer_group_id, @company_cvx, 0, 1, NOW(), 'admin'),
(@peer_group_id, @company_cop, 0, 2, NOW(), 'admin'),
(@peer_group_id, @company_eog, 0, 3, NOW(), 'admin'),
(@peer_group_id, @company_oxy, 0, 4, NOW(), 'admin');

COMMIT;
