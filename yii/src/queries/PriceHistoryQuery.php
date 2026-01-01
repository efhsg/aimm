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
}
