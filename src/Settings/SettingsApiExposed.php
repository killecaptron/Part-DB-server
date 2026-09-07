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


namespace App\Settings;

/**
 * Marks a settings class as available through the settings API (/api/settings).
 *
 * Settings are not exposed by default: a settings class is only reachable through the API if it carries this
 * attribute, so an installation never starts serving a newly added setting (which might well hold a password or
 * an API key) just because it was added somewhere.
 *
 * Only add it to settings whose values are plain data (scalars, arrays of them, or backed enums) and which do not
 * contain any secret - everything the API returns is visible to every user allowed to change the system settings.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class SettingsApiExposed
{
    public function __construct(
        /** @var bool Whether the settings can also be written through the API, or only read */
        public bool $writable = true,
    ) {
    }
}
