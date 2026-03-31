<?php

namespace App\Controller;

use App\Entity\WpOptions;
use App\Entity\WpPosts;
use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("", requirements={"_locale": "fr"}, name="profile_")
 */
class ProfileReservationController extends AbstractController
{
    private $service_manager;
    private $em;

    public function __construct(
        ServiceManager $service_manager,
        EntityManagerInterface $em
    ) {
        $this->service_manager = $service_manager;
        $this->em = $em;
    }

    /**
     * @Route("/{_locale}/profil-utilisateur/cancelReservation/{id}", name="cancelledReservationAnnounce")
     * @param Request $request
     * @return Response
     */
    public function cancelReservationAnnounce(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $r = $this->em->getRepository(WpPosts::class)->find($request->get('id'));
        if ($r->getPostType() == 'shop_order') {
            $r->setPostStatus('wc-cancelled');
        }
        $this->em->persist($r);
        $this->em->flush();
        return $this->render('admin/resultat.html.twig', [
            'result' => 1,
        ]);
    }

    /**
     * @Route("/{_locale}/profil-utilisateur/reservations", name="reservations",methods={"GET"})
     */
    public function reservations()
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $userId = $this->getUser()->getId();
        return $this->render('profile/reservations.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'annoncesBrouillon' => $this->service_manager->readListAnnonceDataOfUser(
                $userId,
                'draft'
            ),
            'annoncesModeration' => $this->service_manager->readListAnnonceDataOfUser(
                $userId,
                'moderation'
            ),
            'annoncesRejetees' => $this->service_manager->readListAnnonceDataOfUser(
                $userId,
                'trash'
            ),
            'annoncesPubliees' => $this->service_manager->readListAnnonceDataOfUser(
                $userId,
                'publish'
            ),
            'annoncesReserves' => $this->service_manager->readListAnnonceDataOfUser(
                $userId,
                'reserved'
            ),
            'annoncesTerminees' => $this->service_manager->readListAnnonceDataOfUser(
                $userId,
                'ended'
            ),
            'annoncesAnnulees' => $this->service_manager->readListAnnonceDataOfUser(
                $userId,
                'drop'
            ),
            'devisEnAttente' => $this->service_manager->readListDevisDataOfUser(
                $userId,
                'devis-pending'
            ),
            'devisEnBrouillon' => $this->service_manager->readListDevisDataOfUser(
                $userId,
                'devis-draft'
            ),
            'reservationsEnCours' => $this->service_manager->readListReservationOfUser(
                $userId,
                'wc-in-progress'
            ),
            'reservationsTerminees' => $this->service_manager->readListReservationOfUser(
                $userId,
                'wc-completed'
            ),
            'reservationsAnnulees' => $this->service_manager->readListReservationOfUser(
                $userId,
                'wc-cancelled'
            ),
            'reservationsDevisEnAttente' => $this->service_manager->readListReservationDevisOfUser(
                $userId,
                'devis-pending'
            ),
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'youtube_url' => $this->em->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
        ]);
    }
}
