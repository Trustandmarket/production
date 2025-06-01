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
use App\Entity\ExtTranslationWpPosts;
use App\Entity\ExtTranslationWpTerms;
use App\Entity\WpTermRelationships;
use App\Entity\WpTerms;
use App\Entity\WpTermTaxonomy;
use App\Entity\WpUsermeta;
use App\Entity\User;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use PDO;

/**
 * Class ServiceManager
 * @package App\Service
 */
class Annonces
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

    /**
     * @param $id
     * @param $date_depays_ville_regionbut
     * @return null|array
     */
    public function readAllAnnoncesforAjaxSearch($id, $code_postal_ville)
    {
        $listeAnnonces = '';

        if ($code_postal_ville == '') {
            $conn = $this->entityManager->getConnection();

            $sql = 'SELECT wp.id AS ID, wp.post_title as titre,wp.guid,wp.post_excerpt as devise,wp.post_name,
            wp.post_author as idUser, wp.post_excerpt as calendrier, wp.guid as price, wp.guid as gallery,
            wtt.taxonomy as categorie_parent, wpSousCategorie.name as sousCategorie,
            wptCategorie.name as categorieParent, wptCategorie.name as categorieParent_en,
            wpSousCategorie.name as sousCategorie_en , wp.post_title as titre_en,
            wpSousCategorie.term_id as idSousCat, wtt.parent as idCat, wp.post_content as images_secondaires,
            wptCategorie.slug as categorieParentSlug, wpSousCategorie.slug as sousCategorieSlug
            FROM wp_posts wp
             JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
             JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
             JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
             JOIN wp_term_taxonomy wtt2 ON wtt.parent=wtt2.term_taxonomy_id
             JOIN wp_terms wptCategorie ON wtt2.term_id=wptCategorie.term_id
             WHERE wp.post_type =:categoriePost and wpSousCategorie.slug=:id_categorie
            and wp.post_status=:post_status';

            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'id_categorie' => $id,
                'categoriePost' => 'product',
                'post_status' => 'publish',
            ]);
            $listeAnnonces = $stmt->fetchAllAssociativeAssociative();

            for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
                //price
                $listeAnnonces[$i]['price'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_price'
                );
                //Images
                $listeAnnonces[$i]['gallery'] = $this->serviceManager->getAllImageForAnnouces(
                    $listeAnnonces[$i]["id"]
                );
                //Pour la devise
                $devise = $this->entityManager
                    ->getRepository(WpPostmeta::class)
                    ->findOneBy([
                        'postId' => $listeAnnonces[$i]["id"],
                        'metaKey' => '_product_devise',
                    ]);
                if ($devise) {
                    $listeAnnonces[$i]['devise'] = $devise->getMetaValue();
                } else {
                    $listeAnnonces[$i]['devise'] = '€';
                }

                //Adresse
                $listeAnnonces[$i]['pays'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_country'
                );
                $listeAnnonces[$i]['ville'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_city'
                );
                $listeAnnonces[$i]['etat'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_adress'
                );
                //Images
                $listeAnnonces[$i]['gallerie'] = $this->serviceManager->getAllImageForAnnouces(
                    $listeAnnonces[$i]["id"]
                );
                //Images secondaires
                $listeAnnonces[$i]['images_secondaires'] = $this->serviceManager->getAllSecondImageForAnnouces($listeAnnonces[$i]["id"], 'images_annonces');

                //Pour la disponibilité
                $calendrier = $this->serviceManager->getUserStringDataValue(
                    $listeAnnonces[$i]['idUser'],
                    'disponibilite'
                );

                if ($calendrier != '') {
                    $arrayToTreat = explode(',', $calendrier);
                    usort($arrayToTreat, array($this, 'date_sort'));
                    $listeAnnonces[$i]['calendrier'] = $arrayToTreat;
                } else {
                    $listeAnnonces[$i]['calendrier'] = [];
                }

                $listeAnnonces[$i]['titre_en'] = '';
                $listeAnnonces[$i]['sousCategorie_en'] = '';
                $listeAnnonces[$i]['categorieParent_en'] = '';
                $title_en = $this->entityManager
                    ->getRepository(ExtTranslationWpPosts::class)
                    ->findOneBy(['foreign_key' => $listeAnnonces[$i]["id"], 'field' => 'postExcerpt', 'locale' => 'en']);
                if ($title_en) {
                    $listeAnnonces[$i]['titre_en'] = $title_en->getContent();
                }

                $cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy(['foreign_key' => $listeAnnonces[$i]['idCat'], 'field' => 'name', 'locale' => 'en_US']);
                if ($cat_en) {
                    $listeAnnonces[$i]['categorieParent_en'] = $cat_en->getContent();
                }

                $sous_cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy(['foreign_key' => $listeAnnonces[$i]['idSousCat'], 'field' => 'name', 'locale' => 'en_US']);
                if ($sous_cat_en) {
                    $listeAnnonces[$i]['sousCategorie_en'] = $sous_cat_en->getContent();
                }
            }
        }

        if ($code_postal_ville != '') {
            $conn = $this->entityManager->getConnection();

            $sql = "SELECT wp.id AS ID, wp.post_title as titre,wp.guid,wp.post_excerpt as devise,wp.post_name,
            wp.post_author as idUser, wp.post_excerpt as calendrier, wp.guid as price, wp.guid as gallery,
            wtt.taxonomy as categorie_parent, wpSousCategorie.name as sousCategorie,
            wptCategorie.name as categorieParent, wptCategorie.name as categorieParent_en,
            wpSousCategorie.name as sousCategorie_en , wp.post_title as titre_en,
            wpSousCategorie.term_id as idSousCat, wtt.parent as idCat, wp.post_content as images_secondaires,
            wpmAdresse.meta_value as adressePost, wpmCodePostal.meta_value as codePostalPost,
            wptCategorie.slug as categorieParentSlug, wpSousCategorie.slug as sousCategorieSlug
            FROM wp_posts wp
             JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
             JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
             JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
             JOIN wp_term_taxonomy wtt2 ON wtt.parent=wtt2.term_taxonomy_id
             JOIN wp_terms wptCategorie ON wtt2.term_id=wptCategorie.term_id
             LEFT JOIN wp_postmeta wpmAdresse ON wp.id=wpmAdresse.post_id
             LEFT JOIN wp_postmeta wpmCodePostal ON wp.id=wpmCodePostal.post_id
             WHERE wp.post_type =:categoriePost and wpSousCategorie.slug=:id_categorie
            and wpmAdresse.meta_key=:adresse and wpmCodePostal.meta_key=:code_postale
            and wp.post_status=:post_status and (wpmAdresse.meta_value LIKE :ville OR wpmCodePostal.meta_value like :ville
            OR CONCAT(wpmCodePostal.meta_value,' ',wpmAdresse.meta_value) like :ville OR CONCAT(wpmAdresse.meta_value,' ',wpmCodePostal.meta_value) like :ville)";

            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'adresse' => '_product_city',
                'code_postale' => '_product_code_postal',
                'id_categorie' => $id,
                'categoriePost' => 'product',
                'ville' => '%' . $code_postal_ville . '%',
                'post_status' => 'publish',
            ]);
            $listeAnnonces = $stmt->fetchAllAssociative();

            for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
                //price
                $listeAnnonces[$i]['price'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_price'
                );
                //Pour la devise
                $devise = $this->entityManager
                    ->getRepository(WpPostmeta::class)
                    ->findOneBy([
                        'postId' => $listeAnnonces[$i]["id"],
                        'metaKey' => '_product_devise',
                    ]);
                if ($devise) {
                    $listeAnnonces[$i]['devise'] = $devise->getMetaValue();
                } else {
                    $listeAnnonces[$i]['devise'] = '€';
                }
                //Adresse
                $listeAnnonces[$i]['pays'] = $this->serviceManager->getPostStringDataValue($listeAnnonces[$i]["id"], '_product_country');
                $listeAnnonces[$i]['ville'] = $this->serviceManager->getPostStringDataValue($listeAnnonces[$i]["id"], '_product_city');
                $listeAnnonces[$i]['etat'] = $this->serviceManager->getPostStringDataValue($listeAnnonces[$i]["id"], '_product_adress');
                //Images
                $listeAnnonces[$i]['gallery'] = $this->serviceManager->getAllImageForAnnouces($listeAnnonces[$i]["id"]);
                //Images secondaires
                $listeAnnonces[$i]['images_secondaires'] = $this->serviceManager->getAllSecondImageForAnnouces($listeAnnonces[$i]["id"], 'images_annonces');
                //Pour la disponibilité
                $calendrier = $this->serviceManager->getUserStringDataValue($listeAnnonces[$i]['idUser'], 'disponibilite');
                if ($calendrier != '') {
                    $arrayToTreat = explode(',', $calendrier);
                    usort($arrayToTreat, array($this, 'date_sort'));
                    $listeAnnonces[$i]['calendrier'] = $arrayToTreat;
                } else {
                    $listeAnnonces[$i]['calendrier'] = [];
                }

                $listeAnnonces[$i]['titre_en'] = '';
                $listeAnnonces[$i]['sousCategorie_en'] = '';
                $listeAnnonces[$i]['categorieParent_en'] = '';
                $title_en = $this->entityManager
                    ->getRepository(ExtTranslationWpPosts::class)
                    ->findOneBy(['foreign_key' => $listeAnnonces[$i]["id"], 'field' => 'postExcerpt', 'locale' => 'en']);
                if ($title_en) {
                    $listeAnnonces[$i]['titre_en'] = $title_en->getContent();
                }

                $cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy(['foreign_key' => $listeAnnonces[$i]['idCat'], 'field' => 'name', 'locale' => 'en_US']);
                if ($cat_en) {
                    $listeAnnonces[$i]['categorieParent_en'] = $cat_en->getContent();
                }

                $sous_cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy(['foreign_key' => $listeAnnonces[$i]['idSousCat'], 'field' => 'name', 'locale' => 'en_US']);
                if ($sous_cat_en) {
                    $listeAnnonces[$i]['sousCategorie_en'] = $sous_cat_en->getContent();
                }
            }
        }
        /* var_dump($listeAnnonces);die();*/
        return $listeAnnonces;
    }

    /**
     * @param $id
     * @param $code_postal_ville
     * @param int $page
     * @param $pages
     * @param $nombreDeLignes
     * @param $offset
     * @param $start
     * @param $end
     * @param int $limit
     * @return null|array
     * @throws Exception
     */
    public function readAllAnnoncesforAjaxSearchPaginate(
        $id,
        $code_postal_ville,
        $page = 1,
        $pages,
        $nombreDeLignes,
        $offset,
        $start,
        $end,
        $limit = 9
    )
    {
        $listeAnnonces = '';
        if ($code_postal_ville == '') {
            $conn = $this->entityManager->getConnection();

            $sql = 'SELECT wp.id, wp.id AS id, wp.post_title as titre,wp.guid,wp.post_excerpt as devise,wp.post_name,
            wp.post_author as idUser,wp.post_author, wp.post_excerpt as calendrier, wp.guid as price, wp.guid as gallery,
            wtt.taxonomy as categorie_parent, wpSousCategorie.name as sousCategorie,
            wptCategorie.name as categorieParent, wptCategorie.name as categorieParent_en,
            wpSousCategorie.name as sousCategorie_en , wp.post_title as titre_en,
            wpSousCategorie.term_id as idSousCat, wtt.parent as idCat, wp.post_content as images_secondaires,
            wptCategorie.slug as categorieParentSlug, wpSousCategorie.slug as sousCategorieSlug, null as nom_commercial
            FROM wp_posts wp
            JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
            JOIN wp_term_taxonomy wtt2 ON wtt.parent=wtt2.term_taxonomy_id
            JOIN wp_terms wptCategorie ON wtt2.term_id=wptCategorie.term_id
            WHERE wp.post_type =:categoriePost AND wpSousCategorie.slug=:id_categorie
            AND wp.post_status=:post_status
            ORDER BY wp.id DESC
            LIMIT ' . $limit . ' ' . 'OFFSET' . ' ' . $offset;

            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'id_categorie' => $id,
                'categoriePost' => 'product',
                'post_status' => 'publish',
            ]);
            $listeAnnonces = $stmt->fetchAllAssociative();

            for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
                //price
                $listeAnnonces[$i]['price'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_price'
                );
                //nom_commercial
                $listeAnnonces[$i]['nom_commercial'] = $this->serviceManager->getUserStringDataValue(
                    $listeAnnonces[$i]['idUser'], 'nom_commercial');
                if ($listeAnnonces[$i]['nom_commercial'] == '') {
                    $listeAnnonces[$i]['nom_commercial'] = $this->serviceManager->getUserStringDataValue(
                        $listeAnnonces[$i]['idUser'], 'first_name');
                }
                //Images
                $listeAnnonces[$i]['gallery'] = $this->serviceManager->getAllImageForAnnouces(
                    $listeAnnonces[$i]["id"]
                );
                //Pour la devise
                $devise = $this->entityManager
                    ->getRepository(WpPostmeta::class)
                    ->findOneBy([
                        'postId' => $listeAnnonces[$i]["id"],
                        'metaKey' => '_product_devise',
                    ]);
                if ($devise) {
                    $listeAnnonces[$i]['devise'] = $devise->getMetaValue();
                } else {
                    $listeAnnonces[$i]['devise'] = '€';
                }

                //Adresse
                $listeAnnonces[$i]['pays'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_country'
                );
                $listeAnnonces[$i]['ville'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_city'
                );
                $listeAnnonces[$i]['etat'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_adress'
                );
                //Images
                $listeAnnonces[$i]['gallerie'] = $this->serviceManager->getAllImageForAnnouces(
                    $listeAnnonces[$i]["id"]
                );
                //Images secondaires
                $listeAnnonces[$i]['images_secondaires'] = $this->serviceManager->getAllSecondImageForAnnouces($listeAnnonces[$i]["id"], 'images_annonces');

                //Pour la disponibilité
                $calendrier = $this->serviceManager->getUserStringDataValue(
                    $listeAnnonces[$i]['idUser'],
                    'disponibilite'
                );

                if ($calendrier != '') {
                    $arrayToTreat = explode(',', $calendrier);
                    usort($arrayToTreat, array($this->serviceManager, 'date_sort'));
                    $listeAnnonces[$i]['calendrier'] = $arrayToTreat;
                } else {
                    $listeAnnonces[$i]['calendrier'] = [];
                }

                $listeAnnonces[$i]['titre_en'] = '';
                $listeAnnonces[$i]['sousCategorie_en'] = '';
                $listeAnnonces[$i]['categorieParent_en'] = '';
                $title_en = $this->entityManager
                    ->getRepository(ExtTranslationWpPosts::class)
                    ->findOneBy(['foreign_key' => $listeAnnonces[$i]["id"], 'field' => 'postExcerpt', 'locale' => 'en']);
                if ($title_en) {
                    $listeAnnonces[$i]['titre_en'] = $title_en->getContent();
                }

                $cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy(['foreign_key' => $listeAnnonces[$i]['idCat'], 'field' => 'name', 'locale' => 'en_US']);
                if ($cat_en) {
                    $listeAnnonces[$i]['categorieParent_en'] = $cat_en->getContent();
                }

                $sous_cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy(['foreign_key' => $listeAnnonces[$i]['idSousCat'], 'field' => 'name', 'locale' => 'en_US']);
                if ($sous_cat_en) {
                    $listeAnnonces[$i]['sousCategorie_en'] = $sous_cat_en->getContent();
                }
            }
        }

        if ($code_postal_ville != '') {
            $conn = $this->entityManager->getConnection();

            $sql = "SELECT wp.id,wp.id AS id, wp.post_title as titre,wp.guid,wp.post_excerpt as devise,wp.post_name,
            wp.post_author as idUser,wp.post_author, wp.post_excerpt as calendrier, wp.guid as price, wp.guid as gallery,
            wtt.taxonomy as categorie_parent, wpSousCategorie.name as sousCategorie,
            wptCategorie.name as categorieParent, wptCategorie.name as categorieParent_en,
            wpSousCategorie.name as sousCategorie_en , wp.post_title as titre_en,
            wpSousCategorie.term_id as idSousCat, wtt.parent as idCat, wp.post_content as images_secondaires,
            wpmAdresse.meta_value as adressePost, wpmCodePostal.meta_value as codePostalPost,
            wptCategorie.slug as categorieParentSlug, wpSousCategorie.slug as sousCategorieSlug, null as nom_commercial
            FROM wp_posts wp
            JOIN wp_users wp_users ON wp.post_author=wp_users.id
            JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
            JOIN wp_term_taxonomy wtt2 ON wtt.parent=wtt2.term_taxonomy_id
            JOIN wp_terms wptCategorie ON wtt2.term_id=wptCategorie.term_id
            LEFT JOIN wp_postmeta wpmAdresse ON wp.id=wpmAdresse.post_id
            LEFT JOIN wp_postmeta wpmCodePostal ON wp.id=wpmCodePostal.post_id
            WHERE wp.post_type =:categoriePost and wpSousCategorie.slug=:id_categorie
            and wpmAdresse.meta_key=:adresse and wpmCodePostal.meta_key=:code_postale
            and wp.post_status=:post_status and (wpmAdresse.meta_value LIKE :ville OR wpmCodePostal.meta_value like :ville
            OR CONCAT(wpmCodePostal.meta_value,' ',wpmAdresse.meta_value) like :ville OR CONCAT(wpmAdresse.meta_value,' ',wpmCodePostal.meta_value) like :ville)
            ORDER BY wp.id DESC
            LIMIT " . $limit . ' ' . 'OFFSET' . ' ' . $offset;
            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'adresse' => '_product_city',
                'code_postale' => '_product_code_postal',
                'id_categorie' => $id,
                'categoriePost' => 'product',
                'ville' => '%' . $code_postal_ville . '%',
                'post_status' => 'publish',
                ]);
            $listeAnnonces = $stmt->fetchAllAssociative();

            for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
                //price
                $listeAnnonces[$i]['price'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_price'
                );
                //nom_commercial
                $listeAnnonces[$i]['nom_commercial'] = $this->serviceManager->getUserStringDataValue(
                    $listeAnnonces[$i]['idUser'],
                    'nom_commercial'
                );
                //Pour la devise
                $devise = $this->entityManager
                    ->getRepository(WpPostmeta::class)
                    ->findOneBy([
                        'postId' => $listeAnnonces[$i]["id"],
                        'metaKey' => '_product_devise',
                    ]);
                if ($devise) {
                    $listeAnnonces[$i]['devise'] = $devise->getMetaValue();
                } else {
                    $listeAnnonces[$i]['devise'] = '€';
                }
                //Adresse
                $listeAnnonces[$i]['pays'] = $this->serviceManager->getPostStringDataValue($listeAnnonces[$i]["id"], '_product_country');
                $listeAnnonces[$i]['ville'] = $this->serviceManager->getPostStringDataValue($listeAnnonces[$i]["id"], '_product_city');
                $listeAnnonces[$i]['etat'] = $this->serviceManager->getPostStringDataValue($listeAnnonces[$i]["id"], '_product_adress');
                //Images
                $listeAnnonces[$i]['gallery'] = $this->serviceManager->getAllImageForAnnouces($listeAnnonces[$i]["id"]);
                //Images secondaires
                $listeAnnonces[$i]['images_secondaires'] = $this->serviceManager->getAllSecondImageForAnnouces($listeAnnonces[$i]["id"], 'images_annonces');
                //Pour la disponibilité
                $calendrier = $this->serviceManager->getUserStringDataValue($listeAnnonces[$i]['idUser'], 'disponibilite');
                if ($calendrier != '') {
                    $arrayToTreat = explode(',', $calendrier);
                    usort($arrayToTreat, array($this, 'date_sort'));
                    $listeAnnonces[$i]['calendrier'] = $arrayToTreat;
                } else {
                    $listeAnnonces[$i]['calendrier'] = [];
                }

                $listeAnnonces[$i]['titre_en'] = '';
                $listeAnnonces[$i]['sousCategorie_en'] = '';
                $listeAnnonces[$i]['categorieParent_en'] = '';
                $title_en = $this->entityManager
                    ->getRepository(ExtTranslationWpPosts::class)
                    ->findOneBy(['foreign_key' => $listeAnnonces[$i]["id"], 'field' => 'postExcerpt', 'locale' => 'en']);
                if ($title_en) {
                    $listeAnnonces[$i]['titre_en'] = $title_en->getContent();
                }

                $cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy(['foreign_key' => $listeAnnonces[$i]['idCat'], 'field' => 'name', 'locale' => 'en_US']);
                if ($cat_en) {
                    $listeAnnonces[$i]['categorieParent_en'] = $cat_en->getContent();
                }

                $sous_cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy(['foreign_key' => $listeAnnonces[$i]['idSousCat'], 'field' => 'name', 'locale' => 'en_US']);
                if ($sous_cat_en) {
                    $listeAnnonces[$i]['sousCategorie_en'] = $sous_cat_en->getContent();
                }
            }
        }
        /* var_dump($listeAnnonces);die();*/
        return $listeAnnonces;
    }

    /**
     * @param $id_commercial
     * @param $code_postal_ville
     * @param int $page
     * @param $pages
     * @param $nombreDeLignes
     * @param $offset
     * @param $start
     * @param $end
     * @param int $limit
     * @return null|array
     * @throws Exception
     */
    public function readAllAnnoncesOfUserPaginate(
        $id_commercial,
        $page = 1,
        $pages,
        $nombreDeLignes,
        $offset,
        $start,
        $end,
        $limit = 9
    )
    {
        $sql = 'SELECT wp.id, wp.id AS id, wp.post_title as titre,wp.guid,wp.post_excerpt as devise,wp.post_name,
            wp.post_author as idUser,wp.post_author, wp.post_excerpt as calendrier, wp.guid as price, wp.guid as gallery,
            wtt.taxonomy as categorie_parent, wpSousCategorie.name as sousCategorie,
            wptCategorie.name as categorieParent, wptCategorie.name as categorieParent_en,
            wpSousCategorie.name as sousCategorie_en , wp.post_title as titre_en,
            wpSousCategorie.term_id as idSousCat, wtt.parent as idCat, wp.post_content as images_secondaires,
            wptCategorie.slug as categorieParentSlug, wpSousCategorie.slug as sousCategorieSlug, null as nom_commercial
            FROM wp_posts wp
            JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
            JOIN wp_term_taxonomy wtt2 ON wtt.parent=wtt2.term_taxonomy_id
            JOIN wp_terms wptCategorie ON wtt2.term_id=wptCategorie.term_id
            WHERE wp.post_type =:categoriePost AND wp.post_author=:id_commercial
            AND wp.post_status=:post_status
            ORDER BY wp.id DESC
            LIMIT ' . $limit . ' ' . 'OFFSET' . ' ' . $offset;

        $stmt = $this->entityManager->getConnection()->prepare($sql);
        $stmt = $stmt->executeQuery([
            'id_commercial' => $id_commercial,
            'categoriePost' => 'product',
            'post_status' => 'publish',
        ]);
        $liseAnnounces = $stmt->fetchAllAssociative();

        for ($i = 0; $i < sizeof($liseAnnounces); $i++) {
            //price
            $liseAnnounces[$i]['price'] = $this->serviceManager->getPostStringDataValue(
                $liseAnnounces[$i]["id"],
                '_price'
            );
            //nom_commercial
            $liseAnnounces[$i]['nom_commercial'] = $this->serviceManager->getUserStringDataValue(
                $liseAnnounces[$i]['idUser'], 'nom_commercial');
            if ($liseAnnounces[$i]['nom_commercial'] == '') {
                $liseAnnounces[$i]['nom_commercial'] = $this->serviceManager->getUserStringDataValue(
                    $liseAnnounces[$i]['idUser'], 'first_name');
            }
            //Images
            $liseAnnounces[$i]['gallery'] = $this->serviceManager->getAllImageForAnnouces(
                $liseAnnounces[$i]["id"]
            );
            //Pour la devise
            $devise = $this->entityManager
                ->getRepository(WpPostmeta::class)
                ->findOneBy([
                    'postId' => $liseAnnounces[$i]["id"],
                    'metaKey' => '_product_devise',
                ]);
            if ($devise) {
                $liseAnnounces[$i]['devise'] = $devise->getMetaValue();
            } else {
                $liseAnnounces[$i]['devise'] = '€';
            }

            //Adresse
            $liseAnnounces[$i]['pays'] = $this->serviceManager->getPostStringDataValue(
                $liseAnnounces[$i]["id"],
                '_product_country'
            );
            $liseAnnounces[$i]['ville'] = $this->serviceManager->getPostStringDataValue(
                $liseAnnounces[$i]["id"],
                '_product_city'
            );
            $liseAnnounces[$i]['etat'] = $this->serviceManager->getPostStringDataValue(
                $liseAnnounces[$i]["id"],
                '_product_adress'
            );
            //Images
            $liseAnnounces[$i]['gallerie'] = $this->serviceManager->getAllImageForAnnouces(
                $liseAnnounces[$i]["id"]
            );
            //Images secondaires
            $liseAnnounces[$i]['images_secondaires'] = $this->serviceManager->getAllSecondImageForAnnouces($liseAnnounces[$i]["id"], 'images_annonces');

            //Pour la disponibilité
            $calendrier = $this->serviceManager->getUserStringDataValue(
                $liseAnnounces[$i]['idUser'],
                'disponibilite'
            );

            if ($calendrier != '') {
                $arrayToTreat = explode(',', $calendrier);
                usort($arrayToTreat, array($this->serviceManager, 'date_sort'));
                $liseAnnounces[$i]['calendrier'] = $arrayToTreat;
            } else {
                $liseAnnounces[$i]['calendrier'] = [];
            }

            $liseAnnounces[$i]['titre_en'] = '';
            $liseAnnounces[$i]['sousCategorie_en'] = '';
            $liseAnnounces[$i]['categorieParent_en'] = '';
            $title_en = $this->entityManager
                ->getRepository(ExtTranslationWpPosts::class)
                ->findOneBy(['foreign_key' => $liseAnnounces[$i]["id"], 'field' => 'postExcerpt', 'locale' => 'en']);
            if ($title_en) {
                $liseAnnounces[$i]['titre_en'] = $title_en->getContent();
            }

            $cat_en = $this->entityManager
                ->getRepository(ExtTranslationWpTerms::class)
                ->findOneBy(['foreign_key' => $liseAnnounces[$i]['idCat'], 'field' => 'name', 'locale' => 'en_US']);
            if ($cat_en) {
                $liseAnnounces[$i]['categorieParent_en'] = $cat_en->getContent();
            }

            $sous_cat_en = $this->entityManager
                ->getRepository(ExtTranslationWpTerms::class)
                ->findOneBy(['foreign_key' => $liseAnnounces[$i]['idSousCat'], 'field' => 'name', 'locale' => 'en_US']);
            if ($sous_cat_en) {
                $liseAnnounces[$i]['sousCategorie_en'] = $sous_cat_en->getContent();
            }
        }
        //dd($liseAnnounces);
        return $liseAnnounces;
    }

    /**
     * @param $id
     * @param $code_postal_ville
     * @return null|array
     * @throws Exception
     */
    public function readAllAnnoncesforAjaxSearchCount($id, $code_postal_ville)
    {
        $listeAnnonces = '';

        if ($code_postal_ville == '') {
            $conn = $this->entityManager->getConnection();

            $sql = 'SELECT COUNT(wp.id) as nombreAnnonces
            FROM wp_posts wp
            JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
            JOIN wp_term_taxonomy wtt2 ON wtt.parent=wtt2.term_taxonomy_id
            JOIN wp_terms wptCategorie ON wtt2.term_id=wptCategorie.term_id
            WHERE wp.post_type =:categoriePost AND wpSousCategorie.slug=:id_categorie
            AND wp.post_status=:post_status';

            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'id_categorie' => $id,
                'categoriePost' => 'product',
                'post_status' => 'publish',
            ]);
            $listeAnnonces = $stmt->fetchAllAssociative();
        }

        if ($code_postal_ville != '') {
            $conn = $this->entityManager->getConnection();

            $sql = "SELECT COUNT(wp.id) AS nombreAnnonces
            FROM wp_posts wp
             JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
             JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
             JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
             JOIN wp_term_taxonomy wtt2 ON wtt.parent=wtt2.term_taxonomy_id
             JOIN wp_terms wptCategorie ON wtt2.term_id=wptCategorie.term_id
             LEFT JOIN wp_postmeta wpmAdresse ON wp.id=wpmAdresse.post_id
             LEFT JOIN wp_postmeta wpmCodePostal ON wp.id=wpmCodePostal.post_id
             WHERE wp.post_type =:categoriePost and wpSousCategorie.slug=:id_categorie
            and wpmAdresse.meta_key=:adresse and wpmCodePostal.meta_key=:code_postale
            and wp.post_status=:post_status and (wpmAdresse.meta_value LIKE :ville OR wpmCodePostal.meta_value like :ville
            OR CONCAT(wpmCodePostal.meta_value,' ',wpmAdresse.meta_value) like :ville OR CONCAT(wpmAdresse.meta_value,' ',wpmCodePostal.meta_value) like :ville)";

            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'adresse' => '_product_city',
                'code_postale' => '_product_code_postal',
                'id_categorie' => $id,
                'categoriePost' => 'product',
                'ville' => '%' . $code_postal_ville . '%',
                'post_status' => 'publish',
            ]);
            $listeAnnonces = $stmt->fetchAllAssociative();
        }
        return $listeAnnonces[0]['nombreAnnonces'];
    }

    /**
     * @param $idAuthor
     * @return null|array
     * @throws Exception
     */
    public function readAllAnnoncesOfUserCount($idAuthor)
    {

        $conn = $this->entityManager->getConnection();

        $sql = 'SELECT COUNT(wp.id) as nombreAnnonces
            FROM wp_posts wp
            JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
            JOIN wp_term_taxonomy wtt2 ON wtt.parent=wtt2.term_taxonomy_id
            JOIN wp_terms wptCategorie ON wtt2.term_id=wptCategorie.term_id
            WHERE wp.post_type =:categoriePost AND wp.post_author=:idAuthor
            AND wp.post_status=:post_status';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'idAuthor' => $idAuthor,
            'categoriePost' => 'product',
            'post_status' => 'publish',
        ]);
        $listeAnnonces = $stmt->fetchAllAssociative();
        return $listeAnnonces[0]['nombreAnnonces'];
    }


    /**
     * @param $id
     * @param $code_postal_ville
     * @return null|array
     * @throws Exception
     */
    public function readAllAnnoncesParentforAjaxSearch($id, $code_postal_ville)
    {
        $listeAnnonces = '';

        if ($code_postal_ville == '') {
            $conn = $this->entityManager->getConnection();

            $sql = 'SELECT wp.post_title as titre,wp.post_author,wp.id,wp.id as id,wp.post_date, wp.post_modified, wp.post_content,wp.post_name,
            wtt.taxonomy as categorie_parent , wpSousCategorie.name as sousCategorie, wp.post_content as avatar,
            wptCategorie.name as categorieParent,wp.guid as price, wp.guid as devise, wp.guid
            ,wp.guid as calendrier, wp.guid as first_name, wp.guid as last_name, wp.guid as pays,
            wp.guid as videos, wp.guid as commentaires, wp.guid as ville,wp.guid as etat
            ,wp.guid as gallery, wp.guid as adressePost, wptCategorie.name as categorieParent_en,
            wpSousCategorie.name as sousCategorie_en , wp.post_title as titre_en,
            wpSousCategorie.term_id as idSousCat, wtt.parent as idCat, wp.post_content as images_secondaires,
            wptCategorie.slug as categorieParentSlug, wpSousCategorie.slug as sousCategorieSlug, null as nom_commercial
            FROM wp_posts wp
             LEFT JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
             LEFT JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
             LEFT JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
             LEFT JOIN wp_terms wptCategorie ON wtt.parent=wptCategorie.term_id
             WHERE wp.post_type =:post_type and wptCategorie.slug=:id_categorie
            and wp.post_status=:post_status';

            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'id_categorie' => $id,
                'post_type' => 'product',
                'post_status' => 'publish',
            ]);
            $listeAnnonces = $stmt->fetchAllAssociative();

            for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
                //price
                $listeAnnonces[$i]['price'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_price'
                );
                //Nom Commercial
                $listeAnnonces[$i]['nom_commercial'] = $this->serviceManager->getUserStringDataValue(
                    $listeAnnonces[$i]['idUser'],
                    'nom_commercial'
                );
                if ($listeAnnonces[$i]['nom_commercial'] == '') {
                    $listeAnnonces[$i]['nom_commercial'] = $this->serviceManager->getUserStringDataValue(
                        $listeAnnonces[$i]['idUser'], 'first_name');
                }
                //Pour la devise
                $devise = $this->entityManager
                    ->getRepository(WpPostmeta::class)
                    ->findOneBy([
                        'postId' => $listeAnnonces[$i]["id"],
                        'metaKey' => '_product_devise',
                    ]);
                if ($devise) {
                    $listeAnnonces[$i]['devise'] = $devise->getMetaValue();
                } else {
                    $listeAnnonces[$i]['devise'] = '€';
                }

                //Adresse
                $listeAnnonces[$i]['pays'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_country'
                );
                $listeAnnonces[$i]['ville'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_city'
                );
                $listeAnnonces[$i]['etat'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_adress'
                );
                //Images
                $listeAnnonces[$i]['gallery'] = $this->serviceManager->getAllImageForAnnouces($listeAnnonces[$i]["id"]);
                //Images secondaires
                $listeAnnonces[$i]['images_secondaires'] = $this->serviceManager->getAllSecondImageForAnnouces($listeAnnonces[$i]["id"], 'images_annonces');
                //Pour la disponibilité
                $calendrier = $this->serviceManager->getUserStringDataValue(
                    $listeAnnonces[$i]['post_author'],
                    'disponibilite'
                );
                if ($calendrier != '') {
                    $arrayToTreat = explode(',', $calendrier);
                    usort($arrayToTreat, array($this->serviceManager, 'date_sort'));
                    $listeAnnonces[$i]['calendrier'] = $arrayToTreat;
                } else {
                    $listeAnnonces[$i]['calendrier'] = [];
                }
                $listeAnnonces[$i]['titre_en'] = '';
                $listeAnnonces[$i]['sousCategorie_en'] = '';
                $listeAnnonces[$i]['categorieParent_en'] = '';
                $title_en = $this->entityManager
                    ->getRepository(ExtTranslationWpPosts::class)
                    ->findOneBy(['foreign_key' => $listeAnnonces[$i]["id"], 'field' => 'postExcerpt', 'locale' => 'en']);
                if ($title_en) {
                    $listeAnnonces[$i]['titre_en'] = $title_en->getContent();
                }

                $cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy(['foreign_key' => $listeAnnonces[$i]['idCat'], 'field' => 'name', 'locale' => 'en_US']);
                if ($cat_en) {
                    $listeAnnonces[$i]['categorieParent_en'] = $cat_en->getContent();
                }

                $sous_cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy(['foreign_key' => $listeAnnonces[$i]['idSousCat'], 'field' => 'name', 'locale' => 'en_US']);
                if ($sous_cat_en) {
                    $listeAnnonces[$i]['sousCategorie_en'] = $sous_cat_en->getContent();
                }
            }
        }

        if ($code_postal_ville != '') {
            $conn = $this->entityManager->getConnection();

            $sql = "SELECT wp.post_title as titre,wp.post_author,wp.id,wp.id as id,wp.post_date, wp.post_modified, wp.post_content,wp.post_name,
            wtt.taxonomy as categorie_parent , wpSousCategorie.name as sousCategorie, wp.post_content as avatar,
            wptCategorie.name as categorieParent,wp.guid as price, wp.guid as devise, wp.guid,
            wp.guid as calendrier, wp.guid as first_name, wp.guid as last_name, wp.guid as pays,
            wp.guid as videos, wp.guid as commentaires, wp.guid as ville,wp.guid as etat,
            wp.guid as gallery, wp.guid as adressePost, wptCategorie.name as categorieParent_en,
            wpSousCategorie.name as sousCategorie_en , wp.post_title as titre_en, wp.post_content as images_secondaires,
            wpSousCategorie.term_id as idSousCat, wtt.parent as idCat,
            wptCategorie.slug as categorieParentSlug, wpSousCategorie.slug as sousCategorieSlug, null as nom_commercial
            FROM wp_posts wp
            LEFT JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            LEFT JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            LEFT JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
            LEFT JOIN wp_terms wptCategorie ON wtt.parent=wptCategorie.term_id
            LEFT JOIN wp_postmeta wpmAdresse ON wp.id=wpmAdresse.post_id
            LEFT JOIN wp_postmeta wpmCodePostal ON wp.id=wpmCodePostal.post_id
            WHERE wp.post_type =:categoriePost and wptCategorie.slug=:id_categorie
            and wpmAdresse.meta_key=:adresse and wpmCodePostal.meta_key=:code_postale
            and wp.post_status=:post_status and (wpmAdresse.meta_value LIKE :ville OR wpmCodePostal.meta_value like :ville
            OR CONCAT(wpmCodePostal.meta_value,' ',wpmAdresse.meta_value) like :ville OR CONCAT(wpmAdresse.meta_value,' ',wpmCodePostal.meta_value) like :ville)";

            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'adresse' => '_product_city',
                'code_postale' => '_product_code_postal',
                'id_categorie' => $id,
                'categoriePost' => 'product',
                'ville' => '%' . $code_postal_ville . '%',
                'post_status' => 'publish',
            ]);
            $listeAnnonces = $stmt->fetchAllAssociative();

            for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
                //price
                $listeAnnonces[$i]['price'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_price'
                );
                //Nom Commercial
                $listeAnnonces[$i]['nom_commercial'] = $this->serviceManager->getUserStringDataValue(
                    $listeAnnonces[$i]['idUser'],
                    'nom_commercial'
                );
                if ($listeAnnonces[$i]['nom_commercial'] == '') {
                    $listeAnnonces[$i]['nom_commercial'] = $this->serviceManager->getUserStringDataValue(
                        $listeAnnonces[$i]['idUser'], 'first_name');
                }
                //Pour la devise
                $devise = $this->entityManager
                    ->getRepository(WpPostmeta::class)
                    ->findOneBy([
                        'postId' => $listeAnnonces[$i]["id"],
                        'metaKey' => '_product_devise',
                    ]);
                if ($devise) {
                    $listeAnnonces[$i]['devise'] = $devise->getMetaValue();
                } else {
                    $listeAnnonces[$i]['devise'] = '€';
                }

                //Adresse
                $listeAnnonces[$i]['pays'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_country'
                );
                $listeAnnonces[$i]['ville'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_city'
                );
                $listeAnnonces[$i]['etat'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_adress'
                );
                //Images
                $listeAnnonces[$i]['gallery'] = $this->serviceManager->getAllImageForAnnouces($listeAnnonces[$i]["id"]);
                //Images secondaires
                $listeAnnonces[$i]['images_secondaires'] = $this->serviceManager->getAllSecondImageForAnnouces($listeAnnonces[$i]["id"], 'images_annonces');
                //Pour la disponibilité
                $calendrier = $this->serviceManager->getUserStringDataValue($listeAnnonces[$i]['post_author'], 'disponibilite');
                if ($calendrier != '') {
                    $arrayToTreat = explode(',', $calendrier);
                    usort($arrayToTreat, array($this, 'date_sort'));
                    $listeAnnonces[$i]['calendrier'] = $arrayToTreat;
                } else {
                    $listeAnnonces[$i]['calendrier'] = [];
                }


                $listeAnnonces[$i]['titre_en'] = '';
                $listeAnnonces[$i]['sousCategorie_en'] = '';
                $listeAnnonces[$i]['categorieParent_en'] = '';
                $title_en = $this->entityManager
                    ->getRepository(ExtTranslationWpPosts::class)
                    ->findOneBy(['foreign_key' => $listeAnnonces[$i]["id"], 'field' => 'postExcerpt', 'locale' => 'en']);
                if ($title_en) {
                    $listeAnnonces[$i]['titre_en'] = $title_en->getContent();
                }

                $cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy(['foreign_key' => $listeAnnonces[$i]['idCat'], 'field' => 'name', 'locale' => 'en_US']);
                if ($cat_en) {
                    $listeAnnonces[$i]['categorieParent_en'] = $cat_en->getContent();
                }

                $sous_cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy(['foreign_key' => $listeAnnonces[$i]['idSousCat'], 'field' => 'name', 'locale' => 'en_US']);
                if ($sous_cat_en) {
                    $listeAnnonces[$i]['sousCategorie_en'] = $sous_cat_en->getContent();
                }
            }
        }
        /* var_dump($listeAnnonces);die();*/
        return $listeAnnonces;
    }

    /**
     * @param $id
     * @param $code_postal_ville
     * @param int $page
     * @param $pages
     * @param $nombreDeLignes
     * @param $offset
     * @param $start
     * @param $end
     * @param int $limit
     * @return null|array
     * @throws Exception
     */
    public function readAllAnnoncesParentforAjaxSearchPaginate(
        $id,
        $code_postal_ville,
        $page = 1,
        $pages,
        $nombreDeLignes,
        $offset,
        $start,
        $end,
        $limit = 9
    )
    {
        $listeAnnonces = '';
        if ($code_postal_ville == '') {
            $conn = $this->entityManager->getConnection();

            $sql = 'SELECT wp.post_title as titre,wp.post_author,wp.post_author as idUser,wp.id, wp.id as id,wp.post_date, wp.post_modified, wp.post_content,wp.post_name,
            wtt.taxonomy as categorie_parent , wpSousCategorie.name as sousCategorie, wp.post_content as avatar,
            wptCategorie.name as categorieParent,wp.guid as price, wp.guid as devise, wp.guid
            ,wp.guid as calendrier, wp.guid as first_name, wp.guid as last_name, wp.guid as pays,
            wp.guid as videos, wp.guid as commentaires, wp.guid as ville,wp.guid as etat
            ,wp.guid as gallery, wp.guid as adressePost, wptCategorie.name as categorieParent_en,
            wpSousCategorie.name as sousCategorie_en , wp.post_title as titre_en,
            wpSousCategorie.term_id as idSousCat, wtt.parent as idCat, wp.post_content as images_secondaires,
            wptCategorie.slug as categorieParentSlug, wpSousCategorie.slug as sousCategorieSlug, null as nom_commercial
            FROM wp_posts wp
            JOIN wp_users wp_users ON wp.post_author=wp_users.id
            LEFT JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            LEFT JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            LEFT JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
            LEFT JOIN wp_terms wptCategorie ON wtt.parent=wptCategorie.term_id
            WHERE wp.post_type =:post_type AND wptCategorie.slug=:id_categorie AND wp.post_status=:post_status
            ORDER BY wp.id DESC
            LIMIT ' . $limit . ' ' . 'OFFSET' . ' ' . $offset;

            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'id_categorie' => $id,
                'post_type' => 'product',
                'post_status' => 'publish'
            ]);
            $listeAnnonces = $stmt->fetchAllAssociative();

            for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
                //price
                $listeAnnonces[$i]['price'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_price'
                );
                //Nom Commercial
                $listeAnnonces[$i]['nom_commercial'] = $this->serviceManager->getUserStringDataValue(
                    $listeAnnonces[$i]['idUser'],
                    'nom_commercial'
                );
                if ($listeAnnonces[$i]['nom_commercial'] == '') {
                    $listeAnnonces[$i]['nom_commercial'] = $this->serviceManager->getUserStringDataValue(
                        $listeAnnonces[$i]['idUser'], 'first_name');
                }
                //Pour la devise
                $devise = $this->entityManager
                    ->getRepository(WpPostmeta::class)
                    ->findOneBy([
                        'postId' => $listeAnnonces[$i]["id"],
                        'metaKey' => '_product_devise',
                    ]);
                if ($devise) {
                    $listeAnnonces[$i]['devise'] = $devise->getMetaValue();
                } else {
                    $listeAnnonces[$i]['devise'] = '€';
                }

                //Adresse
                $listeAnnonces[$i]['pays'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_country'
                );
                $listeAnnonces[$i]['ville'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_city'
                );
                $listeAnnonces[$i]['etat'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_adress'
                );
                //Images
                $listeAnnonces[$i]['gallery'] = $this->serviceManager->getAllImageForAnnouces($listeAnnonces[$i]["id"]);
                //Images secondaires
                $listeAnnonces[$i]['images_secondaires'] = $this->serviceManager->getAllSecondImageForAnnouces($listeAnnonces[$i]["id"], 'images_annonces');
                //Pour la disponibilité
                $calendrier = $this->serviceManager->getUserStringDataValue(
                    $listeAnnonces[$i]['post_author'],
                    'disponibilite'
                );
                if ($calendrier != '') {
                    $arrayToTreat = explode(',', $calendrier);
                    usort($arrayToTreat, array($this->serviceManager, 'date_sort'));
                    $listeAnnonces[$i]['calendrier'] = $arrayToTreat;
                } else {
                    $listeAnnonces[$i]['calendrier'] = [];
                }
                $listeAnnonces[$i]['titre_en'] = '';
                $listeAnnonces[$i]['sousCategorie_en'] = '';
                $listeAnnonces[$i]['categorieParent_en'] = '';
                $title_en = $this->entityManager
                    ->getRepository(ExtTranslationWpPosts::class)
                    ->findOneBy(['foreign_key' => $listeAnnonces[$i]["id"], 'field' => 'postExcerpt', 'locale' => 'en']);
                if ($title_en) {
                    $listeAnnonces[$i]['titre_en'] = $title_en->getContent();
                }

                $cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy(['foreign_key' => $listeAnnonces[$i]['idCat'], 'field' => 'name', 'locale' => 'en_US']);
                if ($cat_en) {
                    $listeAnnonces[$i]['categorieParent_en'] = $cat_en->getContent();
                }

                $sous_cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy(['foreign_key' => $listeAnnonces[$i]['idSousCat'], 'field' => 'name', 'locale' => 'en_US']);
                if ($sous_cat_en) {
                    $listeAnnonces[$i]['sousCategorie_en'] = $sous_cat_en->getContent();
                }
            }
        }

        if ($code_postal_ville != '') {
            $conn = $this->entityManager->getConnection();
            $sql = "SELECT wp.post_title as titre,wp.post_author,wp.post_author as idUser,wp.id,wp.id as id,wp.post_date, wp.post_modified, wp.post_content,wp.post_name,
            wtt.taxonomy as categorie_parent , wpSousCategorie.name as sousCategorie, wp.post_content as avatar,
            wptCategorie.name as categorieParent,wp.guid as price, wp.guid as devise, wp.guid,
            wp.guid as calendrier, wp.guid as first_name, wp.guid as last_name, wp.guid as pays,
            wp.guid as videos, wp.guid as commentaires, wp.guid as ville,wp.guid as etat,
            wp.guid as gallery, wp.guid as adressePost, wptCategorie.name as categorieParent_en,
            wpSousCategorie.name as sousCategorie_en , wp.post_title as titre_en, wp.post_content as images_secondaires,
            wpSousCategorie.term_id as idSousCat, wtt.parent as idCat,
            wptCategorie.slug as categorieParentSlug, wpSousCategorie.slug as sousCategorieSlug, null as nom_commercial
            FROM wp_posts wp
            LEFT JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            LEFT JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            LEFT JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
            LEFT JOIN wp_terms wptCategorie ON wtt.parent=wptCategorie.term_id
            LEFT JOIN wp_postmeta wpmAdresse ON wp.id=wpmAdresse.post_id
            LEFT JOIN wp_postmeta wpmCodePostal ON wp.id=wpmCodePostal.post_id
            WHERE wp.post_type =:categoriePost and wptCategorie.slug=:id_categorie
            and wpmAdresse.meta_key=:adresse and wpmCodePostal.meta_key=:code_postale
            and wp.post_status=:post_status and (wpmAdresse.meta_value LIKE :ville OR wpmCodePostal.meta_value like :ville
            OR CONCAT(wpmCodePostal.meta_value,' ',wpmAdresse.meta_value) like :ville OR CONCAT(wpmAdresse.meta_value,' ',wpmCodePostal.meta_value) like :ville)
            ORDER BY wp.id DESC
            LIMIT " . $limit . ' ' . 'OFFSET' . ' ' . $offset;

            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'adresse' => '_product_city',
                'code_postale' => '_product_code_postal',
                'id_categorie' => $id,
                'categoriePost' => 'product',
                'ville' => '%' . $code_postal_ville . '%',
                'post_status' => 'publish',
            ]);
            $listeAnnonces = $stmt->fetchAllAssociative();

            for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
                //price
                $listeAnnonces[$i]['price'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_price'
                );
                //Nom Commercial
                $listeAnnonces[$i]['nom_commercial'] = $this->serviceManager->getUserStringDataValue(
                    $listeAnnonces[$i]['idUser'],
                    'nom_commercial'
                );
                if ($listeAnnonces[$i]['nom_commercial'] == '') {
                    $listeAnnonces[$i]['nom_commercial'] = $this->serviceManager->getUserStringDataValue(
                        $listeAnnonces[$i]['idUser'], 'first_name');
                }
                //Pour la devise
                $devise = $this->entityManager
                    ->getRepository(WpPostmeta::class)
                    ->findOneBy([
                        'postId' => $listeAnnonces[$i]["id"],
                        'metaKey' => '_product_devise',
                    ]);
                if ($devise) {
                    $listeAnnonces[$i]['devise'] = $devise->getMetaValue();
                } else {
                    $listeAnnonces[$i]['devise'] = '€';
                }

                //Adresse
                $listeAnnonces[$i]['pays'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_country'
                );
                $listeAnnonces[$i]['ville'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_city'
                );
                $listeAnnonces[$i]['etat'] = $this->serviceManager->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_adress'
                );
                //Images
                $listeAnnonces[$i]['gallery'] = $this->serviceManager->getAllImageForAnnouces($listeAnnonces[$i]["id"]);
                //Images secondaires
                $listeAnnonces[$i]['images_secondaires'] = $this->serviceManager->getAllSecondImageForAnnouces($listeAnnonces[$i]["id"], 'images_annonces');
                //Pour la disponibilité
                $calendrier = $this->serviceManager->getUserStringDataValue($listeAnnonces[$i]['post_author'], 'disponibilite');
                if ($calendrier != '') {
                    $arrayToTreat = explode(',', $calendrier);
                    usort($arrayToTreat, array($this, 'date_sort'));
                    $listeAnnonces[$i]['calendrier'] = $arrayToTreat;
                } else {
                    $listeAnnonces[$i]['calendrier'] = [];
                }

                $listeAnnonces[$i]['titre_en'] = '';
                $listeAnnonces[$i]['sousCategorie_en'] = '';
                $listeAnnonces[$i]['categorieParent_en'] = '';
                $title_en = $this->entityManager
                    ->getRepository(ExtTranslationWpPosts::class)
                    ->findOneBy(['foreign_key' => $listeAnnonces[$i]["id"], 'field' => 'postExcerpt', 'locale' => 'en']);
                if ($title_en) {
                    $listeAnnonces[$i]['titre_en'] = $title_en->getContent();
                }

                $cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy(['foreign_key' => $listeAnnonces[$i]['idCat'], 'field' => 'name', 'locale' => 'en_US']);
                if ($cat_en) {
                    $listeAnnonces[$i]['categorieParent_en'] = $cat_en->getContent();
                }

                $sous_cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy(['foreign_key' => $listeAnnonces[$i]['idSousCat'], 'field' => 'name', 'locale' => 'en_US']);
                if ($sous_cat_en) {
                    $listeAnnonces[$i]['sousCategorie_en'] = $sous_cat_en->getContent();
                }
            }
        }
        return $listeAnnonces;
    }

    /**
     * @param $id
     * @param $code_postal_ville
     * @return null|array
     * @throws Exception
     */
    public function readAllAnnoncesParentforAjaxSearchCount($id, $code_postal_ville)
    {
        $listeAnnonces = '';

        if ($code_postal_ville == '') {
            $conn = $this->entityManager->getConnection();

            $sql = 'SELECT COUNT(wp.id) as nombreAnnonces
            FROM wp_posts wp
             LEFT JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
             LEFT JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
             LEFT JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
             LEFT JOIN wp_terms wptCategorie ON wtt.parent=wptCategorie.term_id
             WHERE wp.post_type =:post_type and wptCategorie.slug=:id_categorie
            and wp.post_status=:post_status';

            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'id_categorie' => $id,
                'post_type' => 'product',
                'post_status' => 'publish',
            ]);
            $listeAnnonces = $stmt->fetchAllAssociative();
        }

        if ($code_postal_ville != '') {
            $conn = $this->entityManager->getConnection();

            $sql = "SELECT COUNT(wp.id) as nombreAnnonces
            FROM wp_posts wp
            LEFT JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            LEFT JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            LEFT JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
            LEFT JOIN wp_terms wptCategorie ON wtt.parent=wptCategorie.term_id
            LEFT JOIN wp_postmeta wpmAdresse ON wp.id=wpmAdresse.post_id
            LEFT JOIN wp_postmeta wpmCodePostal ON wp.id=wpmCodePostal.post_id
            WHERE wp.post_type =:categoriePost and wptCategorie.slug=:id_categorie
            and wpmAdresse.meta_key=:adresse and wpmCodePostal.meta_key=:code_postale
            and wp.post_status=:post_status and (wpmAdresse.meta_value LIKE :ville OR wpmCodePostal.meta_value like :ville
            OR CONCAT(wpmCodePostal.meta_value,' ',wpmAdresse.meta_value) like :ville OR CONCAT(wpmAdresse.meta_value,' ',wpmCodePostal.meta_value) like :ville)";

            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'adresse' => '_product_city',
                'code_postale' => '_product_code_postal',
                'id_categorie' => $id,
                'categoriePost' => 'product',
                'ville' => '%' . $code_postal_ville . '%',
                'post_status' => 'publish',
            ]);
            $listeAnnonces = $stmt->fetchAllAssociative();
        }
        /* var_dump($listeAnnonces);die();*/
        return $listeAnnonces[0]['nombreAnnonces'];
    }

    // RECUPERATION DE TOUTES LES ANNONCES D'Un USER

    /**
     * @param $userId
     * @param $tag
     * @param $offset
     * @param int $limit
     * @return array|list|null
     * @throws Exception
     */
    public function readListAnnonceDataOfUserPaginate($userId, $tag, $offset, $limit = 6)
    {
        $conn = $this->entityManager->getConnection();
        $sql = "";
        $sql = 'SELECT wp.post_title as titre,wp.post_author,wp.id,wp.post_date, wp.post_modified, wp.post_content,
            wp.post_name as slug, wtt.taxonomy as categorie_parent ,
            wpSousCategorie.name as sousCategorie, wp.post_content as avatar,
            wptCategorie.name as categorieParent,wp.guid as price, wp.guid as devise, wp.guid
            ,wp.guid as calendrier, wp.guid as first_name, wp.guid as last_name, wp.guid as pays
            ,wp.guid as ville,wp.guid as etat, wp.post_title as titre_en, wp.post_title as rupture
            ,wp.guid as gallery, wp.guid as adressePost, wp.post_title as categorieParentType, wp.post_title as images_secondaires
            FROM wp_posts wp
            JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
            JOIN wp_term_taxonomy wtt2 ON wtt.parent=wtt2.term_taxonomy_id
            JOIN wp_terms wptCategorie ON wtt2.term_id=wptCategorie.term_id
            where ( wp.post_author =:post_author and wp.post_status =:tag and wp.post_type =:type1 and wp.post_parent =:parent) 
            or (wp.post_author =:post_author and wp.post_status =:tag and wp.post_type =:type2 and wp.post_parent =:parent)
            ORDER BY wp.id DESC
            LIMIT ' . $limit . ' ' . 'OFFSET' . ' ' . $offset;

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'type1' => 'product',
            'type2' => 'devis',
            'post_author' => $userId,
            'tag' => $tag,
            'parent' => 0,
        ]);

        $data = $stmt->fetchAllAssociative();


        if ($data != null) {
            for ($i = 0; $i < sizeof($data); $i++) {
                //price
                $data[$i]['price'] = $this->serviceManager->getPostStringDataValue(
                    $data[$i]["id"],
                    '_price'
                );
                //Nom
                $data[$i]['first_name'] = $this->serviceManager->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'first_name'
                );
                //Prenom
                $data[$i]['last_name'] = $this->serviceManager->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'last_name'
                );
                //Rupture
                $data[$i]['rupture'] = $this->serviceManager->getPostStringDataValue(
                    $data[$i]["id"],
                    'rupture'
                );
                if ($data[$i]['rupture'] == '') {
                    $data[$i]['rupture'] = 1;
                }
                //Avatar
                $data[$i]['avatar'] = @unserialize(
                    $this->serviceManager->getUserStringDataValue(
                        $data[$i]['post_author'],
                        'basic_user_avatar'
                    )
                );
                $data[$i]['categorieParentType'] = $this->serviceManager->getProductParentCategory($data[$i]["id"]);
                //Adresse
                $data[$i]['pays'] = $this->serviceManager->getPostStringDataValue(
                    $data[$i]["id"],
                    '_product_country'
                );
                $data[$i]['ville'] = $this->serviceManager->getPostStringDataValue(
                    $data[$i]["id"],
                    '_product_city'
                );

                $data[$i]['etat'] = $this->serviceManager->getPostStringDataValue(
                    $data[$i]["id"],
                    '_product_adress'
                );
                //calendrier

                $calendrier = $this->serviceManager->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'disponibilite'
                );
                if ($calendrier) {
                    $data[$i]['calendrier'] = explode(',', $calendrier);
                } else {
                    $data[$i]['calendrier'] = [];
                }

                //Devises
                $data[$i]['devise'] = $this->serviceManager->getPostStringDataValue(
                    $data[$i]["id"],
                    '_product_devise'
                );
                //Images
                $data[$i]['gallery'] = $this->serviceManager->getAllImageForAnnouces(
                    $data[$i]["id"]
                );
                //Images Secondaires
                $data[$i]['images_secondaires'] = $this->serviceManager->getAllSecondImageForAnnouces($data[$i]["id"], 'images_annonces');
                $data[$i]['titre_en'] = "";
                $title_en = $this->entityManager
                    ->getRepository(ExtTranslationWpPosts::class)
                    ->findOneBy(['foreign_key' => $data[$i]["id"], 'field' => 'postExcerpt', 'locale' => 'en']);
                if ($title_en) {
                    $data[$i]['titre_en'] = $title_en->getContent();
                }
            }
        }
        return $data;
    }

    /**
     *
     */
    public function readListAnnonceDataOfUserCount($userId, $tag)
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT count(wp.id) as nombre
            FROM wp_posts wp
            JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
            JOIN wp_term_taxonomy wtt2 ON wtt.parent=wtt2.term_taxonomy_id
            JOIN wp_terms wptCategorie ON wtt2.term_id=wptCategorie.term_id
            where (wp.post_author =:post_author and wp.post_status =:tag and wp.post_type =:type1 and wp.post_parent =:parent) 
            or (wp.post_author =:post_author and wp.post_status =:tag and wp.post_type =:type2 and wp.post_parent =:parent)';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'type1' => 'product',
            'type2' => 'devis',
            'post_author' => $userId,
            'tag' => $tag,
            'parent' => 0,
        ]);

        $data = $stmt->fetchOne();
        return $data;
    }

    /**
     * RECUPERATION DE TOUT LES Devis D'Un USER
     */
    public function readListDevisDataOfUserPaginate($userId, $tag, $offset, $limit = 6)
    {
        $conn = $this->entityManager->getConnection();

        $sql = 'SELECT wp.post_title as titre,wp.post_author,wp.id,wp.post_date, wp.post_modified, wp.post_content,
            wp.post_content as avatar, wp.guid as price, wp.guid as devise, wp.guid
            ,wp.guid as calendrier, wp.guid as first_name, wp.guid as last_name, wp.guid as pays
            ,wp.guid as ville,wp.guid as etat, wp.guid as gallery, wp.guid as adressePost
            , wp.guid as dates_devis, wp.guid as client, wp.guid as images_secondaires
            FROM wp_posts wp
            WHERE wp.post_type =:post_type and wp.post_author =:post_author and wp.post_status =:tag
            ORDER BY wp.id DESC
            LIMIT ' . $limit . ' ' . 'OFFSET' . ' ' . $offset;

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'post_type' => 'devis',
            'post_author' => $userId,
            'tag' => $tag,
        ]);

        $data = $stmt->fetchAllAssociative();
        /*dd($data);die();*/
        //  print_r($data);

        if ($data != null) {
            for ($i = 0; $i < sizeof($data); $i++) {
                //price
                $data[$i]['price'] = $this->serviceManager->getPostStringDataValue(
                    $data[$i]["id"],
                    '_price'
                );
                //Nom
                $data[$i]['first_name'] = $this->serviceManager->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'first_name'
                );
                //Prenom
                $data[$i]['last_name'] = $this->serviceManager->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'last_name'
                );
                //Avatar
                $data[$i]['avatar'] = @unserialize(
                    $this->serviceManager->getUserStringDataValue(
                        $data[$i]['post_author'],
                        'basic_user_avatar'
                    )
                );
                //Adresse
                $data[$i]['pays'] = $this->serviceManager->getPostStringDataValue(
                    $data[$i]["id"],
                    '_product_country'
                );
                $data[$i]['ville'] = $this->serviceManager->getPostStringDataValue(
                    $data[$i]["id"],
                    '_product_city'
                );

                $data[$i]['etat'] = $this->serviceManager->getPostStringDataValue(
                    $data[$i]["id"],
                    '_product_adress'
                );
                //calendrier

                $calendrier = $this->serviceManager->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'disponibilite'
                );
                if ($calendrier) {
                    $data[$i]['calendrier'] = explode(',', $calendrier);
                } else {
                    $data[$i]['calendrier'] = [];
                }

                //Devises
                $data[$i]['devise'] = $this->serviceManager->getPostStringDataValue(
                    $data[$i]["id"],
                    '_product_devise'
                );
                //Images
                $data[$i]['gallery'] = $this->serviceManager->getAllImageForAnnouces(
                    $data[$i]["id"]
                );
                //Images secondaires
                $data[$i]['images_secondaires'] = $this->serviceManager->getAllSecondImageForAnnouces($data[$i]["id"], 'images_annonces');
                //Adresse
                $data[$i]['dates_devis'] = $this->serviceManager->getPostStringDataValue(
                    $data[$i]["id"],
                    'dates_horaires'
                );
                $client = $this->serviceManager->getPostStringDataValue(
                    $data[$i]["id"],
                    'client'
                );
                $data[$i]['client'] = $this->entityManager
                    ->getRepository(User::class)
                    ->find($client);
            }
        }
        return $data;
        // FIN RECUPERATION
    }

    /**
     * RECUPERATION Du nombre TOUT LES Devis D'Un USER
     * @param $userId
     * @param $tag
     * @return false|mixed
     * @throws Exception
     */
    public function readListDevisDataOfUserCount($userId, $tag)
    {
        $conn = $this->entityManager->getConnection();

        $sql = 'SELECT count(wp.id) as nombre
            FROM wp_posts wp
            where wp.post_type =:post_type and wp.post_author =:post_author 
            and wp.post_status =:tag';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'post_type' => 'devis',
            'post_author' => $userId,
            'tag' => $tag,
        ]);

        $data = $stmt->fetchOne();

        return $data;
        // FIN RECUPERATION
    }

    /**
     * @param $userId
     * @param $tag
     * @param $offset
     * @param int $limit
     * @return array|list
     * @throws Exception
     */
    public function readListReservationOfUserPaginate($userId, $tag, $offset, $limit = 6)
    {
        $conn = $this->entityManager->getConnection();

        $sql = 'SELECT wp.*, wp.post_content as annonces , wp.post_content as date_evenement, null as affiche
            FROM wp_posts wp
            where (wp.post_author =:auteur and wp.post_status =:post_status and wp.post_type =:type1 and wp.pinged =:pinged1) or
            (wp.post_author =:auteur and wp.post_status =:post_status and wp.post_type =:type1 and wp.pinged =:pinged2)
            ORDER BY wp.id DESC
            LIMIT ' . $limit . ' ' . 'OFFSET' . ' ' . $offset;

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'auteur' => $userId,
            'post_status' => $tag,
            'type1' => 'shop_order',
            'pinged1' => 'product',
            'pinged2' => 'devis',
        ]);

        $data = $stmt->fetchAllAssociative();
        //dd($data);
        if ($data) {
            for ($i = 0; $i < sizeOf($data); $i++) {
                $reservations = $data[$i]['post_content'];
                $listeReservations = @unserialize($reservations);
                $tabAnnonces = array();
                if ($data[$i]['pinged'] == 'product') {
                    for ($w = 0; $w < sizeof($listeReservations); $w++) {
                        $annoncesReserves = $this->serviceManager->readAllAnnonceData($listeReservations[$w]['id']);
                        array_push($tabAnnonces, $annoncesReserves);
                        $date_evenement = $listeReservations[$w]['dates'];
                        $data[$i]['date_evenement'] = $date_evenement;
                    }
                } elseif ($data[$i]['pinged'] == 'devis') {
                    if(!array_key_exists('id', $listeReservations)){
                        $listeReservations["id"] = $listeReservations["ID"];
                    }
                    $devisReserves = $this->serviceManager->readAllDevisData($listeReservations["id"]);
                    array_push($tabAnnonces, $devisReserves);
                    $data[$i]['date_evenement'] = $listeReservations['dates_horaires'];
                    $data[$i]['prenom_prestataire'] = $this->serviceManager->getUserStringDataValue($data[$i]['post_author'], 'first_name');
                    $data[$i]['nom_prestataire'] = $this->serviceManager->getUserStringDataValue($data[$i]['post_author'], 'last_name');
                    if (sizeOf($devisReserves) > 0 && !$data[$i]['affiche']) {
                        if (sizeOf($devisReserves['gallery']) > 0 && $devisReserves['gallery'][0]) {
                            $data[$i]['affiche'] = $devisReserves['gallery'][0]->getGuid();
                        } elseif (sizeOf($devisReserves['images_secondaires']) > 0 && $devisReserves['images_secondaires'][0]) {
                            $data[$i]['affiche'] = $devisReserves['images_secondaires'][0]->getGuid();
                        }
                    }
                }
                $data[$i]['annonces'] = $tabAnnonces;
            }
        }

        return $data;
    }

    /**
     * @param $userId
     * @param $tag
     * @return false|mixed
     * @throws Exception
     */
    public function readListReservationOfUserCount($userId, $tag)
    {
        $conn = $this->entityManager->getConnection();
        // var_dump($userId);
        $sql = 'SELECT count(wp.id) as nombre
            FROM wp_posts wp
            where (wp.post_author =:auteur and wp.post_status =:post_status and wp.post_type =:type1 and wp.pinged =:pinged1) or
            (wp.post_author =:auteur and wp.post_status =:post_status and wp.post_type =:type1 and wp.pinged =:pinged2)
            ORDER BY wp.id DESC';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'auteur' => $userId,
            'post_status' => $tag,
            'type1' => 'shop_order',
            'pinged1' => 'product',
            'pinged2' => 'devis',
        ]);

        $data = $stmt->fetchOne();

        return $data;
    }

    /**
     * @param int $userId
     * @param $tag
     * @param $offset
     * @param int $limit
     * @return array|list
     * @throws Exception
     */
    public function readListReservationDevisOfUserPaginate(int $userId, $tag, $offset, $limit = 6)
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT wp.* , wp.post_title as titre, wp.post_content as prenom_prestataire, wp.post_content as nom_prestataire , wp.post_content as dates_horaires, wp.guid as price, wp.guid as avatar, wp.guid as pays, wp.guid as ville,
         wp.guid as etat, wp.guid as pays, wp.guid as devise, wp.guid as gallery, wp.guid as images_secondaires
            FROM wp_posts wp
            INNER JOIN wp_postmeta wpmeta ON wp.id = wpmeta.post_id
            WHERE wp.post_status =:post_status and wpmeta.meta_value=:client 
            and wp.post_type =:type_annnounce
            ORDER BY wp.id DESC
            LIMIT ' . $limit . ' ' . 'OFFSET' . ' ' . $offset;

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'client' => $userId,
            'post_status' => $tag,
            'type_annnounce' => 'devis',
        ]);
        $data = $stmt->fetchAllAssociative();

        if ($data) {
            for ($i = 0; $i < sizeOf($data); $i++) {
                $data[$i]['prenom_prestataire'] = $this->serviceManager->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'first_name'
                );
                $data[$i]['nom_prestataire'] = $this->serviceManager->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'last_name'
                );

                //Adresse
                $data[$i]['dates_devis'] = $this->serviceManager->getPostStringDataValue(
                    $data[$i]["id"],
                    'dates_horaires'
                );
                //price
                $data[$i]['price'] = $this->serviceManager->getPostStringDataValue(
                    $data[$i]["id"],
                    '_price'
                );
                //Avatar
                $data[$i]['avatar'] = @unserialize(
                    $this->serviceManager->getUserStringDataValue(
                        $data[$i]['post_author'],
                        'basic_user_avatar'
                    )
                );
                //Adresse
                $data[$i]['pays'] = $this->serviceManager->getPostStringDataValue(
                    $data[$i]["id"],
                    '_product_country'
                );
                $data[$i]['ville'] = $this->serviceManager->getPostStringDataValue(
                    $data[$i]["id"],
                    '_product_city'
                );

                $data[$i]['etat'] = $this->serviceManager->getPostStringDataValue(
                    $data[$i]["id"],
                    '_product_adress'
                );

                //Devises
                $data[$i]['devise'] = $this->serviceManager->getPostStringDataValue(
                    $data[$i]["id"],
                    '_product_devise'
                );
                //Images
                $data[$i]['gallery'] = $this->serviceManager->getAllImageForAnnouces(
                    $data[$i]["id"]
                );
                //Images secondaires
                $data[$i]['images_secondaires'] = $this->serviceManager->getAllSecondImageForAnnouces($data[$i]["id"], 'images_annonces');
            }
        }
        return $data;
    }

    /**
     * @param int $userId
     * @param $tag
     * @return false|mixed
     * @throws Exception
     */
    public function readListReservationDevisOfUserCount(int $userId, $tag)
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT count(wp.id) as nombre
            FROM wp_posts wp
            INNER JOIN wp_postmeta wpmeta ON wp.id = wpmeta.post_id
            WHERE wp.post_status =:post_status and wpmeta.meta_value=:client 
            and wp.post_type =:type_annnounce
            ORDER BY wp.id DESC';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'client' => $userId,
            'post_status' => $tag,
            'type_annnounce' => 'devis',
        ]);
        $data = $stmt->fetchOne();

        return $data;
    }

    /**
     * @param $userId
     * @param int $page
     * @return array()
     * @throws Exception
     */
    public function readAllProData($userId, $page = 1)
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wpUser.*, wpUser.email_canonical as email,  null as first_name, null as last_name, null as pays,
            null as videos, null as commentaires, null as ville, null as etat, null as titre,
            null as competence, wpUser.user_nicename as reference , wpUser.user_nicename as portfolio,
            null as annonces, null as descriptionUser, null as avatar, null as societe, null as nom_commercial
            FROM wp_users wpUser
            WHERE wpUser.id =:id';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery(['id' => $userId]);
        $data = $stmt->fetchAllAssociative()[0];
        //Nom
        $data['first_name'] = $this->serviceManager->getUserStringDataValue($userId, 'first_name');
        //Prenom
        $data['last_name'] = $this->serviceManager->getUserStringDataValue($userId, 'last_name');
        //Nom Commercial
        $data['nom_commercial'] = $this->serviceManager->getUserStringDataValue($userId, 'nom_commercial');
        if($data['nom_commercial'] == '')
            $data['nom_commercial'] = $data['first_name'];
        //Adresse
        $data['pays'] = $this->serviceManager->getUserStringDataValue($userId, 'billing_country');
        $data['ville'] = $this->serviceManager->getUserStringDataValue($userId, 'billing_city');
        $data['etat'] = $this->serviceManager->getUserStringDataValue($userId, 'billing_state');
        $data['societe'] = $this->serviceManager->getUserStringDataValue($userId, 'billing_company');
        $data['region'] = $this->serviceManager->getUserStringDataValue($userId, 'region');
        $data['titre'] = $this->serviceManager->getUserStringDataValue($userId, 'titre');

        $vid = $this->serviceManager->readUserMeta($userId, 'video');
        //Videos

        $video = array();
        $imgid = array();
        if ($vid) {
            $video = @unserialize($vid->getMetaValue());
            for ($i = 0; $i < sizeof($video); $i++) {
                $imgid[$i] = $this->serviceManager->getYouTubeId($video[$i]);
            }
            $data['videos'] = $imgid;
        }
        //Commentaires
        $data['commentaires'] = $this->entityManager
            ->getRepository(wpComments::class)
            ->findBy([
                'commentPostId' => $data["id"],
            ]);
        //Compétence
        $competence = $this->serviceManager->getUserStringDataValue($userId, 'competence');
        if ($competence) {
            $data['competence'] = explode(',', $competence);
        } else {
            $data['competence'] = [];
        }

        //Pour le portfolio
        $portfolioString = $this->serviceManager->getUserStringDataValue($userId, 'portfolio');
        if ($portfolioString) {
            $image = [];
            $idImages = explode(',', $portfolioString);
            for ($i = 0; $i < sizeof($idImages); $i++) {
                $val = $this->entityManager
                    ->getRepository(WpPosts::class)
                    ->find($idImages[$i]);
                if ($val != null) {
                    array_push($image, $val->getGuid());
                }
            }
            $data['portfolio'] = $image;
        } else {
            $data['portfolio'] = '';
        }
        //Référence Utilisateur
        $data['reference'] = $this->serviceManager->getUserStringDataValue(
            $userId,
            'reference'
        );
        //Titre
        $data['titre'] = $this->serviceManager->getUserStringDataValue($userId, 'titre');
        // Description
        $data['description'] = $this->serviceManager->getUserStringDataValue(
            $userId,
            'description'
        );
        //Avatar
        $data['avatar'] = @unserialize(
            $this->serviceManager->getUserStringDataValue($userId, 'basic_user_avatar')
        );
        //Annonces
        $pages = 0;
        $offset = 0;
        $start = 0;
        $end = 0;
        $nombreDeLignes = 0;
        $limit = 6;
        $nombreDeLignes = $this->readListAnnonceDataOfUserCount($userId, 'publish');
        if ($nombreDeLignes > 0) {
            $pages = ceil($nombreDeLignes / $limit);
        }
        $offset = ($page - 1) * $limit;
        $start = $offset + 1;
        $end = min(($offset + $limit), $nombreDeLignes);
        $data['annonces'] = $this->readListAnnonceDataOfUserWithPagination(
            $userId,
            'publish',
            $page,
            $pages,
            $nombreDeLignes,
            $offset,
            $start,
            $end,
            $limit
        );
        for ($i = 0; $i < sizeof($data['annonces']); $i++) {
            $data['annonces'][$i]['price'] = $this->serviceManager->getPostStringDataValue(
                $data['annonces'][$i]["id"],
                '_price'
            );
            $data['annonces'][$i]['devise'] = $this->serviceManager->getPostStringDataValue(
                $data['annonces'][$i]["id"],
                '_product_devise'
            );
            $data['annonces'][$i]['gallerie'] = $this->serviceManager->getAllImageForAnnouces(
                $data['annonces'][$i]["id"]
            );
        }

        return ['data' => $data, 'pages' => $pages, 'nombreDeLignes' => $nombreDeLignes];
    }

    /**
     * @param $userId
     * @param $tag
     * @param int $page
     * @param $pages
     * @param $nombreDeLignes
     * @param $offset
     * @param $start
     * @param $end
     * @param int $limit
     * @return array|list|null
     * @throws Exception
     */
    public function readListAnnonceDataOfUserWithPagination(
        $userId,
        $tag,
        $page = 1,
        $pages,
        $nombreDeLignes,
        $offset,
        $start,
        $end,
        $limit = 6
    )
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT wp.*, wp.post_title as titre,wp.post_author,wp.id as id, wp.post_name as slug, wtt.taxonomy as categorie_parent,
            wpSousCategorie.name as sousCategorie, wp.post_content as avatar,
            wptCategorie.name as categorieParent,wp.guid as price, wp.guid as devise
            ,null as calendrier, wp.guid as first_name, wp.guid as last_name, wp.guid as pays
            ,null as ville,null as etat, null as titre_en, null as rupture
            ,null as gallery, null as adressePost, null as categorieParentType
            ,null as images_secondaires, wtt.taxonomy as categorie_parent, wpSousCategorie.name as sousCategorie,
            wptCategorie.name as categorieParent, wptCategorie.name as categorieParent_en,
            wpSousCategorie.name as sousCategorie_en ,wpSousCategorie.term_id as idSousCat, wtt.parent as idCat,
            wptCategorie.slug as categorieParentSlug, wpSousCategorie.slug as sousCategorieSlug,null as nom_commercial
            FROM wp_posts wp
            JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
            JOIN wp_term_taxonomy wtt2 ON wtt.parent=wtt2.term_taxonomy_id
            JOIN wp_terms wptCategorie ON wtt2.term_id=wptCategorie.term_id
            where ( wp.post_author =:post_author and wp.post_status =:tag and wp.post_type =:type1 and wp.post_parent =:parent) 
            or (wp.post_author =:post_author and wp.post_status =:tag and wp.post_type =:type2 and wp.post_parent =:parent)
            ORDER BY wp.id DESC
            LIMIT ' . $limit . ' ' . 'OFFSET' . ' ' . $offset;

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'type1' => 'product',
            'type2' => 'devis',
            'post_author' => $userId,
            'tag' => $tag,
            'parent' => 0,
        ]);

        $data = $stmt->fetchAllAssociative();

        if ($data != null) {
            for ($i = 0; $i < sizeof($data); $i++) {
                //price
                $data[$i]['price'] = $this->serviceManager->getPostStringDataValue(
                    $data[$i]["id"],
                    '_price'
                );
                //Nom
                $data[$i]['first_name'] = $this->serviceManager->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'first_name'
                );
                //nom_commercial
                $data[$i]['nom_commercial'] = $this->serviceManager->getUserStringDataValue(
                    $data[$i]['post_author'], 'nom_commercial');
                if ($data[$i]['nom_commercial'] == '') {
                    $data[$i]['nom_commercial'] = $this->serviceManager->getUserStringDataValue(
                        $data[$i]['post_author'], 'first_name');
                }
                //Prenom
                $data[$i]['last_name'] = $this->serviceManager->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'last_name'
                );
                //Rupture
                $data[$i]['rupture'] = $this->serviceManager->getPostStringDataValue(
                    $data[$i]["id"],
                    'rupture'
                );
                if ($data[$i]['rupture'] == '') {
                    $data[$i]['rupture'] = 1;
                }
                //Avatar
                $data[$i]['avatar'] = @unserialize(
                    $this->serviceManager->getUserStringDataValue(
                        $data[$i]['post_author'],
                        'basic_user_avatar'
                    )
                );
                $data[$i]['categorieParentType'] = $this->serviceManager->getProductParentCategory($data[$i]["id"]);
                //Adresse
                $data[$i]['pays'] = $this->serviceManager->getPostStringDataValue(
                    $data[$i]["id"],
                    '_product_country'
                );
                $data[$i]['ville'] = $this->serviceManager->getPostStringDataValue(
                    $data[$i]["id"],
                    '_product_city'
                );

                $data[$i]['etat'] = $this->serviceManager->getPostStringDataValue(
                    $data[$i]["id"],
                    '_product_adress'
                );
                //calendrier

                $calendrier = $this->serviceManager->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'disponibilite'
                );
                if ($calendrier) {
                    $data[$i]['calendrier'] = explode(',', $calendrier);
                } else {
                    $data[$i]['calendrier'] = [];
                }

                //Devises
                $data[$i]['devise'] = $this->serviceManager->getPostStringDataValue(
                    $data[$i]["id"],
                    '_product_devise'
                );
                //Images
                $data[$i]['gallery'] = $this->serviceManager->getAllImageForAnnouces(
                    $data[$i]["id"]
                );
                //Images Secondaires
                $data[$i]['images_secondaires'] = $this->serviceManager->getAllSecondImageForAnnouces($data[$i]["id"], 'images_annonces');
                $title_en = $this->entityManager
                    ->getRepository(ExtTranslationWpPosts::class)
                    ->findOneBy(['foreign_key' => $data[$i]["id"], 'field' => 'postExcerpt', 'locale' => 'en']);
                if ($title_en) {
                    $data[$i]['titre_en'] = $title_en->getContent();
                }
            }
        }
        return $data;
        // FIN RECUPERATION
    }

    /**
     * Annonces de la wishlist d'un utilisateur
     * @param $text_wishlist
     * @return null|array
     * @throws Exception
     */
    public function readFavorisAnnonces($text_wishlist)
    {
        $conn = $this->entityManager->getConnection();
        $sql = "SELECT wp.*,wp.id AS id, wp.post_title as titre,wp.post_excerpt as devise,
            wp.post_author as idUser, wp.post_excerpt as calendrier, wp.guid as price, wp.guid as gallery,
            wtt.taxonomy as categorie_parent, wpSousCategorie.name as sousCategorie,
            wptCategorie.name as categorieParent, wptCategorie.name as categorieParent_en,
            wpSousCategorie.name as sousCategorie_en , wp.post_title as titre_en,
            wpSousCategorie.term_id as idSousCat, wtt.parent as idCat, null as images_secondaires,
            wpmAdresse.meta_value as adressePost, wpmCodePostal.meta_value as codePostalPost,
            wptCategorie.slug as categorieParentSlug, wpSousCategorie.slug as sousCategorieSlug, null as nom_commercial
            FROM wp_posts wp
             JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
             JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
             JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
             JOIN wp_term_taxonomy wtt2 ON wtt.parent=wtt2.term_taxonomy_id
             JOIN wp_terms wptCategorie ON wtt2.term_id=wptCategorie.term_id
             LEFT JOIN wp_postmeta wpmAdresse ON wp.id=wpmAdresse.post_id
             LEFT JOIN wp_postmeta wpmCodePostal ON wp.id=wpmCodePostal.post_id
             WHERE wp.id IN (" . $text_wishlist . ") 
            and wpmAdresse.meta_key=:adresse and wpmCodePostal.meta_key=:code_postale
            ORDER BY wp.id DESC";

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'adresse' => '_product_city',
            'code_postale' => '_product_code_postal',
        ]);
        $listeAnnonces = $stmt->fetchAllAssociative();

        for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
            //price
            $listeAnnonces[$i]['price'] = $this->serviceManager->getPostStringDataValue(
                $listeAnnonces[$i]["id"],
                '_price'
            );
            //Nom commercial
            $listeAnnonces[$i]['nom_commercial'] = $this->serviceManager->getUserStringDataValue(
                $listeAnnonces[$i]['post_author'],
                'nom_commercial'
            );
            if($listeAnnonces[$i]['nom_commercial'] == '')
                $listeAnnonces[$i]['nom_commercial'] = $this->serviceManager->getUserStringDataValue(
                    $listeAnnonces[$i]['post_author'],
                    'first_name'
                );
            //Pour la devise
            $devise = $this->entityManager
                ->getRepository(WpPostmeta::class)
                ->findOneBy([
                    'postId' => $listeAnnonces[$i]["id"],
                    'metaKey' => '_product_devise',
                ]);
            if ($devise) {
                $listeAnnonces[$i]['devise'] = $devise->getMetaValue();
            } else {
                $listeAnnonces[$i]['devise'] = '€';
            }
            //Adresse
            $listeAnnonces[$i]['pays'] = $this->serviceManager->getPostStringDataValue($listeAnnonces[$i]["id"], '_product_country');
            $listeAnnonces[$i]['ville'] = $this->serviceManager->getPostStringDataValue($listeAnnonces[$i]["id"], '_product_city');
            $listeAnnonces[$i]['etat'] = $this->serviceManager->getPostStringDataValue($listeAnnonces[$i]["id"], '_product_adress');
            //Images
            $listeAnnonces[$i]['gallery'] = $this->serviceManager->getAllImageForAnnouces($listeAnnonces[$i]["id"]);
            //Images secondaires
            $listeAnnonces[$i]['images_secondaires'] = $this->serviceManager->getAllSecondImageForAnnouces($listeAnnonces[$i]["id"], 'images_annonces');
            //Pour la disponibilité
            $calendrier = $this->serviceManager->getUserStringDataValue($listeAnnonces[$i]['idUser'], 'disponibilite');
            if ($calendrier != '') {
                $arrayToTreat = explode(',', $calendrier);
                usort($arrayToTreat, array($this, 'date_sort'));
                $listeAnnonces[$i]['calendrier'] = $arrayToTreat;
            } else {
                $listeAnnonces[$i]['calendrier'] = [];
            }

            $listeAnnonces[$i]['titre_en'] = '';
            $listeAnnonces[$i]['sousCategorie_en'] = '';
            $listeAnnonces[$i]['categorieParent_en'] = '';
            $title_en = $this->entityManager
                ->getRepository(ExtTranslationWpPosts::class)
                ->findOneBy(['foreign_key' => $listeAnnonces[$i]["id"], 'field' => 'postExcerpt', 'locale' => 'en']);
            if ($title_en) {
                $listeAnnonces[$i]['titre_en'] = $title_en->getContent();
            }

            $cat_en = $this->entityManager
                ->getRepository(ExtTranslationWpTerms::class)
                ->findOneBy(['foreign_key' => $listeAnnonces[$i]['idCat'], 'field' => 'name', 'locale' => 'en_US']);
            if ($cat_en) {
                $listeAnnonces[$i]['categorieParent_en'] = $cat_en->getContent();
            }

            $sous_cat_en = $this->entityManager
                ->getRepository(ExtTranslationWpTerms::class)
                ->findOneBy(['foreign_key' => $listeAnnonces[$i]['idSousCat'], 'field' => 'name', 'locale' => 'en_US']);
            if ($sous_cat_en) {
                $listeAnnonces[$i]['sousCategorie_en'] = $sous_cat_en->getContent();
            }
        }

        return $listeAnnonces;
    }


    public function date_sort($a, $b)
    {
        return strtotime($a) - strtotime($b);
    }
}
