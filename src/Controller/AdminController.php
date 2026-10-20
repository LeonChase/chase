<?php

namespace App\Controller;

use App\Entity\AcademyArticle;
use App\Entity\AdvertisingProduct;
use App\Entity\AuditEvent;
use App\Entity\Document;
use App\Entity\IntegrationConnection;
use App\Entity\NewsPost;
use App\Entity\Record;
use App\Entity\Region;
use App\Entity\TeamMembership;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/** Portal administration intentionally never grants blanket access to customer data. */
final class AdminController extends PortalController
{
    private const ROLES = ['ROLE_AGENT' => 'Makler:in', 'ROLE_REGION_MANAGER' => 'Regionalleitung', 'ROLE_ADMIN' => 'Administration', 'ROLE_SUPER_ADMIN' => 'Technische Administration'];
    private const NEWS_CATEGORIES = ['Unternehmen', 'Schulung', 'Marketing', 'System'];
    private const NEWS_STATES = ['Entwurf', 'Veröffentlicht', 'Archiviert'];

    #[Route('/admin', name: 'app_admin', methods: ['GET'])]
    public function index(): Response
    {
        $this->admin();
        $users = $this->users();
        return $this->page('admin/index.html.twig', [
            'users' => $users,
            'activeUsers' => count(array_filter($users, fn (User $u) => $u->active)),
            'newsCount' => count($this->records(NewsPost::class)),
            'regionCount' => count($this->records(Region::class)),
            'auditCount' => count($this->records(AuditEvent::class)),
            'budgetTotal' => array_sum(array_map(fn (User $u) => $u->budgetCredits, $users)),
        ]);
    }

    #[Route('/admin/users', name: 'app_admin_users', methods: ['GET', 'POST'])]
    public function userList(Request $request, UserPasswordHasherInterface $hasher): Response
    {
        $this->admin();
        $errors = [];
        $values = ['name' => '', 'email' => '', 'role' => 'ROLE_AGENT', 'region' => '', 'budgetCredits' => '100', 'active' => true];
        if ($request->isMethod('POST')) {
            $this->token($request);
            $values = $this->userValues($request);
            $errors = $this->userErrors($values);
            if (!$errors) {
                $account = new User();
                $account->tenantId = $this->user()->tenantId;
                $this->applyUser($account, $values);
                $password = 'Demo-' . bin2hex(random_bytes(9)) . '!';
                $account->setPassword($hasher->hashPassword($account, $password));
                $this->store->em()->persist($account);
                $this->store->em()->flush();
                $this->audit('admin.user.created', $account->id);
                $this->addFlash('success', 'Demo-Konto angelegt. Das lokale Startpasswort für ' . $account->email . ' wird nur jetzt angezeigt: ' . $password . ' — bitte sicher notieren. Es wird nicht per E-Mail versandt.');
                return $this->redirectToRoute('app_admin_users');
            }
        }
        return $this->page('admin/users.html.twig', ['accounts' => $this->users(), 'roles' => $this->assignableRoles(), 'regions' => $this->records(Region::class), 'errors' => $errors, 'values' => $values]);
    }

    #[Route('/admin/users/{id}', name: 'app_admin_user_edit', methods: ['GET', 'POST'])]
    public function userEdit(string $id, Request $request, UserPasswordHasherInterface $hasher): Response
    {
        $this->admin();
        $account = $this->account($id);
        $this->manageAccount($account);
        $role = $this->primaryRole($account);
        $values = ['name' => $account->name, 'email' => $account->email, 'role' => $role, 'region' => $account->region, 'budgetCredits' => (string) $account->budgetCredits, 'active' => $account->active];
        $errors = [];
        if ($request->isMethod('POST')) {
            $this->token($request);
            if ($request->request->get('action') === 'reset_password') {
                $password = 'Demo-' . bin2hex(random_bytes(9)) . '!';
                $account->setPassword($hasher->hashPassword($account, $password));
                $this->store->em()->flush();
                $this->audit('admin.user.password_reset', $account->id);
                $this->addFlash('success', 'Neues lokales Demo-Passwort für ' . $account->email . ': ' . $password . ' — nur einmal sichtbar, bitte sicher notieren.');
                return $this->redirectToRoute('app_admin_user_edit', ['id' => $account->id]);
            }
            $values = $this->userValues($request);
            if ($account->id === $this->user()->id) {
                if (!$values['active']) $errors[] = 'Das eigene Konto kann nicht deaktiviert werden.';
                if ($values['role'] !== $role) $errors[] = 'Die eigene Administrationsrolle kann hier nicht geändert werden.';
            }
            $errors = array_merge($errors, $this->userErrors($values, $account));
            if (!$errors) {
                $this->applyUser($account, $values);
                $this->store->em()->flush();
                $this->audit('admin.user.updated', $account->id);
                $this->addFlash('success', 'Benutzerkonto und Demo-Budget wurden gespeichert.');
                return $this->redirectToRoute('app_admin_users');
            }
        }
        $roles = $this->assignableRoles();
        if ($account->id === $this->user()->id) $roles[$role] = self::ROLES[$role] ?? $role;
        return $this->page('admin/user_edit.html.twig', ['account' => $account, 'roles' => $roles, 'regions' => $this->records(Region::class), 'values' => $values, 'errors' => $errors]);
    }

