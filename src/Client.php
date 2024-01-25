<?php

namespace RabbitClient;

use EmptyIterator;
use Exception;
use Iterator;
use MessageBroker\ClientInterface;
use MessageBroker\MessageInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

class Client implements ClientInterface
{
    private AbstractConnection $connection;
    private ?AMQPChannel $openChannel;

    public static function initByParams(
        string $host,
        int $port,
        string $user,
        string $password,
        string $vhost = ''
    ): Client {
        $connection = new AMQPConnection($host, $port, $user, $password, $vhost);
        return new static($connection);
    }

    public function __construct(AbstractConnection $connection)
    {
        $this->connection = $connection;
        $this->openChannel = null;
    }

    /**
     * @throws Exception
     */
    public function __destruct()
    {
        if (!empty($this->openChannel) && $this->channelIsOpen()) {
            $this->openChannel->close();
        }

        $this->connection->close();
    }

    /**
     * @inerhitDoc
     */
    public function getMessageIterator(string $exchangeKey, array $options = []): Iterator
    {
        $noAsk = isset($options['no_ask']) && $options['no_ask'] === true;
        $ticket = $options['ticket'] ?? null;
        $channel = $this->getChannel();
        if (empty($channel)) {
            return new EmptyIterator();
        }

        while ($message = $this->getMessageFromChannel($channel, $exchangeKey, $noAsk, $ticket)) {
            yield $message;
        }

        return new EmptyIterator();
    }

    public function getMessage(string $exchangeKey, array $options = []): ?MessageInterface
    {
        $noAsk = isset($options['no_ask']) && $options['no_ask'] === true;
        $ticket = $options['ticket'] ?? null;
        $channel = $this->getChannel();
        if (empty($channel)) {
            return null;
        }

        return $this->getMessageFromChannel($channel, $exchangeKey, $noAsk, $ticket);
    }

    private function getMessageFromChannel(
        AMQPChannel $channel,
        string $exchangeKey,
        bool $noAsk,
        ?string $ticket = null
    ): ?MessageInterface {
        $amqpMessage = $channel->basic_get($exchangeKey, $noAsk, $ticket);
        if (!($amqpMessage instanceof AMQPMessage)) {
            return null;
        }

        return new Message($amqpMessage);
    }

    private function getChannel(): ?AMQPChannel
    {
        if ($this->channelIsOpen()) {
            return $this->openChannel;
        }

        return $this->openChannel = $this->connection->channel();
    }

    private function channelIsOpen(): bool
    {
        return $this->openChannel instanceof AMQPChannel && $this->openChannel->is_open;
    }

    public function sendMessage(string $message, string $exchangeKey, array $options = []): void
    {
        $options['durable'] = true;
        $options['exchangeDurable'] = true;
        $channel = $this->connection->channel();
        $exchange = $options['exchange'] ?? null;
        if (!empty($exchange)) {
            $channel->exchange_declare(
                $exchange,
                ...$this->getExchangeOptions($options)
            );
        }

        $channel->queue_declare($exchangeKey, ...$this->getQueueOptions($options));
        $message = new AMQPMessage($message);

        $channel->basic_publish($message, $exchange ?? '', $exchangeKey);
    }

    private function getExchangeOptions(array $options): array
    {
        return [
            $options['exchangeType'] ?? 'direct',
            isset($options['exchangePassive']) && $options['exchangePassive'] === true,
            isset($options['exchangeDurable']) && $options['exchangeDurable'] === true,
            isset($options['exchangeAutoDelete']) && $options['exchangeAutoDelete'] === true,
            isset($options['exchangeInternal']) && $options['exchangeInternal'] === true,
            isset($options['exchangeNoWait']) && $options['exchangeNoWait'] === true,
            $options['exchangeArguments'] ?? [],
            $options['exchangeTicket'] ?? null
        ];
    }

    private function getQueueOptions(array $options): array
    {
        return [
            isset($options['passive']) && $options['passive'] === true,
            isset($options['durable']) && $options['durable'] === true,
            isset($options['exclusive']) && $options['exclusive'] === true,
            isset($options['auto_delete']) && $options['auto_delete'] === true,
            isset($options['nowait']) && $options['nowait'] === true
        ];
    }
}
