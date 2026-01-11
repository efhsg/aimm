# Data Source Admin CRUD Implementation Plan

This document provides a detailed implementation plan for adding an admin CRUD to manage the `data_source` table.

## Overview

The `data_source` table tracks external data providers (APIs, web scrapers, internal calculations) used throughout AIMM. Each financial data point has a `provider_id` foreign key linking to this table, ensuring complete data provenance.

### Table Schema

| Column | Type | Constraints | Purpose |
|--------|------|-------------|---------|
| `id` | VARCHAR(50) | PRIMARY KEY | Unique identifier (e.g., 'fmp', 'yahoo_finance') |
| `name` | VARCHAR(100) | NOT NULL | Display name |
| `source_type` | VARCHAR(20) | NOT NULL | Type: 'api', 'web_scrape', or 'derived' |
| `base_url` | VARCHAR(255) | nullable | Base URL for web-based sources |
| `is_active` | TINYINT(1) | NOT NULL, DEFAULT 1 | Active/inactive toggle |
| `notes` | TEXT | nullable | Documentation about the source |
| `created_at` | TIMESTAMP | NOT NULL, auto | Creation timestamp |
| `updated_at` | TIMESTAMP | NOT NULL, auto | Last update timestamp |

### Foreign Key Usage

This table is referenced by multiple tables via `provider_id`:
- `annual_financial`, `quarterly_financial`, `ttm_financial`
- `valuation_snapshot`, `price_history`, `macro_indicator`

**Constraint behavior:** RESTRICT on delete (cannot delete a source with data), CASCADE on update.

---

## Required Skills

Load these skills before implementation:

| Skill | File | Purpose |
|-------|------|---------|
| Frontend Design | `.claude/skills/frontend-design.md` | UI patterns, BEM, design tokens |
| Create Migration | `.claude/skills/create-migration.md` | If schema changes are needed |
| Review Changes | `.claude/skills/review-changes.md` | Code review before commit |
| Finalize Changes | `.claude/commands/finalize-changes.md` | Run linter and tests |

Also reference:
- `.claude/rules/architecture.md` — Folder taxonomy and patterns
- `.claude/rules/coding-standards.md` — PHP standards
- `.claude/rules/testing.md` — Test requirements
- `.claude/config/project.md` — Commands and file paths

---

## Implementation Steps

### Phase 1: Model Layer

#### 1.1 Create DataSource Model

**File:** `yii/src/models/DataSource.php`

Create an ActiveRecord model with:

```php
<?php

declare(strict_types=1);

namespace app\models;

use app\queries\DataSourceQuery;
use yii\db\ActiveRecord;

/**
 * ActiveRecord model for data_source table.
 *
 * @property string $id
 * @property string $name
 * @property string $source_type
 * @property string|null $base_url
 * @property int $is_active
 * @property string|null $notes
 * @property string $created_at
 * @property string $updated_at
 */
final class DataSource extends ActiveRecord
{
    public const SOURCE_TYPE_API = 'api';
    public const SOURCE_TYPE_WEB_SCRAPE = 'web_scrape';
    public const SOURCE_TYPE_DERIVED = 'derived';

    public static function tableName(): string
    {
        return 'data_source';
    }

    public static function find(): DataSourceQuery
    {
        return new DataSourceQuery(static::class);
    }

    public function rules(): array
    {
        return [
            [['id', 'name', 'source_type'], 'required'],
            [['id'], 'string', 'max' => 50],
            [['name'], 'string', 'max' => 100],
            [['source_type'], 'string', 'max' => 20],
            [['source_type'], 'in', 'range' => [
                self::SOURCE_TYPE_API,
                self::SOURCE_TYPE_WEB_SCRAPE,
                self::SOURCE_TYPE_DERIVED,
            ]],
            [['base_url'], 'string', 'max' => 255],
            [['base_url'], 'url', 'skipOnEmpty' => true],
            [['is_active'], 'boolean'],
            [['notes'], 'string'],
            [['id'], 'unique'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'source_type' => 'Source Type',
            'base_url' => 'Base URL',
            'is_active' => 'Active',
            'notes' => 'Notes',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }
}
```

#### 1.2 Create DataSourceQuery

**File:** `yii/src/queries/DataSourceQuery.php`

