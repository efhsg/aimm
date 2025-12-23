<?php

namespace app\commands;

use Throwable;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

final class TestController extends Controller
{
    public function actionIndex(): int
    {
        $this->stdout("AIMM is ready.\n");
        $this->stdout('PHP: ' . PHP_VERSION . "\n");
        return ExitCode::OK;
    }

    public function actionDb(): int
    {
        try {
            $value = (string) Yii::$app->db->createCommand('SELECT 1')->queryScalar();
        } catch (Throwable $e) {
            $this->stderr('DB connection failed: ' . $e->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("DB OK: {$value}\n");
        return ExitCode::OK;
    }
}

