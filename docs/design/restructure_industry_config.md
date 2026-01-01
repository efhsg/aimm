# Design: Restructure Industry Configuration

## Status

**Draft** — Pending review

## Problem Statement

The current `industry_config` table stores a monolithic JSON blob containing:

1. **Company metadata** — Ticker, name, exchange, currency, fiscal year end
2. **Peer group membership** — Which companies belong together
3. **Collection rules** — Metrics to fetch, history depth, macro indicators

This creates several issues:

1. **Duplication** — Company metadata is duplicated across configs and now conflicts with the `company` table in the dossier
2. **No reuse** — The same collection rules are copy-pasted across similar industry configs
3. **Poor queryability** — Cannot easily answer "which groups is SHEL in?" or "all Energy sector groups"
4. **Rigid structure** — A company can only belong to one "industry"
5. **User unfriendly** — Editing large JSON blobs is error-prone

## Goals

1. **Normalize company data** — Company metadata lives in `company` table only
2. **Enable flexible membership** — A company can belong to multiple peer groups
3. **Reusable collection policies** — Define rules once, apply to many groups
4. **Simple user experience** — CLI commands to manage groups
5. **Backward compatible migration** — Existing configs migrate cleanly

## Non-Goals

- Full admin UI (can be added later)
- Real-time sync with external company databases
- Complex permission/access control on groups

---

## Proposed Schema

### 1. Collection Policy

Defines reusable collection rules (metrics, history depth, macro requirements).

```sql
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
    required_indicators JSON NULL,      -- ["Henry Hub", "Rig Count"]
    optional_indicators JSON NULL,

    -- Sector default behavior
    is_default_for_sector VARCHAR(100) NULL,

    created_by VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_sector_default (is_default_for_sector)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Notes:**
- `is_default_for_sector` allows one policy per sector to be auto-applied
- Unique constraint prevents multiple defaults for the same sector
- Metrics stored as JSON arrays matching current `MetricDefinition` structure

### 2. Industry Peer Group

Defines a named group of companies for comparison.

```sql
CREATE TABLE industry_peer_group (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    description VARCHAR(500) NULL,
    sector VARCHAR(100) NOT NULL,

    -- Link to collection rules
    policy_id BIGINT UNSIGNED NULL,

    -- Status
    is_active BOOLEAN NOT NULL DEFAULT TRUE,

    created_by VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (policy_id) REFERENCES collection_policy(id) ON DELETE SET NULL,
    INDEX idx_sector (sector),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Notes:**
- `policy_id` is nullable — falls back to sector default if not set
- `is_active` allows soft-disable without deletion
- Sector is a property of the group, not individual companies

### 3. Peer Group Membership

Links companies to groups (many-to-many).

```sql
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Notes:**
- Composite primary key enforces unique membership
- `is_focal` marks the anchor company for analysis
- `display_order` controls listing order (focal typically 0)
- `idx_company` enables "which groups is SHEL in?" queries

---

## Entity Relationships

```
┌─────────────────────┐
│  collection_policy  │
│  ─────────────────  │
│  id                 │
│  slug               │
│  valuation_metrics  │
│  macro requirements │
└─────────┬───────────┘
          │ 1
          │
          │ 0..*
┌─────────▼───────────┐         ┌─────────────────────┐
│ industry_peer_group │         │      company        │
│ ─────────────────── │         │ ─────────────────── │
│ id                  │         │ id                  │
│ slug                │         │ ticker              │
│ sector              │         │ name                │
│ policy_id (FK)      │         │ exchange            │
└─────────┬───────────┘         └──────────┬──────────┘
          │ 1                              │ 1
          │                                │
          │ 0..*                           │ 0..*
┌─────────▼────────────────────────────────▼──────────┐
│           industry_peer_group_member                │
│ ─────────────────────────────────────────────────── │
│ peer_group_id (PK, FK)                              │
│ company_id (PK, FK)                                 │
│ is_focal                                            │
└─────────────────────────────────────────────────────┘
```

---

## Policy Resolution

When collecting data for a peer group, resolve the effective policy:

```
1. If peer_group.policy_id is set → use that policy
2. Else if collection_policy exists where is_default_for_sector = peer_group.sector → use that
3. Else → error (no policy available)
```

This allows:
- Explicit policy assignment for specific groups
- Automatic defaults for most groups in a sector
- Clear error when configuration is incomplete

---

## PHP Implementation

### DTOs

```php
// src/dto/PeerGroup.php
final readonly class PeerGroup
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public string $sector,
        public ?int $policyId,
        public bool $isActive,
    ) {}
}

