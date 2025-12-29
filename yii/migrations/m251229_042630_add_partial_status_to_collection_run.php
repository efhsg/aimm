<?php

declare(strict_types=1);

use yii\db\Migration;

final class m251229_042630_add_partial_status_to_collection_run extends Migration
{
    private const CHECK_NAME = 'chk_collection_run_status';

    public function safeUp(): bool
    {
        if ($this->supportsCheckConstraint()) {
            $this->addCheck(
                self::CHECK_NAME,
                '{{%collection_run}}',
                "status IN ('pending', 'running', 'complete', 'failed', 'partial')"
            );
        }

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->supportsCheckConstraint()) {
            $this->dropCheck(self::CHECK_NAME, '{{%collection_run}}');
        }

        return true;
    }

    private function supportsCheckConstraint(): bool
    {
        $driver = $this->db->driverName;

        if ($driver === 'mysql') {
            return version_compare($this->db->getServerVersion(), '8.0.16', '>=');
        }

        return true;
    }
}
