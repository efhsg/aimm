# Peer Groups

Peer Groups define the focal company and its comparison peers for analysis.

## Overview

A Peer Group consists of:
- A **focal company** (the subject of analysis)
- Multiple **peer companies** (for comparison)
- A linked **Collection Policy** (data requirements)

## Index View

### Features

- Search by name or ticker
- Filter by status (active/inactive)
- Filter by sector
- Run status badges (Complete, Partial, Failed, Never Run)

### Actions

| Action | Description |
|--------|-------------|
| View | See group details and recent runs |
| Edit | Modify group membership |
| Run Collection | Trigger a new collection run |

### Columns

| Column | Description |
|--------|-------------|
| Name | Group display name |
| Sector | Industry sector classification |
| Focal | Focal company ticker |
| Members | Total company count |
| Last Run | Most recent collection run status |
| Actions | View, Edit, Run |

## Detail View

### Group Metadata

| Field | Description |
|-------|-------------|
| Name | Group display name |
| Slug | URL-friendly identifier |
| Sector | Industry sector |
| Policy | Linked Collection Policy |
| Status | Active/Inactive |
| Created | Creation timestamp and user |
| Updated | Last update timestamp and user |

### Members Table

| Column | Description |
|--------|-------------|
| Ticker | Company stock symbol |
| Name | Company name |
| Role | Focal or Peer |
| Exchange | Listing exchange |
| Actions | Remove, Set as Focal |

### Recent Runs

| Column | Description |
|--------|-------------|
| Started | Run start timestamp |
| Status | Complete, Partial, Failed |
| Duration | Elapsed time |
| Errors | Error count |
| Warnings | Warning count |
| Actions | View details |

## Create/Update

### Required Fields

| Field | Description |
|-------|-------------|
| Name | Group display name |
| Slug | URL-friendly identifier (auto-generated) |
| Sector | Industry sector |
| Policy | Collection Policy to use |

### Adding Members

1. Search for company by ticker or name
2. Add to group as peer
3. Designate one member as focal

### Validation Rules

- At least one focal company required
- At least one peer company required
- All members must be in the same sector
- Focal company cannot also be a peer

## Running Collection

### From Index

Click "Run Collection" button on any peer group row.

### From Detail View

Click "Run Collection" in the action bar.

### What Happens

1. System validates group configuration
2. Creates a new Collection Run record
3. Executes Phase 1 (Collect) for all members
4. Runs Collection Gate validation
5. Updates run status with results

### Monitoring

After triggering a run:
- View progress on [Collection Runs](/admin-ui/collection-runs) page
- Status updates as run progresses
- Errors/warnings appear in real-time
