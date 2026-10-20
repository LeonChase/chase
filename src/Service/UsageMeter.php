<?php
namespace App\Service;

use App\Entity\{User, UsageEvent, CreditLedger};

/** SQLite writes are serialized before reading credits; consumption and output commit together. */
final class UsageMeter
{
    public function __construct(private PortalStore $store) {}

    public function available(User $user): int
    {
        $balance = $user->budgetCredits;
        foreach ($this->store->em()->getRepository(CreditLedger::class)->findBy(['ownerId' => $user->id, 'tenantId' => $user->tenantId]) as $entry) {
            $balance += (int) ($entry->data['delta'] ?? 0);
        }
        return max(0, $balance);
    }

    public function consume(User $user, string $tool, callable $operation, int $units = 1): mixed
    {
        if ($units < 1 || $units > 100) { throw new \InvalidArgumentException('Ungültiger Demo-Creditverbrauch.'); }
        $em = $this->store->em();
        $connection = $em->getConnection();
        $connection->executeStatement('PRAGMA busy_timeout = 5000');
        $connection->beginTransaction();
        try {
            // First write acquires SQLite's exclusive writer reservation before balance is read.
            $event = new UsageEvent(['tool' => $tool, 'mode' => 'demo', 'creditUnits' => $units, 'costEur' => 0, 'status' => 'pending', 'isDemo' => true], $user->id, $user->tenantId);
            $event->title = DemoTextProvider::TOOLS[$tool] ?? $tool;
            $this->store->save($event);
            $em->refresh($user);
            if (!$user->active || $this->available($user) < $units) { throw new \DomainException('Ihr Demo-Guthaben reicht nicht aus oder das Konto ist deaktiviert. Bitte wenden Sie sich an den lokalen Demo-Administrator.'); }
            $ledger = new CreditLedger(['delta' => -$units, 'reason' => 'Demo-Text: '.$tool, 'usageId' => $event->id, 'costEur' => 0, 'isDemo' => true], $user->id, $user->tenantId);
            $ledger->title = 'Demo-Verbrauch';
            $this->store->save($ledger);
            $result = $operation();
            $event->data['status'] = 'completed';
            $this->store->save($event);
            $connection->commit();
            return $result;
        } catch (\Throwable $exception) {
            if ($connection->isTransactionActive()) { $connection->rollBack(); }
            $em->clear();
            throw $exception;
        }
    }
}
