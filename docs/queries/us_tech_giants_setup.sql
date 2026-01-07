START TRANSACTION;

-- Collection policy for US Tech Giants
-- Tech sector: no commodity benchmarks (sector_index unavailable on FMP free tier)
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
    'us-tech-giants',
    'US Tech Giants',
    'Large-cap US technology companies (FMP free tier compatible)',
    5,
    8,
    JSON_ARRAY(
        JSON_OBJECT('key','market_cap','unit','currency','required',TRUE,'required_scope','all'),
        JSON_OBJECT('key','fwd_pe','unit','ratio','required',TRUE,'required_scope','all'),
        JSON_OBJECT('key','ev_ebitda','unit','ratio','required',TRUE,'required_scope','all'),
        JSON_OBJECT('key','trailing_pe','unit','ratio','required',FALSE),
        JSON_OBJECT('key','fcf_yield','unit','percent','required',TRUE,'required_scope','focal'),
        JSON_OBJECT('key','div_yield','unit','percent','required',FALSE)
    ),
    JSON_ARRAY(
        JSON_OBJECT('key','revenue','unit','currency','required',TRUE,'required_scope','all'),
        JSON_OBJECT('key','gross_profit','unit','currency','required',TRUE,'required_scope','all'),
        JSON_OBJECT('key','operating_income','unit','currency','required',TRUE,'required_scope','all'),
        JSON_OBJECT('key','ebitda','unit','currency','required',TRUE,'required_scope','all'),
        JSON_OBJECT('key','net_income','unit','currency','required',TRUE,'required_scope','focal'),
        JSON_OBJECT('key','free_cash_flow','unit','currency','required',TRUE,'required_scope','focal'),
        JSON_OBJECT('key','total_equity','unit','currency','required',TRUE,'required_scope','all'),
        JSON_OBJECT('key','total_debt','unit','currency','required',TRUE,'required_scope','all'),
        JSON_OBJECT('key','cash_and_equivalents','unit','currency','required',TRUE,'required_scope','all'),
        JSON_OBJECT('key','net_debt','unit','currency','required',FALSE),
        JSON_OBJECT('key','shares_outstanding','unit','number','required',TRUE,'required_scope','all')
    ),
    JSON_ARRAY(
        JSON_OBJECT('key','revenue','unit','currency','required',TRUE,'required_scope','focal'),
        JSON_OBJECT('key','ebitda','unit','currency','required',FALSE),
        JSON_OBJECT('key','net_income','unit','currency','required',FALSE),
        JSON_OBJECT('key','free_cash_flow','unit','currency','required',FALSE)
    ),
    JSON_ARRAY(),
    NULL,
    NULL,
    NULL,
    JSON_ARRAY(),
    JSON_ARRAY('SP500'),
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
    'us-tech-giants',
    'US Tech Giants',
    'Large-cap US technology companies (FMP free tier compatible)',
    'Technology',
    @policy_id,
    1,
    'admin',
    'admin',
    NOW(),
    NOW()
);
SET @peer_group_id := LAST_INSERT_ID();

-- Companies (all FMP free tier compatible)
INSERT INTO company (ticker, exchange, name, sector, currency, fiscal_year_end) VALUES
('AAPL', 'NASDAQ', 'Apple Inc.', 'Technology', 'USD', 9),
('MSFT', 'NASDAQ', 'Microsoft Corporation', 'Technology', 'USD', 6),
('NVDA', 'NASDAQ', 'NVIDIA Corporation', 'Technology', 'USD', 1),
('AMZN', 'NASDAQ', 'Amazon.com Inc.', 'Technology', 'USD', 12),
('GOOGL', 'NASDAQ', 'Alphabet Inc.', 'Technology', 'USD', 12)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Company IDs
SET @company_aapl := (SELECT id FROM company WHERE ticker = 'AAPL');
SET @company_msft := (SELECT id FROM company WHERE ticker = 'MSFT');
SET @company_nvda := (SELECT id FROM company WHERE ticker = 'NVDA');
SET @company_amzn := (SELECT id FROM company WHERE ticker = 'AMZN');
SET @company_googl := (SELECT id FROM company WHERE ticker = 'GOOGL');

-- Peer group members (AAPL as focal)
INSERT INTO industry_peer_group_member (
    peer_group_id,
    company_id,
    is_focal,
    display_order,
    added_at,
    added_by
) VALUES
(@peer_group_id, @company_aapl, 1, 0, NOW(), 'admin'),
(@peer_group_id, @company_msft, 0, 1, NOW(), 'admin'),
(@peer_group_id, @company_nvda, 0, 2, NOW(), 'admin'),
(@peer_group_id, @company_amzn, 0, 3, NOW(), 'admin'),
(@peer_group_id, @company_googl, 0, 4, NOW(), 'admin');

COMMIT;
