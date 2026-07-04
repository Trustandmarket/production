<?php

namespace App\Service\Recaptcha;

use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use Google\Cloud\RecaptchaEnterprise\V1\Event;
use Google\Cloud\RecaptchaEnterprise\V1\RecaptchaEnterpriseServiceClient;
use Google\Cloud\RecaptchaEnterprise\V1\RiskAnalysis\ClassificationReason;

class Recaptcha
{
    public const ACTION_LOGIN = 'TRUST_LOGIN';
    public const ACTION_REGISTER = 'TRUST_REGISTER';
    public const ACTION_RESET_PASSWORD = 'TRUST_RESET_PASSWORD';
    public const ACTION_CONTACT_US = 'TRUST_CONTACT_US';
    public const ACTION_FEEDBACKS = 'TRUST_FEEDBACKS';
    public const ACTION_NEWSLETTER = 'TRUST_NEWSLETTER';

    public const STATE_ALLOW = 'allow';
    public const STATE_CHALLENGE = 'challenge';
    public const STATE_TECHNICAL_ERROR = 'technical_error';

    private const DEFAULT_ALLOWED_HOSTS = [
        'trustandmarket.com',
        'rec.trustandmarket.com',
    ];

    private const ACTION_SCORE_MIN = [
        self::ACTION_LOGIN => 0.7,
        self::ACTION_REGISTER => 0.75,
        'TRUST_RESETPASSWORD' => 0.8,
        self::ACTION_RESET_PASSWORD => 0.8,
        self::ACTION_CONTACT_US => 0.6,
        self::ACTION_FEEDBACKS => 0.6,
        self::ACTION_NEWSLETTER => 0.6,
    ];

    public function getSiteKey(): string
    {
        return $this->readEnv('RECAPTCHA_SITE_KEY');
    }

    public function getProjectId(): string
    {
        return $this->readEnv('RECAPTCHA_PROJECT_ID');
    }

    public function isConfigured(): bool
    {
        return $this->getSiteKey() !== '' && $this->getProjectId() !== '';
    }

    public function shouldEnforce(?string $environment): bool
    {
        return $this->isConfigured() && in_array((string) $environment, ['prod', 'rec'], true);
    }

    public function assess(string $action, ?string $token): array
    {
        return $this->evaluate($this->getSiteKey(), $token, $this->getProjectId(), $action);
    }

    public function create_assessment(string $recaptchaKey, string $token, string $project, string $action): array
    {
        $result = $this->evaluate($recaptchaKey, $token, $project, $action);

        return [
            'response' => $result['state'] === self::STATE_ALLOW,
            'message' => $result['message'],
            'code' => $result['code'],
            'state' => $result['state'],
            'score' => $result['score'],
            'hostname' => $result['hostname'],
            'reasons' => $result['reasons'],
            'error_type' => $result['error_type'],
        ];
    }

    public function getRealIP(): string
    {
        $cloudflareIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null;
        if (is_string($cloudflareIp) && filter_var($cloudflareIp, FILTER_VALIDATE_IP)) {
            return $cloudflareIp;
        }

        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
        if (is_string($remoteAddr) && filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
            return $remoteAddr;
        }

        return '0.0.0.0';
    }

    private function evaluate(string $recaptchaKey, ?string $token, string $project, string $action): array
    {
        $this->configureGoogleCredentials();

        if ($recaptchaKey === '' || $project === '') {
            return $this->technicalError('Configuration reCAPTCHA incomplete.', 'missing_configuration');
        }

        if (trim((string) $token) === '') {
            return $this->technicalError('Verification de securite indisponible. Merci de reessayer.', 'missing_token');
        }

        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            return $this->technicalError('Verification de securite indisponible. Merci de reessayer.', 'missing_user_agent');
        }

        $ua = (string) $_SERVER['HTTP_USER_AGENT'];
        if ($this->isKnownHeadlessUserAgent($ua)) {
            return $this->challenge('Verification de securite requise. Merci de reessayer.');
        }

        $ip = $this->getRealIP();
        $client = null;

        try {
            $client = new RecaptchaEnterpriseServiceClient();
            $projectName = $client->projectName($project);

            $event = (new Event())
                ->setSiteKey($recaptchaKey)
                ->setToken((string) $token)
                ->setUserAgent($ua);

            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $event->setUserIpAddress($ip);
            }

            $assessment = (new Assessment())->setEvent($event);
            $response = $client->createAssessment($projectName, $assessment);

            $tokenProps = $response->getTokenProperties();
            if ($tokenProps === null || !$tokenProps->getValid()) {
                return $this->challenge('Verification de securite requise. Merci de reessayer.', [
                    'error_type' => 'invalid_token',
                ]);
            }

            $risk = $response->getRiskAnalysis();
            if ($risk === null) {
                return $this->technicalError('Verification de securite indisponible. Merci de reessayer.', 'missing_risk_analysis');
            }

