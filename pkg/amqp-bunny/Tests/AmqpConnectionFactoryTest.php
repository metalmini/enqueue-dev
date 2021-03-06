<?php

namespace Enqueue\AmqpBunny\Tests;

use Enqueue\AmqpBunny\AmqpConnectionFactory;
use Enqueue\AmqpTools\RabbitMqDlxDelayStrategy;
use Enqueue\Test\ClassExtensionTrait;
use Interop\Queue\PsrConnectionFactory;
use PHPUnit\Framework\TestCase;

class AmqpConnectionFactoryTest extends TestCase
{
    use ClassExtensionTrait;

    public function testShouldImplementConnectionFactoryInterface()
    {
        $this->assertClassImplements(PsrConnectionFactory::class, AmqpConnectionFactory::class);
    }

    public function testShouldSetRabbitMqDlxDelayStrategyIfRabbitMqSchemeExtensionPresent()
    {
        $factory = new AmqpConnectionFactory('amqp+rabbitmq:');

        $this->assertAttributeInstanceOf(RabbitMqDlxDelayStrategy::class, 'delayStrategy', $factory);
    }
}
