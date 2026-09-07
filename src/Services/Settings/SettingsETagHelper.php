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

namespace App\Services\Settings;

/**
 * Computes the strong ETag used by the settings API (/api/settings/{name}), so concurrent writers can be detected
 * via If-Match.
 *
 * The ETag is a sha256 hash of the canonical (order-preserving) JSON representation of the exact response body a
 * GET would currently return. It is intentionally order-sensitive: the order of a list setting is usually
 * meaningful (it can decide tie-breaking priority), so reordering without any other change must still produce a
 * different ETag.
 *
 * @see \App\Tests\Services\Settings\SettingsETagHelperTest
 */
final class SettingsETagHelper
{
    /**
     * @param array<string, mixed> $representation The exact structure that GET returns as JSON
     */
    public static function compute(array $representation): string
    {
        $json = json_encode($representation, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return 'sha256:' . hash('sha256', $json);
    }

    /**
     * Extracts the raw ETag value (e.g. "sha256:abcd...") from a raw If-Match header value
     * (e.g. `"sha256:abcd..."`, quoted per RFC 9110), so it can be compared against
     * {@see self::compute()}'s return value. Returns null for an empty/missing header.
     */
    public static function extractFromHeaderValue(?string $headerValue): ?string
    {
        if ($headerValue === null || $headerValue === '') {
            return null;
        }

        return trim($headerValue, " \t\"");
    }
}
