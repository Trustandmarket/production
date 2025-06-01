<?php

// src/App/Controller/AdminController.php

namespace App\Controller\Admin\Annonces;

use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\WpPosts;
use App\Entity\User;
use App\Entity\WpUsermeta;
use App\Entity\WpPostmeta;
use App\Service\ServiceManager;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Config\Loader\FileLoader;

class AnnonceAdminController extends AbstractController
{
    private $sm;
    protected $em;
    public function __construct(ServiceManager $sm, EntityManagerInterface $em)
    {
        $this->sm = $sm;
        $this->em = $em;
    }
    public function detailAction()
    {
        // change the properties of the given entity and save the changes
        $id = $this->request->query->get('id');
        $name = $this->em->getRepository(WpPosts::class)->find($id)->getPostName();
        $url= $this->generateUrl('annonces_detailsAnnonce', array('id' => $name));
        return $this->redirect($url);
    }

    public function newAction()
    {
        $user = $this->em->getRepository(User::class)->findAll();
        return $this->render('admin/writte_article.html.twig', [
          'categorie' => $this->sm->postCategorieWithMultilang("product_cat", 0),
          'user'=> $user
        ]);
    }
    public function editAction()
    {
        $id = $this->request->query->get('id');
        $name = $this->em->getRepository(WpPosts::class)->find($id)->getPostName();
        $user = $this->em->getRepository(User::class)->findAll();

        $DetailsAnnonce = $this->sm->readAllAnnonceDataByName($name);
        //dd($DetailsAnnonce);
        return $this->render('admin/edit_article.html.twig', [
          'a'=>$DetailsAnnonce,
          'categorie' => $this->sm->postCategorieWithMultilang("product_cat", 0),
          'user'=> $user,
          'scategorie' => $this->sm->postCategorieWithMultilang("product_cat", $DetailsAnnonce['parent']),
        ]);
    }

    public function getPriceByPostname(string $name)
    {
        $post = $this->em->getRepository(WpPosts::class)->findOneByPostName($name);
        if ($post) {
            $prix = $this->sm->getPostStringDataValue($post->getId(), '_price');
            return new Response($prix);
        } else {
            return new Response('');
        }
    }

    public function getUsername(int $user)
    {
        $name = $this->sm->getUserStringDataValue($user, 'first_name'). ' ' . $this->sm->getUserStringDataValue($user, 'last_name');
        if (trim($name) == '') {
            return new Response('No User');
        } else {
            return new Response($name);
        }
    }

    public function getTypePrestationByUser($user)
    {
        $name = $this->sm->getUserStringDataValue($user, 'first_name'). ' ' . $this->sm->getUserStringDataValue($user, 'last_name');
        if (trim($name) == '') {
            return new Response('No User');
        } else {
            return new Response($name);
        }
    }

      public function getClientFromDevisByPostId($postId)
      {
          $clientId = $this->sm->getPostStringDataValue($postId, 'client');
          $user = $this->em->getRepository(User::class)->find($clientId);
          if ($user) {
              return new Response($user->getEmailCanonical());
          } else {
              return new Response('');
          }
      }

    public function getCategoryFromAnnounce($annonce, $locale=null)
    {
        $x = 0;
        $tempType = '';
        if ($locale == 'en') {
            $loc = 'en_US';
        }
        $categorie = $this->sm->getCategoryFromAnnounce($annonce);
        //dd($locale);
        if (count($categorie) > 0) {
            $cat = $this->em->getRepository(WpTermsTranslation::class)
            ->findOneBy(['foreignKey' => $categorie[0]['termId'], 'field' => 'name', 'locale'=>$locale]);
            if ($cat) {
                $cat = $cat->getContent();
            };
            //dd($cat);
            $parentId = $categorie[0]['parent'];
            while ($parentId != 0) {
                $temp = $this->em->getRepository(WpTermTaxonomy::class)->findOneByTermTaxonomyId($parentId);
                if ($temp) {
                    $tempType = $temp->getTaxonomy();
                }
                if ($temp && $parentId != 0) {
                    $tempTerm = $this->em->getRepository(WpTermsTranslation::class)
                    ->findOneBy(['foreignKey' => $temp->getTermId(), 'field' => 'name', 'locale'=>$locale]);
                    if ($tempTerm) {
                        $cat = $tempTerm->getContent() . ' | ' . $cat;
                    }
                    $parentId = $temp->getParent();
                } elseif ($parentId == 0 && $temp) {
                    $cat = $temp->getDescription() . ' | ' . $cat;
                } elseif ($tempType = 'product_cat') {
                    $cat = 'Services' . ' | ' . $cat;
                }
                $x++;
            }
            return new Response($cat);
        } else {
            return new Response('Pas de Categorie');
        }
    }
}