    #[Route('/admin/news', name: 'app_admin_news', methods: ['GET'])]
    public function news(): Response
    {
        $this->admin();
        return $this->page('admin/news.html.twig', ['posts' => $this->records(NewsPost::class)]);
    }

    #[Route('/admin/news/new', name: 'app_admin_news_new', methods: ['GET', 'POST'])]
    public function newsNew(Request $request): Response
    {
        $this->admin();
        return $this->newsForm(new NewsPost([], null, $this->user()->tenantId), $request, true);
    }

    #[Route('/admin/news/{id}/edit', name: 'app_admin_news_edit', methods: ['GET', 'POST'])]
    public function newsEdit(string $id, Request $request): Response
    {
        $this->admin();
        return $this->newsForm($this->record(NewsPost::class, $id), $request, false);
    }

    #[Route('/admin/news/{id}/status', name: 'app_admin_news_status', methods: ['POST'])]
    public function newsStatus(string $id, Request $request): Response
    {
        $this->admin();
        $this->token($request);
        $post = $this->record(NewsPost::class, $id);
        $status = (string) $request->request->get('status', '');
        if (!in_array($status, self::NEWS_STATES, true)) throw $this->createNotFoundException();
        $post->data['status'] = $status;
        if ($status === 'Veröffentlicht') $post->data['publishedAt'] = (new \DateTimeImmutable())->format(DATE_ATOM);
        $this->save($post);
        $this->audit('admin.news.status.' . $status, $post->id);
        $this->addFlash('success', 'Status der Demo-Nachricht: ' . $status . '.');
        return $this->redirectToRoute('app_admin_news');
    }

    #[Route('/admin/regions', name: 'app_admin_regions', methods: ['GET', 'POST'])]
    public function regions(Request $request): Response
    {
        $this->admin();
        $errors = [];
        if ($request->isMethod('POST')) {
            $this->token($request);
            $name = trim((string) $request->request->get('name', ''));
            if (mb_strlen($name) < 2 || mb_strlen($name) > 120) $errors[] = 'Bitte eine Regionsbezeichnung mit 2 bis 120 Zeichen eingeben.';
            foreach ($this->records(Region::class) as $region) {
                if (mb_strtolower($region->title) === mb_strtolower($name)) $errors[] = 'Diese Region ist bereits vorhanden.';
            }
            if (!$errors) {
                $region = new Region(['name' => $name, 'title' => $name, 'isDemo' => true], null, $this->user()->tenantId);
                $region->title = $name;
                $this->save($region);
                $this->audit('admin.region.created', $region->id);
                $this->addFlash('success', 'Demo-Region angelegt. Eine Region allein erteilt keine Datenzugriffsrechte.');
                return $this->redirectToRoute('app_admin_regions');
            }
        }
        return $this->page('admin/regions.html.twig', ['regions' => $this->records(Region::class), 'errors' => $errors]);
    }

