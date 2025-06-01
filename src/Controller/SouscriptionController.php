<?php

namespace App\Controller;

use App\Entity\Abonnement;
use App\Entity\OffreInterne;
use App\Entity\User;
use App\Entity\WpOptions;
use App\Service\Payment;
use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

class SouscriptionController extends AbstractController
{
    private $service_manager;
    private $entityManager;
    private $twig;
    private $payment;

    public function __construct(
        ServiceManager $service_manager,
        EntityManagerInterface $entityManager,
        Environment $twig,
        Payment $payment
    )
    {
        $this->service_manager = $service_manager;
        $this->entityManager = $entityManager;
        $this->twig = $twig;
        $this->payment = $payment;
    }
    #[Route('/{_locale}/souscription', name: 'app_souscription')]
    public function index(): Response
    {
        $abonnement = $this->entityManager->getRepository(Abonnement::class)
            ->findAbonnementByUser($this->getUser());
        $offres = $this->entityManager->getRepository(OffreInterne::class)->findBy(array(), array('tarif' => 'ASC'));
        return $this->render('souscription/index.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'youtube_url' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'offres' => $offres,
            'abonnement' => $abonnement
        ]);
    }

    #[Route('/{_locale}/souscription/offre_payante', name: 'app_souscription_offre_payante_get', methods: 'GET')]
    public function app_souscription_offre_payante_get(RequestStack $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $dateJour = new \DateTime();
        $abonnement = $this->entityManager->getRepository(Abonnement::class)
            ->findAbonnementByUser($this->getUser());
        $user = $this->entityManager->getRepository(User::class)->find($this->getUser()->getId());
        $offre = null;
        if($request->getCurrentRequest()->get('forfait')){
            $offre = $this->entityManager->getRepository(OffreInterne::class)
                ->find($request->getCurrentRequest()->get('forfait'));
        }
        $offres = $this->entityManager->getRepository(OffreInterne::class)->findBy(array(), array('tarif' => 'ASC'));

        $dateFin = $abonnement[0]->getDateFin();
        if($dateFin){
            $dateFin = $dateFin->setTime(0, 0);
        }else{
            $dateFin = $dateJour;
        }

        $dateDebut = $abonnement[0]->getCreatedAt();
        $dateDebut = $dateDebut->setTime(0, 0);
        $dateJour = $dateJour->setTime(0, 0);
        $differentielJour = date_diff($dateFin,  $dateJour, true)->d;
        $differentielFin = date_diff($dateFin, $dateDebut, true)->d;
        if($differentielFin == 0){
            $differentielFin = 1;
        }
        $prorata = -1*($abonnement[0]->getTarif() * ($differentielJour)/($differentielFin));

        $totalHt = $offre->getTarif() + $prorata;
        if(abs($prorata) == 0){
            $totalHt = $offre->getTarif();
        }

        //Recuperer la carte
        $card = null;
        $mangopayId = $this->service_manager->getUserStringDataValue($this->getUser()->getId(), 'mp_user_card_id');
        if($mangopayId != ''){
            $card = $this->payment->getCardRegistration($mangopayId);
        }

        //dd($card);
        return $this->render('souscription/souscription_offre_payante.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'youtube_url' => $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'offres' => $offres,
            'user' => $user,
            'prorata' => $prorata,
            'totalAPayer' => $totalHt,
            'totalHt' => $totalHt,
            'offre' => $offre,
            'card' => $card,
            'abonnement' => $abonnement,
            'baseUrl' => $_SERVER['MANGOPAY_BASE_URL'],
            'mpClient' => $_SERVER['MANGOPAY_CLIENT'],
            'mpPassword' => $_SERVER['MANGOPAY_PASSWORD'],
            'mpClientId' => $mangopayId
        ]);
    }

}
