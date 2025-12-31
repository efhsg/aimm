<?php

declare(strict_types=1);

namespace tests\unit\validators;

use app\dto\ValidationResult;
use app\validators\IndustryConfigJsonValidator;
use app\validators\SchemaValidatorInterface;
use Codeception\Test\Unit;
use yii\base\DynamicModel;

/**
 * @covers \app\validators\IndustryConfigJsonValidator
 */
final class IndustryConfigJsonValidatorTest extends Unit
{
    public function testRejectsInvalidJsonSyntax(): void
    {
        $model = $this->createModel('industry_oil', '{invalid json');

        $validator = $this->createValidator();
        $validator->validateAttribute($model, 'config_json');

        $this->assertTrue($model->hasErrors('config_json'));
        $this->assertStringContainsString('syntax', strtolower($model->getFirstError('config_json')));
    }

    public function testRejectsEmptyString(): void
    {
        $model = $this->createModel('industry_oil', '');

        $validator = $this->createValidator();
        $validator->validateAttribute($model, 'config_json');

        $this->assertTrue($model->hasErrors('config_json'));
        $this->assertStringContainsString('required', strtolower($model->getFirstError('config_json')));
    }

    public function testRejectsSchemaViolation(): void
    {
        $json = json_encode(['id' => 'industry_oil', 'name' => 'Oil']);

        $schemaValidator = $this->createMock(SchemaValidatorInterface::class);
        $schemaValidator->method('validate')->willReturn(
            new ValidationResult(valid: false, errors: ['Missing required field: sector'])
        );

        $model = $this->createModel('industry_oil', $json);

        $validator = $this->createValidator($schemaValidator);
        $validator->validateAttribute($model, 'config_json');

        $this->assertTrue($model->hasErrors('config_json'));
        $this->assertStringContainsString('sector', strtolower($model->getFirstError('config_json')));
    }

    public function testRejectsIdMismatch(): void
    {
        $json = json_encode([
            'id' => 'wrong_id',
            'name' => 'Oil Industry',
            'sector' => 'Energy',
            'companies' => [['ticker' => 'XOM', 'name' => 'ExxonMobil', 'listing_exchange' => 'NYSE', 'listing_currency' => 'USD', 'reporting_currency' => 'USD', 'fy_end_month' => 12]],
            'macro_requirements' => [],
            'data_requirements' => ['history_years' => 5, 'quarters_to_fetch' => 4, 'valuation_metrics' => []],
        ]);

        $schemaValidator = $this->createMock(SchemaValidatorInterface::class);
        $schemaValidator->method('validate')->willReturn(
            new ValidationResult(valid: true, errors: [])
        );

        $model = $this->createModel('industry_oil', $json);

        $validator = $this->createValidator($schemaValidator);
        $validator->validateAttribute($model, 'config_json');

        $this->assertTrue($model->hasErrors('config_json'));
        $this->assertStringContainsString('must match', strtolower($model->getFirstError('config_json')));
    }

    public function testAcceptsValidConfig(): void
    {
        $json = json_encode([
            'id' => 'industry_oil',
            'name' => 'Oil Industry',
            'sector' => 'Energy',
            'companies' => [['ticker' => 'XOM', 'name' => 'ExxonMobil', 'listing_exchange' => 'NYSE', 'listing_currency' => 'USD', 'reporting_currency' => 'USD', 'fy_end_month' => 12]],
            'macro_requirements' => [],
            'data_requirements' => ['history_years' => 5, 'quarters_to_fetch' => 4, 'valuation_metrics' => []],
        ]);

        $schemaValidator = $this->createMock(SchemaValidatorInterface::class);
        $schemaValidator->method('validate')->willReturn(
            new ValidationResult(valid: true, errors: [])
        );

        $model = $this->createModel('industry_oil', $json);

        $validator = $this->createValidator($schemaValidator);
        $validator->validateAttribute($model, 'config_json');

        $this->assertFalse($model->hasErrors('config_json'));
    }

    public function testRejectsMalformedUtf8(): void
    {
        $malformedJson = '{"id": "test", "invalid": "' . "\xB1\x31" . '"}';

        $model = $this->createModel('test', $malformedJson);

        $validator = $this->createValidator();
        $validator->validateAttribute($model, 'config_json');

        $this->assertTrue($model->hasErrors('config_json'));
    }

    public function testRejectsNonObjectJson(): void
    {
        $json = '["array", "not", "object"]';

        $schemaValidator = $this->createMock(SchemaValidatorInterface::class);
        $schemaValidator->method('validate')->willReturn(
            new ValidationResult(valid: true, errors: [])
        );

        $model = $this->createModel('test', $json);

        $validator = $this->createValidator($schemaValidator);
        $validator->validateAttribute($model, 'config_json');

        $this->assertTrue($model->hasErrors('config_json'));
        $this->assertStringContainsString('object', strtolower($model->getFirstError('config_json')));
    }

    public function testRejectsJsonWithoutIdField(): void
    {
        $json = json_encode(['name' => 'Oil Industry']);

        $schemaValidator = $this->createMock(SchemaValidatorInterface::class);
        $schemaValidator->method('validate')->willReturn(
            new ValidationResult(valid: true, errors: [])
        );

        $model = $this->createModel('industry_oil', $json);

        $validator = $this->createValidator($schemaValidator);
        $validator->validateAttribute($model, 'config_json');

        $this->assertTrue($model->hasErrors('config_json'));
        $this->assertStringContainsString('id', strtolower($model->getFirstError('config_json')));
    }

    public function testValidateValueReturnsNullForValidJson(): void
    {
        $json = json_encode([
            'id' => 'industry_oil',
            'name' => 'Oil Industry',
        ]);

        $schemaValidator = $this->createMock(SchemaValidatorInterface::class);
        $schemaValidator->method('validate')->willReturn(
            new ValidationResult(valid: true, errors: [])
        );

        $validator = $this->createValidator($schemaValidator);

        $reflection = new \ReflectionMethod($validator, 'validateValue');
        $reflection->setAccessible(true);
        $result = $reflection->invoke($validator, $json);

        $this->assertNull($result);
    }

    public function testValidateValueReturnsErrorForInvalidJson(): void
    {
        $validator = $this->createValidator();

        $reflection = new \ReflectionMethod($validator, 'validateValue');
        $reflection->setAccessible(true);
        $result = $reflection->invoke($validator, '{invalid}');

        $this->assertIsArray($result);
        $this->assertStringContainsString('syntax', strtolower($result[0]));
    }

    private function createValidator(?SchemaValidatorInterface $schemaValidator = null): IndustryConfigJsonValidator
    {
        if ($schemaValidator === null) {
            $schemaValidator = $this->createMock(SchemaValidatorInterface::class);
            $schemaValidator->method('validate')->willReturn(
                new ValidationResult(valid: true, errors: [])
            );
        }

        $validator = new IndustryConfigJsonValidator();
        $validator->schemaValidator = $schemaValidator;

        return $validator;
    }

    private function createModel(string $industryId, string $configJson): DynamicModel
    {
        return new DynamicModel([
            'industry_id' => $industryId,
            'config_json' => $configJson,
        ]);
    }
}
