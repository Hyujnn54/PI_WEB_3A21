<?php

namespace App\Tests\Service;

use App\Entity\Recruiter;
use App\Entity\Recruitment_event;
use App\Service\EventManager;
use PHPUnit\Framework\TestCase;

class EventManagerTest extends TestCase
{
    public function testValidEvent(): void
    {
        $event = $this->createValidEvent();
        $manager = new EventManager();

        $this->assertTrue($manager->validate($event));
    }

    public function testValidWebinarWithMeetLink(): void
    {
        $event = $this->createValidEvent();
        $event->setEvent_type('Webinar');
        $event->setMeet_link('https://meet.example.com/recruitment-webinar');

        $manager = new EventManager();

        $this->assertTrue($manager->validate($event));
    }

    public function testEventWithoutRecruiter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A recruiter is required.');

        $event = $this->createValidEventWithoutRecruiter();

        $manager = new EventManager();
        $manager->validate($event);
    }

    public function testEventWithoutTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Event title is required.');

        $event = $this->createValidEvent();
        $event->setTitle('');

        $manager = new EventManager();
        $manager->validate($event);
    }

    public function testEventWithShortTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Event title must be at least 3 characters.');

        $event = $this->createValidEvent();
        $event->setTitle('HR');

        $manager = new EventManager();
        $manager->validate($event);
    }

    public function testEventWithShortDescription(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Description must be at least 10 characters.');

        $event = $this->createValidEvent();
        $event->setDescription('Too short');

        $manager = new EventManager();
        $manager->validate($event);
    }

    public function testEventWithInvalidEventType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid event type selected.');

        $event = $this->createValidEvent();
        $event->setEvent_type('Conference');

        $manager = new EventManager();
        $manager->validate($event);
    }

    public function testEventWithoutLocation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Location is required.');

        $event = $this->createValidEvent();
        $event->setLocation('');

        $manager = new EventManager();
        $manager->validate($event);
    }

    public function testEventWithPastDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Event date must be in the future.');

        $event = $this->createValidEvent();
        $event->setEvent_date(new \DateTimeImmutable('-1 day'));

        $manager = new EventManager();
        $manager->validate($event);
    }

    public function testEventWithInvalidCapacity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Capacity must be at least 1.');

        $event = $this->createValidEvent();
        $event->setCapacity(0);

        $manager = new EventManager();
        $manager->validate($event);
    }

    public function testEventWithCapacityGreaterThanLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Capacity cannot exceed 1000.');

        $event = $this->createValidEvent();
        $event->setCapacity(1001);

        $manager = new EventManager();
        $manager->validate($event);
    }

    public function testEventWithInvalidMeetLink(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Please enter a valid URL.');

        $event = $this->createValidEvent();
        $event->setMeet_link('meet-link');

        $manager = new EventManager();
        $manager->validate($event);
    }

    private function createValidEvent(): Recruitment_event
    {
        $event = new Recruitment_event();
        $event->setRecruiter_id($this->createRecruiter());
        $this->fillValidEventFields($event);

        return $event;
    }

    private function createValidEventWithoutRecruiter(): Recruitment_event
    {
        $event = new Recruitment_event();
        $this->fillValidEventFields($event);

        return $event;
    }

    private function fillValidEventFields(Recruitment_event $event): void
    {
        $event->setTitle('Symfony Hiring Day');
        $event->setDescription('Meet recruiters and discover Symfony job opportunities.');
        $event->setEvent_type('Hiring Day');
        $event->setLocation('Tunis');
        $event->setEvent_date(new \DateTimeImmutable('+10 days'));
        $event->setCapacity(50);
        $event->setMeet_link('');
        $event->setCreated_at(new \DateTimeImmutable('-1 day'));
    }

    private function createRecruiter(): Recruiter
    {
        $recruiter = new Recruiter();
        $recruiter->setEmail('recruiter@example.com');
        $recruiter->setFirstName('Recruiter');
        $recruiter->setLastName('Test');
        $recruiter->setPhone('+21655123456');
        $recruiter->setPlainPassword('Password123');
        $recruiter->setCompanyName('Talent Bridge');

        return $recruiter;
    }
}
