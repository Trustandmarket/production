<?php

namespace App\Controller;

use App\Service\ServiceManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("", requirements={"_locale": "fr"}, name="profile_")
 */
class ProfileReferentialController extends AbstractController
{
    private $service_manager;

    public function __construct(ServiceManager $service_manager)
    {
        $this->service_manager = $service_manager;
    }

    /**
     * @Route("/profil-utilisateur/{_locale}/sous_categorie/{id}", name="liste_sous_categorie")
     * @param Request $request
     * @return Response
     */
    public function sousCategorie(Request $request)
    {
        $o = 0;
        if ($request->get('o')) {
            $o = $request->get('o');
        }
        return $this->render('profile/sous_categorie.html.twig', [
            'categorie' => $this->service_manager->postCategorieWithMultilang('product_cat', $request->get('id')),
            'option' => $o,
        ]);
    }
}
