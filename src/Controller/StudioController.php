<?php
namespace App\Controller;

use App\Entity\{Property, ContentPost, UsageEvent, CreditLedger, Valuation};
use App\Service\{PortalStore, DemoTextProvider, UsageMeter};
use App\Integration\DemoValuationProvider;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

final class StudioController extends PortalController
{
    public function __construct(PortalStore $store, private DemoTextProvider $provider, private UsageMeter $meter)
    {
        parent::__construct($store);
    }

    #[Route('/studio', name: 'studio', methods: ['GET'])]
    public function studio(Request $request): Response { return $this->generatorPage($request, false); }

    #[Route('/marketing/new', name: 'marketing_new', methods: ['GET'])]
    public function marketingNew(Request $request): Response { return $this->generatorPage($request, true); }

    private function generatorPage(Request $request, bool $marketing): Response
    {
        $propertyId = $request->query->getString('property');
        $property = $propertyId ? $this->owned(Property::class, $propertyId) : null;
        $tool = $marketing ? 'social' : $request->query->getString('tool', 'expose');
        if (!isset(DemoTextProvider::TOOLS[$tool])) { $tool = 'expose'; }
        return $this->page('studio/index.html.twig', ['tools' => DemoTextProvider::TOOLS, 'tones' => DemoTextProvider::TONES, 'tool' => $tool, 'marketingMode' => $marketing, 'property' => $property, 'properties' => $this->store->visible(Property::class, $this->user()), 'balance' => $this->meter->available($this->user()), 'posts' => $this->store->visible(ContentPost::class, $this->user())]);
    }

