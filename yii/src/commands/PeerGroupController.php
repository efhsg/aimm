<?php

declare(strict_types=1);

namespace app\commands;

use app\queries\CompanyQuery;
use app\queries\PeerGroupMemberQuery;
use app\queries\PeerGroupQuery;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Manages industry peer groups for comparative analysis.
 */
final class PeerGroupController extends Controller
{
    public ?string $slug = null;
    public ?string $sector = null;
    public ?int $policy = null;
    public ?string $description = null;

    public function __construct(
        $id,
        $module,
        private readonly PeerGroupQuery $groupQuery,
        private readonly PeerGroupMemberQuery $memberQuery,
        private readonly CompanyQuery $companyQuery,
        array $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    public function options($actionID): array
    {
        $options = parent::options($actionID);

        return match ($actionID) {
            'create' => array_merge($options, ['slug', 'sector', 'policy', 'description']),
            'list' => array_merge($options, ['sector']),
            default => $options,
        };
    }

    public function optionAliases(): array
    {
        return [
            's' => 'sector',
            'p' => 'policy',
            'd' => 'description',
        ];
    }

    /**
     * Creates a new peer group.
     *
     * @param string $name The display name of the peer group
     */
    public function actionCreate(string $name): int
    {
        if ($this->sector === null) {
            $this->stderr("Error: --sector is required\n", Console::FG_RED);
            return ExitCode::USAGE;
        }

        $slug = $this->slug ?? $this->generateSlug($name);

        if ($this->groupQuery->findBySlug($slug) !== null) {
            $this->stderr("Error: Peer group with slug '{$slug}' already exists\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $id = $this->groupQuery->insert([
            'slug' => $slug,
            'name' => $name,
            'description' => $this->description,
            'sector' => $this->sector,
            'policy_id' => $this->policy,
            'is_active' => 1,
        ]);

        $this->stdout("Created peer group: ", Console::FG_GREEN);
        $this->stdout("{$name} (id={$id}, slug={$slug})\n");

        return ExitCode::OK;
    }

    /**
     * Adds companies to a peer group.
     *
     * @param string $groupSlug The peer group slug
     * @param array $tickers Company tickers to add
     */
    public function actionAdd(string $groupSlug, array $tickers): int
    {
        $group = $this->groupQuery->findBySlug($groupSlug);
        if ($group === null) {
            $this->stderr("Error: Peer group '{$groupSlug}' not found\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $added = 0;
        $skipped = 0;
        $currentOrder = $this->memberQuery->countByGroup((int) $group['id']);

        foreach ($tickers as $ticker) {
            $ticker = strtoupper(trim($ticker));
            if (empty($ticker)) {
                continue;
            }

            $companyId = $this->companyQuery->findOrCreate($ticker);

            if ($this->memberQuery->isMember((int) $group['id'], $companyId)) {
                $this->stdout("  Skipped: {$ticker} (already a member)\n", Console::FG_YELLOW);
                $skipped++;
                continue;
            }

            $this->memberQuery->addMember((int) $group['id'], $companyId, false, $currentOrder++);
            $this->stdout("  Added: {$ticker}\n", Console::FG_GREEN);
            $added++;
        }

        $this->stdout("\nAdded {$added} companies, skipped {$skipped}\n");

        return ExitCode::OK;
    }

    /**
     * Sets the focal company for a peer group.
     *
     * @param string $groupSlug The peer group slug
     * @param string $ticker The focal company ticker
     */
    public function actionSetFocal(string $groupSlug, string $ticker): int
    {
        $group = $this->groupQuery->findBySlug($groupSlug);
        if ($group === null) {
            $this->stderr("Error: Peer group '{$groupSlug}' not found\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $ticker = strtoupper(trim($ticker));
        $company = $this->companyQuery->findByTicker($ticker);
        if ($company === null) {
            $this->stderr("Error: Company '{$ticker}' not found\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        if (!$this->memberQuery->isMember((int) $group['id'], (int) $company['id'])) {
            $this->stderr("Error: {$ticker} is not a member of {$groupSlug}\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $this->memberQuery->setFocal((int) $group['id'], (int) $company['id']);
        $this->stdout("Set focal company: {$ticker}\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Removes a company from a peer group.
     *
     * @param string $groupSlug The peer group slug
     * @param string $ticker The company ticker to remove
     */
    public function actionRemove(string $groupSlug, string $ticker): int
    {
        $group = $this->groupQuery->findBySlug($groupSlug);
        if ($group === null) {
            $this->stderr("Error: Peer group '{$groupSlug}' not found\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $ticker = strtoupper(trim($ticker));
        $company = $this->companyQuery->findByTicker($ticker);
        if ($company === null) {
            $this->stderr("Error: Company '{$ticker}' not found\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $this->memberQuery->removeMember((int) $group['id'], (int) $company['id']);
        $this->stdout("Removed {$ticker} from {$groupSlug}\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Lists all peer groups.
     */
    public function actionList(): int
    {
        $groups = $this->sector !== null
            ? $this->groupQuery->findBySector($this->sector, false)
            : $this->groupQuery->findAllWithStats();

        if (empty($groups)) {
            $this->stdout("No peer groups found\n");
            return ExitCode::OK;
        }

        $this->stdout(str_pad('Slug', 35) . str_pad('Sector', 15) . str_pad('Members', 10) . str_pad('Focal', 8) . "Active\n", Console::BOLD);
        $this->stdout(str_repeat('-', 80) . "\n");

        foreach ($groups as $group) {
            $memberCount = $group['member_count'] ?? '?';
            $hasFocal = !empty($group['has_focal']) ? 'Yes' : 'No';
            $isActive = !empty($group['is_active']) ? 'Yes' : 'No';

            $this->stdout(str_pad($group['slug'], 35));
            $this->stdout(str_pad($group['sector'], 15));
            $this->stdout(str_pad((string) $memberCount, 10));
            $this->stdout(str_pad($hasFocal, 8));
            $this->stdout($isActive . "\n");
        }

        return ExitCode::OK;
    }

    /**
     * Shows details of a peer group.
     *
     * @param string $groupSlug The peer group slug
     */
    public function actionShow(string $groupSlug): int
    {
        $group = $this->groupQuery->findBySlug($groupSlug);
        if ($group === null) {
            $this->stderr("Error: Peer group '{$groupSlug}' not found\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $this->stdout("\nPeer Group: ", Console::BOLD);
        $this->stdout($group['name'] . "\n");
        $this->stdout(str_repeat('-', 50) . "\n");

        $this->stdout("Slug:        {$group['slug']}\n");
        $this->stdout("Sector:      {$group['sector']}\n");
        $this->stdout("Policy ID:   " . ($group['policy_id'] ?? 'None (using sector default)') . "\n");
        $this->stdout("Active:      " . ($group['is_active'] ? 'Yes' : 'No') . "\n");
        $this->stdout("Description: " . ($group['description'] ?? '-') . "\n");

        $members = $this->memberQuery->findByGroup((int) $group['id']);
        $this->stdout("\nMembers (" . count($members) . "):\n");

        if (empty($members)) {
            $this->stdout("  (none)\n");
        } else {
            foreach ($members as $member) {
                $focal = $member['is_focal'] ? ' [FOCAL]' : '';
                $name = $member['name'] ?? '';
                $this->stdout("  {$member['ticker']}{$focal}");
                if ($name) {
                    $this->stdout(" - {$name}");
                }
                $this->stdout("\n");
            }
        }

        return ExitCode::OK;
    }

    /**
     * Deactivates a peer group.
     *
     * @param string $groupSlug The peer group slug
     */
    public function actionDeactivate(string $groupSlug): int
    {
        $group = $this->groupQuery->findBySlug($groupSlug);
        if ($group === null) {
            $this->stderr("Error: Peer group '{$groupSlug}' not found\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $this->groupQuery->deactivate((int) $group['id']);
        $this->stdout("Deactivated: {$groupSlug}\n", Console::FG_YELLOW);

        return ExitCode::OK;
    }

    /**
     * Activates a peer group.
     *
     * @param string $groupSlug The peer group slug
     */
    public function actionActivate(string $groupSlug): int
    {
        $group = $this->groupQuery->findBySlug($groupSlug);
        if ($group === null) {
            $this->stderr("Error: Peer group '{$groupSlug}' not found\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $this->groupQuery->activate((int) $group['id']);
        $this->stdout("Activated: {$groupSlug}\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Assigns a collection policy to a peer group.
     *
     * @param string $groupSlug The peer group slug
     * @param int|null $policyId The policy ID (null to clear)
     */
    public function actionAssignPolicy(string $groupSlug, ?int $policyId = null): int
    {
        $group = $this->groupQuery->findBySlug($groupSlug);
        if ($group === null) {
            $this->stderr("Error: Peer group '{$groupSlug}' not found\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $this->groupQuery->assignPolicy((int) $group['id'], $policyId);

        if ($policyId === null) {
            $this->stdout("Cleared policy assignment for {$groupSlug}\n");
        } else {
            $this->stdout("Assigned policy {$policyId} to {$groupSlug}\n", Console::FG_GREEN);
        }

        return ExitCode::OK;
    }

    private function generateSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s_]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug;
    }
}
