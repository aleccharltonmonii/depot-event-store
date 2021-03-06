<?php

use Depot\EventStore\EventStream;
use Depot\EventStore\EventEnvelope;
use Depot\EventStore\Persistence\Persistence;
use Depot\Testing\Fixtures\Banking\Account\AccountWasOpened;
use Depot\Testing\Fixtures\Banking\Account\AccountBalanceIncreased;
use Depot\Contract\SimplePhpFqcnContractResolver;
use Depot\EventStore\Transaction\CommitId;
use PHPUnit_Framework_TestCase as TestCase;

class EventStreamTest extends TestCase
{
    /**
     * @var ContractResolver
     */
    private $contractResolver;

    private function setUpContractResolver()
    {
        $this->contractResolver = new SimplePhpFqcnContractResolver();
    }

    private function createEventEnvelope($eventId, $event, $version)
    {
        return new EventEnvelope(
            $this->contractResolver->resolveFromObject($event),
            $eventId,
            $event,
            $version
        );
    }

    public function testAppendingEventEnvelopeToCreatedEventStream()
    {
        $this->setUpContractResolver();

        $contract = $this->contractResolver->resolveFromClassName(Account::class);

        $persistence = $this->getMockBuilder(Persistence::class)
            ->getMock();

        $persistence
            ->expects($this->never())
            ->method('fetch');

        $eventStream = EventStream::create($persistence, $contract, 123);

        $appendedEventEnvelope = $this->createEventEnvelope(
            123,
            new AccountWasOpened('fixture-account-000', 25),
            0
        );

        $eventStream->append($appendedEventEnvelope);

        $this->assertEquals($eventStream->all(), [
            $appendedEventEnvelope
        ]);
    }

    public function testAppendingEventEnvelopeToOpenedEventStream()
    {
        $this->setUpContractResolver();

        $contract = $this->contractResolver->resolveFromClassName(Account::class);

        $existingEventEnvelope = $this->createEventEnvelope(
            123,
            new AccountWasOpened('fixture-account-000', 25),
            0
        );

        $persistence = $this->getMockBuilder(Persistence::class)
            ->getMock();

        $persistence
            ->expects($this->once())
            ->method('fetch')
            ->with($this->equalTo($contract), $this->equalTo(123))
            ->will($this->returnValue([$existingEventEnvelope]));

        $eventStream = EventStream::open($persistence, $contract, 123);

        $appendedEventEnvelope = $this->createEventEnvelope(
            124,
            new AccountBalanceIncreased('fixture-account-000', 10),
            1
        );

        $eventStream->append($appendedEventEnvelope);

        $this->assertEquals($eventStream->all(), [
            $existingEventEnvelope,
            $appendedEventEnvelope
        ]);
    }

    public function testCommittingEventStream()
    {
        $this->setUpContractResolver();

        $contract = $this->contractResolver->resolveFromClassName(Account::class);

        $appendedEventEnvelopeOne = $this->createEventEnvelope(
            123,
            new AccountWasOpened('fixture-account-000', 25),
            0
        );

        $appendedEventEnvelopeTwo = $this->createEventEnvelope(
            124,
            new AccountWasOpened('fixture-account-001', 35),
            1
        );

        // Commit EventStream - First Time
        $commitIdOne = CommitId::fromString('first-time');

        // Commit EventStream - First Time
        $commitIdTwo = CommitId::fromString('second-time');

        $persistence = $this->getMockBuilder(Persistence::class)
            ->getMock();

        $eventStream = EventStream::create($persistence, $contract, 123);

        $persistence
            ->expects($this->exactly(2))
            ->method('commit')
            ->withConsecutive(
                array($commitIdOne),
                array($commitIdTwo)
            );

        // Append to EventStream
        $eventStream->append($appendedEventEnvelopeOne);

        $this->assertEquals($eventStream->all(), [
            $appendedEventEnvelopeOne
        ]);

        $eventStream->commit($commitIdOne);

        $this->assertEquals($eventStream->all(), [
            $appendedEventEnvelopeOne
        ]);

        $eventStream->append($appendedEventEnvelopeTwo);

        $this->assertEquals($eventStream->all(), [
            $appendedEventEnvelopeOne,
            $appendedEventEnvelopeTwo
        ]);

        $eventStream->commit($commitIdTwo);

        $this->assertEquals($eventStream->all(), [
            $appendedEventEnvelopeOne,
            $appendedEventEnvelopeTwo
        ]);
    }
}
