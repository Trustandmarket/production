<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\ServiceManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use App\Entity\WpPosts;
use App\Entity\WpOptions;

class ProjectController extends AbstractController
{
    public function __construct(ServiceManager $service_manager, MailerInterface $mailer)
    {
        $this->service_manager = $service_manager;
        $this->mailer = $mailer;
    }

    /**
     * @Route("/{_locale}/votre-projet", requirements={"_locale": "fr"}, name="project_index")
     */
    public function index()
    {
        return $this->render('project/index.html.twig', [
            'meta_page' => $this->service_manager->getPageStatique(
                'votre-projet'
            ),
            'listeSousCategories' => $this->service_manager->postCategorie1(
                'product_cat'
            ),
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0)
        ]);
    }

    public function sendProjectRequest(Request $request)
    {
        $date = new \DateTime();
        $sousCategories = $this->service_manager->postCategorie1('product_cat');
        //Store all data througt database
        $postProject = $this->service_manager->createPosts1(
            0,
            $date,
            $date,
            $request->get('message'),
            '',
            '',
            '',
            'closed',
            'closed',
            '',
            '',
            '',
            '',
            $date,
            $date,
            '',
            0,
            '',
            0,
            'projet',
            '',
            0,
            0
        );

        $id = $postProject;

        //Additionnal datas
        $last_name = $this->service_manager->createPostMeta(
            $id,
            'project_last_name',
            $request->get('last_name'),
            $request->getLocale()
        );

        $first_name = $this->service_manager->createPostMeta(
            $id,
            'project_first_name',
            $request->get('first_name'),
            $request->getLocale()
        );

        $email = $this->service_manager->createPostMeta(
            $id,
            'project_email',
            $request->get('email'),
            $request->getLocale()
        );

        $telephone = $this->service_manager->createPostMeta(
            $id,
            'project_telephone',
            $request->get('telephone'),
            $request->getLocale()
        );

        $votre_projet = $this->service_manager->createPostMeta(
            $id,
            'project_votre_projet',
            $request->get('votre_projet'),
            $request->getLocale()
        );

        $calendrier = $this->service_manager->createPostMeta(
            $id,
            'project_calendar',
            $request->get('calendrier'),
            $request->getLocale()
        );

        $lieu = $this->service_manager->createPostMeta(
            $id,
            'project_place',
            $request->get('place'),
            $request->getLocale()
        );

        $date_range = $this->service_manager->createPostMeta(
            $id,
            'project_date_range',
            $request->get('date_range'),
            $request->getLocale()
        );

        $nombre_personnes = $this->service_manager->createPostMeta(
            $id,
            'project_nombre_personnes',
            $request->get('nombre_personnes'),
            $request->getLocale()
        );

        $votre_budget = $this->service_manager->createPostMeta(
            $id,
            'project_votre_budget',
            $request->get('votre_budget'),
            $request->getLocale()
        );

        $tableauSousCategoriesSelectionnees = array();
        for ($i = 0; $i < 21; $i++) {
            //sizeof($sousCategories)
            //if ($request->get("produits".$sousCategories[$i]["termId"]) == "produits".$i){
            if ($request->get('produits' . $i) == 'on') {
                array_push(
                    $tableauSousCategoriesSelectionnees,
                    $sousCategories[$i]['name']
                );
            }
        }
        $sousCategories = $this->service_manager->createPostMeta(
            $id,
            'project_sous_categorie',
            @serialize($tableauSousCategoriesSelectionnees),
            $request->getLocale()
        );
        //Send Email notifications
        $autres = '';
        if ($request->get('autres') != '') {
            $autres = $request->get('autres');
        }
        $date = new \DateTime();
        $body = $this->twig->render('emails/emailProjets.html.twig', [
            'nom' =>
                $request->get('first_name') . ' ' . $request->get('last_name'),
            'email' => $request->get('email'),
            'telephone' => $request->get('telephone'),
            'votre_projet' => $request->get('votre_projet'),
            'date' => $request->get('calendrier'),
            'lieu' => $request->get('place'),
            'message' => $request->get('message'),
            'autres' => $request->get('autres'),
            'periode' => $request->get('date_range'),
            'nombre_de_personnes' => $request->get('nombre_personnes'),
            'categories_prestations' => $tableauSousCategoriesSelectionnees,
            'votre_budget' => $request->get('votre_budget'),
            'Autres' => $request->get('autres')
        ]);

        /* $message = (new \Swift_Message())
            ->setSubject('VOTRE PROJET TRUST & MARKET')
            ->setFrom($_SERVER['MJ_EMAIL_FROM'])
            ->setTo(['contact@kbr-global.com'])
            ->setBody($body, 'text/html');
        $this->mailer->send($message); */

        return $this->render('admin/resultat.html.twig', [
            'result' => 1
        ]);
    }
}