// src/dto/PeerGroupMember.php
final readonly class PeerGroupMember
{
    public function __construct(
        public int $companyId,
        public string $ticker,
        public string $name,
        public bool $isFocal,
        public int $displayOrder,
    ) {}
}

// src/dto/CollectionPolicy.php
final readonly class CollectionPolicy
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public int $historyYears,
        public int $quartersToFetch,
        public array $valuationMetrics,
        public array $annualFinancialMetrics,
        public array $quarterlyFinancialMetrics,
        public array $operationalMetrics,
        public ?string $commodityBenchmark,
        public ?string $marginProxy,
        public ?string $sectorIndex,
        public array $requiredIndicators,
        public array $optionalIndicators,
    ) {}
}

// src/dto/ResolvedPeerGroup.php (fully hydrated for collection)
final readonly class ResolvedPeerGroup
{
    public function __construct(
        public PeerGroup $group,
        public CollectionPolicy $policy,
        public PeerGroupMember $focalCompany,
        public array $peerCompanies,  // PeerGroupMember[]
    ) {}
}
```

### Query Classes

```php
// src/queries/CollectionPolicyQuery.php
class CollectionPolicyQuery
{
    public function findById(int $id): ?array;
    public function findBySlug(string $slug): ?array;
    public function findDefaultForSector(string $sector): ?array;
    public function findAll(): array;
    public function insert(array $data): int;
    public function update(int $id, array $data): void;
}

// src/queries/PeerGroupQuery.php
class PeerGroupQuery
{
    public function findById(int $id): ?array;
    public function findBySlug(string $slug): ?array;
    public function findAllActive(): array;
    public function findBySector(string $sector): array;
    public function findByCompanyId(int $companyId): array;  // Groups containing this company
    public function insert(array $data): int;
    public function update(int $id, array $data): void;
    public function deactivate(int $id): void;
}

// src/queries/PeerGroupMemberQuery.php
class PeerGroupMemberQuery
{
    public function findByGroup(int $groupId): array;
    public function findFocalByGroup(int $groupId): ?array;
    public function addMember(int $groupId, int $companyId, bool $isFocal, int $order): void;
    public function removeMember(int $groupId, int $companyId): void;
    public function setFocal(int $groupId, int $companyId): void;
    public function clearFocal(int $groupId): void;
}
```

### Handler

```php
// src/handlers/peer_group/ResolvePeerGroupHandler.php
final class ResolvePeerGroupHandler
{
    public function __construct(
        private readonly PeerGroupQuery $groupQuery,
        private readonly PeerGroupMemberQuery $memberQuery,
        private readonly CollectionPolicyQuery $policyQuery,
        private readonly CompanyQuery $companyQuery,
    ) {}

    public function resolve(string $groupSlug): ResolvedPeerGroup
    {
        $group = $this->groupQuery->findBySlug($groupSlug);
        if ($group === null) {
            throw new PeerGroupNotFoundException($groupSlug);
        }

        $policy = $this->resolvePolicy($group);
        $members = $this->memberQuery->findByGroup($group['id']);

        // Separate focal from peers, hydrate with company data
        // ...

        return new ResolvedPeerGroup($group, $policy, $focal, $peers);
    }

