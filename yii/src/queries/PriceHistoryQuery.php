<?php

declare(strict_types=1);

namespace app\queries;

use DateTimeImmutable;
use yii\db\Connection;

/**
 * Queries for stock, commodity, and index price history.
 */
class PriceHistoryQuery
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function findBySymbolAndDate(string $symbol, DateTimeImmutable $date): ?array
    {
        $row = $this->db->createCommand(
            'SELECT * FROM price_history 
             WHERE symbol = :symbol AND price_date = :date'
        )
            ->bindValues([
                ':symbol' => $symbol,
                ':date' => $date->format('Y-m-d')
            ])
            ->queryOne();

        return $row === false ? null : $row;
    }

    public function findLatestBySymbol(string $symbol): ?array
    {
        $row = $this->db->createCommand(
            'SELECT * FROM price_history 
             WHERE symbol = :symbol 
             ORDER BY price_date DESC 
             LIMIT 1'
        )
            ->bindValue(':symbol', $symbol)
            ->queryOne();

        return $row === false ? null : $row;
    }

    public function insert(array $data): int
    {
        $this->db->createCommand()
            ->insert('price_history', $data)
            ->execute();

        return (int) $this->db->getLastInsertID();
    }

    /**
     * Bulk insert price history records.
     *
     * @param list<array> $records
     * @return int Number of records inserted
     */
    public function bulkInsert(array $records): int
    {
        if (empty($records)) {
            return 0;
        }

        $columns = array_keys($records[0]);
        $rows = array_map(fn (array $record) => array_values($record), $records);

        $this->db->createCommand()
            ->batchInsert('price_history', $columns, $rows)
            ->execute();

        return count($records);
    }

    /**
     * Find existing price dates for a symbol within a date range.
     *
     * @return list<string> List of existing dates (Y-m-d format)
     */
    public function findExistingDates(string $symbol, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $rows = $this->db->createCommand(
            'SELECT price_date FROM price_history
             WHERE symbol = :symbol
               AND price_date BETWEEN :from AND :to'
        )
            ->bindValues([
                ':symbol' => $symbol,
                ':from' => $from->format('Y-m-d'),
                ':to' => $to->format('Y-m-d'),
            ])
            ->queryColumn();

        return $rows;
    }
}
