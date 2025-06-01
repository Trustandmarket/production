<?php

// src/Services/ServiceManager.php

//Couche d'acces au données

namespace App\Service;

use App\Entity\WpCommentmeta;
use App\Entity\WpComments;
use App\Entity\WpOptions;
use App\Entity\WpPostmeta;
use App\Entity\WpPosts;
use App\Entity\ExtTranslationWpPosts;
use App\Entity\ExtTranslationWpPostmeta;
use App\Entity\ExtTranslationWpTerms;
use App\Entity\ExtTranslationWpTermTaxonomy;
use App\Entity\WpTermRelationships;
use App\Entity\WpTerms;
use App\Entity\WpTermTaxonomy;
use App\Entity\WpUsermeta;
use App\Entity\User;
use Cocur\Slugify\Slugify;
use DateTime;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use function sizeof;

/**
 * Class ServiceManager
 * @package App\Service
 */
class ServiceManager
{
    private $router;
    private $entityManager;

    /**
     * ServiceManager constructor.
     * @param EntityManagerInterface $entityManager
     * @param UrlGeneratorInterface $router
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $router
    ) {
        $this->entityManager = $entityManager;
        $this->router = $router;
        /* $url_local = 'http://localhost:8000/wp-content';  $url_dev = 'https://dev.trustandmarket.com/wp-content'; $url_prod = 'https://trustandmarket.com/wp-content'; */
        $this->local = $_SERVER['APP_FILES_LOCAL_URL'];
    }
    // LES CATEGORIES
    /**
     * @param $name
     * @param $parent
     * @param $type
     * @return int
     */
    public function createCategorie($name, $parent, $type, $termGroup = 0, $termPrice = 0, $locale = 'fr')
    {
        $wp_terms = new WpTerms();
        $wp_terms_taxo = new WpTermTaxonomy();
        $wp_terms->setName($name);
        $wp_terms->setSlug($this->slugify($name));
        $wp_terms->setTermGroup($termGroup);
        $wp_terms->setTermPrice($termPrice);
        $wp_terms->setTermIcon($name);

        $this->entityManager->persist($wp_terms);
        $this->entityManager->flush();
        if ($wp_terms->getTermId() > 0) {
            $wp_terms_taxo->setTermId($wp_terms->getTermId());
            $wp_terms_taxo->setTaxonomy($type);
            $wp_terms_taxo->setDescription($name);
            $wp_terms_taxo->setParent($parent);
            $wp_terms_taxo->setCount(0);

            $this->entityManager->persist($wp_terms_taxo);
            $this->entityManager->flush();

            //Save lang data
            $lang_data = new ExtTranslationWpTerms();
            $lang_data->setLocale($locale);
            $lang_data->setObjectClass("App\Entity\WpTerms");
            $lang_data->setField("name");
            $lang_data->setContent($name);
            $lang_data->setForeignKey($wp_terms);

            $this->entityManager->persist($lang_data);
            $this->entityManager->flush();
        }
        return $wp_terms_taxo->getTermTaxonomyId();
    }

    /**
     * @param $id
     * @return mixed
     */
    public function userById($id)
    {
        return $this->entityManager
            ->getRepository(User::class)
            ->findOneById($id);
    }
    //function that will check if email is already use

    /**
     * @param $email
     * @return int
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function findEmail($email)
    {
        $query = $this->entityManager
            ->createQuery(
                'SELECT count(u.id) as nb FROM App\Entity\User u WHERE u.email=:key'
            )
            ->setParameter('key', $email);
        $users = $query->getSingleResult();
        if ($users['nb'] == '0') {
            return 0;
        } else {
            return 1;
        }
    }

    /**
     * @param $id
     * @return array|null|object
     */
    public function readCategorie($id)
    {
        $Categorie = $this->entityManager
            ->getRepository(WpTerms::class)
            ->find($id);
        if ($Categorie) {
            return $Categorie;
        } else {
            return ['response' => false, 'data' => $Categorie];
        }
    }

    /**
     * @param $id
     * @param $name
     * @param $slug
     * @param $term_group
     * @param $term_price
     * @param $term_icon
     * @return array|Response
     */
    public function updateCategorie(
        $id,
        $name,
        $slug,
        $term_group,
        $term_price,
        $term_icon
    ) {
        $Wp_terms = $this->entityManager
            ->getRepository(WpTerms::class)
            ->find($id);
        if (!$Wp_terms) {
            return ['result' => false, 'value' => $id];
        }
        $Wp_terms = new WpTerms();
        $Wp_terms->setName($name);
        $Wp_terms->setSlug($slug);
        $Wp_terms->setTermGroup($term_group);
        $Wp_terms->setTermPrice($term_price);
        $Wp_terms->setTermIcon($term_icon);

        $this->entityManager->flush();

        return new Response([
            'response' => 'OK',
            'data' => $Wp_terms->getTermId(),
        ]);
    }

    /**
     * @param $id
     * @return int
     */
    public function deleteCategorie($id)
    {
        $categorie = $this->entityManager
            ->getRepository(WpTermTaxonomy::class)
            ->findOneByTermTaxonomyId($id);
        $child = count(
            $this->entityManager
                ->getRepository(WpTermTaxonomy::class)
                ->findByParent($categorie->getTermTaxonomyId())
        );
        if ($child > 0) {
            return 2;
        } else {
            $catTerm = $this->entityManager
                ->getRepository(WpTerms::class)
                ->findOneByTermId($categorie->getTermId());
            if ($catTerm) {
                $this->entityManager->remove($catTerm);
                $this->entityManager->flush();
            }

            if ($categorie->getParent() != 0) {
                $parent = $this->entityManager
                    ->getRepository(wpTermTaxonomy::class)
                    ->findOneByTermTaxonomyId($categorie->getParent());
                if ($parent) {
                    $parent->setCount($parent->getCount() - 1);
                    $this->entityManager->flush();
                }
            }
            $this->entityManager->remove($categorie);
            $this->entityManager->flush();
            return 1;
        }
    }

    // Les Sous Catégories

    /**
     * @param $taxonomy
     * @param $description
     * @param $parent
     * @param $count
     * @param $termId
     * @return null|string
     */
    public function createSousCategorie(
        $taxonomy,
        $description,
        $parent,
        $count,
        $termId
    ) {
        $sous_categories = new WpTermTaxonomy();

        $sous_categories->setTaxonomy($taxonomy);
        $sous_categories->setDescription($description);
        $sous_categories->setParent($parent);
        $sous_categories->setCount($count);
        $sous_categories->setTerms($termId);

        $this->entityManager->persist($sous_categories);
        $this->entityManager->flush();

        return $sous_categories->getTermTaxonomyId();
    }

    //recuperation des categorie ou des sous categories des post
    /**
     * @param $key
     * @param $parent
     * @return array|null|object
     */
    public function postCategorie($key, $parent)
    {
        $query = $this->entityManager
            ->createQuery(
                'SELECT tt.termTaxonomyId, tt.taxonomy, tt.description, tt.parent, tt.count,tt.termId, t.name, t.slug
            FROM App\Entity\WpTermTaxonomy tt
            JOIN App\Entity\WpTerms t
            WHERE tt.termId=t.termId and tt.taxonomy=:key and tt.parent=:p
            ORDER BY t.name ASC'
            )
            ->setParameter('key', $key)
            ->setParameter('p', $parent);
        $Categories = $query->getResult();
        return $Categories;
    }

    //recuperation des categorie ou des sous categories des post
    /**
     * @param $key
     * @param $parent
     * @return array|null|object
     */
    public function postCategorieWithMultilang(
        $key,
        $parent,
        $key2 = 'prestation_type'
    ) {
        $query = $this->entityManager
            ->createQuery('SELECT tt.termTaxonomyId, tt.taxonomy, tt.description, tt.parent, tt.count, t.name, t.slug, t.name as content, t.termId
            FROM App\Entity\WpTermTaxonomy tt
            JOIN App\Entity\WpTerms t
            WHERE tt.termId=t.termId and tt.parent=:p and (tt.taxonomy=:key or tt.taxonomy=:key2)
            ORDER BY t.termId ASC')
            ->setParameter('key', $key)
            ->setParameter('key2', $key2)
            ->setParameter('p', $parent);
        $categories = $query->getResult();

        for ($i=0; $i < sizeof($categories); $i++) {
            $cat = $this->entityManager->getRepository(ExtTranslationWpTerms::class)->findOneBy(['field' => 'App\Entity\WpTerms','locale' => 'en_US', 'foreign_key' => $categories[$i]['termId']]);
            if ($cat) {
                $categories[$i]['content'] = $cat->getContent();
            }
        }
        return $categories;
    }

    /**
     * @param $key
     * @param $parent
     * @param $key2 = 'taxonomie'
     * @return array|null|object
     */
    public function postCategorieWithMultilangByTermId($key, $parent, $key2, $termTaxonomyId) {
        $query = $this->entityManager
            ->createQuery('SELECT tt.termTaxonomyId, tt.taxonomy, tt.description, tt.parent, tt.count, t.name, t.slug, t.name as content, t.termId
            FROM App\Entity\WpTermTaxonomy tt
            JOIN App\Entity\WpTerms t
            WHERE tt.termId=t.termId and tt.parent=:p and tt.termId=:id and (tt.taxonomy=:key or tt.taxonomy=:key2)
            ORDER BY t.termId ASC')
            ->setParameter('key', $key)
            ->setParameter('key2', $key2)
            ->setParameter('p', $parent)
            ->setParameter('id', $termTaxonomyId);
        $categories = $query->getResult();

        for ($i=0; $i < sizeof($categories); $i++) {
            $cat = $this->entityManager->getRepository(ExtTranslationWpTerms::class)->findOneBy(['field' => 'App\Entity\WpTerms','locale' => 'en_US', 'foreign_key' => $categories[$i]['termId']]);
            if ($cat) {
                $categories[$i]['content'] = $cat->getContent();
            }
        }
        return $categories;
    }

    /**
     * @param $key
     * @param $parent
     * @return array|null|object
     */
    public function postCategorieWithMultilangByName(
        $key,
        $parent,
        $key2 = 'prestation_type'
    ) {
        $query = $this->entityManager
            ->createQuery(
                'SELECT tt.termTaxonomyId, tt.taxonomy, tt.description, tt.parent, tt.count, t.name, t.slug, t.name as content, t.termId
            FROM App\Entity\WpTermTaxonomy tt
            JOIN App\Entity\WpTerms t
            JOIN App\Entity\WpTerms tp
            WHERE tt.termId=t.termId and tt.parent=tp.termId and tp.slug=:p and (tt.taxonomy=:key or tt.taxonomy=:key2)
            ORDER BY t.termId ASC'
            )
            ->setParameter('key', $key)
            ->setParameter('key2', $key2)
            ->setParameter('p', $parent);
        $categories = $query->getResult();
        for ($i=0; $i < sizeof($categories); $i++) {
            $cat = $this->entityManager->getRepository(ExtTranslationWpTerms::class)->findOneBy(['field' => 'App\Entity\WpTerms','locale' => 'en_US', 'foreign_key' => $categories[$i]['termId']]);
            if ($cat) {
                $categories[$i]['content'] = $cat->getContent();
            }
        }
        return $categories;
    }

    //recuperation des categorie ou des sous categories des post
    /**
     * @param $key
     * @param $parent
     * @return array|null|object
     */
    public function postCategorieProductWithMultilang($keyParent, $key, $parent)
    {
        $query = $this->entityManager
            ->createQuery(
                'SELECT tt.termTaxonomyId, tt.taxonomy, tt.description, tt.parent, ttp.description as nom_parent, t.name, t.slug,t.termPrice as frais_livraison, t.name as content, t.termId
            FROM App\Entity\WpTermTaxonomy tt
            JOIN App\Entity\WpTermTaxonomy ttp
            JOIN App\Entity\WpTerms t
            WHERE tt.termId=t.termId and ttp.taxonomy=:keyParent and tt.taxonomy=:key and tt.parent=ttp.termTaxonomyId
            ORDER BY t.name ASC'
            )
            ->setParameter('key', $key)
            ->setParameter('keyParent', $keyParent);
        $categories = $query->getResult();

        for ($i=0; $i < sizeof($categories); $i++) {
            $cat = $this->entityManager->getRepository(ExtTranslationWpTerms::class)->findOneBy(['field' => 'App\Entity\WpTerms','locale' => 'en_US', 'foreign_key' => $categories[$i]['termId']]);
            if ($cat) {
                $categories[$i]['content'] = $cat->getContent();
            }
        }

        return $categories;
    }

    // récuperation de la sous categorie
    /**
     * @param $key
     * @param $parent
     * @param $id
     * @return array|null|object
     */
    public function sousCategorie($key, $parent, $id)
    {
        $query = $this->entityManager
            ->createQuery('SELECT tt.termTaxonomyId, tt.taxonomy, tt.description, tt.parent, tt.count, t.name
      FROM App\Entity\WpTermTaxonomy tt
      JOIN App\Entity\WpTerms t
      JOIN App\Entity\ExtTranslationWpTerms tl
      WHERE tt.termId=t.termId and tt.taxonomy=:key and tt.parent!=:p AND tt.termTaxonomyId=:id
      and tl.foreign_key=t.termId and tl.locale=:loc and tl.field=:field')
            ->setParameter('key', $key)
            ->setParameter('p', $parent)
            ->setParameter('id', $id)
            ->setParameter('loc', 'en_US')
            ->setParameter('field', 'name');
        $Categorie = $query->getOneOrNullResult();
        return $Categorie;
    }

    /**
     * @param $key
     * @param $parent
     * @param $id
     * @return array|null|object
     */
    public function sousCategorieByName($key, $parent, $id)
    {
        $query = $this->entityManager
            ->createQuery(
                'SELECT tt.termTaxonomyId, tt.taxonomy, tt.description, tt.parent, tt.count, t.name
        FROM App\Entity\WpTermTaxonomy tt
        JOIN App\Entity\WpTerms t
        JOIN App\Entity\ExtTranslationWpTerms tl
        WHERE tt.termId=t.termId and tt.taxonomy=:key and tt.parent!=:p AND t.slug=:id
        and tl.foreign_key=t.termId and tl.locale=:loc and tl.field=:field'
            )
            ->setParameter('key', $key)
            ->setParameter('p', $parent)
            ->setParameter('id', $id)
            ->setParameter('loc', 'en_US')
            ->setParameter('field', 'name');
        $Categorie = $query->getOneOrNullResult();
        return $Categorie;
    }

    /**
     * @param $key
     * @return array|null|object
     */
    public function postCategorie1($key)
    {
        $query = $this->entityManager
            ->createQuery(
                'SELECT tt.termTaxonomyId, tt.termId, tt.taxonomy, tt.description, tt.parent, tt.count, t.name
        FROM App\Entity\WpTermTaxonomy tt
        JOIN App\Entity\WpTerms t
        WHERE tt.termId=t.termId and tt.taxonomy=:key
        ORDER BY t.name ASC'
            )
            ->setParameter('key', $key);
        $Categories = $query->getResult();
        return $Categories;
    }

    /**
     * @param $key
     * @return array|null|object
     */
    public function postCategorieWithLocaleContent($key, $locale = 'en_US')
    {
        $query = $this->entityManager
            ->createQuery(
                'SELECT tt.termTaxonomyId, tt.termId, tt.taxonomy, tt.description, tt.parent, tt.count, t.name, tr.locale, tr.content, tr.field
        FROM App\Entity\WpTermTaxonomy tt
        JOIN App\Entity\WpTerms t
        JOIN App\Entity\ExtTranslationWpTerms tr
        WHERE tt.termId=t.termId and tt.taxonomy=:key and tr.locale=:loc and tr.foreign_key = t.termId and tr.field =:field 
        ORDER BY t.name ASC'
            )
            ->setParameters([
                'key' => $key,
                'loc' => $locale,
                'field' => 'name',
            ]);

        return $query->getResult();
    }

    /**
     * @param $key
     * @param $parent
     * @param $id
     * @return array|null|object
     */
    public function sousCategorieById($id)
    {
        $query = $this->entityManager->createQuery('SELECT tt.termTaxonomyId, tt.taxonomy, tt.description, tt.parent,
        tt.count, t.name, t.name as name_en, t.slug
        FROM App\Entity\WpTermTaxonomy tt
        JOIN App\Entity\WpTerms t
        WHERE tt.termId=t.termId and tt.termTaxonomyId=:id')
            ->setParameter('id', $id);
        $Categorie = $query->getOneOrNullResult();
        return $Categorie;
    }

    /**
     * @param $id
     * @param $taxonomy
     * @param $description
     * @param $parent
     * @param $count
     * @param $termId
     * @return array|Response
     */
    public function updateSousCategories(
        $id,
        $taxonomy,
        $description,
        $parent,
        $count,
        $termId
    ) {
        $sous_categories = $this->entityManager
            ->getRepository(WpTermTaxonomy::class)
            ->find($id);

        if (!$sous_categories) {
            return ['result' => false, 'value' => $id];
        }

        $sous_categories->setTaxonomy($taxonomy);
        $sous_categories->setDescription($description);
        $sous_categories->setParent($parent);
        $sous_categories->setCount($count);
        $sous_categories->setTerms($termId);

        $this->entityManager->flush();

        return new Response([
            'response' => 'OK',
            'data' => $sous_categories->getTermId(),
        ]);
    }

    /**
     * @param $id
     * @return Response
     */
    public function deleteSousCategorie($id)
    {
        $sous_categories = $this->entityManager
            ->getRepository(WpTermTaxonomy::class)
            ->find($id);

        $this->entityManager->remove($sous_categories);

        $this->entityManager->flush();

        return new Response('OK');
    }

    //Les Options

    /**
     * @param $name
     * @param $value
     * @return mixed
     */
    public function createOption($name, $value)
    {
        $WpOptions = new WpOptions();

        $WpOptions->setOptionName($name);
        $WpOptions->setOptionValue($value);

        $this->entityManager->persist($WpOptions);
        $this->entityManager->flush();

        return $WpOptions->getId();
    }

    /**
     * @param $id
     * @return array|null|object
     */
    public function readOption($id)
    {
        $Option = $this->entityManager
            ->getRepository(WpOptions::class)
            ->find($id);
        if ($Option) {
            return $Option;
        } else {
            return ['response' => false, 'data' => $Option];
        }
    }

    /**
     * @param $id
     * @return array|null|object
     */
    public function readOptionByMetaName($name)
    {
        $Option = $this->entityManager
            ->getRepository(WpOptions::class)
            ->findBy(['optionName' => $name]);
        return $Option;
    }

    /**
     * @param $id
     * @param $name
     * @param $value
     * @return array|Response
     */
    public function updateOption($id, $name, $value)
    {
        $WpOptions = $this->entityManager
            ->getRepository(WpOptions::class)
            ->find($id);

        if (!$WpOptions) {
            return ['result' => false, 'value' => $id];
        }

        $WpOptions->setOptionName($name);
        $WpOptions->setOptionValue($value);

        $this->entityManager->flush();

        return new Response([
            'response' => 'OK',
            'data' => $WpOptions->getOptionId(),
        ]);
    }

    /**
     * @param $id
     * @return Response
     */
    public function deleteOption($id)
    {
        $Option = $this->entityManager
            ->getRepository(WpOptions::class)
            ->find($id);

        $this->entityManager->remove($Option);

        $this->entityManager->flush();

        return new Response('OK');
    }

    //CRUD Commentaires

    /**
     * @param $post
     * @param $commentAuthor
     * @param $commentAuthorEmail
     * @param $commentAuthorUrl
     * @param $commentAuthorIp
     * @param $commentDate
     * @param $commentDateGmt
     * @param $commentContent
     * @param $commentKarma
     * @param $commentApproved
     * @param $commentAgent
     * @param $commentType
     * @param $commentParent
     * @param $userId
     * @return null|string
     */
    public function createComments(
        $commentPostId,
        $commentAuthor,
        $commentAuthorEmail,
        $commentAuthorUrl,
        $commentAuthorIp,
        $commentDate,
        $commentDateGmt,
        $commentContent,
        $commentKarma,
        $commentApproved,
        $commentAgent,
        $commentType,
        $commentParent,
        $userId
    ) {
        $commentaries = new WpComments();

        $commentaries->setCommentPostId($commentPostId);
        $commentaries->setCommentAuthor($commentAuthor);
        $commentaries->setCommentAuthorEmail($commentAuthorEmail);
        $commentaries->setCommentAuthorUrl($commentAuthorUrl);
        $commentaries->setCommentAuthorIp($commentAuthorIp);

        $commentaries->setCommentDate($commentDate);
        $commentaries->setCommentDateGmt($commentDateGmt);
        $commentaries->setCommentContent($commentContent);
        $commentaries->setCommentKarma($commentKarma);
        $commentaries->setCommentApproved($commentApproved);
        $commentaries->setCommentAgent($commentAgent);
        $commentaries->setCommentType($commentType);
        $commentaries->setCommentParent($commentParent);
        $commentaries->setUserId($userId);

        $this->entityManager->persist($commentaries);
        $this->entityManager->flush();

        return $commentaries;
    }

    /**
     * @param $id
     * @return array|null|object
     */
    public function readComments($id)
    {
        $commentaries = $this->entityManager
            ->getRepository(WpComments::class)
            ->find($id);
        if ($commentaries) {
            return $commentaries;
        } else {
            return ['response' => false, 'data' => $commentaries];
        }
    }

    /**
     * @param $id
     * @param $post
     * @param $commentAuthor
     * @param $commentAuthorEmail
     * @param $commentAuthorUrl
     * @param $commentAuthorIp
     * @param $commentDate
     * @param $commentDateGmt
     * @param $commentContent
     * @param $commentKarma
     * @param $commentApproved
     * @param $commentAgent
     * @param $commentType
     * @param $commentParent
     * @param $userId
     * @return array|Response
     */
    public function updateComments(
        $id,
        $post,
        $commentAuthor,
        $commentAuthorEmail,
        $commentAuthorUrl,
        $commentAuthorIp,
        $commentDate,
        $commentDateGmt,
        $commentContent,
        $commentKarma,
        $commentApproved,
        $commentAgent,
        $commentType,
        $commentParent,
        $userId
    ) {
        $Commentaires = $this->entityManager
            ->getRepository(WpComments::class)
            ->find($id);

        if (!$Commentaires) {
            return ['response' => false, 'data' => $id];
        }

        $commentaire = new WpComments();

        $commentaire->setPost($post);
        $commentaire->setCommentAuthor($commentAuthor);
        $commentaire->setCommentAuthorEmail($commentAuthorEmail);
        $commentaire->setCommentAuthorUrl($commentAuthorUrl);
        $commentaire->setCommentAuthorIp($commentAuthorIp);

        $commentaire->setCommentDate($commentDate);
        $commentaire->setCommentDateGmt($commentDateGmt);
        $commentaire->setCommentContent($commentContent);
        $commentaire->setCommentKarma($commentKarma);
        $commentaire->setCommentApproved($commentApproved);
        $commentaire->setCommentAgent($commentAgent);
        $commentaire->setCommentType($commentType);
        $commentaire->setCommentParent($commentParent);
        $commentaire->setUserId($userId);

        $this->entityManager->flush();

        return new Response([
            'response' => 'OK',
            'data' => $commentaire->getCommentId(),
        ]);
    }

    /**
     * @param $id
     * @return array|Response
     */
    public function deleteComments($id)
    {
        $Commentaires = $this->entityManager
            ->getRepository(WpComments::class)
            ->find($id);
        if (!$Commentaires) {
            return ['response' => false];
        }

        $this->entityManager->remove($Commentaires);
        $this->entityManager->flush();

        return new Response('OK');
    }

    //End CRUD COMMENTS

    //SHOW COMMENTS DATA

    /**
     * Nombre de commentaires recus par un utilisateur
     * @param $userEmail
     * @return null|array
     */
    public function countUserCommentReceived($userId)
    {
        $query = $this->entityManager
            ->createQuery(
                'SELECT count(wc.commentId) as comm FROM App\Entity\WpComments wc
        JOIN App\Entity\WpPosts wp
        WHERE wc.commentPostId=wp.id and wc.commentApproved=:commentApproved and wp.postAuthor=:postAuthor'
            )
            ->setParameters([
                'commentApproved' => 1,
                'postAuthor' => $userId,
            ]);

        $commentNumber = $query->getSingleResult();

        return $commentNumber['comm'];
    }

    /**
     * Nombre de commentaires postés par un utilisateur
     * @param $userId
     * @return null|array
     */
    public function countUserCommentPosted($userEmail)
    {
        $query = $this->entityManager
            ->createQuery(
                'SELECT count(wc.commentId) as comm FROM App\Entity\WpComments wc
        where wc.commentApproved=:commentApproved and wc.commentAuthorEmail=:commentAuthorEmail'
            )
            ->setParameters([
                'commentApproved' => 1,
                'commentAuthorEmail' => $userEmail,
            ]);
        $commentNumber = $query->getSingleResult();

        return $commentNumber['comm'];
    }

    /**
     * Nombre de commentaires recus par un post
     * @param $userId
     * @return null|array
     */
    public function countPostCommentReceived($postId)
    {
        $query = $this->entityManager
            ->createQuery('SELECT wc FROM App\Entity\WpComments wc where wc.commentApproved=:commentApproved and wc.commentPostId=:commentPostId')
            ->setParameters([
                'commentApproved' => 1,
                'commentPostId' => $postId,
            ]);
        $data = $query->getResult();
        return sizeof($data);
    }

    /**
     * Réponse a un commentaire
     * @param $postId
     * @return null|array
     */
    public function listAndOrderPostComment($postId)
    {
        $conn = $this->entityManager->getConnection();

        $sql = 'SELECT wp.*
             FROM wp_comments wp
             WHERE wp.comment_post_ID =:idPost and wp.comment_approved=:comment_approved
             ORDER BY wp.comment_ID';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery(['idPost' => $postId, 'comment_approved' => 1]);
        $listeCommentaires = $stmt->fetchAllAssociative();

        for ($i = 0; $i < sizeof($listeCommentaires); $i++) {
            $userId = $this->entityManager
                ->getRepository(User::class)
                ->findOneBy(['email_canonical' => $listeCommentaires[$i]['comment_author_email']])
                ->getId();
            $listeCommentaires[$i]['comment_agent'] = @unserialize(
                $this->getUserStringDataValue($userId, 'basic_user_avatar')
            );
            //Get list of responses for
        }

        return $listeCommentaires;
    }

    //END SHOW COMMENTS DATA

    //CRUD Commentmeta ou données supplémentaires

    /**
     * @param $comment
     * @param $metaKey
     * @param $metaValue
     * @return null|string
     */
    public function createCommentMeta($commentId, $metaKey, $metaValue)
    {
        $commentaire = new WpCommentmeta();

        $commentaire->setMetaKey($metaKey);
        $commentaire->setMetaValue($metaValue);
        $commentaire->setCommentId($commentId);

        $this->entityManager->persist($commentaire);
        $this->entityManager->flush();

        return $commentaire->getMetaId();
    }

    /**
     * @param $id
     * @return array|null|object
     */
    public function readCommentMeta($id)
    {
        $Commentaires = $this->entityManager
            ->getRepository(WpCommentmeta::class)
            ->find($id);
        if ($Commentaires) {
            return $Commentaires;
        } else {
            return ['response' => false, 'data' => $Commentaires];
        }
    }

    /**
     * @param $id
     * @param $comment
     * @param $metaKey
     * @param $metaValue
     * @return array|Response
     */
    public function updateCommentMeta($id, $commentId, $metaKey, $metaValue)
    {
        $Commentaires = $this->entityManager
            ->getRepository(WpCommentmeta::class)
            ->find($id);

        if (!$Commentaires) {
            return ['response' => false, 'data' => $id];
        }

        $commentaire = new WpCommentmeta();

        $commentaire->setMetaKey($metaKey);
        $commentaire->setMetaValue($metaValue);
        $commentaire->setCommentId($commentId);

        $this->entityManager->persist($commentaire);
        $this->entityManager->flush();

        return new JsonResponse([
            'response' => 'OK',
            'data' => $commentaire->getMetaId(),
        ]);
    }

    /**
     * @param $id
     * @return array|Response
     */
    public function deleteCommentMeta($id)
    {
        $Commentaires = $this->entityManager
            ->getRepository(WpCommentmeta::class)
            ->find($id);
        if (!$Commentaires) {
            return ['response' => false];
        }

        $this->entityManager->remove($Commentaires);
        $this->entityManager->flush();

        return new Response('OK');
    }

    // CRUD Post ou ensemble des données générales envoyés sur la BD
    //Cette partie sera gérée avec de maniere non conventionnelle car la relation est ManyToMany et la table de liaison existe deja.

    //CRUD Post

    /**
     * @param $postAuthor
     * @param $postDate
     * @param $postDateGmt
     * @param $postContent
     * @param $postTitle
     * @param $postExcerpt
     * @param $postStatus
     * @param $commentStatus
     * @param $pingStatus
     * @param $postPassword
     * @param $postName
     * @param $toPing
     * @param $pinged
     * @param $postModified
     * @param $postModifiedGmt
     * @param $postContentFiltered
     * @param $postParent
     * @param $guid
     * @param $menuOrder
     * @param $postType
     * @param $postMimeType
     * @param $commentCount
     * @param $termTaxonomyId
     * @return array
     */
    public function createPosts(
        $postAuthor,
        $postDate,
        $postDateGmt,
        $postContent,
        $postTitle,
        $postExcerpt,
        $postStatus,
        $commentStatus,
        $pingStatus,
        $postPassword,
        $postName,
        $toPing,
        $pinged,
        $postModified,
        $postModifiedGmt,
        $postContentFiltered,
        $postParent,
        $guid,
        $menuOrder,
        $postType,
        $postMimeType,
        $commentCount,
        $termTaxonomyId,
        $locale = null
    ) {
        $post = new WpPosts();
        $post->setPostAuthor($postAuthor);
        $post->setPostDate($postDate);
        $post->setPostDateGmt($postDateGmt);
        $post->setPostContent($postContent);
        $post->setPostTitle($postTitle);
        $post->setPostStatus($postStatus);
        $post->setCommentStatus($commentStatus);
        $post->setPingStatus($pingStatus);
        $post->setPostPassword($postPassword);
        $post->setPostName($postName);
        $post->setToPing($toPing);
        $post->setPinged($pinged);
        $post->setPostModified($postModified);
        $post->setPostModifiedGmt($postModifiedGmt);

        if ($locale && $locale == 'en') {
            $post->setPostExcerpt($postTitle);
            $post->setPostContentFiltered($postContent);
        } else {
            $post->setPostExcerpt($postExcerpt);
            $post->setPostContentFiltered($postContentFiltered);
        }

        $post->setPostParent($postParent);
        $post->setGuid($guid);
        $post->setMenuOrder($menuOrder);
        $post->setPostType($postType);
        $post->setPostMimeType($postMimeType);
        $post->setCommentCount($commentCount);

        $this->entityManager->persist($post);
        $this->entityManager->flush();
        $postId = $post->getId();
        //Generate unique name
        $size = sizeof($this->readPostsByName($postName));
        if (sizeof($this->readPostsByName($postName)) > 1) {
            $post->setPostName($postName . '-' . $postId);
        }
        $this->entityManager->persist($post);
        $this->entityManager->flush();
        //End unique name
        $relationPost_SousCategorie = new WpTermRelationships();

        $relationPost_SousCategorie->setTermTaxonomyId($termTaxonomyId);
        $relationPost_SousCategorie->setObjectId($postId);

        $this->entityManager->persist($relationPost_SousCategorie);
        $this->entityManager->flush();

        return $postId;
    }

    /**
     * Permet de créer une nouvelle page statique avec les informations comme le titre et la description de la page
     * @param array $data
     * @return array
     */
    public function createPageStatique($data = [])
    {
        $post = new WpPosts();

        $post->setPostAuthor($data['postAuthor']);
        $post->setPostDate($data['postDate']);
        $post->setPostDateGmt($data['postDateGmt']);
        $post->setPostContent($data['postContent']);
        $post->setPostTitle($data['postTitle']);
        $post->setPostExcerpt($data['postExcerpt']);
        $post->setPostStatus($data['postStatus']);
        $post->setPostName($data['postName']);
        $post->setPostModified($data['postModified']);
        $post->setPostModifiedGmt($data['postModifiedGmt']);
        $post->setPostContentFiltered($data['postContentFiltered']);
        $post->setToPing($data['toPing']);
        $post->setPinged($data['pinged']);
        $post->setPostType($data['postType']);

        $this->entityManager->persist($post);
        $this->entityManager->flush();

        $post_Id = $post->getId();

        return $post_Id;
    }

    /**
     * Permet de créer une nouvelle page statique avec les informations comme le titre et la description de la page
     * @param array $data
     * @return array
     */
    public function updatePageStatique($id, $data = [])
    {
        $post = $this->entityManager
            ->getRepository(WpPosts::class)
            ->findOneById($id);
        if ($post) {
            $post->setPostContent($data['postContent']);
            $post->setPostTitle($data['postTitle']);
            $post->setPostExcerpt($data['postExcerpt']);
            $post->setPostStatus($data['postStatus']);
            $post->setPostModified($data['postModified']);
            $post->setPostModifiedGmt($data['postModifiedGmt']);

            $this->entityManager->persist($post);
            $this->entityManager->flush();

            $post_Id = $post->getId();

            return $post_Id;
        }

        return -1;
    }

    /**
     * Permet de renvoyer les informations de la page statique
     * @param array $data
     * @return array
     */
    public function getPageStatique($code)
    {
        return $this->entityManager->getRepository(WpPosts::class)->findOneBy([
            'postExcerpt' => $code,
            'postType' => 'page-statique',
        ]);
    }

    // CRUD Post ou ensemble des données générales envoyés sur la BD
    //Cette partie sera gérée avec de maniere non conventionnelle car la relation est ManyToMany et la table de liaison existe deja.

    //CRUD Post

    /**
     * @param $postAuthor
     * @param $postDate
     * @param $postDateGmt
     * @param $postContent
     * @param $postTitle
     * @param $postExcerpt
     * @param $postStatus
     * @param $commentStatus
     * @param $pingStatus
     * @param $postPassword
     * @param $postName
     * @param $toPing
     * @param $pinged
     * @param $postModified
     * @param $postModifiedGmt
     * @param $postContentFiltered
     * @param $postParent
     * @param $guid
     * @param $menuOrder
     * @param $postType
     * @param $postMimeType
     * @param $commentCount
     * @param $termTaxonomyId
     * @return array
     */
    public function createPosts1(
        $postAuthor,
        $postDate,
        $postDateGmt,
        $postContent,
        $postTitle,
        $postExcerpt,
        $postStatus,
        $commentStatus,
        $pingStatus,
        $postPassword,
        $postName,
        $toPing,
        $pinged,
        $postModified,
        $postModifiedGmt,
        $postContentFiltered,
        $postParent,
        $guid,
        $menuOrder,
        $postType,
        $postMimeType,
        $commentCount,
        $termTaxonomyId
    ) {
        $post = new WpPosts();
        $post->setPostAuthor($postAuthor);
        $post->setPostDate($postDate);
        $post->setPostDateGmt($postDateGmt);
        $post->setPostContent($postContent);
        $post->setPostTitle($postTitle);
        $post->setPostExcerpt($postExcerpt);
        $post->setPostStatus($postStatus);
        $post->setCommentStatus($commentStatus);
        $post->setPingStatus($pingStatus);
        $post->setPostPassword($postPassword);
        $post->setPostName($postName);
        $post->setToPing($toPing);
        $post->setPinged($pinged);
        $post->setPostModified($postModified);
        $post->setPostModifiedGmt($postModifiedGmt);

        $post->setPostContentFiltered($postContentFiltered);
        $post->setPostParent($postParent);
        $post->setGuid($guid);
        $post->setMenuOrder($menuOrder);
        $post->setPostType($postType);
        $post->setPostMimeType($postMimeType);
        $post->setCommentCount($commentCount);

        $this->entityManager->persist($post);
        $this->entityManager->flush();

        //Generate unique name
        $size = sizeof($this->readPostsByName($postName));
        if (sizeof($this->readPostsByName($postName)) > 1) {
            $post->setPostName($postName . '-' . $post->getId());
        }

        $this->entityManager->persist($post);
        $this->entityManager->flush();
        //End unique name

        return $post->getId();
    }

    /**
     * @param $id
     * @return array|null|object
     */
    public function readPosts($id)
    {
        $post = $this->entityManager->getRepository(WpPosts::class)->find($id);
        if ($post) {
            return $post;
        } else {
            return ['response' => false, 'data' => $post];
        }
    }

    /**
     * @param $id
     * @return array|null|object
     */
    public function readPostsByName($name)
    {
        $post = $this->entityManager
            ->getRepository(WpPosts::class)
            ->findByPostName($name);
        if ($post) {
            return $post;
        } else {
            return [];
        }
    }

    /**
     */
    public function readPostsViaParentAndPostType($postType, $postParent)
    {
        $post = $this->entityManager->getRepository(WpPosts::class)->findOneBy([
            'postType' => $postType,
            'postParent' => $postParent,
        ]);

        return $post;
    }

    /**
     * @param $id
     * @param $postAuthor
     * @param $postDate
     * @param $postDateGmt
     * @param $postContent
     * @param $postTitle
     * @param $postExcerpt
     * @param $postStatus
     * @param $commentStatus
     * @param $pingStatus
     * @param $postPassword
     * @param $postName
     * @param $toPing
     * @param $pinged
     * @param $postModified
     * @param $postModifiedGmt
     * @param $postContentFiltered
     * @param $postParent
     * @param $guid
     * @param $menuOrder
     * @param $postType
     * @param $postMimeType
     * @param $commentCount
     * @param $termTaxonomyId
     * @return Response
     */
    public function updatePosts(
        $id,
        $postAuthor,
        $postDate,
        $postDateGmt,
        $postContent,
        $postTitle,
        $postExcerpt,
        $postStatus,
        $commentStatus,
        $pingStatus,
        $postPassword,
        $postName,
        $toPing,
        $pinged,
        $postModified,
        $postModifiedGmt,
        $postContentFiltered,
        $postParent,
        $guid,
        $menuOrder,
        $postType,
        $postMimeType,
        $commentCount,
        $termTaxonomyId,
        $locale = null
    ) {
        $post = $this->entityManager
            ->getRepository(WpPosts::class)
            ->findOneById($id);
        $post->setPostAuthor($postAuthor);
        $post->setPostDate($postDate);
        $post->setPostDateGmt($postDateGmt);
        if ($locale == null || $locale == 'fr') {
            $post->setPostContent($postContent);
            $post->setPostTitle($postTitle);
            $post->setPostName($postName);
        }
        $post->setPostExcerpt($postExcerpt);
        $post->setPostStatus($postStatus);
        $post->setCommentStatus($commentStatus);
        $post->setPingStatus($pingStatus);
        $post->setPostPassword($postPassword);
        $post->setToPing($toPing);
        $post->setPinged($pinged);
        $post->setPostModified($postModified);
        $post->setPostModifiedGmt($postModifiedGmt);

        $post->setPostContentFiltered($postContentFiltered);
        $post->setPostParent($postParent);
        $post->setGuid($guid);
        $post->setMenuOrder($menuOrder);
        $post->setPostType($postType);
        $post->setPostMimeType($postMimeType);
        $post->setCommentCount($commentCount);
        /* if ($locale) {
            $post->setTranslatableLocale($locale);
        } */
        $this->entityManager->persist($post);
        $this->entityManager->flush();
        $table_liaison = $this->entityManager
            ->getRepository(WpTermRelationships::class)
            ->findOneByObjectId($id);
        $table_liaison->setTermTaxonomyId($termTaxonomyId);

        $this->entityManager->persist($table_liaison);
        $this->entityManager->flush();

        return $post->getId();
    }

    /**
     * @param $id
     * @param $postAuthor
     * @param $postDate
     * @param $postDateGmt
     * @param $postContent
     * @param $postTitle
     * @param $postExcerpt
     * @param $postStatus
     * @param $commentStatus
     * @param $pingStatus
     * @param $postPassword
     * @param $postName
     * @param $toPing
     * @param $pinged
     * @param $postModified
     * @param $postModifiedGmt
     * @param $postContentFiltered
     * @param $postParent
     * @param $guid
     * @param $menuOrder
     * @param $postType
     * @param $postMimeType
     * @param $commentCount
     * @param $termTaxonomyId
     * @return Response
     */
    public function updatePostsAdsContent(
        $id,
        $postAuthor,
        $postDate,
        $postDateGmt,
        $postContent,
        $postTitle,
        $postExcerpt,
        $postStatus,
        $commentStatus,
        $pingStatus,
        $postPassword,
        $postName,
        $toPing,
        $pinged,
        $postModified,
        $postModifiedGmt,
        $postContentFiltered,
        $postParent,
        $guid,
        $menuOrder,
        $postType,
        $postMimeType,
        $commentCount,
        $termTaxonomyId,
        $locale = null
    ) {
        $post = $this->entityManager
            ->getRepository(WpPosts::class)
            ->findOneById($id);

        $post->setPostAuthor($postAuthor);
        $post->setPostDate($postDate);
        $post->setPostDateGmt($postDateGmt);
        if ($locale == null || $locale == 'fr') {
            $post->setPostContent($postContent);
            $post->setPostTitle($postTitle);
            $post->setPostName($postName);
        } /* elseif ($locale == 'en') {
            $post->setPostExcerpt($postExcerpt);
            $post->setPostContentFiltered($postContentFiltered);
            $post->setTranslatableLocale($locale);
        } */
        $post->setPostStatus($postStatus);
        $post->setCommentStatus($commentStatus);
        $post->setPingStatus($pingStatus);
        $post->setPostPassword($postPassword);
        $post->setToPing($toPing);
        $post->setPinged($pinged);
        $post->setPostModified($postModified);
        $post->setPostModifiedGmt($postModifiedGmt);

        $post->setPostParent($postParent);
        $post->setGuid($guid);
        $post->setMenuOrder($menuOrder);
        $post->setPostType($postType);
        $post->setPostMimeType($postMimeType);
        $post->setCommentCount($commentCount);
        $this->entityManager->persist($post);
        $this->entityManager->flush();

        $postId = $post->getId();
        //Generate unique name
        $size = sizeof($this->readPostsByName($postName));
        if (sizeof($this->readPostsByName($postName)) > 1) {
            $post->setPostName($postName . '-' . $postId);
        }
        $this->entityManager->persist($post);
        $this->entityManager->flush();
        //End unique name
        $table_liaison = $this->entityManager
            ->getRepository(WpTermRelationships::class)
            ->findOneByObjectId($id);

        $table_liaison->setTermTaxonomyId($termTaxonomyId);

        $this->entityManager->persist($table_liaison);
        $this->entityManager->flush();

        return $postId;
    }

    /**
     * @param $id
     * @return int
     */
    public function deletePosts($id)
    {
        $post = $this->entityManager->getRepository(WpPosts::class)->find($id);
        if (!$post) {
            return ['response' => false];
        }
        $this->entityManager->remove($post);
        $this->entityManager->flush();
        //Post enfants
        $post = $this->entityManager
            ->getRepository(WpPosts::class)
            ->findBy(['postParent' => $id]);
        if ($post) {
            for ($i = 0; $i < sizeof($post); $i++) {
                $this->entityManager->remove($post[$i]);
                $this->entityManager->flush();
            }
        }

        return 1;
    }

    /**
     * @param $id
     * @return int
     */
    public function updateImage($id, $guid)
    {
        $post = $this->entityManager->getRepository(WpPosts::class)->find($id);
        if (!$post) {
            return ['response' => false];
        }
        $post->setGuid($guid);
        $this->entityManager->persist($post);
        $this->entityManager->flush();

        return 1;
    }

    /**
     * @param $id
     * @return int
     */
    public function deletePosts1($id, $statut)
    {
        if ($id > 0) {
            $post = $this->entityManager
                ->getRepository(WpPosts::class)
                ->find($id);

            if (!$post) {
                return ['response' => false];
            }
            if ($statut == 'trash') {
                $this->entityManager->remove($post);
                $this->entityManager->flush();
            } elseif ($statut == 'draft') {
                $post->setPostStatus('trash');
                $this->entityManager->persist($post);
                $this->entityManager->flush();
            } elseif ($statut == 'publish') {
                $post->setPostStatus('publish');
                $this->entityManager->persist($post);
                $this->entityManager->flush();
            }
        } elseif ($id == 0 && $statut == 'all-trash') {
            $p = $this->naveMenuTrash();
            for ($i = 0; $i < sizeof($p); $i++) {
                $query = $this->entityManager
                    ->createQuery(
                        'DELETE FROM App\Entity\WpPosts p
                 WHERE p.id =:id'
                    )
                    ->setParameter('id', $p[$i]['id']);
                $r = $query->getResult();
            }
        }

        return 1;
    }

    /**
     * @param $id
     * @return int
     */
    public function deleteAllPostmetaByPostIdKey($post_id, $key)
    {
        $query = $this->entityManager
            ->createQuery(
                'DELETE FROM App\Entity\WpPostmeta p 
            WHERE p.postId =:post_id AND p.metaKey =:key'
            )
            ->setParameters([
                'post_id' => $post_id,
                'key' => $key,
            ]);
        $r = $query->getResult();
        return true;
    }

    /**
     * @param $user_id
     * @param $key
     * @return int
     */
    public function deleteAllUsermetaByUserIdKey($user_id, $key)
    {
        $query = $this->entityManager
            ->createQuery('DELETE FROM App\Entity\WpUsermeta p WHERE p.userId =:user_id AND p.metaKey =:key')
            ->setParameters(['user_id' => $user_id, 'key' => $key]);
        $r = $query->getResult();
        return true;
    }

    // Récupérer les annonces:

    /**
     * @param $id
     * @param $ville
     * @return null|array
     * @throws Exception
     */
    public function readAllAnnoncesforAdvancedSearch($id, $ville)
    {
        $listeAnnonces = '';

        if ($ville == '') {
            $conn = $this->entityManager->getConnection();

            $sql = 'SELECT wp.id AS id, wp.post_title as titre,wp.guid,wp.post_excerpt as devise,
            wp.post_author as idUser, wp.post_excerpt as disponibilite,
            wtt.taxonomy as categorie_parent, wpSousCategorie.name as sousCategorie,
            wptCategorie.name as categorieParent, wpm.meta_value as price,
            wpmAdresse.meta_value as adressePost, wpmImage.meta_value as gallerie

            FROM wp_posts wp
             JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
             JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id

             JOIN wp_postmeta wpm ON wp.id=wpm.post_id
             JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
             JOIN wp_terms wptCategorie ON wtt.parent=wptCategorie.term_id
             JOIN wp_postmeta wpmAdresse ON wp.id=wpmAdresse.post_id
             LEFT JOIN wp_postmeta wpmImage ON wp.id=wpmImage.post_id
             WHERE wp.post_type =:categoriePost and wtt.term_taxonomy_id=:id_categorie
            and wpm.meta_key =:price and wpmAdresse.meta_key=:adresse and wpmImage.meta_key=:gallerie
            and wp.post_status=:post_status';

            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'price' => '_sale_price',
                'adresse' => '_product_city',
                'id_categorie' => $id,
                'gallerie' => '_product_image_gallery',
                'categoriePost' => 'product',
                'post_status' => 'publish',
            ]);
            $listeAnnonces = $stmt->fetchAllAssociative();

            for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
                //Pour la devise
                $devise = $this->entityManager
                    ->getRepository(WpPostmeta::class)
                    ->findOneBy([
                        'postId' => $listeAnnonces[$i]['id'],
                        'metaKey' => '_product_devise',
                    ]);
                if ($devise) {
                    $listeAnnonces[$i]['devise'] = $devise->getMetaValue();
                } else {
                    $listeAnnonces[$i]['devise'] = '€';
                }

                //Adresse
                $listeAnnonces[$i]['pays'] = $this->getPostStringDataValue(
                    $listeAnnonces[$i]['id'],
                    '_product_country'
                );
                $listeAnnonces[$i]['ville'] = $this->getPostStringDataValue(
                    $listeAnnonces[$i]['id'],
                    '_product_city'
                );
                $listeAnnonces[$i]['etat'] = $this->getPostStringDataValue(
                    $listeAnnonces[$i]['id'],
                    '_product_adress'
                );
                //Images
                $listeAnnonces[$i]['gallerie'] = $this->getAllImageForAnnouces(
                    $listeAnnonces[$i]['id']
                );

                //Pour la disponibilité
                $calendrier = $this->entityManager
                    ->getRepository(WpUsermeta::class)
                    ->findOneBy([
                        'userId' => $listeAnnonces[$i]['idUser'],
                        'metaKey' => 'disponibilite',
                    ]);
                if ($calendrier) {
                    $listeAnnonces[$i]['disponibilite'] = explode(
                        ',',
                        $calendrier->metaValue()
                    );
                } else {
                    $listeAnnonces[$i]['disponibilite'] = [];
                }
            }
        }
        //100
        if ($ville != '') {
            $conn = $this->entityManager->getConnection();

            $sql = 'SELECT wp.id AS id, wp.post_title as titre,wp.guid,wp.post_excerpt as devise,
            wp.post_author as idUser, wp.post_excerpt as disponibilite,
            wtt.taxonomy as categorie_parent, wpSousCategorie.name as sousCategorie,
            wptCategorie.name as categorieParent, wpm.meta_value as price,
            wpmAdresse.meta_value as adressePost, wpmImage.meta_value as gallerie
            FROM wp_posts wp
             JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
             JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
             JOIN wp_postmeta wpm ON wp.id=wpm.post_id
             JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
             JOIN wp_terms wptCategorie ON wtt.parent=wptCategorie.term_id
             JOIN wp_postmeta wpmAdresse ON wp.id=wpmAdresse.post_id
             LEFT JOIN wp_postmeta wpmImage ON wp.id=wpmImage.post_id
             WHERE wp.post_type =:categoriePost and wtt.term_taxonomy_id=:id_categorie
            and wpm.meta_key =:price and wpmAdresse.meta_key=:adresse and wpmImage.meta_key=:gallerie
            and wp.post_status=:post_status and wpmAdresse.meta_value LIKE :ville';

            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'price' => '_sale_price',
                'adresse' => '_product_city',
                'id_categorie' => $id,
                'gallerie' => '_product_image_gallery',
                'categoriePost' => 'product',
                'ville' => '%' . $ville . '%',
                'post_status' => 'publish',
            ]);
            $listeAnnonces = $stmt->fetchAllAssociative();

            for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
                //Pour la devise
                $devise = $this->entityManager
                    ->getRepository(WpPostmeta::class)
                    ->findOneBy([
                        'postId' => $listeAnnonces[$i]['id'],
                        'metaKey' => '_product_devise',
                    ]);
                if ($devise) {
                    $listeAnnonces[$i]['devise'] = $devise->getMetaValue();
                } else {
                    $listeAnnonces[$i]['devise'] = '€';
                }

                //Adresse
                $listeAnnonces[$i]['pays'] = $this->getPostStringDataValue(
                    $listeAnnonces[$i]['id'],
                    '_product_country'
                );
                $listeAnnonces[$i]['ville'] = $this->getPostStringDataValue(
                    $listeAnnonces[$i]['id'],
                    '_product_city'
                );
                $listeAnnonces[$i]['etat'] = $this->getPostStringDataValue(
                    $listeAnnonces[$i]['id'],
                    '_product_adress'
                );
                //Images
                $listeAnnonces[$i]['gallerie'] = $this->getAllImageForAnnouces(
                    $listeAnnonces[$i]['id']
                );
                //Pour la disponibilité
                $calendrier = $this->entityManager
                    ->getRepository(WpUsermeta::class)
                    ->findOneBy([
                        'userId' => $listeAnnonces[$i]['idUser'],
                        'metaKey' => 'disponibilite',
                    ]);
                if ($calendrier) {
                    $listeAnnonces[$i]['disponibilite'] = explode(
                        ',',
                        $calendrier->metaValue()
                    );
                } else {
                    $listeAnnonces[$i]['disponibilite'] = [];
                }
            }
        }

        return $listeAnnonces;
    }

    // Récupérer les annonces:

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
            $listeAnnonces = $stmt->fetchAllAssociative();

            for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
                //price
                $listeAnnonces[$i]['price'] = $this->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_price'
                );
                //Images
                $listeAnnonces[$i]['gallery'] = $this->getAllImageForAnnouces(
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
                $listeAnnonces[$i]['pays'] = $this->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_country'
                );
                $listeAnnonces[$i]['ville'] = $this->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_city'
                );
                $listeAnnonces[$i]['etat'] = $this->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_adress'
                );
                //Images
                $listeAnnonces[$i]['gallerie'] = $this->getAllImageForAnnouces(
                    $listeAnnonces[$i]["id"]
                );
                //Images secondaires
                $listeAnnonces[$i]['images_secondaires'] = $this->getAllSecondImageForAnnouces(
                    $listeAnnonces[$i]["id"],
                    'images_annonces'
                );

                //Pour la disponibilité
                $calendrier = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['idUser'],
                    'disponibilite'
                );

                if ($calendrier != '') {
                    $arrayToTreat = explode(',', $calendrier);
                    usort($arrayToTreat, [$this, 'date_sort']);
                    $listeAnnonces[$i]['calendrier'] = $arrayToTreat;
                } else {
                    $listeAnnonces[$i]['calendrier'] = [];
                }

                $listeAnnonces[$i]['titre_en'] = '';
                $listeAnnonces[$i]['sousCategorie_en'] = '';
                $listeAnnonces[$i]['categorieParent_en'] = '';
                $title_en = $this->entityManager
                    ->getRepository(ExtTranslationWpPosts::class)
                    ->findOneBy([
                        'foreign_key' => $listeAnnonces[$i]["id"],
                        'field' => 'postExcerpt',
                        'locale' => 'en',
                    ]);
                if ($title_en) {
                    $listeAnnonces[$i]['titre_en'] = $title_en->getContent();
                }

                $cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy([
                        'foreign_key' => $listeAnnonces[$i]['idCat'],
                        'field' => 'name',
                        'locale' => 'en_US',
                    ]);
                if ($cat_en) {
                    $listeAnnonces[$i]['categorieParent_en'] = $cat_en->getContent();
                }

                $sous_cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy([
                        'foreign_key' => $listeAnnonces[$i]['idSousCat'],
                        'field' => 'name',
                        'locale' => 'en_US',
                    ]);
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
                $listeAnnonces[$i]['price'] = $this->getPostStringDataValue(
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
                $listeAnnonces[$i]['pays'] = $this->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_country'
                );
                $listeAnnonces[$i]['ville'] = $this->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_city'
                );
                $listeAnnonces[$i]['etat'] = $this->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_adress'
                );
                //Images
                $listeAnnonces[$i]['gallery'] = $this->getAllImageForAnnouces(
                    $listeAnnonces[$i]["id"]
                );
                //Images secondaires
                $listeAnnonces[$i]['images_secondaires'] = $this->getAllSecondImageForAnnouces(
                    $listeAnnonces[$i]["id"],
                    'images_annonces'
                );
                //Pour la disponibilité
                $calendrier = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['idUser'],
                    'disponibilite'
                );
                if ($calendrier != '') {
                    $arrayToTreat = explode(',', $calendrier);
                    usort($arrayToTreat, [$this, 'date_sort']);
                    $listeAnnonces[$i]['calendrier'] = $arrayToTreat;
                } else {
                    $listeAnnonces[$i]['calendrier'] = [];
                }

                $listeAnnonces[$i]['titre_en'] = '';
                $listeAnnonces[$i]['sousCategorie_en'] = '';
                $listeAnnonces[$i]['categorieParent_en'] = '';
                $title_en = $this->entityManager
                    ->getRepository(ExtTranslationWpPosts::class)
                    ->findOneBy([
                        'foreign_key' => $listeAnnonces[$i]["id"],
                        'field' => 'postExcerpt',
                        'locale' => 'en',
                    ]);
                if ($title_en) {
                    $listeAnnonces[$i]['titre_en'] = $title_en->getContent();
                }

                $cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy([
                        'foreign_key' => $listeAnnonces[$i]['idCat'],
                        'field' => 'name',
                        'locale' => 'en_US',
                    ]);
                if ($cat_en) {
                    $listeAnnonces[$i]['categorieParent_en'] = $cat_en->getContent();
                }

                $sous_cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy([
                        'foreign_key' => $listeAnnonces[$i]['idSousCat'],
                        'field' => 'name',
                        'locale' => 'en_US',
                    ]);
                if ($sous_cat_en) {
                    $listeAnnonces[$i]['sousCategorie_en'] = $sous_cat_en->getContent();
                }
            }
        }
        /* var_dump($listeAnnonces);die();*/
        return $listeAnnonces;
    }

    public function getAllAnnoncesForSitemap()
    {
        return $this->entityManager
            ->getRepository(WpPosts::class)
            ->findBy(['postStatus' => 'publish', 'postType' => 'product']);
    }

    /**
     * @param $id
     * @param $date_depays_ville_regionbut
     * @return null|array
     */
    public function readAllAnnoncesParentforAjaxSearch($id, $code_postal_ville)
    {
        $listeAnnonces = [];

        if ($code_postal_ville == '') {
            $conn = $this->entityManager->getConnection();

            $sql = 'SELECT wp.post_title as titre,wp.post_author,wp.id,wp.post_date, wp.post_modified, wp.post_content,wp.post_name,
            wtt.taxonomy as categorie_parent , wpSousCategorie.name as sousCategorie, wp.post_content as avatar,
            wptCategorie.name as categorieParent,wp.guid as price, wp.guid as devise, wp.guid
            ,wp.guid as calendrier, wp.guid as first_name, wp.guid as last_name, wp.guid as pays,
            wp.guid as videos, wp.guid as commentaires, wp.guid as ville,wp.guid as etat
            ,wp.guid as gallery, wp.guid as adressePost, wptCategorie.name as categorieParent_en,
            wpSousCategorie.name as sousCategorie_en , wp.post_title as titre_en,
            wpSousCategorie.term_id as idSousCat, wtt.parent as idCat, wp.post_content as images_secondaires,
            wptCategorie.slug as categorieParentSlug, wpSousCategorie.slug as sousCategorieSlug
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
                $listeAnnonces[$i]['price'] = $this->getPostStringDataValue(
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
                $listeAnnonces[$i]['pays'] = $this->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_country'
                );
                $listeAnnonces[$i]['ville'] = $this->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_city'
                );
                $listeAnnonces[$i]['etat'] = $this->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_adress'
                );
                //Images
                $listeAnnonces[$i]['gallery'] = $this->getAllImageForAnnouces(
                    $listeAnnonces[$i]["id"]
                );
                //Images secondaires
                $listeAnnonces[$i]['images_secondaires'] = $this->getAllSecondImageForAnnouces(
                    $listeAnnonces[$i]["id"],
                    'images_annonces'
                );
                //Pour la disponibilité
                $calendrier = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['post_author'],
                    'disponibilite'
                );
                if ($calendrier != '') {
                    $arrayToTreat = explode(',', $calendrier);
                    usort($arrayToTreat, [$this, 'date_sort']);
                    $listeAnnonces[$i]['calendrier'] = $arrayToTreat;
                } else {
                    $listeAnnonces[$i]['calendrier'] = [];
                }
                $listeAnnonces[$i]['titre_en'] = '';
                $listeAnnonces[$i]['sousCategorie_en'] = '';
                $listeAnnonces[$i]['categorieParent_en'] = '';
                $title_en = $this->entityManager
                    ->getRepository(ExtTranslationWpPosts::class)
                    ->findOneBy([
                        'foreign_key' => $listeAnnonces[$i]["id"],
                        'field' => 'postExcerpt',
                        'locale' => 'en',
                    ]);
                if ($title_en) {
                    $listeAnnonces[$i]['titre_en'] = $title_en->getContent();
                }

                $cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy([
                        'foreign_key' => $listeAnnonces[$i]['idCat'],
                        'field' => 'name',
                        'locale' => 'en_US',
                    ]);
                if ($cat_en) {
                    $listeAnnonces[$i]['categorieParent_en'] = $cat_en->getContent();
                }

                $sous_cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy([
                        'foreign_key' => $listeAnnonces[$i]['idSousCat'],
                        'field' => 'name',
                        'locale' => 'en_US',
                    ]);
                if ($sous_cat_en) {
                    $listeAnnonces[$i]['sousCategorie_en'] = $sous_cat_en->getContent();
                }
            }
        }

        if ($code_postal_ville != '') {
            $conn = $this->entityManager->getConnection();

            $sql = "SELECT wp.post_title as titre,wp.post_author,wp.id,wp.post_date, wp.post_modified, wp.post_content,wp.post_name,
            wtt.taxonomy as categorie_parent , wpSousCategorie.name as sousCategorie, wp.post_content as avatar,
            wptCategorie.name as categorieParent,wp.guid as price, wp.guid as devise, wp.guid,
            wp.guid as calendrier, wp.guid as first_name, wp.guid as last_name, wp.guid as pays,
            wp.guid as videos, wp.guid as commentaires, wp.guid as ville,wp.guid as etat,
            wp.guid as gallery, wp.guid as adressePost, wptCategorie.name as categorieParent_en,
            wpSousCategorie.name as sousCategorie_en , wp.post_title as titre_en, wp.post_content as images_secondaires,
            wpSousCategorie.term_id as idSousCat, wtt.parent as idCat,
            wptCategorie.slug as categorieParentSlug, wpSousCategorie.slug as sousCategorieSlug
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
                $listeAnnonces[$i]['price'] = $this->getPostStringDataValue(
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
                $listeAnnonces[$i]['pays'] = $this->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_country'
                );
                $listeAnnonces[$i]['ville'] = $this->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_city'
                );
                $listeAnnonces[$i]['etat'] = $this->getPostStringDataValue(
                    $listeAnnonces[$i]["id"],
                    '_product_adress'
                );
                //Images
                $listeAnnonces[$i]['gallery'] = $this->getAllImageForAnnouces(
                    $listeAnnonces[$i]["id"]
                );
                //Images secondaires
                $listeAnnonces[$i]['images_secondaires'] = $this->getAllSecondImageForAnnouces(
                    $listeAnnonces[$i]["id"],
                    'images_annonces'
                );
                //Pour la disponibilité
                $calendrier = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['post_author'],
                    'disponibilite'
                );
                if ($calendrier != '') {
                    $arrayToTreat = explode(',', $calendrier);
                    usort($arrayToTreat, [$this, 'date_sort']);
                    $listeAnnonces[$i]['calendrier'] = $arrayToTreat;
                } else {
                    $listeAnnonces[$i]['calendrier'] = [];
                }

                $listeAnnonces[$i]['titre_en'] = '';
                $listeAnnonces[$i]['sousCategorie_en'] = '';
                $listeAnnonces[$i]['categorieParent_en'] = '';
                $title_en = $this->entityManager
                    ->getRepository(ExtTranslationWpPosts::class)
                    ->findOneBy([
                        'foreign_key' => $listeAnnonces[$i]["id"],
                        'field' => 'postExcerpt',
                        'locale' => 'en',
                    ]);
                if ($title_en) {
                    $listeAnnonces[$i]['titre_en'] = $title_en->getContent();
                }

                $cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy([
                        'foreign_key' => $listeAnnonces[$i]['idCat'],
                        'field' => 'name',
                        'locale' => 'en_US',
                    ]);
                if ($cat_en) {
                    $listeAnnonces[$i]['categorieParent_en'] = $cat_en->getContent();
                }

                $sous_cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy([
                        'foreign_key' => $listeAnnonces[$i]['idSousCat'],
                        'field' => 'name',
                        'locale' => 'en_US',
                    ]);
                if ($sous_cat_en) {
                    $listeAnnonces[$i]['sousCategorie_en'] = $sous_cat_en->getContent();
                }
            }
        }
        /* var_dump($listeAnnonces);die();*/
        return $listeAnnonces;
    }

    // Récupérer les Professionnels:
    /**
     * @param $id
     * @param $ville
     * @param $date_debut
     * @param $date_fin
     * @return null|array
     */
    public function readAllProforAdvancedSearch(
        $id,
        $ville,
        $date_debut,
        $date_fin
    ) {
        $listeAnnonces = '';
        //000
        if ($ville == '' && $date_debut == '' && $date_fin == '') {
            $conn = $this->entityManager->getConnection();

            $sql = 'SELECT wpUsers.id as id,wpUsers.userEmail as email,
            wpFirstName.meta_value as first_name, wpLastName.meta_value as last_name,
            wp.guid as avatar , wp.guid as titre, wp.guid as pays , wp.guid as ville, wp.guid as etat ,wp.post_name,
            wp.guid as disponibilite, wp.guid as adresse, wp.post_content as competence,wp.id as postId,
            wptCategorie.name as categorieParent
            FROM wp_users wpUsers
            JOIN wp_posts wp ON wpUsers.id=wp.post_author
            JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            JOIN wp_terms wptCategorie ON wtt.parent=wptCategorie.term_id
            JOIN wp_usermeta wpFirstName ON wpUsers.id=wpFirstName.user_id
            JOIN wp_usermeta wpLastName ON wpUsers.id=wpLastName.user_id
            WHERE wp.post_type =:categoriePost and wtt.term_taxonomy_id=:id_categorie
            and wpFirstName.meta_key =:first_name and wpLastName.meta_key=:last_name and wp.post_status=:postStatus
            GROUP BY id';

            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'id_categorie' => $id,
                'categoriePost' => 'product',
                'postStatus' => 'publish',
                'first_name' => 'first_name',
                'last_name' => 'last_name',
            ]);
            $listeAnnonces = $stmt->fetchAllAssociative();

            for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
                //Pour l'adresse
                $adresse = $this->entityManager
                    ->getRepository(WpUsermeta::class)
                    ->findOneBy([
                        'userId' => $listeAnnonces[$i]['id'],
                        'metaKey' => 'billing_city',
                    ]);
                if ($adresse) {
                    $listeAnnonces[$i]['adresse'] = $adresse->getMetaValue();
                } else {
                    $listeAnnonces[$i]['adresse'] = '';
                }

                //Adresse
                $listeAnnonces[$i]['pays'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'billing_country'
                );
                $listeAnnonces[$i]['ville'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'billing_city'
                );
                $listeAnnonces[$i]['etat'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'billing_state'
                );
                //Pour le portfolio
                $listeAnnonces[$i]['avatar'] = @unserialize(
                    $this->getUserStringDataValue(
                        $listeAnnonces[$i]['id'],
                        'basic_user_avatar'
                    )
                );
                if ($listeAnnonces[$i]['avatar']) {
                    $listeAnnonces[$i]['avatar'] = array_shift(
                        $listeAnnonces[$i]['avatar']
                    );
                }

                //Pour les competences
                $competence = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'competence'
                );
                if ($competence) {
                    $listeAnnonces[$i]['competence'] = explode(
                        ',',
                        $competence
                    );
                } else {
                    $listeAnnonces[$i]['competence'] = [];
                }
                //TITRE
                $listeAnnonces[$i]['titre'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'titre'
                );
                // Cout moyen
                $data = $this->entityManager
                    ->createQuery(
                        'SELECT wp,t.metaValue FROM App\Entity\WpPosts wp
                 JOIN App\Entity\WpPostmeta t
                 WHERE wp.postAuthor=:iduser and wp.id=t.postId and wp.postType=:postType
                 and wp.postStatus=:postStatus and t.metaKey=:metaKey'
                    )
                    ->setParameters([
                        'iduser' => $listeAnnonces[$i]['id'],
                        'postType' => 'product',
                        'postStatus' => 'publish',
                        'metaKey' => '_price',
                    ])
                    ->getResult();
                $sommeTotale = 0;
                $sommeMoyenne = 0;
                for ($a = 0; $a < sizeof($data); $a++) {
                    $sommeTotale = $sommeTotale + $data[$a]['metaValue'];
                }
                /* var_dump($sommeTotale);*/
                $sommeMoyenne = $sommeTotale / sizeof($data);
                $listeAnnonces[$i]['sommeMoyenne'] = $sommeMoyenne;
                //Pour la disponibilité
                $calendrier = $this->entityManager
                    ->getRepository(WpUsermeta::class)
                    ->findOneBy([
                        'userId' => $listeAnnonces[$i]['id'],
                        'metaKey' => 'disponibilite',
                    ]);
                if ($calendrier) {
                    $listeAnnonces[$i]['disponibilite'] = explode(
                        ',',
                        $calendrier->getMetaValue()
                    );
                } else {
                    $listeAnnonces[$i]['disponibilite'] = [];
                }
            }
        }
        //001
        if ($ville == '' && $date_debut == '' && $date_fin != '') {
            $conn = $this->entityManager->getConnection();

            $sql = 'SELECT wpUsers.id as id,wpUsers.userEmail as email,
            wpFirstName.meta_value as first_name, wpLastName.meta_value as last_name,
            wp.guid as avatar , wp.guid as titre, wp.guid as pays , wp.guid as ville, wp.guid as etat ,
            wp.guid as disponibilite, wp.guid as adresse, wp.post_content as competence,wp.id as postId,
            wptCategorie.name as categorieParent
            FROM wp_users wpUsers
            JOIN wp_posts wp ON wpUsers.id=wp.post_author
            JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            JOIN wp_terms wptCategorie ON wtt.parent=wptCategorie.term_id
            JOIN wp_usermeta wpFirstName ON wpUsers.id=wpFirstName.user_id
            JOIN wp_usermeta wpLastName ON wpUsers.id=wpLastName.user_id
            WHERE wp.post_type =:categoriePost and wtt.term_taxonomy_id=:id_categorie
            and wpFirstName.meta_key =:first_name and wpLastName.meta_key=:last_name and wp.post_status=:postStatus
            GROUP BY id';

            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'id_categorie' => $id,
                'categoriePost' => 'product',
                'postStatus' => 'publish',
                'first_name' => 'first_name',
                'last_name' => 'last_name',
            ]);
            $listeAnnonces = $stmt->fetchAllAssociative();

            for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
                //Adresse
                $listeAnnonces[$i]['pays'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'billing_country'
                );
                $listeAnnonces[$i]['ville'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'billing_city'
                );
                $listeAnnonces[$i]['etat'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'billing_state'
                );
                //Pour le portfolio
                $listeAnnonces[$i]['avatar'] = @unserialize(
                    $this->getUserStringDataValue(
                        $listeAnnonces[$i]['id'],
                        'basic_user_avatar'
                    )
                );
                $listeAnnonces[$i]['avatar'] = array_shift(
                    $listeAnnonces[$i]['avatar']
                );

                //Pour les competences
                $competence = $this->entityManager
                    ->getRepository(WpUsermeta::class)
                    ->findOneBy([
                        'userId' => $listeAnnonces[$i]['id'],
                        'metaKey' => 'competence',
                    ]);
                if ($competence) {
                    $listeAnnonces[$i]['competence'] = explode(
                        ',',
                        $competence->getMetaValue()
                    );
                } else {
                    $listeAnnonces[$i]['competence'] = [];
                }
                //TITRE
                $listeAnnonces[$i]['titre'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'titre'
                );
                // Cout moyen
                $data = $this->entityManager
                    ->createQuery(
                        'SELECT wp,t.metaValue FROM App\Entity\WpPosts wp
                 JOIN App\Entity\WpPostmeta t
                 WHERE wp.postAuthor=:iduser and wp.id=t.postId and wp.postType=:postType
                 and wp.postStatus=:postStatus and t.metaKey=:metaKey'
                    )
                    ->setParameters([
                        'iduser' => $listeAnnonces[$i]['id'],
                        'postType' => 'product',
                        'postStatus' => 'publish',
                        'metaKey' => '_price',
                    ])
                    ->getResult();
                $sommeTotale = 0;
                $sommeMoyenne = 0;
                for ($a = 0; $a < sizeof($data); $a++) {
                    $sommeTotale = $sommeTotale + $data[$a]['metaValue'];
                }
                if ($sommeTotale != 0) {
                    $sommeMoyenne = $sommeTotale / sizeof($data);
                }
                $listeAnnonces[$i]['sommeMoyenne'] = $sommeMoyenne;
                //Pour la disponibilité
                $calendrier = $this->entityManager
                    ->getRepository(WpUsermeta::class)
                    ->findOneBy([
                        'userId' => $listeAnnonces[$i]['id'],
                        'metaKey' => 'disponibilite',
                    ]);
                if ($calendrier) {
                    $calendrier = explode(',', $calendrier->getMetaValue());
                    for ($k = 0; $k < sizeof($calendrier); $k++) {
                        if ($calendrier[$k] < $date_fin) {
                            continue;
                        } else {
                            unset($listeAnnonces[$i]);
                        }
                    }
                    $listeAnnonces[$i]['disponibilite'] = $calendrier;
                } else {
                    $listeAnnonces[$i]['disponibilite'] = [];
                }
            }
        }
        //010
        if ($ville == '' && $date_debut != '' && $date_fin == '') {
            $conn = $this->entityManager->getConnection();

            $sql = 'SELECT wpUsers.id as id,wpUsers.userEmail as email,
            wpFirstName.meta_value as first_name, wpLastName.meta_value as last_name,
            wp.guid as avatar , wp.guid as titre, wp.guid as pays , wp.guid as ville, wp.guid as etat ,
            wp.guid as disponibilite, wp.guid as adresse, wp.post_content as competence,wp.id as postId,
            wptCategorie.name as categorieParent
            FROM wp_users wpUsers
            JOIN wp_posts wp ON wpUsers.id=wp.post_author
            JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            JOIN wp_terms wptCategorie ON wtt.parent=wptCategorie.term_id
            JOIN wp_usermeta wpFirstName ON wpUsers.id=wpFirstName.user_id
            JOIN wp_usermeta wpLastName ON wpUsers.id=wpLastName.user_id
            WHERE wp.post_type =:categoriePost and wtt.term_taxonomy_id=:id_categorie
            and wpFirstName.meta_key =:first_name and wpLastName.meta_key=:last_name and wp.post_status=:postStatus
            GROUP BY id';

            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'id_categorie' => $id,
                'categoriePost' => 'product',
                'postStatus' => 'publish',
                'first_name' => 'first_name',
                'last_name' => 'last_name',
            ]);
            $listeAnnonces = $stmt->fetchAllAssociative();

            for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
                //Adresse
                $listeAnnonces[$i]['pays'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'billing_country'
                );
                $listeAnnonces[$i]['ville'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'billing_city'
                );
                $listeAnnonces[$i]['etat'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'billing_state'
                );
                //Pour le portfolio
                $listeAnnonces[$i]['avatar'] = @unserialize(
                    $this->getUserStringDataValue(
                        $listeAnnonces[$i]['id'],
                        'basic_user_avatar'
                    )
                );
                $listeAnnonces[$i]['avatar'] = array_shift(
                    $listeAnnonces[$i]['avatar']
                );

                //Pour les competences
                $competence = $this->entityManager
                    ->getRepository(WpUsermeta::class)
                    ->findOneBy([
                        'userId' => $listeAnnonces[$i]['id'],
                        'metaKey' => 'competence',
                    ]);
                if ($competence) {
                    $listeAnnonces[$i]['competence'] = explode(
                        ',',
                        $competence->getMetaValue()
                    );
                } else {
                    $listeAnnonces[$i]['competence'] = [];
                }
                //TITRE
                $listeAnnonces[$i]['titre'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'titre'
                );
                // Cout moyen
                $data = $this->entityManager
                    ->createQuery(
                        'SELECT wp,t.metaValue FROM App\Entity\WpPosts wp
                JOIN App\Entity\WpPostmeta t
                WHERE wp.postAuthor=:iduser and wp.id=t.postId and wp.postType=:postType
                and wp.postStatus=:postStatus and t.metaKey=:metaKey'
                    )
                    ->setParameters([
                        'iduser' => $listeAnnonces[$i]['id'],
                        'postType' => 'product',
                        'postStatus' => 'publish',
                        'metaKey' => '_price',
                    ])
                    ->getResult();
                $sommeTotale = 0;
                $sommeMoyenne = 0;
                for ($a = 0; $a < sizeof($data); $a++) {
                    $sommeTotale = $sommeTotale + $data[$a]['metaValue'];
                }
                if ($sommeTotale != 0) {
                    $sommeMoyenne = $sommeTotale / sizeof($data);
                }
                $listeAnnonces[$i]['sommeMoyenne'] = $sommeMoyenne;
                // Cout moyen
                $data = $this->entityManager
                    ->createQuery(
                        'SELECT wp,t.metaValue FROM App\Entity\WpPosts wp
                JOIN App\Entity\WpPostmeta t
                WHERE wp.postAuthor=:iduser and wp.id=t.postId and wp.postType=:postType
                and wp.postStatus=:postStatus and t.metaKey=:metaKey'
                    )
                    ->setParameters([
                        'iduser' => $listeAnnonces[$i]['id'],
                        'postType' => 'product',
                        'postStatus' => 'publish',
                        'metaKey' => '_price',
                    ])
                    ->getResult();
                $sommeTotale = 0;
                $sommeMoyenne = 0;
                for ($a = 0; $a < sizeof($data); $a++) {
                    $sommeTotale = $sommeTotale + $data[$a]['metaValue'];
                }
                if ($sommeTotale != 0) {
                    $sommeMoyenne = $sommeTotale / sizeof($data);
                }
                $listeAnnonces[$i]['sommeMoyenne'] = $sommeMoyenne;
                //Pour la disponibilité
                $calendrier = $this->entityManager
                    ->getRepository(WpUsermeta::class)
                    ->findOneBy([
                        'userId' => $listeAnnonces[$i]['id'],
                        'metaKey' => 'disponibilite',
                    ]);
                if ($calendrier) {
                    $calendrier = explode(',', $calendrier->getMetaValue());
                    for ($k = 0; $k < sizeof($calendrier); $k++) {
                        if ($calendrier[$k] > $date_debut) {
                            continue;
                        } else {
                            unset($listeAnnonces[$i]);
                        }
                    }
                    $listeAnnonces[$i]['disponibilite'] = $calendrier;
                } else {
                    $listeAnnonces[$i]['disponibilite'] = [];
                }
            }
        }
        //011
        if ($ville == '' && $date_debut != '' && $date_fin != '') {
            $conn = $this->entityManager->getConnection();

            $sql = 'SELECT wpUsers.id as id,wpUsers.userEmail as email,
            wpFirstName.meta_value as first_name, wpLastName.meta_value as last_name,
            wp.guid as avatar , wp.guid as titre, wp.guid as pays , wp.guid as ville, wp.guid as etat ,
            wp.guid as disponibilite, wp.guid as adresse, wp.post_content as competence,wp.id as postId,
            wptCategorie.name as categorieParent
            FROM wp_users wpUsers
            JOIN wp_posts wp ON wpUsers.id=wp.post_author
            JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            JOIN wp_terms wptCategorie ON wtt.parent=wptCategorie.term_id
            JOIN wp_usermeta wpFirstName ON wpUsers.id=wpFirstName.user_id
            JOIN wp_usermeta wpLastName ON wpUsers.id=wpLastName.user_id
            WHERE wp.post_type =:categoriePost and wtt.term_taxonomy_id=:id_categorie
            and wpFirstName.meta_key =:first_name and wpLastName.meta_key=:last_name and wp.post_status=:postStatus
            GROUP BY id';

            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'id_categorie' => $id,
                'categoriePost' => 'product',
                'postStatus' => 'publish',
                'first_name' => 'first_name',
                'last_name' => 'last_name',
            ]);
            $listeAnnonces = $stmt->fetchAllAssociative();

            for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
                //Adresse
                $listeAnnonces[$i]['pays'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'billing_country'
                );
                $listeAnnonces[$i]['ville'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'billing_city'
                );
                $listeAnnonces[$i]['etat'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'billing_state'
                );
                //Pour le portfolio
                $listeAnnonces[$i]['avatar'] = @unserialize(
                    $this->getUserStringDataValue(
                        $listeAnnonces[$i]['id'],
                        'basic_user_avatar'
                    )
                );
                $listeAnnonces[$i]['avatar'] = array_shift(
                    $listeAnnonces[$i]['avatar']
                ); //Pour les competences
                $competence = $this->entityManager
                    ->getRepository(WpUsermeta::class)
                    ->findOneBy([
                        'userId' => $listeAnnonces[$i]['id'],
                        'metaKey' => 'competence',
                    ]);
                if ($competence) {
                    $listeAnnonces[$i]['competence'] = explode(
                        ',',
                        $competence->getMetaValue()
                    );
                } else {
                    $listeAnnonces[$i]['competence'] = [];
                }
                //TITRE
                $listeAnnonces[$i]['titre'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'titre'
                );
                // Cout moyen
                $data = $this->entityManager
                    ->createQuery(
                        'SELECT wp,t.metaValue FROM App\Entity\WpPosts wp
                JOIN App\Entity\WpPostmeta t
                WHERE wp.postAuthor=:iduser and wp.id=t.postId and wp.postType=:postType
                and wp.postStatus=:postStatus and t.metaKey=:metaKey'
                    )
                    ->setParameters([
                        'iduser' => $listeAnnonces[$i]['id'],
                        'postType' => 'product',
                        'postStatus' => 'publish',
                        'metaKey' => '_price',
                    ])
                    ->getResult();
                $sommeTotale = 0;
                $sommeMoyenne = 0;
                for ($a = 0; $a < sizeof($data); $a++) {
                    $sommeTotale = $sommeTotale + $data[$a]['metaValue'];
                }
                if ($sommeTotale != 0) {
                    $sommeMoyenne = $sommeTotale / sizeof($data);
                }
                $listeAnnonces[$i]['sommeMoyenne'] = $sommeMoyenne;
                //Pour la disponibilité
                $calendrier = $this->entityManager
                    ->getRepository(WpUsermeta::class)
                    ->findOneBy([
                        'userId' => $listeAnnonces[$i]['id'],
                        'metaKey' => 'disponibilite',
                    ]);
                if ($calendrier) {
                    $calendrier = explode(',', $calendrier->getMetaValue());
                    for ($k = 0; $k < sizeof($calendrier); $k++) {
                        if (
                            $calendrier[$k] <= $date_fin &&
                            $calendrier[$k] > $date_debut
                        ) {
                            continue;
                        } else {
                            unset($listeAnnonces[$i]);
                        }
                    }
                    $listeAnnonces[$i]['disponibilite'] = $calendrier;
                } else {
                    $listeAnnonces[$i]['disponibilite'] = [];
                }
            }
        }
        //100
        if ($ville != '' && $date_debut == '' && $date_fin == '') {
            $conn = $this->entityManager->getConnection();

            $sql = 'SELECT wpUsers.id as id,wpUsers.userEmail as email,
            wpFirstName.meta_value as first_name, wpLastName.meta_value as last_name,
            wp.guid as avatar , wp.guid as titre, wp.guid as pays , wp.guid as ville, wp.guid as etat ,
            wp.guid as disponibilite, wp.guid as adresse, wp.post_content as competence,wp.id as postId,
            wptCategorie.name as categorieParent
            FROM wp_users wpUsers
            JOIN wp_posts wp ON wpUsers.id=wp.post_author
            JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            JOIN wp_terms wptCategorie ON wtt.parent=wptCategorie.term_id
            JOIN wp_usermeta wpFirstName ON wpUsers.id=wpFirstName.user_id
            JOIN wp_usermeta wpLastName ON wpUsers.id=wpLastName.user_id
            JOIN wp_usermeta wpRegion ON wpUsers.id=wpRegion.user_id
            LEFT JOIN wp_usermeta wpmImage ON wpUsers.id=wpmImage.user_id
            LEFT JOIN wp_usermeta wpCompetences ON wpUsers.id=wpCompetences.user_id
            WHERE wp.post_type =:categoriePost and wtt.term_taxonomy_id=:id_categorie
            and wpRegion.meta_key=:adresse and wpFirstName.meta_key=:first_name and wpLastName.meta_key=:last_name and wp.post_status=:postStatus
            and wpRegion.meta_value LIKE :ville
            GROUP BY id';

            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'adresse' => 'billing_city',
                'id_categorie' => $id,
                'postStatus' => 'publish',
                'categoriePost' => 'product',
                'first_name' => 'first_name',
                'last_name' => 'last_name',
                'ville' => '%' . $ville . '%',
            ]);
            $listeAnnonces = $stmt->fetchAllAssociative();

            for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
                //Adresse
                $listeAnnonces[$i]['pays'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'billing_country'
                );
                $listeAnnonces[$i]['ville'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'billing_city'
                );
                $listeAnnonces[$i]['etat'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'billing_state'
                );
                //Pour le portfolio
                $listeAnnonces[$i]['avatar'] = @unserialize(
                    $this->getUserStringDataValue(
                        $listeAnnonces[$i]['id'],
                        'basic_user_avatar'
                    )
                );
                $listeAnnonces[$i]['avatar'] = array_shift(
                    $listeAnnonces[$i]['avatar']
                );

                //Pour les competences
                $competence = $this->entityManager
                    ->getRepository(WpUsermeta::class)
                    ->findOneBy([
                        'userId' => $listeAnnonces[$i]['id'],
                        'metaKey' => 'competence',
                    ]);
                if ($competence) {
                    $listeAnnonces[$i]['competence'] = $competence;
                } else {
                    $listeAnnonces[$i]['competence'] = [];
                }
                //TITRE
                $listeAnnonces[$i]['titre'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'titre'
                );
                // Cout moyen
                $data = $this->entityManager
                    ->createQuery(
                        'SELECT wp,t.metaValue FROM App\Entity\WpPosts wp
                JOIN App\Entity\WpPostmeta t
                WHERE wp.postAuthor=:iduser and wp.id=t.postId and wp.postType=:postType
                and wp.postStatus=:postStatus and t.metaKey=:metaKey'
                    )
                    ->setParameters([
                        'iduser' => $listeAnnonces[$i]['id'],
                        'postType' => 'product',
                        'postStatus' => 'publish',
                        'metaKey' => '_price',
                    ])
                    ->getResult();
                $sommeTotale = 0;
                $sommeMoyenne = 0;
                for ($a = 0; $a < sizeof($data); $a++) {
                    $sommeTotale = $sommeTotale + $data[$a]['metaValue'];
                }
                if ($sommeTotale != 0) {
                    $sommeMoyenne = $sommeTotale / sizeof($data);
                }
                $listeAnnonces[$i]['sommeMoyenne'] = $sommeMoyenne;
                // Cout moyen
                $data = $this->entityManager
                    ->createQuery(
                        'SELECT wp,t.metaValue FROM App\Entity\WpPosts wp
                JOIN App\Entity\WpPostmeta t
                WHERE wp.postAuthor=:iduser and wp.id=t.postId and wp.postType=:postType
                and wp.postStatus=:postStatus and t.metaKey=:metaKey'
                    )
                    ->setParameters([
                        'iduser' => $listeAnnonces[$i]['id'],
                        'postType' => 'product',
                        'postStatus' => 'publish',
                        'metaKey' => '_price',
                    ])
                    ->getResult();
                $sommeTotale = 0;
                $sommeMoyenne = 0;
                for ($a = 0; $a < sizeof($data); $a++) {
                    $sommeTotale = $sommeTotale + $data[$a]['metaValue'];
                }
                if ($sommeTotale != 0) {
                    $sommeMoyenne = $sommeTotale / sizeof($data);
                }
                $listeAnnonces[$i]['sommeMoyenne'] = $sommeMoyenne;
                //Pour la disponibilité
                $calendrier = $this->entityManager
                    ->getRepository(WpUsermeta::class)
                    ->findOneBy([
                        'userId' => $listeAnnonces[$i]['id'],
                        'metaKey' => 'disponibilite',
                    ]);
                if ($calendrier) {
                    $listeAnnonces[$i]['disponibilite'] = explode(
                        ',',
                        $calendrier->getMetaValue()
                    );
                } else {
                    $listeAnnonces[$i]['disponibilite'] = [];
                }
            }
        }
        //101
        if ($ville != '' && $date_debut == '' && $date_fin != '') {
            $conn = $this->entityManager->getConnection();

            $sql = 'SELECT wpUsers.id as id,wpUsers.userEmail as email,
            wpFirstName.meta_value as first_name, wpLastName.meta_value as last_name,
            wp.guid as avatar , wp.guid as titre, wp.guid as pays , wp.guid as ville, wp.guid as etat ,
            wp.guid as disponibilite, wp.guid as adresse, wp.post_content as competence,wp.id as postId,
            wptCategorie.name as categorieParent
            FROM wp_users wpUsers
            JOIN wp_posts wp ON wpUsers.id=wp.post_author
            JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            JOIN wp_terms wptCategorie ON wtt.parent=wptCategorie.term_id
            JOIN wp_usermeta wpFirstName ON wpUsers.id=wpFirstName.user_id
            JOIN wp_usermeta wpLastName ON wpUsers.id=wpLastName.user_id
            JOIN wp_usermeta wpRegion ON wpUsers.id=wpRegion.user_id
            LEFT JOIN wp_usermeta wpmImage ON wpUsers.id=wpmImage.user_id
            LEFT JOIN wp_usermeta wpCompetences ON wpUsers.id=wpCompetences.user_id
            WHERE wp.post_type =:categoriePost and wtt.term_taxonomy_id=:id_categorie
            and wpRegion.meta_key=:adresse and wpFirstName.meta_key=:first_name and wpLastName.meta_key=:last_name and wp.post_status=:postStatus
            and wpRegion.meta_value LIKE :ville
            GROUP BY id';

            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'adresse' => 'billing_city',
                'id_categorie' => $id,
                'postStatus' => 'publish',
                'categoriePost' => 'product',
                'first_name' => 'first_name',
                'last_name' => 'last_name',
                'ville' => '%' . $ville . '%',
            ]);
            $listeAnnonces = $stmt->fetchAllAssociative();

            for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
                //Adresse
                $listeAnnonces[$i]['pays'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'billing_country'
                );
                $listeAnnonces[$i]['ville'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'billing_city'
                );
                $listeAnnonces[$i]['etat'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'billing_state'
                );
                //Pour le portfolio
                $listeAnnonces[$i]['avatar'] = @unserialize(
                    $this->getUserStringDataValue(
                        $listeAnnonces[$i]['id'],
                        'basic_user_avatar'
                    )
                );
                $listeAnnonces[$i]['avatar'] = array_shift(
                    $listeAnnonces[$i]['avatar']
                );

                //Pour les competences
                $competence = $this->entityManager
                    ->getRepository(WpUsermeta::class)
                    ->findOneBy([
                        'userId' => $listeAnnonces[$i]['id'],
                        'metaKey' => 'competence',
                    ]);
                if ($competence) {
                    $listeAnnonces[$i]['competence'] = $competence;
                } else {
                    $listeAnnonces[$i]['competence'] = [];
                }
                //TITRE
                $listeAnnonces[$i]['titre'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'titre'
                );
                // Cout moyen
                $data = $this->entityManager
                    ->createQuery(
                        'SELECT wp,t.metaValue FROM App\Entity\WpPosts wp
                JOIN App\Entity\WpPostmeta t
                WHERE wp.postAuthor=:iduser and wp.id=t.postId and wp.postType=:postType
                and wp.postStatus=:postStatus and t.metaKey=:metaKey'
                    )
                    ->setParameters([
                        'iduser' => $listeAnnonces[$i]['id'],
                        'postType' => 'product',
                        'postStatus' => 'publish',
                        'metaKey' => '_price',
                    ])
                    ->getResult();
                $sommeTotale = 0;
                $sommeMoyenne = 0;
                for ($a = 0; $a < sizeof($data); $a++) {
                    $sommeTotale = $sommeTotale + $data[$a]['metaValue'];
                }
                if ($sommeTotale != 0) {
                    $sommeMoyenne = $sommeTotale / sizeof($data);
                }
                $listeAnnonces[$i]['sommeMoyenne'] = $sommeMoyenne;
                // Cout moyen
                $data = $this->entityManager
                    ->createQuery(
                        'SELECT wp FROM App\Entity\WpPosts wp
                JOIN App\Entity\WpPostmeta t
                WHERE wp.postAuthor=:iduser and wp.id=t.postId and wp.postType=:postType
                and wp.postStatus=:postStatus and t.metaKey=:metaKey'
                    )
                    ->setParameters([
                        'iduser' => $listeAnnonces[$i]['id'],
                        'postType' => 'product',
                        'postSatus' => 'publish',
                        'metakey' => '_price',
                    ])
                    ->getResult();
                $sommeTotale = 0;
                $sommeMoyenne = 0;
                for ($i; $i < sizeof($data); $i++) {
                    $sommeTotale = $sommeTotale + $data[$a]['metaValue'];
                }
                if ($sommeTotale != 0) {
                    $sommeMoyenne = $sommeTotale / sizeof($data);
                }
                $listeAnnonces[$i]['sommeMoyenne'] = $sommeMoyenne;
                //Pour la disponibilité
                $calendrier = $this->entityManager
                    ->getRepository(WpUsermeta::class)
                    ->findOneBy([
                        'userId' => $listeAnnonces[$i]['id'],
                        'metaKey' => 'disponibilite',
                    ]);
                if ($calendrier) {
                    $calendrier = explode(',', $calendrier->getMetaValue());
                    for ($k = 0; $k < sizeof($calendrier); $k++) {
                        if ($calendrier[$k] <= $date_fin) {
                            continue;
                        } else {
                            unset($listeAnnonces[$i]);
                        }
                    }
                    $listeAnnonces[$i]['disponibilite'] = $calendrier;
                } else {
                    $listeAnnonces[$i]['disponibilite'] = [];
                }
            }
        }
        //110
        if ($ville != '' && $date_debut != '') {
            $conn = $this->entityManager->getConnection();

            $sql = 'SELECT wpUsers.id as id,wpUsers.userEmail as email,
            wpFirstName.meta_value as first_name, wpLastName.meta_value as last_name,
            wp.guid as avatar , wp.guid as titre, wp.guid as pays , wp.guid as ville, wp.guid as etat ,
            wp.guid as disponibilite, wp.guid as adresse, wp.post_content as competence,wp.id as postId,
            wptCategorie.name as categorieParent
            FROM wp_users wpUsers
            JOIN wp_posts wp ON wpUsers.id=wp.post_author
            JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            JOIN wp_terms wptCategorie ON wtt.parent=wptCategorie.term_id
            JOIN wp_usermeta wpFirstName ON wpUsers.id=wpFirstName.user_id
            JOIN wp_usermeta wpLastName ON wpUsers.id=wpLastName.user_id
            JOIN wp_usermeta wpRegion ON wpUsers.id=wpRegion.user_id
            LEFT JOIN wp_usermeta wpmImage ON wpUsers.id=wpmImage.user_id
            LEFT JOIN wp_usermeta wpCompetences ON wpUsers.id=wpCompetences.user_id
            WHERE wp.post_type =:categoriePost and wtt.term_taxonomy_id=:id_categorie
            and wpRegion.meta_key=:adresse and wpFirstName.meta_key=:first_name and wpLastName.meta_key=:last_name and wp.post_status=:postStatus
            and wpRegion.meta_value LIKE :ville
            GROUP BY id';

            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'adresse' => 'billing_city',
                'id_categorie' => $id,
                'postStatus' => 'publish',
                'categoriePost' => 'product',
                'first_name' => 'first_name',
                'last_name' => 'last_name',
                'ville' => '%' . $ville . '%',
            ]);
            $listeAnnonces = $stmt->fetchAllAssociative();

            for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
                //Adresse
                $listeAnnonces[$i]['pays'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'billing_country'
                );
                $listeAnnonces[$i]['ville'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'billing_city'
                );
                $listeAnnonces[$i]['etat'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'billing_state'
                );
                //Pour le portfolio
                $listeAnnonces[$i]['avatar'] = @unserialize(
                    $this->getUserStringDataValue(
                        $listeAnnonces[$i]['id'],
                        'basic_user_avatar'
                    )
                );
                $listeAnnonces[$i]['avatar'] = array_shift(
                    $listeAnnonces[$i]['avatar']
                );
                //Pour les competences
                $competence = $this->entityManager
                    ->getRepository(WpUsermeta::class)
                    ->findOneBy([
                        'userId' => $listeAnnonces[$i]['id'],
                        'metaKey' => 'competence',
                    ]);
                if ($competence) {
                    $listeAnnonces[$i]['competence'] = $competence;
                } else {
                    $listeAnnonces[$i]['competence'] = [];
                }
                //TITRE
                $listeAnnonces[$i]['titre'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'titre'
                );
                // Cout moyen
                $data = $this->entityManager
                    ->createQuery(
                        'SELECT wp,t.metaValue FROM App\Entity\WpPosts wp
                JOIN App\Entity\WpPostmeta t
                WHERE wp.postAuthor=:iduser and wp.id=t.postId and wp.postType=:postType
                and wp.postStatus=:postStatus and t.metaKey=:metaKey'
                    )
                    ->setParameters([
                        'iduser' => $listeAnnonces[$i]['id'],
                        'postType' => 'product',
                        'postStatus' => 'publish',
                        'metaKey' => '_price',
                    ])
                    ->getResult();
                $sommeTotale = 0;
                $sommeMoyenne = 0;
                for ($a = 0; $a < sizeof($data); $a++) {
                    $sommeTotale = $sommeTotale + $data[$a]['metaValue'];
                }
                if ($sommeTotale != 0) {
                    $sommeMoyenne = $sommeTotale / sizeof($data);
                }
                $listeAnnonces[$i]['sommeMoyenne'] = $sommeMoyenne;
                // Cout moyen
                $data = $this->entityManager
                    ->createQuery(
                        'SELECT wp FROM App\Entity\WpPosts wp
                JOIN App\Entity\WpPostmeta t
                WHERE wp.postAuthor=:iduser and wp.id=t.postId and wp.postType=:postType
                and wp.postStatus=:postStatus and t.metaKey=:metaKey'
                    )
                    ->setParameters([
                        'iduser' => $listeAnnonces[$i]['id'],
                        'postType' => 'product',
                        'postSatus' => 'publish',
                        'metakey' => '_price',
                    ])
                    ->getResult();
                $sommeTotale = 0;
                $sommeMoyenne = 0;
                for ($i; $i < sizeof($data); $i++) {
                    $sommeTotale = $sommeTotale + $data[$a]['metaValue'];
                }
                if ($sommeTotale != 0) {
                    $sommeMoyenne = $sommeTotale / sizeof($data);
                }
                $listeAnnonces[$i]['sommeMoyenne'] = $sommeMoyenne;
                //Pour la disponibilité
                $calendrier = $this->entityManager
                    ->getRepository(WpUsermeta::class)
                    ->findOneBy([
                        'userId' => $listeAnnonces[$i]['id'],
                        'metaKey' => 'disponibilite',
                    ]);
                if ($calendrier) {
                    $calendrier = explode(',', $calendrier->getMetaValue());
                    for ($k = 0; $k < sizeof($calendrier); $k++) {
                        if ($calendrier[$k] >= $date_debut) {
                            continue;
                        } else {
                            unset($listeAnnonces[$i]);
                        }
                    }
                    $listeAnnonces[$i]['disponibilite'] = $calendrier;
                } else {
                    $listeAnnonces[$i]['disponibilite'] = [];
                }
            }
        }
        //111
        if ($ville != '' && $date_debut != '' && $date_fin != '') {
            $conn = $this->entityManager->getConnection();

            $sql = 'SELECT wpUsers.id as id,wpUsers.userEmail as email,
            wpFirstName.meta_value as first_name, wpLastName.meta_value as last_name,
            wp.guid as avatar , wp.guid as titre, wp.guid as pays , wp.guid as ville, wp.guid as etat ,
            wp.guid as disponibilite, wp.guid as adresse, wp.post_content as competence,wp.id as postId,
            wptCategorie.name as categorieParent, wp.guid as sommeMoyenne
            FROM wp_users wpUsers
            JOIN wp_posts wp ON wpUsers.id=wp.post_author
            JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            JOIN wp_terms wptCategorie ON wtt.parent=wptCategorie.term_id
            JOIN wp_usermeta wpFirstName ON wpUsers.id=wpFirstName.user_id
            JOIN wp_usermeta wpLastName ON wpUsers.id=wpLastName.user_id
            JOIN wp_usermeta wpRegion ON wpUsers.id=wpRegion.user_id
            LEFT JOIN wp_usermeta wpmImage ON wpUsers.id=wpmImage.user_id
            LEFT JOIN wp_usermeta wpCompetences ON wpUsers.id=wpCompetences.user_id
            WHERE wp.post_type =:categoriePost and wtt.term_taxonomy_id=:id_categorie
            and wpRegion.meta_key=:adresse and wpFirstName.meta_key=:first_name and wpLastName.meta_key=:last_name and wp.post_status=:postStatus
            and wpRegion.meta_value LIKE :ville
            GROUP BY id';

            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'adresse' => 'billing_city',
                'id_categorie' => $id,
                'postStatus' => 'publish',
                'categoriePost' => 'product',
                'first_name' => 'first_name',
                'last_name' => 'last_name',
                'ville' => '%' . $ville . '%',
            ]);
            $listeAnnonces = $stmt->fetchAllAssociative();

            for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
                //Adresse
                $listeAnnonces[$i]['pays'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'billing_country'
                );
                $listeAnnonces[$i]['ville'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'billing_city'
                );
                $listeAnnonces[$i]['etat'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'billing_state'
                );
                //Pour le portfolio
                $listeAnnonces[$i]['avatar'] = @unserialize(
                    $this->getUserStringDataValue(
                        $listeAnnonces[$i]['id'],
                        'basic_user_avatar'
                    )
                );
                $listeAnnonces[$i]['avatar'] = array_shift(
                    $listeAnnonces[$i]['avatar']
                );
                //Pour les competences
                $competence = $this->entityManager
                    ->getRepository(WpUsermeta::class)
                    ->findOneBy([
                        'userId' => $listeAnnonces[$i]['id'],
                        'metaKey' => 'competence',
                    ]);
                if ($competence) {
                    $listeAnnonces[$i]['competence'] = $competence;
                } else {
                    $listeAnnonces[$i]['competence'] = [];
                }
                //TITRE
                $listeAnnonces[$i]['titre'] = $this->getUserStringDataValue(
                    $listeAnnonces[$i]['id'],
                    'titre'
                );
                // Cout moyen
                $data = $this->entityManager
                    ->createQuery(
                        'SELECT wp,t.metaValue FROM App\Entity\WpPosts wp
                 JOIN App\Entity\WpPostmeta t
                 WHERE wp.postAuthor=:iduser and wp.id=t.postId and wp.postType=:postType
                 and wp.postStatus=:postStatus and t.metaKey=:metaKey'
                    )
                    ->setParameters([
                        'iduser' => $listeAnnonces[$i]['id'],
                        'postType' => 'product',
                        'postStatus' => 'publish',
                        'metaKey' => '_price',
                    ])
                    ->getResult();
                $sommeTotale = 0;
                $sommeMoyenne = 0;
                for ($a = 0; $a < sizeof($data); $a++) {
                    $sommeTotale = $sommeTotale + $data[$a]['metaValue'];
                }
                if ($sommeTotale != 0) {
                    $sommeMoyenne = $sommeTotale / sizeof($data);
                }
                $listeAnnonces[$i]['sommeMoyenne'] = $sommeMoyenne;

                //Pour la disponibilité
                $calendrier = $this->entityManager
                    ->getRepository(WpUsermeta::class)
                    ->findOneBy([
                        'userId' => $listeAnnonces[$i]['id'],
                        'metaKey' => 'disponibilite',
                    ]);
                if ($calendrier) {
                    $calendrier = explode(',', $calendrier->getMetaValue());
                    for ($k = 0; $k < sizeof($calendrier); $k++) {
                        if (
                            $calendrier[$k] <= $date_fin &&
                            $calendrier[$k] >= $date_debut
                        ) {
                            continue;
                        } else {
                            unset($listeAnnonces[$i]);
                        }
                    }
                    $listeAnnonces[$i]['disponibilite'] = $calendrier;
                } else {
                    $listeAnnonces[$i]['disponibilite'] = [];
                }
            }
        }

        return $listeAnnonces;
    }

    // Récupérer les annonces:

    /**
     * @param $tag
     * @return null|array
     */
    public function readPostsAnnonces($tag)
    {
        $query = $this->entityManager
            ->createQuery(
                'SELECT wp.id,wp.postDate,wp.postTitle,wp.postStatus,wtt.name,wpm.metaValue as price,
        wpm2.metaValue as imagePost
         FROM App\Entity\WpPosts wp
         JOIN App\Entity\WpTermRelationships wtr WHERE wp.id=wtr.objectId
         JOIN App\Entity\WpTermTaxonomy wtt WHERE wtr.termTaxonomyId=wtt.termTaxonomyId
         JOIN App\Entity\WpTerms wts WHERE wtt.termId=wts.termId
         JOIN App\Entity\WpPostmeta wpm WHERE wp.id=wpm.postId
         JOIN App\Entity\WpPostmeta wpm2 WHERE wp.id=wpm.postId
         WHERE wp.post_type=:postType and wts.name LIKE :tag
            and wtt.taxonomy=:productCat and wpm.metaKey =:price and wpm2.metaKey=:imagePost'
            )
            ->setParameter('postType', 'product')
            ->setParameter('tag', '%' . $tag . '%')
            ->setParameter('productCat', 'product_cat')
            ->setParameter('price', '%' . $tag . '%')
            ->setParameter('imagePost', '_product_image_gallery');

        $annonces = $query->getResult();

        return $annonces;
    }

    // End posts

    // CRUD PostMeta ou ensemble des données supplémentaires liées aux Posts

    /**
     * @param $postId
     * @param $metaKey
     * @param $metaValue
     * @return null|string
     */
    public function createPostMeta($postId, $metaKey, $metaValue, $locale)
    {
        $postmeta = new WpPostmeta();

        $postmeta->setMetaKey($metaKey);
        $postmeta->setMetaValue($metaValue);
        /* $postmeta->setTranslatableLocale($locale); */
        $postmeta->setPostId($postId);

        $this->entityManager->persist($postmeta);
        $this->entityManager->flush();

        return $postmeta->getMetaId();
    }
    // lire un post meta precis
    /**
     * @param $postId
     * @param $key
     */
    public function readPostMeta($postId, $key)
    {
        $postmeta = $this->entityManager
            ->getRepository(WpPostmeta::class)
            ->findOneBy([
                'postId' => $postId,
                'metaKey' => $key,
            ]);

        return $postmeta;
    }

    /**
     * @param $postId
     * @param $key
     */
    public function readAllPostMeta($postId, $key)
    {
        $postmeta = $this->entityManager
            ->getRepository(WpPostmeta::class)
            ->findBy([
                'postId' => $postId,
                'metaKey' => $key,
            ]);
        $query = $this->entityManager->createQuery(
            'SELECT p
                FROM App\Entity\WpPostmeta p
                WHERE p.postId =:postId and p.metaKey =:metaKey
                ORDER BY p.metaId DESC'
        )
            ->setParameter('postId', $postId)
            ->setParameter('metaKey', $key);

        // returns an array of Product objects
        return $query->getArrayResult();

        return $postmeta;
    }

    /**
     * @param $id
     * @param $postId
     * @param $metaKey
     * @param $metaValue
     * @return array|Response
     */
    public function updatePostMeta($id, $postId, $metaKey, $metaValue, $locale)
    {
        $postmeta = $this->entityManager
            ->getRepository(WpPostmeta::class)
            ->find($id);

        if (!$postmeta) {
            $postmeta = new WpPostmeta();
        }

        $postmeta->setMetaKey($metaKey);
        $postmeta->setMetaValue($metaValue);
        /* $postmeta->setTranslatableLocale($locale); */
        $postmeta->setPostId($postId);

        $this->entityManager->flush();

        return $postmeta->getMetaId();
    }

    /**
     * @param $id
     * @return array|Response
     */
    public function deletePostMeta($id)
    {
        $postmeta = $this->entityManager
            ->getRepository(WpPostmeta::class)
            ->find($id);
        if (!$postmeta) {
            return ['response' => false];
        }

        $this->entityManager->remove($postmeta);
        $this->entityManager->flush();

        return new Response('OK');
    }

    /**
     * @param $id
     * @return array|Response
     */
    public function deletePost($id)
    {
        $p = $this->entityManager->getRepository(WpPosts::class)->find($id);
        $p->setPostStatus('trash');

        $this->entityManager->persist($p);
        $this->entityManager->flush();

        return $p;
    }

    //Authentications

    //UserMeta

    //Create
    /**
     * @param $userId
     * @param $metaKey
     * @param $metaValue
     */
    public function createUserMeta($userId, $metaKey, $metaValue)
    {
        $additionnalData = new WpUsermeta();
        $additionnalData->setMetaKey($metaKey);
        $additionnalData->setMetaValue($metaValue);
        //  $additionnalData->setTranslatableLocale($locale);
        $additionnalData->setUserId($userId);

        $this->entityManager->persist($additionnalData);
        $this->entityManager->flush();
        return $additionnalData;
    }

    //Update
    /**
     * @param $id
     * @param $metaKey
     * @param $metaValue
     * @return int
     */
    public function updateUserMeta($id, $metaKey, $metaValue)
    {
        $query = $this->entityManager
            ->createQuery('UPDATE App\Entity\WpUsermeta p SET p.metaValue=:pc WHERE p.userId=:id and p.metaKey=:mk')
            ->setParameter('id', $id)
            ->setParameter('mk', $metaKey)
            ->setParameter('pc', $metaValue);
        $r = $query->getResult();
        if ($r == 0) {
            $u = $this->readUserMeta($id, $metaKey);
            if (!$u && $metaValue) {
                $this->createUserMeta1($id, $metaKey, $metaValue);
            }
        }

        /* if ($r == 1) {
            $u = $this->readUserMeta($id, $metaKey);
            $query1 = $this->entityManager
                ->createQuery('UPDATE App\Entity\Translation\WpUsermetaTranslation p SET p.content=:pc WHERE p.foreign_key=:id')
                ->setParameter('id', $u->getUmetaId())
                ->setParameter('pc', $metaValue);
            $r1 = $query1->getResult();
        } */
        return $r;
    }

    /**
     * @return mixed
     */
    public function viderCommande()
    {
        $query = $this->entityManager
            ->createQuery(
                'DELETE FROM App\Entity\WpPosts p
             WHERE p.postStatus =:s1 or p.postStatus =:s2 or p.postStatus =:s3 or p.postStatus =:s4 or p.postStatus =:s5'
            )
            ->setParameter('s1', 'wc-pending-trash')
            ->setParameter('s2', 'wc-completed-trash')
            ->setParameter('s3', 'wc-in-progress-trash')
            ->setParameter('s4', 'wc-cancelled-trash')
            ->setParameter('s5', 'wc-failed-trash');
        $r = $query->getResult();
        return $r;
    }

    //Update User meta where double datas can exist
    /**
     * @param $id
     * @param $metaKey
     * @param $metaValue
     * @return int
     */
    public function updateUserMetaWithDouble($id, $metaKey, $metaValue)
    {
        $conn = $this->entityManager->getConnection();
        $sql1 = 'DELETE FROM wp_usermeta WHERE wp_usermeta.meta_key LIKE :id';

        $stmt = $conn->prepare($sql1);
        $stmt->executeQuery(['id' => '%' . $metaKey . '%']);

        $u = $this->createUserMeta1($id, $metaKey, $metaValue);

        return $u;
    }

    //Read userMeta with key and userId
    /**
     * @param $userId
     * @param $metaKey
     * @return array|null|object
     */
    public function readUserMeta($userId, $metaKey)
    {
        $usermeta = $this->entityManager
            ->getRepository(WpUsermeta::class)
            ->findOneBy([
                'userId' => $userId,
                'metaKey' => $metaKey,
            ]);

        return $usermeta;
    }

    /**
     * @param $userId
     * @param $metaKey
     * @return array|null
     */
    public function readListUserMeta($userId, $metaKey)
    {
        $datas = $this->entityManager
            ->createQuery('SELECT um.metaValue FROM App\Entity\WpUsermeta um 
            WHERE um.userId=:userId AND um.metaKey=:metaKey')
            ->setParameters(['userId' => $userId, 'metaKey' => $metaKey])
            ->getResult();
        $response = [];
        foreach ($datas as $data) {
            $response[] = $data['metaValue'];
        }
        return $response;
    }

    /**
     * Read usermeta by all key
     * @param $userId
     * @param $metaKey
     * @return null|object
     */
    public function readUserMetaByAllKey($userId, $metaKey,$metaValue)
    {
        $usermeta = $this->entityManager
            ->getRepository(WpUsermeta::class)
            ->findOneBy([
                'userId' => $userId,
                'metaKey' => $metaKey,
                'metaValue' => $metaValue,
            ]);

        return $usermeta;
    }

    /**
     * @param $userId
     * @param $metaKey
     * @return array|null|object
     */
    public function readUserMeta1($userId)
    {
        $usermeta = $this->entityManager
            ->getRepository(WpUsermeta::class)
            ->findBy(['userId' => $userId]);

        return $usermeta;
    }

    // RECUPERATION DE TOUTES LES DONNEES D'une ANNONCE
    /**
     * @param $postId
     * @return int
     */
    public function readAllAnnonceData($postId)
    {
        $conn = $this->entityManager->getConnection();

        $sql = 'SELECT wp.post_title,wp.post_author,wp.id,wp.post_date, wp.post_modified, wp.post_content,wp.post_status,wp.post_type,
            wp.post_name as slug, wtt.term_id as categorie_parent ,wpSousCategorie.term_id as IdSousCategorie,
            wpSousCategorie.name as sousCategorie, 
            wp.post_content as avatar, wp.post_content as email, wptCategorie.name as categorieParent, wptCategorie.name as categorieParentType,
            null as prix, wp.guid as devise,wp.guid as titre_en,wp.guid as content_en, wp.guid as calendrier, wp.guid as first_name,
            wp.guid as last_name, wp.guid as pays, wp.guid as videos, wp.guid as commentaires, wp.guid as ville,
            wp.guid as etat, wp.guid as codePostal, wp.guid as gallery, wtr.term_taxonomy_id, wtt.parent,
            wp.post_title as categorie_parent_en, wp.post_title as categorie_en, wpSousCategorie.term_id as idSousCat,
            wtt.parent as idCat, wp.post_content as images_secondaires, wp.post_content as societe,
            wpSousCategorie.slug as sousCategorieSlug,wptCategorie.slug as categorieParentSlug
            FROM wp_posts wp
            JOIN wp_users wus ON wp.post_author=wus.id
            JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
            JOIN wp_term_taxonomy wtt2 ON wtt.parent=wtt2.term_taxonomy_id
            JOIN wp_terms wptCategorie ON wtt2.term_id=wptCategorie.term_id
            WHERE wp.id =:id';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery(['id' => $postId]);

        $data = $stmt->fetch();
        //dd($data);
        if ($data) {
            //price
            $data['prix'] = trim($this->getPostStringDataValue($postId, '_price'));
            //Nom
            $data['first_name'] = $this->getUserStringDataValue(
                $data['post_author'],
                'first_name'
            );
            //Prenom
            $data['last_name'] = $this->getUserStringDataValue(
                $data['post_author'],
                'last_name'
            );
            //Avatar
            $data['avatar'] = @unserialize(
                $this->getUserStringDataValue(
                    $data['post_author'],
                    'basic_user_avatar'
                )
            );
            //Prenom
            $data['societe'] = $this->getUserStringDataValue(
                $data['post_author'],
                'billing_company'
            );
            //Adresse
            $data['pays'] = $this->getPostStringDataValue(
                $data["id"],
                '_product_country'
            );
            $data['ville'] = $this->getPostStringDataValue(
                $data["id"],
                '_product_city'
            );
            $data['etat'] = $this->getPostStringDataValue(
                $data["id"],
                '_product_adress'
            );
            $data['codePostal'] = $this->getPostStringDataValue(
                $data["id"],
                '_product_code_postal'
            );

            if ($data['pays'] != '') {
                $data['ville'] = str_replace(
                    $data['pays'],
                    '',
                    $this->getPostStringDataValue($data["id"], '_product_city')
                );
                //Adresse Post
                $data['etat'] = str_replace(
                    $data['pays'],
                    '',
                    $this->getPostStringDataValue(
                        $data["id"],
                        '_product_adress'
                    )
                );
            }

            $data['email'] = $this->entityManager
                ->getRepository(User::class)
                ->find($data['post_author'])
                ->getEmailCanonical();
            //calendrier

            $calendrier = $this->entityManager
                ->getRepository(WpUsermeta::class)
                ->findOneBy([
                    'userId' => $data['post_author'],
                    'metaKey' => 'disponibilite',
                ]);
            if ($calendrier) {
                $data['calendrier'] = explode(',', $calendrier->getMetaValue());
            } else {
                $data['calendrier'] = [];
            }

            //Videos

            $video = [];
            $imgid = [];
            $vid = $this->getPostStringDataValue($data["id"], '_product_video');
            if ($vid != '') {
                $video = @unserialize($vid);
                for ($j = 0; $j < sizeof($video); $j++) {
                    $imgid[$j] = $this->getYouTubeId($video[$j]);
                }
                $data['videos'] = $imgid;
            }

            $data['videos'] = $imgid;

            //Commentaires
            $conn = $this->entityManager->getConnection();

            $lesCommentaires =
                'SELECT wp.*,wp.comment_author as avatar FROM wp_comments wp WHERE wp.comment_post_ID =:id and wp.comment_approved =:etat';

            $stmt = $conn->prepare($lesCommentaires);
            $stmt = $stmt->executeQuery(['id' => $data["id"], 'etat' => 1]);

            $dataComments = $stmt->fetchAllAssociative();
            for ($s = 0; $s < sizeof($dataComments); $s++) {
                $dataComments[$s]['avatar'] = @unserialize(
                    $this->getUserStringDataValue(
                        $data['post_author'],
                        'basic_user_avatar'
                    )
                );
            }

            $data['commentaires'] = $dataComments;
            //Devises
            $data['devise'] = $this->entityManager
                ->getRepository(wpPostmeta::class)
                ->findOneBy([
                    'postId' => $data["id"],
                    'metaKey' => '_product_devise',
                ]);
            //Images
            $data['gallery'] = $this->getAllImageForAnnouces($data["id"]);
            //Images secondaires
            $data['images_secondaires'] = $this->getAllSecondImageForAnnouces(
                $data["id"],
                'images_annonces'
            );
            //Titre En
            $data['titre_en'] = '';
            $titre_en = $this->entityManager
                ->getRepository(ExtTranslationWpPosts::class)
                ->findOneBy([
                    'foreign_key' => $data["id"],
                    'field' => 'postExcerpt',
                    'locale' => 'en',
                ]);
            if ($titre_en) {
                $data['titre_en'] = $titre_en->getContent();
            }
            //Store category parent type
            $data['categorieParentType'] = $this->getProductParentCategory(
                $postId
            );
            //End Store category parent type
            //Contenu En
            $data['content_en'] = '';
            $content_en = $this->entityManager
                ->getRepository(ExtTranslationWpPosts::class)
                ->findOneBy([
                    'foreign_key' => $data["id"],
                    'field' => 'postContentFiltered',
                    'locale' => 'en',
                ]);
            if ($content_en) {
                $data['content_en'] = $content_en->getContent();
            }

            $data['categorie_en'] = '';
            $data['categorie_parent_en'] = '';

            $cat_en = $this->entityManager
                ->getRepository(ExtTranslationWpTerms::class)
                ->findOneBy([
                    'foreign_key' => $data['idCat'],
                    'field' => 'name',
                    'locale' => 'en_US',
                ]);
            if ($cat_en) {
                $data['categorie_en'] = $cat_en->getContent();
            }

            $sous_cat_en = $this->entityManager
                ->getRepository(ExtTranslationWpTerms::class)
                ->findOneBy([
                    'foreign_key' => $data['idSousCat'],
                    'field' => 'name',
                    'locale' => 'en_US',
                ]);
            if ($sous_cat_en) {
                $data['categorie_parent_en'] = $sous_cat_en->getContent();
            }

            return $data;
        } else {
            $data = [];
        }

        return $data;
        // FIN RECUPERATION
    }

    /**
     * @param $postId
     * @return int
     */
    public function readPartialAnnonceData($postId)
    {
        $conn = $this->entityManager->getConnection();

        $sql = 'SELECT wp.post_title,wp.post_author,wp.id,wp.post_date, wp.post_modified, wp.post_content,wp.post_status,wp.post_type,
            wp.post_name as slug, wtt.term_id as categorie_parent ,wpSousCategorie.term_id as IdSousCategorie,
            wpSousCategorie.name as sousCategorie, 
            wp.post_content as avatar, wp.post_content as email, wptCategorie.name as categorieParent, wptCategorie.name as categorieParentType,
            null as prix, wp.guid as devise,wp.guid as titre_en,wp.guid as content_en, wp.guid as calendrier, wp.guid as first_name,
            wp.guid as last_name, wp.guid as pays, wp.guid as videos, wp.guid as commentaires, wp.guid as ville,
            wp.guid as etat, wp.guid as codePostal, wp.guid as gallery, wtr.term_taxonomy_id, wtt.parent,
            wp.post_title as categorie_parent_en, wp.post_title as categorie_en, wpSousCategorie.term_id as idSousCat,
            wtt.parent as idCat, wp.post_content as images_secondaires, wp.post_content as societe,
            wpSousCategorie.slug as sousCategorieSlug,wptCategorie.slug as categorieParentSlug
            FROM wp_posts wp
            JOIN wp_users wus ON wp.post_author=wus.id
            JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
            JOIN wp_term_taxonomy wtt2 ON wtt.parent=wtt2.term_taxonomy_id
            JOIN wp_terms wptCategorie ON wtt2.term_id=wptCategorie.term_id
            WHERE wp.id =:id';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery(['id' => $postId]);

        $data = $stmt->fetch();

        return $data;
        // FIN RECUPERATION
    }

    /**
     * @param $postName
     * @return int
     * @throws Exception
     */
    public function readAllAnnonceDataByName($postName)
    {
        $conn = $this->entityManager->getConnection();

        $sql = 'SELECT wp.post_title,wp.post_author,wp.id,wp.post_date, wp.post_modified, wp.post_content,wp.post_status,wp.post_type,wp.post_name,
        wtt.term_id as categorie_parent ,wpSousCategorie.term_id as IdSousCategorie, wpSousCategorie.name as sousCategorie, wp.post_name as slug,
        wp.post_content as avatar, wp.post_content as email, wptCategorie.name as categorieParent, wptCategorie.name as categorieParentType,
        wp.guid as prix, null as devise,null as titre_en,null as content_en, null as calendrier, null as first_name,
        null as last_name, null as nom_commercial, null as pays, null as videos, null as commentaires, null as ville,
        null as etat, null as codePostal, null as gallery, wtr.term_taxonomy_id, wtt.parent,
        wp.post_title as categorie_parent_en, wp.post_title as categorie_en, wpSousCategorie.term_id as idSousCat,
        wtt.parent as idCat, null as images_secondaires, null as societe,
        wpSousCategorie.slug as sousCategorieSlug, wpSousCategorie.term_price as livraison,wptCategorie.slug as categorieParentSlug, wus.email_canonical as email
        FROM wp_posts wp
        JOIN wp_users wus ON wp.post_author=wus.id
        JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
        JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
        JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
        JOIN wp_term_taxonomy wtt2 ON wtt.parent=wtt2.term_taxonomy_id
        JOIN wp_terms wptCategorie ON wtt2.term_id=wptCategorie.term_id
        WHERE wp.post_name =:id
        LIMIT 1';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery(['id' => $postName]);

        $data = $stmt->fetch();

        if ($data != null) {
            //price
            $data['prix'] = str_replace(
                ' ',
                '',
                $this->getPostStringDataValue($data["id"], '_price')
            );
            //Nom
            $data['first_name'] = $this->getUserStringDataValue(
                $data['post_author'],
                'first_name'
            );
            //Prenom
            $data['last_name'] = $this->getUserStringDataValue(
                $data['post_author'],
                'last_name'
            );
            //Prenom
            $data['nom_commercial'] = $this->getUserStringDataValue(
                $data['post_author'],
                'nom_commercial'
            );
            if($data['nom_commercial'] == '')
                $data['nom_commercial'] = $data['first_name'];
            //Avatar
            $data['avatar'] = @unserialize(
                $this->getUserStringDataValue(
                    $data['post_author'],
                    'basic_user_avatar'
                )
            );
            //Prenom
            $data['societe'] = $this->getUserStringDataValue(
                $data['post_author'],
                'billing_company'
            );
            //Adresse
            $data['pays'] = $this->getPostStringDataValue(
                $data["id"],
                '_product_country'
            );
            $data['ville'] = $this->getPostStringDataValue(
                $data["id"],
                '_product_city'
            );
            $data['etat'] = $this->getPostStringDataValue(
                $data["id"],
                '_product_adress'
            );
            $data['codePostal'] = $this->getPostStringDataValue(
                $data["id"],
                '_product_code_postal'
            );

            if ($data['pays'] != '') {
                $data['ville'] = str_replace(
                    $data['pays'],
                    '',
                    $this->getPostStringDataValue($data["id"], '_product_city')
                );
                //Adresse Post
                $data['etat'] = str_replace(
                    $data['pays'],
                    '',
                    $this->getPostStringDataValue(
                        $data["id"],
                        '_product_adress'
                    )
                );
            }

            $data['email'] = $this->entityManager
                ->getRepository(User::class)
                ->find($data['post_author'])
                ->getEmailCanonical();
            //calendrier

            $calendrier = $this->entityManager
                ->getRepository(WpUsermeta::class)
                ->findOneBy([
                    'userId' => $data['post_author'],
                    'metaKey' => 'disponibilite',
                ]);
            if ($calendrier) {
                $data['calendrier'] = explode(',', $calendrier->getMetaValue());
            } else {
                $data['calendrier'] = [];
            }
            //Videos
            $video = [];
            $imgid = [];
            $vid = $this->getPostStringDataValue($data["id"], '_product_video');
            if ($vid != '') {
                $video = @unserialize($vid);
                for ($j = 0; $j < sizeof($video); $j++) {
                    $imgid[$j] = $this->getYouTubeId($video[$j]);
                }
                $data['videos'] = $imgid;
            }

            $data['videos'] = $imgid;

            //Livraison
            $data['livraison'] = ($data['livraison'] == 'oui') ? 'oui' : 'non' ;
            //Fin livraison

            //Commentaires
            $conn = $this->entityManager->getConnection();

            $lesCommentaires = 'SELECT wp.*,wp.comment_author as avatar
             FROM wp_comments wp
             WHERE wp.comment_post_ID =:id and wp.comment_approved =:etat';

            $stmt = $conn->prepare($lesCommentaires);
            $stmt = $stmt->executeQuery(['id' => $data["id"], 'etat' => 1]);

            $dataComments = $stmt->fetchAllAssociative();
            for ($s = 0; $s < sizeof($dataComments); $s++) {
                $dataComments[$s]['avatar'] = @unserialize(
                    $this->getUserStringDataValue(
                        $data['post_author'],
                        'basic_user_avatar'
                    )
                );
            }

            $data['commentaires'] = $dataComments;
            //Devises
            $data['devise'] = $this->entityManager
                ->getRepository(wpPostmeta::class)
                ->findOneBy([
                    'postId' => $data["id"],
                    'metaKey' => '_product_devise',
                ]);
            //Images
            $data['gallery'] = $this->getAllImageForAnnouces($data["id"]);
            //Images secondaires
            $data['images_secondaires'] = $this->getAllSecondImageForAnnouces(
                $data["id"],
                'images_annonces'
            );
            //Titre En
            $data['titre_en'] = '';
            $titre_en = $this->entityManager
                ->getRepository(ExtTranslationWpPosts::class)
                ->findOneBy([
                    'foreign_key' => $data["id"],
                    'field' => 'postExcerpt',
                    'locale' => 'en',
                ]);
            if ($titre_en) {
                $data['titre_en'] = $titre_en->getContent();
            }
            //Store category parent type
            $data['categorieParentType'] = $this->getProductParentCategory(
                $data["id"]
            );
            //End Store category parent type
            //Contenu En
            $data['content_en'] = '';
            $content_en = $this->entityManager
                ->getRepository(ExtTranslationWpPosts::class)
                ->findOneBy([
                    'foreign_key' => $data["id"],
                    'field' => 'postContentFiltered',
                    'locale' => 'en',
                ]);
            if ($content_en) {
                $data['content_en'] = $content_en->getContent();
            }

            $data['categorie_en'] = '';
            $data['categorie_parent_en'] = '';

            $cat_en = $this->entityManager
                ->getRepository(ExtTranslationWpTerms::class)
                ->findOneBy([
                    'foreign_key' => $data['idCat'],
                    'field' => 'name',
                    'locale' => 'en_US',
                ]);
            if ($cat_en) {
                $data['categorie_en'] = $cat_en->getContent();
            }

            $sous_cat_en = $this->entityManager
                ->getRepository(ExtTranslationWpTerms::class)
                ->findOneBy([
                    'foreign_key' => $data['idSousCat'],
                    'field' => 'name',
                    'locale' => 'en_US',
                ]);
            if ($sous_cat_en) {
                $data['categorie_parent_en'] = $sous_cat_en->getContent();
            }
            return $data;
        }
        return $data;
        // FIN RECUPERATION
    }

    /**
     * @param $id
     * @return array|null|object
     */
    public function readTermRelation($id)
    {
        $post = $this->entityManager
            ->getRepository(WpTermRelationships::class)
            ->findOneByObjectId($id);
        return $post;
    }

    /**
     * @param $file
     * @param $postId
     * @return string
     */
    public function updateArticle(
        $name,
        $article,
        $id,
        $status,
        $categorie,
        $locale = null
    ) {
        $p = $this->entityManager
            ->getRepository(WpPosts::class)
            ->findOneById($id);
        if ($p) {
            if ($locale == 'en') {
                $p->setPostContentFiltered($article);
                $p->setPostExcerpt($name);
            } else {
                $p->setPostTitle($name);
                $p->setPostContent($article);
            }
            $p->setPostStatus($status);
            $p->setPostName($this->slugify($name));
            $this->entityManager->persist($p);
            $this->entityManager->flush();
            $c = $this->readTermRelation($id);
            $c->setTermTaxonomyId($categorie);

            $this->entityManager->persist($c);
            $this->entityManager->flush();
        }

        return $p->getid();
    }

    /**
     * @param $id
     * @return null|object
     */
    public function postParentImage($id)
    {
        return $this->entityManager
            ->getRepository(WpPosts::class)
            ->findOneBy(['postParent' => $id, 'postType' => 'attachment']);
    }

    // Fontion qui permet d'uploder les Fichiers
    /**
     * @param $file
     * @param $postId
     * @return string
     */
    public function updateUpload(
        $file,
        $destination,
        $postId,
        $postParent,
        $postAuthor
    ) {
        /** @var Symfony\Component\HttpFoundation\File\UploadedFile $file */

        $ids = '';
        $date = new DateTime();
        if (!empty($file[0])) {
            for ($i = 0; $i < sizeof($file); $i++) {
                if (!empty($file[$i])) {
                    $fileName =
                        $postAuthor .
                        md5(uniqid()) .
                        '.' .
                        $file[$i]->guessExtension();
                    // moves the file to the directory where brochures are stored
                    $ext = $file[$i]->guessExtension();
                    $file[$i]->move($destination, $fileName);
                    //End upload file
                    //Compression
                    if (filesize($destination . '/' . $fileName) > 200 * 1024) {
                        // moves the compress file to the directory where brochures are stored
                        $compress_file = 'cmp_' . $fileName;
                        $compress_image = $this->compressImage(
                            $destination . '/' . $fileName,
                            $destination . '/' . $compress_file,
                            50,
                            4
                        );
                        unlink($destination . '/' . $fileName);
                        $fileName = $compress_file;
                        //End move file
                    }
                    //End compression
                    $p = $this->entityManager
                        ->getRepository(WpPosts::class)
                        ->findOneById($postId);
                    if ($p) {
                        $p->setGuid($this->local . '/uploads/' . $fileName);
                        $this->entityManager->persist($p);
                        $this->entityManager->flush();
                        $id = $p->getId();
                    } else {
                        $id = $this->createPosts(
                            $postAuthor,
                            $date,
                            $date,
                            $fileName,
                            $fileName,
                            $fileName,
                            'inherit',
                            'open',
                            'closed',
                            $fileName,
                            $fileName,
                            $fileName,
                            $fileName,
                            $date,
                            $date,
                            $fileName,
                            $postParent,
                            $this->local . '/uploads/' . $fileName,
                            0,
                            'attachment',
                            'image/' . $ext,
                            0,
                            0
                        );
                    }
                    $ids += $id . ',';
                }
            }
        }
        return $ids;
    }
    // RECUPERATION DE TOUTES LES ANNONCES D'Un USER

    /**
     * @param $userId
     * @param $tag
     * @return array|list|null
     * @throws Exception
     */
    public function readListAnnonceDataOfUser($userId, $tag)
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT wp.*, wp.post_title as titre, wp.id as id, wp.post_name as slug, wtt.taxonomy as categorie_parent ,
            wpSousCategorie.name as sousCategorie, null as avatar,
            wptCategorie.name as categorieParent,null as price, null as devise,
            ,null as calendrier, null as first_name, null as last_name, null as pays
            ,null as ville,null as etat, wp.post_title as titre_en, null as rupture
            ,null as gallery, null as adressePost, null as categorieParentType,
             null as images_secondaires, null as nom_commercial
            FROM wp_posts wp
            JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
            JOIN wp_term_taxonomy wtt2 ON wtt.parent=wtt2.term_taxonomy_id
            JOIN wp_terms wptCategorie ON wtt2.term_id=wptCategorie.term_id
            where ( wp.post_author =:post_author and wp.post_status =:tag and wp.post_type =:type1 and wp.post_parent =:parent) 
            or (wp.post_author =:post_author and wp.post_status =:tag and wp.post_type =:type2 and wp.post_parent =:parent)
            ORDER BY wp.id DESC';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'type1' => 'product',
            'type2' => 'devis',
            'post_author' => $userId,
            'tag' => $tag,
            'parent' => 0,
        ]);

        $data = $stmt->fetchAllAssociative();

        //  print_r($data);

        if ($data != null) {
            for ($i = 0; $i < sizeof($data); $i++) {
                //price
                $data[$i]['price'] = $this->getPostStringDataValue(
                    $data[$i]["id"],
                    '_price'
                );
                //Nom
                $data[$i]['first_name'] = $this->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'first_name'
                );
                //nom_commercial
                $data[$i]['nom_commercial'] = $this->getUserStringDataValue(
                    $data[$i]['idUser'], 'nom_commercial');
                if ($data[$i]['nom_commercial'] == '') {
                    $data[$i]['nom_commercial'] = $this->getUserStringDataValue(
                        $data[$i]['idUser'], 'first_name');
                }
                //Prenom
                $data[$i]['last_name'] = $this->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'last_name'
                );
                //Rupture
                $data[$i]['rupture'] = $this->getPostStringDataValue(
                    $data[$i]["id"],
                    'rupture'
                );
                if ($data[$i]['rupture'] == '') {
                    $data[$i]['rupture'] = 1;
                }
                //Avatar
                $data[$i]['avatar'] = @unserialize(
                    $this->getUserStringDataValue(
                        $data[$i]['post_author'],
                        'basic_user_avatar'
                    )
                );
                $data[$i]['categorieParentType'] = $this->getProductParentCategory($data[$i]["id"]);
                //Adresse
                $data[$i]['pays'] = $this->getPostStringDataValue(
                    $data[$i]["id"],
                    '_product_country'
                );
                $data[$i]['ville'] = $this->getPostStringDataValue(
                    $data[$i]["id"],
                    '_product_city'
                );

                $data[$i]['etat'] = $this->getPostStringDataValue(
                    $data[$i]["id"],
                    '_product_adress'
                );
                //calendrier

                $calendrier = $this->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'disponibilite'
                );
                if ($calendrier) {
                    $data[$i]['calendrier'] = explode(',', $calendrier);
                } else {
                    $data[$i]['calendrier'] = [];
                }

                //Devises
                $data[$i]['devise'] = $this->getPostStringDataValue(
                    $data[$i]["id"],
                    '_product_devise'
                );
                //Images
                $data[$i]['gallery'] = array_filter($this->getAllImageForAnnouces($data[$i]["id"]));
                //Images Secondaires
                $data[$i]['images_secondaires'] = array_filter($this->getAllSecondImageForAnnouces($data[$i]["id"], 'images_annonces'));
                $data[$i]['titre_en'] = '';
                $title_en = $this->entityManager
                    ->getRepository(ExtTranslationWpPosts::class)
                    ->findOneBy([
                        'foreign_key' => $data[$i]["id"],
                        'field' => 'postExcerpt',
                        'locale' => 'en',
                    ]);
                if ($title_en) {
                    $data[$i]['titre_en'] = $title_en->getContent();
                }
            }
        }
        return $data;
        // FIN RECUPERATION
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
        // FIN RECUPERATION
    }

    // RECUPERATION DE TOUTES LES RESERVATIONS D'Un USER
    /**
     */
    public function readListAnnonceReserves($userId, $tag)
    {
        $conn = $this->entityManager->getConnection();

        $sql = 'SELECT wp.*, wp.post_content as first_name,wp.post_content as last_name
                FROM wp_posts wp
                where wp.post_author =:idUser and wp.post_status =:tag and wp.post_type =:type
                ORDER BY wp.id DESC
                LIMIT 3';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'idUser' => $userId,
            'tag' => $tag,
            'type' => 'shop_order',
        ]);
        $data = $stmt->fetchAllAssociative();

        for ($i = 0; $i < sizeOf($data); $i++) {
            $data[$i]['post_content'] = @unserialize($data[$i]['post_content']);

            for ($j = 0; $j < sizeof($data[$i]['post_content']); $j++) {
                $data[$i]['post_content'][$j]['dates'] = explode(
                    '|',
                    $data[$i]['post_content'][$j]['dates']
                );
            }
        }
        return $data;
        // FIN RECUPERATION
    }

    //Toutes les réservations d'un utilisateur

    // RECUPERATION DE TOUTES LES ANNONCES D'Un USER
    /**
     */
    public function readListReservationOfUser($userId, $tag)
    {
        $conn = $this->entityManager->getConnection();
        // var_dump($userId);
        $sql = 'SELECT wp.*, wp.post_content as annonces , wp.post_content as date_evenement
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

        $data = $stmt->fetchAllAssociative();
        //dd($data);
        if ($data) {
            for ($i = 0; $i < sizeOf($data); $i++) {
                $reservations = $data[$i]['post_content'];
                $listeReservations = @unserialize($reservations);
                $tabAnnonces = [];
                if ($data[$i]['pinged'] == 'product') {
                    for ($w = 0; $w < sizeof($listeReservations); $w++) {
                        array_push(
                            $tabAnnonces,
                            $this->readAllAnnonceData(
                                $listeReservations[$w]['id']
                            )
                        );
                        $date_evenement = $listeReservations[$w]['dates'];
                        $data[$i]['date_evenement'] = $date_evenement;
                    }
                } elseif ($data[$i]['pinged'] == 'devis') {
                    array_push(
                        $tabAnnonces,
                        $this->readAllDevisData($listeReservations["id"])
                    );
                    $data[$i]['date_evenement'] =
                        $listeReservations['dates_horaires'];
                }
                $data[$i]['annonces'] = $tabAnnonces;
            }
        }

        return $data;
    }

    //Toutes les réservations Devis d'un utilisateur
    // RECUPERATION DE TOUT LES Devis D'Un USER
    /**
     */
    public function readListReservationDevisOfUser(int $userId, $tag)
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT wp.* , wp.post_title as titre, wp.post_content as prenom_prestataire, wp.post_content as nom_prestataire , wp.post_content as dates_horaires, wp.guid as price, wp.guid as avatar, wp.guid as pays, wp.guid as ville,
         wp.guid as etat, wp.guid as pays, wp.guid as devise, wp.guid as gallery, wp.guid as images_secondaires
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
        $data = $stmt->fetchAllAssociative();

        if ($data) {
            for ($i = 0; $i < sizeOf($data); $i++) {
                $data[$i]['prenom_prestataire'] = $this->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'first_name'
                );
                $data[$i]['nom_prestataire'] = $this->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'last_name'
                );

                //Adresse
                $data[$i]['dates_devis'] = $this->getPostStringDataValue(
                    $data[$i]["id"],
                    'dates_horaires'
                );
                //price
                $data[$i]['price'] = $this->getPostStringDataValue(
                    $data[$i]["id"],
                    '_price'
                );
                //Avatar
                $data[$i]['avatar'] = @unserialize(
                    $this->getUserStringDataValue(
                        $data[$i]['post_author'],
                        'basic_user_avatar'
                    )
                );
                //Adresse
                $data[$i]['pays'] = $this->getPostStringDataValue(
                    $data[$i]['id'],
                    '_product_country'
                );
                $data[$i]['ville'] = $this->getPostStringDataValue(
                    $data[$i]['id'],
                    '_product_city'
                );

                $data[$i]['etat'] = $this->getPostStringDataValue(
                    $data[$i]['id'],
                    '_product_adress'
                );

                //Devises
                $data[$i]['devise'] = $this->getPostStringDataValue(
                    $data[$i]['id'],
                    '_product_devise'
                );
                //Images
                $data[$i]['gallery'] = $this->getAllImageForAnnouces(
                    $data[$i]['id']
                );
                //Images secondaires
                $data[$i]['images_secondaires'] = $this->getAllSecondImageForAnnouces(
                    $data[$i]['id'],
                    'images_annonces'
                );
            }
        }

        return $data;
    }

    // RECUPERATION DE TOUTES LES DONNEES D'un Devis
    /**
     * @param $postId
     * @return int
     */
    public function readAllDevisData($postId)
    {
        $conn = $this->entityManager->getConnection();

        $sql = 'SELECT wp.post_title,wp.post_author,wp.id,wp.post_date, wp.post_modified, wp.post_content,wp.post_status,
            wp.post_type, wp.post_content as avatar, wp.post_content as email, wp.guid as prix, wp.guid as devise
            ,wp.guid as calendrier, wp.guid as first_name, wp.guid as last_name, wp.guid as pays, wp.guid as dates_horaires,
            wp.guid as videos, wp.guid as commentaires, wp.guid as ville,wp.guid as etat, wp.guid as codePostal,
            wp.guid as images_secondaires ,wp.guid as gallery, wp.post_name as slug
            FROM wp_posts wp
            WHERE wp.id =:id';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery(['id' => $postId]);

        $data = $stmt->fetchAllAssociative();

        if ($data) {
            $user = $this->entityManager
                ->getRepository(User::class)
                ->find($data['0']['post_author']);
            //price
            $data['0']['prix'] = str_replace(
                ' ',
                '',
                $this->getPostStringDataValue($postId, '_price')
            );
            //Nom
            $data['0']['first_name'] = $this->getUserStringDataValue(
                $data['0']['post_author'],
                'first_name'
            );
            //Prenom
            $data['0']['last_name'] = $this->getUserStringDataValue(
                $data['0']['post_author'],
                'last_name'
            );
            //Avatar
            $data['0']['avatar'] = @unserialize(
                $this->getUserStringDataValue(
                    $data['0']['post_author'],
                    'basic_user_avatar'
                )
            );
            //Adresse
            $data['0']['pays'] = $this->getPostStringDataValue(
                $data['0']['id'],
                '_product_country'
            );
            $data['0']['ville'] = $this->getPostStringDataValue(
                $data['0']['id'],
                '_product_city'
            );
            $data['0']['etat'] = $this->getPostStringDataValue(
                $data['0']['id'],
                '_product_adress'
            );
            $data['0']['codePostal'] = $this->getPostStringDataValue(
                $data['0']['id'],
                '_product_code_postal'
            );
            $data['0']['dates_horaires'] = $this->getPostStringDataValue(
                $data['0']['id'],
                'dates_horaires'
            );

            if ($data['0']['pays'] != '') {
                $data['0']['ville'] = str_replace(
                    $data['0']['pays'],
                    '',
                    $this->getPostStringDataValue(
                        $data['0']['id'],
                        '_product_city'
                    )
                );
                //Adresse Post
                $data['0']['etat'] = str_replace(
                    $data['0']['pays'],
                    '',
                    $this->getPostStringDataValue(
                        $data['0']['id'],
                        '_product_adress'
                    )
                );
            }

            if($user)
                $data['0']['email'] = $user->getEmailCanonical();
            else
                $data['0']['email'] = '';
            //calendrier
            $calendrier = $this->entityManager
                ->getRepository(WpUsermeta::class)
                ->findOneBy([
                    'userId' => $data['0']['post_author'],
                    'metaKey' => 'disponibilite',
                ]);
            if ($calendrier) {
                $data['0']['calendrier'] = explode(
                    ',',
                    $calendrier->getMetaValue()
                );
            } else {
                $data['0']['calendrier'] = [];
            }

            //Videos
            $video = [];
            $imgid = [];
            $vid = $this->getPostStringDataValue(
                $data['0']['id'],
                '_product_video'
            );
            if ($vid != '') {
                $video = @unserialize($vid);
                for ($j = 0; $j < sizeof($video); $j++) {
                    $imgid[$j] = $this->getYouTubeId($video[$j]);
                }
                $data['0']['videos'] = $imgid;
            }
            $data['0']['videos'] = $imgid;
            //Devises
            $data['0']['devise'] = $this->entityManager
                ->getRepository(wpPostmeta::class)
                ->findOneBy([
                    'postId' => $data['0']['id'],
                    'metaKey' => '_product_devise',
                ]);
            //Images
            $data['0']['gallery'] = $this->getAllImageForAnnouces(
                $data['0']['id']
            );
            //images_secondaires
            $data['0']['images_secondaires'] = $this->getAllSecondImageForAnnouces(
                $data['0']['id'],
                'images_annonces'
            );

            return $data['0'];
        }
        return $data;
        // FIN RECUPERATION
    }

    /**
     * RECUPERATION DE TOUT LES Devis D'Un USER
     */
    public function readListDevisDataOfUser($userId, $tag)
    {
        $conn = $this->entityManager->getConnection();

        $sql = 'SELECT wp.post_title as titre,wp.post_author,wp.id,wp.post_date, wp.post_modified, wp.post_content,
            wp.post_content as avatar, wp.guid as price, wp.guid as devise, wp.guid
            ,wp.guid as calendrier, wp.guid as first_name, wp.guid as last_name, wp.guid as pays
            ,wp.guid as ville,wp.guid as etat, wp.guid as gallery, wp.guid as adressePost
            , wp.guid as dates_devis, wp.guid as client, wp.guid as images_secondaires
            FROM wp_posts wp
            where wp.post_type =:post_type and wp.post_author =:post_author 
            and wp.post_status =:tag';

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
                $data[$i]['price'] = $this->getPostStringDataValue(
                    $data[$i]['id'],
                    '_price'
                );
                //Nom
                $data[$i]['first_name'] = $this->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'first_name'
                );
                //Prenom
                $data[$i]['last_name'] = $this->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'last_name'
                );
                //Avatar
                $data[$i]['avatar'] = @unserialize(
                    $this->getUserStringDataValue(
                        $data[$i]['post_author'],
                        'basic_user_avatar'
                    )
                );
                //Adresse
                $data[$i]['pays'] = $this->getPostStringDataValue(
                    $data[$i]['id'],
                    '_product_country'
                );
                $data[$i]['ville'] = $this->getPostStringDataValue(
                    $data[$i]['id'],
                    '_product_city'
                );

                $data[$i]['etat'] = $this->getPostStringDataValue(
                    $data[$i]['id'],
                    '_product_adress'
                );
                //calendrier

                $calendrier = $this->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'disponibilite'
                );
                if ($calendrier) {
                    $data[$i]['calendrier'] = explode(',', $calendrier);
                } else {
                    $data[$i]['calendrier'] = [];
                }

                //Devises
                $data[$i]['devise'] = $this->getPostStringDataValue(
                    $data[$i]['id'],
                    '_product_devise'
                );
                //Images
                $data[$i]['gallery'] = $this->getAllImageForAnnouces(
                    $data[$i]['id']
                );
                //Images secondaires
                $data[$i]['images_secondaires'] = $this->getAllSecondImageForAnnouces(
                    $data[$i]['id'],
                    'images_annonces'
                );
                //Adresse
                $data[$i]['dates_devis'] = $this->getPostStringDataValue(
                    $data[$i]['id'],
                    'dates_horaires'
                );
                $client = $this->getPostStringDataValue(
                    $data[$i]['id'],
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
     * Undocumented function
     *
     * @param [type] $id
     * @return void
     */
    public function getAllDevisData($id)
    {
        $conn = $this->entityManager->getConnection();

        $sql = 'SELECT wp.post_title as titre,wp.post_author, wp.id,wp.post_date, wp.post_modified, wp.post_content as description,  wp.guid as first_name, wp.guid as last_name,wp.guid as dates_horaires, wp.guid as prix_devis, wp.guid as devise
            FROM wp_posts wp
            where wp.id =:id';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery(['id' => $id]);

        $data = $stmt->fetch();
        if (sizeof($data) > 0) {
            $data['first_name'] = $this->getUserStringDataValue(
                $data['post_author'],
                'first_name'
            );
            $data['last_name'] = $this->getUserStringDataValue(
                $data['post_author'],
                'last_name'
            );
            $data['dates_horaires'] = $this->getPostStringDataValue(
                $data['id'],
                'dates_horaires'
            );
            $data['prix_devis'] = $this->getPostStringDataValue(
                $data['id'],
                '_price'
            );
            $data['devise'] = $this->getPostStringDataValue(
                $data['id'],
                '_product_devise'
            ); /*
            $data['email'] = $this->em->getRepository(User::class)->find($data['post_author']);
            if($data['email']){
                $data['email'] = $data['email']->getEmailCanonical();
            } */

            return $data;
        }
        return $data;
    }

    // RECUPERATION DE TOUTES LES ANNONCES DU SYSTEME
    /**
     */
    public function readListAnnonceData()
    {
        $conn = $this->entityManager->getConnection();

        $sql = 'SELECT wp.post_title as titre,wp.post_author,wp.id,wp.post_date, wp.post_modified, wp.post_content,
            wtt.taxonomy as categorie_parent , wpSousCategorie.name as sousCategorie, wp.post_content as avatar,
            wptCategorie.name as categorieParent,wp.guid as price, wp.guid as devise, wp.guid
            ,wp.guid as calendrier, wp.guid as first_name, wp.guid as last_name, wp.guid as pays,
            wp.guid as videos, wp.guid as commentaires, wp.guid as ville,wp.guid as etat
            ,wp.guid as gallery, wp.guid as adressePost, wptCategorie.name as categorieParent_en,
            wpSousCategorie.name as sousCategorie_en , wp.post_title as titre_en,
            wpSousCategorie.term_id as idSousCat, wtt.parent as idCat
            FROM wp_posts wp
            JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
            JOIN wp_terms wptCategorie ON wtt.parent=wptCategorie.term_id
            where wp.post_type =:post_type and wp.post_status =:post_status';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery(['post_type' => 'product', 'post_status' => 'publish']);

        $data = $stmt->fetchAllAssociative();

        //  print_r($data);

        if ($data != null) {
            for ($i = 0; $i < sizeof($data); $i++) {
                //price
                $data[$i]['price'] = $this->getPostStringDataValue(
                    $data[$i]['id'],
                    '_price'
                );
                //Nom
                $data[$i]['first_name'] = $this->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'first_name'
                );
                //Prenom
                $data[$i]['last_name'] = $this->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'last_name'
                );
                //Avatar
                $data[$i]['avatar'] = @unserialize(
                    $this->getUserStringDataValue(
                        $data[$i]['post_author'],
                        'basic_user_avatar'
                    )
                );
                //Adresse
                $data[$i]['pays'] = $this->getPostStringDataValue(
                    $data[$i]['id'],
                    '_product_country'
                );
                $data[$i]['ville'] = $this->getPostStringDataValue(
                    $data[$i]['id'],
                    '_product_city'
                );
                $data[$i]['adressePost'] = $this->getPostStringDataValue(
                    $data[$i]['id'],
                    '_product_adress'
                );
                if ($data[$i]['pays'] != '') {
                    $data[$i]['ville'] = str_replace(
                        $data[$i]['pays'],
                        '',
                        $this->getPostStringDataValue(
                            $data[$i]['id'],
                            '_product_city'
                        )
                    );
                    //Adresse Post
                    $data[$i]['adressePost'] = str_replace(
                        $data[$i]['pays'],
                        '',
                        $this->getPostStringDataValue(
                            $data[$i]['id'],
                            '_product_adress'
                        )
                    );
                }
                $data[$i]['etat'] = $this->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'billing_state'
                );
                //calendrier

                $calendrier = $this->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'disponibilite'
                );
                if ($calendrier) {
                    $arrayToTreat = explode(',', $calendrier);
                    usort($arrayToTreat, [$this, 'date_sort']);
                    // var_dump(usort($arrayToTreat ,array($this,"date_sort"))); die();
                    $data[$i]['calendrier'] = $arrayToTreat;
                } else {
                    $data[$i]['calendrier'] = [];
                }

                //Videos

                $video = [];
                $imgid = [];
                $vid = $this->getPostStringDataValue(
                    $data[$i]['id'],
                    '_product_video'
                );
                if ($vid != '') {
                    $video = @unserialize($vid);
                    for ($j = 0; $j < sizeof($video); $j++) {
                        $imgid[$j] = $this->getYouTubeId($video[$j]);
                    }
                    $data[$i]['videos'] = $imgid;
                }

                //Commentaires
                $data[$i]['commentaires'] = $this->entityManager
                    ->getRepository(wpComments::class)
                    ->findBy([
                        'commentPostId' => $data[$i]['id'],
                        'commentApproved' => 1,
                    ]);
                //Devises
                $data[$i]['devise'] = $this->getPostStringDataValue(
                    $data[$i]['post_author'],
                    '_product_devise'
                );
                //Images
                $data[$i]['gallery'] = $this->getAllImageForAnnouces(
                    $data[$i]['id']
                );

                $data[$i]['titre_en'] = '';
                $data[$i]['sousCategorie_en'] = '';
                $data[$i]['categorieParent_en'] = '';
                $title_en = $this->entityManager
                    ->getRepository(ExtTranslationWpPosts::class)
                    ->findOneBy([
                        'foreign_key' => $data[$i]['id'],
                        'field' => 'postExcerpt',
                        'locale' => 'en',
                    ]);
                if ($title_en) {
                    $data[$i]['titre_en'] = $title_en->getContent();
                }

                $cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy([
                        'foreign_key' => $data[$i]['idCat'],
                        'field' => 'name',
                        'locale' => 'en_US',
                    ]);
                if ($cat_en) {
                    $data[$i]['categorieParent_en'] = $cat_en->getContent();
                }

                $sous_cat_en = $this->entityManager
                    ->getRepository(ExtTranslationWpTerms::class)
                    ->findOneBy([
                        'foreign_key' => $data[$i]['idSousCat'],
                        'field' => 'name',
                        'locale' => 'en_US',
                    ]);
                if ($sous_cat_en) {
                    $data[$i]['sousCategorie_en'] = $sous_cat_en->getContent();
                }
            }
        }
        return $data;
        // FIN RECUPERATION
    }

    // RECUPERATION DE TOUTES LES ANNONCES DU SYSTEME
    /**
     */
    public function readListAnnonceDataUsingCategories($tag)
    {
        $conn = $this->entityManager->getConnection();

        $sql = 'SELECT wp.post_title as titre,wp.post_author,wp.id,wp.post_date, wp.post_modified, wp.post_content,
            wtt.taxonomy as categorie_parent , wpSousCategorie.name as sousCategorie, wp.post_content as avatar,
            wptCategorie.name as categorieParent,wp.guid as price, wp.guid as devise, wp.guid
            ,wp.guid as calendrier, wp.guid as first_name, wp.guid as last_name, wp.guid as pays,
            wp.guid as videos, wp.guid as commentaires, wp.guid as ville,wp.guid as etat,wp.guid as adressePost
            ,wp.guid as gallery, wp.guid as adressePost
            FROM wp_posts wp
            JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
            JOIN wp_terms wptCategorie ON wtt.parent=wptCategorie.term_id
            where wp.post_type =:post_type and wp.post_status =:post_status and wptCategorie.slug =:tag';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'post_type' => 'product',
            'post_status' => 'publish',
            'tag' => $tag,
        ]);

        $data = $stmt->fetchAllAssociative();

        //print_r(sizeof(array_filter($data))); print_r(sizeof($data));

        if ($data != null) {
            for ($i = 0; $i < sizeof($data); $i++) {
                //price
                $data[$i]['price'] = $this->getPostStringDataValue(
                    $data[$i]['id'],
                    '_price'
                );
                //Nom
                $data[$i]['first_name'] = $this->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'first_name'
                );
                //Prenom
                $data[$i]['last_name'] = $this->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'last_name'
                );
                //Avatar
                $data[$i]['avatar'] = @unserialize(
                    $this->getUserStringDataValue(
                        $data[$i]['post_author'],
                        'basic_user_avatar'
                    )
                );
                //Adresse
                $data[$i]['pays'] = $this->getPostStringDataValue(
                    $data[$i]['id'],
                    '_product_country'
                );
                $data[$i]['ville'] = $this->getPostStringDataValue(
                    $data[$i]['id'],
                    '_product_city'
                );

                $data[$i]['etat'] = $this->getPostStringDataValue(
                    $data[$i]['id'],
                    '_product_adress'
                );
                $data[$i]['adressePost'] = $this->getPostStringDataValue(
                    $data[$i]['id'],
                    '_product_adress'
                );
                //calendrier

                $calendrier = $this->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'disponibilite'
                );
                if ($calendrier) {
                    $arrayToTreat = explode(',', $calendrier);
                    usort($arrayToTreat, [$this, 'date_sort']);
                    $data[$i]['calendrier'] = $arrayToTreat;
                } else {
                    $data[$i]['calendrier'] = [];
                }

                //Videos

                $video = [];
                $imgid = [];
                $vid = $this->getPostStringDataValue(
                    $data[$i]['id'],
                    '_product_video'
                );
                if ($vid != '') {
                    $video = @unserialize($vid);
                    for ($j = 0; $j < sizeof($video); $j++) {
                        $imgid[$j] = $this->getYouTubeId($video[$j]);
                    }
                    $data[$i]['videos'] = $imgid;
                }

                //Commentaires
                $data[$i]['commentaires'] = $this->entityManager
                    ->getRepository(wpComments::class)
                    ->findBy([
                        'commentPostId' => $data[$i]['id'],
                        'commentApproved' => 1,
                    ]);
                //Devises
                $data[$i]['devise'] = $this->getPostStringDataValue(
                    $data[$i]['post_author'],
                    '_product_devise'
                );
                //Images
                $data[$i]['gallery'] = $this->getAllImageForAnnouces(
                    $data[$i]['id']
                );
            }
        }
        return $data;
        // FIN RECUPERATION
    }

    // RECUPERATION DE TOUTES LES ANNONCES DU SYSTEME
    /**
     */
    public function readListAnnonceDataTri($tag)
    {
        $conn = $this->entityManager->getConnection();
        $sql = '';

        $sql = 'SELECT wp.post_title as titre,wp.post_author,wp.id,wp.post_date, wp.post_modified, wp.post_content,
        wtt.taxonomy as categorie_parent , wpSousCategorie.name as sousCategorie, wp.post_content as avatar,
        wptCategorie.name as categorieParent,wp.guid as price, wp.guid as devise, wp.guid
        ,wp.guid as calendrier, wp.guid as first_name, wp.guid as last_name, wp.guid as pays,
        wp.guid as videos, wp.guid as commentaires, wp.guid as ville,wp.guid as etat
        ,wp.guid as gallery, wp.guid as adressePost
        FROM wp_posts wp
        JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
        JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
        JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
        JOIN wp_terms wptCategorie ON wtt.parent=wptCategorie.term_id
        where wp.post_type =:post_type and wp.post_status =:post_status';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery(['post_type' => 'product', 'post_status' => 'publish']);

        $data = $stmt->fetchAllAssociative();

        //  print_r($data);

        if ($data != null) {
            for ($i = 0; $i < sizeof($data); $i++) {
                //price
                $data[$i]['price'] = $this->getPostStringDataValue(
                    $data[$i]['id'],
                    '_price'
                );
                //Nom
                $data[$i]['first_name'] = $this->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'first_name'
                );
                //Prenom
                $data[$i]['last_name'] = $this->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'last_name'
                );
                //Avatar
                $data[$i]['avatar'] = @unserialize(
                    $this->getUserStringDataValue(
                        $data[$i]['post_author'],
                        'basic_user_avatar'
                    )
                );
                //Adresse
                $data[$i]['pays'] = $this->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'billing_country'
                );
                $data[$i]['ville'] = $this->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'billing_city'
                );

                $data[$i]['etat'] = $this->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'billing_state'
                );
                //calendrier

                $calendrier = $this->getUserStringDataValue(
                    $data[$i]['post_author'],
                    'disponibilite'
                );
                if ($calendrier) {
                    $arrayToTreat = explode(',', $calendrier);
                    usort($arrayToTreat, [$this, 'date_sort']);
                    $data[$i]['calendrier'] = $arrayToTreat;
                } else {
                    $data[$i]['calendrier'] = [];
                }

                //Videos

                $video = [];
                $imgid = [];
                $vid = $this->getPostStringDataValue(
                    $data[$i]['id'],
                    '_product_video'
                );
                if ($vid != '') {
                    $video = @unserialize($vid);
                    for ($j = 0; $j < sizeof($video); $j++) {
                        $imgid[$j] = $this->getYouTubeId($video[$j]);
                    }
                    $data[$i]['videos'] = $imgid;
                }

                //Commentaires
                $data[$i]['commentaires'] = $this->entityManager
                    ->getRepository(wpComments::class)
                    ->findBy([
                        'commentPostId' => $data[$i]['id'],
                        'commentApproved' => 1,
                    ]);
                //Devises
                $data[$i]['devise'] = $this->getPostStringDataValue(
                    $data[$i]['post_author'],
                    '_product_devise'
                );
                //Images
                $data[$i]['gallery'] = $this->getAllImageForAnnouces(
                    $data[$i]['id']
                );
            }
        }
        if ($tag == 'all') {
            $data = $data;
        }
        if ($tag == 'croissantPrice') {
            $array_column = array_column($data, 'price');
            array_multisort($array_column, SORT_ASC, $data);
        }
        if ($tag == 'decroissantPrice') {
            $array_column = array_column($data, 'price');
            array_multisort($array_column, SORT_DESC, $data);
        }
        if ($tag == 'croissantDate') {
            $array_column = array_column($data, 'post_date');
            array_multisort($array_column, SORT_ASC, $data);
        }
        if ($tag == 'decroissantDate') {
            $array_column = array_column($data, 'post_date');
            array_multisort($array_column, SORT_DESC, $data);
        }
        return $data;
        // FIN RECUPERATION
    }

    /**
     * @param $userId
     * @return array()
     */
    public function readAllProData($userId)
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
        $data['first_name'] = $this->getUserStringDataValue($userId, 'first_name');
        //Prenom
        $data['last_name'] = $this->getUserStringDataValue($userId, 'last_name');
        //Nom Commercial
        $data['nom_commercial'] = $this->getUserStringDataValue($userId, 'nom_commercial');
        //Adresse
        $data['pays'] = $this->getUserStringDataValue(
            $userId,
            'billing_country'
        );
        $data['ville'] = $this->getUserStringDataValue($userId, 'billing_city');
        $data['etat'] = $this->getUserStringDataValue($userId, 'billing_state');
        $data['billing_company'] = $this->getUserStringDataValue($userId, 'billing_company');
        $data['region'] = $this->getUserStringDataValue($userId, 'region');
        $data['titre'] = $this->getUserStringDataValue($userId, 'titre');

        $vid = $this->readUserMeta($userId, 'video');
        //Videos

        $video = [];
        $imgid = [];
        if ($vid) {
            $video = @unserialize($vid->getMetaValue());
            for ($i = 0; $i < sizeof($video); $i++) {
                $imgid[$i] = $this->getYouTubeId($video[$i]);
            }
            $data['videos'] = $imgid;
        }
        //Commentaires
        $data['commentaires'] = $this->entityManager
            ->getRepository(wpComments::class)
            ->findBy([
                'commentPostId' => $data['id'],
            ]);
        // var_dump($data["commentaires"]);
        //Compétence
        $competence = $this->getUserStringDataValue($userId, 'competence');
        if ($competence) {
            $data['competence'] = explode(',', $competence);
        } else {
            $data['competence'] = [];
        }

        //Pour le portfolio
        $portfolioString = $this->getUserStringDataValue($userId, 'portfolio');
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
        $data['reference'] = $this->getUserStringDataValue(
            $userId,
            'reference'
        );
        //Titre
        $data['titre'] = $this->getUserStringDataValue($userId, 'titre');
        // Description
        $data['description'] = $this->getUserStringDataValue(
            $userId,
            'description'
        );
        //Avatar
        $data['avatar'] = @unserialize(
            $this->getUserStringDataValue($userId, 'basic_user_avatar')
        );
        //Annonces
        $data['annonces'] = $this->readListAnnonceDataOfUser(
            $userId,
            'publish'
        );
        //var_dump($data["annonces"]);
        for ($i = 0; $i < sizeof($data['annonces']); $i++) {
            $data['annonces'][$i]['price'] = $this->getPostStringDataValue(
                $data['annonces'][$i]['id'],
                '_price'
            );
            $data['annonces'][$i]['devise'] = $this->getPostStringDataValue(
                $data['annonces'][$i]['id'],
                '_product_devise'
            );
            $data['annonces'][$i]['gallerie'] = $this->getAllImageForAnnouces(
                $data['annonces'][$i]['id']
            );
        }
        return $data;
    }

    /**
     * @param $userId
     * @return array()
     * @throws Exception
     */
    public function readProDataWithoutAnnounce($userId)
    {
        $conn = $this->entityManager->getConnection();

        $sql = 'SELECT  wpUser.*,wpUser.email_canonical as email,  wpUser.user_nicename as first_name, wpUser.user_nicename as last_name, wpUser.user_nicename as pays,
            wpUser.user_nicename as videos, wpUser.user_nicename as commentaires, wpUser.user_nicename as ville, wpUser.user_nicename as etat, wpUser.user_nicename as titre,
            wpUser.user_nicename as competence, wpUser.user_nicename as reference , wpUser.user_nicename as portfolio,
            wpUser.user_nicename as annonces, wpUser.user_nicename as titre, wpUser.user_nicename as descriptionUser,
            wpUser.user_nicename as avatar, wpUser.user_nicename as societe
            FROM wp_users wpUser
            WHERE wpUser.id =:id';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery(['id' => $userId]);
        $data = $stmt->fetchAllAssociative();
        if (!empty($data)) {
            $data = $data[0];
        } else {
            return null;
        }

        //Nom
        $data['first_name'] = $this->getUserStringDataValue(
            $userId,
            'first_name'
        );
        //Prenom
        $data['last_name'] = $this->getUserStringDataValue(
            $userId,
            'last_name'
        );
        //Adresse
        $data['pays'] = $this->getUserStringDataValue(
            $userId,
            'billing_country'
        );
        $data['ville'] = $this->getUserStringDataValue($userId, 'billing_city');
        $data['etat'] = $this->getUserStringDataValue($userId, 'billing_state');
        $data['societe'] = $this->getUserStringDataValue(
            $userId,
            'billing_company'
        );
        $data['region'] = $this->getUserStringDataValue($userId, 'region');
        $data['titre'] = $this->getUserStringDataValue($userId, 'titre');
        //Commentaires
        $data['commentaires'] = $this->entityManager
            ->getRepository(wpComments::class)
            ->findBy([
                'commentPostId' => $data['id'],
            ]);
        // var_dump($data["commentaires"]);
        //Compétence
        $competence = $this->getUserStringDataValue($userId, 'competence');
        if ($competence) {
            $data['competence'] = explode(',', $competence);
        } else {
            $data['competence'] = [];
        }

        //Pour le portfolio
        $portfolioString = $this->getUserStringDataValue($userId, 'portfolio');
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
        $data['reference'] = $this->getUserStringDataValue(
            $userId,
            'reference'
        );
        //Titre
        $data['titre'] = $this->getUserStringDataValue($userId, 'titre');
        // Description
        $data['description'] = $this->getUserStringDataValue(
            $userId,
            'description'
        );
        //Avatar
        $data['avatar'] = @unserialize(
            $this->getUserStringDataValue($userId, 'basic_user_avatar')
        );
        if(!$data['avatar']){

        }
        //Annonces publiees
        $data['annonces_publiees'] = $this->readListAnnonceDataOfUserCount(
            $userId,
            'publish'
        );
        return $data;
    }

    /**
     * @param $userId
     * @return array()
     */
    //Données annonces publiés du pro + Annonces
    /**
     * @param $userId
     * @return mixed[]
     * @throws DBALException
     */
    public function readAllProAnnouncesWithCommentsPosted($userId)
    {
        //Annonces
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT DISTINCT wp.*, wp.post_excerpt as devise, wp.post_excerpt as auteur,wp.post_excerpt as adresse, wp.post_excerpt as price, wp.post_excerpt as gallerie,wp.post_excerpt as commentaires,wp.post_excerpt as avatar
                FROM wp_posts wp
                JOIN wp_comments wp_c on wp.id = wp_c.comment_post_ID
                WHERE wp.post_type =:categoriePost and wp.post_status=:post_status and wp_c.user_id =:idAuteur and wp_c.comment_approved =:etatCommentaire';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'categoriePost' => 'product',
            'post_status' => 'publish',
            'etatCommentaire' => 1,
            'idAuteur' => $userId,
        ]);
        $data = $stmt->fetchAllAssociative();
        //var_dump($data["annonces"]);
        for ($j = 0; $j < sizeof($data); $j++) {
            $data[$j]['price'] = $this->getPostStringDataValue(
                $data[$j]['id'],
                '_price'
            );
            $data[$j]['devise'] = $this->getPostStringDataValue(
                $data[$j]['id'],
                '_product_devise'
            );
            $data[$j]['gallerie'] = $this->getAllImageForAnnouces(
                $data[$j]['id']
            );
            $data[$j]['ville'] = $this->getPostStringDataValue(
                $data[$j]['id'],
                '_product_city'
            );
            $data[$j]['pays'] = $this->getPostStringDataValue(
                $data[$j]['id'],
                '_product_country'
            );
            $data[$j]['adresse'] = $this->getPostStringDataValue(
                $data[$j]['id'],
                '_product_adress'
            );

            //Get comments
            $conn5 = $this->entityManager->getConnection();
            $sql5 = 'SELECT wp_comments.*, wp_comments.comment_post_ID as avatar
                    FROM wp_comments
                    WHERE wp_comments.comment_post_ID =:commentId and wp_comments.comment_approved=:etat and wp_comments.user_id=:auteur';

            $stmt5 = $conn5->prepare($sql5);
            $stmt5->executeQuery([
                'commentId' => $data[$j]['id'],
                'etat' => 1,
                'auteur' => $userId,
            ]);
            $data5 = $stmt5->fetchAllAssociative();
            for ($z = 0; $z < sizeof($data5); $z++) {
                $avatars = $this->readUserMeta(
                    $data5[$z]['user_id'],
                    'basic_user_avatar'
                );
                $avatar = '';
                if ($avatars && $avatars->getMetaValue()) {
                    $img = @unserialize($avatars->getMetaValue());
                    $avatar = $img[sizeof($img) - 1];
                }
                $data5[$z]['avatar'] = $avatar;
            }
            $data[$j]['commentaires'] = $data5;
            //End Comments

            $conn = $this->entityManager->getConnection();
            $sql1 = 'SELECT  wpUser.*,  wpUser.user_nicename as first_name, wpUser.user_nicename as last_name, wpUser.user_nicename as pays, wpUser.user_nicename as ville, wpUser.user_nicename as etat, wpUser.user_nicename as calendrier
                FROM wp_users wpUser
                WHERE wpUser.id =:id';
            $stmt = $conn->prepare($sql1);
            $stmt = $stmt->executeQuery(['id' => $data[$j]['post_author']]);
            $data1 = $stmt->fetchAllAssociative();
            //Nom
            $data1['first_name'] = $this->getUserStringDataValue(
                $userId,
                'first_name'
            );
            //  var_dump($data["first_name"]);
            //Prenom
            $data1['last_name'] = $this->getUserStringDataValue(
                $userId,
                'last_name'
            );
            //  var_dump($data["last_name"]);
            //Adresse
            $data1['pays'] = $this->getUserStringDataValue(
                $userId,
                'billing_country'
            );
            $data1['ville'] = $this->getUserStringDataValue(
                $userId,
                'billing_city'
            );
            $data1['etat'] = $this->getUserStringDataValue(
                $userId,
                'billing_state'
            );
            $calendrier = $this->getUserStringDataValue(
                $userId,
                'disponibilite'
            );
            if ($calendrier) {
                $arrayToTreat = explode(',', $calendrier);
                usort($arrayToTreat, [$this, 'date_sort']);
                $calendrier = $arrayToTreat;
            //$data[$i]["calendrier"] = $arrayToTreat;
            } else {
                $calendrier = [];
            }
            //Calendrier
            $data[$j]['calendrier'] = $calendrier;
            $data[$j]['auteur'] = $data1;
        }
        return $data;
    }
    //Données commentaires recus du pro

    /**
     * @param $userId
     * @return mixed[]
     * @throws DBALException
     */
    public function readAllProAnnouncesWithComments($userId)
    {
        //  var_dump($userId);
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wpUser.*,  wpUser.user_nicename as first_name, wpUser.user_nicename as last_name, wpUser.user_nicename as pays, wpUser.user_nicename as ville, wpUser.user_nicename as etat,wpUser.user_nicename as annonces,
                wpUser.user_nicename as descriptionUser, wpUser.user_nicename as avatar, wpUser.user_nicename as calendrier
                FROM wp_users wpUser
                WHERE wpUser.id =:id';
        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery(['id' => $userId]);
        $data = $stmt->fetchAllAssociative();
        for ($i = 0; $i < sizeof($data); $i++) {
            //Nom
            $data[$i]['first_name'] = $this->getUserStringDataValue(
                $userId,
                'first_name'
            );
            //  var_dump($data["first_name"]);
            //Prenom
            $data[$i]['last_name'] = $this->getUserStringDataValue(
                $userId,
                'last_name'
            );
            //  var_dump($data["last_name"]);
            //Adresse
            $data[$i]['pays'] = $this->getUserStringDataValue(
                $userId,
                'billing_country'
            );
            $data[$i]['ville'] = $this->getUserStringDataValue(
                $userId,
                'billing_city'
            );
            $data[$i]['etat'] = $this->getUserStringDataValue(
                $userId,
                'billing_state'
            );
            $calendrier = $this->getUserStringDataValue(
                $userId,
                'disponibilite'
            );
            if ($calendrier) {
                $arrayToTreat = explode(',', $calendrier);
                usort($arrayToTreat, [$this, 'date_sort']);
                $calendrier = $arrayToTreat;
            } else {
                $calendrier = [];
            }
            //Calendrier
            $data[$i]['calendrier'] = $calendrier;
            //Annonces
            $conn = $this->entityManager->getConnection();
            $sql = 'SELECT wp.* ,wp.post_excerpt as devise,
                wp.post_excerpt as price, wp.post_excerpt as gallerie,wp.post_excerpt as commentaires,
                wp.post_excerpt as avatar
                FROM wp_posts wp
                JOIN wp_comments wp_c on wp.id = wp_c.comment_post_ID
                WHERE wp.post_type =:categoriePost and wp.post_author=:idUser and wp.post_status=:post_status';
            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'idUser' => $userId,
                'categoriePost' => 'product',
                'post_status' => 'publish',
            ]);
            $data[$i]['annonces'] = $stmt->fetchAllAssociative();
            //var_dump($data["annonces"]);
            for ($j = 0; $j < sizeof($data[$i]['annonces']); $j++) {
                $data[$i]['annonces'][$j]['price'] = $this->getPostStringDataValue(
                    $data[$i]['annonces'][$j]['id'],
                    '_price'
                );
                $data[$i]['annonces'][$j]['devise'] = $this->getPostStringDataValue(
                    $data[$i]['annonces'][$j]['id'],
                    '_product_devise'
                );
                $data[$i]['annonces'][$j]['gallerie'] = $this->getAllImageForAnnouces(
                    $data[$i]['annonces'][$j]['id']
                );
                //get comments for announces
                $conn2 = $this->entityManager->getConnection();
                $sql2 = 'SELECT wp_c.*, wp_c.comment_content as avatar, wp_c.comment_content as nom, wp_c.comment_content as prenom
                FROM wp_comments wp_c
                WHERE wp_c.commentPostId =:id and commentApproved =:etat';

                $stmt = $conn->prepare($sql2);
                $stmt = $stmt->executeQuery([
                    'id' => $data[$i]['annonces'][$j]['id'],
                    'etat' => 1,
                ]);

                $dataComments = $stmt->fetchAllAssociative();
                for ($k = 0; $k < sizeOf($dataComments); $k++) {
                    $dataComments[$k]['avatar'] = @unserialize(
                        $this->getUserStringDataValue(
                            $dataComments[$k]['user_id'],
                            'basic_user_avatar'
                        )
                    );
                    $dataComments[$k]['nom'] = $this->getUserStringDataValue(
                        $dataComments[$k]['user_id'],
                        'last_name'
                    );
                    $dataComments[$k]['prenom'] = $this->getUserStringDataValue(
                        $dataComments[$k]['user_id'],
                        'first_name'
                    );
                }
                $data[$i]['annonces'][$j]['commentaires'] = $dataComments;
            }
        }
        return $data;
    }

    // RECUPERATION DES DONNEES DES USERS:

    /**
     * @param int $userId
     * @param string $tag
     * @return string
     */
    public function getUserStringDataValue(int $userId, string $tag)
    {
        $usermeta = $this->entityManager
            ->getRepository(wpUsermeta::class)
            ->findOneBy(['userId' => $userId, 'metaKey' => $tag],
                ['umetaId' => 'desc']);
        if ($usermeta) {
            return $usermeta->getMetaValue();
        } else {
            return '';
        }
    }

    /**
     * Fetch all metadata for a user in a single query.
     *
     * @param int $userId
     * @param array $keys
     * @return array
     */
    public function getUserMetadata(int $userId, array $keys): array
    {
        // Subquery to get the latest metaValue for each metaKey
        $subQuery = $this->entityManager
            ->getRepository(WpUsermeta::class)
            ->createQueryBuilder('um1')
            ->select('MAX(um1.umetaId)') // Assuming umetaId is the primary key and auto-increments
            ->where('um1.userId = :userId')
            ->andWhere('um1.metaKey = um.metaKey') // Correlate with the outer query
            ->groupBy('um1.metaKey');

        // Main query to fetch the latest metadata for each key
        $metadata = $this->entityManager
            ->getRepository(WpUsermeta::class)
            ->createQueryBuilder('um')
            ->select('um.metaKey', 'um.metaValue')
            ->where('um.userId = :userId')
            ->andWhere('um.metaKey IN (:keys)')
            ->andWhere('um.umetaId IN (' . $subQuery->getDQL() . ')') // Use the subquery
            ->setParameter('userId', $userId)
            ->setParameter('keys', $keys)
            ->orderBy('um.metaKey', 'ASC') // Order by metaKey (optional)
            ->getQuery()
            ->getResult();

        // Convert the result into an associative array for easy access
        $result = [];
        foreach ($metadata as $item) {
            $result[$item['metaKey']] = $item['metaValue'];
        }

        return $result;
    }

    // RECUPERATION DES DONNEES DES USERS:
    /**
     * @param $userId
     * @return string
     */
    public function getPostStringDataValue(string $userId, string $tag)
    {
        $usermeta = $this->entityManager
            ->getRepository(WpPostmeta::class)
            ->findOneBy(['postId' => $userId, 'metaKey' => $tag]);
        if ($usermeta) {
            return $usermeta->getMetaValue();
        } else {
            return '';
        }
    }

    /**
     * @param $userId
     * @return string
     */
    public function getPostmetaStringContentLangValue(int $id, string $field, string $locale)
    {
        $data = $this->entityManager
            ->getRepository(ExtTranslationWpPostmeta::class)
            ->findOneBy(['foreign_key' => $id, 'field' => $field, 'locale' => $locale, 'object_class' => 'App\Entity\WpPostmeta']);
        if ($data) {
            return $data->getContent();
        } else {
            return '';
        }
    }

    /**
     * @param $userId
     * @return string
     */
    public function getPostsStringContentLangValue(int $id, string $field, string $locale)
    {
        $data = $this->entityManager
            ->getRepository(ExtTranslationWpPosts::class)
            ->findOneBy(
                ['foreign_key' => $id, 'field' => $field, 'locale' => $locale, 'object_class' => 'App\Entity\WpPosts'],
                ['id' => 'desc']
            );
        //dd($data);
        if ($data) {
            return $data->getContent();
        } else {
            return '';
        }
    }

    // RECUPERATION DES DONNEES DES Posts:
    /**
     * @param $userId
     * @return string
     */
    public function getUserStringPostValue(int $postId, string $tag)
    {
        $usermeta = $this->entityManager
            ->getRepository(wpPostmeta::class)
            ->findOneBy(['postId' => $postId, 'metaKey' => $tag]);
        if ($usermeta) {
            return $usermeta->getMetaValue();
        } else {
            return '';
        }
    }

    // RECUPERATION DES DONNEES DES USERS:
    /**
     * @param $userId
     * @return array
     */
    public function getAllImageForAnnouces($userId)
    {
        $stringId = $this->entityManager
            ->getRepository(wpPostmeta::class)
            ->findOneBy([
                'postId' => $userId,
                'metaKey' => '_product_image_gallery',
            ]);
        $response = [];
        if ($stringId) {
            $userTabImage = explode(',', $stringId->getMetaValue());
            for ($i = 0; $i < sizeOf($userTabImage); $i++) {
                $tempImage = $this->entityManager
                    ->getRepository(wpPosts::class)
                    ->find($userTabImage[$i]);
                    if ($tempImage && $tempImage != '') {
                        $response[] = $tempImage;
                    }
            }
        }
        return $response;
    }

    /**
     * @param $postId
     * @return array
     */
    public function getAllSecondImageForAnnouces($postId)
    {
        $stringId = $this->entityManager
            ->getRepository(wpPostmeta::class)
            ->findOneBy(
                ['postId' => $postId, 'metaKey' => 'images_annonces'],
                ['metaId' => 'desc'],
                1,
                0
            );
        $response = [];
        if ($stringId) {
            $userTabImage = array_filter(
                explode(',', $stringId->getMetaValue())
            );

            for ($i = 0; $i < sizeOf($userTabImage); $i++) {
                $tempImage = $this->entityManager
                    ->getRepository(wpPosts::class)
                    ->find($userTabImage[$i]);
                    if ($tempImage && $tempImage != '') {
                        $response[] = $tempImage;
                    }
            }
        }
        return $response;
    }

    // RECUPERATION DES DONNEES DU BLOG:
    /**
     * @param $userId
     * @return string
     */
    public function getAllBlog()
    {
        $conn = $this->entityManager->getConnection();

        $sql = 'SELECT DISTINCT wp.* ,wp.guid as title,wp.guid as descriptionPost,
            wp.guid as imagePost
            FROM wp_posts wp
            WHERE wp.post_type =:categoriePost and wp.post_status=:statut
            ORDER BY wp.id DESC';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery(['statut' => 'publish', 'categoriePost' => 'post']);

        $data = $stmt->fetchAllAssociative();
        for ($i = 0; $i < sizeof($data); $i++) {
            $data[$i]['title'] = $this->getUserStringPostValue(
                $data[$i]['id'],
                '_aioseop_title'
            );
            $data[$i]['descriptionPost'] = $this->tronquer_texte(
                $data[$i]['post_content'],
                50
            );

            $img = $this->entityManager
                ->getRepository(wpPosts::class)
                ->findOneBy([
                    'postParent' => $data[$i]['id'],
                    'postType' => 'attachment',
                ]);
            if ($img) {
                $data[$i]['imagePost'] = $img->getGuid();
            }
            $data[$i]['title_en'] = '';
            $data[$i]['content_en'] = '';
            $title_en = $this->entityManager
                ->getRepository(ExtTranslationWpPosts::class)
                ->findOneBy([
                    'foreign_key' => $data[$i]['id'],
                    'field' => 'postExcerpt',
                    'locale' => 'en_US',
                ]);
            if ($title_en) {
                $data[$i]['title_en'] = $title_en->getContent();
            }

            $content_en = $this->entityManager
                ->getRepository(ExtTranslationWpPosts::class)
                ->findOneBy([
                    'foreign_key' => $data[$i]['id'],
                    'field' => 'postContentFiltered',
                    'locale' => 'en_US',
                ]);
            if ($content_en) {
                $data[$i]['content_en'] = $content_en->getContent();
            }
        }
        return $data;
    }

    /**
     * @param $userId
     * @return string
     */
    public function getNumberBlog($number)
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT DISTINCT wp.* ,wp.guid as title,wp.guid as descriptionPost,
            wp.guid as imagePost
            FROM wp_posts wp
            WHERE wp.post_type =:categoriePost and wp.post_status=:statut
            ORDER BY wp.id DESC
            LIMIT ' . $number;

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery(['statut' => 'publish', 'categoriePost' => 'post']);

        $data = $stmt->fetchAllAssociative();
        for ($i = 0; $i < sizeof($data); $i++) {
            $data[$i]['title'] = $this->getUserStringPostValue(
                $data[$i]['id'],
                '_aioseop_title'
            );
            $data[$i]['descriptionPost'] = $this->tronquer_texte(
                $data[$i]['post_content'],
                50
            );

            $img = $this->entityManager
                ->getRepository(wpPosts::class)
                ->findOneBy([
                    'postParent' => $data[$i]['id'],
                    'postType' => 'attachment',
                ]);
            if ($img) {
                $data[$i]['imagePost'] = $img->getGuid();
            }
            $data[$i]['title_en'] = '';
            $data[$i]['content_en'] = '';
            $title_en = $this->entityManager
                ->getRepository(ExtTranslationWpPosts::class)
                ->findOneBy([
                    'foreign_key' => $data[$i]['id'],
                    'field' => 'postExcerpt',
                    'locale' => 'en_US',
                ]);
            if ($title_en) {
                $data[$i]['title_en'] = $title_en->getContent();
            }

            $content_en = $this->entityManager
                ->getRepository(ExtTranslationWpPosts::class)
                ->findOneBy([
                    'foreign_key' => $data[$i]['id'],
                    'field' => 'postContentFiltered',
                    'locale' => 'en_US',
                ]);
            if ($content_en) {
                $data[$i]['content_en'] = $content_en->getContent();
            }
        }
        return $data;
    }

    // RECUPERATION DES DONNEES DU BLOG:
    /**
     * @param $userId
     * @return string
     */
    public function getAllBlogBy($tag)
    {
        $conn = $this->entityManager->getConnection();

        $sql = 'SELECT DISTINCT wp.* ,wp.guid as title,wp.guid as descriptionPost,
            wp.guid as imagePost,wp.guid as title_en, wp.post_content as content_en
            FROM wp_posts wp
            JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
            WHERE wp.post_type =:categoriePost and wp.post_status =:statut and wpSousCategorie.slug =:tag
            ORDER BY wp.id DESC';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'statut' => 'publish',
            'categoriePost' => 'post',
            'tag' => $tag,
        ]);

        $data = $stmt->fetchAllAssociative();
        for ($i = 0; $i < sizeof($data); $i++) {
            $data[$i]['title'] = $this->getUserStringPostValue(
                $data[$i]['id'],
                '_aioseop_title'
            );
            $data[$i]['descriptionPost'] =
                substr(strstr($data[$i]['post_content'], '</p>'), 0, 300) .
                '</p>';

            $img = $this->entityManager
                ->getRepository(wpPosts::class)
                ->findOneBy(['postParent' => $data[$i]['id']]);
            if ($img) {
                $data[$i]['imagePost'] = $img->getGuid();
            } else {
                $data[$i]['imagePost'] = '';
            }
            $data[$i]['title_en'] = '';
            $data[$i]['content_en'] = '';
            $title_en = $this->entityManager
                ->getRepository(ExtTranslationWpPosts::class)
                ->findOneBy([
                    'foreign_key' => $data[$i]['id'],
                    'field' => 'postExcerpt',
                    'locale' => 'en_US',
                ]);
            if ($title_en) {
                $data[$i]['title_en'] = $title_en->getContent();
            }

            $content_en = $this->entityManager
                ->getRepository(ExtTranslationWpPosts::class)
                ->findOneBy([
                    'foreign_key' => $data[$i]['id'],
                    'field' => 'postContentFiltered',
                    'locale' => 'en_US',
                ]);
            if ($content_en) {
                $data[$i]['content_en'] = $content_en->getContent();
            }
        }
        return $data;
    }

    /**
     * @param $idPost
     * @return mixed
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getDetailsOfBlogPost($idPost)
    {
        $data = $this->entityManager
            ->createQuery(
                'SELECT wp FROM App\Entity\WpPosts wp WHERE wp.id=:idPost'
            )
            ->setParameters(['idPost' => $idPost])
            ->getSingleResult();
        $data->setPostExcerpt(
            $this->getUserStringPostValue($data->getId(), '_aioseop_title')
        );
        $data->setPostPassword(
            $this->getUserStringPostValue(
                $data->getId(),
                '_aioseop_description'
            )
        );

        $img = $this->entityManager
            ->getRepository(wpPosts::class)
            ->findOneBy(['postParent' => $data->getId()]);
        if ($img) {
            $data->setGuid($img->getGuid());
        } else {
            $data->setGuid('');
        }
        return $data;
    }

    /**
     * @param $slug
     * @return mixed
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getDetailsOfBlogPostBySlug($slug)
    {
        $data = $this->entityManager
            ->createQuery(
                'SELECT wp FROM App\Entity\WpPosts wp
            WHERE wp.postName=:postName and wp.postType=:postType and wp.postStatus=:postStatus'
            )
            ->setParameters([
                'postName' => $slug,
                'postType' => 'post',
                'postStatus' => 'publish',
            ])
            ->setMaxResults(1)
            ->getOneOrNullResult();

        $img = $this->entityManager
            ->getRepository(wpPosts::class)
            ->findOneBy(['postParent' => $data->getId()]);
        if ($img) {
            $data->setGuid($img->getGuid());
        } else {
            $data->setGuid('');
        }
        return $data;
    }

    /**
     * @param $idPost
     * @return mixed[]
     * @throws DBALException
     */
    public function getRelatedBlogPost($idPost)
    {
        $conn = $this->entityManager->getConnection();

        $sql = 'SELECT DISTINCT wpSousCategorie.name,wp.*
            FROM wp_posts wp
            JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
            JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
            WHERE wp.id =:idPost
            ORDER BY wp.id DESC';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery(['idPost' => $idPost]);

        $data1 = $stmt->fetchAllAssociative()[0]['name'];

        $conn = $this->entityManager->getConnection();

        $sql = 'SELECT DISTINCT wp.* ,wp.guid as title,wp.guid as descriptionPost,
        wp.guid as imagePost
        FROM wp_posts wp
        JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
        JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
        JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
        WHERE wp.post_type =:categoriePost and wp.post_status =:statut and wpSousCategorie.slug =:tag
        ORDER BY wp.id DESC';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'statut' => 'publish',
            'categoriePost' => 'post',
            'tag' => $data1,
        ]);

        $data = $stmt->fetchAllAssociative();
        for ($i = 0; $i < sizeof($data); $i++) {
            $data[$i]['categorie'] = $data1;
            $data[$i]['title'] = $this->getUserStringPostValue(
                $data[$i]['id'],
                '_aioseop_title'
            );
            $textContent = $this->getUserStringPostValue(
                $data[$i]['id'],
                '_aioseop_description'
            );
            if (strlen($textContent) > 60) {
                $data[$i]['descriptionPost'] = substr(
                    $textContent,
                    0,
                    strpos($textContent, ' ', 60)
                );
            } else {
                $data[$i]['descriptionPost'] = substr($textContent, 0, 60);
            }

            // $image = $this->getUserStringPostValue($data[$i]["id"],'_thumbnail_id');
            $img = $this->entityManager
                ->getRepository(wpPosts::class)
                ->findOneBy(['postParent' => $data[$i]['id']]);
            if ($img) {
                $data[$i]['imagePost'] = $img->getGuid();
            } else {
                $data[$i]['imagePost'] = '';
            }
            $data[$i]['title_en'] = '';
            $data[$i]['content_en'] = '';
            $title_en = $this->entityManager
                ->getRepository(ExtTranslationWpPosts::class)
                ->findOneBy([
                    'foreign_key' => $data[$i]['id'],
                    'field' => 'postExcerpt',
                    'locale' => 'en_US',
                ]);
            if ($title_en) {
                $data[$i]['title_en'] = $title_en->getContent();
            }

            $content_en = $this->entityManager
                ->getRepository(ExtTranslationWpPosts::class)
                ->findOneBy([
                    'foreign_key' => $data[$i]['id'],
                    'field' => 'postContentFiltered',
                    'locale' => 'en_US',
                ]);
            if ($content_en) {
                $data[$i]['content_en'] = $content_en->getContent();
            }
        }
        return $data;
    }

    /**
     * @param $idPost
     * @return mixed[]
     * @throws DBALException
     */
    public function getLastBlogPost($number)
    {
        $conn = $this->entityManager->getConnection();

        $sql = 'SELECT DISTINCT wp.* ,wp.guid as title,wp.guid as descriptionPost,
        wp.guid as imagePost
        FROM wp_posts wp
        JOIN wp_term_relationships wtr ON wp.id=wtr.object_id
        JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
        JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
        WHERE wp.post_type =:categoriePost and wp.post_status =:statut
        ORDER BY wp.ID DESC
        LIMIT ' . $number;

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'statut' => 'publish',
            'categoriePost' => 'post'
        ]);

        $data = $stmt->fetchAllAssociative();
        for ($i = 0; $i < sizeof($data); $i++) {
            $data[$i]['title'] = $this->getUserStringPostValue(
                $data[$i]['id'],
                '_aioseop_title'
            );
            $textContent = $this->getUserStringPostValue(
                $data[$i]['id'],
                '_aioseop_description'
            );
            if (strlen($textContent) > 60) {
                $data[$i]['descriptionPost'] = substr(
                    $textContent,
                    0,
                    strpos($textContent, ' ', 60)
                );
            } else {
                $data[$i]['descriptionPost'] = substr($textContent, 0, 60);
            }

            // $image = $this->getUserStringPostValue($data[$i]["id"],'_thumbnail_id');
            $img = $this->entityManager
                ->getRepository(wpPosts::class)
                ->findOneBy(['postParent' => $data[$i]['id']]);
            if ($img) {
                $data[$i]['imagePost'] = $img->getGuid();
            } else {
                $data[$i]['imagePost'] = '';
            }
            $data[$i]['title_en'] = '';
            $data[$i]['content_en'] = '';
            $title_en = $this->entityManager
                ->getRepository(ExtTranslationWpPosts::class)
                ->findOneBy([
                    'foreign_key' => $data[$i]['id'],
                    'field' => 'postExcerpt',
                    'locale' => 'en_US',
                ]);
            if ($title_en) {
                $data[$i]['title_en'] = $title_en->getContent();
            }

            $content_en = $this->entityManager
                ->getRepository(ExtTranslationWpPosts::class)
                ->findOneBy([
                    'foreign_key' => $data[$i]['id'],
                    'field' => 'postContentFiltered',
                    'locale' => 'en_US',
                ]);
            if ($content_en) {
                $data[$i]['content_en'] = $content_en->getContent();
            }
        }
        return $data;
    }

    //Announces data indicator
    /**
     * @param $tag
     * @return int
     */

    public function countAnnouncesKnowingTags($tag, $postAuthor)
    {
        $query = $this->entityManager
            ->createQuery('SELECT count(wp.id) as nbrPosts FROM App\Entity\WpPosts wp WHERE wp.postAuthor=:postAuthor and wp.postStatus=:statusPost')
            ->setParameters([
                'statusPost' => $tag,
                'postAuthor' => $postAuthor,
            ]);
        $result = $query->getSingleResult()['nbrPosts'];
        return $result;
    }
    //Read userMeta with key and userId
    /**
     * @param $userId
     * @param $metaKey
     * @return array|null|object
     */
    public function readIdentityFiles($userId, $metaKey)
    {
        $identite = null;

        $r = $this->entityManager
            ->createQuery('SELECT tt FROM App\Entity\WpUsermeta tt WHERE tt.userId =:id and tt.metaKey LIKE :meta ORDER BY tt.umetaId DESC')
            ->setParameter('id', $userId)
            ->setParameter('meta', '%' . $metaKey . '%')
            ->getResult();
        if (count($r) != 0) {
            foreach ($r as $key) {
                $mofifyResult = @unserialize($key->getMetaValue());
                if ($mofifyResult['type'] == 'IDENTITY_PROOF') {
                    $identite = $mofifyResult;
                    return $identite;
                }
            }
        }
        return $identite;
    }

    //Read userMeta with key and userId
    /**
     * @param $userId
     * @param $metaKey
     * @return array|null|object
     */
    public function readSubscribeFiles($userId, $metaKey)
    {
        $enregistrement = null;

        $r = $this->entityManager
            ->createQuery('SELECT tt FROM App\Entity\WpUsermeta tt WHERE tt.userId =:id and tt.metaKey LIKE :meta ORDER BY tt.umetaId DESC')
            ->setParameter('id', $userId)
            ->setParameter('meta', '%' . $metaKey . '%')
            ->getResult();
        if (count($r) != 0) {
            foreach ($r as $key) {
                $mofifyResult = @unserialize($key->getMetaValue());
                if ($mofifyResult['type'] == 'REGISTRATION_PROOF') {
                    $enregistrement = $mofifyResult;
                    return $enregistrement;
                }
            }
        }
        return $enregistrement;
    }

    /* =======================================    CES FONCTIONS S'APPLIQUE A L'ESPACE ADMIN =============================*/
    /**
     * @param $userId
     * @param $metaKey
     * @param $metaValue
     */
    public function createUserMeta1($userId, $metaKey, $metaValue)
    {
        $additionnalData = new WpUsermeta();
        $additionnalData->setMetaKey($metaKey);
        $additionnalData->setMetaValue($metaValue);
        $additionnalData->setUserId($userId);
        $this->entityManager->persist($additionnalData);
        $this->entityManager->flush();
    }

    // Fonction qui compte le nombre d'utilisateur
    /**
     * @return int
     */
    public function totalUser()
    {
        $query = $this->entityManager->createQuery(
            'SELECT count(u.id) as nb FROM App\Entity\User u'
        );
        $users = $query->getSingleResult();

        return $users['nb'];
    }

    // Fonction qui compte les annonces, commandes
    /**
     * @param $postType
     * @return int
     */
    public function totalPost($postType)
    {
        $query = $this->entityManager
            ->createQuery(
                'SELECT p FROM App\Entity\WpPosts p WHERE p.postType=:pt '
            )
            ->setParameter('pt', $postType);
        $post = $query->getResult();

        return $post;
    }

    // Fonction qui compte les Inscriptions par landing page
    /**
     * @param $postType
     * @return int
     */
    public function totalPostLandingPage($status)
    {
        $query = $this->entityManager
            ->createQuery(
                'SELECT p FROM App\Entity\WpPosts p WHERE p.postStatus=:pt '
            )
            ->setParameter('pt', $status);
        $post = $query->getResult();

        return $post;
    }

    // Fonction qui permet de lire un fichier de traduction
    /**
     * @param $file
     * @return array
     */
    public function getFile($file)
    {
        try {
            $value = Yaml::parseFile($file);
        } catch (ParseException $exception) {
            printf(
                'Unable to parse the YAML string: %s',
                $exception->getMessage()
            );
        }

        return $value;
    }

    // Fonction qui permet d'ecrire dans  un fichier de traduction
    /**
     * @param $file
     * @param $key
     * @param $val
     * @return int
     */
    public function writteFile($file, $key, $val)
    {
        $r = 1;
        $data = $this->getFile($file);
        $k = explode('.', $key);
        if (sizeof($k) == 2) {
            $data[$k[0]][$k[1]] = $val;
        } elseif (sizeof($k) == 3) {
            $data[$k[0]][$k[1]][$k[2]] = $val;
        } else {
            $r = 0;
        }
        $yaml = Yaml::dump($data);
        file_put_contents($file, $yaml);

        return $r;
    }

    // Fontion qui permet d'uploder les Fichiers
    /**
     * @param $file
     * @param $postAuthor
     * @return string
     */
    public function upload($file, $destination, $postAuthor)
    {
        /** @var Symfony\Component\HttpFoundation\File\UploadedFile $file */

        $ids = '';
        $date = new DateTime();
        if (!empty($file[0])) {
            for ($i = 0; $i < sizeof($file); $i++) {
                if (!empty($file[$i])) {
                    $fileName =
                        $postAuthor .
                        md5(uniqid()) .
                        '.' .
                        $file[$i]->guessExtension();
                    $ext = $file[$i]->guessExtension();
                    // moves the file to the directory where brochures are stored
                    $file[$i]->move($destination, $fileName);
                    $id = $this->createPosts(
                        $postAuthor,
                        $date,
                        $date,
                        $fileName,
                        $fileName,
                        $fileName,
                        'inherit',
                        'open',
                        'closed',
                        $fileName,
                        $fileName,
                        $fileName,
                        $fileName,
                        $date,
                        $date,
                        $fileName,
                        0,
                        $this->local . '/uploads/' . $fileName,
                        0,
                        'attachment',
                        'image/' . $ext,
                        0,
                        0
                    );
                    if ($ids != '') {
                        $ids = $ids . ',' . $id;
                    } else {
                        $ids = '' . $id;
                    }
                }
            }
        }
        return $ids;
    }

    // Fontion qui permet d'uploder les Fichiers
    /**
     * @param $file
     * @param $postAuthor
     * @return string
     */
    public function upload1($file, $destination, $postAuthor)
    {
        /** @var Symfony\Component\HttpFoundation\File\UploadedFile $file */

        $ids = '';
        $date = new DateTime();
        if (!empty($file[0])) {
            for ($i = 0; $i < sizeof($file); $i++) {
                if (!empty($file[$i]) && $file[$i]->guessExtension()) {
                    // moves the file to the directory where brochures are stored
                    $fileName =
                        $postAuthor .
                        md5(uniqid()) .
                        '.' .
                        $file[$i]->guessExtension();
                    $ext = $file[$i]->guessExtension();
                    $file[$i]->move($destination, $fileName);
                    //End upload
                    //Compression
                    if (filesize($destination . '/' . $fileName) > 200 * 1024) {
                        // moves the compress file to the directory where brochures are stored
                        $compress_file = 'cmp_' . $fileName;
                        $compress_image = $this->compressImage(
                            $destination . '/' . $fileName,
                            $destination . '/' . $compress_file,
                            50,
                            4
                        );
                        unlink($destination . '/' . $fileName);
                        $fileName = $compress_file;
                        //End move file
                    }
                    // End compression

                    $ids = $this->local . '/uploads/' . $fileName;
                }
            }
        }
        return $ids;
    }

    // Fontion qui permet d'uploder les avatar
    /**
     * @param $file
     * @param $userId
     * @param $destination
     * @return string
     */
    public function uploadAvatar($userId, $file, $destination)
    {
        /** @var Symfony\Component\HttpFoundation\File\UploadedFile $file */

        $ids = '';
        if (!empty($file)) {
            // moves the file to the directory where brochures are stored
            $fileName = md5(uniqid()) . '.' . $file->guessExtension();
            $file->move($destination, $fileName);
            // End move file
            //Compression
            if (filesize($destination . '/' . $fileName) > 200 * 1024) {
                // moves the compress file to the directory where brochures are stored
                $compress_file = 'cmp_' . $fileName;
                $compress_image = $this->compressImage(
                    $destination . '/' . $fileName,
                    $destination . '/' . $compress_file
                );
                unlink($destination . '/' . $fileName);
                $fileName = $compress_file;
                //End move file
            }
            //End compression
            $avatars = $this->readUserMeta($userId, 'basic_user_avatar');

            if ($avatars && $avatars->getMetaValue()) {
                $img = @unserialize($avatars->getMetaValue());
                $img[sizeof($img)] = $this->local . '/avatars/' . $fileName;
                $ids = @serialize($img);
            } else {
                $img = [$this->local . '/avatars/' . $fileName];
                $ids = @serialize($img);
            }
        }
        return $ids;
    }

    // Fontion qui permet d'uploder les fichiers de preuve d'identité
    /**
     * @param $file
     * @param $userId
     * @param $destination
     * @return string
     */
    public function uploadIdentityProof($userId, $file, $destination)
    {
        /** @var Symfony\Component\HttpFoundation\File\UploadedFile $file */

        $ids = '';
        if (!empty($file)) {
            $myDate = new DateTime();
            $document_name = $file->getClientOriginalName();
            $creation_date = date_timestamp_get($myDate);
            $type = 'IDENTITY_PROOF';
            $metakey = 'kyc_document_' . $creation_date;
            $url = $this->local . '/identite/' . $document_name;
            //Vérifier qu'il existe des documents et récuperer le plus récent;

            //CREATE TABLE AND SERIALIZE
            $data = @serialize([
                'type' => $type,
                'id_mp_doc' => $creation_date,
                'creation_date' => $creation_date,
                'document_name' => $document_name,
                'url' => $url,
            ]);

            // moves the file to the directory where brochures are stored
            $file->move($destination, $document_name);
        }
        return ['data' => $data, 'metakey' => $metakey];
    }

    // Fontion qui permet d'uploder les fichiers de preuve d'enregistrement
    /**
     * @param $file
     * @param $userId
     * @param $destination
     * @return string
     */
    public function uploadRegistrationProof($userId, $file, $destination)
    {
        /** @var Symfony\Component\HttpFoundation\File\UploadedFile $file */

        $ids = '';
        if (!empty($file)) {
            $myDate = new DateTime();
            $document_name = $file->getClientOriginalName();
            $creation_date = date_timestamp_get($myDate);
            $type = 'REGISTRATION_PROOF';
            $metakey = 'kyc_document_' . $creation_date;
            $url = $this->local . '/enregistrement/' . $document_name;
            //Vérifier qu'il existe des documents et récuperer le plus récent;

            //CREATE TABLE AND SERIALIZE
            $data = @serialize([
                'type' => $type,
                'id_mp_doc' => $creation_date,
                'creation_date' => $creation_date,
                'document_name' => $document_name,
                'url' => $url,
            ]);

            // moves the file to the directory where brochures are stored
            $file->move($destination, $document_name);
        }
        return ['data' => $data, 'metakey' => $metakey];
    }

    // Fontion qui permet d'uploder les Fichiers
    /**
     * @param $file
     * @param $postAuthor
     * @return string
     */
    public function portfolio($file, $destination, $postAuthor)
    {
        /** @var Symfony\Component\HttpFoundation\File\UploadedFile $file */

        $ids = '';
        $date = new DateTime();
        if (!empty($file[0])) {
            for ($i = 0; $i < sizeof($file); $i++) {
                if (!empty($file[$i])) {
                    $fileName =
                        $postAuthor .
                        md5(uniqid()) .
                        '.' .
                        $file[$i]->guessExtension();
                    $ext = $file[$i]->guessExtension();
                    $file[$i]->move($destination, $fileName);
                    if (filesize($destination . '/' . $fileName) > 200 * 1024) {
                        // moves the compress file to the directory where brochures are stored
                        $compress_file = 'cmp_' . $fileName;
                        $compress_image = $this->compressImage(
                            $destination . '/' . $fileName,
                            $destination . '/' . $compress_file
                        );
                        unlink($destination . '/' . $fileName);
                        $fileName = $compress_file;
                        //End move file
                    }
                    $id = $this->createPosts1(
                        $postAuthor,
                        $date,
                        $date,
                        $fileName,
                        $fileName,
                        $fileName,
                        'inherit',
                        'open',
                        'closed',
                        $fileName,
                        $fileName,
                        $fileName,
                        $fileName,
                        $date,
                        $date,
                        $fileName,
                        0,
                        $this->local . '/portfolio/' . $fileName,
                        0,
                        'attachment',
                        'image/' . $ext,
                        0,
                        0
                    );
                    if ($ids != '') {
                        $ids = $ids . ',' . $id;
                    } else {
                        $ids = '' . $id;
                    }
                }
            }
        }
        return $ids;
    }

    // Fontion qui permet d'uploder les Fichiers
    /**
     * @param $file
     * @param $postAuthor
     * @return string
     */
    public function uploadImageInFolder(
        $file,
        $destination,
        $folder = 'marketing'
    ) {
        /** @var Symfony\Component\HttpFoundation\File\UploadedFile $file */

        $url = '';
        $date = new DateTime();
        if (!empty($file[0])) {
            $fileName = md5(uniqid()) . '.' . $file[0]->guessExtension();
            $ext = $file[0]->guessExtension();
            $file[0]->move($destination, $fileName);
            if (filesize($destination . '/' . $fileName) > 200 * 1024) {
                // moves the compress file to the directory where brochures are stored
                $compress_file = 'cmp_' . $fileName;
                $compress_image = $this->compressImage(
                    $destination . '/' . $fileName,
                    $destination . '/' . $compress_file
                );
                unlink($destination . '/' . $fileName);
                $fileName = $compress_file;
                //End move file
                $url = $this->local . '/' . $folder . '/' . $fileName;
            } else {
                $url = $this->local . '/' . $folder . '/' . $fileName;
            }
        }
        return $url;
    }

    public function imagesAnnoncesUpload($file, $destination, $postAuthor)
    {
        /** @var Symfony\Component\HttpFoundation\File\UploadedFile $file */

        $ids = '';
        $date = new DateTime();
        for ($i = 0; $i < sizeof($file); $i++) {
            if (!empty($file[$i])) {
                $fileName =
                    $postAuthor .
                    md5(uniqid()) .
                    '.' .
                    $file[$i]->guessExtension();
                $ext = $file[$i]->guessExtension();
                $file[$i]->move($destination, $fileName);
                if (filesize($destination . '/' . $fileName) > 200 * 1024) {
                    // moves the compress file to the directory where brochures are stored
                    $compress_file = 'cmp_' . $fileName;
                    $compress_image = $this->compressImage(
                        $destination . '/' . $fileName,
                        $destination . '/' . $compress_file
                    );
                    unlink($destination . '/' . $fileName);
                    $fileName = $compress_file;
                    //End move file
                }
                $id = $this->createPosts1(
                    $postAuthor,
                    $date,
                    $date,
                    $fileName,
                    $fileName,
                    $fileName,
                    'inherit',
                    'open',
                    'closed',
                    $fileName,
                    $fileName,
                    $fileName,
                    $fileName,
                    $date,
                    $date,
                    $fileName,
                    0,
                    $this->local . '/annonces/' . $fileName,
                    0,
                    'attachment',
                    'image/' . $ext,
                    0,
                    0
                );
                if ($ids != '') {
                    $ids = $ids . ',' . $id;
                } else {
                    $ids = '' . $id;
                }
            }
        }
        return $ids;
    }

    //GESTION DES CONTENU

    // Recuperation des Nav_Menu_item
    /**
     * @param $id
     * @return array|null|object
     */
    public function naveMenuItem($id)
    {
        $query = $this->entityManager
            ->createQuery(
                'SELECT tt FROM App\Entity\WpPosts tt
                        JOIN App\Entity\WpTermRelationships t
                        WHERE tt.id=t.objectId and tt.postStatus!=:ps and tt.postStatus!=:ps1 and t.termTaxonomyId=:id and tt.postType=:idSousMenu
                        ORDER BY tt.menuOrder ASC'
            )
            ->setParameter('id', $id)
            ->setParameter('ps', 'trash')
            ->setParameter('ps1', 'draft')
            ->setParameter('idSousMenu', 'nav_menu_item');
        $menuItem = $query->getResult();
        return $menuItem;
    }

    /**
     * @param $id
     * @return array|null|object
     */
    public function getCategoryFromAnnounce($annonce)
    {
        $query = $this->entityManager
            ->createQuery(
                'SELECT tt.termTaxonomyId, tt.taxonomy, tt.description, tt.parent, tt.count, t.name, t.slug, t.termId
            FROM App\Entity\WpTermTaxonomy tt
            JOIN App\Entity\WpTerms t
            JOIN App\Entity\WpTermRelationships tr
            JOIN App\Entity\WpPosts p
            WHERE tt.termId=t.termId and tr.termTaxonomyId = tt.termTaxonomyId and tr.objectId =:annonce and p.id = tr.objectId
            ORDER BY t.name ASC'
            )
            ->setParameter('annonce', $annonce);
        $category = $query->setMaxResults(1)->getResult();

        return $category;
    }

    // Recuperation des Nav_Menu_item
    /**
     * @param $id
     * @return array|null|object
     */
    public function naveMenuItemBrouillon()
    {
        $query = $this->entityManager
            ->createQuery(
                'SELECT tt.id, tt.postTitle, tt.postStatus, te.name FROM App\Entity\WpPosts tt
                                          JOIN App\Entity\WpTermRelationships t
                                          JOIN App\Entity\WpTerms te
                                          WHERE tt.id=t.objectId and tt.postStatus=:ps and t.termTaxonomyId=te.termId and tt.postType=:idSousMenu
                                          ORDER BY tt.menuOrder ASC'
            )
            ->setParameter('ps', 'draft')
            ->setParameter('idSousMenu', 'nav_menu_item');
        $menuItem = $query->getResult();

        return $menuItem;
    }

    // Recuperation des Nav_Menu_item
    /**
     * @param $id
     * @return array|null|object
     */
    public function naveMenuTrash()
    {
        $query = $this->entityManager
            ->createQuery(
                'SELECT tt.id, tt.postTitle, tt.postStatus, te.name FROM App\Entity\WpPosts tt
                                          JOIN App\Entity\WpTermRelationships t
                                          JOIN App\Entity\WpTerms te
                                          WHERE tt.id=t.objectId and tt.postStatus=:ps and t.termTaxonomyId=te.termId and tt.postType=:idSousMenu
                                          ORDER BY tt.menuOrder ASC'
            )
            ->setParameter('ps', 'trash')
            ->setParameter('idSousMenu', 'nav_menu_item');
        $menuItem = $query->getResult();

        return $menuItem;
    }

    // modification d'une page
    /**
     * @param $id
     * @param $val
     * @return int
     */
    public function updatePage(
        $id,
        $idMenu,
        $val,
        $status,
        $locale = null,
        $title_en = null,
        $image = null
    ) {
        $query = '';
        if ($locale == 'en') {
            $query = $this->entityManager
                ->createQuery(
                    'UPDATE App\Entity\WpPosts p
             SET p.postContentFiltered=:pc, p.postStatus=:st,
             p.postExcerpt=:t_en,p.guid=:img
             WHERE p.id=:id'
                )
                ->setParameter('id', $id)
                ->setParameter('img', $image)
                ->setParameter('st', $status)
                ->setParameter('t_en', $title_en)
                ->setParameter('pc', $val);
            //Update en_US Field
            $query_en = $this->entityManager
                ->createQuery(
                    'SELECT tt
                    FROM App\Entity\ExtTranslationWpPosts tt
                    WHERE tt.foreign_key=:id and tt.locale=:loc and tt.field=:field'
                )
                ->setParameter('id', $id)
                ->setParameter('field', 'postContentFiltered')
                ->setParameter('loc', 'en_US');
            $contenu_en = $query_en->getArrayResult();
            if (sizeof($contenu_en) > 0) {
                //Update English
                $query_en = $this->entityManager
                    ->createQuery(
                        'UPDATE App\Entity\ExtTranslationWpPosts p SET p.content=:pc
                    WHERE p.foreign_key=:idp and p.field=:field and p.locale=:loc'
                    )
                    ->setParameter('field', 'postContentFiltered')
                    ->setParameter('loc', 'en_US')
                    ->setParameter('idp', $id)
                    ->setParameter('pc', $val);
                $r = $query_en->getResult();
            } else {
                //CREATE English CONTENT
                $r = new ExtTranslationWpPosts();
                $r->setContent($val);
                $r->setField('postContentFiltered');
                $r->setObjectClass('App\Entity\WpPosts');
                $r->setLocale('en_US');
                $r->setforeignKey($id);
                $this->entityManager->persist($r);
                $this->entityManager->flush();
                $r = $r->getId();
            }
        } else {
            $query = $this->entityManager
                ->createQuery(
                    'UPDATE App\Entity\WpPosts p
             SET p.postContent=:pc, p.postStatus=:st,p.guid=:img
             WHERE p.id=:id'
                )
                ->setParameter('id', $id)
                ->setParameter('img', $image)
                ->setParameter('st', $status)
                ->setParameter('pc', $val);
        }

        $query1 = $this->entityManager
            ->createQuery(
                'UPDATE App\Entity\WpPosts p
                 SET p.postStatus=:st
                 WHERE p.id=:id'
            )
            ->setParameter('id', $idMenu)
            ->setParameter('st', $status);

        $r = $query->getResult();
        $r1 = $query1->getResult();
        return 1;
    }

    // modification d'une page
    /**
     * @param $id
     * @param $val
     * @return int
     */
    public function updateMenu($id, $val, $locale = null)
    {
        $query = $this->entityManager
            ->createQuery(
                'SELECT tt
                    FROM App\Entity\ExtTranslationWpPosts tt
                    WHERE tt.foreign_key=:id'
            )
            ->setParameter('id', $id);
        $contenus_en = $query->getArrayResult();
        if (sizeof($contenus_en) > 0) {
            if ($locale == 'en') {
                //Update PostExcerpt
                $query = $this->entityManager
                    ->createQuery(
                        'UPDATE App\Entity\WpPosts p SET p.postExcerpt=:pc WHERE p.id=:id'
                    )
                    ->setParameter('id', $id)
                    ->setParameter('pc', $val);

                $r = $query->getResult();
                //Update English
                $query = $this->entityManager
                    ->createQuery(
                        'UPDATE App\Entity\ExtTranslationWpPosts p SET p.content=:pc
                    WHERE p.foreign_key=:idp and p.field=:field and p.locale=:loc'
                    )
                    ->setParameter('field', 'postExcerpt')
                    ->setParameter('loc', 'en_US')
                    ->setParameter('idp', $id)
                    ->setParameter('pc', $val);
                $r = $query->getResult();
            } else {
                $query = $this->entityManager
                    ->createQuery(
                        'UPDATE App\Entity\WpPosts p SET p.postTitle=:pc WHERE p.id=:id'
                    )
                    ->setParameter('id', $id)
                    ->setParameter('pc', $val);

                $r = $query->getResult();
            }
        } else {
            if ($locale == 'en') {
                //Update PostExcerpt
                $query = $this->entityManager
                    ->createQuery(
                        'UPDATE App\Entity\WpPosts p SET p.postExcerpt=:pc WHERE p.id=:id'
                    )
                    ->setParameter('id', $id)
                    ->setParameter('pc', $val);

                $r = $query->getResult();
                //CREATE English CONTENT
                $r = new ExtTranslationWpPosts();
                $r->setContent($val);
                $r->setField('postExcerpt');
                $r->setObjectClass('App\Entity\WpPosts');
                $r->setLocale('en_US');
                $r->setforeignKey($id);
                $this->entityManager->persist($r);
                $this->entityManager->flush();
                $r = $r->getId();
            } else {
                $query = $this->entityManager
                    ->createQuery(
                        'UPDATE App\Entity\WpPosts p SET p.postTitle=:pc WHERE p.id=:id'
                    )
                    ->setParameter('id', $id)
                    ->setParameter('pc', $val);

                $r = $query->getResult();
            }
        }

        return $r;
    }

    // modification d'une categorie
    /**
     * @param $id
     * @param $val
     * @return int
     */
    public function updateCat($id, $val, $locale = null)
    {
        $idTerm = $this->entityManager
            ->getRepository(wpTermTaxonomy::class)
            ->findOneByTermTaxonomyId($id)
            ->getTermId();
        $count = count(
            $this->entityManager
                ->getRepository(wpTermTaxonomy::class)
                ->findByParent($id)
        );
        //Update count in Term Taxonomy
        $query = $this->entityManager
            ->createQuery('UPDATE App\Entity\WpTermTaxonomy p SET p.count=:ct, p.description=:dc WHERE p.termTaxonomyId=:id')
            ->setParameters(['id' => $id, 'ct' => $count, 'dc' => $val]);
        $r = $query->getResult();
        //End Update
        if ($locale == 'en') {
            $query = $this->entityManager
                ->createQuery(
                    'UPDATE App\Entity\ExtTranslationWpTerms p SET p.content=:pc WHERE p.foreign_key=:id
            and p.locale=:loc'
                )
                ->setParameters([
                    'id' => $idTerm,
                    'pc' => $val,
                    'loc' => 'en_US',
                ]);
            $r = $query->getResult();
        } else {
            $query = $this->entityManager
                ->createQuery(
                    'UPDATE App\Entity\WpTerms p SET p.name=:pc, p.slug=:sl WHERE p.termId=:id'
                )
                ->setParameter('id', $idTerm)
                ->setParameter('pc', $val)
                ->setParameter('sl', $this->slugify($val));
            $r = $query->getResult();
        }
        return $r;
    }

    public function updateCatWithDelivery(
        $id,
        $val,
        $frais_livraison,
        $locale = null
    ) {
        $idTerm = $id;
        $count = count(
            $this->entityManager
                ->getRepository(wpTermTaxonomy::class)
                ->findByParent($id)
        );
        //Update count in Term Taxonomy
        $query = $this->entityManager
            ->createQuery(
                'UPDATE App\Entity\WpTermTaxonomy p SET p.count=:ct, p.description=:dc WHERE p.termTaxonomyId=:id'
            )
            ->setParameters(['id' => $id, 'ct' => $count, 'dc' => $val]);
        $r = $query->getResult();
        //End Update
        if ($locale == 'en') {
            $query = $this->entityManager
                ->createQuery(
                    'UPDATE App\Entity\ExtTranslationWpTerms p
            SET p.content=:pc, p.termPrice=:fl
            WHERE p.foreign_key=:id
            and p.locale=:loc'
                )
                ->setParameters([
                    'id' => $idTerm,
                    'pc' => $val,
                    'fl' => $frais_livraison,
                    'loc' => 'en_US',
                ]);
            $r = $query->getResult();
        } else {
            $query = $this->entityManager
                ->createQuery(
                    'UPDATE App\Entity\WpTerms p SET p.name=:pc, p.slug=:sl, p.termPrice=:fl WHERE p.termId=:id'
                )
                ->setParameter('id', $idTerm)
                ->setParameter('pc', $val)
                ->setParameter('fl', $frais_livraison)
                ->setParameter('sl', $this->slugify($val));
            $r = $query->getResult();

            $query = $this->entityManager
                ->createQuery(
                    'UPDATE App\Entity\ExtTranslationWpTerms p SET p.content=:pc WHERE p.foreign_key=:id
            and p.locale=:loc'
                )
                ->setParameters(['id' => $idTerm, 'pc' => $val, 'loc' => 'fr']);
            $r = $query->getResult();
        }
        return $r;
    }

    /**
     * @param $id
     * @param $parent
     * @return int
     */
    public function updatePostParent($id, $parent)
    {
        $query = $this->entityManager
            ->createQuery(
                'UPDATE App\Entity\WpPosts p SET p.postParent=:pc WHERE p.id=:id'
            )
            ->setParameter('id', $id)
            ->setParameter('pc', $parent);
        $r = $query->getResult();
        return $r;
    }

    /**
     * @param $id
     * @param $parent
     * @param $name
     * @return int
     */
    public function updatePostParent1($id, $parent, $name)
    {
        $query = $this->entityManager
            ->createQuery(
                'UPDATE App\Entity\WpPosts p SET p.postTitle=:n, p.postParent=:pc WHERE p.id=:id'
            )
            ->setParameter('id', $id)
            ->setParameter('pc', $parent)
            ->setParameter('n', $name);
        $r = $query->getResult();

        return $r;
    }

    // recuperation de tous les post parent d'un post
    /**
     * @param $postParent
     * @param $postType
     * @return array|null|object
     */
    public function readPostParent($postParent, $postType)
    {
        $post = $this->entityManager->getRepository(WpPosts::class)->findBy(
            [
                'postParent' => $postParent,
                'postType' => $postType,
            ],
            ['id' => 'DESC']
        );

        return $post;
    }

    /**
     * @return object[]
     */
    public function readAllPost()
    {
        $post = $this->entityManager->getRepository(WpPosts::class)->findBy(
            [
                'postType' => 'product',
                'postStatus' => 'publish',
            ],
            ['id' => 'DESC']
        );

        return $post;
    }
    // Fonction qui recupere l'id d'une video youtube

    /**
     * @param $url
     * @return bool
     */
    public function getYouTubeId($url)
    {
        $parts = parse_url($url);
        if (isset($parts['query'])) {
            parse_str($parts['query'], $qs);
            if (isset($qs['v'])) {
                return $qs['v'];
            } elseif (isset($qs['vi'])) {
                return $qs['vi'];
            }
        }
        if (isset($parts['path'])) {
            $path = explode('/', trim($parts['path'], '/'));
            return $path[count($path) - 1];
        }
        return false;
    }

    //Mise a jour Mot de passe user

    /**
     * @param $id
     * @param $password
     * @return array|Response
     */
    public function updateUserPassword($id, $newPassword)
    {
        $user = $this->entityManager->getRepository(User::class)->find($id);
        if ($user) {
            $user->setPassword($newPassword);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $user;
        }
        return 0;
    }

    // recherche des annonce

    /**
     * @param $searchterm
     * @return array|null|object
     */
    public function search($searchterm)
    {
        $listeAnnonces = null;
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT wp.ID as id, wp.post_title as name,wp.guid,wp.post_name as slug,
            wp.post_author as idUser, wp.post_excerpt as calendrier, wp.guid as price, wp.guid as gallery,
            wtt.taxonomy as categorie_parent, wpSousCategorie.name as sousCategorie,
            wptCategorie.name as categorieParent
            FROM wp_posts wp
            JOIN wp_term_relationships wtr ON wp.ID=wtr.object_id
            JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
            JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
            JOIN wp_term_taxonomy wtt2 ON wtt.parent=wtt2.term_taxonomy_id
            JOIN wp_terms wptCategorie ON wtt2.term_id=wptCategorie.term_id
            WHERE wp.post_type =:categoriePost and wp.post_status=:post_status
            and (LOWER(wp.post_title) LIKE :searchterm or LOWER(wptCategorie.name) LIKE :searchterm or
            LOWER(wpSousCategorie.name) LIKE :searchterm)
            LIMIT 20';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'categoriePost' => 'product',
            'post_status' => 'publish',
            'searchterm' => '%' . strtolower($searchterm) . '%',
        ]);
        $listeAnnonces = $stmt->fetchAllAssociative();

        for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
            //Images
            $listeAnnonces[$i]['image'] = $this->getAllImageForAnnouces($listeAnnonces[$i]['id']);
            if (array_key_exists(0,$listeAnnonces[$i]['image']) && $listeAnnonces[$i]['image'][0]) {
                $listeAnnonces[$i]['image'] = $listeAnnonces[$i]['image'][0]->getGuid();
            } else {
                $listeAnnonces[$i]['image'] = $this->getAllSecondImageForAnnouces(
                    $listeAnnonces[$i]['id'],
                    'images_annonces'
                );
                if (
                    sizeof($listeAnnonces[$i]['image']) > 0 &&
                    $listeAnnonces[$i]['image'][0] &&
                    $listeAnnonces[$i]['image'][0] != ''
                ) {
                    $listeAnnonces[$i]['image'] = $listeAnnonces[$i]['image'][0]->getGuid();
                } else {
                    $listeAnnonces[$i]['image'] = 'data:image/gif;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==';
                }
            }
        }

        return $listeAnnonces;
    }

    /**
     * @param $searchterm
     * @return array|null|object
     */
    public function search_admin_content($searchterm, $type)
    {
        $conn = $this->entityManager->getConnection();
        $listeAnnonces = [];
        if ($type == 'annonce') {
            $sql = 'SELECT wp.ID as id, wp.post_title as name,wp.guid,wp.post_name as slug,
                wp.post_author as idUser, wp.post_excerpt as calendrier, wp.guid as price, wp.guid as gallery,
                wtt.taxonomy as categorie_parent, wpSousCategorie.name as sousCategorie,
                wptCategorie.name as categorieParent
                FROM wp_posts wp
                JOIN wp_term_relationships wtr ON wp.ID=wtr.object_id
                JOIN wp_term_taxonomy wtt ON wtr.term_taxonomy_id=wtt.term_taxonomy_id
                JOIN wp_terms wpSousCategorie ON wtt.term_id=wpSousCategorie.term_id
                JOIN wp_term_taxonomy wtt2 ON wtt.parent=wtt2.term_taxonomy_id
                JOIN wp_terms wptCategorie ON wtt2.term_id=wptCategorie.term_id
                WHERE wp.post_type =:categoriePost and wp.post_status=:post_status
                and (LOWER(wp.post_title) LIKE :searchterm or LOWER(wptCategorie.name) LIKE :searchterm or
                LOWER(wpSousCategorie.name) LIKE :searchterm)
                LIMIT 20';

            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'categoriePost' => 'product',
                'post_status' => 'publish',
                'searchterm' => '%' . strtolower($searchterm) . '%',
            ]);
            $listeAnnonces = $stmt->fetchAllAssociative();
            for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
                $listeAnnonces[$i]['url'] = $this->router->generate(
                    'annonces_detailsAnnonce',
                    ['id' => $listeAnnonces[$i]['slug']],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );
                //Images
                $array = $this->getAllImageForAnnouces($listeAnnonces[$i]['id']);
                $listeAnnonces[$i]['image'] = end($array);
                
                if ($listeAnnonces[$i]['image']) {
                    $listeAnnonces[$i]['image'] = $listeAnnonces[$i]['image']->getGuid();
                } else {
                    $listeAnnonces[$i]['image'] = $this->getAllSecondImageForAnnouces(
                        $listeAnnonces[$i]['id'],
                        'images_annonces'
                    );
                    if (
                        sizeof($listeAnnonces[$i]['image']) > 0 &&
                        $listeAnnonces[$i]['image'][0] &&
                        $listeAnnonces[$i]['image'][0] != ''
                    ) {
                        $listeAnnonces[$i]['image'] = $listeAnnonces[$i]['image'][0]->getGuid();
                    }
                }
            }
        } elseif ($type == 'blog') {
            $sql = 'SELECT DISTINCT wp.* ,wp.guid as title,wp.guid as descriptionPost,
                wp.guid as imagePost, wp_postmeta.meta_value as titre_meta
                FROM wp_posts wp
                LEFT JOIN wp_postmeta ON wp_postmeta.post_id = wp.ID
                WHERE wp.post_type =:categoriePost and wp.post_status=:statut and wp_postmeta.meta_key=:metakey
                and LOWER(wp_postmeta.meta_value) LIKE :searchterm
                ORDER BY wp.ID DESC';

            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'statut' => 'publish',
                'categoriePost' => 'post',
                'metakey' => '_aioseop_title',
                'searchterm' => '%' . strtolower($searchterm) . '%',
            ]);

            $array1 = $stmt->fetchAllAssociative();

            $sql = 'SELECT DISTINCT wp.* ,wp.guid as title,wp.guid as descriptionPost,
                    wp.guid as imagePost, wp.post_content as titre_meta
                    FROM wp_posts wp
                    WHERE wp.post_type =:categoriePost and wp.post_status=:statut
                    and LOWER(wp.post_title) LIKE :searchterm
                    ORDER BY wp.ID DESC';

            $stmt = $conn->prepare($sql);
            $stmt = $stmt->executeQuery([
                'statut' => 'publish',
                'categoriePost' => 'post',
                'searchterm' => '%' . strtolower($searchterm) . '%',
            ]);

            $array2 = $stmt->fetchAllAssociative();
            $tab = array_merge($array1, $array2);
            $listeAnnonces = $this->multi_unique($tab, 'id');

            for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
                $listeAnnonces[$i]['name'] = $this->getUserStringPostValue(
                    $listeAnnonces[$i]['id'],
                    '_aioseop_title'
                );
                if ($listeAnnonces[$i]['name'] == '') {
                    $listeAnnonces[$i]['name'] =
                        $listeAnnonces[$i]['post_title'];
                }
                $listeAnnonces[$i]['url'] = $this->router->generate(
                    'blog_details_postBlog',
                    ['slug' => $listeAnnonces[$i]['post_name']],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                $listeAnnonces[$i]['descriptionPost'] = $this->tronquer_texte(
                    $listeAnnonces[$i]['post_content'],
                    50
                );

                $img = $this->entityManager
                    ->getRepository(wpPosts::class)
                    ->findOneBy([
                        'postParent' => $listeAnnonces[$i]['id'],
                        'postType' => 'attachment',
                    ]);
                if ($img) {
                    $listeAnnonces[$i]['image'] = $img->getGuid();
                }
                $listeAnnonces[$i]['title_en'] = '';
                $listeAnnonces[$i]['content_en'] = '';
                $title_en = $this->entityManager
                    ->getRepository(ExtTranslationWpPosts::class)
                    ->findOneBy([
                        'foreign_key' => $listeAnnonces[$i]['id'],
                        'field' => 'postExcerpt',
                        'locale' => 'en_US',
                    ]);
                if ($title_en) {
                    $listeAnnonces[$i]['title_en'] = $title_en->getContent();
                }

                $content_en = $this->entityManager
                    ->getRepository(ExtTranslationWpPosts::class)
                    ->findOneBy([
                        'foreign_key' => $listeAnnonces[$i]['id'],
                        'field' => 'postContentFiltered',
                        'locale' => 'en_US',
                    ]);
                if ($content_en) {
                    $listeAnnonces[$i]['content_en'] = $content_en->getContent();
                }
            }
        } elseif ($type == 'ops_speciale') {
            $listeAnnonces = $this->getAllSpecialOpsLike($searchterm);
            for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
                $listeAnnonces[$i]['url'] = $this->router->generate(
                    'marketing_details_ops_speciales',
                    ['slug' => $listeAnnonces[$i]['slug']],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );
                //Images
                $listeAnnonces[$i]['image'] = $listeAnnonces[$i]['articles'][0]->getGuid();
                $listeAnnonces[$i]['name'] = $listeAnnonces[$i]['titre'];
            }
        } elseif ($type == 'promo_com') {
            $listeAnnonces = $this->getAllPromoComLike($searchterm);
            for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
                $listeAnnonces[$i]['url'] = $this->router->generate(
                    'marketing_details_promo_com',
                    ['slug' => $listeAnnonces[$i]['slug']],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );
                //Images
                $listeAnnonces[$i]['image'] = $listeAnnonces[$i]['articles'][0]->getGuid();
                $listeAnnonces[$i]['name'] = $listeAnnonces[$i]['titre'];
            }
        }elseif ($type == 'univers_trust') {
            $listeAnnonces = $this->getAllExperiencesLike($searchterm);
            for ($i = 0; $i < sizeof($listeAnnonces); $i++) {
                $listeAnnonces[$i]['url'] = $this->router->generate(
                    'marketing_details_univers_trust',
                    ['slug' => $listeAnnonces[$i]['slug']],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );
                //Images
                $listeAnnonces[$i]['image'] = $listeAnnonces[$i]['image_couverture'];
                $listeAnnonces[$i]['name'] = $listeAnnonces[$i]['titre'];
            }
        }

        return $listeAnnonces;
    }

    /**
     * @param $searchterm
     * @return array|null|object
     */
    public function search_user($searchterm)
    {
        $q = $this->entityManager
            ->createQuery(
                "SELECT a.email_canonical as email, a.id, b.metaValue as firstName, c.metaValue as lastName
             FROM App\Entity\User a
             JOIN App\Entity\WpUsermeta b
             JOIN App\Entity\WpUsermeta c
             WHERE a.id=b.userId and a.id=c.userId and b.metaKey =:cleFirstName and c.metaKey =:cleLastName and (b.metaValue LIKE :first_name or c.metaValue LIKE :last_name or a.email_canonical LIKE :email)"
            )
            ->setParameter('last_name', '%' . $searchterm . '%')
            ->setParameter('first_name', '%' . $searchterm . '%')
            ->setParameter('email', '%' . $searchterm . '%')
            ->setParameter('cleFirstName', 'first_name')
            ->setParameter('cleLastName', 'last_name')
            ->setMaxResults(20);

        $post = $q->getResult();
        /*dd($post);die();*/
        return $post;
    }

    //mailing moification du user Statut

    /**
     * @param $id
     * @param $statut
     * @return mixed
     */
    public function updateUserStatus($id, $statut)
    {
        $u = $this->userById($id);
        $u->setUserStatus($statut);

        $this->entityManager->persist($u);
        $this->entityManager->flush();
        return $u->getId();
    }

    /**
     * @param $id
     * @param $statut
     * @return mixed
     */
    public function updatePostStatus($id, $statut)
    {
        $p = $this->entityManager
            ->getRepository(WpPosts::class)
            ->findOneById($id);
        $p->setPostStatus($statut);

        $this->entityManager->persist($p);
        $this->entityManager->flush();
        return $p->getId();
    }

    /**
     * @param $id
     * @return mixed
     */
    public function postById($id)
    {
        $p = $this->entityManager
            ->getRepository(WpPosts::class)
            ->findOneById($id);

        return $p;
    }

    /**
     * @return object[]
     */
    public function getAllUser()
    {
        $p = $this->entityManager->getRepository(User::class)->findAll();
        return $p;
    }
    // recuperation de tous les post parent d'un post
    /**
     * @param $userId
     * @param $role
     * @return bool
     */
    public function devenirPro($userId, $role)
    {
        $u = $this->entityManager
            ->getRepository(User::class)
            ->findOneById($userId);
        $u->setRoles([$role]);
        $this->entityManager->persist($u);
        $this->entityManager->flush();

/*        $query = $this->entityManager
            ->createQuery('UPDATE App\Entity\User p SET p.roles=:pc WHERE p.id=:id')
            ->setParameter('id', $userId)
            ->setParameter('pc', "[\"" . $role . "\"]");
        $r = $query->getResult();*/

        $query = $this->entityManager
            ->createQuery('SELECT wc FROM App\Entity\WpUsermeta wc
        where wc.userId=:id and wc.metaKey like :cle')
            ->setParameters(['id' => $userId, 'cle' => '%kyc_document%']);
        $data = $query->getResult();
        for ($i = 0; $i < sizeof($data); $i++) {
            $this->entityManager->remove($data[$i]);
        }
        $this->entityManager->flush();

        return true;
    }

    // recuperation de tous les users qui ont demandes a vendre des produits

    /**
     * @return array|list
     * @throws Exception
     */
    public function getAllUserRequestReferenceProduct()
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT wp_users.ID as id,wp_users.roles as permissions, wp_users.email_canonical as email,umn.meta_value as nom,
        ump.meta_value as prenom,umd.meta_value as etat,umd.umeta_id, umn.meta_value as kyc_identite, umn.meta_value as kyc_enregistrement,
        umn.meta_value as kyc_kbis, umn.meta_value as kyc_statuts, umn.meta_value as kyc_shareholder
        FROM wp_users
        LEFT JOIN wp_usermeta umn on umn.user_id=wp_users.ID
        LEFT JOIN wp_usermeta ump on ump.user_id=wp_users.ID
        LEFT JOIN wp_usermeta umd on umd.user_id=wp_users.ID 
        WHERE umn.meta_key =:first_name and ump.meta_key =:last_name and umd.meta_key =:demand
        ORDER BY umd.meta_value ASC';
        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'demand' => 'demande_referencement_produit',
        ]);
        $data = $stmt->fetchAllAssociative();
        //dd($data);
        return $data;
    }

    // Manipuler des textes

    /**
     * @param $texte
     * @param $nbchar
     * @return null|string
     */
    public function tronquer_texte($texte, $nbchar): ?string
    {
        return strlen($texte) > $nbchar
            ? substr(
                substr($texte, 0, $nbchar),
                0,
                strrpos(substr($texte, 0, $nbchar) . '...', ' ')
            )
            : $texte;
    }

    // DisableProfile

    /**
     * @param int $id
     * @return mixed
     */
    public function deleteProfil(int $id)
    {
        $user = $this->entityManager->getRepository(User::class)->findOneById($id);
        $user->setEnabled(0);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user->getId();
    }

    /**
     * @param $id
     * @param $produit
     * @param $prix
     * @return mixed
     */
    public function updateCommande($id, $produit, $prix)
    {
        $p = $this->postById($id);
        $p->setPostContent($produit);
        $p->setPostParent($prix);
        $this->entityManager->persist($p);
        $this->entityManager->flush();

        return $p->getId();
    }

    /**
     * @param $id
     * @param $date
     * @param $status
     * @param $client
     * @return int
     */
    public function updateCommande1($id, $date, $status)
    {
        $p = $this->postById($id);

        if ($p) {
            $p->setPostStatus($status);
            $p->setPostDate(new DateTime($date));

            $this->entityManager->persist($p);
            $this->entityManager->flush();

            return $p->getId();
        } else {
            return 1;
        }
    }

    /**
     * @param $userId
     * @param $email
     * @param $password
     * @param $role
     * @return mixed
     */
    public function updateUseremailPassRole($userId, $email, $password, $role)
    {
        $u = $this->userById($userId);
        if ($email) {
            $u->setEmail($email);
        }
        if ($password) {
            $u->setPassword(password_hash($password, PASSWORD_BCRYPT));
        }
        if ($role) {
            $u->setRoles([$role]);
        }
        $this->entityManager->persist($u);
        $this->entityManager->flush();

        return $u->getId();
    }

    /**
     * @param $id
     * @return bool
     * @throws DBALException
     */
    public function deleteProfilAll($id)
    {
        $conn = $this->entityManager->getConnection();
        //Delete chat data
        $sql1 = 'DELETE FROM wp_messages WHERE wp_messages.sender_id =:id or wp_messages.addressee_id =:id';
        $stmt = $conn->prepare($sql1);
        $stmt = $stmt->executeQuery(['id' => $id]);

        $sql1 = 'DELETE FROM wp_channels WHERE wp_channels.first_user_id =:id or wp_channels.second_user_id =:id';
        $stmt = $conn->prepare($sql1);
        $stmt = $stmt->executeQuery(['id' => $id]);

        //delete usermeta
        $usermetas = $this->entityManager->getRepository(WpUsermeta::class)->findByUserId($id);
        for ($i = 0; $i < sizeof($usermetas); $i++) {
            $this->entityManager->remove($usermetas[$i]);
            $this->entityManager->flush();
        }

        //delete post
        $posts = $this->entityManager->getRepository(WpPosts::class)->findByPostAuthor($id);
        for ($i = 0; $i < sizeof($posts); $i++) {
            $wp_post_meta = $this->entityManager->getRepository(WpPostmeta::class)->findByPostId($posts[$i]->getId());
            foreach ($wp_post_meta as $key => $value) {
                $this->entityManager->remove($value);
                $this->entityManager->flush();
            }

            $sql1 = 'DELETE FROM wp_term_relationships WHERE wp_term_relationships.object_id =:id';
            $stmt = $conn->prepare($sql1);
            $stmt = $stmt->executeQuery(['id' => $posts[$i]->getId()]);
        }

        for ($i = 0; $i < sizeof($posts); $i++) {
            $this->entityManager->remove($posts[$i]);
            $this->entityManager->flush();
        }

        $user = $this->entityManager->getRepository(User::class)->findOneById($id);
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return true;
    }

    /**
     * @param $id
     * @return bool
     * @throws DBALException
     */
    public function deleteProductAll($id)
    {
        //  var_dump($id);
        $conn = $this->entityManager->getConnection();
        //delete post
        $sql1 = 'DELETE FROM wp_posts WHERE ID =:id';
        $stmt = $conn->prepare($sql1);
        $stmt = $stmt->executeQuery(['id' => $id]);
        $this->entityManager->flush();

        $sql2 = 'DELETE FROM wp_posts WHERE post_parent =:id';
        $stmt = $conn->prepare($sql2);
        $stmt = $stmt->executeQuery(['id' => $id]);
        $this->entityManager->flush();

        $sql3 = 'DELETE FROM wp_term_relationships WHERE object_id =:id';
        $stmt = $conn->prepare($sql3);
        $stmt = $stmt->executeQuery(['id' => $id]);
        $this->entityManager->flush();
        return true;
    }

    /**
     * @param $userId
     * @return mixed
     * @throws DBALException
     */
    public function getAllUserdata($userId)
    {
        // var_dump($userId);
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wpUser.*,wpUser.email_canonical as email, wpUser.user_nicename as first_name, wpUser.user_nicename as last_name,
         wpUser.user_nicename as pays, wpUser.user_nicename as ville, wpUser.user_nicename as nom_livraison,
         wpUser.user_nicename as pays_livraison
            FROM wp_users wpUser
            WHERE wpUser.ID =:id';
        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery(['id' => $userId]);
        $data = $stmt->fetchAllAssociative()['0'];
        //Nom
        $data['first_name'] = $this->getUserStringDataValue(
            $userId,
            'first_name'
        );
        //  var_dump($data["first_name"]);
        //Prenom
        $data['last_name'] = $this->getUserStringDataValue(
            $userId,
            'last_name'
        );
        //  var_dump($data["last_name"]);
        $data['rue'] = $this->getUserStringDataValue(
            $userId,
            'billing_address_1'
        );
        $data['code_postale'] = $this->getUserStringDataValue(
            $userId,
            'billing_postcode'
        );
        $data['ville'] = $this->getUserStringDataValue($userId, 'billing_city');
        $data['pays'] = $this->getUserStringDataValue(
            $userId,
            'billing_country'
        );
        $data['pays_livraison'] = $this->getUserStringDataValue(
            $userId,
            'pays_livraison'
        );
        if (trim($data['pays_livraison']) == '') {
            $data['pays_livraison'] = $data['pays'];
        }
        $data['email'] = $this->getUserStringDataValue($userId, 'billing_email');
        if ($data['email'] == '' || $data['email'] == null) {
            $data['email'] = $data['email_canonical'];
        }

        $data['telephone'] = $this->getUserStringDataValue(
            $userId,
            'telephone'
        );
        $data['calendrier'] = $this->getUserStringDataValue(
            $userId,
            'disponibilite'
        );
        $data['nom_livraison'] =
            $this->getUserStringDataValue($userId, 'prenom_livraison') .
            ' ' .
            $this->getUserStringDataValue($userId, 'nom_livraison');
        if ($data['calendrier'] != '') {
            $arrayToTreat = explode(',', $data['calendrier']);
            usort($arrayToTreat, [$this, 'date_sort']);
            $data['calendrier'] = $arrayToTreat;
        }
        $data['adresse_livraison'] =
            $this->getUserStringDataValue($userId, 'ville_livraison') .
            ' ' .
            $this->getUserStringDataValue($userId, 'numeroNomRue_livraison');
        return $data;
    }

    //Donnees de vendeur (prenom-nom, societe, email)
    /**
     * @param $userId
     * @return mixed
     * @throws DBALException
     */
    public function getVendeurData($userId)
    {
        $nom_prenom =
            $this->getUserStringDataValue($userId, 'first_name') .
            ' ' .
            $this->getUserStringDataValue($userId, 'last_name');
        $societe = $this->getUserStringDataValue($userId, 'billing_company');
        $email = $this->getUserStringDataValue($userId, 'billing_email');
        return [$nom_prenom, $societe, $email];
    }

    //Transaction effectuees

    /**
     * @param $id
     * @return mixed[]
     * @throws DBALException
     */
    public function getCommandesEffectues($id)
    {
        $conn = $this->entityManager->getConnection();

        $sql = 'SELECT  wp.*
            FROM wp_posts wp
            WHERE (wp.post_author =:id and wp.post_type =:type and wp.post_status =:statut) or
            (wp.post_author =:id and wp.post_type =:type and wp.post_status =:statut2) or
            (wp.post_author =:id and wp.post_type =:type and wp.post_status =:statut3) 
            ORDER BY wp.ID DESC';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'id' => $id,
            'type' => 'shop_order',
            'statut' => 'wc-completed',
            'statut2' => 'wc-in-progress',
            'statut3' => 'wc-cancelled',
        ]);

        $data = $stmt->fetchAllAssociative();

        for ($i = 0; $i < sizeof($data); $i++) {
            $data[$i]['post_content'] = @unserialize($data[$i]['post_content']);
            if ($data[$i]['pinged'] == 'product') {
                $data[$i]['prix_total'] = 0;
                for ($x = 0; $x < sizeof($data[$i]['post_content']); $x++) {
                    $data[$i]['prix_total'] =
                        $data[$i]['prix_total'] +
                        $data[$i]['post_content'][$x]['prix'];
                }
            } elseif ($data[$i]['pinged'] == 'devis') {
                $data[$i]['post_content']['prix'] =
                    $data[$i]['post_content']['prix_devis'];
                $data[$i]['post_content']['prix_total'] =
                    $data[$i]['post_content']['prix_devis'];
            }
            if ($data[$i]['post_name'] != '') {
                $data[$i]['livraison'] = $this->entityManager
                    ->getRepository(WpPosts::class)
                    ->find($data[$i]['post_name']);
                if ($data[$i]['livraison']) {
                    $data[$i]['critere'] = $this->entityManager
                        ->getRepository(WpPosts::class)
                        ->find($data[$i]['livraison']->getPostParent());
                    $data[$i]['prix_total'] =
                        (float) $data[$i]['prix_total'] +
                        (float) $data[$i]['livraison']->getGuid();
                }
            } else {
                $data[$i]['critere'] = null;
                $data[$i]['livraison'] = null;
            }
        }
        return $data;
    }

    //transactions recues

    /**
     * @param $id
     * @return array|int
     * @throws DBALException
     */
    public function getCommandesRecues($id, $email)
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wp.*,wp.post_content, wp.post_content as client
            FROM wp_posts wp
            WHERE wp.post_type =:type1 and wp.post_content Like :id and (wp.post_status =:statut or wp.post_status =:statut2 or wp.post_status =:statut3)
            ORDER BY wp.ID DESC';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt = $stmt->executeQuery([
            'type1' => 'shop_order',
            'id' => "%\"" . $email . "\"%",
            'statut' => 'wc-completed',
            'statut2' => 'wc-in-progress',
            'statut3' => 'wc-cancelled',
        ]);

        $dataToTreat = $stmt->fetchAllAssociative();
        $dataCommande = [];
        for ($i = 0; $i < sizeof($dataToTreat); $i++) {
            $dataToTreat[$i]['client'] = null;
            $dataToTreat[$i]['client'][] =
                $this->getUserStringDataValue(
                    $dataToTreat[$i]['post_author'],
                    'prenom_livraison'
                ) .
                ' ' .
                $this->getUserStringDataValue(
                    $dataToTreat[$i]['post_author'],
                    'nom_livraison'
                );
            $dataToTreat[$i]['client'][] = $this->getUserStringDataValue(
                $dataToTreat[$i]['post_author'],
                'ville_livraison'
            );
            $dataToTreat[$i]['client'][] = $this->getUserStringDataValue(
                $dataToTreat[$i]['post_author'],
                'numeroNomRue_livraison'
            );
            if ($dataToTreat[$i]['post_content'] != '') {
                $dataToTreat[$i]['post_content'] = @unserialize(
                    $dataToTreat[$i]['post_content']
                );
                if ($dataToTreat[$i]['pinged'] == 'devis') {
                    //  $dataToTreat[$i]['vendeur'] = $dataToTreat[$i]['post_content']["post_author"];
                } elseif ($dataToTreat[$i]['pinged'] == 'product') {
                    $dataToTreat[$i]['prix_total'] = 0;
                    for (
                        $x = 0;
                        $x < sizeof($dataToTreat[$i]['post_content']);
                        $x++
                    ) {
                        if (
                            $dataToTreat[$i]['post_content'][$x]['email'] ==
                            $email
                        ) {
                            $dataToTreat[$i]['prix_total'] =
                                $dataToTreat[$i]['prix_total'] +
                                $dataToTreat[$i]['post_content'][$x]['prix'];
                        }
                    }
                }
            }
        }
        // dd($dataToTreat);
        return $dataToTreat;
    }

    /**
     * Get all specials operations
     * @param $id
     * @return mixed[]
     * @throws DBALException
     */
    public function getAllSpecialOps()
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wp.ID as id, wp.post_title as titre, wp.post_content as articles, wp.post_excerpt as meta_description,
        wp.post_content as prix,wp.post_content as categorie ,wp.post_content as chapeau, wp.post_name as slug, wp.post_status as status
            FROM wp_posts wp
            WHERE (wp.post_type =:type) 
            ORDER BY wp.ID DESC';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'type' => 'ops_speciales_post',
        ]);

        $data = $stmt->fetchAllAssociative();
        //dd($data);
        for ($i = 0; $i < sizeof($data); $i++) {
            $prix = 0;
            $cat = [];
            $data[$i]['articles'] = $this->entityManager
                ->getRepository(WpPosts::class)
                ->findBy(['postParent' => $data[$i]['id']]);
            foreach ($data[$i]['articles'] as $key => $value) {
                $prix += $value->getToPing();
            }
            $categorie = $this->entityManager
                ->getRepository(WpPostmeta::class)
                ->findBy([
                    'postId' => $data[$i]['id'],
                    'metaKey' => 'ops_speciales_categories_show',
                ]);
            for ($j = 0; $j < sizeof($categorie); $j++) {
                if ($categorie[$j]) {
                    $cat[] = $this->entityManager
                        ->getRepository(WpTerms::class)
                        ->findOneBy([
                            'termId' => $categorie[$j]->getMetaValue(),
                        ]);
                }
            }
            $data[$i]['prix'] = $prix;
            $data[$i]['categorie'] = $cat;
            $data[$i]['meta_description'] = $this->getPostStringDataValue(
                $data[$i]['id'],
                'ops_speciales_meta_description'
            );
            $data[$i]['chapeau'] = $this->getPostStringDataValue(
                $data[$i]['id'],
                'ops_speciales_chapeau'
            );
            $data[$i]['status'] = $this->getPostStringDataValue(
                $data[$i]['id'],
                'ops_speciales_chapeau'
            );
        }
        return $data;
    }

    /**
     * Get all specials operations
     * @param $id
     * @return mixed[]
     * @throws DBALException
     */
    public function getAllSpecialOpsLike($search)
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wp.ID as id, wp.post_title as titre, wp.post_content as articles, wp.post_excerpt as meta_description,
        wp.post_content as prix,wp.post_content as categorie ,wp.post_content as chapeau, wp.post_name as slug
            FROM wp_posts wp
            WHERE (wp.post_type =:type and LOWER(wp.post_title) LIKE :search) 
            ORDER BY wp.ID DESC';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'type' => 'ops_speciales_post',
            'search' => '%' . strtolower($search) . '%',
        ]);

        $data = $stmt->fetchAllAssociative();
        for ($i = 0; $i < sizeof($data); $i++) {
            $prix = 0;
            $cat = [];
            $data[$i]['articles'] = $this->entityManager
                ->getRepository(WpPosts::class)
                ->findBy(['postParent' => $data[$i]['id']]);
            foreach ($data[$i]['articles'] as $key => $value) {
                $prix += $value->getToPing();
            }
            $categorie = $this->entityManager
                ->getRepository(WpPostmeta::class)
                ->findBy([
                    'postId' => $data[$i]['id'],
                    'metaKey' => 'ops_speciales_categories_show',
                ]);
            for ($j = 0; $j < sizeof($categorie); $j++) {
                if ($categorie[$j]) {
                    $cat[] = $this->entityManager
                        ->getRepository(WpTerms::class)
                        ->findOneBy([
                            'termId' => $categorie[$j]->getMetaValue(),
                        ]);
                }
            }
            $data[$i]['prix'] = $prix;
            $data[$i]['categorie'] = $cat;
            $data[$i]['meta_description'] = $this->getPostStringDataValue(
                $data[$i]['id'],
                'ops_speciales_meta_description'
            );
            $data[$i]['chapeau'] = $this->getPostStringDataValue(
                $data[$i]['id'],
                'ops_speciales_chapeau'
            );
        }
        return $data;
    }

    /**
     * Get 1 special operation
     * @param $id
     * @return mixed[]
     * @throws DBALException
     */
    public function getOneSpecialOps($id)
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wp.ID as id, wp.post_title as titre, wp.post_content as articles,
        wp.post_excerpt as meta_description, wp.post_content as prix,wp.post_content as categorie,
        wp.post_content as description, wp.post_content as chapeau, wp.post_name as slug
            FROM wp_posts wp
            WHERE (wp.post_type =:type AND wp.id =:id) 
            ORDER BY wp.ID DESC';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'type' => 'ops_speciales_post',
            'id' => $id,
        ]);

        $data = $stmt->fetchAllAssociative()[0];
        $cat = [];
        $prix = 0;
        $data['articles'] = $this->entityManager
            ->getRepository(WpPosts::class)
            ->findBy(['postParent' => $data['id']]);
        foreach ($data['articles'] as $key => $value) {
            $prix += $value->getToPing();
        }
        $categorie = $this->entityManager
            ->getRepository(WpPostmeta::class)
            ->findBy([
                'postId' => $data['id'],
                'metaKey' => 'ops_speciales_categories_show',
            ]);
        for ($i = 0; $i < sizeof($categorie); $i++) {
            if ($categorie[$i]) {
                $cat[] = $this->entityManager
                    ->getRepository(WpTerms::class)
                    ->findOneBy(['termId' => $categorie[$i]->getMetaValue()]);
            }
        }
        $data['prix'] = $prix;
        $data['categorie'] = $cat;
        $data['meta_description'] = $this->getPostStringDataValue(
            $data['id'],
            'ops_speciales_meta_description'
        );
        $data['chapeau'] = $this->getPostStringDataValue(
            $data['id'],
            'ops_speciales_chapeau'
        );
        return $data;
    }

    /**
     * Get 1 special operation By post name
     * @param $slug
     * @return mixed[]
     * @throws DBALException
     */
    public function getOneSpecialOpsByName($slug)
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wp.ID as id, wp.post_title as titre,wp.post_author, wp.post_content as articles,
        wp.post_excerpt as meta_description, wp.post_content as prix,wp.post_content as categorie,
        wp.post_content as description, wp.post_content as chapeau, wp.post_name as slug,
        wp.post_date
            FROM wp_posts wp
            WHERE (wp.post_type =:type AND wp.post_name =:post_name) OR (wp.post_type =:type AND wp.id =:post_name)
            ORDER BY wp.ID DESC';
        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'type' => 'ops_speciales_post',
            'post_name' => $slug,
        ]);

        $data = $stmt->fetchAllAssociative()[0];
        $cat = [];
        $prix = 0;
        $data['articles'] = $this->entityManager
            ->getRepository(WpPosts::class)
            ->findBy(['postParent' => $data['id']]);
        foreach ($data['articles'] as $key => $value) {
            $prix += $value->getToPing();
        }
        $categorie = $this->entityManager
            ->getRepository(WpPostmeta::class)
            ->findBy([
                'postId' => $data['id'],
                'metaKey' => 'ops_speciales_categories_show',
            ]);
        for ($i = 0; $i < sizeof($categorie); $i++) {
            if ($categorie[$i]) {
                $cat[] = $this->sousCategorieById(
                    $categorie[$i]->getMetaValue()
                );
            }
        }
        $data['prix'] = $prix;
        $data['categorie'] = $cat;
        $data['meta_description'] = $this->getPostStringDataValue(
            $data['id'],
            'ops_speciales_meta_description'
        );
        $data['chapeau'] = $this->getPostStringDataValue(
            $data['id'],
            'ops_speciales_chapeau'
        );
        return $data;
    }

    /**
     * Get all promo Com
     * @param $id
     * @return mixed[]
     * @throws DBALException
     */
    public function getAllPromoCom()
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wp.ID as id, wp.post_title as titre, wp.post_content as articles, wp.post_excerpt as meta_description,
        wp.post_content as prix,wp.post_content as categorie ,wp.post_content as chapeau, wp.post_name as slug
            FROM wp_posts wp
            WHERE (wp.post_type =:type) 
            ORDER BY wp.ID DESC';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'type' => 'promo_com_post'
        ]);

        $data = $stmt->fetchAllAssociative();
        for ($i = 0; $i < sizeof($data); $i++) {
            $prix = 0;
            $cat = [];
            $data[$i]['articles'] = $this->entityManager
                ->getRepository(WpPosts::class)
                ->findBy(['postParent' => $data[$i]['id']]);
            foreach ($data[$i]['articles'] as $key => $value) {
                $prix += $value->getToPing();
            }
            $categorie = $this->entityManager
                ->getRepository(WpPostmeta::class)
                ->findBy([
                    'postId' => $data[$i]['id'],
                    'metaKey' => 'promo_com_categories_show',
                ]);
            for ($j = 0; $j < sizeof($categorie); $j++) {
                if ($categorie[$j]) {
                    $cat[] = $this->entityManager
                        ->getRepository(WpTerms::class)
                        ->findOneBy([
                            'termId' => $categorie[$j]->getMetaValue(),
                        ]);
                }
            }

            $data[$i]['categorie'] = $cat;
            $data[$i]['meta_description'] = $this->getPostStringDataValue(
                $data[$i]['id'],
                'promo_com_meta_description'
            );
            $data[$i]['chapeau'] = $this->getPostStringDataValue(
                $data[$i]['id'],
                'promo_com_chapeau'
            );
        }
        return $data;
    }

    /**
     * Get 1 special operation
     * @param $id
     * @return mixed[]
     * @throws DBALException
     */
    public function getOnePromoCom($id)
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wp.ID as id, wp.post_title as titre, wp.post_content as articles,
        wp.post_excerpt as meta_description, wp.post_content as prix,wp.post_content as categorie,
        wp.post_content as description, wp.post_content as chapeau, wp.post_name as slug
            FROM wp_posts wp
            WHERE (wp.post_type =:type AND wp.id =:id) 
            ORDER BY wp.ID DESC';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'type' => 'promo_com_post',
            'id' => $id,
        ]);

        $data = $stmt->fetchAllAssociative()[0];
        $cat = [];
        $prix = 0;
        $data['articles'] = $this->entityManager
            ->getRepository(WpPosts::class)
            ->findBy(['postParent' => $data['id']]);
        foreach ($data['articles'] as $key => $value) {
            $prix += $value->getToPing();
        }
        $categorie = $this->entityManager
            ->getRepository(WpPostmeta::class)
            ->findBy([
                'postId' => $data['id'],
                'metaKey' => 'promo_com_categories_show',
            ]);
        for ($i = 0; $i < sizeof($categorie); $i++) {
            if ($categorie[$i]) {
                $cat[] = $this->entityManager
                    ->getRepository(WpTerms::class)
                    ->findOneBy(['termId' => $categorie[$i]->getMetaValue()]);
            }
        }
        $data['categorie'] = $cat;
        $data['meta_description'] = $this->getPostStringDataValue(
            $data['id'],
            'promo_com_meta_description'
        );
        $data['chapeau'] = $this->getPostStringDataValue(
            $data['id'],
            'promo_com_chapeau'
        );
        return $data;
    }

    /**
     * Get 1 special operation By post name
     * @param $slug
     * @return mixed[]
     * @throws DBALException
     */
    public function getOnePromoComByName($slug)
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wp.ID as id, wp.post_title as titre,wp.post_author, wp.post_content as articles,
        wp.post_excerpt as meta_description, wp.post_content as prix,wp.post_content as categorie,
        wp.post_content as description, wp.post_content as chapeau, wp.post_name as slug,
        wp.post_date
            FROM wp_posts wp
            WHERE (wp.post_type =:type AND wp.post_name =:post_name) OR (wp.post_type =:type AND wp.id =:post_name)
            ORDER BY wp.ID DESC';
        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'type' => 'promo_com_post',
            'post_name' => $slug,
        ]);

        $data = $stmt->fetchAllAssociative()[0];
        $cat = [];
        $prix = 0;
        $data['articles'] = $this->entityManager
            ->getRepository(WpPosts::class)
            ->findBy(['postParent' => $data['id']]);
        foreach ($data['articles'] as $key => $value) {
            $prix += $value->getToPing();
        }
        $categorie = $this->entityManager
            ->getRepository(WpPostmeta::class)
            ->findBy([
                'postId' => $data['id'],
                'metaKey' => 'promo_com_categories_show',
            ]);
        for ($i = 0; $i < sizeof($categorie); $i++) {
            if ($categorie[$i]) {
                $cat[] = $this->sousCategorieById(
                    $categorie[$i]->getMetaValue()
                );
            }
        }
        $data['prix'] = $prix;
        $data['categorie'] = $cat;
        $data['meta_description'] = $this->getPostStringDataValue(
            $data['id'],
            'promo_com_meta_description'
        );
        $data['chapeau'] = $this->getPostStringDataValue(
            $data['id'],
            'promo_com_chapeau'
        );
        return $data;
    }

    /**
     * Get all specials operations
     * @param $id
     * @return mixed[]
     * @throws DBALException
     */
    public function getAllPromoComLike($search)
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wp.ID as id, wp.post_title as titre, wp.post_content as articles, wp.post_excerpt as meta_description,
        wp.post_content as prix,wp.post_content as categorie ,wp.post_content as chapeau, wp.post_name as slug
            FROM wp_posts wp
            WHERE (wp.post_type =:type and LOWER(wp.post_title) LIKE :search) 
            ORDER BY wp.ID DESC';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'type' => 'promo_com_post',
            'search' => '%' . strtolower($search) . '%',
        ]);

        $data = $stmt->fetchAllAssociative();
        for ($i = 0; $i < sizeof($data); $i++) {
            $prix = 0;
            $cat = [];
            $data[$i]['articles'] = $this->entityManager
                ->getRepository(WpPosts::class)
                ->findBy(['postParent' => $data[$i]['id']]);
            foreach ($data[$i]['articles'] as $key => $value) {
                $prix += $value->getToPing();
            }
            $categorie = $this->entityManager
                ->getRepository(WpPostmeta::class)
                ->findBy([
                    'postId' => $data[$i]['id'],
                    'metaKey' => 'promo_com_categories_show',
                ]);
            for ($j = 0; $j < sizeof($categorie); $j++) {
                if ($categorie[$j]) {
                    $cat[] = $this->entityManager
                        ->getRepository(WpTerms::class)
                        ->findOneBy([
                            'termId' => $categorie[$j]->getMetaValue(),
                        ]);
                }
            }
            $data[$i]['categorie'] = $cat;
            $data[$i]['meta_description'] = $this->getPostStringDataValue(
                $data[$i]['id'],
                'promo_com_meta_description'
            );
            $data[$i]['chapeau'] = $this->getPostStringDataValue(
                $data[$i]['id'],
                'promo_com_chapeau'
            );
        }
        return $data;
    }

    /**
     * Get all promo Com
     * @param $id
     * @return mixed[]
     * @throws DBALException
     */
    public function getAllLeadsGen()
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wp.ID as id, wp.post_title as titre, wp.post_content as articles, wp.post_excerpt as meta_description,
        wp.post_content as prix,wp.post_content as categorie ,wp.post_content as chapeau, wp.post_name as slug
            FROM wp_posts wp
            WHERE (wp.post_type =:type) 
            ORDER BY wp.ID DESC';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'type' => 'leads_gen_post',
        ]);

        $data = $stmt->fetchAllAssociative();
        for ($i = 0; $i < sizeof($data); $i++) {
            $prix = 0;
            $cat = [];
            $data[$i]['articles'] = $this->entityManager
                ->getRepository(WpPosts::class)
                ->findBy(['postParent' => $data[$i]['id']]);
            foreach ($data[$i]['articles'] as $key => $value) {
                $prix += $value->getToPing();
            }
            $categorie = $this->entityManager
                ->getRepository(WpPostmeta::class)
                ->findBy([
                    'postId' => $data[$i]['id'],
                    'metaKey' => 'leads_gen_categories_show',
                ]);
            for ($j = 0; $j < sizeof($categorie); $j++) {
                if ($categorie[$j]) {
                    $cat[] = $this->entityManager
                        ->getRepository(WpTerms::class)
                        ->findOneBy([
                            'termId' => $categorie[$j]->getMetaValue(),
                        ]);
                }
            }

            $data[$i]['categorie'] = $cat;
            $data[$i]['meta_description'] = $this->getPostStringDataValue(
                $data[$i]['id'],
                'leads_gen_meta_description'
            );
            $data[$i]['chapeau'] = $this->getPostStringDataValue(
                $data[$i]['id'],
                'leads_gen_chapeau'
            );
        }
        return $data;
    }

    /**
     * Get 1 special operation
     * @param $id
     * @return mixed[]
     * @throws DBALException
     */
    public function getOneLeadsGen($id)
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wp.ID as id, wp.post_title as titre, wp.post_content as articles,
        wp.post_excerpt as meta_description, wp.post_content as prix,wp.post_content as categorie,
        wp.post_content as description, wp.post_content as chapeau, wp.post_name as slug
            FROM wp_posts wp
            WHERE (wp.post_type =:type AND wp.id =:id) 
            ORDER BY wp.ID DESC';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'type' => 'leads_gen_post',
            'id' => $id,
        ]);

        $data = $stmt->fetchAllAssociative()[0];
        $cat = [];
        $prix = 0;
        $data['articles'] = $this->entityManager
            ->getRepository(WpPosts::class)
            ->findBy(['postParent' => $data['id']]);
        foreach ($data['articles'] as $key => $value) {
            $prix += $value->getToPing();
        }
        $categorie = $this->entityManager
            ->getRepository(WpPostmeta::class)
            ->findBy([
                'postId' => $data['id'],
                'metaKey' => 'leads_gen_categories_show',
            ]);
        for ($i = 0; $i < sizeof($categorie); $i++) {
            if ($categorie[$i]) {
                $cat[] = $this->entityManager
                    ->getRepository(WpTerms::class)
                    ->findOneBy(['termId' => $categorie[$i]->getMetaValue()]);
            }
        }
        $data['categorie'] = $cat;
        $data['meta_description'] = $this->getPostStringDataValue(
            $data['id'],
            'leads_gen_meta_description'
        );
        $data['chapeau'] = $this->getPostStringDataValue(
            $data['id'],
            'leads_gen_chapeau'
        );
        return $data;
    }

    /**
     * Get 1 special operation By post name
     * @param $slug
     * @return mixed[]
     * @throws DBALException
     */
    public function getOneLeadsGenByName($slug)
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wp.ID as id, wp.post_title as titre,wp.post_author, wp.post_content as articles,
        wp.post_excerpt as meta_description, wp.post_content as prix,wp.post_content as categorie,
        wp.post_content as description, wp.post_content as chapeau, wp.post_name as slug,
        wp.post_date
            FROM wp_posts wp
            WHERE (wp.post_type =:type AND wp.post_name =:post_name) OR (wp.post_type =:type AND wp.id =:post_name)
            ORDER BY wp.ID DESC';
        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'type' => 'leads_gen_post',
            'post_name' => $slug,
        ]);

        $data = $stmt->fetchAllAssociative()[0];
        $cat = [];
        $prix = 0;
        $data['articles'] = $this->entityManager
            ->getRepository(WpPosts::class)
            ->findBy(['postParent' => $data['id']]);
        foreach ($data['articles'] as $key => $value) {
            $prix += $value->getToPing();
        }
        $categorie = $this->entityManager
            ->getRepository(WpPostmeta::class)
            ->findBy([
                'postId' => $data['id'],
                'metaKey' => 'leads_gen_categories_show',
            ]);
        for ($i = 0; $i < sizeof($categorie); $i++) {
            if ($categorie[$i]) {
                $cat[] = $this->sousCategorieById(
                    $categorie[$i]->getMetaValue()
                );
            }
        }
        $data['prix'] = $prix;
        $data['categorie'] = $cat;
        $data['meta_description'] = $this->getPostStringDataValue(
            $data['id'],
            'leads_gen_meta_description'
        );
        $data['chapeau'] = $this->getPostStringDataValue(
            $data['id'],
            'leads_gen_chapeau'
        );
        return $data;
    }

    /**
     * Get all specials operations
     * @param $id
     * @return mixed[]
     * @throws DBALException
     */
    public function getAllLeadsGenLike($search)
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wp.ID as id, wp.post_title as titre, wp.post_content as articles, wp.post_excerpt as meta_description,
        wp.post_content as prix,wp.post_content as categorie ,wp.post_content as chapeau, wp.post_name as slug
            FROM wp_posts wp
            WHERE (wp.post_type =:type and LOWER(wp.post_title) LIKE :search) 
            ORDER BY wp.ID DESC';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'type' => 'leads_gen_post',
            'search' => '%' . strtolower($search) . '%',
        ]);

        $data = $stmt->fetchAllAssociative();
        for ($i = 0; $i < sizeof($data); $i++) {
            $prix = 0;
            $cat = [];
            $data[$i]['articles'] = $this->entityManager
                ->getRepository(WpPosts::class)
                ->findBy(['postParent' => $data[$i]['id']]);
            foreach ($data[$i]['articles'] as $key => $value) {
                $prix += $value->getToPing();
            }
            $categorie = $this->entityManager
                ->getRepository(WpPostmeta::class)
                ->findBy([
                    'postId' => $data[$i]['id'],
                    'metaKey' => 'leads_gen_categories_show',
                ]);
            for ($j = 0; $j < sizeof($categorie); $j++) {
                if ($categorie[$j]) {
                    $cat[] = $this->entityManager
                        ->getRepository(WpTerms::class)
                        ->findOneBy([
                            'termId' => $categorie[$j]->getMetaValue(),
                        ]);
                }
            }
            $data[$i]['categorie'] = $cat;
            $data[$i]['meta_description'] = $this->getPostStringDataValue(
                $data[$i]['id'],
                'leads_gen_meta_description'
            );
            $data[$i]['chapeau'] = $this->getPostStringDataValue(
                $data[$i]['id'],
                'leads_gen_chapeau'
            );
        }
        return $data;
    }

    /**
     * Get all promo Com
     * @param $id
     * @return mixed[]
     * @throws DBALException
     */
    public function getAllOpsFree()
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wp.ID as id, wp.*, wpm.meta_value as cat
            FROM wp_posts wp
            JOIN wp_postmeta wpm ON wp.id = wpm.post_id
            WHERE (wp.post_type =:type AND wpm.meta_key =:key ) 
            ORDER BY wp.ID DESC';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'type' => 'ops_free_delivery',
            'key' => 'ops_free_delivery_category',
        ]);
        $data = $stmt->fetchAllAssociative();
        for ($i = 0; $i < sizeof($data); $i++) {
            $data[$i]['cat'] = $this->entityManager
                ->getRepository(WpTerms::class)
                ->findOneByTermId($data[$i]['cat']);
        }
        return $data;
    }

    /**
     * Get all promo Com
     * @param $id
     * @return mixed[]
     * @throws DBALException
     */
    public function getAllActiveOpsFree()
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wp.ID as id, wpm.meta_value as cat,wp.guid as prix
            FROM wp_posts wp
            JOIN wp_postmeta wpm ON wp.id = wpm.post_id
            WHERE (wp.post_type =:type AND wp.post_status =:state AND wpm.meta_key =:key ) 
            ORDER BY wp.ID DESC';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'type' => 'ops_free_delivery',
            'key' => 'ops_free_delivery_category',
            'state' => 1,
        ]);
        $data = $stmt->fetchAllAssociative();
        for ($i = 0; $i < sizeof($data); $i++) {
            $data[$i]['cat'] = $this->entityManager
                ->getRepository(WpTerms::class)
                ->findOneByTermId($data[$i]['cat'])->getSlug();
        }
        return $data;
    }

    /**
     * Get 1 special operation
     * @param $id
     * @return mixed[]
     * @throws DBALException
     */
    public function getOneOpsFree($id)
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wp.ID as id, wp.*, wpm.meta_value as cat
            FROM wp_posts wp
            JOIN wp_postmeta wpm ON wp.id = wpm.post_id
            WHERE (wp.post_type =:type AND wpm.meta_key =:key AND wp.id =:id) 
            ORDER BY wp.ID DESC';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'type' => 'ops_free_delivery',
            'key' => 'ops_free_delivery_category',
            'id' => $id,
        ]);

        $data = $stmt->fetchAllAssociative()[0];
        //dd($data);
        $data['cat'] = $this->entityManager
            ->getRepository(WpTerms::class)
            ->findOneByTermId($data['cat']);
        return $data;
    }

    /**
     * Get all promo Com
     * @param $id
     * @return mixed[]
     * @throws DBALException
     */
    public function getAllExperiences()
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wp.ID as id, wp.post_title as titre, wp.post_content as contenu, wp.post_excerpt as lien_acces_rapides,
        wp.post_content as meta_titre, wp.post_content as meta_description,wp.post_content as youtube,
        wp.guid as image_couverture, wp.post_name as slug, wp.post_date as created_at
            FROM wp_posts wp
            WHERE (wp.post_type =:type) 
            ORDER BY wp.ID DESC';

        $stmt = $conn->prepare($sql);

        $stmt = $stmt->executeQuery([
            'type' => 'experiences_post',
        ]);

        $data = $stmt->fetchAllAssociative();
        for ($i = 0; $i < sizeof($data); $i++) {
            $data[$i]['meta_description'] = $this->getPostStringDataValue(
                $data[$i]['id'],
                'experiences_meta_description'
            );
            $data[$i]['meta_titre'] = $this->getPostStringDataValue(
                $data[$i]['id'],
                'experiences_meta_titre'
            );
            $data[$i]['youtube'] = $this->getPostStringDataValue(
                $data[$i]['id'],
                'experiences_youtube'
            );
        }
        return $data;
    }

    /**
     * Get all promo Com
     * @param $name
     * @return mixed[]
     * @throws Exception
     */
    public function getAllExperiencesLike($name)
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wp.ID as id, wp.post_title as titre, wp.post_content as contenu, wp.post_excerpt as lien_acces_rapides,
        wp.post_content as meta_titre, wp.post_content as meta_description,wp.post_content as youtube,
        wp.guid as image_couverture, wp.post_name as slug, wp.post_date as created_at
            FROM wp_posts wp
            WHERE (wp.post_type =:type AND wp.post_title LIKE :search) 
            ORDER BY wp.ID DESC';

        $stmt = $conn->prepare($sql);

        $stmt = $stmt->executeQuery([
            'type' => 'experiences_post',
            'search' => '%' . $name . '%'
        ]);

        $data = $stmt->fetchAllAssociative();
        for ($i = 0; $i < sizeof($data); $i++) {
            $data[$i]['meta_description'] = $this->getPostStringDataValue(
                $data[$i]['id'],
                'experiences_meta_description'
            );
            $data[$i]['meta_titre'] = $this->getPostStringDataValue(
                $data[$i]['id'],
                'experiences_meta_titre'
            );
            $data[$i]['youtube'] = $this->getPostStringDataValue(
                $data[$i]['id'],
                'experiences_youtube'
            );
        }
        return $data;
    }

    /**
     * Get 1 special operation
     * @param $id
     * @param string $locale
     * @return mixed[]
     * @throws Exception
     */
    public function getOneExperiences($id, string $locale = 'fr')
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wp.ID as id, wp.post_title as titre, wp.post_content as contenu, wp.post_excerpt as lien_acces_rapides,
        wp.post_content as meta_titre, wp.post_content as meta_description,wp.post_content as youtube,
        wp.guid as image_couverture, wp.post_name as slug, wp.post_date as created_at
            FROM wp_posts wp
            WHERE (wp.post_type =:type AND wp.id =:id) 
            ORDER BY wp.ID DESC';

        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'type' => 'experiences_post',
            'id' => $id,
        ]);
        $data = $stmt->fetchAllAssociative()[0];
        $data['meta_description'] = $this->getPostStringDataValue(
            $data['id'],
            'experiences_meta_description'
        );
        $data['meta_titre'] = $this->getPostStringDataValue(
            $data['id'],
            'experiences_meta_titre'
        );
        $data['youtube'] = $this->getPostStringDataValue(
            $data['id'],
            'experiences_youtube'
        );

        return $data;
    }

    /**
     * Get 1 special operation By post name
     * @param $slug
     * @param string $locale
     * @return mixed[]
     * @throws Exception
     */
    public function getOneExperiencesByName($slug, $locale = 'fr')
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wp.ID as id, wp.post_title as titre, wp.post_content as contenu, wp.post_excerpt as lien_acces_rapides,
        wp.post_content as meta_titre, wp.post_content as meta_description,wp.post_content as youtube,
        wp.guid as image_couverture, wp.post_name as slug, wp.post_date as created_at, wp.post_modified as edited_at
            FROM wp_posts wp
            WHERE (wp.post_type =:type AND wp.post_name =:post_name) 
            ORDER BY wp.ID DESC';
        $stmt = $conn->prepare($sql);
        $stmt = $stmt->executeQuery([
            'type' => 'experiences_post',
            'post_name' => $slug,
        ]);

        $data = $stmt->fetchAllAssociative()[0];

        $data['meta_description'] = $this->getPostStringDataValue(
            $data['id'],
            'experiences_meta_description'
        );
        $data['meta_titre'] = $this->getPostStringDataValue(
            $data['id'],
            'experiences_meta_titre'
        );
        $data['youtube'] = $this->getPostStringDataValue(
            $data['id'],
            'experiences_youtube'
        );

        return $data;
    }

    public function date_sort($a, $b)
    {
        return strtotime($a) - strtotime($b);
    }

    public function slugify($text)
    {
        $slugify = new Slugify();
        if (empty($text)) {
            return 'n-a';
        }
        return $slugify->slugify($text);
    }

    public function getProductParentCategory($idProduct)
    {
        $value = '';
        $state = true;
        $result = '';
        $tempTermTaxonomy = $this->getCategoryFromAnnounce($idProduct);
        if (count($tempTermTaxonomy) > 0) {
            $result = $tempTermTaxonomy[0]['taxonomy'];
            $value = $tempTermTaxonomy[0]['parent'];
            while ($value != 0 && $state) {
                $tempTermTaxonomy = $this->entityManager
                    ->getRepository(WpTermTaxonomy::class)
                    ->findOneByTermTaxonomyId($value);
                if ($tempTermTaxonomy) {
                    if ($tempTermTaxonomy->getParent() == 0) {
                        $value = $tempTermTaxonomy->getParent();
                        $result = $tempTermTaxonomy->getTaxonomy();
                        $state = false;
                    } else {
                        $value = $tempTermTaxonomy->getParent();
                    }
                } else {
                    $state = false;
                }
            }
        }
        //dd($result);
        return $result;
    }

    public function getDeliveryType($price)
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT post_livraison.post_content as delai,post_livraison.post_title as fr_text,
        post_livraison.post_excerpt as en_text,post_livraison.guid as prix,critere_livraison.post_status as val_inf,
        critere_livraison.pinged as val_sup,critere_livraison.to_ping as int_inf,critere_livraison.guid as int_sup,
        critere_livraison.post_title as fr_crit_title,critere_livraison.post_excerpt as en_crit_title,
        critere_livraison.post_content as fr_crit_text,critere_livraison.post_content_filtered as en_crit_text,
        post_livraison.ID as id
            FROM wp_posts post_livraison
            INNER JOIN wp_posts critere_livraison ON post_livraison.post_parent=critere_livraison.id
            WHERE post_livraison.post_type=:type1 AND critere_livraison.post_type =:type2
            ORDER BY CAST(prix as unsigned) ASC';

        $data = $conn->prepare($sql);
        $data = $data->executeQuery([
            'type1' => 'post_livraison',
            'type2' => 'critere_livraison',
        ]);

        $data = $data->fetchAllAssociative();
        $results = [];
        for ($i = 0; $i < sizeof($data); $i++) {
            if (
                ($data[$i]['int_inf'] == '=' || $data[$i]['int_inf'] == '>=') &&
                ($data[$i]['int_sup'] == '=' || $data[$i]['int_sup'] == '<=')
            ) {
                if (
                    $price >= $data[$i]['val_inf'] &&
                    $price <= $data[$i]['val_sup']
                ) {
                    $results[] = $data[$i];
                }
            } elseif (
                ($data[$i]['int_inf'] == '=' || $data[$i]['int_inf'] == '>=') &&
                $data[$i]['int_sup'] == '<'
            ) {
                if (
                    $price >= $data[$i]['val_inf'] &&
                    $price < $data[$i]['val_sup']
                ) {
                    $results[] = $data[$i];
                }
            } elseif (
                $data[$i]['int_inf'] == '>' &&
                ($data[$i]['int_sup'] == '=' || $data[$i]['int_sup'] == '<=')
            ) {
                if (
                    $price > $data[$i]['val_inf'] &&
                    $price <= $data[$i]['val_sup']
                ) {
                    $results[] = $data[$i];
                }
            } elseif (
                $data[$i]['int_inf'] == '>' &&
                $data[$i]['int_sup'] == '<'
            ) {
                if (
                    $price > $data[$i]['val_inf'] &&
                    $price < $data[$i]['val_sup']
                ) {
                    $results[] = $data[$i];
                }
            }
        }
        return $results;
    }

    //Processus des experiences utilisateurs
    /**
     * Get all experiences process
     * @param [$user_id, $status]
     * @return mixed[]
     * @throws DBALException
     */
    public function getAllUserExperiencesProcess($user_id, $status)
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wp.ID as id, wp.post_title as titre, wp.post_status as status, wp.post_content as exp_ville, wp.post_excerpt as exp_univers,
        wp.post_content as exp_besoins, wp.post_content as exp_options,wp.post_content as exp_precisions, wp.post_content as exp_type_experience,
        wp.post_content as exp_lieu_evt,wp.post_content as exp_lieu_evt, wp.post_content as exp_participants_evt,
        wp.post_date as created_at, wp.post_type
            FROM wp_posts wp
            WHERE (wp.post_type =:type OR wp.post_type =:type2) AND wp.post_author =:user AND wp.post_status =:status 
            ORDER BY wp.ID DESC';

        $stmt = $conn->prepare($sql);

        $stmt = $stmt->executeQuery([
            'type' => 'exp_experiences',
            'type2' => 'exp_evenementiel',
            'status' => $status,
            'user' => $user_id,
        ]);

        $data = $stmt->fetchAllAssociative();
        for ($i = 0; $i < sizeof($data); $i++) {
            $data[$i]['exp_ville'] = $this->getPostStringDataValue($data[$i]['id'], 'exp_ville');
            $data[$i]['exp_univers'] = $this->getPostStringDataValue($data[$i]['id'], 'exp_univers');
            $data[$i]['exp_precisions'] = $this->getPostStringDataValue($data[$i]['id'], 'exp_precisions');
            $data[$i]['exp_nom_evt'] = $this->getPostStringDataValue($data[$i]['id'], 'exp_nom_evt');
            $data[$i]['exp_lieu_evt'] = $this->getPostStringDataValue($data[$i]['id'], 'exp_lieu_evt');
            $data[$i]['exp_participants_evt'] = $this->getPostStringDataValue($data[$i]['id'], 'exp_participants_evt');
            $data[$i]['exp_type_experience'] = $this->getPostStringDataValue($data[$i]['id'], 'exp_type_experience');
            $data[$i]['exp_besoins'] = $this->readAllPostMeta($data[$i]['id'], 'exp_besoins');
            $data[$i]['exp_options'] = $this->readAllPostMeta($data[$i]['id'], 'exp_options');
        }
        return $data;
    }

    /**
     * Get all experiences process
     * @param [$user_id, $status]
     * @return mixed[]
     * @throws DBALException
     */
    public function getAllExperiencesProcess()
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wp.ID as id, wp.post_title as titre, wp.post_status as status, wp.post_content as exp_ville, wp.post_excerpt as exp_univers,
        wp.post_content as exp_besoins, wp.post_content as exp_options,wp.post_content as exp_precisions, wp.post_content as exp_type_experience,
        wp.post_content as exp_lieu_evt,wp.post_content as exp_lieu_evt, wp.post_content as exp_participants_evt,
        wp.post_date as created_at, wu.email_canonical as user
            FROM wp_posts wp
            JOIN wp_users wu ON wp.post_author = wu.ID
            WHERE (wp.post_type =:type OR wp.post_type =:type2) 
            ORDER BY wp.ID DESC';

        $stmt = $conn->prepare($sql);

        $stmt = $stmt->executeQuery([
            'type' => 'exp_experiences',
            'type2' => 'exp_evenementiel'
        ]);

        $data = $stmt->fetchAllAssociative();
        for ($i = 0; $i < sizeof($data); $i++) {
            $data[$i]['exp_ville'] = $this->getPostStringDataValue($data[$i]['id'], 'exp_ville');
            $data[$i]['exp_univers'] = $this->getPostStringDataValue($data[$i]['id'], 'exp_univers');
            $data[$i]['exp_precisions'] = $this->getPostStringDataValue($data[$i]['id'], 'exp_precisions');
            $data[$i]['exp_nom_evt'] = $this->getPostStringDataValue($data[$i]['id'], 'exp_nom_evt');
            $data[$i]['exp_lieu_evt'] = $this->getPostStringDataValue($data[$i]['id'], 'exp_lieu_evt');
            $data[$i]['exp_participants_evt'] = $this->getPostStringDataValue($data[$i]['id'], 'exp_participants_evt');
            $data[$i]['exp_type_experience'] = $this->getPostStringDataValue($data[$i]['id'], 'exp_type_experience');
            $data[$i]['exp_besoins'] = $this->readAllPostMeta($data[$i]['id'], 'exp_besoins');
            $data[$i]['exp_options'] = $this->readAllPostMeta($data[$i]['id'], 'exp_options');
        }
        return $data;
    }

    /**
     * Get One experience process
     * @param $id
     * @return mixed[]
     * @throws DBALException
     */
    public function getOneUserExperiencesProcess($id)
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wp.ID as id, wp.post_title as titre, wp.post_status as status, wp.post_content as exp_ville, wp.post_excerpt as exp_univers,
        wp.post_content as exp_besoins, wp.post_content as exp_options,wp.post_content as exp_precisions, wp.post_content as exp_type_experience,
        wp.post_content as exp_lieu_evt,wp.post_content as exp_lieu_evt, wp.post_content as exp_participants_evt,
        wp.post_date as created_at
            FROM wp_posts wp
            WHERE (wp.ID =:id)';

        $stmt = $conn->prepare($sql);

        $stmt = $stmt->executeQuery([
            'id' => $id
        ]);

        $data = $stmt->fetchAllAssociative()[0];
        $data['exp_ville'] = $this->getPostStringDataValue($id, 'exp_ville');
        $data['exp_univers'] = $this->getPostStringDataValue($id, 'exp_univers');
        $data['exp_precisions'] = $this->getPostStringDataValue($id, 'exp_precisions');
        $data['exp_nom_evt'] = $this->getPostStringDataValue($id, 'exp_nom_evt');
        $data['exp_lieu_evt'] = $this->getPostStringDataValue($id, 'exp_lieu_evt');
        $data['exp_participants_evt'] = $this->getPostStringDataValue($id, 'exp_participants_evt');
        $data['exp_type_experience'] = $this->getPostStringDataValue($id, 'exp_type_experience');
        //dd($this->readAllPostMeta($id, 'exp_besoins'));
        $data['exp_besoins'] = $this->readAllPostMeta($id, 'exp_besoins');
        $data['exp_options'] = $this->readAllPostMeta($id, 'exp_options');
        return $data;
    }

    /**
     * Get One experience process
     * @param $id
     * @return mixed[]
     * @throws DBALException
     */
    public function getOneUserExperiencesProcessArray($id)
    {
        $conn = $this->entityManager->getConnection();
        $sql = 'SELECT  wp.ID as id, wp.post_title as titre, wp.post_status as status, wp.post_content as exp_ville, wp.post_excerpt as exp_univers,
        wp.post_content as exp_besoins, wp.post_content as exp_options,wp.post_content as exp_precisions, wp.post_content as exp_type_experience,
         wp.post_date as created_at
            FROM wp_posts wp
            WHERE (wp.ID =:id)';

        $stmt = $conn->prepare($sql);

        $stmt = $stmt->executeQuery([
            'id' => $id
        ]);

        $data = $stmt->fetchAllAssociative()[0];
        $data['exp_ville'] = $this->getPostStringDataValue($id, 'exp_ville');
        $data['exp_univers'] = $this->getPostStringDataValue($id, 'exp_univers');
        $data['exp_precisions'] = $this->getPostStringDataValue($id, 'exp_precisions');
        $data['exp_type_experience'] = $this->getPostStringDataValue($id, 'exp_type_experience');
        //dd($this->readAllPostMeta($id, 'exp_besoins'));
        $data['exp_besoins'] = $this->readAllPostMeta($id, 'exp_besoins', 'array');
        $data['exp_options'] = $this->readAllPostMeta($id, 'exp_options', 'array');
        return $data;
    }

    public function getDataToUpdateMangopayUser()
    {
        // Define all the keys you need to fetch
        $keys = [
            'first_name', 'last_name', 'telephone', 'sexe', 'billing_email', 'residenceCountry',
            'nationalityCountry', 'mp_user_id_sandbox', 'bdaytime', 'numeroNomRue_domicile',
            'pays_domicile', 'codePostal_domicile',
            'ville_domicile', 'region_domicile', 'numeroNomRue_livraison', 'pays_livraison',
            'codePostal_livraison', 'ville_livraison', 'region_livraison',
            //Compagny Datas
            'siret', 'tva', 'billing_address_1', 'billing_company', 'billing_country', 'billing_postcode',
            'billing_state', 'billing_city', 'billing_phone', 'billing_email', 'nom_commercial',
            //Bank informations
            'vendor_account_type', 'vendor_account_name', 'vendor_account_address1', 'vendor_account_city', 'vendor_account_postcode', 'vendor_account_country',
            'vendor_account_region'
        ];

        // Fetch all metadata for the user in a single query
        $userMetadata = $this->service_manager->getUserMetadata($this->getUser()->getId(), $keys);

        // Map the metadata to the desired output structure
        $data = [
            "firstname" => $userMetadata['first_name'] ?? '',
            "lastname" => $userMetadata['last_name'] ?? '',
            "phone" => $userMetadata['telephone'] ?? '',
            "sexe" => isset($userMetadata['sexe']) ? ($userMetadata['sexe'] == 'femme' ? 'female' : 'male') : '',
            "user_email" => $this->getUser()->getEmailCanonical(),
            "countryOfResidence" => $userMetadata['residenceCountry'] ?? '',
            "nationality" => $userMetadata['nationalityCountry'] ?? '',
            "mpAccount" => $userMetadata['mp_user_id_sandbox'] ?? '',
            "birthday" => $userMetadata['bdaytime'] ?? '',
            "user_address_1" => $userMetadata['numeroNomRue_domicile'] ?? '',
            "user_country" => $userMetadata['pays_domicile'] ?? '',
            "user_postcode" => $userMetadata['codePostal_domicile'] ?? '',
            "user_city" => $userMetadata['ville_domicile'] ?? '',
            "region_domicile" => $userMetadata['region_domicile'] ?? '',
            "user_address_1_livraison" => $userMetadata['numeroNomRue_livraison'] ?? '',
            "user_country_livraison" => $userMetadata['pays_livraison'] ?? '',
            "user_postcode_livraison" => $userMetadata['codePostal_livraison'] ?? '',
            "user_city_livraison" => $userMetadata['ville_livraison'] ?? '',
            "region_livraison" => $userMetadata['region_livraison'] ?? '',
            //Company Datas
            "siret" => $userMetadata['siret'] ?? '',
            "tva" => $userMetadata['tva'] ?? '',
            "billing_company" => $userMetadata['billing_company'] ?? '',
            "billing_address_1" => $userMetadata['billing_address_1'] ?? '',
            "billing_country" => $userMetadata['billing_country'] ?? '',
            "billing_postcode" => $userMetadata['billing_postcode'] ?? '',
            "billing_state" => $userMetadata['billing_state'] ?? '',
            "billing_city" => $userMetadata['billing_city'] ?? '',
            "billing_phone" => $userMetadata['billing_phone'] ?? '',
            "billing_email" => $userMetadata['billing_email'] ?? '',
            "nom_commercial" => $userMetadata['nom_commercial'] ?? '',
            //Banking datas
            "vendor_account_type" => $userMetadata['vendor_account_type'] ?? '',
            "vendor_account_name" => $userMetadata['vendor_account_name'] ?? '',
            "vendor_account_address1" => $userMetadata['vendor_account_address1'] ?? '',
            "vendor_account_city" => $userMetadata['vendor_account_city'] ?? '',
            "vendor_account_postcode" => $userMetadata['vendor_account_postcode'] ?? '',
            "vendor_account_country" => $userMetadata['vendor_account_country'] ?? '',
            "vendor_account_region" => $userMetadata['vendor_account_region'] ?? '',
        ];

        // Fallback for billing email
        if (empty($data["billing_email"])) {
            $data["billing_email"] = $data["user_email"];
        }

        return $data;
    }

    //geolocalisation
    public function ip_info($ip = null, $purpose = 'location', $deep_detect = true)
    {
        $output = null;
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $ip = $_SERVER['REMOTE_ADDR'];
            if ($deep_detect) {
                if (
                    filter_var(
                        @$_SERVER['HTTP_X_FORWARDED_FOR'],
                        FILTER_VALIDATE_IP
                    )
                ) {
                    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
                }
                if (
                    filter_var(@$_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP)
                ) {
                    $ip = $_SERVER['HTTP_CLIENT_IP'];
                }
            }
        }
        $purpose = str_replace(
            ['name', "\n", "\t", ' ', '-', '_'],
            null,
            strtolower(trim($purpose))
        );
        $support = [
            'country',
            'countrycode',
            'state',
            'region',
            'city',
            'location',
            'address',
        ];
        $continents = [
            'AF' => 'Africa',
            'AN' => 'Antarctica',
            'AS' => 'Asia',
            'EU' => 'Europe',
            'OC' => 'Australia (Oceania)',
            'NA' => 'North America',
            'SA' => 'South America',
        ];
        if (
            filter_var($ip, FILTER_VALIDATE_IP) &&
            in_array($purpose, $support)
        ) {
            $ipdat = @json_decode(
                file_get_contents('http://www.geoplugin.net/json.gp?ip=' . $ip)
            );
            if (@strlen(trim($ipdat->geoplugin_countryCode)) == 2) {
                switch ($purpose) {
                    case 'location':
                        $output = [
                            'city' => @$ipdat->geoplugin_city,
                            'state' => @$ipdat->geoplugin_regionName,
                            'country' => @$ipdat->geoplugin_countryName,
                            'country_code' => @$ipdat->geoplugin_countryCode,
                            'continent' => @$continents[strtoupper($ipdat->geoplugin_continentCode)],
                            'continent_code' => @$ipdat->geoplugin_continentCode,
                        ];
                        break;
                    case 'address':
                        $address = [$ipdat->geoplugin_countryName];
                        if (@strlen($ipdat->geoplugin_regionName) >= 1) {
                            $address[] = $ipdat->geoplugin_regionName;
                        }
                        if (@strlen($ipdat->geoplugin_city) >= 1) {
                            $address[] = $ipdat->geoplugin_city;
                        }
                        $output = implode(', ', array_reverse($address));
                        break;
                    case 'city':
                        $output = @$ipdat->geoplugin_city;
                        break;
                    case 'state':
                        $output = @$ipdat->geoplugin_regionName;
                        break;
                    case 'region':
                        $output = @$ipdat->geoplugin_regionName;
                        break;
                    case 'country':
                        $output = @$ipdat->geoplugin_countryName;
                        break;
                    case 'countrycode':
                        $output = @$ipdat->geoplugin_countryCode;
                        break;
                }
            }
        }
        return $output;
    }
    public function change_locale($locale)
    {
        //check localization
        $data = $this->ip_info('Visitor', 'Location');
        $loc = 'fr';
        if (
            $data['continent_code'] == 'AN' ||
            $data['continent_code'] == 'AS' ||
            $data['continent_code'] == 'OC' ||
            $data['continent_code'] == 'NA' ||
            $data['continent_code'] == 'SA'
        ) {
            $loc = 'en';
        } elseif ($data['continent_code'] == 'AF') {
            $af_en_array = ['GHA', 'KEN', 'LBR', 'NGA', 'SLE', 'ZAF'];
            if (in_array($data['country_code'], $af_en_array)) {
                $loc = 'en';
            } else {
                $loc = 'fr';
            }
        } elseif ($data['continent_code'] == 'EU') {
            $eu_en_array = ['IRL', 'AUS', 'NZL', 'GBR', 'USA'];
            if (in_array($data['country_code'], $eu_en_array)) {
                $loc = 'en';
            } else {
                $loc = 'fr';
            }
        }
        if ($loc == $locale) {
            return false;
        } else {
            return true;
        }
        //End localization
    }

    public function get_user_locale()
    {
        //check localization
        $data = $this->ip_info('Visitor', 'Location');
        $loc = 'fr';
        if (
            $data['continent_code'] == 'AN' ||
            $data['continent_code'] == 'AS' ||
            $data['continent_code'] == 'OC' ||
            $data['continent_code'] == 'NA' ||
            $data['continent_code'] == 'SA'
        ) {
            $loc = 'en';
        } elseif ($data['continent_code'] == 'AF') {
            $af_en_array = ['GHA', 'KEN', 'LBR', 'NGA', 'SLE', 'ZAF'];
            if (in_array($data['country_code'], $af_en_array)) {
                $loc = 'en';
            } else {
                $loc = 'fr';
            }
        } elseif ($data['continent_code'] == 'EU') {
            $eu_en_array = ['IRL', 'AUS', 'NZL', 'GBR', 'USA'];
            if (in_array($data['country_code'], $eu_en_array)) {
                $loc = 'en';
            } else {
                $loc = 'fr';
            }
        }
        return $loc;
    }

    public function compressImage(
        $source_image,
        $compress_image,
        $jpg_percent = 35,
        $png_prob = 3
    ) {
        $image_info = getimagesize($source_image);
        $source_path_redim = $this->resize_image($source_image, 1920, 1080);
        if (
            $image_info['mime'] == 'image/jpeg' ||
            $image_info['mime'] == 'image/jpg'
        ) {
            $source_image = imagecreatefromjpeg($source_image);
            imagejpeg($source_image, $compress_image, $jpg_percent); //for jpeg or gif, it should be 0-100
        } elseif ($image_info['mime'] == 'image/png') {
            $source_image = imagecreatefrompng($source_image);
            imagepng($source_image, $compress_image, $png_prob); //for png it should be 0 to 9
        }
        return $compress_image;
    }

    public function resize_image($file, $w, $h, $crop = false)
    {
        list($width, $height) = getimagesize($file);
        $image_info = getimagesize($file);
        $src = null;
        $r = $width / $height;
        if ($crop) {
            if ($width > $height) {
                $width = ceil($width - $width * abs($r - $w / $h));
            } else {
                $height = ceil($height - $height * abs($r - $w / $h));
            }
            $newwidth = $w;
            $newheight = $h;
        } else {
            if ($w / $h > $r) {
                $newwidth = $h * $r;
                $newheight = $h;
            } else {
                $newheight = $w / $r;
                $newwidth = $w;
            }
        }
        if ($image_info['mime'] == 'image/jpeg' || $image_info['mime'] == 'image/jpg') {
            $src = imagecreatefromjpeg($file);
        } elseif ($image_info['mime'] == 'image/png') {
            $src = imagecreatefrompng($file);
        }
        $dst = imagecreatetruecolor($newwidth, $newheight);
        imagecopyresampled(
            $dst,
            $src,
            0,
            0,
            0,
            0,
            $newwidth,
            $newheight,
            $width,
            $height
        );

        return $dst;
    }

    public function getIPAddress()
    {
        //whether ip is from the share internet
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        //whether ip is from the proxy
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        //whether ip is from the remote address
        else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;
    }

    public function multi_unique($array, $key)
    {
        $temp_array = [];
        foreach ($array as &$v) {
            if (!isset($temp_array[$v[$key]])) {
                $temp_array[$v[$key]] = &$v;
            }
        }
        $array = array_values($temp_array);
        return $array;
    }

    function verifierDate($date) {
        // Vérifie si la date est au format MM/DD/YYYY
        if (!preg_match("/^(0[1-9]|1[0-2])\/([0[1-9]|1[0-2]|2[0-9]|3[01])\/([0-9]{4})$/", $date)) {
            // La date n'est pas valide ou est au format DD/MM/YYYY
            // Convertit la date au format MM/DD/YYYY
            $date = explode("/", $date);
            $date = $date[1] . "/" . $date[0] . "/" . $date[2];
        }

        // Retourne la date
        return $date;
    }

}
