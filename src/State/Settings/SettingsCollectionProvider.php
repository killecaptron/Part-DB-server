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

namespace App\State\Settings;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Settings\SettingsResource;
use App\Services\Settings\ExposedSettingsRegistry;

/**
 * Handles GET /api/settings: lists the settings which are available through the API at all, so a client can
 * discover them instead of having to know their names beforehand.
 *
 * The values themselves are not part of the listing - they are read one settings at a time, which is also where
 * the ETag needed for writing comes from.
 */
final readonly class SettingsCollectionProvider implements ProviderInterface
{
    public function __construct(private ExposedSettingsRegistry $exposedSettings)
    {
    }

    /**
     * @return SettingsResource[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $result = [];

        foreach ($this->exposedSettings->getExposedNames() as $name) {
            $resource = new SettingsResource();
            $resource->name = $name;
            $resource->writable = $this->exposedSettings->isWritable($name);

            $result[] = $resource;
        }

        return $result;
    }
}
