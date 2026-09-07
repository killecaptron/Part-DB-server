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

use Jbtronics\SettingsBundle\Metadata\MetadataManagerInterface;

/**
 * Translates a settings object into the plain array the settings API returns, and back.
 *
 * Only the parameters the settings bundle actually knows about are part of the representation, so a settings class
 * can keep internal state without it leaking into the API.
 *
 * Values are limited to what has an unambiguous JSON representation: scalars, null, (nested) arrays of them and
 * backed enums. A parameter of any other type makes the settings unusable through the API - which is caught here
 * with a clear error instead of silently returning or storing something wrong.
 *
 * @see \App\Tests\Services\Settings\SettingsRepresentationBuilderTest
 */
final class SettingsRepresentationBuilder
{
    public function __construct(private readonly MetadataManagerInterface $metadataManager)
    {
    }

    /**
     * Returns the parameters of the given settings object, in the exact structure the API returns them in.
     * @return array<string, mixed>
     */
    public function toRepresentation(object $settings): array
    {
        $metadata = $this->metadataManager->getSettingsMetadata($settings);
        $representation = [];

        foreach ($metadata->getParameters() as $parameter) {
            $property = $parameter->getPropertyName();
            $representation[$parameter->getName()] = $this->valueToRepresentation(
                $settings->$property,
                $metadata->getClassName() . '::$' . $property
            );
        }

        return $representation;
    }

    /**
     * Applies the given parameters onto the settings object, replacing all of them.
     *
     * The parameters have to be complete: every parameter of the settings must be present, and no unknown one may
     * be, so a client can not accidentally reset a parameter it did not know about to its default value.
     *
     * @param array<string, mixed> $parameters
     * @throws \InvalidArgumentException If a parameter is missing, unknown, or has an unusable value
     */
    public function applyRepresentation(object $settings, array $parameters): void
    {
        $metadata = $this->metadataManager->getSettingsMetadata($settings);

        $known = [];
        foreach ($metadata->getParameters() as $parameter) {
            $known[$parameter->getName()] = $parameter->getPropertyName();
        }

        $unknown = array_diff(array_keys($parameters), array_keys($known));
        if ($unknown !== []) {
            throw new \InvalidArgumentException(sprintf('Unknown parameter(s): %s', implode(', ', $unknown)));
        }

        $missing = array_diff(array_keys($known), array_keys($parameters));
        if ($missing !== []) {
            throw new \InvalidArgumentException(sprintf('Missing parameter(s): %s', implode(', ', $missing)));
        }

        foreach ($known as $name => $property) {
            $settings->$property = $this->representationToValue(
                $parameters[$name],
                new \ReflectionProperty($settings, $property),
                $name
            );
        }
    }

    /**
     * @throws \LogicException If the value has no unambiguous JSON representation
     */
    private function valueToRepresentation(mixed $value, string $path): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->valueToRepresentation($item, $path), $value);
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        throw new \LogicException(sprintf(
            'The value of %s (%s) can not be represented in the settings API. Only scalars, arrays and backed enums '
            . 'are supported, so this settings class must not be exposed via the API.',
            $path,
            get_debug_type($value)
        ));
    }

    /**
     * @throws \InvalidArgumentException If the value does not fit the property it should be written to
     */
    private function representationToValue(mixed $value, \ReflectionProperty $property, string $name): mixed
    {
        $type = $property->getType();

        //Without a declared type there is nothing to check the value against, so take it as it is
        if (!$type instanceof \ReflectionNamedType) {
            return $value;
        }

        if ($value === null) {
            if (!$type->allowsNull()) {
                throw new \InvalidArgumentException(sprintf('The parameter "%s" must not be null.', $name));
            }

            return null;
        }

        $expected = $type->getName();

        if (is_a($expected, \BackedEnum::class, true)) {
            if (!is_string($value) && !is_int($value)) {
                throw new \InvalidArgumentException(sprintf('The parameter "%s" must be a string or an integer.', $name));
            }

            return $expected::tryFrom($value)
                ?? throw new \InvalidArgumentException(sprintf('"%s" is not a valid value for the parameter "%s".', $value, $name));
        }

        $actual = get_debug_type($value);

        //An integer is an acceptable float, but not the other way around
        if ($expected === 'float' && $actual === 'int') {
            return (float) $value;
        }

        if ($expected !== $actual) {
            throw new \InvalidArgumentException(sprintf(
                'The parameter "%s" must be of type %s, %s given.', $name, $expected, $actual
            ));
        }

        return $value;
    }
}
