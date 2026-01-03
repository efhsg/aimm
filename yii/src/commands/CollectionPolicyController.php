<?php

declare(strict_types=1);

namespace app\commands;

use app\queries\CollectionPolicyQuery;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\helpers\Json;

/**
 * Manages collection policies for data collection configuration.
 */
final class CollectionPolicyController extends Controller
{
    public ?string $name = null;
    public ?string $description = null;
    public ?int $historyYears = null;
    public ?int $quarters = null;

    public function __construct(
        $id,
        $module,
        private readonly CollectionPolicyQuery $policyQuery,
        array $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    public function options($actionID): array
    {
        $options = parent::options($actionID);

        return match ($actionID) {
            'create' => array_merge($options, ['name', 'description', 'historyYears', 'quarters']),
            default => $options,
        };
    }

    public function optionAliases(): array
    {
        return [
            'n' => 'name',
            'd' => 'description',
            'h' => 'historyYears',
            'q' => 'quarters',
        ];
    }

    /**
     * Creates a new collection policy from a JSON file.
     *
     * @param string $slug Unique slug identifier
     * @param string $jsonFile Path to JSON config file (or - for stdin)
     */
    public function actionCreate(string $slug, string $jsonFile): int
    {
        if ($this->policyQuery->findBySlug($slug) !== null) {
            $this->stderr("Error: Policy with slug '{$slug}' already exists\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $json = $this->readJsonFile($jsonFile);
        if ($json === null) {
            return ExitCode::NOINPUT;
        }

        $data = $this->buildPolicyData($slug, $json);
        if ($data === null) {
            return ExitCode::DATAERR;
        }

        $id = $this->policyQuery->insert($data);

        $this->stdout("Created policy: ", Console::FG_GREEN);
        $this->stdout("{$data['name']} (id={$id}, slug={$slug})\n");

        return ExitCode::OK;
    }

    /**
     * Lists all collection policies.
     */
    public function actionList(): int
    {
        $policies = $this->policyQuery->findAll();

        if (empty($policies)) {
            $this->stdout("No collection policies found\n");
            return ExitCode::OK;
        }

        $this->stdout(str_pad('Slug', 30) . str_pad('Name', 30) . str_pad('History', 10) . str_pad('Quarters', 10) . "Default For\n", Console::BOLD);
        $this->stdout(str_repeat('-', 90) . "\n");

        foreach ($policies as $policy) {
            $this->stdout(str_pad($policy['slug'], 30));
            $this->stdout(str_pad($policy['name'], 30));
            $this->stdout(str_pad($policy['history_years'] . ' yrs', 10));
            $this->stdout(str_pad((string) $policy['quarters_to_fetch'], 10));
            $this->stdout(($policy['is_default_for_sector'] ?? '-') . "\n");
        }

        return ExitCode::OK;
    }

    /**
     * Shows details of a collection policy.
     *
     * @param string $slug The policy slug
     */
    public function actionShow(string $slug): int
    {
        $policy = $this->policyQuery->findBySlug($slug);
        if ($policy === null) {
            $this->stderr("Error: Policy '{$slug}' not found\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $this->stdout("\nCollection Policy: ", Console::BOLD);
        $this->stdout($policy['name'] . "\n");
        $this->stdout(str_repeat('-', 60) . "\n");

        $this->stdout("Slug:           {$policy['slug']}\n");
        $this->stdout("Description:    " . ($policy['description'] ?? '-') . "\n");
        $this->stdout("History Years:  {$policy['history_years']}\n");
        $this->stdout("Quarters:       {$policy['quarters_to_fetch']}\n");
        $this->stdout("Default For:    " . ($policy['is_default_for_sector'] ?? '-') . "\n");

        $this->stdout("\nMacro Settings:\n", Console::BOLD);
        $this->stdout("  Commodity:    " . ($policy['commodity_benchmark'] ?? '-') . "\n");
        $this->stdout("  Margin Proxy: " . ($policy['margin_proxy'] ?? '-') . "\n");
        $this->stdout("  Sector Index: " . ($policy['sector_index'] ?? '-') . "\n");

        $this->stdout("\nMetrics:\n", Console::BOLD);
        $this->printJsonField('  Valuation', $policy['valuation_metrics']);
        $this->printJsonField('  Annual', $policy['annual_financial_metrics']);
        $this->printJsonField('  Quarterly', $policy['quarterly_financial_metrics']);
        $this->printJsonField('  Operational', $policy['operational_metrics']);

        $this->stdout("\nIndicators:\n", Console::BOLD);
        $this->printJsonField('  Required', $policy['required_indicators']);
        $this->printJsonField('  Optional', $policy['optional_indicators']);

        return ExitCode::OK;
    }

    /**
     * Sets a policy as the default for a sector.
     *
     * @param string $slug The policy slug
     * @param string $sector The sector name
     */
    public function actionSetDefault(string $slug, string $sector): int
    {
        $policy = $this->policyQuery->findBySlug($slug);
        if ($policy === null) {
            $this->stderr("Error: Policy '{$slug}' not found\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $this->policyQuery->setDefaultForSector((int) $policy['id'], $sector);
        $this->stdout("Set '{$slug}' as default policy for sector: {$sector}\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Clears the default policy for a sector.
     *
     * @param string $sector The sector name
     */
    public function actionClearDefault(string $sector): int
    {
        $this->policyQuery->clearDefaultForSector($sector);
        $this->stdout("Cleared default policy for sector: {$sector}\n", Console::FG_YELLOW);

        return ExitCode::OK;
    }

    /**
     * Exports a policy as JSON.
     *
     * @param string $slug The policy slug
     */
    public function actionExport(string $slug): int
    {
        $policy = $this->policyQuery->findBySlug($slug);
        if ($policy === null) {
            $this->stderr("Error: Policy '{$slug}' not found\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        // Build export structure
        $export = [
            'name' => $policy['name'],
            'description' => $policy['description'],
            'history_years' => (int) $policy['history_years'],
            'quarters_to_fetch' => (int) $policy['quarters_to_fetch'],
            'valuation_metrics' => Json::decode($policy['valuation_metrics'] ?? '[]'),
            'annual_financial_metrics' => Json::decode($policy['annual_financial_metrics'] ?? '[]'),
            'quarterly_financial_metrics' => Json::decode($policy['quarterly_financial_metrics'] ?? '[]'),
            'operational_metrics' => Json::decode($policy['operational_metrics'] ?? '[]'),
            'commodity_benchmark' => $policy['commodity_benchmark'],
            'margin_proxy' => $policy['margin_proxy'],
            'sector_index' => $policy['sector_index'],
            'required_indicators' => Json::decode($policy['required_indicators'] ?? '[]'),
            'optional_indicators' => Json::decode($policy['optional_indicators'] ?? '[]'),
        ];

        $this->stdout(Json::encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        return ExitCode::OK;
    }

    /**
     * Updates an existing collection policy from a JSON file.
     *
     * @param string $slug The policy slug to update
     * @param string $jsonFile Path to JSON file (or - for stdin)
     */
    public function actionUpdate(string $slug, string $jsonFile): int
    {
        $policy = $this->policyQuery->findBySlug($slug);
        if ($policy === null) {
            $this->stderr("Error: Policy '{$slug}' not found\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $json = $this->readJsonFile($jsonFile);
        if ($json === null) {
            return ExitCode::DATAERR;
        }

        $data = $this->buildUpdateData($policy, $json);

        $this->policyQuery->update((int) $policy['id'], $data);
        $this->stdout("Updated: {$slug}\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Deletes a collection policy.
     *
     * @param string $slug The policy slug
     */
    public function actionDelete(string $slug): int
    {
        $policy = $this->policyQuery->findBySlug($slug);
        if ($policy === null) {
            $this->stderr("Error: Policy '{$slug}' not found\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        if (!$this->confirm("Delete policy '{$slug}'?")) {
            $this->stdout("Cancelled\n");
            return ExitCode::OK;
        }

        $this->policyQuery->delete((int) $policy['id']);
        $this->stdout("Deleted: {$slug}\n", Console::FG_YELLOW);

        return ExitCode::OK;
    }

    private function readJsonFile(string $path): ?array
    {
        if ($path === '-') {
            $content = file_get_contents('php://stdin');
        } elseif (!file_exists($path)) {
            $this->stderr("Error: File not found: {$path}\n", Console::FG_RED);
            return null;
        } else {
            $content = file_get_contents($path);
        }

        if ($content === false || $content === '') {
            $this->stderr("Error: Could not read input\n", Console::FG_RED);
            return null;
        }

        try {
            return Json::decode($content);
        } catch (\Exception $e) {
            $this->stderr("Error: Invalid JSON: {$e->getMessage()}\n", Console::FG_RED);
            return null;
        }
    }

    private function buildPolicyData(string $slug, array $json): ?array
    {
        $name = $this->name ?? $json['name'] ?? null;
        if ($name === null) {
            $this->stderr("Error: Name is required (--name or 'name' in JSON)\n", Console::FG_RED);
            return null;
        }

        $valuationMetrics = $json['valuation_metrics'] ?? null;
        if ($valuationMetrics === null || !is_array($valuationMetrics)) {
            $this->stderr("Error: valuation_metrics is required in JSON\n", Console::FG_RED);
            return null;
        }

        return [
            'slug' => $slug,
            'name' => $name,
            'description' => $this->description ?? $json['description'] ?? null,
            'history_years' => $this->historyYears ?? $json['history_years'] ?? 5,
            'quarters_to_fetch' => $this->quarters ?? $json['quarters_to_fetch'] ?? 8,
            'valuation_metrics' => Json::encode($valuationMetrics),
            'annual_financial_metrics' => isset($json['annual_financial_metrics']) ? Json::encode($json['annual_financial_metrics']) : null,
            'quarterly_financial_metrics' => isset($json['quarterly_financial_metrics']) ? Json::encode($json['quarterly_financial_metrics']) : null,
            'operational_metrics' => isset($json['operational_metrics']) ? Json::encode($json['operational_metrics']) : null,
            'commodity_benchmark' => $json['commodity_benchmark'] ?? null,
            'margin_proxy' => $json['margin_proxy'] ?? null,
            'sector_index' => $json['sector_index'] ?? null,
            'required_indicators' => isset($json['required_indicators']) ? Json::encode($json['required_indicators']) : null,
            'optional_indicators' => isset($json['optional_indicators']) ? Json::encode($json['optional_indicators']) : null,
        ];
    }

    /**
     * Build update data from JSON, preserving existing values for unset fields.
     *
     * @param array<string, mixed> $policy Existing policy data
     * @param array<string, mixed> $json JSON input data
     * @return array<string, mixed>
     */
    private function buildUpdateData(array $policy, array $json): array
    {
        $data = [];

        if (isset($json['name'])) {
            $data['name'] = $json['name'];
        }
        if (array_key_exists('description', $json)) {
            $data['description'] = $json['description'];
        }
        if (isset($json['history_years'])) {
            $data['history_years'] = (int) $json['history_years'];
        }
        if (isset($json['quarters_to_fetch'])) {
            $data['quarters_to_fetch'] = (int) $json['quarters_to_fetch'];
        }
        if (isset($json['valuation_metrics'])) {
            $data['valuation_metrics'] = Json::encode($json['valuation_metrics']);
        }
        if (isset($json['annual_financial_metrics'])) {
            $data['annual_financial_metrics'] = Json::encode($json['annual_financial_metrics']);
        }
        if (isset($json['quarterly_financial_metrics'])) {
            $data['quarterly_financial_metrics'] = Json::encode($json['quarterly_financial_metrics']);
        }
        if (isset($json['operational_metrics'])) {
            $data['operational_metrics'] = Json::encode($json['operational_metrics']);
        }
        if (array_key_exists('commodity_benchmark', $json)) {
            $data['commodity_benchmark'] = $json['commodity_benchmark'];
        }
        if (array_key_exists('margin_proxy', $json)) {
            $data['margin_proxy'] = $json['margin_proxy'];
        }
        if (array_key_exists('sector_index', $json)) {
            $data['sector_index'] = $json['sector_index'];
        }
        if (isset($json['required_indicators'])) {
            $data['required_indicators'] = Json::encode($json['required_indicators']);
        }
        if (isset($json['optional_indicators'])) {
            $data['optional_indicators'] = Json::encode($json['optional_indicators']);
        }

        return $data;
    }

    private function printJsonField(string $label, ?string $json): void
    {
        if ($json === null) {
            $this->stdout("{$label}: -\n");
            return;
        }

        try {
            $data = Json::decode($json);
            if (empty($data)) {
                $this->stdout("{$label}: []\n");
            } else {
                $this->stdout("{$label}: " . implode(', ', (array) $data) . "\n");
            }
        } catch (\Exception $e) {
            $this->stdout("{$label}: (invalid JSON)\n");
        }
    }
}
