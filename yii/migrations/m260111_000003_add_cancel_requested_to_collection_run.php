<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Class m260111_000003_add_cancel_requested_to_collection_run
 */
class m260111_000003_add_cancel_requested_to_collection_run extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('{{%collection_run}}', 'cancel_requested', $this->boolean()->notNull()->defaultValue(0)->after('status'));
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropColumn('{{%collection_run}}', 'cancel_requested');
    }
}
