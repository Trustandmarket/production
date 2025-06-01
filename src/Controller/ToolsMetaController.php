<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\{WpPosts, WpUsermeta, User, WpTermTaxonomy, WpTerms, WpOptions};
use Doctrine\ORM\EntityManagerInterface;

class ToolsMetaController extends AbstractController
{
    private $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager
    )
    {
        $this->entityManager = $entityManager;
    }
    public function getLoginRegisterImage($code)
    {
        $page_background_image = $this->entityManager->getRepository(WpOptions::class)->findOneByOptionName($code);
        if ($page_background_image) {
            return new Response($page_background_image->getOptionValue());
        } else {
            return new Response('');
        }
    }

    public function getPageTitle($code)
    {
        $meta_desc = $this->entityManager->getRepository(WpPosts::class)->findOneBy([
            'postExcerpt' => $code,
            'postType' => 'page-statique',
        ]);
        if ($meta_desc) {
            return new Response($meta_desc->getPostTitle());
        } else {
            return new Response('');
        }
    }

    public function getPageDescription($code)
    {
        $meta_desc = $this->entityManager->getRepository(WpPosts::class)->findOneBy([
            'postExcerpt' => $code,
            'postType' => 'page-statique',
        ]);
        if ($meta_desc) {
            return new Response($meta_desc->getPostContent());
        } else {
            return new Response('');
        }
    }
}
