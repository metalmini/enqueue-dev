<?php

namespace Enqueue\Symfony\DependencyInjection;

use Enqueue\Client\DriverInterface;
use Enqueue\ConnectionFactoryFactoryInterface;
use Enqueue\Resources;
use Interop\Queue\PsrConnectionFactory;
use Interop\Queue\PsrContext;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
final class TransportFactory
{
    /**
     * @var string
     */
    private $name;

    public function __construct(string $name)
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('The name could not be empty.');
        }

        $this->name = $name;
    }

    public function addConfiguration(ArrayNodeDefinition $builder): void
    {
        $knownSchemes = array_keys(Resources::getKnownSchemes());
        $availableSchemes = array_keys(Resources::getAvailableSchemes());

        $builder
            ->info('The transport option could accept a string DSN, an array with DSN key, or null. It accept extra options. To find out what option you can set, look at connection factory constructor docblock.')
            ->beforeNormalization()
                ->always(function ($v) {
                    if (empty($v)) {
                        return ['dsn' => 'null:'];
                    }

                    if (is_array($v)) {
                        if (isset($v['factory_class']) && isset($v['factory_service'])) {
                            throw new \LogicException('Both options factory_class and factory_service are set. Please choose one.');
                        }

                        if (isset($v['connection_factory_class']) && (isset($v['factory_class']) || isset($v['factory_service']))) {
                            throw new \LogicException('The option connection_factory_class must not be used with factory_class or factory_service at the same time. Please choose one.');
                        }

                        return $v;
                    }

                    if (is_string($v)) {
                        return ['dsn' => $v];
                    }

                    throw new \LogicException(sprintf('The value must be array, null or string. Got "%s"', gettype($v)));
                })
        ->end()
        ->ignoreExtraKeys(false)
        ->children()
            ->scalarNode('dsn')
                ->cannotBeEmpty()
                ->isRequired()
                ->info(sprintf(
                    'The MQ broker DSN. These schemes are supported: "%s", to use these "%s" you have to install a package.',
                    implode('", "', $knownSchemes),
                    implode('", "', $availableSchemes)
                ))
            ->end()
            ->scalarNode('connection_factory_class')
                ->info(sprintf('The connection factory class should implement "%s" interface', PsrConnectionFactory::class))
            ->end()
            ->scalarNode('factory_service')
                ->info(sprintf('The factory class should implement "%s" interface', ConnectionFactoryFactoryInterface::class))
            ->end()
            ->scalarNode('factory_class')
                ->info(sprintf('The factory service should be a class that implements "%s" interface', ConnectionFactoryFactoryInterface::class))
            ->end()
        ->end()
        ;
    }

    public function createConnectionFactory(ContainerBuilder $container, array $config): string
    {
        $factoryFactoryId = 'enqueue.connection_factory_factory';
        $factoryId = sprintf('enqueue.transport.%s.connection_factory', $this->getName());

        if (array_key_exists('factory_class', $config)) {
            $factoryFactoryId = sprintf('enqueue.transport.%s.connection_factory_factory', $this->getName());

            $container->register($factoryFactoryId, $config['factory_class']);
        }

        $factoryFactoryService = new Reference(
            array_key_exists('factory_service', $config) ? $config['factory_service'] : $factoryFactoryId
        );

        unset($config['factory_service'], $config['factory_class']);

        if (array_key_exists('connection_factory_class', $config)) {
            $connectionFactoryClass = $config['connection_factory_class'];
            unset($config['connection_factory_class']);

            $container->register($factoryId, $connectionFactoryClass)
                ->addArgument($config)
            ;
        } else {
            $container->register($factoryId, PsrConnectionFactory::class)
                ->setFactory([$factoryFactoryService, 'create'])
                ->addArgument($config)
            ;
        }

        $container->setAlias('enqueue.transport.connection_factory', new Alias($factoryId, true));

        return $factoryId;
    }

    public function createContext(ContainerBuilder $container, array $config): string
    {
        $contextId = sprintf('enqueue.transport.%s.context', $this->getName());
        $factoryId = sprintf('enqueue.transport.%s.connection_factory', $this->getName());

        $container->register($contextId, PsrContext::class)
            ->setFactory([new Reference($factoryId), 'createContext'])
        ;

        $container->setAlias('enqueue.transport.context', new Alias($contextId, true));

        return $contextId;
    }

    public function createDriver(ContainerBuilder $container, array $config): string
    {
        $factoryId = sprintf('enqueue.transport.%s.connection_factory', $this->getName());
        $driverId = sprintf('enqueue.client.%s.driver', $this->getName());

        $container->register($driverId, DriverInterface::class)
            ->setFactory([new Reference('enqueue.client.driver_factory'), 'create'])
            ->addArgument(new Reference($factoryId))
            ->addArgument($config['dsn'])
            ->addArgument($config)
        ;

        $container->setAlias('enqueue.client.driver', new Alias($driverId, true));

        return $driverId;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