```php
<?php

declare(strict_types=1);

namespace app\queries;

use app\models\DataSource;
use yii\db\ActiveQuery;

final class DataSourceQuery extends ActiveQuery
{
    public function active(): self
    {
        return $this->andWhere(['is_active' => 1]);
    }

    public function inactive(): self
    {
        return $this->andWhere(['is_active' => 0]);
    }

    public function ofType(string $sourceType): self
    {
        return $this->andWhere(['source_type' => $sourceType]);
    }

    public function alphabetical(): self
    {
        return $this->orderBy(['name' => SORT_ASC]);
    }

    /**
     * Find all data sources as arrays.
     *
     * @return array<string, mixed>[]
     */
    public function findAll(): array
    {
        return $this->asArray()->all();
    }

    /**
     * Find a data source by ID.
     */
    public function findById(string $id): ?array
    {
        return $this->andWhere(['id' => $id])->asArray()->one();
    }

    /**
     * Get counts by status.
     *
     * @return array{total: int, active: int, inactive: int}
     */
    public function getCounts(): array
    {
        $total = (int) (clone $this)->count();
        $active = (int) (clone $this)->active()->count();

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
        ];
    }

    public function all($db = null): array
    {
        return parent::all($db);
    }

    public function one($db = null): DataSource|array|null
    {
        return parent::one($db);
    }
}
```

---

### Phase 2: DTOs and Handlers

#### 2.1 Create Request DTOs

**File:** `yii/src/dto/datasource/CreateDataSourceRequest.php`

```php
<?php

declare(strict_types=1);

namespace app\dto\datasource;

final readonly class CreateDataSourceRequest
{
    public function __construct(
        public string $id,
        public string $name,
        public string $sourceType,
        public string $actorUsername,
        public ?string $baseUrl = null,
        public ?string $notes = null,
    ) {}
}
```

**File:** `yii/src/dto/datasource/UpdateDataSourceRequest.php`

```php
<?php

declare(strict_types=1);

namespace app\dto\datasource;

final readonly class UpdateDataSourceRequest
{
    public function __construct(
        public string $id,
        public string $name,
        public string $sourceType,
        public string $actorUsername,
        public ?string $baseUrl = null,
        public ?string $notes = null,
    ) {}
}
```

**File:** `yii/src/dto/datasource/ToggleDataSourceRequest.php`

```php
<?php

declare(strict_types=1);

namespace app\dto\datasource;

final readonly class ToggleDataSourceRequest
{
    public function __construct(
        public string $id,
        public string $actorUsername,
    ) {}
}
```

**File:** `yii/src/dto/datasource/DeleteDataSourceRequest.php`

```php
<?php

declare(strict_types=1);

namespace app\dto\datasource;

final readonly class DeleteDataSourceRequest
{
    public function __construct(
        public string $id,
        public string $actorUsername,
    ) {}
}
```

#### 2.2 Create Result DTO

**File:** `yii/src/dto/datasource/SaveDataSourceResult.php`

```php
<?php

declare(strict_types=1);

namespace app\dto\datasource;

final readonly class SaveDataSourceResult
{
    /**
     * @param array<string, mixed>|null $dataSource
     * @param string[] $errors
     */
    public function __construct(
        public bool $success,
        public ?array $dataSource = null,
        public array $errors = [],
    ) {}

    public static function success(array $dataSource): self
    {
        return new self(true, $dataSource);
    }

    /**
     * @param string[] $errors
     */
    public static function failure(array $errors): self
    {
        return new self(false, null, $errors);
    }
}
```

#### 2.3 Create Handler Interfaces

**File:** `yii/src/handlers/datasource/CreateDataSourceInterface.php`

```php
<?php

declare(strict_types=1);

namespace app\handlers\datasource;

use app\dto\datasource\CreateDataSourceRequest;
use app\dto\datasource\SaveDataSourceResult;

interface CreateDataSourceInterface
{
    public function create(CreateDataSourceRequest $request): SaveDataSourceResult;
}
```

**File:** `yii/src/handlers/datasource/UpdateDataSourceInterface.php`

```php
<?php

declare(strict_types=1);

namespace app\handlers\datasource;

use app\dto\datasource\SaveDataSourceResult;
use app\dto\datasource\UpdateDataSourceRequest;

interface UpdateDataSourceInterface
{
    public function update(UpdateDataSourceRequest $request): SaveDataSourceResult;
}
```

**File:** `yii/src/handlers/datasource/ToggleDataSourceInterface.php`

