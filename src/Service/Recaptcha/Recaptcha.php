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
    public const STATE_FALLBACK_V2_REQUIRED = 'fallback_v2_required';

    private const MODE_POLICY_V3 = 'policy_v3';
    private const MODE_CHECKBOX_V2 = 'checkbox_v2';

    private const DEFAULT_ALLOWED_HOSTS = [
        'trustandmarket.com',
        'rec.trustandmarket.com',
    ];

    private const SUPPORTED_ACTIONS = [
        self::ACTION_LOGIN,
        self::ACTION_REGISTER,
        self::ACTION_RESET_PASSWORD,
        self::ACTION_CONTACT_US,
        self::ACTION_FEEDBACKS,
        self::ACTION_NEWSLETTER,
    ];

    private const V2_FALLBACK_ERROR_TYPES = [
        'missing_configuration',
        'missing_token',
        'missing_user_agent',
        'missing_risk_analysis',
        'assessment_exception',
    ];

    public function getSiteKey(): string
    {
        return $this->readEnv('RECAPTCHA_SITE_KEY');
    }

    public function getV2SiteKey(): string
    {
        return $this->readEnv('RECAPTCHA_V2_SITE_KEY');
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

    public function isV2FallbackEnabled(): bool
    {
        if ($this->getProjectId() === '' || $this->getV2SiteKey() === '') {
            return false;
        }

        return $this->readBooleanEnv('RECAPTCHA_V2_FALLBACK_ENABLED');
    }

    public function shouldUseV2Fallback(string $action, string $errorType): bool
    {
        if (!$this->isSupportedAction($action) || !$this->isV2FallbackEnabled()) {
            return false;
        }

        if (!in_array($errorType, self::V2_FALLBACK_ERROR_TYPES, true)) {
            return false;
        }

        $configuredActions = $this->getV2FallbackActions();
        if ($configuredActions === []) {
            return true;
        }

        return in_array($action, $configuredActions, true);
    }

    public function assess(string $action, ?string $token): array
    {
        return $this->assessPrimary($action, $token);
    }

    public function assessPrimary(string $action, ?string $token): array
    {
        return $this->evaluate($this->getSiteKey(), $token, $this->getProjectId(), $action, self::MODE_POLICY_V3);
    }

    public function assessFallbackV2(string $action, ?string $token): array
    {
        return $this->evaluate($this->getV2SiteKey(), $token, $this->getProjectId(), $action, self::MODE_CHECKBOX_V2);
    }

    public function create_assessment(string $recaptchaKey, string $token, string $project, string $action): array
    {
        $mode = $recaptchaKey === $this->getV2SiteKey() ? self::MODE_CHECKBOX_V2 : self::MODE_POLICY_V3;
        $result = $this->evaluate($recaptchaKey, $token, $project, $action, $mode);

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

    private function evaluate(string $recaptchaKey, ?string $token, string $project, string $action, string $mode): array
    {
        $this->configureGoogleCredentials();

        if (!$this->isSupportedAction($action)) {
            return $this->technicalError('Action reCAPTCHA non configuree.', 'unknown_action');
        }

        if ($recaptchaKey === '' || $project === '') {
            return $this->buildTechnicalOutcome($mode, $action, 'Configuration reCAPTCHA incomplete.', 'missing_configuration');
        }

        if (trim((string) $token) === '') {
            if ($mode === self::MODE_CHECKBOX_V2) {
                return $this->challenge('Verification de securite requise. Merci de reessayer.', [
                    'error_type' => 'missing_token',
                ]);
            }

            return $this->buildTechnicalOutcome($mode, $action, 'Verification de securite indisponible. Merci de reessayer.', 'missing_token');
        }

        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            return $this->buildTechnicalOutcome($mode, $action, 'Verification de securite indisponible. Merci de reessayer.', 'missing_user_agent');
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
                return $this->buildTechnicalOutcome($mode, $action, 'Verification de securite indisponible. Merci de reessayer.', 'missing_risk_analysis');
            }

            $score = (float) $risk->getScore();
            $reasons = [];
            foreach ($risk->getReasons() as $reason) {
                $reasons[] = (string) $reason;
            }

            $hostname = (string) $tokenProps->getHostname();
            if ($this->mustValidateAction($mode) && $tokenProps->getAction() !== $action) {
                return $this->challenge('Verification de securite requise. Merci de reessayer.', [
                    'score' => $score,
                    'hostname' => $hostname,
                    'reasons' => $reasons,
                    'error_type' => 'unexpected_action',
                ]);
            }

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

            return $this->allow($score, $hostname, $reasons);
        } catch (\Throwable $e) {
            error_log('reCAPTCHA error: ' . $e->getMessage());

            return $this->buildTechnicalOutcome($mode, $action, 'Verification de securite indisponible. Merci de reessayer.', 'assessment_exception');
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

    private function readBooleanEnv(string $name): bool
    {
        $value = strtolower($this->readEnv($name));

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
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

    private function getV2FallbackActions(): array
    {
        $configured = $this->readEnv('RECAPTCHA_V2_FALLBACK_ACTIONS');
        if ($configured === '') {
            return [];
        }

        $actions = array_filter(array_map('trim', explode(',', $configured)));

        return array_values(array_intersect($actions, self::SUPPORTED_ACTIONS));
    }

    private function isSupportedAction(string $action): bool
    {
        return in_array($action, self::SUPPORTED_ACTIONS, true);
    }

    private function mustValidateAction(string $mode): bool
    {
        return $mode === self::MODE_POLICY_V3;
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

    private function allow(float $score, string $hostname, array $reasons): array
    {
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

    private function fallbackRequired(string $message, string $errorType): array
    {
        return [
            'response' => false,
            'state' => self::STATE_FALLBACK_V2_REQUIRED,
            'message' => $message,
            'code' => 503,
            'score' => null,
            'hostname' => '',
            'reasons' => [],
            'error_type' => $errorType,
        ];
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

    private function buildTechnicalOutcome(string $mode, string $action, string $message, string $errorType): array
    {
        if ($mode === self::MODE_POLICY_V3 && $this->shouldUseV2Fallback($action, $errorType)) {
            return $this->fallbackRequired($message, $errorType);
        }

        return $this->technicalError($message, $errorType);
    }
}