    private function resolvePolicy(array $group): CollectionPolicy
    {
        if ($group['policy_id'] !== null) {
            $policy = $this->policyQuery->findById($group['policy_id']);
        } else {
            $policy = $this->policyQuery->findDefaultForSector($group['sector']);
        }

        if ($policy === null) {
            throw new NoPolicyAvailableException($group['slug'], $group['sector']);
        }

        return $this->hydrate($policy);
    }
}
```

---

## CLI Commands

### Peer Group Management

```bash
# Create a new peer group
php yii peer-group/create "Global Energy Supermajors" \
    --slug=global-energy-supermajors \
    --sector=Energy \
    --policy=oil-gas-standard

# Add companies (auto-creates in company table if needed)
php yii peer-group/add global-energy-supermajors SHEL XOM BP CVX TTE

# Set focal company
php yii peer-group/set-focal global-energy-supermajors SHEL

# Remove a company
php yii peer-group/remove global-energy-supermajors CVX

# List all groups
php yii peer-group/list
php yii peer-group/list --sector=Energy

# Show group details
php yii peer-group/show global-energy-supermajors

# Deactivate (soft delete)
php yii peer-group/deactivate global-energy-supermajors
```

### Policy Management

```bash
# Create a policy from JSON file
php yii collection-policy/create oil-gas-standard \
    --name="Oil & Gas Standard" \
    --file=policies/oil-gas.json

# Set as sector default
php yii collection-policy/set-default oil-gas-standard --sector=Energy

# List policies
php yii collection-policy/list

# Show policy details
php yii collection-policy/show oil-gas-standard

# Export policy to JSON
php yii collection-policy/export oil-gas-standard > policy.json
```

### Example Policy JSON

```json
{
    "history_years": 5,
    "quarters_to_fetch": 8,
    "valuation_metrics": [
        {"key": "market_cap", "unit": "currency", "required": true, "required_scope": "all"},
        {"key": "trailing_pe", "unit": "ratio", "required": true, "required_scope": "all"},
        {"key": "div_yield", "unit": "percent", "required": true, "required_scope": "all"},
        {"key": "fcf_yield", "unit": "percent", "required": true, "required_scope": "focal"}
    ],
    "annual_financial_metrics": [
        {"key": "revenue", "unit": "currency", "required": true},
        {"key": "ebitda", "unit": "currency", "required": true},
        {"key": "net_income", "unit": "currency", "required": true},
        {"key": "free_cash_flow", "unit": "currency", "required": true}
    ],
    "operational_metrics": [
        {"key": "total_production_kboed", "unit": "number", "required": false}
    ],
    "commodity_benchmark": "BRENT",
    "margin_proxy": "GLOBAL_REFINING_MARGIN",
    "sector_index": "XLE",
    "required_indicators": ["Natural Gas Henry Hub", "TTF Gas Price"],
    "optional_indicators": ["Carbon Price (ETS)", "US Rig Count"]
}
```

---

## Migration Plan

### Phase 1: Create New Tables

```php
// migrations/m260102_000000_create_peer_group_schema.php
public function safeUp()
{
    // Create collection_policy
    // Create industry_peer_group
    // Create industry_peer_group_member
}
```

### Phase 2: Migrate Existing Configs

```php
// commands/MigratePeerGroupsController.php
public function actionIndex(): int
{
    $configs = $this->industryConfigQuery->findAll();

    foreach ($configs as $config) {
        $json = json_decode($config['config_json'], true);

        // 1. Create or find policy from requirements
        $policyId = $this->findOrCreatePolicy($json);

        // 2. Create peer group
        $groupId = $this->peerGroupQuery->insert([
            'slug' => $json['id'],
            'name' => $json['name'],
            'sector' => $json['sector'],
            'policy_id' => $policyId,
            'is_active' => (bool) $config['is_enabled'],
        ]);

        // 3. Add members
        foreach ($json['companies'] as $i => $company) {
            $companyId = $this->companyQuery->findOrCreate($company['ticker']);

            // Update company metadata if missing
            $this->updateCompanyMetadata($companyId, $company);

            $isFocal = $company['ticker'] === $json['focal_ticker'];
            $this->memberQuery->addMember($groupId, $companyId, $isFocal, $i);
        }

        $this->stdout("Migrated: {$json['name']}\n");
    }

    return ExitCode::OK;
}
```

### Phase 3: Update Collection Pipeline

Modify `CollectIndustryHandler` to accept `ResolvedPeerGroup` instead of reading from `industry_config` JSON:

```php
// Before
$config = $this->industryConfigQuery->findBySlug($slug);
$dto = IndustryConfig::fromJson($config['config_json']);

