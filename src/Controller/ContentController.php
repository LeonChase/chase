<?php

namespace App\Controller;

use App\Entity\AcademyArticle;
use App\Entity\AdvertisingProduct;
use App\Entity\Document;
use App\Entity\NewsPost;
use App\Entity\PartnerProfile;
use App\Entity\Property;
use App\Entity\Record;
use App\Entity\UserPreference;
use App\Service\MaterialsImporter;
use App\Service\Lexicon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/** The local partner hub: every lookup uses the same server-side visibility policy. */
final class ContentController extends PortalController
{
    #[Route('/documents', name: 'app_documents', methods: ['GET'])]
    public function documents(Request $request): Response
    {
        $documents = $this->store->visible(Document::class, $this->user());
        $hasProvided = (bool) array_filter($documents, static fn (Record $d): bool => (bool) ($d->data['provided'] ?? false));
        $showDemo = $request->query->getBoolean('demo');
        if ($hasProvided && !$showDemo) $documents = array_values(array_filter($documents, static fn (Record $d): bool => (bool) ($d->data['provided'] ?? false)));
        $q = mb_substr(trim((string) $request->query->get('q', '')), 0, 200);
        $category = (string) $request->query->get('category', '');
        $subfolder = (string) $request->query->get('folder', '');
        $format = strtoupper((string) $request->query->get('format', ''));
        $sort = (string) $request->query->get('sort', 'uploaded_desc');
        $favoriteOnly = $request->query->getBoolean('favorites');
        $favorites = $this->preferenceTargets('favorite');
        $folders = []; $formats = []; $counts = array_fill_keys(MaterialsImporter::CATEGORIES, 0); $lastUpload = '';
        foreach ($documents as $document) {
            $cat = $document->data['category'] ?? 'Vorlagen'; $counts[$cat] = ($counts[$cat] ?? 0) + 1;
            $formats[] = (string) ($document->data['format'] ?? 'TXT-DEMO');
            if (($category === '' || $category === $cat) && !empty($document->data['subfolder'])) $folders[] = $document->data['subfolder'];
            $lastUpload = max($lastUpload, (string) ($document->data['uploadedAt'] ?? $document->createdAt->format(DATE_ATOM)));
        }
        $formats = array_values(array_unique($formats)); sort($formats); $folders = array_values(array_unique($folders)); sort($folders);
        $items = array_values(array_filter($documents, static function (Record $document) use ($q, $category, $subfolder, $format, $favoriteOnly, $favorites): bool {
            $search = $document->title.' '.($document->data['sourcePath'] ?? '').' '.($document->data['searchText'] ?? $document->data['description'] ?? '');
            return ($q === '' || str_contains(mb_strtolower($search), mb_strtolower($q)))
                && ($category === '' || ($document->data['category'] ?? '') === $category)
                && ($subfolder === '' || ($document->data['subfolder'] ?? '') === $subfolder)
                && ($format === '' || ($document->data['format'] ?? 'TXT-DEMO') === $format)
                && (!$favoriteOnly || in_array($document->id, $favorites, true));
        }));
        usort($items, static function (Record $a, Record $b) use ($sort): int {
            return match ($sort) {
                'title_asc' => strnatcasecmp($a->title, $b->title),
                'title_desc' => strnatcasecmp($b->title, $a->title),
                'type' => strcmp((string) ($a->data['format'] ?? ''), (string) ($b->data['format'] ?? '')) ?: strnatcasecmp($a->title, $b->title),
                'uploaded_asc' => strcmp((string) ($a->data['uploadedAt'] ?? $a->createdAt->format(DATE_ATOM)), (string) ($b->data['uploadedAt'] ?? $b->createdAt->format(DATE_ATOM))),
                default => strcmp((string) ($b->data['uploadedAt'] ?? $b->createdAt->format(DATE_ATOM)), (string) ($a->data['uploadedAt'] ?? $a->createdAt->format(DATE_ATOM))) ?: strnatcasecmp($a->title, $b->title),
            };
        });
        $total = count($items); $page = max(1, $request->query->getInt('page', 1)); $pages = max(1, (int) ceil($total / 24)); $page = min($page, $pages);
        $items = array_slice($items, ($page - 1) * 24, 24);
        return $this->page('content/documents.html.twig', compact('items', 'q', 'category', 'subfolder', 'format', 'sort', 'favoriteOnly', 'favorites', 'folders', 'formats', 'counts', 'lastUpload', 'total', 'page', 'pages', 'hasProvided', 'showDemo') + ['categories' => MaterialsImporter::CATEGORIES]);
    }

