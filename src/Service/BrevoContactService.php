<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class BrevoContactService
{
    public function __construct(private readonly ParameterBagInterface $params)
    {
    }

    public function updateContactAttributes(string $email, array $attributes): bool
    {
        if ($this->params->get('environnement') !== 'prod') {
            return false;
        }

        $apiKey = $_SERVER['SENDBLUE_API_KEY'] ?? null;
        if (!$apiKey || empty($attributes)) {
            return false;
        }

        $payload = [
            'email' => $email,
            'attributes' => $attributes,
            'updateEnabled' => true,
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://api.brevo.com/v3/contacts');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'api-key: ' . $apiKey,
            'content-type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $hasSucceeded = $response !== false;

        curl_close($ch);

        return $hasSucceeded;
    }
}
