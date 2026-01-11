# Setup

AIMM requires a database populated with sectors, industries, and companies before the pipeline can be executed.

## Prerequisites

- Docker containers running: `aimm_yii` (app) and `aimm_mysql` (database)
- MySQL credentials configured: `aimm` / `aimm_secret` / `aimm`

## Quick Start

For a fresh installation with all configuration data:

```bash
docker exec aimm_yii php yii db/init
```

This runs migrations and seeds the database with industry configurations.

## Database Setup Options

### Option 1: Full Setup (Recommended)

Use `db/init` for a complete setup with all reference data:

```bash
docker exec aimm_yii php yii db/init
```

**What it does:**
1. **Migrates**: Creates all tables (Sector, Industry, Company, etc.)
2. **Seeds reference data**: Populates `data_source` table (API providers)
3. **Seeds config**: Creates industry configurations (Oil Majors, US Energy Majors, US Tech Giants)

### Option 2: Schema Only

Use `migrate/fresh` for a minimal database with only the schema and essential reference data:

```bash
docker exec aimm_yii php yii migrate/fresh --interactive=0
```

**What it does:**
1. Drops all tables
2. Runs all migrations (schema + `data_source` seed)
3. Does **not** run application seeders (no industries, companies, or policies)

Use this when:
- Testing migration changes
- Setting up a clean database for custom configuration
- Debugging schema issues

### Option 3: Reset Existing Database

Use `db/reset` to drop everything and reinitialize:

```bash
docker exec aimm_yii php yii db/reset --force
```

> [!WARNING]
> This will drop all tables and recreate them. All collected financial data will be lost.

## Seeding Options

Seeders populate the database with configuration and test data. Run them manually as needed.

### Seed Industry Configuration

Sets up the standard industry groups, policies, and company lists. Automatically run by `db/init`.

```bash
docker exec aimm_yii php yii seed/config
```

**Industries Created:**
- **Global Energy Supermajors** (`global-energy-supermajors`): SHEL, XOM, COP, CVX, TTE
- **US Energy Majors** (`us-energy-majors`): EOG, OXY, PSX, MPC, VLO
- **US Tech Giants** (`us-tech-giants`): AAPL, MSFT, GOOGL, AMZN, META

### Load Financial Test Data

Load pre-populated test data to test the **Analyze** and **Render** phases without external API calls:

```bash
docker exec aimm_yii php yii seed/testdata
```

**What it does:**
- Loads 5 years of annual financials for Global Energy Supermajors
- Loads 8 quarters of financial data
- Loads valuation snapshots
- Allows immediate report generation for `global-energy-supermajors`

## Verification

Confirm the setup by listing available industries:

```bash
docker exec aimm_yii php yii collect/list
```

Expected output shows the configured industries with their company counts.

## Related

- [Squash Migrations](./squash-migrations.md) - Consolidate migrations into a single schema file