    #[Route('/admin/access', name: 'app_admin_access', methods: ['GET', 'POST'])]
    public function access(Request $request): Response
    {
        $this->admin();
        $errors = [];
        if ($request->isMethod('POST')) {
            $this->token($request);
            if ($request->request->get('action') === 'revoke') {
                $grant = $this->record(TeamMembership::class, (string) $request->request->get('id', ''));
                $this->store->remove($grant);
                $this->audit('admin.team_access.revoked', $grant->id);
                $this->addFlash('success', 'Explizite Teamfreigabe widerrufen.');
                return $this->redirectToRoute('app_admin_access');
            }
            $manager = $this->account((string) $request->request->get('managerId', ''));
            $member = $this->account((string) $request->request->get('memberId', ''));
            if (!in_array('ROLE_REGION_MANAGER', $manager->getRoles(), true)) $errors[] = 'Teamfreigaben sind nur für eine ausdrücklich gewählte Regionalleitung vorgesehen.';
            if (!$manager->active || !$member->active) $errors[] = 'Beide Konten müssen aktiv sein.';
            if ($manager->id === $member->id) $errors[] = 'Für eigene Datensätze ist keine zusätzliche Teamfreigabe nötig.';
            foreach ($this->records(TeamMembership::class) as $existing) {
                if (($existing->data['managerId'] ?? '') === $manager->id && ($existing->data['memberId'] ?? '') === $member->id) $errors[] = 'Diese ausdrückliche Teamfreigabe besteht bereits.';
            }
            if (!$errors) {
                $grant = new TeamMembership(['title' => 'Explizite Demo-Teamfreigabe', 'managerId' => $manager->id, 'memberId' => $member->id, 'active' => true, 'scope' => 'read-write', 'isDemo' => true], $manager->id, $this->user()->tenantId);
                $this->save($grant);
                $this->audit('admin.team_access.granted', $grant->id);
                $this->addFlash('success', 'Die gewählte Regionalleitung darf jetzt die freigegebenen Teamdaten dieses Mitglieds bearbeiten.');
                return $this->redirectToRoute('app_admin_access');
            }
        }
        $accounts = $this->users();
        $names = [];
        foreach ($accounts as $account) $names[$account->id] = $account->name;
        return $this->page('admin/access.html.twig', ['grants' => $this->records(TeamMembership::class), 'accounts' => $accounts, 'managers' => array_filter($accounts, fn (User $u) => in_array('ROLE_REGION_MANAGER', $u->getRoles(), true)), 'names' => $names, 'errors' => $errors]);
    }

    #[Route('/admin/audit', name: 'app_admin_audit', methods: ['GET'])]
    public function auditLog(): Response
    {
        $this->admin();
        $events = $this->records(AuditEvent::class);
        usort($events, fn (Record $a, Record $b) => $b->createdAt <=> $a->createdAt);
        return $this->page('admin/audit.html.twig', ['events' => array_slice($events, 0, 200)]);
    }

    #[Route('/admin/integrations', name: 'app_admin_integrations', methods: ['GET'])]
    public function integrations(): Response
    {
        $this->admin();
        return $this->page('admin/integrations.html.twig', ['connections' => $this->records(IntegrationConnection::class)]);
    }

