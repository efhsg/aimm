<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;

final class HealthController extends Controller
{
    public function actionIndex(): string
    {
        Yii::$app->response->format = Response::FORMAT_RAW;
        return "OK\n";
    }
}
