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
        $result = ['response' => true, 'message' => '', 'code' => 200];
        // Définissez les propriétés de l'événement à suivre.
        $event = (new Event())
            ->setSiteKey($recaptchaKey)
            ->setToken($token);

        // Créez la demande d'évaluation.
        $assessment = (new Assessment())
            ->setEvent($event);

        try {
            $response = $client->createAssessment($projectName, $assessment);
            // Vérifiez si le jeton est valide.
            if ($response->getTokenProperties()->getValid() == false) {
/*                throw new \Exception('Un probleme est survenu: ' .
                    InvalidReason::name($response->getTokenProperties()->getInvalidReason()), 400);*/
                $result = ['response' => false, 'message' => 'Un probleme est survenu: Etes vous un humain?'
                    /*InvalidReason::name($response->getTokenProperties()->getInvalidReason())*/, 'code' => 400];
                return $result;
            }

            // Vérifiez si l'action attendue a été exécutée.
            if ($response->getTokenProperties()->getAction() == $action) {
                // Obtenez le score de risques et le ou les motifs.
                // Pour savoir comment interpréter l'évaluation, consultez les pages suivantes :
                // https://cloud.google.com/recaptcha-enterprise/docs/interpret-assessment
                /*printf('The score for the protection action is:');
                printf($response->getRiskAnalysis()->getScore());*/
            } else {
                //throw new \Exception('Le format de cette requette est invalide', 400);
                $result = ['response' => false, 'message' => 'Le format de cette requette est invalide', 'code' => 400];
                return $result;
            }
            return $result;
        } catch (exception $e) {
            return $result;
        }
    }

}
