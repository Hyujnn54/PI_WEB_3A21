<?php

namespace App\Tests\Service;

use App\Entity\Interview;
use App\Entity\Job_application;
use App\Entity\Recruiter;
use App\Service\InterviewManager;
use PHPUnit\Framework\TestCase;

class InterviewManagerTest extends TestCase
{
    public function testValidOnlineInterview(): void
    {
        $interview = $this->createValidOnlineInterview();
        $manager = new InterviewManager();

        $this->assertTrue($manager->validate($interview));
    }

    public function testInterviewStatusAliasesAreStoredAsDatabaseValues(): void
    {
        $interview = $this->createValidOnlineInterview();
        $interview->setStatus('completed');

        $manager = new InterviewManager();

        $this->assertSame('DONE', $interview->getStatus());
        $this->assertTrue($manager->validate($interview));
    }

    public function testNullableOptionalInterviewFieldsAreNormalized(): void
    {
        $interview = $this->createValidOnlineInterview();
        $interview->setLocation(null);
        $interview->setNotes(null);

        $this->assertSame('', $interview->getLocation());
        $this->assertSame('', $interview->getNotes());
        $this->assertSame('online', $interview->getMode());
        $this->assertTrue((new InterviewManager())->validate($interview));
    }

    public function testValidOnsiteInterview(): void
    {
        $interview = $this->createValidOnsiteInterview();
        $manager = new InterviewManager();

        $this->assertTrue($manager->validate($interview));
    }

    public function testInterviewWithoutApplication(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('An application is required.');

        $interview = $this->createValidInterviewWithoutApplication();

        $manager = new InterviewManager();
        $manager->validate($interview);
    }

    public function testInterviewWithoutRecruiter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A recruiter is required.');

        $interview = $this->createValidInterviewWithoutRecruiter();

        $manager = new InterviewManager();
        $manager->validate($interview);
    }

    public function testInterviewWithPastDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Interview date/time must be in the future.');

        $interview = $this->createValidOnlineInterview();
        $interview->setScheduled_at(new \DateTimeImmutable('-1 day'));

        $manager = new InterviewManager();
        $manager->validate($interview);
    }

    public function testInterviewScheduledTooFarAhead(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Interview cannot be scheduled more than 90 days ahead.');

        $interview = $this->createValidOnlineInterview();
        $interview->setScheduled_at(new \DateTimeImmutable('+120 days'));

        $manager = new InterviewManager();
        $manager->validate($interview);
    }

    public function testInterviewWithInvalidDuration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duration must be between 15 and 240 minutes.');

        $interview = $this->createValidOnlineInterview();
        $interview->setDuration_minutes(10);

        $manager = new InterviewManager();
        $manager->validate($interview);
    }

    public function testOnlineInterviewWithoutMeetingLink(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Meeting link is required for online interviews.');

        $interview = $this->createValidOnlineInterview();
        $interview->setMeeting_link('');

        $manager = new InterviewManager();
        $manager->validate($interview);
    }

    public function testOnlineInterviewWithInvalidMeetingLink(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Meeting link must be a valid http(s) URL.');

        $interview = $this->createValidOnlineInterview();
        $interview->setMeeting_link('meeting-room');

        $manager = new InterviewManager();
        $manager->validate($interview);
    }

    public function testOnsiteInterviewWithoutLocation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Location is required for onsite interviews.');

        $interview = $this->createValidOnsiteInterview();
        $interview->setLocation('');

        $manager = new InterviewManager();
        $manager->validate($interview);
    }

    public function testInterviewWithInvalidNotes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Notes contain unsupported characters or exceed 1000 characters.');

        $interview = $this->createValidOnlineInterview();
        $interview->setNotes(str_repeat('a', 1001));

        $manager = new InterviewManager();
        $manager->validate($interview);
    }

    public function testInterviewWithInvalidStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Interview status is invalid.');

        $interview = $this->createValidOnlineInterview();
        $interview->setStatus('WAITING');

        $manager = new InterviewManager();
        $manager->validate($interview);
    }

    private function createValidOnlineInterview(): Interview
    {
        $interview = new Interview();
        $interview->setApplication_id(new Job_application());
        $interview->setRecruiter_id($this->createRecruiter());
        $this->fillValidInterviewFields($interview);
        $interview->setMode('online');
        $interview->setMeeting_link('https://meet.example.com/interview-room');
        $interview->setLocation('');

        return $interview;
    }

    private function createValidOnsiteInterview(): Interview
    {
        $interview = new Interview();
        $interview->setApplication_id(new Job_application());
        $interview->setRecruiter_id($this->createRecruiter());
        $this->fillValidInterviewFields($interview);
        $interview->setMode('onsite');
        $interview->setMeeting_link('');
        $interview->setLocation('Talent Bridge Office Tunis');

        return $interview;
    }

    private function createValidInterviewWithoutApplication(): Interview
    {
        $interview = new Interview();
        $interview->setRecruiter_id($this->createRecruiter());
        $this->fillValidInterviewFields($interview);
        $interview->setMode('online');
        $interview->setMeeting_link('https://meet.example.com/interview-room');

        return $interview;
    }

    private function createValidInterviewWithoutRecruiter(): Interview
    {
        $interview = new Interview();
        $interview->setApplication_id(new Job_application());
        $this->fillValidInterviewFields($interview);
        $interview->setMode('online');
        $interview->setMeeting_link('https://meet.example.com/interview-room');

        return $interview;
    }

    private function fillValidInterviewFields(Interview $interview): void
    {
        $interview->setScheduled_at(new \DateTimeImmutable('+7 days'));
        $interview->setDuration_minutes(60);
        $interview->setStatus('SCHEDULED');
        $interview->setNotes('Technical interview with the recruiter.');
        $interview->setCreated_at(new \DateTime('-1 day'));
        $interview->setReminder_sent(false);
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
