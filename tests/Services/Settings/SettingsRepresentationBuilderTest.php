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

use App\Services\Settings\SettingsRepresentationBuilder;
use App\Settings\TagStyleSettings;
use App\Tests\SettingsTestHelper;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SettingsRepresentationBuilderTest extends KernelTestCase
{
    private SettingsRepresentationBuilder $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(SettingsRepresentationBuilder::class);
    }

    private function settings(array $rules = []): TagStyleSettings
    {
        /** @var TagStyleSettings $settings */
        $settings = SettingsTestHelper::createSettingsDummy(TagStyleSettings::class);
        $settings->rules = $rules;

        return $settings;
    }

    public function testRepresentationContainsEveryParameter(): void
    {
        $rules = [['match_type' => 'exact', 'pattern' => 'Risk', 'color' => 'info']];

        self::assertSame(['rules' => $rules], $this->service->toRepresentation($this->settings($rules)));
    }

    public function testRepresentationCanBeAppliedBack(): void
    {
        $settings = $this->settings();
        $rules = [['match_type' => 'prefix', 'pattern' => 'Project:', 'color' => 'success']];

        $this->service->applyRepresentation($settings, ['rules' => $rules]);

        self::assertSame($rules, $settings->rules);
    }

    public function testUnknownParameterIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown parameter(s): not_a_parameter');

        $this->service->applyRepresentation($this->settings(), ['rules' => [], 'not_a_parameter' => 1]);
    }

    public function testMissingParameterIsRejected(): void
    {
        //A PUT replaces everything, so a client must not be able to reset a parameter it did not know about
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing parameter(s): rules');

        $this->service->applyRepresentation($this->settings(), []);
    }

    public function testValueOfTheWrongTypeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be of type array');

        $this->service->applyRepresentation($this->settings(), ['rules' => 'not-an-array']);
    }

    public function testNullForANonNullableParameterIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must not be null');

        $this->service->applyRepresentation($this->settings(), ['rules' => null]);
    }
}
