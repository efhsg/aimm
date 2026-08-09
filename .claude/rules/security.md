# Security Policies

## Scope Enforcement

- Handlers validate user has access before operating
- Never trust client-provided IDs without verification
- Log access attempts with user context

## Data Provenance

- Every datapoint must have source attribution
- Never fabricate financial data; report missing or conflicting provenance instead
- Document failed collection attempts
- Preserve source URL, retrieval time, reporting period, unit, transformation,
  and validation outcome for every financial datapoint

## Secrets

- No credentials in code
- Use environment variables or Yii params
- Never log sensitive values
- Never place credentials, tokens, private keys, connection secrets, or local
  machine exceptions in tracked agent configuration or evidence artifacts
- Use named runtime environment variables or clearly non-secret placeholders in
  examples
