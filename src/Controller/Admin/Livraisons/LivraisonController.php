<?php

// src/App/Controller/AdminController.php

namespace App\Controller\Admin\Livraisons;

use App\Entity\User;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use EasyCorp\Bundle\EasyAdminBundle\Event\EasyAdminEvents;
use App\Entity\WpPosts;
use App\Entity\WpPostmeta;
use App\Entity\WpOptions;
use App\Entity\WpUsermeta;
use App\Entity\WpTerms;
use App\Entity\WpTermTaxonomy;
use App\Service\ServiceManager;
use App\Service\Payment;
use App\Service\ToolsMeta;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpFoundation\JsonResponse;

class LivraisonController extends AbstractController
{
    private $entityManager;
    private $sm;
    private $payment;
    private $tools;

    public function __construct(
        EntityManagerInterface $entityManager,
        ServiceManager $sm,
        Payment $payment,
        ToolsMeta $tools
    ) {
        $this->entityManager = $entityManager;
        $this->sm = $sm;
        $this->payment = $payment;
        $this->tools = $tools;
    }

    /**
     * @Route("/{_locale}/admin/new_critere_type", name="admin_new_critere_type")
     * @param Request $request
     * @return Response
     */
    public function newCritereType(Request $request)
    {
        //Val inf = postStatus;cond inf = toPing;Val sup = pinged;cond sup = guid;
        $date = new \DateTime();
        $r = 0;

        if ($request->get('state') == 'edit') {
            $critere = $this->entityManager->getRepository(WpPosts::class)->find($request->get('id_crit'));
            if ($critere) {
                $critere->setPostStatus($request->get('val_inf'));
                $critere->setToPing($request->get('int_inf'));
                $critere->setPinged($request->get('val_sup'));
                $critere->setGuid($request->get('int_sup'));
                $critere->setPostName(
                    $this->sm->slugify($request->get('crit_name'))
                );
                if ($request->getLocale() == 'fr') {
                    $critere->setPostTitle($request->get('crit_name'));
                    $critere->setPostContent($request->get('crit_texte'));
                } else {
                    $critere->setPostExcerpt($request->get('crit_name'));
                    $critere->setPostContentFiltered(
                        $request->get('crit_texte')
                    );
                }
            }
            $this->entityManager->persist($critere);
            $this->entityManager->flush();

            $r = $critere->getId();
        } else {
            $r = $this->sm->createPosts(
                $this->getUser()->getId(),
                $date,
                $date,
                $request->get('crit_texte'),
                $request->get('crit_name'),
                $request->get('crit_name'),
                $request->get('val_inf'),
                'open',
                'closed',
                $request->get('crit_name'),
                $this->sm->slugify($request->get('crit_name')),
                $request->get('int_inf'),
                $request->get('val_sup'),
                $date,
                $date,
                $request->get('crit_texte'),
                0,
                $request->get('int_sup'),
                0,
                'critere_livraison',
                'menu',
                0,
                0,
                $request->getLocale()
            );
        }
        return $this->render('admin/resultat.html.twig', ['result' => $r]);
    }

    /**
     * @Route("/{_locale}/admin/accept_ref_request", name="admin_accept_ref_request")
     * @param Request $request
     * @param MailerInterface $mailer
     * @return Response
     */
    public function accept_ref_request(Request $request, MailerInterface $mailer)
    {
        $r = $this->sm->updateUserMeta(
            $request->get('id'),
            'demande_referencement_produit',
            'publish'
        );
        $nom =
            $this->sm->getUserStringDataValue(
                $request->get('id'),
                'first_name'
            ) .
            ' ' .
            $this->sm->getUserStringDataValue($request->get('id'), 'last_name');
        $userEmail = $this->entityManager
            ->getRepository(User::class)
            ->find($request->get('id'))
            ->getEmailCanonical();
        //Notifier l'utilisateur
/*         $body = [
            'Messages' => [
                [
                    'From' => [
                        'Email' => $_SERVER['MJ_EMAIL_FROM'],
                        'Name' => "Trust & Market"
                    ],
                    'To' => [
                        [
                            'Email' => $userEmail,
                            'Name' => $nom
                        ]
                    ],
                    'TemplateID' => 5388926,
                    'TemplateLanguage' => true,
                    'Subject' => "Acceptation de référencement produit",
                    'Variables' => array("nom_prenom" => $nom)
                ]
            ]
        ]; */

        return $this->render('admin/resultat.html.twig', ['result' => 1]);
    }

