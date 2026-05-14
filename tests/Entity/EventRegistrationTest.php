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

    public function testAttendanceStatusAliasesAreNormalized(): void
    {
        $registration = new Event_registration();

        $registration->setAttendance_status('CONFIRMED');
        $this->assertSame(Event_registration::STATUS_CONFIRMED, $registration->getAttendance_status());

        $registration->setAttendance_status('approved');
        $this->assertSame(Event_registration::STATUS_CONFIRMED, $registration->getAttendance_status());

        $registration->setAttendance_status('DECLINED');
        $this->assertSame(Event_registration::STATUS_REJECTED, $registration->getAttendance_status());

        $registration->setAttendance_status(null);
        $this->assertSame(Event_registration::STATUS_REGISTERED, $registration->getAttendance_status());
    }
}
