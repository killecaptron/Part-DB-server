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

namespace App\Tests\Services\Settings;

use App\Services\Settings\SettingsETagHelper;
use PHPUnit\Framework\TestCase;

final class SettingsETagHelperTest extends TestCase
{
    public function testSameContentProducesSameEtag(): void
    {
        $body = ['rules' => [['match_type' => 'exact', 'pattern' => 'A', 'color' => 'info']]];

        self::assertSame(SettingsETagHelper::compute($body), SettingsETagHelper::compute($body));
    }

    public function testDifferentContentProducesDifferentEtag(): void
    {
        $a = ['rules' => [['match_type' => 'exact', 'pattern' => 'A', 'color' => 'info']]];
        $b = ['rules' => [['match_type' => 'exact', 'pattern' => 'B', 'color' => 'info']]];

        self::assertNotSame(SettingsETagHelper::compute($a), SettingsETagHelper::compute($b));
    }

    public function testReorderingProducesDifferentEtag(): void
    {
        $a = ['rules' => [
            ['match_type' => 'exact', 'pattern' => 'A', 'color' => 'info'],
            ['match_type' => 'exact', 'pattern' => 'B', 'color' => 'danger'],
        ]];
        $b = ['rules' => [
            ['match_type' => 'exact', 'pattern' => 'B', 'color' => 'danger'],
            ['match_type' => 'exact', 'pattern' => 'A', 'color' => 'info'],
        ]];

        self::assertNotSame(SettingsETagHelper::compute($a), SettingsETagHelper::compute($b));
    }

    public function testFormat(): void
    {
        self::assertMatchesRegularExpression('/^sha256:[0-9a-f]{64}$/', SettingsETagHelper::compute(['rules' => []]));
    }

    public function testExtractFromHeaderValueStripsQuotesAndWhitespace(): void
    {
        self::assertSame('sha256:abc', SettingsETagHelper::extractFromHeaderValue('"sha256:abc"'));
        self::assertSame('sha256:abc', SettingsETagHelper::extractFromHeaderValue(' "sha256:abc" '));
        self::assertSame('sha256:abc', SettingsETagHelper::extractFromHeaderValue('sha256:abc'));
    }

    public function testExtractFromHeaderValueReturnsNullForEmpty(): void
    {
        self::assertNull(SettingsETagHelper::extractFromHeaderValue(null));
        self::assertNull(SettingsETagHelper::extractFromHeaderValue(''));
    }
}
