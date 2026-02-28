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
    public function __construct(
    ) {
    }

    /**
     * Créez une évaluation pour analyser le risque d'une action dans l'interface utilisateur.
     * @param string $recaptchaKey La clé reCAPTCHA associée au site ou à l'application
     * @param string $token Jeton généré auprès du client.
     * @param string $project L'ID de votre projet Google Cloud.
     * @param string $action Nom d'action correspondant au jeton.
     * @throws Exception
     */
    // Fonction pour récupérer l'IP de provenance
    function getRealIP()
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
        // Créez le client reCAPTCHA.
        // À FAIRE : mettre en cache le code de génération du client (recommandé) ou appeler client.close() avant de quitter la méthode.
        putenv("GOOGLE_APPLICATION_CREDENTIALS=" . __DIR__ . '/../../../trust-market/security_form.json');
        $result = ['response' => false, 'message' => 'Captcha invalide', 'code' => 403];

        // User-Agent obligatoire
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            return $result;
        }
        // Bloque les environnements headless connus
        $ua = $_SERVER['HTTP_USER_AGENT'];
        if (
            stripos($ua, 'Headless') !== false ||
            stripos($ua, 'PhantomJS') !== false ||
            stripos($ua, 'Puppeteer') !== false
        ) 
        {
            return $result;
        }

        $ip = getRealIP();
        $client = new RecaptchaEnterpriseServiceClient();
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
        // Créez la demande d'évaluation.
        $assessment = (new Assessment())
            ->setEvent($event);
        
        try 
        {
            $response = $client->createAssessment($projectName,$assessment);

            //validité du token 
            //Recupération des propriétés du token
            $tokenProps = $response->getTokenProperties();                          
            // Vérifier la validité du token
            if ($tokenProps === null || !$tokenProps->getValid()) 
            {
                return $result;
            }

            //Récupération de la raison pour éliminer les headless / puppeteer
            $risk = $response->getRiskAnalysis();
            if (!$risk) 
            {
                return $result;
            }
            $reasons = $risk ? $risk->getReasons() : [];
            $score = $risk ? $risk->getScore() : 0;
            if (!empty($reasons)) 
            {
                foreach ($reasons as $reason) 
                {
                    // Blocage direct (signal bot très fort)
                    if ($reason === ClassificationReason::UNEXPECTED_ENVIRONMENT) 
                    {
                        return $result;
                    }
                    // Blocage conditionnel (évite les faux positifs en cas de pic de trafic)
                    if ($reason === ClassificationReason::TOO_MUCH_TRAFFIC 
                        && $score < 0.4) 
                    {
                        return $result;
                    }
                }
            }
         
            // Vérifiez si l'action attendue a été exécutée.
            if ($tokenProps->getAction() !== $action) {
                return $result;
            } 
            // On vérifie le hostname
            $allowedHosts = ['trustandmarket.com','rec.trustandmarket.com','*.trustandmarket.com'];
            if (!in_array($tokenProps->getHostname(), $allowedHosts,true)) {
                return $result;
            }
            // Anti replay (token < 2 min)
            $createTimeObj = $tokenProps->getCreateTime();
            if (!$createTimeObj) 
            {
                return $result;
            }
            $createTime = $createTimeObj->getSeconds();            
            if (time() - $createTime > 120) 
            {
               return $result;
            }

            //Contrôle du score : il faudra adapter le seuil par action
            if ($score < 0.5) 
            {
                return $result;
            }
           
            //Sinon tous les checks sont OK     
            return ['response' => true, 'message' => 'OK', 'code' => 200];
          
        } catch (\Throwable $e) 
        {  
            error_log('reCAPTCHA error: '.$e->getMessage());
            return $result;
        }
    }

}