    #[Route('/documents/{id}', name: 'app_document', methods: ['GET'])]
    public function document(string $id): Response
    {
        $document = $this->visible(Document::class, $id);
        return $this->page('content/document.html.twig', [
            'document' => $document, 'favorites' => $this->preferenceTargets('favorite'),
        ]);
    }

    #[Route('/documents/{id}/download', name: 'app_document_download', methods: ['GET'])]
    public function download(string $id, MaterialsImporter $materials): Response
    {
        $document = $this->visible(Document::class, $id);
        if (!empty($document->data['filePath'])) {
            $path = $materials->filePath($document);
            if ($path === null) throw $this->createNotFoundException('Die Originaldatei ist nicht verfügbar oder ihre Integritätsprüfung ist fehlgeschlagen.');
            $name = (string) ($document->data['fileName'] ?? basename($path));
            $fallback = 'CHASE-Unterlage-'.$document->id.'.'.pathinfo($name, PATHINFO_EXTENSION);
            $response = new BinaryFileResponse($path);
            // Even uploaded HTML, SVG and EML are downloads, never same-origin executable previews.
            $response->setContentDisposition('attachment', $name, $fallback);
            $response->headers->set('Content-Type', 'application/octet-stream');
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('Content-Security-Policy', "default-src 'none'; sandbox");
            $response->headers->set('Cache-Control', 'private, no-store');
            $this->audit('material.download', $document->id);
            return $response;
        }
        // Generated text only: there is no path parameter or public file storage to bypass ACLs.
        $text = "UNVERBINDLICHES DEMO-MUSTER · NICHT RECHTSVERBINDLICH\n\n";
        $text .= $document->title."\n\n".(string) ($document->data['content'] ?? $document->data['description'] ?? 'Demo-Inhalt');
        $text .= "\n\nVor einer echten Verwendung sind eine CHASE-Freigabe und gegebenenfalls eine fachliche oder juristische Prüfung erforderlich.\n";
        $this->audit('document.download', $document->id);
        return new Response($text, Response::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', 'CHASE-Demo-Muster-'.$document->id.'.txt'),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    #[Route('/academy', name: 'app_academy', methods: ['GET'])]
    public function academy(Request $request, Lexicon $lexicon): Response
    {
        return $this->catalog($request, AcademyArticle::class, 'academy', ['lexiconCount' => count($lexicon->all())]);
    }

    #[Route('/academy/lexicon', name: 'app_lexicon', methods: ['GET'], priority: 20)]
    public function lexicon(Request $request, Lexicon $lexicon): Response
    {
        $q = mb_substr(trim((string) $request->query->get('q', '')), 0, 150);
        $letter = mb_strtoupper(mb_substr((string) $request->query->get('letter', ''), 0, 1));
        $all = $lexicon->all(); $entries = $q === '' ? $all : $lexicon->search($q);
        $letters = array_values(array_unique(array_map(static fn (array $entry): string => mb_strtoupper(mb_substr($entry['term'], 0, 1)), $all)));
        if ($letter !== '') $entries = array_values(array_filter($entries, static fn (array $entry): bool => mb_strtoupper(mb_substr($entry['term'], 0, 1)) === $letter));
        return $this->page('content/lexicon.html.twig', compact('q', 'letter', 'entries', 'letters') + ['source' => $lexicon->source(), 'sourceDocument' => $this->lexiconDocument(), 'lexiconCount' => count($all)]);
    }

    #[Route('/academy/lexicon/{id}', name: 'app_lexicon_term', methods: ['GET'], priority: 20)]
    public function lexiconTerm(string $id, Lexicon $lexicon): Response
    {
        $entry = $lexicon->find($id);
        if (!$entry) throw $this->createNotFoundException('Dieser Begriff ist nicht im bereitgestellten Lexikon enthalten.');
        return $this->page('content/lexicon_term.html.twig', ['entry' => $entry, 'source' => $lexicon->source(), 'sourceDocument' => $this->lexiconDocument()]);
    }

    #[Route('/academy/video-upload', name: 'app_academy_video_upload', methods: ['POST'], priority: 20)]
    public function academyVideoUpload(Request $request, MaterialsImporter $materials): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN'); $this->token($request);
        $file = $request->files->get('video');
        $title = trim((string) $request->request->get('title', ''));
        $mimeMap = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile || !$file->isValid() || $file->getSize() > 12582912 || !isset($mimeMap[$file->getMimeType()]) || mb_strlen($title) < 3 || mb_strlen($title) > 180) {
            $this->addFlash('error', 'Bitte laden Sie ein gültiges MP4-/WebM-Lernvideo bis 12 MiB mit einem Titel (3–180 Zeichen) hoch.');
            return $this->redirectToRoute('app_academy');
        }
        $storage = $materials->storeUploadedFile($file->getPathname(), $mimeMap[$file->getMimeType()], $this->user()->tenantId);
        $video = new AcademyArticle(array_merge($storage, [
            'title' => $title, 'category' => 'Lernvideos', 'kind' => 'video', 'provided' => true, 'isDemo' => false,
            'content' => [], 'teaser' => 'Intern bereitgestelltes Lernvideo.', 'mimeType' => $file->getMimeType(),
            'uploadedAt' => (new \DateTimeImmutable())->format(DATE_ATOM), 'fileName' => $file->getClientOriginalName(),
        ]), null, $this->user()->tenantId);
        $this->save($video); $this->audit('academy.video.uploaded', $video->id);
        $this->addFlash('success', 'Das Lernvideo wurde privat gespeichert und ist in der Academy abspielbar.');
        return $this->redirectToRoute('app_academy_article', ['id' => $video->id]);
    }

