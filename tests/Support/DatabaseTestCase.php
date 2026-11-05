<?php

namespace App\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Base class for service tests against the real test database (rolled back after each test).
 * Use {@see ClockSensitiveTrait::mockTime()} to pin "now" for services that use the clock.
 */
abstract class DatabaseTestCase extends KernelTestCase
{
    use ClockSensitiveTrait;

    protected EntityManagerInterface $em;
    protected TestDataFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->em      = static::getContainer()->get(EntityManagerInterface::class);
        $this->factory = new TestDataFactory($this->em);
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    protected function service(string $id): object
    {
        return static::getContainer()->get($id);
    }
}
