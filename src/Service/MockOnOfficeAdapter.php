<?php
namespace App\Service;

use App\Entity\Property;
use App\Entity\User;
use App\Integration\CrmProvider;

/** Local fixture adapter. It never contacts onOffice or any other network service. */
final class MockOnOfficeAdapter implements CrmProvider
{
    public function __construct(private PortalStore $store, private string $projectDir) {}

    public function listProperties(User $user): array
    {
        $file = $this->projectDir.'/data/demo_onoffice_objects.json';
        $rows = is_file($file) ? json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR) : [];
        // Fixture owner mappings are explicit. An administrative role grants no customer-data access.
        $owners = $this->store->visibleOwnerIds($user);
        return array_values(array_filter($rows, function (array $row) use ($user, $owners): bool {
            $id = $row['ownerUserId'] ?? null;
            if (!in_array($id, $owners, true)) return false;
            $owner = $this->store->em()->find(User::class, $id);
            return $owner instanceof User && $owner->active && $owner->tenantId === $user->tenantId;
        }));
    }

    public function getProperty(string $id, User $user): ?array
    {
        foreach ($this->listProperties($user) as $row) {
            if (($row['externalId'] ?? '') === $id) return $row;
        }
        return null;
    }

    public function syncProperties(User $user): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $em = $this->store->em();
        $connection = $em->getConnection();
        // SQLite serializes writes. Holding this lock through lookup + insert prevents duplicate imports.
        $connection->beginTransaction();
        try {
            $table = $connection->quoteIdentifier($em->getClassMetadata(Property::class)->getTableName());
            $connection->executeStatement('UPDATE '.$table.' SET id = id WHERE 0 = 1');
            foreach ($this->listProperties($user) as $row) {
                $match = $em->getRepository(Property::class)->findOneBy(['tenantId'=>$user->tenantId, 'externalId'=>$row['externalId']]);
                // Never silently move existing local enrichments to another owner.
                if ($match && ($match->ownerId !== $row['ownerUserId'] || !$this->store->canAccess($match, $user))) {
                    ++$result['skipped'];
                    continue;
                }
                $created = !$match;
                $property = $match ?? new Property([], $row['ownerUserId'], $user->tenantId);
                // CRM owns only these factual fields; portal notes, checklists and generated content survive.
                foreach (['externalSystem','externalId','title','propertyType','city','region','areaSqm','rooms','plotSqm','yearBuilt','status','priceEur','source','syncVersion','lastModifiedExternal','isDemo'] as $key) {
                    if (array_key_exists($key, $row)) $property->data[$key] = $row[$key];
                }
                $property->data['lastSync'] = (new \DateTimeImmutable())->format(DATE_ATOM);
                $property->data['internalId'] ??= $row['externalId'];
                $property->data['marketingType'] ??= 'Kauf';
                $property->externalId = $row['externalId'];
                $property->title = (string) $row['title'];
                $property->updatedAt = new \DateTimeImmutable();
                $em->persist($property);
                ++$result[$created ? 'created' : 'updated'];
            }
            $em->flush();
            $connection->commit();
        } catch (\Throwable $e) {
            if ($connection->isTransactionActive()) $connection->rollBack();
            throw $e;
        }
        return $result;
    }
}