    #[Route('/academy/{id}/video', name: 'app_academy_video', methods: ['GET'])]
    public function academyVideo(string $id, MaterialsImporter $materials): Response
    {
        $video = $this->visible(AcademyArticle::class, $id);
        $mime = $video->data['mimeType'] ?? '';
        $path = $materials->filePath($video);
        if (($video->data['kind'] ?? '') !== 'video' || !in_array($mime, ['video/mp4', 'video/webm'], true) || !$path) throw $this->createNotFoundException();
        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $mime); $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }

    #[Route('/academy/{id}', name: 'app_academy_article', methods: ['GET'])]
    public function academyArticle(string $id): Response
    {
        return $this->page('content/academy_article.html.twig', [
            'article' => $this->visible(AcademyArticle::class, $id),
            'favorites' => $this->preferenceTargets('favorite'),
            'learned' => $this->preferenceTargets('progress'),
        ]);
    }

    #[Route('/news', name: 'app_news', methods: ['GET'])]
    public function news(Request $request): Response
    {
        return $this->catalog($request, NewsPost::class, 'news');
    }

    #[Route('/news/{id}', name: 'app_news_article', methods: ['GET'])]
    public function newsArticle(string $id): Response
    {
        return $this->page('content/news_article.html.twig', [
            'article' => $this->visible(NewsPost::class, $id),
            'read' => $this->preferenceTargets('read'),
        ]);
    }

    #[Route('/network', name: 'app_network', methods: ['GET'])]
    public function network(Request $request): Response
    {
        $query = mb_strtolower(trim((string) $request->query->get('q', '')));
        $partners = array_values(array_filter(
            $this->store->visible(PartnerProfile::class, $this->user()),
            static fn (Record $p): bool => !empty($p->data['visibleToPartners']) && ($query === '' || str_contains(mb_strtolower($p->title.' '.($p->data['region'] ?? '')), $query))
        ));
        return $this->page('content/network.html.twig', ['partners' => $partners, 'q' => $query]);
    }

    #[Route('/preferences/{kind}/{type}/{id}', name: 'app_content_preference', methods: ['POST'])]
    public function preference(Request $request, string $kind, string $type, string $id): Response
    {
        $this->token($request);
        $types = [
            'document' => [Document::class, 'app_document'],
            'academy' => [AcademyArticle::class, 'app_academy_article'],
            'advertising' => [AdvertisingProduct::class, 'app_advertising'],
            'news' => [NewsPost::class, 'app_news_article'],
        ];
        $allowed = ['document' => ['favorite'], 'academy' => ['favorite', 'progress'], 'advertising' => ['favorite'], 'news' => ['read']];
        if (!isset($types[$type]) || !in_array($kind, $allowed[$type], true)) {
            throw $this->createNotFoundException();
        }
        $this->visible($types[$type][0], $id);
        $existing = $this->findPreference($kind, $id);
        if ($existing) {
            $this->store->remove($existing);
            $this->addFlash('success', match ($kind) { 'progress' => 'Lernstatus zurückgesetzt.', 'read' => 'Als ungelesen markiert.', default => 'Aus der Merkliste entfernt.' });
        } else {
            $this->save(new UserPreference([
                'kind' => $kind, 'targetId' => $id, 'type' => $type,
                'title' => $kind.' '.$id, 'completed' => $kind === 'progress',
            ], $this->user()->id, $this->user()->tenantId));
            $this->addFlash('success', match ($kind) { 'progress' => 'Lerneinheit als abgeschlossen gespeichert.', 'read' => 'Als gelesen markiert.', default => 'In Ihrer Merkliste gespeichert.' });
        }
        $back = (string) $request->request->get('back', '');
        // Only fixed local destinations; never follow a submitted URL.
        if ($back === 'list') {
            return $this->redirectToRoute(match ($type) { 'document' => 'app_documents', 'academy' => 'app_academy', 'news' => 'app_news', default => 'app_advertising' });
        }
        return $this->redirectToRoute($types[$type][1], $type === 'advertising' ? [] : ['id' => $id]);
    }

    #[Route('/profile', name: 'app_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, UserPasswordHasherInterface $hasher): Response
    {
        $user = $this->user();
        $errors = [];
        if ($request->isMethod('POST')) {
            $this->token($request);
            $name = trim((string) $request->request->get('name', ''));
            $password = (string) $request->request->get('new_password', '');
            $confirmation = (string) $request->request->get('password_confirmation', '');
            if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
                $errors[] = 'Ihr Name muss zwischen 2 und 100 Zeichen lang sein.';
            }
            if ($password !== '') {
                if (!$hasher->isPasswordValid($user, (string) $request->request->get('current_password', ''))) {
                    $errors[] = 'Das bisherige Passwort ist nicht korrekt.';
                }
                if (mb_strlen($password) < 12 || mb_strlen($password) > 200) {
                    $errors[] = 'Das neue Passwort muss zwischen 12 und 200 Zeichen lang sein.';
                }
                if ($password !== $confirmation) {
                    $errors[] = 'Die neuen Passwörter stimmen nicht überein.';
                }
            }
            if (!$errors) {
                $user->name = $name;
                if ($password !== '') {
                    $user->setPassword($hasher->hashPassword($user, $password));
                }
                $user->preferences = array_merge($user->preferences, ['directoryVisible' => $request->request->getBoolean('directory_visible')]);
                $this->save($user);
                // Visibility is opt-in per user, never inferred from their region.
                $foundProfile = false;
                foreach ($this->store->all(PartnerProfile::class) as $profile) {
                    if ($profile->tenantId === $user->tenantId && ($profile->data['userId'] ?? '') === $user->id) {
                        $foundProfile = true;
                        $profile->title = $name;
                        $profile->data = array_merge($profile->data, ['displayName' => $name, 'visibleToPartners' => $user->preferences['directoryVisible'], 'region' => $user->region]);
                        $this->save($profile);
                    }
                }
                if (!$foundProfile && $user->preferences['directoryVisible']) {
                    $this->save(new PartnerProfile([
                        'title' => $name, 'displayName' => $name, 'userId' => $user->id,
                        'region' => $user->region, 'visibleToPartners' => true,
                        'position' => 'CHASE Partner (Demo)', 'isDemo' => true,
                        'profileDescription' => 'Freigegebenes Profil der lokalen Demo-Umgebung.',
                    ], $user->id, $user->tenantId));
                }
                $this->audit($password === '' ? 'profile.updated' : 'profile.password.changed', $user->id);
                $this->addFlash('success', 'Ihr Profil wurde gespeichert.');
                return $this->redirectToRoute('app_profile');
            }
        }
        $directoryVisible = $user->preferences['directoryVisible'] ?? null;
        if ($directoryVisible === null) {
            foreach ($this->store->all(PartnerProfile::class) as $profile) {
                if ($profile->tenantId === $user->tenantId && ($profile->data['userId'] ?? '') === $user->id) {
                    $directoryVisible = (bool) ($profile->data['visibleToPartners'] ?? false);
                }
            }
        }
        return $this->page('profile/index.html.twig', ['errors' => $errors, 'directoryVisible' => (bool) $directoryVisible]);
    }

    #[Route('/profile/theme', name: 'app_theme', methods: ['POST'])]
    public function theme(Request $request): Response
    {
        $this->token($request);
        $theme = (string) $request->request->get('theme', '');
        if (!in_array($theme, ['light', 'dark'], true)) {
            return new JsonResponse(['error' => 'Bitte wählen Sie Hell oder Dunkel.'], 422);
        }
        $user = $this->user();
        $user->preferences = array_merge($user->preferences, ['theme' => $theme]);
        $this->save($user);
        if ($request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json')) {
            return new JsonResponse(['theme' => $theme, 'saved' => true]);
        }
        $this->addFlash('success', 'Die Darstellung wurde gespeichert.');
        return $this->redirectToRoute('app_profile');
    }

    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function search(Request $request, Lexicon $lexicon): Response
    {
        $query = mb_substr(trim((string) $request->query->get('q', '')), 0, 200);
        $needle = mb_strtolower($query);
        $groups = [];
        if ($needle !== '') {
            $definitions = [
                'Meine Immobilien' => [Property::class, 'property', ['city', 'location', 'region', 'internalId', 'externalId', 'type']],
                'Dokumente' => [Document::class, 'document', ['description', 'category', 'content', 'sourcePath', 'searchText']],
                'Wissen / Academy' => [AcademyArticle::class, 'academy', ['teaser', 'category', 'content']],
                'News' => [NewsPost::class, 'news', ['summary', 'body', 'category']],
            ];
            foreach ($definitions as $label => [$class, $type, $fields]) {
                $matches = [];
                foreach ($this->store->visible($class, $this->user()) as $record) {
                    $haystack = $record->title;
                    foreach ($fields as $field) {
                        $value = $record->data[$field] ?? '';
                        $haystack .= ' '.(is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value);
                    }
                    if (str_contains(mb_strtolower($haystack), $needle)) {
                        $matches[] = ['record' => $record, 'type' => $type];
                    }
                }
                $groups[$label] = $matches;
            }
        }
        $lexiconResults = $needle !== '' ? $lexicon->search($query) : [];
        return $this->page('search/index.html.twig', ['q' => $query, 'groups' => $groups, 'lexiconResults' => $lexiconResults, 'total' => array_sum(array_map('count', $groups)) + count($lexiconResults)]);
    }

    #[Route('/help', name: 'app_help', methods: ['GET'])]
    public function help(): Response
    {
        return $this->page('help/index.html.twig');
    }

    private function catalog(Request $request, string $class, string $section, array $extra = []): Response
    {
        $all = $this->store->visible($class, $this->user());
        $q = mb_substr(trim((string) $request->query->get('q', '')), 0, 200);
        $category = (string) $request->query->get('category', '');
        $favorites = $this->preferenceTargets('favorite');
        $read = $this->preferenceTargets('read');
        $learned = $this->preferenceTargets('progress');
        $favoriteOnly = $request->query->getBoolean('favorites');
        $unreadOnly = $request->query->getBoolean('unread');
        $categories = array_values(array_unique(array_filter(array_map(static fn (Record $item): string => (string) ($item->data['category'] ?? ''), $all))));
        sort($categories);
        $items = array_values(array_filter($all, static function (Record $item) use ($q, $category, $favorites, $read, $favoriteOnly, $unreadOnly): bool {
            $description = $item->data['description'] ?? $item->data['teaser'] ?? $item->data['summary'] ?? '';
            return ($q === '' || str_contains(mb_strtolower($item->title.' '.$description.' '.($item->data['category'] ?? '')), mb_strtolower($q)))
                && ($category === '' || ($item->data['category'] ?? '') === $category)
                && (!$favoriteOnly || in_array($item->id, $favorites, true))
                && (!$unreadOnly || !in_array($item->id, $read, true));
        }));
        if ($section === 'news') {
            usort($items, static function (Record $a, Record $b): int {
                $pinned = (int) ($b->data['pinned'] ?? false) <=> (int) ($a->data['pinned'] ?? false);
                return $pinned ?: strcmp((string) ($b->data['publishedAt'] ?? ''), (string) ($a->data['publishedAt'] ?? ''));
            });
        }
        $currentUserId = $this->user()->id;
        $requests = array_values(array_filter($this->store->visible(UserPreference::class, $this->user()), static fn (Record $pref): bool => $pref->ownerId === $currentUserId && ($pref->data['kind'] ?? '') === 'advertising_request'));
        return $this->page('content/'.$section.'.html.twig', compact('items', 'all', 'q', 'category', 'categories', 'favorites', 'read', 'learned', 'favoriteOnly', 'unreadOnly', 'requests') + $extra);
    }

    private function lexiconDocument(): ?Record
    {
        foreach ($this->store->visible(Document::class, $this->user()) as $document) if (!empty($document->data['lexiconOriginal'])) return $document;
        return null;
    }

    private function visible(string $class, string $id): Record
    {
        return $this->store->findVisible($class, $id, $this->user());
    }

    /** @return list<string> */
    private function preferenceTargets(string $kind): array
    {
        $targets = [];
        foreach ($this->store->visible(UserPreference::class, $this->user()) as $preference) {
            if ($preference->ownerId === $this->user()->id && ($preference->data['kind'] ?? '') === $kind && isset($preference->data['targetId'])) {
                $targets[] = (string) $preference->data['targetId'];
            }
        }
        return array_values(array_unique($targets));
    }

    private function findPreference(string $kind, string $targetId): ?UserPreference
    {
        foreach ($this->store->visible(UserPreference::class, $this->user()) as $preference) {
            if ($preference->ownerId === $this->user()->id && ($preference->data['kind'] ?? '') === $kind && ($preference->data['targetId'] ?? '') === $targetId) {
                return $preference;
            }
        }
        return null;
    }
}
