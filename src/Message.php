<?php

namespace RabbitClient;


use MessageBroker\MessageInterface;
use PhpAmqpLib\Message\AMQPMessage;

class Message implements MessageInterface
{
    /**
     * @var AMQPMessage
     */
    private $message;

    /**
     * @param AMQPMessage $message
     */
    public function __construct(AMQPMessage $message)
    {
        $this->message = $message;
    }

    /**
     * @param  string $data
     * @param  array  $properties
     * @return MessageInterface
     */
    public static function createFromData(string $data, array $properties = []): MessageInterface
    {
        $message = new AMQPMessage($data, $properties);
        return new AmqpMessage($message);
    }

    /**
     * @param  mixed $data
     * @return void
     */
    public function confirm($data = null): void
    {
        $this->message->ack();
    }

    /**
     * @return string
     */
    public function getData(): string
    {
        return $this->message->getBody();
    }

    public function getOriginal(): AMQPMessage
    {
        return $this->message;
    }
}
