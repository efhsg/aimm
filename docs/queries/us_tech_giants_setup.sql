START TRANSACTION;

-- Sector: Technology
INSERT INTO sector (slug, name) VALUES ('technology', 'Technology')
ON DUPLICATE KEY UPDATE name = VALUES(name);
SET @sector_id := (SELECT id FROM sector WHERE slug = 'technology');

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
    created_by,
    created_at,
    updated_at
) VALUES (
    'us-tech-giants',
    'US Tech Giants',
    'Large-cap US technology companies (FMP free tier compatible)',
    5,
    5,
    JSON_ARRAY(
        JSON_OBJECT('key','market_cap','unit','currency','required',TRUE),
        JSON_OBJECT('key','fwd_pe','unit','ratio','required',TRUE),
        JSON_OBJECT('key','ev_ebitda','unit','ratio','required',TRUE),
        JSON_OBJECT('key','trailing_pe','unit','ratio','required',FALSE),
        JSON_OBJECT('key','fcf_yield','unit','percent','required',TRUE),
        JSON_OBJECT('key','div_yield','unit','percent','required',FALSE)
    ),
    JSON_ARRAY(
        JSON_OBJECT('key','revenue','unit','currency','required',TRUE),
        JSON_OBJECT('key','gross_profit','unit','currency','required',TRUE),
        JSON_OBJECT('key','operating_income','unit','currency','required',TRUE),
        JSON_OBJECT('key','ebitda','unit','currency','required',TRUE),
        JSON_OBJECT('key','net_income','unit','currency','required',TRUE),
        JSON_OBJECT('key','free_cash_flow','unit','currency','required',TRUE),
        JSON_OBJECT('key','total_assets','unit','currency','required',TRUE),
        JSON_OBJECT('key','total_liabilities','unit','currency','required',TRUE),
        JSON_OBJECT('key','total_equity','unit','currency','required',TRUE),
        JSON_OBJECT('key','total_debt','unit','currency','required',TRUE),
        JSON_OBJECT('key','cash_and_equivalents','unit','currency','required',TRUE),
        JSON_OBJECT('key','net_debt','unit','currency','required',FALSE),
        JSON_OBJECT('key','shares_outstanding','unit','number','required',TRUE)
    ),
    JSON_ARRAY(
        JSON_OBJECT('key','revenue','unit','currency','required',TRUE),
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
    'us-tech-giants',
    'US Tech Giants',
    'Large-cap US technology companies (FMP free tier compatible)',
    @policy_id,
    1,
    'admin',
    'admin',
    NOW(),
    NOW()
);
SET @industry_id := LAST_INSERT_ID();

-- Companies (all FMP free tier compatible)
INSERT INTO company (ticker, exchange, name, industry_id, currency, fiscal_year_end) VALUES
('AAPL', 'NASDAQ', 'Apple Inc.', @industry_id, 'USD', 9),
('MSFT', 'NASDAQ', 'Microsoft Corporation', @industry_id, 'USD', 6),
('NVDA', 'NASDAQ', 'NVIDIA Corporation', @industry_id, 'USD', 1),
('AMZN', 'NASDAQ', 'Amazon.com Inc.', @industry_id, 'USD', 12),
('GOOGL', 'NASDAQ', 'Alphabet Inc.', @industry_id, 'USD', 12)
ON DUPLICATE KEY UPDATE name = VALUES(name), industry_id = VALUES(industry_id);

COMMIT;