    /**
     * @Route("/{_locale}/admin/reject_ref_request", name="admin_reject_ref_request")
     * @param Request $request
     * @return Response
     */
    public function reject_ref_request(Request $request)
    {
        $r = $this->sm->updateUserMeta(
            $request->get('id'),
            'demande_referencement_produit',
            'rejected'
        );
        $nom = $this->sm->getUserStringDataValue($request->get('id'), 'first_name') . ' ' . $this->sm->getUserStringDataValue($request->get('id'), 'last_name');
        $userEmail = $this->entityManager->getRepository(User::class)->find($request->get('id'))->getEmailCanonical();
        
        //Notifier l'utilisateur
/*         $body = [
            'Messages' => [
                [
                    'From' => [
                        'Email' => $_SERVER['MJ_EMAIL_FROM'],
                        'Name' => "Trust & Market"
                    ],
                    'To' => [
                        [
                            'Email' => $userEmail,
                            'Name' => $nom
                        ]
                    ],
                    'TemplateID' => 5388885,
                    'TemplateLanguage' => true,
                    'Subject' => "Refus de référencement produits",
                    'Variables' => array("nom_prenom" => $nom)
                ]
            ]
        ]; */
        return $this->render('admin/resultat.html.twig', ['result' => 1]);
    }

    /**
     * @Route("/{_locale}/admin/new_delivery_type", name="admin_new_delivery_type")
     * @param Request $request
     * @return Response
     */
    public function newDeliveryType(Request $request)
    {
        $date = new \DateTime();
        $r = 0;
        if ($request->get('state') == 'edit') {
            $livraison = $this->entityManager
                ->getRepository(WpPosts::class)
                ->find($request->get('livraison_id'));
            if ($livraison) {
                $livraison->setGuid($request->get('prix'));
                $livraison->setPostParent($request->get('cat_critere'));
                $livraison->setPostName(
                    $this->sm->slugify($request->get('name'))
                );
                if ($request->getLocale() == 'fr') {
                    $livraison->setPostTitle($request->get('name'));
                    $livraison->setPostContent($request->get('cat_duree'));
                } else {
                    $livraison->setPostExcerpt($request->get('name'));
                    $livraison->setPostContentFiltered(
                        $request->get('cat_duree')
                    );
                }
            }
            $this->entityManager->persist($livraison);
            $this->entityManager->flush();

            $r = $livraison->getId();
        } else {
            // namefr => title; duree = postContent, $critere = parent, prix = guid
            $r = $this->sm->createPosts(
                $this->getUser()->getId(),
                $date,
                $date,
                $request->get('cat_duree'),
                $request->get('name'),
                $request->get('name'),
                $request->get('name'),
                'open',
                'closed',
                $request->get('name'),
                $this->sm->slugify($request->get('name')),
                $request->get('name'),
                $request->get('name'),
                $date,
                $date,
                $request->get('name'),
                $request->get('cat_critere'),
                $request->get('prix'),
                0,
                'post_livraison',
                'menu',
                0,
                0,
                $request->getLocale()
            );
        }
        return $this->render('admin/resultat.html.twig', ['result' => $r]);
    }


    //Gestion des livraisons gratuites

    /**
     * @Route("/{_locale}/admin/new_critere_free_op", name="admin_livraison_new_critere_free_op", requirements={"_locale": "fr"}, methods="post")
     * @param Request $request
     * @return Response
     */
    public function newCritereFreeOp(Request $request)
    {
        //Statut = postStatus; Montant = guid;
        $date = new \DateTime();
        $r = 0;
        if ($request->get('state') == 'edit') {
            $critere = $this->entityManager
                ->getRepository(WpPosts::class)
                ->find($request->get('id'));
            if ($critere) {
                $critere->setPostStatus($request->get('statut'));
                $critere->setGuid($request->get('montant'));
            }
            $this->entityManager->persist($critere);
            $this->entityManager->flush();

            $meta = $this->entityManager->getRepository(WpPostmeta::class)
            ->findOneBy([
                'metaKey' => 'ops_free_delivery_category',
                'postId' => $critere->getId(),
            ]);
            if ($meta) {
                $this->entityManager->remove($meta);
                $this->entityManager->flush();
            }
            $r = $this->sm->createPostMeta(
                $critere->getId(),
                'ops_free_delivery_category',
                $request->get('categorie'),
                $request->getLocale()
            );
        } elseif ($request->get('state') == 'create') {
            $r = $this->sm->createPosts1(
                $this->getUser()->getId(),
                $date,
                $date,
                '',
                '',
                '',
                $request->get('statut'),
                'open',
                'closed',
                '',
                '',
                0,
                0,
                $date,
                $date,
                '',
                0,
                $request->get('montant'),
                0,
                'ops_free_delivery',
                'menu',
                0,
                0,
                $request->getLocale()
            );
            $this->sm->createPostMeta(
                $r,
                'ops_free_delivery_category',
                $request->get('categorie'),
                $request->getLocale()
            );
        }
        return $this->render('admin/resultat.html.twig', ['result' => $r]);
    }
}
