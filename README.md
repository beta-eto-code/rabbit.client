# Клиент для работы с брокером сообщений RabbitMQ

## Установка

```shell
composer require beta/rabbit.client
```

## Consumer пример работы

```php
use RabbitClient\Client;

$client = Client::initByParams('127.0.0.1', 5672, 'testUser', 'somePassword', 'myVhost');
$message = $client->getMessage('my_topic', ['no_ask' => false]); // запрашиваем 1 сообщение из брокера
$message->getData(); // payload сообщения
$message->getOriginal(); // оригинальное сообщение AMQPMessage
$message->confirm(); // подтверждаем обработку сообщения

/**
* Перебираем новые сообщения из брокера
**/
foreach ($client->getMessageIterator('my_topic') as $message) {
    echo $message->getData();
    $message->confirm();
}
```

## Producer пример работы

```php
use RabbitClient\Client;

$client = Client::initByParams('127.0.0.1', 5672, 'testUser', 'somePassword', 'myVhost');
$client->sendMessage('Test message', 'my_topic');
```