    #[Route('/studio/generate', name: 'studio_generate', methods: ['POST'])]
    public function generate(Request $request): Response
    {
        $this->token($request);
        $tool = $request->request->getString('tool', 'expose');
        $tone = $request->request->getString('tone', 'sachlich');
        if (!isset(DemoTextProvider::TOOLS[$tool], DemoTextProvider::TONES[$tone])) { throw $this->createNotFoundException('Unbekanntes Werkzeug.'); }
        $propertyId = $request->request->getString('propertyId');
        $property = $propertyId ? $this->owned(Property::class, $propertyId) : null;
        $facts = $property ? array_merge($property->data, ['title' => $property->title]) : [];
        if (!$property) {
            foreach (['title', 'propertyType', 'city', 'district', 'features'] as $key) { $facts[$key] = $this->input($request, $key, $key === 'features' ? 2000 : 200); }
            foreach (['areaSqm', 'rooms', 'yearBuilt', 'priceEur'] as $key) {
                $value = str_replace(',', '.', trim($request->request->getString($key)));
                if ($value !== '' && (!is_numeric($value) || !is_finite((float) $value) || (float) $value <= 0 || (float) $value > 1000000000 || ($key === 'yearBuilt' && ((float) $value < 1000 || (float) $value > 2200 || floor((float)$value) != (float)$value)))) {
                    $this->addFlash('error', 'Bitte die Zahlenangaben prüfen. Es sind nur positive, plausible Werte erlaubt.');
                    return $this->redirectToRoute('studio', ['tool' => $tool]);
                }
                $facts[$key] = $value === '' ? null : (float) $value;
            }
        }
        $facts['verifiedLocation'] = $this->input($request, 'verifiedLocation', 4000);
        $options = ['cta' => $this->input($request, 'cta', 300), 'topic' => $this->input($request, 'topic', 200)];
        if ($tool === 'social' && !$property && !$facts['title'] && $options['topic']) { $facts['title'] = $options['topic']; }
        $channel = $request->request->getString('channel', 'Instagram');
        $format = $request->request->getString('format', 'Feed');
        if (!in_array($channel, ['Instagram', 'Facebook', 'LinkedIn'], true) || !in_array($format, ['Feed', 'Story', 'Reel', 'Textpost'], true)) { throw $this->createNotFoundException('Unbekanntes Format.'); }
        try {
            $post = $this->meter->consume($this->user(), $tool, function () use ($facts, $tool, $tone, $options, $property, $channel, $format): ContentPost {
                $result = $this->provider->generate($tool, $facts, $tone, $options);
                $now = (new \DateTimeImmutable())->format(DATE_ATOM);
                $post = new ContentPost(['kind' => $tool === 'social' ? 'marketing' : 'text', 'tool' => $tool, 'tone' => $tone, 'propertyId' => $property?->id, 'content' => $result['text'], 'caption' => $result['text'], 'channel' => $channel, 'format' => $format, 'status' => 'Entwurf', 'approved' => false, 'isPublished' => false, 'plannedAt' => null, 'source' => $property ? ['type' => 'property', 'id' => $property->id, 'title' => $property->title, 'facts' => $facts] : ['type' => 'manual', 'facts' => $facts], 'checklist' => $result['checklist'], 'mode' => $result['mode'], 'version' => 1, 'versions' => [['number' => 1, 'title' => $result['headline'], 'text' => $result['text'], 'time' => $now, 'approved' => false]], 'isDemo' => true], $this->user()->id, $this->user()->tenantId);
                $post->title = $result['headline'];
                $this->save($post);
                $this->audit('content.generated', $post->id);
                return $post;
            });
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());
            return $this->redirectToRoute('studio', ['tool' => $tool]);
        }
        $this->addFlash('success', 'Ihr Demo-Text wurde erstellt und als bearbeitbarer Entwurf gespeichert. Ein Demo-Credit wurde verwendet; tatsächliche Kosten: 0,00 €.');
        return $this->redirectToRoute($tool === 'social' ? 'marketing_edit' : 'studio_edit', ['id' => $post->id]);
    }

    #[Route('/studio/content/{id}', name: 'studio_edit', methods: ['GET', 'POST'])]
    #[Route('/marketing/{id}/edit', name: 'marketing_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $id): Response
    {
        $post = $this->owned(ContentPost::class, $id);
        $this->denyAccessUnlessGranted('EDIT', $post);
        $error = null;
        if ($request->isMethod('POST')) {
            $this->token($request);
            $title = $this->input($request, 'title', 200);
            $text = $this->input($request, 'content', 30000);
            $channel = $request->request->getString('channel', 'Instagram');
            $format = $request->request->getString('format', 'Feed');
            $status = $request->request->getString('status', 'Entwurf');
            $plannedInput = $request->request->getString('plannedAt');
            $plannedAt = $this->dateInput($plannedInput);
            if (!$title || !$text) { $error = 'Bitte Titel und Text ausfüllen.'; }
            elseif (!in_array($channel, ['Instagram', 'Facebook', 'LinkedIn'], true) || !in_array($format, ['Feed', 'Story', 'Reel', 'Textpost'], true) || !in_array($status, ['Entwurf', 'Freigegeben', 'Geplant intern'], true)) { $error = 'Bitte gültige Kanal-, Format- und Statuswerte wählen.'; }
            elseif ($plannedInput && !$plannedAt) { $error = 'Bitte einen gültigen Planungstermin angeben.'; }
            elseif ($status === 'Geplant intern' && !$plannedAt) { $error = 'Ein intern geplanter Beitrag benötigt einen Termin.'; }
            if (!$error) {
                $data = $post->data;
                $version = (int) ($data['version'] ?? 0) + 1;
                if (empty($data['versions'])) { $data['versions'] = [['number' => 1, 'title' => $post->title, 'text' => $data['content'] ?? $data['caption'] ?? '', 'time' => $post->createdAt->format(DATE_ATOM), 'approved' => (bool)($data['approved'] ?? false)]]; $version = 2; }
                $data['versions'][] = ['number' => $version, 'title' => $title, 'text' => $text, 'time' => (new \DateTimeImmutable())->format(DATE_ATOM), 'approved' => $status !== 'Entwurf'];
                $post->title = $title;
                $post->data = array_merge($data, ['content' => $text, 'caption' => $text, 'channel' => $channel, 'format' => $format, 'status' => $status, 'approved' => $status !== 'Entwurf', 'plannedAt' => $plannedAt?->format(DATE_ATOM), 'isPublished' => false, 'version' => $version]);
                $this->save($post);
                $this->audit('content.updated', $post->id);
                $this->addFlash('success', 'Version '.$version.' gespeichert. Eine Planung bleibt ausschließlich intern.');
                return $this->redirectToRoute($this->isMarketing($post) ? 'marketing_edit' : 'studio_edit', ['id' => $id]);
            }
        }
        $sourceProperty = null;
        if ($post->data['propertyId'] ?? null) {
            try { $sourceProperty = $this->store->findVisible(Property::class, $post->data['propertyId'], $this->user()); }
            catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException) { /* The saved text remains usable if its source is removed or no longer shared. */ }
        }
        return $this->page('studio/edit.html.twig', ['post' => $post, 'error' => $error, 'sourceProperty' => $sourceProperty, 'isMarketing' => $this->isMarketing($post)]);
    }

    #[Route('/studio/content/{id}/export', name: 'studio_export', methods: ['GET'])]
    public function export(string $id): Response
    {
        $post = $this->owned(ContentPost::class, $id);
        $content = '# '.$post->title."\n\n".($post->data['content'] ?? $post->data['caption'] ?? '')."\n\n---\nCHASE · Lokale Demo · Version ".($post->data['version'] ?? 1)."\nStatus: ".($post->data['status'] ?? 'Entwurf')."\nVor externer Verwendung fachlich prüfen. Keine automatische Veröffentlichung.\n";
        return new Response($content, 200, ['Content-Type' => 'text/markdown; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="CHASE-Entwurf-'.$post->id.'.md"', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    #[Route('/marketing', name: 'marketing', methods: ['GET'])]
    public function marketing(Request $request): Response
    {
        $zone = new \DateTimeZone('Europe/Berlin');
        $today = new \DateTimeImmutable('today', $zone);
        $month = $request->query->getString('month', $today->format('Y-m'));
        if (!preg_match('/^(20\d{2}|2100)-(0[1-9]|1[0-2])$/', $month)) { $month = $today->format('Y-m'); }
        $first = new \DateTimeImmutable($month.'-01', $zone);
        $view = $request->query->getString('view') === 'week' ? 'week' : 'month';
        $anchorInput = $request->query->getString('date', $today->format('Y-m-d'));
        $anchor = preg_match('/^20\d{2}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $anchorInput) ? new \DateTimeImmutable($anchorInput, $zone) : $today;
        $start = ($view === 'week' ? $anchor : $first)->modify('monday this week');
        $days = [];
        for ($i = 0; $i < ($view === 'week' ? 7 : 42); ++$i) { $date = $start->modify('+'.$i.' days'); $days[] = ['date' => $date, 'key' => $date->format('Y-m-d'), 'current' => $date->format('Y-m') === $month, 'today' => $date->format('Y-m-d') === $today->format('Y-m-d')]; }
        $posts = array_values(array_filter($this->store->visible(ContentPost::class, $this->user()), $this->isMarketing(...)));
        $scheduled = [];
        foreach ($posts as $post) { if ($post->data['plannedAt'] ?? null) { try { $key = (new \DateTimeImmutable($post->data['plannedAt']))->setTimezone($zone)->format('Y-m-d'); $scheduled[$key][] = $post; } catch (\Throwable) {} } }
        $campaignPath = dirname(__DIR__, 2).'/data/demo_campaigns.json';
        $campaigns = is_file($campaignPath) ? json_decode(file_get_contents($campaignPath), true) : [];
        return $this->page('marketing/index.html.twig', ['posts' => $posts, 'scheduled' => $scheduled, 'days' => $days, 'month' => $month, 'monthDate' => $first, 'view' => $view, 'anchor' => $anchor, 'previous' => ($view === 'week' ? $anchor->modify('-7 days') : $first->modify('-1 month')), 'next' => ($view === 'week' ? $anchor->modify('+7 days') : $first->modify('+1 month')), 'campaigns' => $campaigns ?? []]);
    }

    #[Route('/studio/visuals', name: 'studio_visuals', methods: ['GET'])]
    #[Route('/studio/image', name: 'studio_image', methods: ['GET'])]
    #[Route('/studio/video', name: 'studio_video', methods: ['GET'])]
    #[Route('/studio/floorplan', name: 'studio_floorplan', methods: ['GET'])]
    #[Route('/studio/doccheck', name: 'studio_doccheck', methods: ['GET'])]
    #[Route('/studio/redaction', name: 'studio_redaction', methods: ['GET'])]
    public function visuals(Request $request): Response
    {
        $route = $request->attributes->get('_route');
        $tool = match ($route) { 'studio_video' => 'video', 'studio_floorplan' => 'floorplan', 'studio_doccheck' => 'doccheck', 'studio_redaction' => 'redaction', default => $request->query->getString('tool', 'image') };
        if (!in_array($tool, ['image', 'staging', 'video', 'floorplan', 'doccheck', 'redaction'], true)) { $tool = 'image'; }
        $propertyId = $request->query->getString('property');
        $property = $propertyId ? $this->owned(Property::class, $propertyId) : null;
        $path = dirname(__DIR__, 2).'/data/demo_document_checks.json';
        $checks = is_file($path) ? json_decode(file_get_contents($path), true) : [];
        return $this->page('studio/visuals.html.twig', ['tool' => $tool, 'property' => $property, 'checks' => $checks]);
    }

    #[Route('/valuation', name: 'valuation', methods: ['GET', 'POST'])]
    public function valuation(Request $request, DemoValuationProvider $calculator): Response
    {
        $error = null; $result = null;
        if ($request->isMethod('POST')) {
            $this->token($request);
            $propertyId = $request->request->getString('propertyId');
            $property = $propertyId ? $this->owned(Property::class, $propertyId) : null;
            try {
                $result = $calculator->calculate(['monthlyRent' => str_replace(',', '.', $request->request->getString('monthlyRent')), 'factor' => str_replace(',', '.', $request->request->getString('factor'))]);
                $record = new Valuation($result + ['propertyId' => $property?->id, 'isDemo' => true], $this->user()->id, $this->user()->tenantId);
                $record->title = $property?->title ?? 'Demo-Rechenbeispiel';
                $this->save($record); $this->audit('valuation.demo_created', $record->id);
                $this->addFlash('success', 'Mathematisches Demo-Rechenbeispiel gespeichert.');
                return $this->redirectToRoute('valuation', ['result' => $record->id]);
            } catch (\InvalidArgumentException $exception) { $error = $exception->getMessage(); }
        }
        if ($request->query->getString('result')) { $result = $this->owned(Valuation::class, $request->query->getString('result'))->data; }
        return $this->page('valuation/index.html.twig', ['error' => $error, 'result' => $result, 'properties' => $this->store->visible(Property::class, $this->user()), 'valuations' => array_reverse($this->store->visible(Valuation::class, $this->user()))]);
    }

    #[Route('/usage', name: 'usage', methods: ['GET'])]
    public function usage(): Response
    {
        // Personal budget and ledger remain personal even for explicitly assigned team managers.
        $user = $this->user();
        $events = $this->store->em()->getRepository(UsageEvent::class)->findBy(['ownerId' => $user->id, 'tenantId' => $user->tenantId], ['createdAt' => 'DESC']);
        $ledger = $this->store->em()->getRepository(CreditLedger::class)->findBy(['ownerId' => $user->id, 'tenantId' => $user->tenantId], ['createdAt' => 'DESC']);
        return $this->page('usage/index.html.twig', ['balance' => $this->meter->available($user), 'budget' => $user->budgetCredits, 'events' => $events, 'ledger' => $ledger]);
    }

    private function input(Request $request, string $name, int $limit): string
    {
        return mb_substr(trim($request->request->getString($name)), 0, $limit);
    }

    private function dateInput(string $input): ?\DateTimeImmutable
    {
        if (!$input) { return null; }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $input, new \DateTimeZone('Europe/Berlin'));
        return $date && $date->format('Y-m-d\TH:i') === $input ? $date : null;
    }

    private function isMarketing(ContentPost $post): bool
    {
        return ($post->data['kind'] ?? null) === 'marketing' || ($post->data['tool'] ?? null) === 'social' || (isset($post->data['channel']) && !isset($post->data['tool']));
    }
}
