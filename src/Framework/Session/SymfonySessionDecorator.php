<?php

namespace Framework\Session;

use BadMethodCallException;
use Framework\Contracts\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;

class SymfonySessionDecorator implements SessionInterface
{
    /**
     * The underlying Laravel session store.
     *
     * @var \Framework\Session\Store
     */
    protected $store;

    /**
     * Create a new session decorator.
     *
     * @param  \Framework\Contracts\Session\Session  $store
     * @return void
     */
    public function __construct(Session $store)
    {
        $this->store = $store;
    }

    /**
     * {@inheritdoc}
     */
    public function start(): bool
    {
        return $this->store->start();
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return $this->store->getId();
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function setId(string $id): void
    {
        $this->store->setId($id);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->store->getName();
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function setName(string $name): void
    {
        $this->store->setName($name);
    }

    /**
     * {@inheritdoc}
     */
    public function invalidate(?int $lifetime = null): bool
    {
        $this->store->invalidate();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function migrate(bool $destroy = false, int|null $lifetime = null): bool
    {
        $this->store->migrate($destroy);

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function save(): void
    {
        $this->store->save();
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $name): bool
    {
        return $this->store->has($name);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name, mixed $default = null): mixed
    {
        return $this->store->get($name, $default);
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function set(string $name, mixed $value): void
    {
        $this->store->put($name, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function all(): array
    {
        return $this->store->all();
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function replace(array $attributes): void
    {
        $this->store->replace($attributes);
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $name): mixed
    {
        return $this->store->remove($name);
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function clear(): void
    {
        $this->store->flush();
    }

    /**
     * {@inheritdoc}
     */
    public function isStarted(): bool
    {
        return $this->store->isStarted();
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function registerBag(SessionBagInterface $bag): void
    {
        throw new BadMethodCallException('Method not implemented by Laravel.');
    }

    /**
     * {@inheritdoc}
     */
    public function getBag(string $name): SessionBagInterface
    {
        throw new BadMethodCallException('Method not implemented by Laravel.');
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadataBag(): MetadataBag
    {
        throw new BadMethodCallException('Method not implemented by Laravel.');
    }
}
