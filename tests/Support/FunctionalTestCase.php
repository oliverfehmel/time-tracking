<?php

namespace App\Tests\Support;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Base class for functional tests against the real test database.
 * Each test runs inside a transaction that dama/doctrine-test-bundle rolls back afterwards.
 */
abstract class FunctionalTestCase extends WebTestCase
{
    use ClockSensitiveTrait;
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;
    protected TestDataFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        // Stateless CSRF protection (form token id "submit") accepts same-origin requests.
        $this->client  = static::createClient([], ['HTTP_SEC_FETCH_SITE' => 'same-origin']);
        $this->em      = static::getContainer()->get(EntityManagerInterface::class);
        $this->factory = new TestDataFactory($this->em);
    }

    protected function loginAs(User $user): void
    {
        $this->client->loginUser($user);
    }

    /**
     * Reloads an entity from the database (the request ran in the same connection, but a different unit of work state).
     *
     * @template T of object
     * @param T $entity
     * @return T|null
     */
    protected function reload(object $entity): ?object
    {
        $id = $this->em->getUnitOfWork()->getSingleIdentifierValue($entity);
        $this->em->clear();

        return $this->em->find($entity::class, $id);
    }

    protected function assertFlash(string $type, string $message): void
    {
        $flashes = $this->client->getRequest()->getSession()->getFlashBag()->peek($type);
        self::assertContains($message, $flashes, sprintf('Flash "%s" not found in "%s": %s', $message, $type, json_encode($flashes)));
    }

    /**
     * Extracts the value of a hidden CSRF input that belongs to a form posting to $action.
     */
    protected function csrfTokenFor(string $pageUrl, string $action): string
    {
        $crawler = $this->client->request('GET', $pageUrl);
        self::assertResponseIsSuccessful();
        $input = $crawler->filter(sprintf('form[action="%s"] input[name="_token"]', $action));
        self::assertCount(1, $input, sprintf('No CSRF form for %s on %s', $action, $pageUrl));

        return (string) $input->attr('value');
    }
}
