<?php

declare(strict_types=1);

namespace tests\unit\commands;

use app\commands\SquashMigrationsController;
use Codeception\Test\Unit;
use ReflectionMethod;
use Yii;
use yii\db\ColumnSchema;
use yii\db\Expression;

/**
 * Tests for migration squashing logic: column definitions, default values, and dependency sorting.
 */
final class SquashMigrationsControllerTest extends Unit
{
    private SquashMigrationsController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new SquashMigrationsController('squash-migrations', Yii::$app);
    }

    // columnToDefinition tests

    public function testColumnToDefinitionReturnsIntegerForStandardInt(): void
    {
        $column = $this->createColumn('integer');

        $result = $this->invokeColumnToDefinition($column);

        $this->assertSame('$this->integer()->notNull()', $result);
    }

    public function testColumnToDefinitionReturnsTinyIntegerWhenSizeIsOne(): void
    {
        $column = $this->createColumn('integer', size: 1);

        $result = $this->invokeColumnToDefinition($column);

        $this->assertSame('$this->tinyInteger()->notNull()', $result);
    }

    public function testColumnToDefinitionReturnsSmallIntegerWhenSizeIsTwo(): void
    {
        $column = $this->createColumn('integer', size: 2);

        $result = $this->invokeColumnToDefinition($column);

        $this->assertSame('$this->smallInteger()->notNull()', $result);
    }

    public function testColumnToDefinitionReturnsBigInteger(): void
    {
        $column = $this->createColumn('bigint');

        $result = $this->invokeColumnToDefinition($column);

        $this->assertSame('$this->bigInteger()->notNull()', $result);
    }

    public function testColumnToDefinitionReturnsStringWithSize(): void
    {
        $column = $this->createColumn('string', size: 100);

        $result = $this->invokeColumnToDefinition($column);

        $this->assertSame('$this->string(100)->notNull()', $result);
    }

    public function testColumnToDefinitionReturnsText(): void
    {
        $column = $this->createColumn('text');

        $result = $this->invokeColumnToDefinition($column);

        $this->assertSame('$this->text()->notNull()', $result);
    }

    public function testColumnToDefinitionReturnsBoolean(): void
    {
        $column = $this->createColumn('boolean');

        $result = $this->invokeColumnToDefinition($column);

        $this->assertSame('$this->boolean()->notNull()', $result);
    }

    public function testColumnToDefinitionReturnsDecimalWithPrecisionAndScale(): void
    {
        $column = $this->createColumn('decimal', precision: 12, scale: 4);

        $result = $this->invokeColumnToDefinition($column);

        $this->assertSame('$this->decimal(12, 4)->notNull()', $result);
    }

    public function testColumnToDefinitionReturnsDateTime(): void
    {
        $column = $this->createColumn('datetime');

        $result = $this->invokeColumnToDefinition($column);

        $this->assertSame('$this->dateTime()->notNull()', $result);
    }

    public function testColumnToDefinitionReturnsJson(): void
    {
        $column = $this->createColumn('json');

        $result = $this->invokeColumnToDefinition($column);

        $this->assertSame('$this->json()->notNull()', $result);
    }

    public function testColumnToDefinitionReturnsPrimaryKeyForAutoIncrement(): void
    {
        $column = $this->createColumn('integer', autoIncrement: true);

        $result = $this->invokeColumnToDefinition($column);

        $this->assertSame('$this->primaryKey()', $result);
    }

    public function testColumnToDefinitionReturnsBigPrimaryKeyForBigintAutoIncrement(): void
    {
        $column = $this->createColumn('bigint', autoIncrement: true);

        $result = $this->invokeColumnToDefinition($column);

        $this->assertSame('$this->bigPrimaryKey()', $result);
    }

    public function testColumnToDefinitionAddsNullableDefault(): void
    {
        $column = $this->createColumn('string', allowNull: true, size: 255);

        $result = $this->invokeColumnToDefinition($column);

        $this->assertSame('$this->string(255)->defaultValue(null)', $result);
    }

    public function testColumnToDefinitionAddsDefaultValue(): void
    {
        $column = $this->createColumn('string', defaultValue: 'active', size: 50);

        $result = $this->invokeColumnToDefinition($column);

        $this->assertSame("\$this->string(50)->notNull()->defaultValue('active')", $result);
    }

    public function testColumnToDefinitionAddsUnsigned(): void
    {
        $column = $this->createColumn('integer', unsigned: true);

        $result = $this->invokeColumnToDefinition($column);

        $this->assertSame('$this->integer()->notNull()->unsigned()', $result);
    }

    public function testColumnToDefinitionAddsComment(): void
    {
        $column = $this->createColumn('string', comment: 'User status', size: 20);

        $result = $this->invokeColumnToDefinition($column);

        $this->assertSame("\$this->string(20)->notNull()->comment('User status')", $result);
    }

    public function testColumnToDefinitionReturnsDefaultStringForUnknownType(): void
    {
        $column = $this->createColumn('unknown_type');

        $result = $this->invokeColumnToDefinition($column);

        $this->assertSame('$this->string()->notNull()', $result);
    }

    // formatDefaultValue tests

    public function testFormatDefaultValueReturnsExpressionSyntax(): void
    {
        $expression = new Expression('CURRENT_TIMESTAMP');

        $result = $this->invokeFormatDefaultValue($expression);

        $this->assertSame("new Expression('CURRENT_TIMESTAMP')", $result);
    }

    public function testFormatDefaultValueReturnsTrueForBooleanTrue(): void
    {
        $result = $this->invokeFormatDefaultValue(true);

        $this->assertSame('true', $result);
    }

    public function testFormatDefaultValueReturnsFalseForBooleanFalse(): void
    {
        $result = $this->invokeFormatDefaultValue(false);

        $this->assertSame('false', $result);
    }

    public function testFormatDefaultValueReturnsIntegerAsString(): void
    {
        $result = $this->invokeFormatDefaultValue(42);

        $this->assertSame('42', $result);
    }

    public function testFormatDefaultValueReturnsFloatAsString(): void
    {
        $result = $this->invokeFormatDefaultValue(3.14);

        $this->assertSame('3.14', $result);
    }

    public function testFormatDefaultValueReturnsQuotedString(): void
    {
        $result = $this->invokeFormatDefaultValue('hello');

        $this->assertSame("'hello'", $result);
    }

    public function testFormatDefaultValueEscapesSpecialCharacters(): void
    {
        $result = $this->invokeFormatDefaultValue("it's a test");

        $this->assertSame("'it\\'s a test'", $result);
    }

    // sortTablesByDependency tests

    public function testSortTablesByDependencyReturnsTablesInOrder(): void
    {
        $schema = [
            'users' => $this->createTableSchema([]),
            'posts' => $this->createTableSchema([
                'fk_posts_user' => ['refTable' => 'users'],
            ]),
        ];

        $result = $this->invokeSortTablesByDependency($schema);

        $this->assertSame(['posts', 'users'], $result);
    }

    public function testSortTablesByDependencyHandlesMultipleDependencies(): void
    {
        $schema = [
            'users' => $this->createTableSchema([]),
            'categories' => $this->createTableSchema([]),
            'posts' => $this->createTableSchema([
                'fk_posts_user' => ['refTable' => 'users'],
                'fk_posts_category' => ['refTable' => 'categories'],
            ]),
        ];

        $result = $this->invokeSortTablesByDependency($schema);

        $postsIndex = array_search('posts', $result, true);
        $usersIndex = array_search('users', $result, true);
        $categoriesIndex = array_search('categories', $result, true);

        $this->assertLessThan($usersIndex, $postsIndex);
        $this->assertLessThan($categoriesIndex, $postsIndex);
    }

    public function testSortTablesByDependencyHandlesChainedDependencies(): void
    {
        $schema = [
            'users' => $this->createTableSchema([]),
            'posts' => $this->createTableSchema([
                'fk_posts_user' => ['refTable' => 'users'],
            ]),
            'comments' => $this->createTableSchema([
                'fk_comments_post' => ['refTable' => 'posts'],
            ]),
        ];

        $result = $this->invokeSortTablesByDependency($schema);

        $this->assertSame(['comments', 'posts', 'users'], $result);
    }

    public function testSortTablesByDependencyIgnoresSelfReferences(): void
    {
        $schema = [
            'categories' => $this->createTableSchema([
                'fk_parent' => ['refTable' => 'categories'],
            ]),
        ];

        $result = $this->invokeSortTablesByDependency($schema);

        $this->assertSame(['categories'], $result);
    }

    public function testSortTablesByDependencyIgnoresExternalReferences(): void
    {
        $schema = [
            'posts' => $this->createTableSchema([
                'fk_external' => ['refTable' => 'external_table'],
            ]),
        ];

        $result = $this->invokeSortTablesByDependency($schema);

        $this->assertSame(['posts'], $result);
    }

    public function testSortTablesByDependencyHandlesNoDependencies(): void
    {
        $schema = [
            'table_a' => $this->createTableSchema([]),
            'table_b' => $this->createTableSchema([]),
            'table_c' => $this->createTableSchema([]),
        ];

        $result = $this->invokeSortTablesByDependency($schema);

        $this->assertCount(3, $result);
        $this->assertContains('table_a', $result);
        $this->assertContains('table_b', $result);
        $this->assertContains('table_c', $result);
    }

    // Helper methods

    private function createColumn(
        string $type,
        ?int $size = null,
        ?int $precision = null,
        ?int $scale = null,
        bool $allowNull = false,
        bool $autoIncrement = false,
        bool $unsigned = false,
        mixed $defaultValue = null,
        ?string $comment = null,
    ): ColumnSchema {
        $column = new ColumnSchema();
        $column->type = $type;
        $column->size = $size;
        $column->precision = $precision;
        $column->scale = $scale;
        $column->allowNull = $allowNull;
        $column->autoIncrement = $autoIncrement;
        $column->unsigned = $unsigned;
        $column->defaultValue = $defaultValue;
        $column->comment = $comment;

        return $column;
    }

    /**
     * @param array<string, array{refTable: string}> $foreignKeys
     * @return array{columns: array, primaryKey: array, indexes: array, foreignKeys: array}
     */
    private function createTableSchema(array $foreignKeys): array
    {
        return [
            'columns' => [],
            'primaryKey' => [],
            'indexes' => [],
            'foreignKeys' => $foreignKeys,
        ];
    }

    private function invokeColumnToDefinition(ColumnSchema $column): string
    {
        $method = new ReflectionMethod($this->controller, 'columnToDefinition');

        return $method->invoke($this->controller, $column);
    }

    private function invokeFormatDefaultValue(mixed $value): string
    {
        $method = new ReflectionMethod($this->controller, 'formatDefaultValue');

        return $method->invoke($this->controller, $value);
    }

    /**
     * @param array<string, array{columns: array, primaryKey: array, indexes: array, foreignKeys: array}> $schema
     * @return list<string>
     */
    private function invokeSortTablesByDependency(array $schema): array
    {
        $method = new ReflectionMethod($this->controller, 'sortTablesByDependency');

        return $method->invoke($this->controller, $schema);
    }
}
