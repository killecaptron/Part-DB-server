<?php
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

declare(strict_types=1);

namespace App\Services\Settings;

use App\Settings\SettingsApiExposed;
use Jbtronics\SettingsBundle\Manager\SettingsRegistryInterface;

/**
 * Knows which settings classes are available through the settings API.
 *
 * Settings are opt-in: only a class carrying the SettingsApiExposed attribute is reachable, so no setting is ever
 * served by accident (see the attribute for why that matters).
 *
 * @see \App\Tests\Services\Settings\ExposedSettingsRegistryTest
 */
final class ExposedSettingsRegistry
{
    /** @var array<string, SettingsApiExposed>|null The attribute of every exposed settings class, by settings name */
    private ?array $exposed = null;

    public function __construct(private readonly SettingsRegistryInterface $settingsRegistry)
    {
    }

    /**
     * Returns the names of all settings which are exposed through the API, sorted alphabetically.
     * @return string[]
     */
    public function getExposedNames(): array
    {
        $names = array_keys($this->getExposed());
        sort($names);

        return $names;
    }

    /**
     * Returns the settings class behind the given API name, or null if there is no exposed settings with that name.
     * @return class-string|null
     */
    public function getSettingsClass(string $name): ?string
    {
        if (!isset($this->getExposed()[$name])) {
            return null;
        }

        return $this->settingsRegistry->getSettingsClasses()[$name] ?? null;
    }

    /**
     * Returns whether the settings with the given name may also be written through the API.
     * Unknown or not exposed settings are never writable.
     */
    public function isWritable(string $name): bool
    {
        return $this->getExposed()[$name]->writable ?? false;
    }

    /**
     * Returns the classes of all settings which can be written through the API.
     * These are the settings a save of the settings form has to be serialized against (see SettingsController).
     * @return class-string[]
     */
    public function getWritableSettingsClasses(): array
    {
        $classes = [];

        foreach ($this->getExposedNames() as $name) {
            $class = $this->isWritable($name) ? $this->getSettingsClass($name) : null;

            if ($class !== null) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * @return array<string, SettingsApiExposed>
     */
    private function getExposed(): array
    {
        if ($this->exposed !== null) {
            return $this->exposed;
        }

        $exposed = [];

        foreach ($this->settingsRegistry->getSettingsClasses() as $name => $class) {
            $attributes = (new \ReflectionClass($class))->getAttributes(SettingsApiExposed::class);

            if ($attributes !== []) {
                $exposed[$name] = $attributes[0]->newInstance();
            }
        }

        return $this->exposed = $exposed;
    }
}