```php
<?php

declare(strict_types=1);

namespace app\handlers\datasource;

use app\dto\datasource\SaveDataSourceResult;
use app\dto\datasource\ToggleDataSourceRequest;

interface ToggleDataSourceInterface
{
    public function toggle(ToggleDataSourceRequest $request): SaveDataSourceResult;
}
```

**File:** `yii/src/handlers/datasource/DeleteDataSourceInterface.php`

```php
<?php

declare(strict_types=1);

namespace app\handlers\datasource;

use app\dto\datasource\DeleteDataSourceRequest;
use app\dto\datasource\SaveDataSourceResult;

interface DeleteDataSourceInterface
{
    public function delete(DeleteDataSourceRequest $request): SaveDataSourceResult;
}
```

#### 2.4 Create Handler Implementations

**File:** `yii/src/handlers/datasource/CreateDataSourceHandler.php`

```php
<?php

declare(strict_types=1);

namespace app\handlers\datasource;

use app\dto\datasource\CreateDataSourceRequest;
use app\dto\datasource\SaveDataSourceResult;
use app\models\DataSource;
use Yii;

final class CreateDataSourceHandler implements CreateDataSourceInterface
{
    public function create(CreateDataSourceRequest $request): SaveDataSourceResult
    {
        $model = new DataSource();
        $model->id = $request->id;
        $model->name = $request->name;
        $model->source_type = $request->sourceType;
        $model->base_url = $request->baseUrl;
        $model->notes = $request->notes;
        $model->is_active = 1;

        if (!$model->validate()) {
            return SaveDataSourceResult::failure(
                array_values(array_map(
                    fn(array $errors) => $errors[0],
                    $model->getErrors()
                ))
            );
        }

        if (!$model->save(false)) {
            Yii::error("Failed to save DataSource: {$request->id}", __METHOD__);
            return SaveDataSourceResult::failure(['Failed to save data source.']);
        }

        Yii::info("DataSource created: {$request->id} by {$request->actorUsername}", __METHOD__);

        return SaveDataSourceResult::success($model->getAttributes());
    }
}
```

**File:** `yii/src/handlers/datasource/UpdateDataSourceHandler.php`

```php
<?php

declare(strict_types=1);

namespace app\handlers\datasource;

use app\dto\datasource\SaveDataSourceResult;
use app\dto\datasource\UpdateDataSourceRequest;
use app\models\DataSource;
use Yii;

final class UpdateDataSourceHandler implements UpdateDataSourceInterface
{
    public function update(UpdateDataSourceRequest $request): SaveDataSourceResult
    {
        $model = DataSource::findOne(['id' => $request->id]);

        if ($model === null) {
            return SaveDataSourceResult::failure(['Data source not found.']);
        }

        $model->name = $request->name;
        $model->source_type = $request->sourceType;
        $model->base_url = $request->baseUrl;
        $model->notes = $request->notes;

        if (!$model->validate()) {
            return SaveDataSourceResult::failure(
                array_values(array_map(
                    fn(array $errors) => $errors[0],
                    $model->getErrors()
                ))
            );
        }

        if (!$model->save(false)) {
            Yii::error("Failed to update DataSource: {$request->id}", __METHOD__);
            return SaveDataSourceResult::failure(['Failed to update data source.']);
        }

        Yii::info("DataSource updated: {$request->id} by {$request->actorUsername}", __METHOD__);

        return SaveDataSourceResult::success($model->getAttributes());
    }
}
```

**File:** `yii/src/handlers/datasource/ToggleDataSourceHandler.php`

```php
<?php

declare(strict_types=1);

namespace app\handlers\datasource;

use app\dto\datasource\SaveDataSourceResult;
use app\dto\datasource\ToggleDataSourceRequest;
use app\models\DataSource;
use Yii;

final class ToggleDataSourceHandler implements ToggleDataSourceInterface
{
    public function toggle(ToggleDataSourceRequest $request): SaveDataSourceResult
    {
        $model = DataSource::findOne(['id' => $request->id]);

        if ($model === null) {
            return SaveDataSourceResult::failure(['Data source not found.']);
        }

        $model->is_active = $model->is_active ? 0 : 1;

        if (!$model->save(false)) {
            Yii::error("Failed to toggle DataSource: {$request->id}", __METHOD__);
            return SaveDataSourceResult::failure(['Failed to toggle data source status.']);
        }

        $status = $model->is_active ? 'activated' : 'deactivated';
        Yii::info("DataSource {$status}: {$request->id} by {$request->actorUsername}", __METHOD__);

        return SaveDataSourceResult::success($model->getAttributes());
    }
}
```

