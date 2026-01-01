<?php

declare(strict_types=1);

namespace app\handlers\peergroup;

use app\dto\CollectIndustryRequest;
use app\dto\CompanyConfig;
use app\dto\DataRequirements;
use app\dto\IndustryConfig;
use app\dto\MacroRequirements;
use app\dto\MetricDefinition;
use app\dto\peergroup\CollectPeerGroupRequest;
use app\dto\peergroup\CollectPeerGroupResult;
use app\handlers\collection\CollectIndustryInterface;
use app\queries\CollectionPolicyQuery;
use app\queries\CollectionRunRepository;
use app\queries\CompanyQuery;
use app\queries\PeerGroupMemberQuery;
use app\queries\PeerGroupQuery;
use Throwable;
use yii\log\Logger;

/**
 * Handler for collecting data for a peer group.
 *
 * Abstracts the underlying CollectIndustryInterface, preventing UI
 * controllers from depending on collection internals.
 */
final class CollectPeerGroupHandler implements CollectPeerGroupInterface
{
    private const DEFAULT_EXCHANGE = 'NASDAQ';
    private const DEFAULT_CURRENCY = 'USD';
    private const DEFAULT_FY_END_MONTH = 12;

    public function __construct(
        private readonly PeerGroupQuery $peerGroupQuery,
        private readonly PeerGroupMemberQuery $memberQuery,
        private readonly CollectionPolicyQuery $policyQuery,
        private readonly CompanyQuery $companyQuery,
        private readonly CollectIndustryInterface $industryCollector,
        private readonly CollectionRunRepository $runRepository,
        private readonly Logger $logger,
    ) {
    }

