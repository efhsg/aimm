<?php

declare(strict_types=1);

namespace app\handlers\peergroup;

use app\dto\peergroup\AddMembersRequest;
use app\dto\peergroup\AddMembersResult;
use app\queries\CompanyQuery;
use app\queries\PeerGroupMemberQuery;
use app\queries\PeerGroupQuery;
use Throwable;
use Yii;
use yii\log\Logger;

/**
 * Handler for adding members to a peer group.
 */
final class AddMembersHandler implements AddMembersInterface
{
    public function __construct(
        private readonly PeerGroupQuery $peerGroupQuery,
        private readonly PeerGroupMemberQuery $memberQuery,
        private readonly CompanyQuery $companyQuery,
        private readonly Logger $logger,
    ) {
    }

    public function add(AddMembersRequest $request): AddMembersResult
    {
        $this->logger->log(
            [
                'message' => 'Adding members to peer group',
                'group_id' => $request->groupId,
                'ticker_count' => count($request->tickers),
                'actor' => $request->actorUsername,
            ],
            Logger::LEVEL_INFO,
            'peergroup'
        );

        $group = $this->peerGroupQuery->findById($request->groupId);
        if ($group === null) {
            return AddMembersResult::failure(['Peer group not found.']);
        }

        if (empty($request->tickers)) {
            return AddMembersResult::failure(['No tickers provided.']);
        }

        $transaction = Yii::$app->db->beginTransaction();

        try {
            $added = [];
            $skipped = [];
            $displayOrder = $this->memberQuery->countByGroup($request->groupId);

            foreach ($request->tickers as $ticker) {
                $ticker = $this->normalizeTicker($ticker);
                if ($ticker === '') {
                    continue;
                }

                $companyId = $this->companyQuery->findOrCreate($ticker);

                if ($this->memberQuery->isMember($request->groupId, $companyId)) {
                    $skipped[] = $ticker;
                    continue;
                }

                $this->memberQuery->addMember(
                    $request->groupId,
                    $companyId,
                    false,
                    $displayOrder++,
                    $request->actorUsername
                );

                $added[] = $ticker;
            }

            $transaction->commit();

            $this->logger->log(
                [
                    'message' => 'Members added to peer group',
                    'group_id' => $request->groupId,
                    'added_count' => count($added),
                    'skipped_count' => count($skipped),
                    'actor' => $request->actorUsername,
                ],
                Logger::LEVEL_INFO,
                'peergroup'
            );

            return AddMembersResult::success($added, $skipped);
        } catch (Throwable $e) {
            $transaction->rollBack();

            $this->logger->log(
                [
                    'message' => 'Failed to add members to peer group',
                    'group_id' => $request->groupId,
                    'error' => $e->getMessage(),
                ],
                Logger::LEVEL_ERROR,
                'peergroup'
            );

            return AddMembersResult::failure(['Failed to add members: ' . $e->getMessage()]);
        }
    }

    private function normalizeTicker(string $ticker): string
    {
        $ticker = strtoupper(trim($ticker));
        // Remove any invalid characters - only allow alphanumeric and period (for tickers like BRK.B)
        $ticker = preg_replace('/[^A-Z0-9.]/', '', $ticker);
        return $ticker;
    }
}
