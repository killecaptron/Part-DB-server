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

namespace App\ApiResource\Settings;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Put;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\State\Settings\SettingsCollectionProvider;
use App\State\Settings\SettingsProcessor;
use App\State\Settings\SettingsProvider;

/**
 * The system settings which are exposed through the API.
 *
 * Settings are opt-in: only a settings class carrying the SettingsApiExposed attribute shows up here (see
 * {@see \App\Settings\SettingsApiExposed} for why). GET /api/settings lists them, GET /api/settings/{name} returns
 * the parameters of one of them together with a strong ETag, and PUT replaces them, guarded by If-Match.
 *
 * The concurrency control is handled at the HTTP level in the state providers/processor (which return a raw
 * Response), not through the normal API Platform output.
 */
#[ApiResource(
    uriTemplate: '/settings/{name}',
    shortName: 'Settings',
    description: 'The system settings which are exposed through the API.',
    operations: [
        new GetCollection(
            uriTemplate: '/settings',
            openapi: new Operation(
                summary: 'List the settings which are available through the API',
                description: 'Returns the name of every exposed settings and whether it can also be written.',
            ),
            security: 'is_granted("IS_AUTHENTICATED_FULLY") and is_granted("@config.change_system_settings")',
            provider: SettingsCollectionProvider::class,
        ),
        new Get(
            openapi: new Operation(
                summary: 'Get the current values of one settings',
                description: 'Returns {"name": ..., "parameters": {...}}. The response carries a strong ETag header '
                    . 'that must be sent back as If-Match on the next PUT.',
            ),
            security: 'is_granted("IS_AUTHENTICATED_FULLY") and is_granted("@config.change_system_settings")',
            outputFormats: ['json' => ['application/json']],
            provider: SettingsProvider::class,
        ),
        new Put(
            openapi: new Operation(
                summary: 'Replace the values of one settings',
                description: 'Replaces all parameters of the settings with the given ones. The parameters have to be '
                    . 'complete: every parameter must be given, and no unknown one may be. Requires an If-Match '
                    . 'header with the ETag of the last GET.',
                parameters: [
                    new Parameter(
                        name: 'If-Match',
                        in: 'header',
                        required: true,
                        description: 'The ETag returned by the last GET of this settings.',
                        schema: ['type' => 'string'],
                    ),
                ],
            ),
            security: 'is_granted("IS_AUTHENTICATED_FULLY") and is_granted("@config.change_system_settings")',
            inputFormats: ['json' => ['application/json']],
            outputFormats: ['json' => ['application/json']],
            read: false,
            processor: SettingsProcessor::class,
        ),
    ],
)]
class SettingsResource
{
    /** @var string The name of the settings, as it is used in the URL (e.g. "tag_styles") */
    #[ApiProperty(identifier: true)]
    public string $name = '';

    /** @var bool Whether these settings can also be written through the API */
    public bool $writable = false;

    /** @var array<string, mixed> The values of the settings, by parameter name */
    public array $parameters = [];
}