**File:** `yii/src/handlers/datasource/DeleteDataSourceHandler.php`

```php
<?php

declare(strict_types=1);

namespace app\handlers\datasource;

use app\dto\datasource\DeleteDataSourceRequest;
use app\dto\datasource\SaveDataSourceResult;
use app\models\DataSource;
use Yii;
use yii\db\IntegrityException;

final class DeleteDataSourceHandler implements DeleteDataSourceInterface
{
    public function delete(DeleteDataSourceRequest $request): SaveDataSourceResult
    {
        $model = DataSource::findOne(['id' => $request->id]);

        if ($model === null) {
            return SaveDataSourceResult::failure(['Data source not found.']);
        }

        try {
            if (!$model->delete()) {
                return SaveDataSourceResult::failure(['Failed to delete data source.']);
            }
        } catch (IntegrityException $e) {
            Yii::warning("Cannot delete DataSource {$request->id}: has dependent records", __METHOD__);
            return SaveDataSourceResult::failure([
                'Cannot delete this data source because it has associated data records. ' .
                'Deactivate it instead.',
            ]);
        }

        Yii::info("DataSource deleted: {$request->id} by {$request->actorUsername}", __METHOD__);

        return SaveDataSourceResult::success(['id' => $request->id]);
    }
}
```

#### 2.5 Register Handlers in DI Container

**File:** `yii/config/container.php`

Add the following bindings:

```php
use app\handlers\datasource\CreateDataSourceHandler;
use app\handlers\datasource\CreateDataSourceInterface;
use app\handlers\datasource\DeleteDataSourceHandler;
use app\handlers\datasource\DeleteDataSourceInterface;
use app\handlers\datasource\ToggleDataSourceHandler;
use app\handlers\datasource\ToggleDataSourceInterface;
use app\handlers\datasource\UpdateDataSourceHandler;
use app\handlers\datasource\UpdateDataSourceInterface;

// Inside the container definitions array:
CreateDataSourceInterface::class => CreateDataSourceHandler::class,
UpdateDataSourceInterface::class => UpdateDataSourceHandler::class,
ToggleDataSourceInterface::class => ToggleDataSourceHandler::class,
DeleteDataSourceInterface::class => DeleteDataSourceHandler::class,
```

---

### Phase 3: Controller

**File:** `yii/src/controllers/DataSourceController.php`

