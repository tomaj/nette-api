<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Authorization;

use InvalidArgumentException;

class MultiAuthorizator implements ApiAuthorizationInterface
{
    /**
     * @var string|null
     */
    protected $errorMessage = null;

    /**
     * @var array<ApiAuthorizationInterface>
     */
    private $authorizators = [];

    public function __construct(array $authorizators)
    {
        foreach ($authorizators as $authorizator) {
            if (!$authorizator instanceof ApiAuthorizationInterface) {
                throw new InvalidArgumentException(sprintf('First argument must contain only %s items.', ApiAuthorizationInterface::class));
            }
            $this->authorizators[] = $authorizator;
        }
        if (count($this->authorizators) === 0) {
            throw new InvalidArgumentException('Set at least one Authorizator');
        }
    }

    /**
     * @return array<ApiAuthorizationInterface>
     */
    public function getAuthorizators(): array
    {
        return $this->authorizators;
    }

    public function authorized(): bool
    {
        foreach ($this->authorizators as $authorizator) {
            if ($authorizator->authorized()) {
                return true;
            } elseif ($authorizator->getErrorMessage() !== null) {
                $this->errorMessage = $authorizator->getErrorMessage();
            }
        }
        if ($this->errorMessage === null) {
            $this->errorMessage = 'Request is invalid for all authorizators';
        }
        return false;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
