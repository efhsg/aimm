# Setup

AIMM requires a database populated with sectors, industries, and companies before the pipeline can be executed.

## Database Initialization

To initialize the database from scratch (run migrations and load configuration data):

```bash
./yii db/init
```

**What it does:**
1.  **Migrates**: Creates all tables (Sector, Industry, Company, etc.).
2.  **Seeds Config**: Populates the database with industry configurations (Oil Majors, US Energy Majors, US Tech Giants).
3.  **No Test Data**: Does *not* load financial test data (revenue, prices, etc.), keeping the database clean for production use.

## Database Reset

If you need to clear all data and start over:

```bash
./yii db/reset
```

> [!WARNING]
> This will drop all tables and recreate them. All collected financial data will be lost.

## Seeding Options

You can run seeders manually to manage configuration and test data.

### Seed Industry Configuration

Sets up the standard industry groups, policies, and company lists. This is automatically run by `db/init`.

```bash
./yii seed/config
```

**Industries Created:**
- **Global Energy Supermajors** (`global-energy-supermajors`):
  - **SHEL** (Shell plc)
  - **XOM** (Exxon Mobil Corp)
  - **COP** (ConocoPhillips)
  - **CVX** (Chevron Corp)
  - **TTE** (TotalEnergies SE)
- **US Energy Majors** (`us-energy-majors`): EOG, OXY, PSX, MPC, VLO
- **US Tech Giants** (`us-tech-giants`): AAPL, MSFT, GOOGL, AMZN, META

### Load Financial Test Data

If you want to test the **Analyze** and **Render** phases without making external API calls, you can load pre-populated test data.

```bash
./yii seed/testdata
```

**What it does:**
- Loads 5 years of annual financials for Global Energy Supermajors (XOM, CVX, SHEL, COP, TTE).
- Loads 8 quarters of financial data.
- Loads valuation snapshots.
- Allows immediate report generation for the `global-energy-supermajors` industry.

## Next Steps

Once the database is initialized, you can list the available industries to confirm the setup:

```bash
./yii collect/list
```
