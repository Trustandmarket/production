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

        try {
            return $this->render('admin/ai_enrichment/dashboard.html.twig', [
                'locale' => $_locale,
                'jobs_url' => $this->generateUrl('admin_ai_enrichment_jobs', ['_locale' => $_locale]),
            ]);
        } catch (\Throwable $e) {
            return $this->fallbackResponse('dashboard', $_locale, $e);
        }
    }

    #[Route('/{_locale}/admin/ai-enrichment/jobs', name: 'admin_ai_enrichment_jobs', methods: ['GET'])]
    public function jobs(string $_locale): Response
    {
        $this->denyIfNoBackofficeAiAccess();

        try {
            return $this->render('admin/ai_enrichment/jobs.html.twig', [
                'locale' => $_locale,
                'dashboard_url' => $this->generateUrl('admin_ai_enrichment_dashboard', ['_locale' => $_locale]),
                'detail_url_template' => $this->generateUrl('admin_ai_enrichment_job_detail', ['_locale' => $_locale, 'id' => 0]),
            ]);
        } catch (\Throwable $e) {
            return $this->fallbackResponse('jobs', $_locale, $e);
        }
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

    private function fallbackResponse(string $screen, string $locale, \Throwable $e): Response
    {
        $jobsUrl = $this->generateUrl('admin_ai_enrichment_jobs', ['_locale' => $locale]);
        $dashboardUrl = $this->generateUrl('admin_ai_enrichment_dashboard', ['_locale' => $locale]);

        $html = sprintf(
            '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Trust Agentique IA - Fallback</title></head><body style="font-family:Arial,sans-serif;padding:20px;">
            <h2>Trust Agentique IA (fallback)</h2>
            <p>Un incident est survenu sur l ecran <strong>%s</strong>.</p>
            <p><a href="%s">Dashboard</a> | <a href="%s">Jobs</a></p>
            <pre style="background:#f8fafc;border:1px solid #cbd5e1;padding:12px;white-space:pre-wrap;">%s</pre>
            </body></html>',
            htmlspecialchars($screen, ENT_QUOTES),
            htmlspecialchars($dashboardUrl, ENT_QUOTES),
            htmlspecialchars($jobsUrl, ENT_QUOTES),
            htmlspecialchars($e->getMessage(), ENT_QUOTES)
        );

        return new Response($html, 200);
    }
}