```php
<?php

declare(strict_types=1);

namespace app\controllers;

use app\dto\datasource\CreateDataSourceRequest;
use app\dto\datasource\DeleteDataSourceRequest;
use app\dto\datasource\ToggleDataSourceRequest;
use app\dto\datasource\UpdateDataSourceRequest;
use app\filters\AdminAuthFilter;
use app\handlers\datasource\CreateDataSourceInterface;
use app\handlers\datasource\DeleteDataSourceInterface;
use app\handlers\datasource\ToggleDataSourceInterface;
use app\handlers\datasource\UpdateDataSourceInterface;
use app\models\DataSource;
use app\queries\DataSourceQuery;
use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Controller for managing data sources.
 *
 * Provides CRUD operations for the admin UI.
 * All actions require HTTP Basic Authentication.
 */
final class DataSourceController extends Controller
{
    public $layout = 'main';

    public function __construct(
        $id,
        $module,
        private readonly DataSourceQuery $query,
        private readonly CreateDataSourceInterface $createHandler,
        private readonly UpdateDataSourceInterface $updateHandler,
        private readonly ToggleDataSourceInterface $toggleHandler,
        private readonly DeleteDataSourceInterface $deleteHandler,
        $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        return [
            'auth' => [
                'class' => AdminAuthFilter::class,
            ],
        ];
    }

    public function actionIndex(): string
    {
        $request = Yii::$app->request;

        $status = $request->get('status');
        $type = $request->get('type');
        $search = $request->get('search');

        $query = DataSource::find()->alphabetical();

        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'inactive') {
            $query->inactive();
        }

        if ($type !== null && $type !== '') {
            $query->ofType($type);
        }

        if ($search !== null && $search !== '') {
            $query->andWhere(['or',
                ['like', 'id', $search],
                ['like', 'name', $search],
            ]);
        }

        $dataSources = $query->asArray()->all();
        $counts = DataSource::find()->getCounts();

        return $this->render('index', [
            'dataSources' => $dataSources,
            'counts' => $counts,
            'currentStatus' => $status,
            'currentType' => $type,
            'search' => $search ?? '',
        ]);
    }

    public function actionView(string $id): string
    {
        $dataSource = $this->query->findById($id);

        if ($dataSource === null) {
            throw new NotFoundHttpException('Data source not found.');
        }

        return $this->render('view', [
            'dataSource' => $dataSource,
        ]);
    }

    public function actionCreate(): Response|string
    {
        $request = Yii::$app->request;

        if ($request->isPost) {
            $id = trim((string) $request->post('id', ''));
            $name = trim((string) $request->post('name', ''));
            $sourceType = trim((string) $request->post('source_type', ''));
            $baseUrl = trim((string) $request->post('base_url', ''));
            $notes = trim((string) $request->post('notes', ''));

            $result = $this->createHandler->create(new CreateDataSourceRequest(
                id: $id,
                name: $name,
                sourceType: $sourceType,
                actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
                baseUrl: $baseUrl !== '' ? $baseUrl : null,
                notes: $notes !== '' ? $notes : null,
            ));

            if ($result->success) {
                Yii::$app->session->setFlash('success', 'Data source created successfully.');
                return $this->redirect(['view', 'id' => $result->dataSource['id']]);
            }

            return $this->render('create', [
                'id' => $id,
                'name' => $name,
                'sourceType' => $sourceType,
                'baseUrl' => $baseUrl,
                'notes' => $notes,
                'errors' => $result->errors,
            ]);
        }

        return $this->render('create', [
            'id' => '',
            'name' => '',
            'sourceType' => DataSource::SOURCE_TYPE_API,
            'baseUrl' => '',
            'notes' => '',
            'errors' => [],
        ]);
    }

    public function actionUpdate(string $id): Response|string
    {
        $dataSource = $this->query->findById($id);

        if ($dataSource === null) {
            throw new NotFoundHttpException('Data source not found.');
        }

        $request = Yii::$app->request;

        if ($request->isPost) {
            $name = trim((string) $request->post('name', ''));
            $sourceType = trim((string) $request->post('source_type', ''));
            $baseUrl = trim((string) $request->post('base_url', ''));
            $notes = trim((string) $request->post('notes', ''));

            $result = $this->updateHandler->update(new UpdateDataSourceRequest(
                id: $id,
                name: $name,
                sourceType: $sourceType,
                actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
                baseUrl: $baseUrl !== '' ? $baseUrl : null,
                notes: $notes !== '' ? $notes : null,
            ));

            if ($result->success) {
                Yii::$app->session->setFlash('success', 'Data source updated successfully.');
                return $this->redirect(['view', 'id' => $id]);
            }

            return $this->render('update', [
                'id' => $id,
                'name' => $name,
                'sourceType' => $sourceType,
                'baseUrl' => $baseUrl,
                'notes' => $notes,
                'errors' => $result->errors,
            ]);
        }

        return $this->render('update', [
            'id' => $dataSource['id'],
            'name' => $dataSource['name'],
            'sourceType' => $dataSource['source_type'],
            'baseUrl' => $dataSource['base_url'] ?? '',
            'notes' => $dataSource['notes'] ?? '',
            'errors' => [],
        ]);
    }

    public function actionToggle(string $id): Response
    {
        $request = Yii::$app->request;

        if (!$request->isPost) {
            throw new NotFoundHttpException('Method not allowed.');
        }

        $result = $this->toggleHandler->toggle(new ToggleDataSourceRequest(
            id: $id,
            actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
        ));

        if ($result->success) {
            $status = $result->dataSource['is_active'] ? 'activated' : 'deactivated';
            Yii::$app->session->setFlash('success', "Data source {$status} successfully.");
        } else {
            Yii::$app->session->setFlash('error', $result->errors[0] ?? 'Failed to toggle status.');
        }

        $returnUrl = $request->post('return_url', ['index']);
        return $this->redirect($returnUrl);
    }

    public function actionDelete(string $id): Response
    {
        $request = Yii::$app->request;

        if (!$request->isPost) {
            throw new NotFoundHttpException('Method not allowed.');
        }

        $result = $this->deleteHandler->delete(new DeleteDataSourceRequest(
            id: $id,
            actorUsername: AdminAuthFilter::getAuthenticatedUsername() ?? 'unknown',
        ));

        if ($result->success) {
            Yii::$app->session->setFlash('success', 'Data source deleted successfully.');
        } else {
            Yii::$app->session->setFlash('error', $result->errors[0] ?? 'Failed to delete data source.');
        }

        return $this->redirect(['index']);
    }
}
```

