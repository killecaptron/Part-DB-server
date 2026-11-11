<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Services\Settings;

use App\Entity\SettingsEntry;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Jbtronics\SettingsBundle\Metadata\MetadataManagerInterface;

/**
 * Serializes concurrent writers of one or more settings classes against each other, using a
 * pessimistic database row lock on the settings storage row.
 *
 * This protects against lost updates when two writers race: two concurrent API PUTs to the same
 * settings, or an API PUT racing an administrator saving the whole settings form (which
 * rewrites every embedded settings object, including ones the admin didn't actually touch on
 * that page load). Without this, "reload settings, compare ETag, save" still has a race window
 * between the compare and the save where another writer's change could be silently overwritten.
 *
 * Limitation: a settings class that has never been saved before has no storage row yet, so its
 * very first save is not lock-protected (there is nothing to lock). This is an accepted, narrow
 * edge case - once any save has happened, all subsequent writes are protected.
 */
final class SettingsWriteLockService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MetadataManagerInterface $metadataManager,
    ) {
    }

    /**
     * Runs $callback inside a database transaction, having first taken a pessimistic write lock
     * on the storage row of each of $settingsClasses (if it already exists).
     *
     * @template T
     * @param class-string[] $settingsClasses
     * @param callable(): T $callback
     * @return T
     */
    public function withLock(array $settingsClasses, callable $callback): mixed
    {
        return $this->entityManager->wrapInTransaction(function () use ($settingsClasses, $callback) {
            foreach ($settingsClasses as $settingsClass) {
                $this->lockStorageRow($settingsClass);
            }

            return $callback();
        });
    }

    private function lockStorageRow(string $settingsClass): void
    {
        $key = $this->metadataManager->getSettingsMetadata($settingsClass)->getStorageKey();

        $this->entityManager->createQueryBuilder()
            ->select('e.id')
            ->from(SettingsEntry::class, 'e')
            ->where('e.key = :key')
            ->setParameter('key', $key)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getOneOrNullResult();
    }
}
