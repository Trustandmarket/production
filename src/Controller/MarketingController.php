<?php

namespace App\Controller;

use App\Service\ServiceManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Service\ToolsMeta;

/**
 * @Route(name="marketing_")
 */
class MarketingController extends AbstractController
{
    private $service_manager;
    private $tools;

    public function __construct(ServiceManager $service_manager, ToolsMeta $tools)
    {
        $this->service_manager = $service_manager;
        $this->tools = $tools;
    }

    /**
     * @Route("/{_locale}/operations-speciales/{slug}", name="details_ops_speciales", requirements={"_locale": "fr"})
     * @param Request $request
     * @return Response
     */
    public function details(Request $request)
    {
        $ops = $this->service_manager->getOneSpecialOpsByName($request->get('slug'));
        $page_slide = ceil(count($ops['articles']) / 4);
        //dd($ops);
        return $this->render('marketing/details.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'ops' => $ops,
            'page_slide' => $page_slide,
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0)
        ]);
    }

    /**
     * @Route("/{_locale}/promo-et-com/{slug}", name="details_promo_com", requirements={"_locale": "fr"})
     * @param Request $request
     * @return Response
     */
    public function details_promo_com(Request $request)
    {
        $ops = $this->service_manager->getOnePromoComByName($request->get('slug'));
        $page_slide = ceil(count($ops['articles']) / 4);
        return $this->render('marketing/details_promo.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'ops' => $ops,
            'page_slide' => $page_slide,
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0)
        ]);
    }

    /**
     * @Route("/{_locale}/leads-gen/{slug}", name="details_leads_gen", requirements={"_locale": "fr"})
     */
    public function details_leads_gen(Request $request)
    {
        $ops = $this->service_manager->getOneLeadsGenByName($request->get('slug'));
        $page_slide = ceil(count($ops['articles']) / 4);
        return $this->render('marketing/details_leads_gen.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'ops' => $ops,
            'page_slide' => $page_slide,
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0)
        ]);
    }

    /**
     * @Route("/{_locale}/leads-gen/email/send", name="leads_gen_send_email", methods="POST")
     */
    public function marketing_leads_gen_send_email(Request $request)
    {
        $r = 0;
        // Prepare the email data
/*         $body = [
            'Messages' => [
                [
                    'From' => [
                        'Email' => $_SERVER['MJ_EMAIL_FROM'],
                        'Name' => 'KBR Global'
                    ],
                    'To' => [
                        [
                            'Email' => 'serviceclients@kbr-global.com',
                            'Name' => 'Service Clients'
                        ]
                    ],
                    'Subject' => 'Leads Gen (' . $request->get('titre') . ')',
                    'HTMLPart' => $this->renderView( // Render the email template with data
                        'emails/marketing/leadsGenEmail.html.twig',
                        [
                            'nom' => $request->get('nom'),
                            'prenom' => $request->get('prenom'),
                            'telephone' => $request->get('numero_telephone'),
                            'adresse_email' => $request->get('email'),
                            'code_postal' => $request->get('code_postal'),
                            'type_evenement' => $request->get('type_evenement'),
                            'date_evenement' => $request->get('date_evenement'),
                            'nombre_invites' => $request->get('nombre_invites'),
                            'message' => $request->get('message')
                        ]
                    )
                ]
            ]
        ]; */


        $r = 1;

        return $this->render('admin/resultat.html.twig', [
            'result' => $r
        ]);
    }

    /**
     * @Route("/{_locale}/univers-trust/{slug}", name="details_univers_trust", requirements={"_locale": "fr"})
     */
    public function details_univers_trust(Request $request)
    {
        $ops = $this->service_manager->getOneExperiencesByName($request->get('slug'));

        return $this->render('marketing/details_univers_trust.html.twig', [
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'ops' => $ops,
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0)
        ]);
    }
}