---

### Phase 4: Views

#### 4.1 Index View

**File:** `yii/src/views/data-source/index.php`

Reference: `yii/src/views/collection-policy/index.php`

Features to implement:
- Page header with title and "Create Data Source" button
- Filter tabs: All | Active | Inactive (with counts)
- Filter dropdown for source type (api, web_scrape, derived)
- Search input for ID/name
- Table with columns: ID, Name, Type, Base URL, Status, Actions
- Empty state when no results
- Badges for status (Active/Inactive) and type

```php
<?php

declare(strict_types=1);

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var array<string, mixed>[] $dataSources
 * @var array{total: int, active: int, inactive: int} $counts
 * @var string|null $currentStatus
 * @var string|null $currentType
 * @var string $search
 */

$this->title = 'Data Sources';
?>

<div class="page-header">
    <h1 class="page-header__title"><?= Html::encode($this->title) ?></h1>
    <div class="toolbar">
        <a href="<?= Url::to(['create']) ?>" class="btn btn--primary">
            + Create Data Source
        </a>
    </div>
</div>

<div class="filter-bar">
    <div class="filter-bar__tabs">
        <a href="<?= Url::to(['index', 'type' => $currentType, 'search' => $search]) ?>"
           class="filter-tab<?= $currentStatus === null ? ' filter-tab--active' : '' ?>">
            All (<?= $counts['total'] ?>)
        </a>
        <a href="<?= Url::to(['index', 'status' => 'active', 'type' => $currentType, 'search' => $search]) ?>"
           class="filter-tab<?= $currentStatus === 'active' ? ' filter-tab--active' : '' ?>">
            Active (<?= $counts['active'] ?>)
        </a>
        <a href="<?= Url::to(['index', 'status' => 'inactive', 'type' => $currentType, 'search' => $search]) ?>"
           class="filter-tab<?= $currentStatus === 'inactive' ? ' filter-tab--active' : '' ?>">
            Inactive (<?= $counts['inactive'] ?>)
        </a>
    </div>

    <div class="filter-bar__controls">
        <select class="form-select" onchange="location.href=this.value">
            <option value="<?= Url::to(['index', 'status' => $currentStatus, 'search' => $search]) ?>"
                <?= $currentType === null ? 'selected' : '' ?>>All Types</option>
            <option value="<?= Url::to(['index', 'status' => $currentStatus, 'type' => 'api', 'search' => $search]) ?>"
                <?= $currentType === 'api' ? 'selected' : '' ?>>API</option>
            <option value="<?= Url::to(['index', 'status' => $currentStatus, 'type' => 'web_scrape', 'search' => $search]) ?>"
                <?= $currentType === 'web_scrape' ? 'selected' : '' ?>>Web Scrape</option>
            <option value="<?= Url::to(['index', 'status' => $currentStatus, 'type' => 'derived', 'search' => $search]) ?>"
                <?= $currentType === 'derived' ? 'selected' : '' ?>>Derived</option>
        </select>

        <form method="get" action="<?= Url::to(['index']) ?>" class="filter-bar__search">
            <?php if ($currentStatus !== null): ?>
                <input type="hidden" name="status" value="<?= Html::encode($currentStatus) ?>">
            <?php endif; ?>
            <?php if ($currentType !== null): ?>
                <input type="hidden" name="type" value="<?= Html::encode($currentType) ?>">
            <?php endif; ?>
            <input type="text" name="search" value="<?= Html::encode($search) ?>"
                   placeholder="Search by ID or name..." class="form-input form-input--sm">
            <button type="submit" class="btn btn--sm btn--secondary">Search</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card__body">
        <?php if (empty($dataSources)): ?>
            <div class="empty-state">
                <h3 class="empty-state__title">No data sources found</h3>
                <p class="empty-state__text">
                    <?php if ($search !== '' || $currentStatus !== null || $currentType !== null): ?>
                        Try adjusting your filters or search term.
                    <?php else: ?>
                        Create a data source to track where your financial data comes from.
                    <?php endif; ?>
                </p>
                <?php if ($search === '' && $currentStatus === null && $currentType === null): ?>
                    <a href="<?= Url::to(['create']) ?>" class="btn btn--primary">
                        + Create Data Source
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Base URL</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dataSources as $ds): ?>
                            <tr>
                                <td class="table__cell--mono">
                                    <a href="<?= Url::to(['view', 'id' => $ds['id']]) ?>">
                                        <code><?= Html::encode($ds['id']) ?></code>
                                    </a>
                                </td>
                                <td>
                                    <strong><?= Html::encode($ds['name']) ?></strong>
                                    <?php if (!empty($ds['notes'])): ?>
                                        <br>
                                        <span class="text-muted text-sm">
                                            <?= Html::encode(mb_substr($ds['notes'], 0, 50)) ?>
                                            <?= mb_strlen($ds['notes']) > 50 ? '...' : '' ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $typeLabels = [
                                        'api' => 'API',
                                        'web_scrape' => 'Web Scrape',
                                        'derived' => 'Derived',
                                    ];
                                    $typeClass = [
                                        'api' => 'badge--info',
                                        'web_scrape' => 'badge--warning',
                                        'derived' => 'badge--secondary',
                                    ];
                                    ?>
                                    <span class="badge <?= $typeClass[$ds['source_type']] ?? '' ?>">
                                        <?= $typeLabels[$ds['source_type']] ?? $ds['source_type'] ?>
                                    </span>
                                </td>
                                <td class="table__cell--mono">
                                    <?php if (!empty($ds['base_url'])): ?>
                                        <a href="<?= Html::encode($ds['base_url']) ?>" target="_blank" rel="noopener">
                                            <?= Html::encode(parse_url($ds['base_url'], PHP_URL_HOST) ?: $ds['base_url']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($ds['is_active']): ?>
                                        <span class="badge badge--active">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge--inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="table__actions">
                                        <a href="<?= Url::to(['view', 'id' => $ds['id']]) ?>"
                                           class="btn btn--sm btn--secondary">View</a>
                                        <a href="<?= Url::to(['update', 'id' => $ds['id']]) ?>"
                                           class="btn btn--sm btn--secondary">Edit</a>
                                        <form method="post" action="<?= Url::to(['toggle', 'id' => $ds['id']]) ?>"
                                              style="display: inline;">
                                            <?= Html::hiddenInput(
                                                Yii::$app->request->csrfParam,
                                                Yii::$app->request->getCsrfToken()
                                            ) ?>
                                            <button type="submit" class="btn btn--sm <?= $ds['is_active'] ? 'btn--warning' : 'btn--success' ?>">
                                                <?= $ds['is_active'] ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
```

