<?php

declare(strict_types=1);

namespace app\handlers\industry;

use app\dto\industry\AddMembersRequest;
use app\dto\industry\AddMembersResult;
use app\queries\CompanyQuery;
use app\queries\IndustryMemberQuery;
use app\queries\IndustryQuery;
use Throwable;
use Yii;
use yii\log\Logger;

/**
 * Handler for adding member companies to an industry.
 */
final class AddMembersHandler implements AddMembersInterface
{
    public function __construct(
        private readonly IndustryQuery $industryQuery,
        private readonly CompanyQuery $companyQuery,
        private readonly IndustryMemberQuery $memberQuery,
        private readonly Logger $logger,
    ) {
    }

    public function add(AddMembersRequest $request): AddMembersResult
    {
        $this->logger->log(
            [
                'message' => 'Adding members to industry',
                'industry_id' => $request->industryId,
                'tickers' => $request->tickers,
                'actor' => $request->actorUsername,
            ],
            Logger::LEVEL_INFO,
            'industry'
        );

        $industry = $this->industryQuery->findById($request->industryId);
        if ($industry === null) {
            return AddMembersResult::failure(['Industry not found.']);
        }

        if (empty($request->tickers)) {
            return AddMembersResult::failure(['No tickers provided.']);
        }

        $transaction = Yii::$app->db->beginTransaction();

        try {
            $added = [];
            $skipped = [];

            foreach ($request->tickers as $ticker) {
                // findOrCreate gets or creates the company record
                $companyId = $this->companyQuery->findOrCreate($ticker);

                if ($this->memberQuery->isMember($request->industryId, $companyId)) {
                    $skipped[] = $ticker;
                } else {
                    $this->memberQuery->addMember($request->industryId, $companyId);
                    $added[] = $ticker;
                }
            }

            $transaction->commit();

            $this->logger->log(
                [
                    'message' => 'Members added to industry',
                    'industry_id' => $request->industryId,
                    'added' => $added,
                    'skipped' => $skipped,
                    'actor' => $request->actorUsername,
                ],
                Logger::LEVEL_INFO,
                'industry'
            );

            return AddMembersResult::success($added, $skipped);
        } catch (Throwable $e) {
            $transaction->rollBack();

            $this->logger->log(
                [
                    'message' => 'Failed to add members to industry',
                    'industry_id' => $request->industryId,
                    'error' => $e->getMessage(),
                ],
                Logger::LEVEL_ERROR,
                'industry'
            );

            return AddMembersResult::failure(['Failed to add members: ' . $e->getMessage()]);
        }
    }
}
