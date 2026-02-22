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
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }

        return '0.0.0.0';
    }
    // Fonction pour l'évaluation
    function create_assessment(string $recaptchaKey,string $token,string $project,string $action) 
    {
        // Créez le client reCAPTCHA.
        // À FAIRE : mettre en cache le code de génération du client (recommandé) ou appeler client.close() avant de quitter la méthode.
        putenv("GOOGLE_APPLICATION_CREDENTIALS=" . __DIR__ . '/../../../trust-market/security_form.json');
        $client = new RecaptchaEnterpriseServiceClient();
        $projectName = $client->projectName($project);
        $result = ['response' => false, 'message' => 'Captcha invalide', 'code' => 403];
        // Définissez les propriétés de l'événement à suivre.
        $event = (new Event())
            ->setSiteKey($recaptchaKey)
            ->setToken($token)
            ->setUserIpAddress($this->getRealIP());
        
        // Créez la demande d'évaluation.
        $assessment = (new Assessment())
            ->setEvent($event);
        
        try 
        {
            $response = $client->createAssessment($projectName,$assessment);
            //Recupération des propriétés du token
            $tokenProps = $response->getTokenProperties();
                                         
            // Vérifier la validité du token
            if (!$tokenProps->getValid()) {
                return $result;
            }

            // Vérifiez si l'action attendue a été exécutée.
            if ($tokenProps->getAction() !== $action) {
                return $result;
            } 
            // On vérifie le hostname
            $allowedHosts = ['trustandmarket.com','www.trustandmarket.com','rec.trustandmarket.com'];
            if (!in_array($tokenProps->getHostname(), $allowedHosts)) {
                return $result;
            }
            // Anti replay (token < 2 min)
            $createTime = $tokenProps->getCreateTime()->getSeconds();            
            if (time() - $createTime > 120) {
               return $result;
            }

            //Contrôle du score : il faudra adapter le seuil par action
           $score = $response->getRiskAnalysis()->getScore();
            if ($score < 0.5) {
                return $result;
            }
           
            //Sinon tous les checks sont OK     
            return ['response' => true, 'message' => 'OK', 'code' => 200];
          
        } catch (exception $e) 
        {
            return $result;
        }
    }

}