#### 4.2 View Template

**File:** `yii/src/views/data-source/view.php`

Display all details about a data source with edit/toggle/delete actions.

#### 4.3 Create Template

**File:** `yii/src/views/data-source/create.php`

Wrapper that includes _form.php partial with `isCreate = true`.

#### 4.4 Update Template

**File:** `yii/src/views/data-source/update.php`

Wrapper that includes _form.php partial with `isCreate = false`.

#### 4.5 Form Partial

**File:** `yii/src/views/data-source/_form.php`

Shared form with fields:
- ID (text input, readonly on update)
- Name (text input, required)
- Source Type (select: api, web_scrape, derived)
- Base URL (text input, optional)
- Notes (textarea, optional)

Follow the patterns from:
- `yii/src/views/collection-policy/_form.php`
- `yii/src/views/industry/_form.php`

---

### Phase 5: Navigation

#### 5.1 Update Main Layout

**File:** `yii/src/views/layouts/main.php`

Add navigation link after "Runs":

```php
<a href="<?= Url::to(['/data-source/index']) ?>"
   class="admin-nav__link<?= $currentController === 'data-source' ? ' admin-nav__link--active' : '' ?>">
    Sources
</a>
```

---

### Phase 6: Tests

#### 6.1 Unit Tests for Query Class

**File:** `yii/tests/unit/queries/DataSourceQueryTest.php`

