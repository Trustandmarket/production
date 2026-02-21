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
    /**function getRealIP() 
    {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
        return $_SERVER['REMOTE_ADDR'];
    }*/
    // Fonction pour l'évaluation
    function create_assessment(
        string $recaptchaKey,
        string $token,
        string $project,
        string $action
    ) {
        // Créez le client reCAPTCHA.
        // À FAIRE : mettre en cache le code de génération du client (recommandé) ou appeler client.close() avant de quitter la méthode.
        putenv("GOOGLE_APPLICATION_CREDENTIALS=" . __DIR__ . '/../../../trust-market/security_form.json');
        $client = new RecaptchaEnterpriseServiceClient();
        $projectName = $client->projectName($project);
        $result = ['response' => false, 'message' => 'Captcha invalide', 'code' => 403];
        // Définissez les propriétés de l'événement à suivre.
        $event = (new Event())
            ->setSiteKey($recaptchaKey)
            ->setToken($token);
            //->setUserIpAddress(getRealIP()); // LIAISON IP
        
        // Créez la demande d'évaluation.
        $assessment = (new Assessment())
            ->setEvent($event);
        
        try 
        {
            $response = $client->createAssessment($projectName,$assessment);
            //Recupération des propriétés du token
            $tokenProps = $response->getTokenProperties();
                             
            // Vérifier la validité du token
            if ($response->getTokenProperties()->getValid() == false) {
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