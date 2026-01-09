<?php

declare(strict_types=1);

namespace app\handlers\industry;

use app\dto\industry\MemberActionResult;
use app\dto\industry\RemoveMemberRequest;
use app\queries\IndustryMemberQuery;
use app\queries\IndustryQuery;
use Throwable;
use Yii;
use yii\log\Logger;

/**
 * Handler for removing a member company from an industry.
 */
final class RemoveMemberHandler implements RemoveMemberInterface
{
    public function __construct(
        private readonly IndustryQuery $industryQuery,
        private readonly IndustryMemberQuery $memberQuery,
        private readonly Logger $logger,
    ) {
    }

    public function remove(RemoveMemberRequest $request): MemberActionResult
    {
        $this->logger->log(
            [
                'message' => 'Removing member from industry',
                'industry_id' => $request->industryId,
                'company_id' => $request->companyId,
                'actor' => $request->actorUsername,
            ],
            Logger::LEVEL_INFO,
            'industry'
        );

        $industry = $this->industryQuery->findById($request->industryId);
        if ($industry === null) {
            return MemberActionResult::failure(['Industry not found.']);
        }

        if (!$this->memberQuery->isMember($request->industryId, $request->companyId)) {
            return MemberActionResult::failure(['Company is not a member of this industry.']);
        }

        $transaction = Yii::$app->db->beginTransaction();

        try {
            $this->memberQuery->removeMember($request->industryId, $request->companyId);
            $transaction->commit();

            $this->logger->log(
                [
                    'message' => 'Member removed from industry',
                    'industry_id' => $request->industryId,
                    'company_id' => $request->companyId,
                    'actor' => $request->actorUsername,
                ],
                Logger::LEVEL_INFO,
                'industry'
            );

            return MemberActionResult::success();
        } catch (Throwable $e) {
            $transaction->rollBack();

            $this->logger->log(
                [
                    'message' => 'Failed to remove member from industry',
                    'industry_id' => $request->industryId,
                    'company_id' => $request->companyId,
                    'error' => $e->getMessage(),
                ],
                Logger::LEVEL_ERROR,
                'industry'
            );

            return MemberActionResult::failure(['Failed to remove member: ' . $e->getMessage()]);
        }
    }
}
