<?php

namespace App\Tests\Entity;

use App\Entity\Event_registration;
use PHPUnit\Framework\TestCase;

class EventRegistrationTest extends TestCase
{
    public function testImmutableRegisteredAtIsStoredAsMutableDateTime(): void
    {
        $registration = new Event_registration();
        $registration->setRegistered_at(new \DateTimeImmutable());

        $this->assertInstanceOf(\DateTime::class, $registration->getRegistered_at());
    }
}
