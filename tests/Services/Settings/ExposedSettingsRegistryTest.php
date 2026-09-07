<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Tests\Services\Settings;

use App\Services\Settings\ExposedSettingsRegistry;
use App\Settings\SettingsApiExposed;
use Jbtronics\SettingsBundle\Manager\SettingsRegistryInterface;
use PHPUnit\Framework\TestCase;

#[SettingsApiExposed]
class WritableFixtureSettings
{
}

#[SettingsApiExposed(writable: false)]
class ReadOnlyFixtureSettings
{
}

class NotExposedFixtureSettings
{
}

final class ExposedSettingsRegistryTest extends TestCase
{
    private function registry(): ExposedSettingsRegistry
    {
        $settingsRegistry = $this->createMock(SettingsRegistryInterface::class);
        $settingsRegistry->method('getSettingsClasses')->willReturn([
            'writable_fixture' => WritableFixtureSettings::class,
            'read_only_fixture' => ReadOnlyFixtureSettings::class,
            'not_exposed_fixture' => NotExposedFixtureSettings::class,
        ]);

        return new ExposedSettingsRegistry($settingsRegistry);
    }

    public function testOnlySettingsWithTheAttributeAreExposed(): void
    {
        //A settings class is never reachable through the API just because it exists
        self::assertSame(['read_only_fixture', 'writable_fixture'], $this->registry()->getExposedNames());
    }

    public function testExposedSettingsResolveToTheirClass(): void
    {
        self::assertSame(WritableFixtureSettings::class, $this->registry()->getSettingsClass('writable_fixture'));
    }

    public function testNotExposedSettingsDoNotResolve(): void
    {
        self::assertNull($this->registry()->getSettingsClass('not_exposed_fixture'));
        self::assertNull($this->registry()->getSettingsClass('does_not_exist'));
    }

    public function testWritabilityIsTakenFromTheAttribute(): void
    {
        $registry = $this->registry();

        self::assertTrue($registry->isWritable('writable_fixture'));
        self::assertFalse($registry->isWritable('read_only_fixture'));
    }

    public function testUnknownSettingsAreNeverWritable(): void
    {
        $registry = $this->registry();

        self::assertFalse($registry->isWritable('not_exposed_fixture'));
        self::assertFalse($registry->isWritable('does_not_exist'));
    }

    public function testOnlyWritableSettingsHaveToBeLocked(): void
    {
        self::assertSame([WritableFixtureSettings::class], $this->registry()->getWritableSettingsClasses());
    }
}
