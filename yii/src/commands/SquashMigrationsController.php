<?php

declare(strict_types=1);

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\ColumnSchema;
use yii\db\Connection;
use yii\db\Expression;
use yii\helpers\Console;

/**
 * Squashes all migrations into a single initial migration.
 *
 * This command reads the current database schema and generates a new
 * migration that recreates all tables. Old migrations can be archived.
 */
final class SquashMigrationsController extends Controller
{
    public string $migrationPath = '@app/../migrations';

    public bool $archive = false;

    public bool $withSeed = false;

    /**
     * Tables to include in seed migration (reference data required for FK constraints).
     *
     * @var list<string>
     */
    public array $seedTables = ['data_source'];

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), [
            'migrationPath',
            'archive',
            'withSeed',
            'seedTables',
        ]);
    }

    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), [
            'a' => 'archive',
            'p' => 'migrationPath',
            's' => 'withSeed',
        ]);
    }

    /**
     * Squashes all migrations into a single migration file.
     *
     * @param string $name Name suffix for the squashed migration (default: 'squashed_schema')
     */
    public function actionIndex(string $name = 'squashed_schema'): int
    {
        $db = Yii::$app->db;
        if (!$db instanceof Connection) {
            $this->stderr("Database connection not available.\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $migrationPath = Yii::getAlias($this->migrationPath);
        if (!is_dir($migrationPath)) {
            $this->stderr("Migration path does not exist: {$migrationPath}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Squashing migrations from: {$migrationPath}\n", Console::FG_CYAN);

        $tables = $this->getTables($db);
        if (empty($tables)) {
            $this->stderr("No tables found in database.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $this->stdout("Found " . count($tables) . " tables to export.\n");

        $existingMigrations = $this->getExistingMigrations($migrationPath);
        if (empty($existingMigrations)) {
            $this->stderr("No existing migrations found.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $this->stdout("Found " . count($existingMigrations) . " existing migrations.\n");

        $schema = $this->generateSchema($db, $tables);
        $migrationContent = $this->generateMigrationFile($name, $schema, $existingMigrations);

        $timestamp = date('ymd_His');
        $className = "m{$timestamp}_{$name}";
        $filename = "{$className}.php";
        $filepath = $migrationPath . '/' . $filename;

        if (file_put_contents($filepath, $migrationContent) === false) {
            $this->stderr("Failed to write migration file: {$filepath}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Created squashed migration: {$filename}\n", Console::FG_GREEN);

        // Generate seed migration if requested
        $seedFilename = null;
        if ($this->withSeed) {
            $seedResult = $this->generateSeedMigration($db, $migrationPath);
            if ($seedResult === null) {
                $this->stderr("Failed to generate seed migration.\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }
            $seedFilename = $seedResult;
            $this->stdout("Created seed migration: {$seedFilename}\n", Console::FG_GREEN);
        }

        if ($this->archive) {
            $archivePath = $migrationPath . '/archived';
            if (!is_dir($archivePath) && !mkdir($archivePath, 0755, true)) {
                $this->stderr("Failed to create archive directory.\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            foreach ($existingMigrations as $migration) {
                $oldPath = $migrationPath . '/' . $migration;
                $newPath = $archivePath . '/' . $migration;
                if (rename($oldPath, $newPath)) {
                    $this->stdout("Archived: {$migration}\n", Console::FG_YELLOW);
                } else {
                    $this->stderr("Failed to archive: {$migration}\n", Console::FG_RED);
                }
            }
        }

        $this->stdout("\nNext steps:\n", Console::FG_CYAN);
        $this->stdout("1. Review the generated migration: {$filename}\n");
        if ($seedFilename !== null) {
            $this->stdout("2. Review the seed migration: {$seedFilename}\n");
            $this->stdout("3. Run: php yii migrate/fresh --interactive=0\n");
        } else {
            $this->stdout("2. Reset the migration table: TRUNCATE TABLE migration;\n");
            $this->stdout("3. Insert the squashed migration:\n");
            $this->stdout("   INSERT INTO migration (version, apply_time) VALUES ('{$className}', UNIX_TIMESTAMP());\n");
        }

        return ExitCode::OK;
    }

    /**
     * @return list<string>
     */
    private function getTables(Connection $db): array
    {
        $tables = $db->schema->getTableNames();
        $prefix = $db->tablePrefix;

        return array_values(array_filter($tables, function (string $table) use ($prefix): bool {
            // Exclude migration table
            $baseName = $prefix ? str_replace($prefix, '', $table) : $table;
            return $baseName !== 'migration';
        }));
    }

    private function stripPrefix(Connection $db, string $tableName): string
    {
        $prefix = $db->tablePrefix;
        if ($prefix && str_starts_with($tableName, $prefix)) {
            return substr($tableName, strlen($prefix));
        }
        return $tableName;
    }

    /**
     * @return list<string>
     */
    private function getExistingMigrations(string $path): array
    {
        $files = glob($path . '/m*.php');
        if ($files === false) {
            return [];
        }

        return array_map('basename', $files);
    }

    /**
     * @param list<string> $tables
     * @return array<string, array{columns: array, indexes: array, foreignKeys: array}>
     */
    private function generateSchema(Connection $db, array $tables): array
    {
        $schema = [];

        foreach ($tables as $tableName) {
            $tableSchema = $db->getTableSchema($tableName);
            if ($tableSchema === null) {
                continue;
            }

            $columns = [];
            foreach ($tableSchema->columns as $column) {
                $columns[$column->name] = $this->columnToDefinition($column);
            }

            $indexes = $this->getTableIndexes($db, $tableName);
            $foreignKeys = $this->getTableForeignKeys($db, $tableName);

            // Use stripped table name as key
            $strippedName = $this->stripPrefix($db, $tableName);
            $schema[$strippedName] = [
                'columns' => $columns,
                'primaryKey' => $tableSchema->primaryKey,
                'indexes' => $indexes,
                'foreignKeys' => $foreignKeys,
            ];
        }

        return $schema;
    }

    private function columnToDefinition(ColumnSchema $column): string
    {
        $definition = match ($column->type) {
            'integer' => $column->size === 1 ? '$this->tinyInteger()' :
                        ($column->size === 2 ? '$this->smallInteger()' : '$this->integer()'),
            'bigint' => '$this->bigInteger()',
            'smallint' => '$this->smallInteger()',
            'tinyint' => '$this->tinyInteger()',
            'string' => '$this->string(' . ($column->size ?? 255) . ')',
            'text' => '$this->text()',
            'boolean' => '$this->boolean()',
            'float' => '$this->float()',
            'double' => '$this->double()',
            'decimal' => '$this->decimal(' . ($column->precision ?? 10) . ', ' . ($column->scale ?? 0) . ')',
            'datetime' => '$this->dateTime()',
            'timestamp' => '$this->timestamp()',
            'time' => '$this->time()',
            'date' => '$this->date()',
            'binary' => '$this->binary()',
            'json' => '$this->json()',
            default => "\$this->string()",
        };

        if ($column->autoIncrement) {
            $definition = match ($column->type) {
                'bigint' => '$this->bigPrimaryKey()',
                default => '$this->primaryKey()',
            };
        } else {
            if (!$column->allowNull) {
                $definition .= '->notNull()';
            }

            if ($column->defaultValue !== null) {
                $default = $this->formatDefaultValue($column->defaultValue);
                $definition .= "->defaultValue({$default})";
            } elseif ($column->defaultValue === null && $column->allowNull) {
                $definition .= '->defaultValue(null)';
            }
        }

        if ($column->unsigned) {
            $definition .= '->unsigned()';
        }

        if ($column->comment) {
            $definition .= "->comment(" . var_export($column->comment, true) . ")";
        }

        return $definition;
    }

    private function formatDefaultValue(mixed $value): string
    {
        if ($value instanceof Expression) {
            return "new Expression(" . var_export((string) $value, true) . ")";
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return var_export($value, true);
    }

    /**
     * @return array<string, array{columns: list<string>, unique: bool}>
     */
    private function getTableIndexes(Connection $db, string $tableName): array
    {
        $indexes = [];
        $tableSchema = $db->getTableSchema($tableName);

        if ($tableSchema === null) {
            return [];
        }

        try {
            // Query SHOW INDEX to get all indexes including non-unique ones
            $sql = 'SHOW INDEX FROM ' . $db->quoteTableName($tableName);
            $rows = $db->createCommand($sql)->queryAll();

            $indexData = [];
            foreach ($rows as $row) {
                $indexName = $row['Key_name'];
                $columnName = $row['Column_name'];
                $nonUnique = (int) $row['Non_unique'];

                // Skip PRIMARY key - handled separately
                if ($indexName === 'PRIMARY') {
                    continue;
                }

                if (!isset($indexData[$indexName])) {
                    $indexData[$indexName] = [
                        'columns' => [],
                        'unique' => $nonUnique === 0,
                    ];
                }

                $indexData[$indexName]['columns'][] = $columnName;
            }

            // Filter out indexes that are just foreign key indexes (same name as FK)
            // These are auto-created by MySQL and will be recreated with the FK
            $fkNames = array_keys($tableSchema->foreignKeys);
            foreach ($indexData as $indexName => $index) {
                // Keep unique indexes and named indexes that aren't just FK indexes
                $isFkIndex = in_array($indexName, $fkNames, true);
                if (!$isFkIndex || $index['unique']) {
                    $indexes[$indexName] = $index;
                }
            }
        } catch (\Throwable $e) {
            Yii::warning("Could not retrieve indexes for {$tableName}: " . $e->getMessage());
        }

        return $indexes;
    }

    /**
     * @return array<string, array{columns: list<string>, refTable: string, refColumns: list<string>, onDelete: ?string, onUpdate: ?string}>
     */
    private function getTableForeignKeys(Connection $db, string $tableName): array
    {
        $foreignKeys = [];
        $tableSchema = $db->getTableSchema($tableName);

        if ($tableSchema === null) {
            return [];
        }

        // Get FK actions from information_schema
        $fkActions = $this->getForeignKeyActions($db, $tableName);

        foreach ($tableSchema->foreignKeys as $fkName => $fk) {
            $refTable = array_shift($fk);
            $columns = [];
            $refColumns = [];

            foreach ($fk as $column => $refColumn) {
                $columns[] = $column;
                $refColumns[] = $refColumn;
            }

            $foreignKeys[$fkName] = [
                'columns' => $columns,
                'refTable' => $this->stripPrefix($db, $refTable),
                'refColumns' => $refColumns,
                'onDelete' => $fkActions[$fkName]['onDelete'] ?? 'CASCADE',
                'onUpdate' => $fkActions[$fkName]['onUpdate'] ?? 'CASCADE',
            ];
        }

        return $foreignKeys;
    }

    /**
     * Get ON DELETE and ON UPDATE actions for foreign keys from information_schema.
     *
     * @return array<string, array{onDelete: string, onUpdate: string}>
     */
    private function getForeignKeyActions(Connection $db, string $tableName): array
    {
        $actions = [];
        $strippedName = $this->stripPrefix($db, $tableName);

        try {
            $sql = <<<SQL
                SELECT
                    CONSTRAINT_NAME,
                    DELETE_RULE,
                    UPDATE_RULE
                FROM information_schema.REFERENTIAL_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                  AND TABLE_NAME = :tableName
            SQL;

            $rows = $db->createCommand($sql, [':tableName' => $strippedName])->queryAll();

            foreach ($rows as $row) {
                $actions[$row['CONSTRAINT_NAME']] = [
                    'onDelete' => $row['DELETE_RULE'],
                    'onUpdate' => $row['UPDATE_RULE'],
                ];
            }
        } catch (\Throwable $e) {
            Yii::warning("Could not retrieve FK actions for {$tableName}: " . $e->getMessage());
        }

        return $actions;
    }

    /**
     * @param array<string, array{columns: array, primaryKey: array, indexes: array, foreignKeys: array}> $schema
     * @param list<string> $archivedMigrations
     */
    private function generateMigrationFile(string $name, array $schema, array $archivedMigrations): string
    {
        $timestamp = date('ymd_His');
        $className = "m{$timestamp}_{$name}";

        $upCode = $this->generateUpCode($schema);
        $downCode = $this->generateDownCode($schema);

        $archivedList = implode("\n *   - ", $archivedMigrations);

        return <<<PHP
<?php

declare(strict_types=1);

use yii\db\Expression;
use yii\db\Migration;

/**
 * Database schema - structure only, no seed data.
 *
 * Auto-generated by squash-migrations command. Consolidates:
 *   - {$archivedList}
 */
final class {$className} extends Migration
{
    public function safeUp(): void
    {
{$upCode}
    }

    public function safeDown(): void
    {
{$downCode}
    }
}

PHP;
    }

    /**
     * @param array<string, array{columns: array, primaryKey: array, indexes: array, foreignKeys: array}> $schema
     */
    private function generateUpCode(array $schema): string
    {
        $lines = [];

        // Create tables first (without foreign keys)
        foreach ($schema as $tableName => $table) {
            $lines[] = "        // Table: {$tableName}";
            $lines[] = "        \$this->createTable('{{%{$tableName}}}', [";

            foreach ($table['columns'] as $columnName => $definition) {
                $lines[] = "            '{$columnName}' => {$definition},";
            }

            // Add explicit PRIMARY KEY for non-auto-increment primary keys
            if (!empty($table['primaryKey']) && !$this->hasAutoIncrementPrimaryKey($table)) {
                $pkColumns = implode(']], [[', $table['primaryKey']);
                $lines[] = "            'PRIMARY KEY ([[{$pkColumns}]])',";
            }

            $lines[] = "        ]);";
            $lines[] = "";

            // Add indexes (both unique and non-unique)
            foreach ($table['indexes'] as $indexName => $index) {
                $columns = "'" . implode("', '", $index['columns']) . "'";
                $unique = $index['unique'] ? 'true' : 'false';
                $lines[] = "        \$this->createIndex('{$indexName}', '{{%{$tableName}}}', [{$columns}], {$unique});";
            }

            if (!empty($table['indexes'])) {
                $lines[] = "";
            }
        }

        // Add foreign keys after all tables are created
        foreach ($schema as $tableName => $table) {
            foreach ($table['foreignKeys'] as $fkName => $fk) {
                $columns = "'" . implode("', '", $fk['columns']) . "'";
                $refColumns = "'" . implode("', '", $fk['refColumns']) . "'";
                $onDelete = $fk['onDelete'] ?? 'CASCADE';
                $onUpdate = $fk['onUpdate'] ?? 'CASCADE';
                $lines[] = "        \$this->addForeignKey(";
                $lines[] = "            '{$fkName}',";
                $lines[] = "            '{{%{$tableName}}}',";
                $lines[] = "            [{$columns}],";
                $lines[] = "            '{{%{$fk['refTable']}}}',";
                $lines[] = "            [{$refColumns}],";
                $lines[] = "            '{$onDelete}',";
                $lines[] = "            '{$onUpdate}'";
                $lines[] = "        );";
                $lines[] = "";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, array{columns: array, primaryKey: array, indexes: array, foreignKeys: array}> $schema
     */
    private function generateDownCode(array $schema): string
    {
        $lines = [];

        // Drop foreign keys first
        foreach ($schema as $tableName => $table) {
            foreach ($table['foreignKeys'] as $fkName => $fk) {
                $lines[] = "        \$this->dropForeignKey('{$fkName}', '{{%{$tableName}}}');";
            }
        }

        if (!empty($lines)) {
            $lines[] = "";
        }

        // Drop tables in dependency order (tables with FKs first)
        $sorted = $this->sortTablesByDependency($schema);
        foreach ($sorted as $tableName) {
            $lines[] = "        \$this->dropTable('{{%{$tableName}}}');";
        }

        return implode("\n", $lines);
    }

    /**
     * Sort tables so that tables with foreign keys come before the tables they reference.
     *
     * @param array<string, array{columns: array, primaryKey: array, indexes: array, foreignKeys: array}> $schema
     * @return list<string>
     */
    private function sortTablesByDependency(array $schema): array
    {
        $tables = array_keys($schema);
        $dependencies = [];

        foreach ($schema as $tableName => $table) {
            $dependencies[$tableName] = [];
            foreach ($table['foreignKeys'] as $fk) {
                if ($fk['refTable'] !== $tableName && in_array($fk['refTable'], $tables, true)) {
                    $dependencies[$tableName][] = $fk['refTable'];
                }
            }
        }

        // Topological sort: tables that depend on others come first
        $sorted = [];
        $visited = [];

        $visit = function (string $table) use (&$visit, &$sorted, &$visited, $dependencies): void {
            if (isset($visited[$table])) {
                return;
            }
            $visited[$table] = true;

            foreach ($dependencies[$table] ?? [] as $dep) {
                $visit($dep);
            }

            array_unshift($sorted, $table);
        };

        foreach ($tables as $table) {
            $visit($table);
        }

        return $sorted;
    }

    /**
     * Check if a table has an auto-increment primary key.
     *
     * @param array{columns: array<string, string>, primaryKey: list<string>, indexes: array, foreignKeys: array} $table
     */
    private function hasAutoIncrementPrimaryKey(array $table): bool
    {
        foreach ($table['columns'] as $definition) {
            if (str_contains($definition, 'primaryKey()') || str_contains($definition, 'bigPrimaryKey()')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a seed migration for reference data.
     *
     * @return string|null The filename if successful, null on failure
     */
    private function generateSeedMigration(Connection $db, string $migrationPath): ?string
    {
        $seedData = $this->collectSeedData($db);
        if (empty($seedData)) {
            $this->stdout("No seed data found in tables: " . implode(', ', $this->seedTables) . "\n", Console::FG_YELLOW);
            return null;
        }

        $timestamp = date('ymd_His', strtotime('+1 second'));
        $className = "m{$timestamp}_initial_seed";
        $filename = "{$className}.php";
        $filepath = $migrationPath . '/' . $filename;

        $content = $this->generateSeedMigrationContent($className, $seedData);

        if (file_put_contents($filepath, $content) === false) {
            return null;
        }

        return $filename;
    }

    /**
     * Collect seed data from configured tables.
     *
     * @return array<string, array{columns: list<string>, rows: list<list<mixed>>}>
     */
    private function collectSeedData(Connection $db): array
    {
        $seedData = [];

        foreach ($this->seedTables as $tableName) {
            $fullTableName = $db->tablePrefix . $tableName;
            $tableSchema = $db->getTableSchema($fullTableName);

            if ($tableSchema === null) {
                $this->stderr("Seed table not found: {$tableName}\n", Console::FG_YELLOW);
                continue;
            }

            // Get column names (exclude auto-generated timestamps)
            $excludeColumns = ['created_at', 'updated_at'];
            $columns = array_filter(
                array_keys($tableSchema->columns),
                fn (string $col) => !in_array($col, $excludeColumns, true)
            );
            $columns = array_values($columns);

            // Fetch all rows
            $rows = $db->createCommand("SELECT * FROM {$db->quoteTableName($fullTableName)}")->queryAll();

            if (empty($rows)) {
                continue;
            }

            // Extract only the columns we want
            $seedRows = [];
            foreach ($rows as $row) {
                $seedRow = [];
                foreach ($columns as $col) {
                    $seedRow[] = $row[$col] ?? null;
                }
                $seedRows[] = $seedRow;
            }

            $seedData[$tableName] = [
                'columns' => $columns,
                'rows' => $seedRows,
            ];
        }

        return $seedData;
    }

    /**
     * Generate the seed migration file content.
     *
     * @param array<string, array{columns: list<string>, rows: list<list<mixed>>}> $seedData
     */
    private function generateSeedMigrationContent(string $className, array $seedData): string
    {
        $upCode = $this->generateSeedUpCode($seedData);
        $downCode = $this->generateSeedDownCode($seedData);
        $tableList = implode(', ', array_keys($seedData));

        return <<<PHP
<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Seeds initial reference data required to run the application.
 *
 * This migration populates: {$tableList}
 *
 * Auto-generated by squash-migrations --with-seed command.
 */
final class {$className} extends Migration
{
    public function safeUp(): void
    {
{$upCode}
    }

    public function safeDown(): void
    {
{$downCode}
    }
}

PHP;
    }

    /**
     * Generate the safeUp code for seed migration.
     *
     * @param array<string, array{columns: list<string>, rows: list<list<mixed>>}> $seedData
     */
    private function generateSeedUpCode(array $seedData): string
    {
        $lines = [];

        foreach ($seedData as $tableName => $data) {
            $columnsStr = "['" . implode("', '", $data['columns']) . "']";
            $lines[] = "        \$this->batchInsert('{{%{$tableName}}}', {$columnsStr}, [";

            foreach ($data['rows'] as $row) {
                $values = array_map(fn ($v) => $this->formatSeedValue($v), $row);
                $lines[] = "            [" . implode(', ', $values) . "],";
            }

            $lines[] = "        ]);";
            $lines[] = "";
        }

        return implode("\n", $lines);
    }

    /**
     * Generate the safeDown code for seed migration.
     *
     * @param array<string, array{columns: list<string>, rows: list<list<mixed>>}> $seedData
     */
    private function generateSeedDownCode(array $seedData): string
    {
        $lines = [];

        foreach ($seedData as $tableName => $data) {
            // Find the primary key column (assume first column is PK for simplicity)
            $pkColumn = $data['columns'][0];
            $pkValues = array_map(fn ($row) => $this->formatSeedValue($row[0]), $data['rows']);

            $lines[] = "        \$this->delete('{{%{$tableName}}}', [";
            $lines[] = "            '{$pkColumn}' => [" . implode(', ', $pkValues) . "],";
            $lines[] = "        ]);";
            $lines[] = "";
        }

        return implode("\n", $lines);
    }

    /**
     * Format a value for use in seed migration code.
     */
    private function formatSeedValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        // Escape single quotes in strings
        return "'" . str_replace("'", "\\'", (string) $value) . "'";
    }
}
