<?php

namespace App\Tests\Service;

use App\Entity\Job_offer;
use App\Service\JobOfferManager;
use PHPUnit\Framework\TestCase;

class JobOfferManagerTest extends TestCase
{
    public function testValidJobOffer(): void
    {
        $offer = $this->createValidJobOffer();
        $manager = new JobOfferManager();

        $this->assertTrue($manager->validate($offer));
    }

    public function testJobOfferWithoutTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Title is required.');

        $offer = $this->createValidJobOffer();
        $offer->setTitle('');

        $manager = new JobOfferManager();
        $manager->validate($offer);
    }

    public function testJobOfferWithInvalidTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Title must be 3-150 characters and contain valid text.');

        $offer = $this->createValidJobOffer();
        $offer->setTitle('AI');

        $manager = new JobOfferManager();
        $manager->validate($offer);
    }

    public function testJobOfferWithInvalidContractType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Contract type is invalid.');

        $offer = $this->createValidJobOffer();
        $offer->setContract_type('Temporary');

        $manager = new JobOfferManager();
        $manager->validate($offer);
    }

    public function testJobOfferWithInvalidStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Status is invalid.');

        $offer = $this->createValidJobOffer();
        $offer->setStatus('published');

        $manager = new JobOfferManager();
        $manager->validate($offer);
    }

    public function testJobOfferWithShortDescription(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Description must be 10-1000 characters and contain valid text.');

        $offer = $this->createValidJobOffer();
        $offer->setDescription('Too short');

        $manager = new JobOfferManager();
        $manager->validate($offer);
    }

    public function testJobOfferWithoutLocation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Location is required.');

        $offer = $this->createValidJobOffer();
        $offer->setLocation('');

        $manager = new JobOfferManager();
        $manager->validate($offer);
    }

    public function testJobOfferWithPastDeadline(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Deadline must be greater than today.');

        $offer = $this->createValidJobOffer();
        $offer->setDeadline(new \DateTimeImmutable('-1 day'));

        $manager = new JobOfferManager();
        $manager->validate($offer);
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
}
