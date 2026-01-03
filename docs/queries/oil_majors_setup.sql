START TRANSACTION;

-- Collection policy
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
    'global-energy-supermajors',
    'Global Energy Supermajors',
    NULL,
    5,
    8,
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
        JSON_OBJECT('key','net_debt','unit','currency','required',FALSE)
    ),
    JSON_ARRAY(
        JSON_OBJECT('key','revenue','unit','currency','required',TRUE,'required_scope','focal'),
        JSON_OBJECT('key','ebitda','unit','currency','required',FALSE),
        JSON_OBJECT('key','net_income','unit','currency','required',FALSE),
        JSON_OBJECT('key','free_cash_flow','unit','currency','required',FALSE)
    ),
    JSON_ARRAY(
        JSON_OBJECT('key','total_production_kboed','unit','number','required',FALSE),
        JSON_OBJECT('key','lng_liquefaction_volumes','unit','number','required',FALSE)
    ),
    'BRENT',
    'GLOBAL_REFINING_MARGIN',
    'XLE',
    JSON_ARRAY('natural_gas', 'brent_crude'),
    NULL,
    NULL,
    'admin',
    '2025-12-31 10:05:21',
    '2025-12-31 22:25:03'
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
    'global-energy-supermajors',
    'Global Energy Supermajors',
    NULL,
    'Energy',
    @policy_id,
    1,
    'admin',
    'claude-code',
    '2025-12-31 10:05:21',
    '2025-12-31 22:25:03'
);
SET @peer_group_id := LAST_INSERT_ID();

-- Companies
INSERT INTO company (ticker, exchange, name, sector, currency, fiscal_year_end) VALUES
('SHEL', 'NYSE', 'Shell plc', 'Energy', 'USD', 12),
('XOM',  'NYSE', 'Exxon Mobil Corporation', 'Energy', 'USD', 12),
('BP',   'NYSE', 'BP plc', 'Energy', 'USD', 12),
('CVX',  'NYSE', 'Chevron Corporation', 'Energy', 'USD', 12),
('TTE',  'NYSE', 'TotalEnergies SE', 'Energy', 'USD', 12);

-- Company IDs
SET @company_shel := (SELECT id FROM company WHERE ticker = 'SHEL');
SET @company_xom  := (SELECT id FROM company WHERE ticker = 'XOM');
SET @company_bp   := (SELECT id FROM company WHERE ticker = 'BP');
SET @company_cvx  := (SELECT id FROM company WHERE ticker = 'CVX');
SET @company_tte  := (SELECT id FROM company WHERE ticker = 'TTE');

-- Peer group members
INSERT INTO industry_peer_group_member (
    peer_group_id,
    company_id,
    is_focal,
    display_order,
    added_at,
    added_by
) VALUES
(@peer_group_id, @company_shel, 1, 0, '2025-12-31 10:05:21', 'admin'),
(@peer_group_id, @company_xom,  0, 1, '2025-12-31 10:05:21', 'admin'),
(@peer_group_id, @company_bp,   0, 2, '2025-12-31 10:05:21', 'admin'),
(@peer_group_id, @company_cvx,  0, 3, '2025-12-31 10:05:21', 'admin'),
(@peer_group_id, @company_tte,  0, 4, '2025-12-31 10:05:21', 'admin');

COMMIT;
