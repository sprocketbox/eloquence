<?php

namespace Sprocketbox\Eloquence;

class ModelIdentity
{
    protected string $class;

    /**
     * @var mixed
     */
    protected         $id;

    protected ?string $connection = null;

    public function __construct(string $class, $id, ?string $connection = null)
    {
        $this->id         = $id;
        $this->class      = $class;
        $this->connection = $connection;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getConnection(): ?string
    {
        return $this->connection;
    }

    public function __toString()
    {
        return implode(':', [
            $this->getConnection(),
            $this->getClass(),
            $this->getId(),
        ]);
    }
}