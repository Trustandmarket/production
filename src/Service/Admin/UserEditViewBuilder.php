<?php

namespace App\Service\Admin;

use App\Entity\Departement;
use App\Entity\WpPosts;
use App\Entity\WpTermTaxonomy;
use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;

class UserEditViewBuilder
{
    private ServiceManager $serviceManager;
    private EntityManagerInterface $em;

    public function __construct(ServiceManager $serviceManager, EntityManagerInterface $em)
    {
        $this->serviceManager = $serviceManager;
        $this->em = $em;
    }

    public function build(int $userId): array
    {
        $principalActivity = $this->serviceManager->readUserMeta($userId, 'activite_principale');

        if ($principalActivity) {
            $principalActivity = $this->em->getRepository(WpTermTaxonomy::class)->findOneBy([
                'termTaxonomyId' => $principalActivity->getMetaValue(),
            ]);
        }

        return array_merge(
            $this->getIdentityData($userId),
            $this->getProfileData($userId),
            $this->getBankAccountData($userId),
            $this->getBillingData($userId),
            [
                'id' => $userId,
                'activities' => $this->serviceManager->postCategorie1('product_activity'),
                'principal_activity' => $principalActivity,
                'user' => $this->serviceManager->userById($userId),
                'identite' => $this->serviceManager->readIdentityFiles($userId, 'kyc_document'),
                'enregistrement' => $this->serviceManager->readSubscribeFiles($userId, 'kyc_document'),
                'departements' => $this->em->getRepository(Departement::class)->findAll(),
                'user_departement' => $this->serviceManager->getUserStringDataValue($userId, 'departement'),
            ]
        );
    }

    private function getIdentityData(int $userId): array
    {
        return [
            'first_name' => $this->serviceManager->readUserMeta($userId, 'first_name'),
            'last_name' => $this->serviceManager->readUserMeta($userId, 'last_name'),
            'nom_commercial' => $this->getCommercialName($userId),
            'bdaytime' => $this->serviceManager->readUserMeta($userId, 'bdaytime'),
            'sexe' => $this->serviceManager->readUserMeta($userId, 'sexe'),
            'birth_place' => $this->serviceManager->readUserMeta($userId, 'nationalityCountry'),
            'telephone' => $this->serviceManager->readUserMeta($userId, 'telephone'),
            'raison_sociale' => $this->serviceManager->readUserMeta($userId, 'raison_sociale'),
            'siret' => $this->serviceManager->readUserMeta($userId, 'siret'),
            'tva' => $this->serviceManager->readUserMeta($userId, 'tva'),
        ];
    }

    private function getProfileData(int $userId): array
    {
        $competences = $this->serviceManager->readUserMeta($userId, 'competence');
        $portfolioMeta = $this->serviceManager->readUserMeta($userId, 'portfolio');
        $videoMeta = $this->serviceManager->readUserMeta($userId, 'video');

        return [
            'avatar' => $this->getAvatar($userId),
            'titre' => $this->serviceManager->readUserMeta($userId, 'titre'),
            'description' => $this->serviceManager->readUserMeta($userId, 'description'),
            'competence' => $competences ? explode(',', $competences->getMetaValue()) : [],
            'competences' => $competences,
            'region' => $this->serviceManager->readUserMeta($userId, 'region'),
            'reference' => $this->serviceManager->readUserMeta($userId, 'reference'),
            'portfolio' => $this->getPortfolio($portfolioMeta?->getMetaValue()),
            'video' => $this->getVideos($videoMeta?->getMetaValue()),
            'imgid' => $this->getVideoImageIds($videoMeta?->getMetaValue()),
        ];
    }

    private function getBankAccountData(int $userId): array
    {
        return [
            'typeCompte' => $this->serviceManager->readUserMeta($userId, 'vendor_account_type'),
            'nomCompte' => $this->serviceManager->readUserMeta($userId, 'vendor_account_name'),
            'adresseDetenteur' => $this->serviceManager->readUserMeta($userId, 'vendor_account_address1'),
            'villeCompte' => $this->serviceManager->readUserMeta($userId, 'vendor_account_city'),
            'codePostaleCompte' => $this->serviceManager->readUserMeta($userId, 'vendor_account_postcode'),
            'paysCompte' => $this->serviceManager->readUserMeta($userId, 'vendor_account_country'),
            'regionCompte' => $this->serviceManager->readUserMeta($userId, 'vendor_account_region'),
        ];
    }

    private function getBillingData(int $userId): array
    {
        return [
            'prenom' => $this->serviceManager->readUserMeta($userId, 'billing_first_name'),
            'nom' => $this->serviceManager->readUserMeta($userId, 'billing_last_name'),
            'nomEntreprise' => $this->serviceManager->readUserMeta($userId, 'billing_company'),
            'pays' => $this->serviceManager->readUserMeta($userId, 'billing_country'),
            'numeroNomRue' => $this->serviceManager->readUserMeta($userId, 'billing_address_1'),
            'codePostal' => $this->serviceManager->readUserMeta($userId, 'billing_postcode'),
            'ville' => $this->serviceManager->readUserMeta($userId, 'billing_city'),
            'etatComte' => $this->serviceManager->readUserMeta($userId, 'billing_state'),
            'telephone1' => $this->serviceManager->readUserMeta($userId, 'billing_phone'),
            'email' => $this->serviceManager->readUserMeta($userId, 'billing_email'),
        ];
    }

    private function getCommercialName(int $userId): string
    {
        $commercialName = $this->serviceManager->getUserStringDataValue($userId, 'nom_commercial');

        if ($commercialName !== '') {
            return $commercialName;
        }

        return $this->serviceManager->getUserStringDataValue($userId, 'first_name');
    }

    private function getAvatar(int $userId): string
    {
        $avatarMeta = $this->serviceManager->readUserMeta($userId, 'basic_user_avatar');

        if (!$avatarMeta || !$avatarMeta->getMetaValue()) {
            return '';
        }

        $image = @unserialize($avatarMeta->getMetaValue());

        if (!is_array($image) || $image === []) {
            return '';
        }

        return (string) array_values($image)[0];
    }

    private function getPortfolio(?string $portfolioIds): array
    {
        if (!$portfolioIds) {
            return [];
        }

        return $this->em->getRepository(WpPosts::class)->findById(explode(',', $portfolioIds));
    }

    private function getVideos(?string $serializedVideos): array
    {
        if (!$serializedVideos) {
            return [];
        }

        $videos = @unserialize($serializedVideos);

        return is_array($videos) ? $videos : [];
    }

    private function getVideoImageIds(?string $serializedVideos): array
    {
        $videos = $this->getVideos($serializedVideos);
        $imageIds = [];

        foreach ($videos as $index => $video) {
            $imageIds[$index] = $this->serviceManager->getYouTubeId($video);
        }

        return $imageIds;
    }
}
