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
namespace App\Tests\API\Endpoints;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\DataFixtures\APITokenFixtures;
use App\Tests\API\AuthenticatedApiTestCase;

final class SettingsEndpointTest extends AuthenticatedApiTestCase
{
    private const COLLECTION_PATH = '/api/settings';
    private const PATH = '/api/settings/tag_styles';

    /**
     * These endpoints are plain JSON only (no Hydra/JSON-LD), unlike the rest of the API, so the inherited
     * helper's default Accept header (application/ld+json) would get a 406 here.
     */
    protected static function createAuthenticatedClient(string $token = APITokenFixtures::TOKEN_ADMIN): Client
    {
        return static::createClient(defaultOptions: [
            'headers' => ['authorization' => 'Token '.$token, 'accept' => 'application/json'],
        ]);
    }

    private function getEtag(array $headers): string
    {
        self::assertArrayHasKey('etag', $headers);

        return $headers['etag'][0];
    }

    private function rules(array $rules): array
    {
        return ['parameters' => ['rules' => $rules]];
    }

    public function testUnauthenticatedIsRejected(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient(defaultOptions: ['headers' => ['accept' => 'application/json']]);
        $client->request('GET', self::PATH);
        self::assertResponseStatusCodeSame(401);
    }

    public function testTokenWithoutPermissionIsRejected(): void
    {
        self::createAuthenticatedClient(APITokenFixtures::TOKEN_READONLY)->request('GET', self::PATH);
        self::assertResponseStatusCodeSame(403);

        self::createAuthenticatedClient(APITokenFixtures::TOKEN_READONLY)->request('PUT', self::PATH, [
            'json' => $this->rules([]),
            'headers' => ['If-Match' => '"sha256:whatever"'],
        ]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testCollectionListsTheExposedSettings(): void
    {
        $response = self::createAuthenticatedClient()->request('GET', self::COLLECTION_PATH);

        self::assertResponseIsSuccessful();

        $names = array_column($response->toArray(), 'name');
        self::assertContains('tag_styles', $names);
        self::assertContains('external_part_links', $names);
    }

    public function testGetReturnsTheCurrentValuesWithAnEtag(): void
    {
        $response = self::createAuthenticatedClient()->request('GET', self::PATH);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json');
        //Symfony's Response::prepare() adds its own session-safety directives alongside ours, our stricter
        //"no-store" is still among them
        self::assertStringContainsString('no-store', $response->getHeaders()['cache-control'][0]);
        self::assertMatchesRegularExpression('/^"sha256:[0-9a-f]{64}"$/', $this->getEtag($response->getHeaders()));

        $data = $response->toArray();
        self::assertSame('tag_styles', $data['name']);
        self::assertTrue($data['writable']);
        self::assertIsArray($data['parameters']['rules']);
    }

    public function testSettingsWhichAreNotExposedAreNotFound(): void
    {
        self::createAuthenticatedClient()->request('GET', '/api/settings/system');
        self::assertResponseStatusCodeSame(404);
    }

    public function testPutWithoutIfMatchIsRejected(): void
    {
        self::createAuthenticatedClient()->request('PUT', self::PATH, ['json' => $this->rules([])]);
        self::assertResponseStatusCodeSame(428);
    }

    public function testPutReplacesTheValues(): void
    {
        $client = self::createAuthenticatedClient();
        $etag = $this->getEtag($client->request('GET', self::PATH)->getHeaders());

        $rules = [['match_type' => 'prefix', 'pattern' => 'Project:', 'color' => 'success']];
        $response = $client->request('PUT', self::PATH, [
            'json' => $this->rules($rules),
            'headers' => ['If-Match' => $etag],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame($rules, $response->toArray()['parameters']['rules']);
        //The written values must also be what a following GET returns
        self::assertSame($rules, $client->request('GET', self::PATH)->toArray()['parameters']['rules']);
    }

    public function testPutWithStaleIfMatchIsRejectedAndChangesNothing(): void
    {
        $client = self::createAuthenticatedClient();
        $stale_etag = $this->getEtag($client->request('GET', self::PATH)->getHeaders());

        //A first, valid write moves the real ETag away from the one captured above
        $client->request('PUT', self::PATH, [
            'json' => $this->rules([['match_type' => 'exact', 'pattern' => 'A', 'color' => 'info']]),
            'headers' => ['If-Match' => $stale_etag],
        ]);
        self::assertResponseIsSuccessful();

        $client->request('PUT', self::PATH, [
            'json' => $this->rules([['match_type' => 'exact', 'pattern' => 'B', 'color' => 'danger']]),
            'headers' => ['If-Match' => $stale_etag],
        ]);
        self::assertResponseStatusCodeSame(412);

        //The rejected write must not have been applied
        $rules = $client->request('GET', self::PATH)->toArray()['parameters']['rules'];
        self::assertSame('A', $rules[0]['pattern']);
    }

    public function testUnknownParameterIsRejected(): void
    {
        $client = self::createAuthenticatedClient();
        $etag = $this->getEtag($client->request('GET', self::PATH)->getHeaders());

        $client->request('PUT', self::PATH, [
            'json' => ['parameters' => ['rules' => [], 'not_a_parameter' => true]],
            'headers' => ['If-Match' => $etag],
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testInvalidValueIsRejected(): void
    {
        $client = self::createAuthenticatedClient();
        $etag = $this->getEtag($client->request('GET', self::PATH)->getHeaders());

        $client->request('PUT', self::PATH, [
            'json' => ['parameters' => ['rules' => 'not-a-list']],
            'headers' => ['If-Match' => $etag],
        ]);
        self::assertResponseStatusCodeSame(422);
    }
}
