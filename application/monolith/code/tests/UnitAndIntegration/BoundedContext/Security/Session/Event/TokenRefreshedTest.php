<?php

declare(strict_types=1);

namespace Tests\Galeas\Api\UnitAndIntegration\BoundedContext\Security\Session\Event;

use Galeas\Api\BoundedContext\Security\Session\Aggregate\Session;
use Galeas\Api\BoundedContext\Security\Session\Event\TokenRefreshed;
use Galeas\Api\BoundedContext\Security\Session\ValueObject\SessionDetails;
use Galeas\Api\Common\Id\Id;
use Galeas\Api\Primitive\PrimitiveValidation\Session\SessionTokenValidator;
use PHPUnit\Framework\Assert;
use Tests\Galeas\Api\UnitAndIntegration\UnitTestBase;

class TokenRefreshedTest extends UnitTestBase
{
    /**
     * @test
     */
    public function testCreate(): void
    {
        $aggregateId = Id::createNew();
        $authenticatedUserId = Id::createNew();
        $metadata = [1, 2, 3];
        $withIp = '127.0.0.1';
        $withSessionToken = 'session_token';

        $tokenRefreshed = TokenRefreshed::fromProperties(
            $aggregateId,
            $authenticatedUserId,
            $metadata,
            $withIp,
            $withSessionToken
        );

        Assert::assertEquals($aggregateId, $tokenRefreshed->aggregateId());
        Assert::assertEquals($authenticatedUserId, $tokenRefreshed->authenticatedUserId());
        Assert::assertEquals($metadata, $tokenRefreshed->metadata());
        Assert::assertEquals($withIp, $tokenRefreshed->withIp());
        Assert::assertEquals($withSessionToken, $tokenRefreshed->withExistingSessionToken());
        Assert::assertTrue(
            SessionTokenValidator::isValid(
                $tokenRefreshed->refreshedSessionToken()
            )
        );
        Assert::assertNotEquals(
            $tokenRefreshed->withExistingSessionToken(), $tokenRefreshed->refreshedSessionToken());
    }

    /**
     * @test
     */
    public function testTransformAggregate(): void
    {
        $session = Session::fromProperties(
            Id::createNew(),
            SessionDetails::fromProperties(
                Id::createNew(),
                'test_username',
                'test_email',
                'test_hashed_password',
                'by_device_label',
                '127.0.0.1',
                'with_session_token'
            ),
            null
        );

        $signedOut = TokenRefreshed::fromProperties(
            Id::createNew(),
            Id::createNew(),
            [1, 2, 3],
            '127.0.0.2',
            'new_session_token'
        );

        $transformedSession = $signedOut->transformSession($session);

        Assert::assertEquals(
            $session->aggregateId(),
            $transformedSession->id()
        );
        Assert::assertEquals(
            SessionDetails::fromProperties(
                $session->sessionDetails()->asUser(),
                $session->sessionDetails()->withUsername(),
                $session->sessionDetails()->withEmail(),
                $session->sessionDetails()->withHashedPassword(),
                $session->sessionDetails()->byDeviceLabel(),
                $signedOut->withIp(),
                $signedOut->refreshedSessionToken()
            ),
            $transformedSession->sessionDetails()
        );
        Assert::assertEquals(
            $session->sessionIsSignedOut(),
            $transformedSession->sessionIsSignedOut()
        );
    }
}
