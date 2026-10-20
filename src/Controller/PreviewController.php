<?php
namespace App\Controller;

use App\Entity\{ContentPost, UserPreference};
use App\Service\PdfExporter;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class PreviewController extends PortalController
{
    private const TYPES = [
        'valuation' => ['label' => 'Bewertungsanfrage', 'title' => 'Ihre Immobilie. Ihr nächster Schritt.', 'cta' => 'Bewertung anfragen', 'intro' => 'Eine unverbindliche Anfrage zur Vorbereitung einer persönlichen Beratung.'],
        'callback' => ['label' => 'Rückrufwunsch', 'title' => 'Wir nehmen uns Zeit für Ihre Fragen.', 'cta' => 'Rückruf wünschen', 'intro' => 'Ein persönliches Gespräch zu Ihrem Immobilienvorhaben vorbereiten.'],
        'guide' => ['label' => 'Ratgeberdownload', 'title' => 'Gut vorbereitet in den Immobilienverkauf.', 'cta' => 'Ratgeber anfragen', 'intro' => 'Unterlagen, wichtige Fragen und nächste Schritte im Überblick.'],
        'buyer' => ['label' => 'Käufer-Suchprofil', 'title' => 'Was macht Ihr neues Zuhause aus?', 'cta' => 'Suchprofil vormerken', 'intro' => 'Wünsche an Ort, Größe und Budget strukturiert erfassen.'],
    ];

    #[Route('/studio/content/{id}/pdf', name: 'studio_pdf', methods: ['GET'])]
    public function pdf(string $id, PdfExporter $exporter): Response
    {
        $post = $this->owned(ContentPost::class, $id);
        $this->denyAccessUnlessGranted('DOWNLOAD', $post);
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $post->id);
        return new Response($exporter->export($post), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="CHASE-DEMO-MUSTER-'.$filename.'.pdf"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    #[Route('/marketing/landing-preview', name: 'marketing_landing_preview', methods: ['GET', 'POST'])]
    public function landing(Request $request): Response
    {
        $draftId = $request->isMethod('POST') ? $request->request->getString('draft') : $request->query->getString('draft');
        $draft = $draftId ? $this->owned(UserPreference::class, $draftId) : null;
        if ($draft && ($draft->data['kind'] ?? '') !== 'landing_preview') { throw $this->createNotFoundException(); }
        $type = $request->isMethod('POST') ? $request->request->getString('type', 'valuation') : $request->query->getString('type', $draft?->data['type'] ?? 'valuation');
        $error = null;
        if (!isset(self::TYPES[$type])) { $type = 'valuation'; $error = 'Bitte einen gültigen Vorschautyp auswählen.'; }
        if (!$draft && !$request->isMethod('POST')) {
            foreach ($this->store->visible(UserPreference::class, $this->user()) as $saved) {
                if (($saved->data['kind'] ?? '') === 'landing_preview' && ($saved->data['type'] ?? '') === $type) { $draft = $saved; break; }
            }
        }
        $values = ['title' => $draft?->title ?: self::TYPES[$type]['title'], 'cta' => $draft?->data['cta'] ?? self::TYPES[$type]['cta'], 'intro' => $draft?->data['intro'] ?? self::TYPES[$type]['intro']];
        if ($request->isMethod('POST')) {
            $this->token($request);
            foreach (['title', 'cta', 'intro'] as $key) { $values[$key] = trim($request->request->getString($key)); }
            if (!$values['title'] || mb_strlen($values['title']) > 180 || !$values['cta'] || mb_strlen($values['cta']) > 80 || mb_strlen($values['intro']) > 2000) { $error = 'Bitte einen Titel mit maximal 180 Zeichen und einen Buttontext mit maximal 80 Zeichen eingeben. Der Beschreibungstext darf bis zu 2.000 Zeichen enthalten.'; }
            if (!$error) {
                if (!$draft) {
                    foreach ($this->store->visible(UserPreference::class, $this->user()) as $saved) {
                        if (($saved->data['kind'] ?? '') === 'landing_preview' && ($saved->data['type'] ?? '') === $type) { $draft = $saved; break; }
                    }
                }
                $draft ??= new UserPreference([], $this->user()->id, $this->user()->tenantId);
                $this->denyAccessUnlessGranted('EDIT', $draft);
                $draft->title = $values['title'];
                $draft->data = ['kind' => 'landing_preview', 'type' => $type, 'title' => $values['title'], 'cta' => $values['cta'], 'intro' => $values['intro'], 'isLive' => false, 'acceptsLeads' => false, 'isDemo' => true];
                $this->save($draft);
                $this->audit('marketing.preview_saved', $draft->id);
                $this->addFlash('success', 'Ihre private Vorschau wurde lokal gespeichert. Es wurde keine Website veröffentlicht und kein Kontaktformular aktiviert.');
                return $this->redirectToRoute('marketing_landing_preview', ['draft' => $draft->id]);
            }
        }
        $response = $this->page('marketing/landing_preview.html.twig', ['types' => self::TYPES, 'type' => $type, 'values' => $values, 'draft' => $draft, 'error' => $error]);
        if ($error && $request->isMethod('POST')) { $response->setStatusCode(422); }
        return $response;
    }
}
