<?php

namespace App\Service;

class BrevoMailer
{
    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, error: string}
     */
    public function sendTemplate(array $payload): array
    {
        $apiKey = $_SERVER['SENDBLUE_API_KEY'] ?? '';
        if ($apiKey === '') {
            return ['ok' => false, 'error' => 'SENDBLUE_API_KEY manquant'];
        }

        $ch = curl_init();
        if ($ch === false) {
            return ['ok' => false, 'error' => 'curl_init failed'];
        }

        curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/smtp/email');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'api-key: ' . $apiKey,
            'content-type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'error' => $curlError !== '' ? $curlError : 'curl_exec failed'];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            return ['ok' => false, 'error' => 'HTTP ' . $httpCode . ' ' . (string) $response];
        }

        return ['ok' => true, 'error' => ''];
    }
}