# Security Policies

## Scope Enforcement

- Handlers validate user has access before operating
- Never trust client-provided IDs without verification
- Log access attempts with user context

## Data Provenance

- Every datapoint must have source attribution
- Never fabricate financial data
- Document failed collection attempts

## Secrets

- No credentials in code
- Use environment variables or Yii params
- Never log sensitive values