Test methods:
- `testFindAllReturnsArray()`
- `testFindByIdReturnsDataSource()`
- `testFindByIdReturnsNullForMissing()`
- `testActiveFiltersCorrectly()`
- `testInactiveFiltersCorrectly()`
- `testOfTypeFiltersCorrectly()`
- `testGetCountsReturnsCorrectCounts()`

#### 6.2 Unit Tests for Handlers

**Files:**
- `yii/tests/unit/handlers/datasource/CreateDataSourceHandlerTest.php`
- `yii/tests/unit/handlers/datasource/UpdateDataSourceHandlerTest.php`
- `yii/tests/unit/handlers/datasource/ToggleDataSourceHandlerTest.php`
- `yii/tests/unit/handlers/datasource/DeleteDataSourceHandlerTest.php`

Test cases:
- Success scenarios
- Validation failures
- Not found scenarios
- Foreign key constraint on delete (IntegrityException)

Reference: `.claude/rules/testing.md` for test structure and naming conventions.

---

## File Checklist

### Model Layer
- [ ] `yii/src/models/DataSource.php`
- [ ] `yii/src/queries/DataSourceQuery.php`

### DTOs
- [ ] `yii/src/dto/datasource/CreateDataSourceRequest.php`
- [ ] `yii/src/dto/datasource/UpdateDataSourceRequest.php`
- [ ] `yii/src/dto/datasource/ToggleDataSourceRequest.php`
- [ ] `yii/src/dto/datasource/DeleteDataSourceRequest.php`
- [ ] `yii/src/dto/datasource/SaveDataSourceResult.php`

### Handlers
- [ ] `yii/src/handlers/datasource/CreateDataSourceInterface.php`
- [ ] `yii/src/handlers/datasource/CreateDataSourceHandler.php`
- [ ] `yii/src/handlers/datasource/UpdateDataSourceInterface.php`
- [ ] `yii/src/handlers/datasource/UpdateDataSourceHandler.php`
- [ ] `yii/src/handlers/datasource/ToggleDataSourceInterface.php`
- [ ] `yii/src/handlers/datasource/ToggleDataSourceHandler.php`
- [ ] `yii/src/handlers/datasource/DeleteDataSourceInterface.php`
- [ ] `yii/src/handlers/datasource/DeleteDataSourceHandler.php`

### Controller
- [ ] `yii/src/controllers/DataSourceController.php`

### Views
- [ ] `yii/src/views/data-source/index.php`
- [ ] `yii/src/views/data-source/view.php`
- [ ] `yii/src/views/data-source/create.php`
- [ ] `yii/src/views/data-source/update.php`
- [ ] `yii/src/views/data-source/_form.php`

### Configuration
- [ ] `yii/config/container.php` (add handler bindings)

### Layout
- [ ] `yii/src/views/layouts/main.php` (add nav link)

### Tests
- [ ] `yii/tests/unit/queries/DataSourceQueryTest.php`
- [ ] `yii/tests/unit/handlers/datasource/CreateDataSourceHandlerTest.php`
- [ ] `yii/tests/unit/handlers/datasource/UpdateDataSourceHandlerTest.php`
- [ ] `yii/tests/unit/handlers/datasource/ToggleDataSourceHandlerTest.php`
- [ ] `yii/tests/unit/handlers/datasource/DeleteDataSourceHandlerTest.php`

---

## Validation Commands

Run after implementation:

```bash
# Linter
docker exec aimm_yii vendor/bin/php-cs-fixer fix --dry-run

# Fix style issues
docker exec aimm_yii vendor/bin/php-cs-fixer fix

# Run all tests
docker exec aimm_yii vendor/bin/codecept run unit

# Run specific test file
docker exec aimm_yii vendor/bin/codecept run unit queries/DataSourceQueryTest
```

---

## Notes

1. **No migration needed** — The `data_source` table already exists with correct schema.

2. **String ID pattern** — Unlike other models that use auto-increment integers, DataSource uses human-readable string IDs like 'fmp', 'yahoo_finance'. The ID field is editable only on create.

3. **Delete protection** — The foreign key constraints use RESTRICT on delete. The delete handler catches `IntegrityException` and shows a user-friendly message suggesting deactivation instead.

4. **Source types** — Use constants from the model (`DataSource::SOURCE_TYPE_*`) for type values.

5. **Actor tracking** — All handlers log the `actorUsername` for audit purposes. This comes from `AdminAuthFilter::getAuthenticatedUsername()`.
