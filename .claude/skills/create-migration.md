---
name: create-migration
description: Create or modify Yii2 schema migrations with reversible changes, complete ActiveRecord coverage, and isolated test-schema validation. Use for database schema work, not runtime datapacks or report artifacts.
---

# Create Migration

Create the smallest migration that implements the approved schema change and
keep the application model layer synchronized with it.

## Environment Boundary

- Use only the environment-specific database commands in
  `.claude/config/project.md`; that file owns command syntax and runtime facts.
- In the PromptManager runner, edit and review files only. Hand the exact
  applicable commands from project configuration to an AIMM maintainer.
- Before any database mutation, record the resolved application and test
  database names, prove that they are distinct, and capture a recovery anchor
  for each.

## Decide Whether a Migration Is Appropriate

AIMM stores datapacks and reports as runtime JSON artifacts. Do not introduce a
database table merely to persist those files. Use a migration only for an
approved relational schema requirement such as domain records, audit history,
or operational state.

## Naming

| Change | Name pattern |
|--------|--------------|
| Create table | `create_<table>_table` |
| Add column | `add_<column>_to_<table>` |
| Add index | `add_<index>_index_to_<table>` |
| Drop table | `drop_<table>_table` |

Generate the timestamped file with the canonical create command in
`.claude/config/project.md`, or prepare the file manually when the active
environment cannot run Yii.

## Migration Contract

```php
<?php

declare(strict_types=1);

use yii\db\Migration;

final class m241214_100000_create_example_table extends Migration
{
    public function safeUp(): bool
    {
        $this->createTable('{{%example}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string()->notNull(),
            'created_at' => $this->timestamp()
                ->notNull()
                ->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex('idx_example_name', '{{%example}}', 'name');

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTable('{{%example}}');

        return true;
    }
}
```

- Use `{{%table_name}}` for every table reference.
- Choose column types, nullability, defaults, indexes, and foreign keys from
  actual access and integrity requirements.
- Make `safeDown()` reverse `safeUp()` in dependency-safe order. If reversal
  would destroy data or cannot be made safe, stop and obtain explicit approval
  for the non-reversible design instead of pretending rollback is supported.
- Do not put runtime credentials or environment-specific database names in a
  migration.

## Model and Test Completeness

For every newly created table, include in the same approved change:

1. A final ActiveRecord model in `yii/src/models/` with typed PHPDoc properties,
   validation rules, `{{%table_name}}`, relations, and a typed `find()` method.
2. Its corresponding `ActiveQuery` class in `yii/src/queries/`.
3. Mapped unit tests under `yii/tests/unit/models/` and
   `yii/tests/unit/queries/`, plus tests for schema-dependent logic.

For an existing table change, update its model, query behavior, and affected
tests whenever the schema change alters their contract. Follow
`.claude/rules/architecture.md` for the canonical structures.

## Validation Order

1. Review the complete migration, model, query, and test diff.
2. Capture complete application and test migration histories and recovery
   anchors using `.claude/config/project.md`.
3. Apply and read back the migration on the isolated test schema first.
4. Run the mapped tests.
5. Apply to the approved application schema only after test-schema validation
   succeeds, then read back its complete history.

Do not roll back a shared or production schema merely to exercise `safeDown()`.
Test rollback only on an explicitly approved disposable schema, then reapply
and verify the final state.

## Definition of Done

- [ ] Migration is minimal, strictly typed, correctly named, and uses table prefixes
- [ ] `safeUp()` includes the required columns, constraints, indexes, and foreign keys
- [ ] `safeDown()` safely reverses the change, or the approved non-reversible exception is documented
- [ ] Every new table has its ActiveRecord model and corresponding ActiveQuery class
- [ ] Existing models and queries reflect any changed table contract
- [ ] Mapped model, query, and schema-dependent tests are included
- [ ] Application and test targets, complete histories, and recovery anchors are recorded
- [ ] Migration and rollback validation succeed on an approved disposable test schema
- [ ] Relevant tests succeed in the supported runtime
- [ ] Application migration is applied only after approval and successful test validation
- [ ] Final application and test histories are read back and recorded