    #[Route('/admin/content/{type}', name: 'app_admin_content', methods: ['GET', 'POST'], defaults: ['type' => 'documents'])]
    public function content(string $type, Request $request): Response
    {
        $this->admin();
        $types = ['documents' => [Document::class, 'Dokumente'], 'academy' => [AcademyArticle::class, 'Academy'], 'advertising' => [AdvertisingProduct::class, 'Werbekatalog']];
        if (!isset($types[$type])) throw $this->createNotFoundException();
        [$class, $label] = $types[$type];
        $errors = [];
        $item = $request->query->get('edit') ? $this->record($class, (string) $request->query->get('edit')) : null;
        $values = $item?->data ?? [];
        if ($item) $values['title'] = $item->title;
        if ($request->isMethod('POST')) {
            $this->token($request);
            $id = (string) $request->request->get('id', '');
            $item = $id !== '' ? $this->record($class, $id) : new $class([], null, $this->user()->tenantId);
            $values = ['title' => trim((string) $request->request->get('title', '')), 'category' => trim((string) $request->request->get('category', '')), 'description' => trim((string) $request->request->get('description', '')), 'content' => trim((string) $request->request->get('content', '')), 'audience' => (string) $request->request->get('audience', 'Alle Makler'), 'unit' => trim((string) $request->request->get('unit', '')), 'status' => 'Demo – nicht bestellbar', 'priceLabel' => 'Preis auf Anfrage', 'isDemo' => true, 'lastUpdated' => date('Y-m-d')];
            if (mb_strlen($values['title']) < 3 || mb_strlen($values['title']) > 180) $errors[] = 'Der Titel muss 3 bis 180 Zeichen enthalten.';
            if ($values['category'] === '' || mb_strlen($values['category']) > 100) $errors[] = 'Bitte eine Kategorie mit höchstens 100 Zeichen angeben.';
            if (mb_strlen($values['description']) > 1000 || mb_strlen($values['content']) > 30000) $errors[] = 'Bitte Beschreibung (maximal 1.000) und Inhalt (maximal 30.000 Zeichen) kürzen.';
            if (mb_strlen($values['unit']) > 120) $errors[] = 'Die Einheit darf höchstens 120 Zeichen enthalten.';
            if (!in_array($values['audience'], ['Alle Makler', 'Regionalleitung, Admin', 'Admin'], true)) $errors[] = 'Unbekannte Zielgruppe.';
            if ($type !== 'advertising' && $values['content'] === '') $errors[] = 'Bitte einen lokalen Musterinhalt eingeben.';
            if (!$errors) {
                $item->title = $values['title'];
                if ($type === 'academy') {
                    $values['teaser'] = $values['description'];
                    $values['readingMinutes'] = max(1, (int) ceil(str_word_count($values['content']) / 180));
                    $values['content'] = [['Lerneinheit – Demo', $values['content']]];
                }
                if ($type === 'documents') $values['format'] = 'TXT-DEMO';
                $item->data = $values;
                $this->save($item);
                $this->audit('admin.content.' . $type . '.saved', $item->id);
                $this->addFlash('success', 'Lokaler Demo-Inhalt gespeichert. Es wurde nichts extern veröffentlicht oder bestellt.');
                return $this->redirectToRoute('app_admin_content', ['type' => $type]);
            }
        }
        if (is_array($values['content'] ?? null)) $values['content'] = implode("\n\n", array_map(fn ($section) => is_array($section) ? implode("\n", $section) : (string) $section, $values['content']));
        return $this->page('admin/content.html.twig', ['type' => $type, 'label' => $label, 'items' => $this->records($class), 'item' => $item, 'values' => $values, 'errors' => $errors]);
    }

    private function newsForm(NewsPost $post, Request $request, bool $new): Response
    {
        $values = array_merge(['title' => $post->title, 'category' => 'Unternehmen', 'summary' => '', 'body' => '', 'status' => 'Entwurf', 'pinned' => false], $post->data);
        $values['title'] = $post->title ?: ($values['title'] ?? '');
        $errors = [];
        if ($request->isMethod('POST')) {
            $this->token($request);
            $values = ['title' => trim((string) $request->request->get('title', '')), 'category' => (string) $request->request->get('category', ''), 'summary' => trim((string) $request->request->get('summary', '')), 'body' => trim((string) $request->request->get('body', '')), 'status' => (string) $request->request->get('status', ''), 'pinned' => $request->request->getBoolean('pinned'), 'isDemo' => true];
            if (mb_strlen($values['title']) < 3 || mb_strlen($values['title']) > 180) $errors[] = 'Bitte einen Titel mit 3 bis 180 Zeichen eingeben.';
            if (mb_strlen($values['summary']) < 3 || mb_strlen($values['summary']) > 500) $errors[] = 'Bitte eine Zusammenfassung mit 3 bis 500 Zeichen eingeben.';
            if (mb_strlen($values['body']) < 5 || mb_strlen($values['body']) > 30000) $errors[] = 'Der Nachrichtentext muss 5 bis 30.000 Zeichen enthalten.';
            if (!in_array($values['category'], self::NEWS_CATEGORIES, true)) $errors[] = 'Bitte eine gültige Kategorie wählen.';
            if (!in_array($values['status'], self::NEWS_STATES, true)) $errors[] = 'Bitte einen gültigen Status wählen.';
            if (!$errors) {
                $values['publishedAt'] = $values['status'] === 'Veröffentlicht' ? (new \DateTimeImmutable())->format(DATE_ATOM) : ($post->data['publishedAt'] ?? null);
                $post->title = $values['title'];
                $post->data = $values;
                $this->save($post);
                $this->audit($new ? 'admin.news.created' : 'admin.news.updated', $post->id);
                $this->addFlash('success', 'Interne Demo-Nachricht gespeichert. Status: ' . $values['status'] . '.');
                return $this->redirectToRoute('app_admin_news');
            }
        }
        return $this->page('admin/news_form.html.twig', ['post' => $post, 'new' => $new, 'values' => $values, 'errors' => $errors, 'categories' => self::NEWS_CATEGORIES, 'states' => self::NEWS_STATES]);
    }

