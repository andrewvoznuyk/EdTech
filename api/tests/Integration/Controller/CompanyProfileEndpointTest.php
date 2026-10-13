<?php

namespace App\Tests\Integration\Controller;

use App\Entity\CompanyProfile;
use Symfony\Component\HttpFoundation\Response;

class CompanyProfileEndpointTest extends AbstractControllerIntegrationTestCase
{
    /**
     * @return void
     */
    public function testPublicGetCompaniesCollection(): void
    {
        $this->client->request('GET', '/api/public/companies');
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
    }

    /**
     * @return void
     */
    public function testPublicGetCompanyItem(): void
    {
        $user = $this->createUser(sprintf('company_owner_%s@example.com', uniqid()));

        $company = new CompanyProfile();
        $company->setCompanyName('Public Company ' . uniqid());
        $company->setEdrpou(str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT));
        $company->setActualAddress('Address 1');
        $company->setRegistrationAddress('Address 2');
        $company->setPosition('Director');
        $company->setPhone('+380501234567');
        $company->setUser($user);
        $this->entityManager->persist($company);
        $this->entityManager->flush();

        $companyId = $company->getId()->toRfc4122();

        $this->client->request('GET', '/api/public/companies/' . $companyId);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertSame($company->getCompanyName(), $content['companyName']);
    }

    /**
     * @return void
     */
    public function testCreateCompanyUnauthorized(): void
    {
        $payload = json_encode([
            'companyName' => 'Unauthorized Ltd',
            'edrpou' => '12345678',
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/companies', [], [], ['CONTENT_TYPE' => 'application/ld+json'], $payload);
        $this->assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testCreateCompanySuccess(): void
    {
        $user = $this->createUser(sprintf('creator_%s@example.com', uniqid()));
        $headers = $this->getAuthHeaders($user);
        $headers['CONTENT_TYPE'] = 'application/ld+json';

        $companyName = 'Created Company ' . uniqid();
        $edrpou = str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT);

        $payload = json_encode([
            'companyName' => $companyName,
            'edrpou' => $edrpou,
            'actualAddress' => 'Khreshchatyk 1',
            'registrationAddress' => 'Khreshchatyk 1',
            'position' => 'CEO',
            'phone' => '+380501112233',
        ], JSON_THROW_ON_ERROR);

        $this->client->request('POST', '/api/companies', [], [], $headers, $payload);
        $this->assertSame(Response::HTTP_CREATED, $this->client->getResponse()->getStatusCode());

        $content = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($content);
        $this->assertSame($companyName, $content['companyName']);

        $persisted = $this->entityManager->getRepository(CompanyProfile::class)->findOneBy(['companyName' => $companyName]);
        $this->assertNotNull($persisted);
        $this->assertSame($user->getId()->toRfc4122(), $persisted->getUser()->getId()->toRfc4122());
    }

    /**
     * @return void
     */
    public function testGetUserCompaniesCollectionSecurity(): void
    {
        $owner = $this->createUser(sprintf('owner_%s@example.com', uniqid()));
        $otherUser = $this->createUser(sprintf('other_%s@example.com', uniqid()));

        $ownerHeaders = $this->getAuthHeaders($owner);
        $otherHeaders = $this->getAuthHeaders($otherUser);

        $ownerId = $owner->getId()->toRfc4122();

        // Owner can access own companies
        $this->client->request('GET', '/api/users/' . $ownerId . '/companies', [], [], $ownerHeaders);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        // Other regular user is forbidden from accessing owner's companies
        $this->client->request('GET', '/api/users/' . $ownerId . '/companies', [], [], $otherHeaders);
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testPatchCompanySecurity(): void
    {
        $owner = $this->createUser(sprintf('patch_owner_%s@example.com', uniqid()));
        $otherUser = $this->createUser(sprintf('patch_other_%s@example.com', uniqid()));

        $company = new CompanyProfile();
        $company->setCompanyName('Original Name ' . uniqid());
        $company->setEdrpou(str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT));
        $company->setActualAddress('Address 1');
        $company->setRegistrationAddress('Address 2');
        $company->setPosition('Director');
        $company->setPhone('+380501234567');
        $company->setUser($owner);
        $this->entityManager->persist($company);
        $this->entityManager->flush();

        $companyId = $company->getId()->toRfc4122();

        $otherHeaders = $this->getAuthHeaders($otherUser);
        $otherHeaders['CONTENT_TYPE'] = 'application/merge-patch+json';

        $payload = json_encode(['position' => 'Hacked Position'], JSON_THROW_ON_ERROR);

        // Other user is forbidden
        $this->client->request('PATCH', '/api/companies/' . $companyId, [], [], $otherHeaders, $payload);
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());

        // Owner is allowed
        $ownerHeaders = $this->getAuthHeaders($owner);
        $ownerHeaders['CONTENT_TYPE'] = 'application/merge-patch+json';

        $ownerPayload = json_encode(['position' => 'Updated Position'], JSON_THROW_ON_ERROR);
        $this->client->request('PATCH', '/api/companies/' . $companyId, [], [], $ownerHeaders, $ownerPayload);
        $this->assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
    }

    /**
     * @return void
     */
    public function testDeleteCompanySecurity(): void
    {
        $owner = $this->createUser(sprintf('del_owner_%s@example.com', uniqid()));
        $otherUser = $this->createUser(sprintf('del_other_%s@example.com', uniqid()));

        $company = new CompanyProfile();
        $company->setCompanyName('To Delete ' . uniqid());
        $company->setEdrpou(str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT));
        $company->setActualAddress('Address 1');
        $company->setRegistrationAddress('Address 2');
        $company->setPosition('Director');
        $company->setPhone('+380501234567');
        $company->setUser($owner);
        $this->entityManager->persist($company);
        $this->entityManager->flush();

        $companyId = $company->getId()->toRfc4122();

        // Other user cannot delete
        $otherHeaders = $this->getAuthHeaders($otherUser);
        $this->client->request('DELETE', '/api/companies/' . $companyId, [], [], $otherHeaders);
        $this->assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());

        // Owner can delete
        $ownerHeaders = $this->getAuthHeaders($owner);
        $this->client->request('DELETE', '/api/companies/' . $companyId, [], [], $ownerHeaders);
        $this->assertSame(Response::HTTP_NO_CONTENT, $this->client->getResponse()->getStatusCode());
    }
}
