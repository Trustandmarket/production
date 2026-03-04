<?php

// src/Services/ServiceManager.php

//Couche d'acces au données

namespace App\Service\Recaptcha;

use App\Entity\Translation\WpPostsTranslation;
use App\Entity\Translation\WpPostmetaTranslation;
use App\Entity\Translation\WpTermsTranslation;
use Exception;
use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use Google\Cloud\RecaptchaEnterprise\V1\Event;
use Google\Cloud\RecaptchaEnterprise\V1\RecaptchaEnterpriseServiceClient;
use Google\Cloud\RecaptchaEnterprise\V1\TokenProperties\InvalidReason;
use Symfony\Component\HttpFoundation\Response;
use Google\Cloud\RecaptchaEnterprise\V1\RiskAnalysis\ClassificationReason;

/**
 * Class ServiceManager
 * @package App\Service
 */
class Recaptcha
{
    //Variables globales quel que soit l'appel 
    private const result = ['response' => false, 'message' => 'Captcha invalide', 'code' => 403];
    private const  allowedHosts = [
        'trustandmarket.com',
        'rec.trustandmarket.com'
        ];
    $Actiontrust = [
                'TRUST_LOGIN' => 0.7,
                'TRUST_REGISTER' => 0.75,
                'TRUST_RESETPASSWORD' => 0.8,
                'TRUST_CONTACT_US' => 0.6,
                'TRUST_FEEDBACKS' => 0.6,
                'TRUST_NEWSLETTER'=> 0.6,
                ];
    // filtrage par raisons de l'action pour éliminer les headless / puppeteer
    private function mustBlockByRiskReason(iterable $reasons, float $score): bool
    {
        foreach ($reasons as $reason) 
        {
            // Blocage direct (signal bot très fort)
            if ($reason === ClassificationReason::UNEXPECTED_ENVIRONMENT) {
                return true;
            }
            // Blocage conditionnel (évite les faux positifs en cas de pic de trafic)
            if ($reason === ClassificationReason::TOO_MUCH_TRAFFIC && $score < 0.4) {
                return true;
            }
        }
        return false;
    }
    // Bloque les environnements headless connus 
    private function isKnownHeadlessUserAgent(string $userAgent): bool
    {
        return stripos($userAgent, 'Headless') !== false
            || stripos($userAgent, 'PhantomJS') !== false
            || stripos($userAgent, 'Puppeteer') !== false;
    }
    // À FAIRE : mettre en cache le code de génération du client (recommandé)
    // ou appeler client.close() avant de quitter la méthode.
    private function configureGoogleCredentials(): void
    {
        if (getenv('GOOGLE_APPLICATION_CREDENTIALS')) {
            return;
        }

        $defaultCredentialsPath = __DIR__ . '/../../../trust-market/security_form.json';
        if (is_file($defaultCredentialsPath)) {
            putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $defaultCredentialsPath);
        }
    }
    // Fonction pour récupérer l'IP de provenance
    public function getRealIP() : string 
    {
        // IP Cloudflare valide ?
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP']) && !empty($_SERVER['REMOTE_ADDR'])) 
        {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        // fallback
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    // Fonction pour l'évaluation
    function create_assessment(string $recaptchaKey,string $token,string $project,string $action) 
    {
        $this->configureGoogleCredentials();
        // User-Agent obligatoire
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            return self::result;
        }
        // Bloque les environnements headless connus
        $ua = (string) $_SERVER['HTTP_USER_AGENT'];
        if ($this->isKnownHeadlessUserAgent($ua)) 
        {
            return self::result;
        }

        $ip = $this->getRealIP();
        $client = new RecaptchaEnterpriseServiceClient();
        try 
        {
            $projectName = $client->projectName($project);
            // Définissez les propriétés de l'événement à suivre.
            $event = (new Event())
                ->setSiteKey($recaptchaKey)
                ->setToken($token)
                ->setUserAgent($ua);

            // on ajoute l'ip ici si valide
            if (filter_var($ip, FILTER_VALIDATE_IP)) 
            {
                $event->setUserIpAddress($ip); 
            }
            $assessment = (new Assessment())->setEvent($event);
            $response = $client->createAssessment($projectName,$assessment);

            //validité du token 
            //Recupération des propriétés du token
            $tokenProps = $response->getTokenProperties();                          
            // Vérifier la validité du token
            if ($tokenProps === null || !$tokenProps->getValid()) 
            {
                return self::result;
            }

            //Risk analysis
            $risk = $response->getRiskAnalysis();
            if ($risk === null) 
            {
                return self::result;
            }
            $score = $risk->getScore();
            if ($this->mustBlockByRiskReason($risk->getReasons(), $score)) {
                return self::result;
            }
            // Vérifiez si l'action attendue a été exécutée.
            if ($tokenProps->getAction() !== $action) {
                return self::result;
            } 
            // On vérifie le hostname
            if (!in_array($tokenProps->getHostname(), sel::allowedHosts,true)) {
                return self::result;
            }
            // Anti replay (token < 2 min)
            $createTimeObj = $tokenProps->getCreateTime();
            if ($createTime === null || (time() - $createTimeObj->getSeconds()) > 120) {
                return self::result;
            }
            //Contrôle du score : seuil par action
            $minScore = self::Actiontrust[$action] ?? 0.5;
            if ($score < $minScore) 
            {
                return self::result;
            }
           
            //Sinon tous les checks sont OK     
            return ['response' => true, 'message' => 'OK', 'code' => 200];
          
        } catch (\Throwable $e) 
        {  
            error_log('reCAPTCHA error: '.$e->getMessage());
            return self::result;
        }finally { $client->close();}
    }
    
}