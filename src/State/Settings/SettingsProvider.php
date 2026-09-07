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
use App\Services\Settings\ExposedSettingsRegistry;
use App\Services\Settings\SettingsETagHelper;
use App\Services\Settings\SettingsRepresentationBuilder;
use Jbtronics\SettingsBundle\Manager\SettingsManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Handles GET /api/settings/{name}: returns the current values of one exposed settings, together with the strong
 * ETag a following PUT has to send back as If-Match.
 */
final readonly class SettingsProvider implements ProviderInterface
{
    public function __construct(
        private ExposedSettingsRegistry $exposedSettings,
        private SettingsManagerInterface $settingsManager,
        private SettingsRepresentationBuilder $representationBuilder,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $name = (string) ($uriVariables['name'] ?? '');
        $class = $this->exposedSettings->getSettingsClass($name);

        //Settings which are not exposed must not be distinguishable from settings which do not exist at all
        if ($class === null) {
            throw new NotFoundHttpException(sprintf('There are no settings named "%s" available in the API.', $name));
        }

        $body = [
            'name' => $name,
            'writable' => $this->exposedSettings->isWritable($name),
            'parameters' => $this->representationBuilder->toRepresentation($this->settingsManager->get($class)),
        ];

        $response = new JsonResponse($body);
        $response->setEtag(SettingsETagHelper::compute($body));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
