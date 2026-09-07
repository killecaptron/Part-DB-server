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
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Settings\SettingsResource;
use App\Services\Settings\ExposedSettingsRegistry;
use App\Services\Settings\SettingsETagHelper;
use App\Services\Settings\SettingsRepresentationBuilder;
use App\Services\Settings\SettingsWriteLockService;
use Jbtronics\SettingsBundle\Manager\SettingsManagerInterface;
use Jbtronics\SettingsBundle\Manager\SettingsValidatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionFailedHttpException;
use Symfony\Component\HttpKernel\Exception\PreconditionRequiredHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Handles PUT /api/settings/{name}: replaces all parameters of one exposed settings.
 *
 * The write is protected twice over: by If-Match/ETag against a client which did not see the current state, and by
 * a pessimistic database lock (see SettingsWriteLockService) against a concurrent write - another API call, or an
 * administrator saving the settings form - which would otherwise be silently lost.
 */
final readonly class SettingsProcessor implements ProcessorInterface
{
    public function __construct(
        private ExposedSettingsRegistry $exposedSettings,
        private SettingsManagerInterface $settingsManager,
        private SettingsValidatorInterface $settingsValidator,
        private SettingsRepresentationBuilder $representationBuilder,
        private SettingsWriteLockService $lockService,
        private RequestStack $requestStack,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Response
    {
        $name = (string) ($uriVariables['name'] ?? '');
        $class = $this->exposedSettings->getSettingsClass($name);

        if ($class === null) {
            throw new NotFoundHttpException(sprintf('There are no settings named "%s" available in the API.', $name));
        }

        if (!$this->exposedSettings->isWritable($name)) {
            throw new MethodNotAllowedHttpException(['GET'],
                sprintf('The settings "%s" can only be read through the API.', $name));
        }

        if (!$data instanceof SettingsResource) {
            throw new BadRequestHttpException('Invalid request body for a settings update.');
        }

        $request = $context['request'] ?? $this->requestStack->getCurrentRequest();
        $expected_etag = SettingsETagHelper::extractFromHeaderValue($request?->headers->get('If-Match'));
        if ($expected_etag === null) {
            throw new PreconditionRequiredHttpException(sprintf(
                'An If-Match header with the ETag of the last GET /api/settings/%s is required.', $name
            ));
        }

        return $this->lockService->withLock([$class], function () use ($class, $name, $data, $expected_etag): Response {
            //Everything from here on happens under the lock, so the state we compare the ETag against can not
            //change between the comparison and the save
            $settings = $this->settingsManager->reload($class);

            $current = [
                'name' => $name,
                'writable' => true,
                'parameters' => $this->representationBuilder->toRepresentation($settings),
            ];

            if (!hash_equals(SettingsETagHelper::compute($current), $expected_etag)) {
                throw new PreconditionFailedHttpException(sprintf(
                    'The settings "%s" have changed since your last GET. Re-fetch and retry.', $name
                ));
            }

            try {
                $this->representationBuilder->applyRepresentation($settings, $data->parameters);
            } catch (\InvalidArgumentException $e) {
                //The values did not fit the settings at all, so nothing was applied
                $this->settingsManager->reload($class);
                throw new UnprocessableEntityHttpException($e->getMessage(), $e);
            }

            $errors = $this->settingsValidator->validate($settings);
            if ($errors !== []) {
                //Drop the invalid values, so they can not leak into a later save of these settings
                $this->settingsManager->reload($class);
                throw new UnprocessableEntityHttpException($this->formatErrors($errors));
            }

            $this->settingsManager->save($settings, cascade: false);

            $body = [
                'name' => $name,
                'writable' => true,
                'parameters' => $this->representationBuilder->toRepresentation($settings),
            ];

            $response = new JsonResponse($body);
            $response->setEtag(SettingsETagHelper::compute($body));
            $response->headers->set('Cache-Control', 'no-store');

            return $response;
        });
    }

    /**
     * @param array<string, string[]> $errors The validation errors, by parameter name
     */
    private function formatErrors(array $errors): string
    {
        $messages = [];

        foreach ($errors as $parameter => $parameter_errors) {
            foreach ((array) $parameter_errors as $error) {
                $messages[] = sprintf('%s: %s', $parameter, $error);
            }
        }

        return implode(' ', $messages);
    }
}
