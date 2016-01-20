<?php

namespace Depot\EventStore;

use Depot\EventStore\Persistence\CommittedEvent;

class CallableEventVisitor implements EventVisitor
{
    /**
     * @var callable
     */
    private $callable;

    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    public function doWithCommittedEvent(CommittedEvent $committedEvent)
    {
        call_user_func($this->callable, $committedEvent);
    }
}
