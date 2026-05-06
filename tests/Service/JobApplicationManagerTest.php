<?php

namespace App\Tests\Service;

use App\Entity\Candidate;
use App\Entity\Job_application;
use App\Entity\Job_offer;
use App\Service\JobApplicationManager;
use PHPUnit\Framework\TestCase;

class JobApplicationManagerTest extends TestCase
{
    public function testValidJobApplication(): void
    {
        $application = $this->createValidJobApplication();
        $manager = new JobApplicationManager();

        $this->assertTrue($manager->validate($application));
    }

    public function testJobApplicationWithoutOffer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A job offer is required.');

        $application = $this->createValidJobApplicationWithoutOffer();

        $manager = new JobApplicationManager();
        $manager->validate($application);
    }

    public function testJobApplicationWithoutCandidate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A candidate is required.');

        $application = $this->createValidJobApplicationWithoutCandidate();

        $manager = new JobApplicationManager();
        $manager->validate($application);
    }

    public function testJobApplicationWithInvalidPhone(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Phone number is invalid.');

        $application = $this->createValidJobApplication();
        $application->setPhone('12345678');

        $manager = new JobApplicationManager();
        $manager->validate($application);
    }

    public function testJobApplicationWithShortCoverLetter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cover letter must be between 50 and 2000 characters.');

        $application = $this->createValidJobApplication();
        $application->setCover_letter('I am interested.');

        $manager = new JobApplicationManager();
        $manager->validate($application);
    }

    public function testJobApplicationWithoutCv(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CV is required.');

        $application = $this->createValidJobApplication();
        $application->setCv_path('');

        $manager = new JobApplicationManager();
        $manager->validate($application);
    }

    public function testJobApplicationWithInvalidStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Application status is invalid.');

        $application = $this->createValidJobApplication();
        $application->setCurrent_status('PENDING');

        $manager = new JobApplicationManager();
        $manager->validate($application);
    }

    public function testJobApplicationWithFutureApplicationDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Application date cannot be in the future.');

        $application = $this->createValidJobApplication();
        $application->setApplied_at(new \DateTimeImmutable('+1 day'));

        $manager = new JobApplicationManager();
        $manager->validate($application);
    }

    private function createValidJobApplication(): Job_application
    {
        $application = new Job_application();
        $application->setOffer_id($this->createValidJobOffer());
        $application->setCandidate_id($this->createValidCandidate());
        $this->fillValidApplicationFields($application);

        return $application;
    }

    private function createValidJobApplicationWithoutOffer(): Job_application
    {
        $application = new Job_application();
        $application->setCandidate_id($this->createValidCandidate());
        $this->fillValidApplicationFields($application);

        return $application;
    }

    private function createValidJobApplicationWithoutCandidate(): Job_application
    {
        $application = new Job_application();
        $application->setOffer_id($this->createValidJobOffer());
        $this->fillValidApplicationFields($application);

        return $application;
    }

    private function fillValidApplicationFields(Job_application $application): void
    {
        $application->setPhone('+21655123456');
        $application->setCover_letter('I am very interested in this role because my Symfony experience matches the project needs.');
        $application->setCv_path('candidate-cv.pdf');
        $application->setCurrent_status('SUBMITTED');
        $application->setApplied_at(new \DateTimeImmutable('-1 hour'));
        $application->setIs_archived(false);
    }

    private function createValidJobOffer(): Job_offer
    {
        $offer = new Job_offer();
        $offer->setTitle('Symfony Developer');
        $offer->setContract_type('CDI');
        $offer->setStatus('open');
        $offer->setDescription('Build and maintain Symfony recruitment platform features.');
        $offer->setLocation('Tunis Centre');
        $offer->setDeadline(new \DateTimeImmutable('+7 days'));

        return $offer;
    }

    private function createValidCandidate(): Candidate
    {
        $candidate = new Candidate();
        $candidate->setEmail('candidate@example.com');
        $candidate->setFirstName('Aziz');
        $candidate->setLastName('Gharbi');
        $candidate->setPhone('+21655123456');
        $candidate->setPlainPassword('Password123');

        return $candidate;
    }
}
