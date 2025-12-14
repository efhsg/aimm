---
name: create-migration
description: Create Yii2 database migrations for MoneyMonkey modules. Use when adding new tables or modifying schema. Follows Yii2 migration conventions with proper naming and rollback support. Do NOT use for runtime data (datapacks are JSON files, not database records).
---

# CreateMigration

Generate Yii2 database migrations with proper structure and rollback.

## When to Use

MoneyMonkey primarily uses JSON files for datapacks and reports. Database migrations are needed for:

- **Collection module:** Source attempt logs, rate limit state, collection job queue
- **Analysis module:** Report metadata, cached calculations
- **Audit:** User actions, access logs

## Interface

```bash
./yii migrate/create <migration_name>
```

Naming convention: `create_<table>_table` or `add_<column>_to_<table>`

## Migration Structure

```php
<?php

use yii\db\Migration;

class m241214_100000_create_source_attempts_table extends Migration
{
    public function safeUp(): bool
    {
        $this->createTable('{{%source_attempts}}', [
            'id' => $this->primaryKey(),
            // columns...
            'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);
        
        // indexes...
        
        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTable('{{%source_attempts}}');
        return true;
    }
}
```

## Collection Module Tables

### source_attempts

Logs every HTTP request made during collection.

```php
public function safeUp(): bool
{
    $this->createTable('{{%source_attempts}}', [
        'id' => $this->primaryKey(),
        'datapack_id' => $this->string(36)->notNull(),
        'provider_id' => $this->string(50)->notNull(),
        'url' => $this->text()->notNull(),
        'status' => $this->string(20)->notNull(),         // success, failed, skipped
        'error_reason' => $this->string(50)->null(),      // http_4xx, timeout, etc.
        'response_code' => $this->smallInteger()->null(),
        'response_time_ms' => $this->integer()->null(),
        'attempted_at' => $this->timestamp()->notNull(),
    ]);
    
    $this->createIndex('idx_source_attempts_datapack', '{{%source_attempts}}', 'datapack_id');
    $this->createIndex('idx_source_attempts_provider', '{{%source_attempts}}', 'provider_id');
    $this->createIndex('idx_source_attempts_status', '{{%source_attempts}}', 'status');
    
    return true;
}
```

### rate_limit_state

Tracks rate limiting state per domain.

```php
public function safeUp(): bool
{
    $this->createTable('{{%rate_limit_state}}', [
        'id' => $this->primaryKey(),
        'domain' => $this->string(255)->notNull()->unique(),
        'request_count' => $this->integer()->notNull()->defaultValue(0),
        'window_start' => $this->timestamp()->notNull(),
        'last_request_at' => $this->timestamp()->null(),
        'backoff_until' => $this->timestamp()->null(),
        'backoff_level' => $this->smallInteger()->notNull()->defaultValue(0),
    ]);
    
    $this->createIndex('idx_rate_limit_domain', '{{%rate_limit_state}}', 'domain');
    
    return true;
}
```

### collection_jobs

Queue for collection jobs (if using database queue driver).

```php
public function safeUp(): bool
{
    $this->createTable('{{%collection_jobs}}', [
        'id' => $this->primaryKey(),
        'industry_id' => $this->string(50)->notNull(),
        'status' => $this->string(20)->notNull()->defaultValue('pending'),
        'datapack_id' => $this->string(36)->null(),
        'started_at' => $this->timestamp()->null(),
        'completed_at' => $this->timestamp()->null(),
        'error_message' => $this->text()->null(),
        'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
    ]);
    
    $this->createIndex('idx_collection_jobs_status', '{{%collection_jobs}}', 'status');
    $this->createIndex('idx_collection_jobs_industry', '{{%collection_jobs}}', 'industry_id');
    
    return true;
}
```

### datapack_metadata

Metadata about generated datapacks (the JSON files themselves live in runtime/).

```php
public function safeUp(): bool
{
    $this->createTable('{{%datapack_metadata}}', [
        'id' => $this->primaryKey(),
        'datapack_id' => $this->string(36)->notNull()->unique(),
        'industry_id' => $this->string(50)->notNull(),
        'status' => $this->string(20)->notNull(),         // complete, partial, failed
        'companies_count' => $this->smallInteger()->notNull(),
        'datapoints_found' => $this->integer()->notNull(),
        'datapoints_missing' => $this->integer()->notNull(),
        'gate_passed' => $this->boolean()->notNull(),
        'gate_errors' => $this->json()->null(),
        'gate_warnings' => $this->json()->null(),
        'file_path' => $this->string(500)->notNull(),
        'file_size_bytes' => $this->integer()->notNull(),
        'collected_at' => $this->timestamp()->notNull(),
        'created_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
    ]);
    
    $this->createIndex('idx_datapack_meta_industry', '{{%datapack_metadata}}', 'industry_id');
    $this->createIndex('idx_datapack_meta_status', '{{%datapack_metadata}}', 'status');
    $this->createIndex('idx_datapack_meta_collected', '{{%datapack_metadata}}', 'collected_at');
    
    return true;
}
```

## Running Migrations

```bash
# Run all pending migrations
./yii migrate

# Run specific migration
./yii migrate/up 1

# Rollback last migration
./yii migrate/down 1

# Check migration status
./yii migrate/history
```

## Definition of Done

- [ ] Migration file created in `migrations/` directory
- [ ] `safeUp()` creates table with all columns and indexes
- [ ] `safeDown()` properly rolls back (drops table)
- [ ] Column types appropriate for data
- [ ] Indexes on foreign keys and frequently queried columns
- [ ] Table prefix `{{%` used for all table names
- [ ] Migration runs without errors: `./yii migrate`
- [ ] Migration rolls back without errors: `./yii migrate/down 1`

## Naming Conventions

| Action | Pattern | Example |
|--------|---------|---------|
| New table | `create_<table>_table` | `create_source_attempts_table` |
| Add column | `add_<column>_to_<table>` | `add_response_time_to_source_attempts` |
| Add index | `add_<index>_index_to_<table>` | `add_status_index_to_collection_jobs` |
| Drop table | `drop_<table>_table` | `drop_legacy_logs_table` |

## Common Column Types

```php
$this->primaryKey()                    // Auto-increment integer
$this->string(36)                      // UUID
$this->string(50)                      // Short identifier
$this->string(255)                     // Standard string
$this->text()                          // Long text (URLs, messages)
$this->json()                          // JSON data
$this->integer()                       // Numbers
$this->smallInteger()                  // Small numbers (counts, codes)
$this->boolean()                       // True/false
$this->timestamp()                     // Datetime
$this->decimal(10, 2)                  // Money/precise decimals
```

## Database Configuration

**config/console.php** — add db component:

```php
'components' => [
    'db' => [
        'class' => 'yii\db\Connection',
        'dsn' => 'mysql:host=localhost;dbname=moneymonkey',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'tablePrefix' => 'mm_',
    ],
    // ... other components
],
```
