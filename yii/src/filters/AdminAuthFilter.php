<?php

declare(strict_types=1);

namespace app\filters;

use Yii;
use yii\base\ActionFilter;
use yii\web\UnauthorizedHttpException;

/**
 * Environment-driven HTTP Basic Authentication filter for admin routes.
 *
 * Credentials are read from environment variables:
 * - ADMIN_USERNAME (required)
 * - ADMIN_PASSWORD (required)
 *
 * If credentials are not configured, all requests are denied.
 */
final class AdminAuthFilter extends ActionFilter
{
    /**
     * @throws UnauthorizedHttpException
     */
    public function beforeAction($action): bool
    {
        $username = $this->getEnvUsername();
        $password = $this->getEnvPassword();

        if ($username === null || $password === null) {
            Yii::warning(
                'Admin auth not configured: ADMIN_USERNAME or ADMIN_PASSWORD not set',
                'auth'
            );
            $this->sendAuthChallenge();
            throw new UnauthorizedHttpException('Admin authentication not configured.');
        }

        $authHeader = Yii::$app->request->getHeaders()->get('Authorization');

        if ($authHeader === null || !str_starts_with($authHeader, 'Basic ')) {
            $this->sendAuthChallenge();
            throw new UnauthorizedHttpException('Authentication required.');
        }

        $credentials = base64_decode(substr($authHeader, 6), true);
        if ($credentials === false) {
            $this->sendAuthChallenge();
            throw new UnauthorizedHttpException('Invalid authentication header.');
        }

        $parts = explode(':', $credentials, 2);
        if (count($parts) !== 2) {
            $this->sendAuthChallenge();
            throw new UnauthorizedHttpException('Invalid credentials format.');
        }

        [$providedUsername, $providedPassword] = $parts;

        if (!$this->validateCredentials($providedUsername, $providedPassword, $username, $password)) {
            Yii::warning(
                sprintf('Failed admin login attempt for user: %s', $providedUsername),
                'auth'
            );
            $this->sendAuthChallenge();
            throw new UnauthorizedHttpException('Invalid credentials.');
        }

        return true;
    }

    /**
     * Get the authenticated username for audit logging.
     */
    public static function getAuthenticatedUsername(): ?string
    {
        $authHeader = Yii::$app->request->getHeaders()->get('Authorization');

        if ($authHeader === null || !str_starts_with($authHeader, 'Basic ')) {
            return null;
        }

        $credentials = base64_decode(substr($authHeader, 6), true);
        if ($credentials === false) {
            return null;
        }

        $parts = explode(':', $credentials, 2);
        if (count($parts) !== 2) {
            return null;
        }

        return $parts[0];
    }

    private function getEnvUsername(): ?string
    {
        $value = getenv('ADMIN_USERNAME');
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function getEnvPassword(): ?string
    {
        $value = getenv('ADMIN_PASSWORD');
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function validateCredentials(
        string $providedUsername,
        string $providedPassword,
        string $expectedUsername,
        string $expectedPassword
    ): bool {
        return hash_equals($expectedUsername, $providedUsername)
            && hash_equals($expectedPassword, $providedPassword);
    }

    private function sendAuthChallenge(): void
    {
        Yii::$app->response->getHeaders()->set(
            'WWW-Authenticate',
            'Basic realm="AIMM Admin"'
        );
    }
}
