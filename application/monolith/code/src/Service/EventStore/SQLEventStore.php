<?php

declare(strict_types=1);

namespace Galeas\Api\Service\EventStore;

use Doctrine\DBAL\Connection;
use Galeas\Api\Common\Aggregate\Aggregate;
use Galeas\Api\Common\Event\AggregateFromEvents;
use Galeas\Api\Common\Event\Event;
use Galeas\Api\Common\Event\EventDeserializer;
use Galeas\Api\Common\Event\EventSerializer;
use Galeas\Api\Common\Event\SerializedEvent;
use Galeas\Api\Common\ExceptionBase\EventStoreCannotRead;
use Galeas\Api\Common\ExceptionBase\EventStoreCannotWrite;
use Galeas\Api\Service\EventStore\Exception\CancellingTransactionRequiresActiveTransaction;
use Galeas\Api\Service\EventStore\Exception\CompletingTransactionRequiresActiveTransaction;
use Galeas\Api\Service\EventStore\Exception\FindingAggregateRequiresActiveTransaction;
use Galeas\Api\Service\EventStore\Exception\SavingEventRequiresActiveTransaction;
use Galeas\Api\Service\EventStore\Exception\TransactionIsAlreadyActive;

class SQLEventStore implements EventStore
{
    private Connection $connection;

    public function __construct(SQLEventStoreConnection $SQLEventStoreConnection)
    {
        $this->connection = $SQLEventStoreConnection->getConnection();
    }

    public function beginTransaction(): void
    {
        try {
            if ($this->connection->isTransactionActive()) {
                throw new TransactionIsAlreadyActive();
            }

            $this->connection->beginTransaction();
        } catch (\Throwable $exception) {
            throw new EventStoreCannotWrite($exception);
        }
    }

    public function completeTransaction(): void
    {
        try {
            if (false === $this->connection->isTransactionActive()) {
                throw new CompletingTransactionRequiresActiveTransaction();
            }

            $this->connection->commit();
        } catch (\Throwable $exception) {
            throw new EventStoreCannotWrite($exception);
        }
    }

    public function cancelTransaction(): void
    {
        try {
            if (false === $this->connection->isTransactionActive()) {
                throw new CancellingTransactionRequiresActiveTransaction();
            }

            $this->connection->rollBack();
        } catch (\Throwable $exception) {
            throw new EventStoreCannotWrite($exception);
        }
    }

    public function find(string $aggregateId): ?Aggregate
    {
        try {
            if (false === $this->connection->isTransactionActive()) {
                throw new FindingAggregateRequiresActiveTransaction();
            }

            $statement = $this->connection->prepare('SELECT * FROM `event` WHERE `aggregate_id` = ? FOR UPDATE');
            $statement->bindValue(1, $aggregateId);

            $eventArrays = $statement->executeQuery()->fetchAllAssociative();
            if (0 === count($eventArrays)) {
                return null;
            }

            $aggregateEvents = array_map(function (array $eventArray) {
                return SerializedEvent::fromProperties(
                    $eventArray['event_id'],
                    $eventArray['aggregate_id'],
                    $eventArray['authorizer_id'],
                    $eventArray['causation_id'],
                    $eventArray['correlation_id'],
                    $eventArray['recorded_on'],
                    $eventArray['event_name'],
                    $eventArray['json_payload'],
                    $eventArray['json_metadata']
                );
            }, $eventArrays);

            $creationEvent = array_shift($aggregateEvents);
            $transformationEvents = $aggregateEvents;

            return AggregateFromEvents::aggregateFromEvents(
                EventDeserializer::serializedEventsToEvents([$creationEvent])[0],
                EventDeserializer::serializedEventsToEvents($transformationEvents)
            );
        } catch (\Throwable $exception) {
            throw new EventStoreCannotRead($exception);
        }
    }

    public function findEvent(string $eventId): ?Event
    {
        try {
            if (false === $this->connection->isTransactionActive()) {
                throw new FindingAggregateRequiresActiveTransaction();
            }

            $statement = $this->connection->prepare('SELECT * FROM `event` WHERE `event_id` = ? FOR UPDATE');
            $statement->bindValue(1, $eventId);

            $eventArray = $statement->executeQuery()->fetchAssociative();
            if (false === $eventArray || null === $eventArray) {
                return null;
            }

            $serializedEvent = SerializedEvent::fromProperties(
                $eventArray['event_id'],
                $eventArray['aggregate_id'],
                $eventArray['authorizer_id'],
                $eventArray['causation_id'],
                $eventArray['correlation_id'],
                $eventArray['recorded_on'],
                $eventArray['event_name'],
                $eventArray['json_payload'],
                $eventArray['json_metadata']
            );

            return EventDeserializer::serializedEventsToEvents([$serializedEvent])[0];
        } catch (\Throwable $exception) {
            throw new EventStoreCannotRead($exception);
        }
    }

    public function save(Event $event): void
    {
        try {
            if (false === $this->connection->isTransactionActive()) {
                throw new SavingEventRequiresActiveTransaction();
            }

            $serializedEvent = EventSerializer::eventsToSerializedEvents([$event])[0];

            $this->connection->insert('event',
                [
                    'event_id' => $serializedEvent->eventId(),
                    'aggregate_id' => $serializedEvent->aggregateId(),
                    'aggregate_version' => $serializedEvent->aggregateVersion(),
                    'causation_id' => $serializedEvent->causationId(),
                    'correlation_id' => $serializedEvent->correlationId(),
                    'recorded_on' => $serializedEvent->recordedOn(),
                    'event_name' => $serializedEvent->eventName(),
                    'json_payload' => $serializedEvent->jsonPayload(),
                    'json_metadata' => $serializedEvent->jsonMetadata(),
                ],
                [
                    'event_id' => \PDO::PARAM_STR,
                    'aggregate_id' => \PDO::PARAM_STR,
                    'aggregate_version' => \PDO::PARAM_INT,
                    'causation_id' => \PDO::PARAM_STR,
                    'correlation_id' => \PDO::PARAM_STR,
                    'recorded_on' => \PDO::PARAM_STR,
                    'event_name' => \PDO::PARAM_STR,
                    'json_payload' => \PDO::PARAM_LOB,
                    'json_metadata' => \PDO::PARAM_LOB,
                ]
            );
        } catch (\Throwable $exception) {
            throw new EventStoreCannotWrite($exception);
        }
    }
}
