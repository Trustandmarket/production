<?php

namespace App\Service;

use App\Entity\User;

class ProfileCompletionCalculator
{
    public function __construct(private readonly ServiceManager $serviceManager)
    {
    }

    public function calculateForUser(User $user): int
    {
        $userId = (int) $user->getId();
        $score = 0;

        // Identite (25)
        $avatarMeta = $this->serviceManager->readUserMeta($userId, 'basic_user_avatar');
        if ($avatarMeta && $avatarMeta->getMetaValue()) {
            $img = @unserialize($avatarMeta->getMetaValue());
            if (is_array($img) && !empty($img)) {
                $score += 5;
            }
        }
        if ($this->hasValue($this->serviceManager->getUserStringDataValue($userId, 'last_name'))) {
            $score += 4;
        }
        if ($this->hasValue($this->serviceManager->getUserStringDataValue($userId, 'first_name'))) {
            $score += 4;
        }
        if ($this->hasValue($this->serviceManager->getUserStringDataValue($userId, 'sexe'))) {
            $score += 3;
        }
        if ($this->hasValue($this->serviceManager->getUserStringDataValue($userId, 'bdaytime'))) {
            $score += 5;
        }
        if ($this->hasValue($this->serviceManager->getUserStringDataValue($userId, 'nationalityCountry'))) {
            $score += 4;
        }

        // Contact et activite (20)
        if ($this->hasValue($user->getEmailCanonical())) {
            $score += 6;
        }
        if ($this->hasValue($this->serviceManager->getUserStringDataValue($userId, 'telephone'))) {
            $score += 4;
        }

        $principalActivity = $this->serviceManager->readUserMeta($userId, 'activite_principale');
        if ($principalActivity && $this->hasValue($principalActivity->getMetaValue())) {
            $score += 3;
        }

        $competence = $this->serviceManager->getUserStringDataValue($userId, 'competence');
        if ($this->hasValue($competence)) {
            $score += 10;
        }

        if ($this->hasValue($this->serviceManager->getUserStringDataValue($userId, 'nom_commercial'))) {
            $score += 1;
        }

        if ($user->getUserUniqueData() && $user->getUserUniqueData()->getDepartement()) {
            $score += 1;
        }

        // Adresse domicile (15)
        if ($this->hasValue($this->serviceManager->getUserStringDataValue($userId, 'numeroNomRue_domicile'))) {
            $score += 5;
        }
        if ($this->hasValue($this->serviceManager->getUserStringDataValue($userId, 'codePostal_domicile'))) {
            $score += 3;
        }
        if ($this->hasValue($this->serviceManager->getUserStringDataValue($userId, 'ville_domicile'))) {
            $score += 3;
        }
        if ($this->hasValue($this->serviceManager->getUserStringDataValue($userId, 'region_domicile'))) {
            $score += 2;
        }
        if ($this->hasValue($this->serviceManager->getUserStringDataValue($userId, 'pays_domicile'))) {
            $score += 2;
        }

        // Contenu de profil (25)
        if ($this->hasValue($this->serviceManager->getUserStringDataValue($userId, 'description'))) {
            $score += 20;
        }
        if ($this->hasValue($this->serviceManager->getUserStringDataValue($userId, 'reference'))) {
            $score += 5;
        }

        // Portfolio (15)
        $portfolio = $this->serviceManager->getUserStringDataValue($userId, 'portfolio');
        if ($this->hasValue($portfolio)) {
            $score += 15;
        }

        if ($score < 0) {
            return 0;
        }

        if ($score > 100) {
            return 100;
        }

        return $score;
    }

    private function hasValue(mixed $value): bool
    {
        return trim((string) $value) !== '';
    }
}
