<?php

declare(strict_types=1);

namespace app\commands;

use app\dto\CollectPriceHistoryRequest;
use app\handlers\collection\CollectPriceHistoryHandler;
use app\queries\CompanyQuery;
use app\queries\IndustryMemberQuery;
use app\queries\IndustryQuery;
use app\queries\PriceHistoryQuery;
use DateTimeImmutable;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\log\Logger;

/**
 * CLI controller for collecting historical stock prices.
 */
final class CollectPriceHistoryController extends Controller
{
    /**
     * @var int Number of months of history to collect
     */
    public int $months = 24;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['months']);
    }

    /**
     * Collect price history for a single ticker.
     *
     * Usage: yii collect-price-history/ticker XOM --months=24
     */
    public function actionTicker(string $ticker): int
    {
        $this->stdout("Collecting price history for {$ticker}...\n");

        $handler = $this->createHandler();
        $result = $handler->collect($this->createRequest($ticker));

        if ($result->error !== null) {
            $this->stderr("  Error: {$result->error}\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("  Collected: {$result->recordsCollected} records\n");
        $this->stdout("  Inserted: {$result->recordsInserted} new records\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Collect price history for all companies in an industry.
     *
     * Usage: yii collect-price-history/industry global-energy-supermajors --months=24
     */
    public function actionIndustry(string $slug): int
    {
        $industryQuery = Yii::$container->get(IndustryQuery::class);
        $memberQuery = Yii::$container->get(IndustryMemberQuery::class);

        $industry = $industryQuery->findBySlug($slug);
        if ($industry === null) {
            $this->stderr("Industry not found: {$slug}\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $members = $memberQuery->findByIndustry((int) $industry['id']);
        if (empty($members)) {
            $this->stderr("No companies in industry\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $this->stdout("Collecting price history for industry: {$industry['name']}\n");
        $this->stdout("Period: {$this->months} months\n\n");

        $handler = $this->createHandler();
        $totalCollected = 0;
        $totalInserted = 0;
        $errors = [];

        foreach ($members as $member) {
            $ticker = $member['ticker'];
            $this->stdout("  {$ticker}: ", Console::FG_CYAN);

            $result = $handler->collect($this->createRequest($ticker));

            if ($result->error !== null) {
                $this->stdout("ERROR - {$result->error}\n", Console::FG_RED);
                $errors[] = $ticker;
                continue;
            }

            $totalCollected += $result->recordsCollected;
            $totalInserted += $result->recordsInserted;
            $this->stdout("{$result->recordsInserted} new records\n", Console::FG_GREEN);
        }

        $this->stdout("\n");
        $this->stdout("Total: {$totalCollected} records collected, {$totalInserted} inserted\n", Console::FG_GREEN);

        if (!empty($errors)) {
            $this->stderr("Errors: " . implode(', ', $errors) . "\n", Console::FG_YELLOW);
        }

        return empty($errors) ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * Collect price history for all companies in the database.
     *
     * Usage: yii collect-price-history/all --months=24
     */
    public function actionAll(): int
    {
        $companyQuery = Yii::$container->get(CompanyQuery::class);
        $companies = $companyQuery->findAll();

        if (empty($companies)) {
            $this->stderr("No companies found\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $this->stdout("Collecting price history for all companies\n");
        $this->stdout("Period: {$this->months} months\n");
        $this->stdout("Companies: " . count($companies) . "\n\n");

        $handler = $this->createHandler();
        $totalCollected = 0;
        $totalInserted = 0;
        $errors = [];

        foreach ($companies as $company) {
            $ticker = $company['ticker'];
            $this->stdout("  {$ticker}: ", Console::FG_CYAN);

            $result = $handler->collect($this->createRequest($ticker));

            if ($result->error !== null) {
                $this->stdout("ERROR - {$result->error}\n", Console::FG_RED);
                $errors[] = $ticker;
                continue;
            }

            $totalCollected += $result->recordsCollected;
            $totalInserted += $result->recordsInserted;
            $this->stdout("{$result->recordsInserted} new records\n", Console::FG_GREEN);
        }

        $this->stdout("\n");
        $this->stdout("Total: {$totalCollected} records collected, {$totalInserted} inserted\n", Console::FG_GREEN);

        if (!empty($errors)) {
            $this->stderr("Errors: " . implode(', ', $errors) . "\n", Console::FG_YELLOW);
        }

        return empty($errors) ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    private function createHandler(): CollectPriceHistoryHandler
    {
        return new CollectPriceHistoryHandler(
            priceQuery: Yii::$container->get(PriceHistoryQuery::class),
            logger: Yii::$container->get(Logger::class),
        );
    }

    private function createRequest(string $ticker): CollectPriceHistoryRequest
    {
        $to = new DateTimeImmutable();
        $from = $to->modify("-{$this->months} months");

        return new CollectPriceHistoryRequest(
            ticker: $ticker,
            from: $from,
            to: $to,
        );
    }
}
