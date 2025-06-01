<?php

// src/Services/ServiceManager.php

//Couche d'acces au données

namespace App\Service\DataAccessLayer;

use App\Service\ServiceManager;
use App\Entity\WpCommentmeta;
use App\Entity\WpComments;
use App\Entity\WpOptions;
use App\Entity\WpPostmeta;
use App\Entity\WpPosts;
use App\Entity\Translation\WpPostsTranslation;
use App\Entity\Translation\WpTermsTranslation;
use App\Entity\WpTermRelationships;
use App\Entity\WpTerms;
use App\Entity\WpTermTaxonomy;
use App\Entity\WpUsermeta;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use PDO;

/**
 * Class ServiceManager
 * @package App\Service
 */
class DevisCommandes
{
    private $router;
    private $entityManager;
    private $serviceManager;
    /**
     * ServiceManager constructor.
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager, ServiceManager $serviceManager)
    {
        $this->entityManager = $entityManager;
        $this->serviceManager = $serviceManager;
        /* $url_local = 'http://localhost:8000/wp-content';  $url_dev = 'http://dev.trustandmarket.com/wp-content'; $url_prod = 'http://trustandmarket.com/wp-content'; */
        $this->local = $_SERVER['APP_FILES_LOCAL_URL'];
    }
}
