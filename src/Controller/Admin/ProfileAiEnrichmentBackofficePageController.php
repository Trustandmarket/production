<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProfileAiEnrichmentBackofficePageController extends AbstractController
{
    #[Route('/{_locale}/admin/ai-enrichment/dashboard', name: 'admin_ai_enrichment_dashboard', methods: ['GET'])]
    public function dashboard(string $_locale): Response
    {
        $this->denyIfNoBackofficeAiAccess();

        return $this->render('admin/ai_enrichment/dashboard.html.twig', [
            'locale' => $_locale,
            'jobs_url' => $this->generateUrl('admin_ai_enrichment_jobs', ['_locale' => $_locale]),
        ]);
    }

    #[Route('/{_locale}/admin/ai-enrichment/jobs', name: 'admin_ai_enrichment_jobs', methods: ['GET'])]
    public function jobs(string $_locale): Response
    {
        $this->denyIfNoBackofficeAiAccess();

        return $this->render('admin/ai_enrichment/jobs.html.twig', [
            'locale' => $_locale,
            'dashboard_url' => $this->generateUrl('admin_ai_enrichment_dashboard', ['_locale' => $_locale]),
            'detail_url_template' => $this->generateUrl('admin_ai_enrichment_job_detail', ['_locale' => $_locale, 'id' => 0]),
        ]);
    }

    #[Route('/{_locale}/admin/ai-enrichment/jobs/{id<\d+>}', name: 'admin_ai_enrichment_job_detail', methods: ['GET'])]
    public function detail(string $_locale, int $id): Response
    {
        $this->denyIfNoBackofficeAiAccess();

        // Defensive fallback: reuse the stable jobs page with selected_job to avoid
        // hard failure if the dedicated detail template is unavailable on target env.
        return $this->redirectToRoute('admin_ai_enrichment_jobs', [
            '_locale' => $_locale,
            'selected_job' => $id,
        ]);
    }

    private function denyIfNoBackofficeAiAccess(): void
    {
        if (!$this->getUser()) {
            throw $this->createAccessDeniedException('Utilisateur non authentifie.');
        }

        if (!$this->isGranted('ROLE_SUPER_ADMIN') && !$this->isGranted('ROLE_COMMERCE')) {
            throw $this->createAccessDeniedException('Acces refuse.');
        }
    }
}