    private function admin(): void
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_SUPER_ADMIN')) throw $this->createAccessDeniedException('Dieser Bereich ist für die Portaladministration bestimmt.');
    }

    private function records(string $class): array
    {
        return array_values(array_filter($this->store->all($class), fn ($record) => $record->tenantId === $this->user()->tenantId));
    }

    private function record(string $class, string $id): Record
    {
        foreach ($this->records($class) as $record) if ($record->id === $id) return $record;
        throw $this->createNotFoundException('Der Datensatz wurde nicht gefunden.');
    }

    private function users(): array
    {
        $accounts = array_values(array_filter($this->store->all(User::class), fn (User $account) => $account->tenantId === $this->user()->tenantId));
        usort($accounts, fn (User $a, User $b) => strcasecmp($a->name, $b->name));
        return $accounts;
    }

    private function account(string $id): User
    {
        foreach ($this->users() as $account) if ($account->id === $id) return $account;
        throw $this->createNotFoundException('Das Konto wurde nicht gefunden.');
    }

    private function manageAccount(User $account): void
    {
        if (!$this->isGranted('ROLE_SUPER_ADMIN') && $account->id !== $this->user()->id && (in_array('ROLE_ADMIN', $account->getRoles(), true) || in_array('ROLE_SUPER_ADMIN', $account->getRoles(), true))) {
            throw $this->createAccessDeniedException('Andere Administrationskonten können nur durch die technische Administration geändert werden.');
        }
    }

    private function primaryRole(User $account): string
    {
        foreach (['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_REGION_MANAGER', 'ROLE_AGENT'] as $role) if (in_array($role, $account->getRoles(), true)) return $role;
        return 'ROLE_AGENT';
    }

    private function assignableRoles(): array
    {
        return $this->isGranted('ROLE_SUPER_ADMIN') ? self::ROLES : array_intersect_key(self::ROLES, array_flip(['ROLE_AGENT', 'ROLE_REGION_MANAGER']));
    }

    private function userValues(Request $request): array
    {
        return ['name' => trim((string) $request->request->get('name', '')), 'email' => mb_strtolower(trim((string) $request->request->get('email', ''))), 'role' => (string) $request->request->get('role', ''), 'region' => trim((string) $request->request->get('region', '')), 'budgetCredits' => (string) $request->request->get('budgetCredits', ''), 'active' => $request->request->getBoolean('active')];
    }

    private function userErrors(array $values, ?User $editing = null): array
    {
        $errors = [];
        if (mb_strlen($values['name']) < 2 || mb_strlen($values['name']) > 100) $errors[] = 'Der Name muss 2 bis 100 Zeichen enthalten.';
        if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($values['email']) > 180) $errors[] = 'Bitte eine gültige E-Mail-Adresse eingeben.';
        foreach ($this->store->all(User::class) as $account) if (mb_strtolower($account->email) === $values['email'] && (!$editing || $account->id !== $editing->id)) $errors[] = 'Diese E-Mail-Adresse kann nicht verwendet werden.';
        $roles = $this->assignableRoles();
        if ($editing?->id === $this->user()->id) $roles[$this->primaryRole($editing)] = '';
        if (!isset($roles[$values['role']])) $errors[] = 'Diese Rolle darf mit dem aktuellen Konto nicht zugewiesen werden.';
        $regionNames = array_map(fn (Region $region) => $region->title, $this->records(Region::class));
        if ($editing && $editing->region) $regionNames[] = $editing->region;
        if (!in_array($values['region'], $regionNames, true)) $errors[] = 'Bitte eine vorhandene Region wählen.';
        if (!ctype_digit($values['budgetCredits']) || (int) $values['budgetCredits'] > 1000000) $errors[] = 'Das fiktive Budget muss eine ganze Zahl zwischen 0 und 1.000.000 sein.';
        return array_values(array_unique($errors));
    }

    private function applyUser(User $account, array $values): void
    {
        $account->name = $values['name'];
        $account->email = $values['email'];
        $account->region = $values['region'];
        $account->roles = [$values['role']];
        $account->active = $values['active'];
        $account->budgetCredits = (int) $values['budgetCredits'];
        if (property_exists($account, 'updatedAt')) $account->updatedAt = new \DateTimeImmutable();
    }
}
