<?php

declare(strict_types=1);

use yii\db\Migration;

/**
 * Renames config_yaml column to config_json for clarity.
 */
class m251229_235826_rename_config_yaml_to_config_json extends Migration
{
    public function safeUp(): void
    {
        $this->renameColumn('{{%industry_config}}', 'config_yaml', 'config_json');
    }

    public function safeDown(): void
    {
        $this->renameColumn('{{%industry_config}}', 'config_json', 'config_yaml');
    }
}