// After
$resolved = $this->resolvePeerGroupHandler->resolve($slug);
$dto = IndustryConfig::fromResolvedPeerGroup($resolved);
```

### Phase 4: Deprecate industry_config

1. Add deprecation notice to `IndustryConfigQuery`
2. Keep table for rollback safety
3. Remove after 1-2 release cycles

---

## Rollback Plan

If issues arise:

1. Collection pipeline can fall back to `industry_config` JSON
2. New tables can be dropped without data loss
3. No destructive changes to existing tables

---

## Testing Strategy

### Unit Tests

- `CollectionPolicyQueryTest` — CRUD operations, sector default lookup
- `PeerGroupQueryTest` — CRUD, sector filtering, company lookup
- `PeerGroupMemberQueryTest` — Add/remove, focal management
- `ResolvePeerGroupHandlerTest` — Policy resolution logic, error cases

### Integration Tests

- Migration script correctly transforms existing configs
- Collection pipeline works with new structure
- CLI commands function correctly

---

## Open Questions

1. **Should we support policy inheritance?** (e.g., "Oil & Gas Deep Dive" extends "Oil & Gas Standard")
   - *Recommendation:* Not in v1. Keep policies flat and copy shared values.

2. **How to handle company metadata conflicts?** (JSON says USD, FMP says GBP)
   - *Recommendation:* Dossier is source of truth. Migration logs warnings for conflicts.

3. **Should focal be per-group or per-analysis-run?**
   - *Recommendation:* Per-group default, with CLI override for ad-hoc analysis.

---

## Timeline

| Phase | Description | Estimate |
|-------|-------------|----------|
| 1 | Schema migration | 1 file |
| 2 | Query classes + tests | 3 files + 3 tests |
| 3 | CLI commands | 2 controllers |
| 4 | Data migration script | 1 file |
| 5 | Update collection pipeline | Modify existing |
| 6 | Documentation | This doc + CLI help |

---

## Appendix: Example Queries

### Find all groups a company belongs to

```sql
SELECT g.*
FROM industry_peer_group g
JOIN industry_peer_group_member m ON g.id = m.peer_group_id
JOIN company c ON m.company_id = c.id
WHERE c.ticker = 'SHEL';
```

### Find all active Energy sector groups with their focal company

```sql
SELECT g.slug, g.name, c.ticker AS focal_ticker
FROM industry_peer_group g
JOIN industry_peer_group_member m ON g.id = m.peer_group_id AND m.is_focal = TRUE
JOIN company c ON m.company_id = c.id
WHERE g.sector = 'Energy' AND g.is_active = TRUE;
```

### Resolve effective policy for a group

```sql
SELECT
    g.slug AS group_slug,
    COALESCE(p1.slug, p2.slug) AS policy_slug,
    COALESCE(p1.name, p2.name) AS policy_name
FROM industry_peer_group g
LEFT JOIN collection_policy p1 ON g.policy_id = p1.id
LEFT JOIN collection_policy p2 ON p2.is_default_for_sector = g.sector
WHERE g.slug = 'global-energy-supermajors';
```

### List groups with member counts

```sql
SELECT
    g.slug,
    g.name,
    g.sector,
    COUNT(m.company_id) AS member_count,
    SUM(m.is_focal) AS has_focal
FROM industry_peer_group g
LEFT JOIN industry_peer_group_member m ON g.id = m.peer_group_id
WHERE g.is_active = TRUE
GROUP BY g.id
ORDER BY g.sector, g.name;
```