    public function collect(CollectPeerGroupRequest $request): CollectPeerGroupResult
    {
        $this->logger->log(
            [
                'message' => 'Starting peer group collection',
                'group_id' => $request->groupId,
                'actor' => $request->actorUsername,
            ],
            Logger::LEVEL_INFO,
            'collection'
        );

        // 1. Load peer group
        $group = $this->peerGroupQuery->findById($request->groupId);
        if ($group === null) {
            return CollectPeerGroupResult::failure(['Peer group not found.']);
        }

        if (!$group['is_active']) {
            return CollectPeerGroupResult::failure(['Peer group is not active.']);
        }

        // 2. Check for running collection
        if ($this->runRepository->hasRunningCollection($request->groupId)) {
            return CollectPeerGroupResult::failure(['A collection is already running for this group.']);
        }

        // 3. Load policy (from group or sector default)
        $policy = $this->resolvePolicy($group);
        if ($policy === null) {
            return CollectPeerGroupResult::failure(['No collection policy configured for this group or sector.']);
        }

        // 4. Load members with company data
        $members = $this->memberQuery->findByGroup($request->groupId);
        if (empty($members)) {
            return CollectPeerGroupResult::failure(['Peer group has no members.']);
        }

        try {
            // 5. Build IndustryConfig from group + policy + members
            $industryConfig = $this->buildIndustryConfig($group, $policy, $members);

            // 6. Determine focal ticker
            $focalTicker = $this->resolveFocalTicker($members, $request->focalTickerOverride);

            // 7. Execute collection
            $result = $this->industryCollector->collect(new CollectIndustryRequest(
                config: $industryConfig,
                batchSize: $request->batchSize,
                enableMemoryManagement: $request->enableMemoryManagement,
                focalTicker: $focalTicker,
            ));

            // 8. Get run ID from datapack
            $run = $this->runRepository->findByDatapackId($result->datapackId);
            if ($run === null) {
                $this->logger->log(
                    [
                        'message' => 'Collection run record not found',
                        'group_id' => $request->groupId,
                        'datapack_id' => $result->datapackId,
                    ],
                    Logger::LEVEL_ERROR,
                    'collection'
                );

                return CollectPeerGroupResult::failure(['Collection completed but run record not found.']);
            }

            $runId = (int) $run['id'];

            $this->logger->log(
                [
                    'message' => 'Peer group collection completed',
                    'group_id' => $request->groupId,
                    'run_id' => $runId,
                    'status' => $result->overallStatus->value,
                    'gate_passed' => $result->gateResult->passed,
                ],
                Logger::LEVEL_INFO,
                'collection'
            );

            return CollectPeerGroupResult::success(
                runId: $runId,
                datapackId: $result->datapackId,
                status: $result->overallStatus,
                gateResult: $result->gateResult,
            );
        } catch (Throwable $e) {
            $this->logger->log(
                [
                    'message' => 'Peer group collection failed',
                    'group_id' => $request->groupId,
                    'error' => $e->getMessage(),
                ],
                Logger::LEVEL_ERROR,
                'collection'
            );

            return CollectPeerGroupResult::failure(['Collection failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Resolve the collection policy for the group.
     *
     * @param array<string, mixed> $group
     * @return array<string, mixed>|null
     */
    private function resolvePolicy(array $group): ?array
    {
        // First try group's assigned policy
        if (!empty($group['policy_id'])) {
            $policy = $this->policyQuery->findById((int) $group['policy_id']);
            if ($policy !== null) {
                return $policy;
            }
        }

        // Fall back to sector default
        return $this->policyQuery->findDefaultForSector($group['sector']);
    }

    /**
     * Build an IndustryConfig from peer group data.
     *
     * @param array<string, mixed> $group
     * @param array<string, mixed> $policy
     * @param array<string, mixed>[] $members
     */
    private function buildIndustryConfig(array $group, array $policy, array $members): IndustryConfig
    {
        $companyConfigs = [];
        foreach ($members as $member) {
            $company = $this->companyQuery->findById((int) $member['company_id']);
            $companyConfigs[] = $this->buildCompanyConfig($member, $company);
        }

        return new IndustryConfig(
            id: $group['slug'],
            name: $group['name'],
            sector: $group['sector'],
            companies: $companyConfigs,
            macroRequirements: $this->buildMacroRequirements($policy),
            dataRequirements: $this->buildDataRequirements($policy),
            focalTicker: $this->getFocalTickerFromMembers($members),
        );
    }

    /**
     * Build a CompanyConfig from member and company data.
     *
     * @param array<string, mixed> $member
     * @param array<string, mixed>|null $company
     */
    private function buildCompanyConfig(array $member, ?array $company): CompanyConfig
    {
        $exchange = $company['exchange'] ?? self::DEFAULT_EXCHANGE;
        $currency = $company['currency'] ?? self::DEFAULT_CURRENCY;
        $fyEndMonth = $company['fiscal_year_end'] ?? self::DEFAULT_FY_END_MONTH;

        return new CompanyConfig(
            ticker: $member['ticker'],
            name: $member['name'] ?? $member['ticker'],
            listingExchange: $exchange,
            listingCurrency: $currency,
            reportingCurrency: $currency,
            fyEndMonth: (int) $fyEndMonth,
        );
    }

    /**
     * Build MacroRequirements from policy data.
     *
     * @param array<string, mixed> $policy
     */
    private function buildMacroRequirements(array $policy): MacroRequirements
    {
        return new MacroRequirements(
            commodityBenchmark: $policy['commodity_benchmark'] ?? null,
            marginProxy: $policy['margin_proxy'] ?? null,
            sectorIndex: $policy['sector_index'] ?? null,
            requiredIndicators: $this->decodeJson($policy['required_indicators'] ?? null) ?? [],
            optionalIndicators: $this->decodeJson($policy['optional_indicators'] ?? null) ?? [],
        );
    }

    /**
     * Build DataRequirements from policy data.
     *
     * @param array<string, mixed> $policy
     */
    private function buildDataRequirements(array $policy): DataRequirements
    {
        return new DataRequirements(
            historyYears: (int) ($policy['history_years'] ?? 5),
            quartersToFetch: (int) ($policy['quarters_to_fetch'] ?? 8),
            valuationMetrics: $this->parseMetrics($policy['valuation_metrics'] ?? null),
            annualFinancialMetrics: $this->parseMetrics($policy['annual_financial_metrics'] ?? null),
            quarterMetrics: $this->parseMetrics($policy['quarterly_financial_metrics'] ?? null),
            operationalMetrics: $this->parseMetrics($policy['operational_metrics'] ?? null),
        );
    }

    /**
     * Parse metric definitions from JSON column.
     *
     * @return list<MetricDefinition>
     */
    private function parseMetrics(mixed $value): array
    {
        $data = $this->decodeJson($value);
        if (!is_array($data)) {
            return [];
        }

        $metrics = [];
        foreach ($data as $item) {
            if (!is_array($item) || !isset($item['key'])) {
                continue;
            }

            $metrics[] = new MetricDefinition(
                key: $item['key'],
                unit: $item['unit'] ?? MetricDefinition::UNIT_NUMBER,
                required: (bool) ($item['required'] ?? false),
                requiredScope: $item['required_scope'] ?? MetricDefinition::SCOPE_ALL,
            );
        }

        return $metrics;
    }

    /**
     * Decode JSON value, handling both string and already-decoded arrays.
     *
     * @return mixed
     */
    private function decodeJson(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        return null;
    }

    /**
     * Get the focal ticker from members.
     *
     * @param array<string, mixed>[] $members
     */
    private function getFocalTickerFromMembers(array $members): ?string
    {
        foreach ($members as $member) {
            if (!empty($member['is_focal'])) {
                return $member['ticker'];
            }
        }

        return null;
    }

    /**
     * Resolve focal ticker with override support.
     *
     * @param array<string, mixed>[] $members
     */
    private function resolveFocalTicker(array $members, ?string $override): ?string
    {
        if ($override !== null && $override !== '') {
            // Validate override is a member
            foreach ($members as $member) {
                if ($member['ticker'] === $override) {
                    return $override;
                }
            }
        }

        return $this->getFocalTickerFromMembers($members);
    }
}
