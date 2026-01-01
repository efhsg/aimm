# Guide: Configuring and Collecting Industry Data

**Version:** 1.0  
**Last Updated:** 2026-01-01  
**Audience:** Data Analysts and System Operators

---

## Overview

This guide outlines the standard operating procedure for configuring new industry peer groups and triggering the data collection pipeline within the AIMM platform. By following these steps, you ensure that the **Company Dossier** is populated with accurate, sourced financial and operational data for downstream analysis.

## Prerequisites

Before starting, ensure you have the following:

1.  **System Access**: Access to the AIMM CLI environment (via Docker).
2.  **Ticker List**: A verified list of stock ticker symbols (e.g., `SHEL`, `XOM`) for the companies you wish to track.
3.  **Sector Knowledge**: You must know the primary sector (e.g., "Energy", "Technology") to assign the correct default collection policies.
4.  **Permissions**: "Operator" or "Admin" level permissions to execute write commands in the CLI.

---

## Step-by-Step Instructions

### Phase 1: Configure the Peer Group

This phase defines *who* we are collecting data for.

1.  **Access the Command Line**
    Open your terminal and ensure the Docker container is running:
    ```bash
    docker ps
    # Verify 'aimm_yii' is listed
    ```

2.  **Create the Peer Group**
    Use the `peer-group/create` command to define the container for your companies.
    *   **Syntax**: `php yii peer-group/create "[Name]" --slug=[slug] --sector=[Sector]`
    *   **Example**:
        ```bash
        docker exec aimm_yii php yii peer-group/create "Global Energy Supermajors" \
          --slug=global-energy-supermajors \
          --sector=Energy
        ```
    *   *Result*: The system will confirm the group creation and ID.

    *[Insert Screenshot: Terminal showing successful group creation message]*

3.  **Add Companies to the Group**
    Add the companies by their ticker symbols. The system will automatically link them to the Dossier.
    *   **Syntax**: `php yii peer-group/add [slug] [Ticker1] [Ticker2] ...`
    *   **Example**:
        ```bash
        docker exec aimm_yii php yii peer-group/add global-energy-supermajors SHEL XOM BP CVX TTE
        ```

4.  **Set the Focal Company (Optional)**
    If this group is centered around a specific company for analysis comparisons, mark it as "Focal".
    *   **Action**: Run `php yii peer-group/set-focal global-energy-supermajors SHEL`

### Phase 2: Trigger Data Collection

This phase executes the fetchers to populate the database.

1.  **Run the Collection Command**
    Initiate the collection pipeline for your specific group.
    *   **Command**:
        ```bash
        docker exec aimm_yii php yii collect/industry --group=global-energy-supermajors
        ```

2.  **Monitor Progress**
    The CLI will output real-time logs. Watch for:
    *   **Green [OK]**: Successful data retrieval.
    *   **Yellow [WARN]**: Non-critical issues (e.g., "Metric not found for 2021").
    *   **Red [ERR]**: Critical failures (see Troubleshooting).

    *[Insert Screenshot: CLI output showing progress bars and status messages]*

3.  **Verify Data Integrity**
    Once complete, run a summary report to ensure data was stored in the Dossier.
    *   **Command**: `php yii report/summary --group=global-energy-supermajors`

---

## Data Specifications

When preparing your inputs or reviewing outputs, adhere to these standards:

*   **Ticker Symbols**: Must be uppercase standard exchange tickers (e.g., `AAPL`, not `Apple`).
*   **Currencies**: The system stores values in the **reporting currency** (e.g., EUR for TotalEnergies, USD for Exxon). Do not manually convert currencies before input; the system handles FX normalization during analysis.
*   **Fiscal Years**: Data is collected based on the company's fiscal calendar. Ensure you are comparing "FY2023" to "FY2023", even if the calendar months differ.

## Security & Compliance

Strict adherence to data governance is required:

*   **Data Provenance**: Never manually insert fabricated financial figures into the database. If data is missing, it must be recorded as `NULL` or via a `record-not-found` entry.
*   **Source Attribution**: The system automatically tags every datapoint with its source URL. Do not override or obfuscate these tags.
*   **Rate Limits**: Do not script the collection command to run in a tight loop. The system has built-in delays to respect provider rate limits; bypassing these may result in IP bans.

---

## Troubleshooting

### Common Issues

**1. "Company Not Found" Error**
*   **Cause**: The ticker symbol does not exist in the source provider or is misspelled.
*   **Solution**: Verify the ticker on a public finance site (e.g., Yahoo Finance). If the company is listed on a non-US exchange, you may need the suffix (e.g., `SHELL.AS` for Amsterdam).

**2. "No Policy Available" Error**
*   **Cause**: You created a group in a Sector that does not have a default Collection Policy, and you didn't assign a specific one.
*   **Solution**: Assign a policy explicitly:
    ```bash
    php yii peer-group/set-policy global-energy-supermajors --policy=standard-equity
    ```

**3. "Rate Limit Exceeded"**
*   **Cause**: Too many requests were sent in a short period.
*   **Solution**: Wait 15 minutes before retrying. The system will automatically back off, but manual retries should be paused.

---

**Need Help?**
Contact the Backend Engineering Lead or submit a ticket in the repository issue tracker with the label `area:collection`.
