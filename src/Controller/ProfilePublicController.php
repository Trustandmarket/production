<?php

namespace App\Controller;

use App\Entity\Departement;
use App\Entity\User;
use App\Entity\wpComments;
use App\Entity\WpOptions;
use App\Entity\WpPosts;
use App\Entity\WpTermTaxonomy;
use App\Service\DataAccessLayer\Annonces;
use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("", requirements={"_locale": "fr"}, name="profile_")
 */
class ProfilePublicController extends AbstractController
{
    private $service_manager;
    private $annonces_access_layer;
    private $em;

    public function __construct(
        ServiceManager $service_manager,
        Annonces $annonces_access_layer,
        EntityManagerInterface $em
    ) {
        $this->service_manager = $service_manager;
        $this->annonces_access_layer = $annonces_access_layer;
        $this->em = $em;
    }

    /**
     * @Route("/{_locale}/profil-utilisateur/details/{id}", name="detailsProfessionnel", requirements={"_locale": "fr"})
     * @param Request $request
     * @return Response
     */
    public function detailsProfessionnel(Request $request)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $arr = explode('-', $request->get('id'));
        $user_id = $arr[array_key_last($arr)];
        $user = $this->em->getRepository(User::class)->find($user_id);
        $profileCompletionRate = (int) $this->service_manager->getUserStringDataValue((int) $user_id, 'profile_completion_rate');
        $isOwner = (int) $this->getUser()->getId() === (int) $user_id;

        if ($profileCompletionRate < 80 && !$isOwner) {
            return $this->redirectToRoute('index', ['_locale' => $request->getLocale()]);
        }
        $noPage = 1;
        if ($request->get('noPage')) {
            $noPage = $request->get('noPage');
        }
        $detailsPro = $this->annonces_access_layer->readAllProData($user_id, $noPage);
        $lastComment = '';
        $idDernierPost = $this->em->getRepository(WpPosts::class)->findBy(['postAuthor' => $user_id], ['id' => 'DESC']);
        if ($idDernierPost != null) {
            $idDernierPost = $idDernierPost['0']->getId();
            $lastComment = $this->em
                ->getRepository(wpComments::class)
                ->findBy(['commentPostId' => $idDernierPost]);
            if ($lastComment != null) {
                $lastComment = $lastComment['0']->getCommentContent();
            } else {
                $lastComment = '';
            }
        }

        $competences = $this->service_manager->readUserMeta($user_id, 'competence');
        $raison_sociale = $this->service_manager->readUserMeta($user_id, 'raison_sociale');
        $principal_activity = $this->service_manager->readUserMeta($user_id, 'activite_principale');
        if ($principal_activity) {
            $principal_activity = $this->em->getRepository(WpTermTaxonomy::class)->findOneBy(['termTaxonomyId' => $principal_activity->getMetaValue()]);
        }
        $competence = [];
        if ($competences) {
            $competence = explode(',', $competences->getMetaValue());
        }
        $departements = $this->em->getRepository(Departement::class)->findAll();

        $statut_kyc = null;
        $avatar = '';
        $avatars = $this->service_manager->readUserMeta($user_id, 'basic_user_avatar');
        if ($avatars && $avatars->getMetaValue()) {
            $img = @unserialize($avatars->getMetaValue());
            $avatar = end($img);
        }
        return $this->render('annonces/detailsProfil.html.twig', [
            'user' => $user,
            'header' => $this->service_manager->naveMenuItem(10),
            'footer' => $this->service_manager->naveMenuItem(18),
            'detailsPro' => $detailsPro['data'],
            'profileCompletionRate' => $profileCompletionRate,
            'lastComment' => $lastComment,
            'noPage' => $noPage,
            'pages' => $detailsPro['pages'],
            'prestations' => $this->service_manager->postCategorieWithMultilang('product_cat', 0),
            'youtube_url' => $this->em->getRepository(WpOptions::class)->findOneByOptionName('home-youtube'),
            'competence' => $competence,
            'raison_sociale' => $raison_sociale,
            'principal_activity' => $principal_activity,
            'statut_kyc' => $statut_kyc,
            'avatar' => $avatar,
            'departements' => $departements
        ]);
    }
}