            $score = (float) $risk->getScore();
            $reasons = [];
            foreach ($risk->getReasons() as $reason) {
                $reasons[] = (string) $reason;
            }

            if ($tokenProps->getAction() !== $action) {
                return $this->challenge('Verification de securite requise. Merci de reessayer.', [
                    'score' => $score,
                    'hostname' => (string) $tokenProps->getHostname(),
                    'reasons' => $reasons,
                    'error_type' => 'unexpected_action',
                ]);
            }

            $hostname = (string) $tokenProps->getHostname();
            if (!in_array($hostname, $this->getAllowedHosts(), true)) {
                return $this->challenge('Verification de securite requise. Merci de reessayer.', [
                    'score' => $score,
                    'hostname' => $hostname,
                    'reasons' => $reasons,
                    'error_type' => 'unexpected_hostname',
                ]);
            }

            $createTime = $tokenProps->getCreateTime();
            if ($createTime === null || (time() - $createTime->getSeconds()) > 120) {
                return $this->challenge('Verification de securite requise. Merci de reessayer.', [
                    'score' => $score,
                    'hostname' => $hostname,
                    'reasons' => $reasons,
                    'error_type' => 'expired_token',
                ]);
            }

            if ($this->mustBlockByRiskReason($risk->getReasons(), $score)) {
                return $this->challenge('Verification de securite requise. Merci de reessayer.', [
                    'score' => $score,
                    'hostname' => $hostname,
                    'reasons' => $reasons,
                    'error_type' => 'risk_reasons',
                ]);
            }

            $minScore = self::ACTION_SCORE_MIN[$action] ?? null;
            if ($minScore === null) {
                return $this->technicalError('Action reCAPTCHA non configuree.', 'unknown_action');
            }

            if ($score < $minScore) {
                return $this->challenge('Verification de securite requise. Merci de reessayer.', [
                    'score' => $score,
                    'hostname' => $hostname,
                    'reasons' => $reasons,
                    'error_type' => 'score_below_threshold',
                ]);
            }

            return [
                'response' => true,
                'state' => self::STATE_ALLOW,
                'message' => 'OK',
                'code' => 200,
                'score' => $score,
                'hostname' => $hostname,
                'reasons' => $reasons,
                'error_type' => null,
            ];
        } catch (\Throwable $e) {
            error_log('reCAPTCHA error: ' . $e->getMessage());

            return $this->technicalError('Verification de securite indisponible. Merci de reessayer.', 'assessment_exception');
        } finally {
            if ($client !== null) {
                $client->close();
            }
        }
    }

    private function configureGoogleCredentials(): void
    {
        if (getenv('GOOGLE_APPLICATION_CREDENTIALS')) {
            return;
        }

        $configuredPath = $this->readEnv('GOOGLE_APPLICATION_CREDENTIALS');
        if ($configuredPath !== '' && is_file($configuredPath)) {
            putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $configuredPath);
            return;
        }

        $defaultCredentialsPath = __DIR__ . '/../../../trust-market/security_form.json';
        if (is_file($defaultCredentialsPath)) {
            putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $defaultCredentialsPath);
        }
    }

    private function readEnv(string $name): string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return is_string($value) ? trim($value) : '';
    }

    private function getAllowedHosts(): array
    {
        $configured = $this->readEnv('RECAPTCHA_ALLOWED_HOSTS');
        if ($configured === '') {
            return self::DEFAULT_ALLOWED_HOSTS;
        }

        $hosts = array_filter(array_map('trim', explode(',', $configured)));

        return $hosts === [] ? self::DEFAULT_ALLOWED_HOSTS : array_values($hosts);
    }

    private function mustBlockByRiskReason(iterable $reasons, float $score): bool
    {
        foreach ($reasons as $reason) {
            if ($reason === ClassificationReason::UNEXPECTED_ENVIRONMENT) {
                return true;
            }

            if ($reason === ClassificationReason::TOO_MUCH_TRAFFIC && $score < 0.4) {
                return true;
            }
        }

        return false;
    }

    private function isKnownHeadlessUserAgent(string $userAgent): bool
    {
        return stripos($userAgent, 'Headless') !== false
            || stripos($userAgent, 'PhantomJS') !== false
            || stripos($userAgent, 'Puppeteer') !== false;
    }

    private function challenge(string $message, array $context = []): array
    {
        return array_merge([
            'response' => false,
            'state' => self::STATE_CHALLENGE,
            'message' => $message,
            'code' => 403,
            'score' => null,
            'hostname' => '',
            'reasons' => [],
            'error_type' => null,
        ], $context);
    }

    private function technicalError(string $message, string $errorType): array
    {
        return [
            'response' => false,
            'state' => self::STATE_TECHNICAL_ERROR,
            'message' => $message,
            'code' => 503,
            'score' => null,
            'hostname' => '',
            'reasons' => [],
            'error_type' => $errorType,
        ];
    }
}
