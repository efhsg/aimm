# Admin Guide: Industry Config Management

This guide explains how to access and use the administrative interface for managing Industry Configurations in the AIMM system.

## 1. Access & Authentication

The Industry Config Management UI is an internal tool and is not publicly accessible. It requires **HTTP Basic Authentication**.

### URL
- **Local/Dev:** `http://localhost:8080/industry-config` (or your configured local domain)
- **Production:** `https://your-domain.com/industry-config`

### Credentials
Authentication is controlled by environment variables. You must provide these credentials when prompted by your browser:

- **Username:** Value of `ADMIN_USERNAME`
- **Password:** Value of `ADMIN_PASSWORD`

> **Note:** If these environment variables are not set, the admin UI is completely disabled and will return a 401 Unauthorized error for all requests.

## 2. Managing Industries

### List View
The dashboard shows all configured industries.
- **Search:** Filter by industry name or ID.
- **Status Filter:** View only "Active" or "Inactive" configurations.
- **Columns:** Shows Name, ID, Active Status, Last Updated, and Last Editor.

### Creating a Configuration
1. Click **"Create New Industry"**.
2. **Industry ID:** Enter a unique identifier (lowercase, numbers, underscores only). This **cannot be changed** later.
3. **Configuration JSON:** Enter the full JSON configuration.
   - You can use the **"Format JSON"** button to prettify your input.
   - Use **"Validate"** to check for errors before saving.
4. Click **"Create Configuration"**.

### Updating a Configuration
1. Click the **"Edit"** button (pencil icon) on any industry row.
2. Modify the **Configuration JSON**.
3. The **Industry ID** is read-only.
4. Click **"Save Changes"**.

### Toggling Status (Enable/Disable)
- Use the **"Disable"** or **"Enable"** button on the list or detail view.
- **Disabling** an industry stops the collection pipeline for that industry immediately.
- **Enabling** validates the configuration but does not require it to be perfect (though the collection pipeline may fail if the JSON is invalid).
- **Note:** You can disable an industry even if its JSON is currently invalid.

## 3. Validation Rules

The system enforces strict validation to ensure the collection pipeline runs smoothly.

### JSON Requirements
1. **Valid Syntax:** Must be valid JSON.
2. **Schema Compliance:** Must match `industry-config.schema.json`. Common requirements:
   - `companies` array must not be empty.
   - `data_requirements` must specify history depth.
3. **ID Match:** The `id` field inside the JSON must exactly match the **Industry ID** of the record.

### Troubleshooting Common Errors

- **"Configuration 'id' (...) must match industry_id (...)"**
  - Ensure the `id` key in your JSON matches the Industry ID you entered (or the existing one).
- **"Schema validation failed"**
  - Check that all required fields (like `companies`, `macro_requirements`) are present.
  - Ensure `companies` has at least one entry.
