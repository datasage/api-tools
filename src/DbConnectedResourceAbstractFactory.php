<?php

declare(strict_types=1);

namespace Laminas\ApiTools;

use Laminas\ApiTools\Rest\Resource;
use Laminas\Db\TableGateway\TableGatewayInterface;
use Laminas\Paginator\Paginator;
use Laminas\ServiceManager\AbstractFactoryInterface;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Psr\Container\ContainerInterface;

use function class_exists;
use function is_array;
use function is_subclass_of;
use function sprintf;

class DbConnectedResourceAbstractFactory implements AbstractFactoryInterface
{
    /**
     * Can this factory create the requested service?
     *
     * @param string $requestedName
     * @return bool
     */
    public function canCreate(ContainerInterface $container, $requestedName)
    {
        if (! $container->has('config')) {
            return false;
        }

        $config = $container->get('config');

        if (! isset($config['api-tools']['db-connected'])) {
            return false;
        }

        $config = $config['api-tools']['db-connected'];

        if (
            ! isset($config[$requestedName])
            || ! is_array($config[$requestedName])
            || ! $this->isValidConfig($config[$requestedName], $requestedName, $container)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Can this factory create the requested service? (v2)
     *
     * Provided for backwards compatiblity; proxies to canCreate().
     *
     * @param string $name
     * @param string $requestedName
     * @return bool
     */
    public function canCreateServiceWithName(ServiceLocatorInterface $container, $name, $requestedName)
    {
        return $this->canCreate($container, $requestedName);
    }

    /**
     * Create and return the database-connected resource.
     *
     * @param string $requestedName
     * @param null|array $options
     * @return Resource
     */
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $config        = $container->get('config');
        $config        = $config['api-tools']['db-connected'][$requestedName];
        $table         = $this->getTableGatewayFromConfig($config, $requestedName, $container);
        $identifier    = $this->getIdentifierFromConfig($config);
        $collection    = $this->getCollectionFromConfig($config, $requestedName);
        $resourceClass = $this->getResourceClassFromConfig($config, $requestedName);

        return new $resourceClass($table, $identifier, $collection);
    }

    /**
     * Create and return the database-connected resource (v2).
     *
     * Provided for backwards compatibility; proxies to __invoke().
     *
     * @param string $name
     * @param string $requestedName
     * @return Resource
     */
    public function createServiceWithName(ServiceLocatorInterface $container, $name, $requestedName)
    {
        return $this($container, $requestedName);
    }

    /**
     * Tests if the configuration is valid
     *
     * If the configuration has a "table_service" key, and that service exists,
     * then the configuration is valid.
     *
     * Otherwise, it checks if the service $requestedName\Table exists.
     *
     * @param  string $requestedName
     * @return bool
     */
    protected function isValidConfig(array $config, $requestedName, ContainerInterface $container)
    {
        if (isset($config['table_service'])) {
            return $container->has($config['table_service']);
        }

        $tableGatewayService = $requestedName . '\Table';
        return $container->has($tableGatewayService);
    }

    /**
     * Retrieve a table gateway instance based on provided configuration.
     *
     * @param string $requestedName
     * @return TableGatewayInterface
     */
    protected function getTableGatewayFromConfig(array $config, $requestedName, ContainerInterface $container)
    {
        if (isset($config['table_service'])) {
            return $container->get($config['table_service']);
        }

        $tableGatewayService = $requestedName . '\Table';
        return $container->get($tableGatewayService);
    }

    /**
     * Retrieve the table identifier field from the provided configuration.
     *
     * Defaults to 'id' if none is found.
     *
     * @return string
     */
    protected function getIdentifierFromConfig(array $config)
    {
        if (isset($config['entity_identifier_name'])) {
            return $config['entity_identifier_name'];
        }

        // Deprecated; for pre-0.8.1 code only.
        if (isset($config['identifier_name'])) {
            return $config['identifier_name'];
        }

        if (isset($config['table_name'])) {
            return $config['table_name'] . '_id';
        }

        return 'id';
    }

    /**
     * Retrieve the collection class based on the provided configuration.
     *
     * Defaults to Laminas\Paginator\Paginator.
     *
     * @param string $requestedName
     * @return string
     * @throws ServiceNotCreatedException If the discovered collection class does not exist.
     */
    protected function getCollectionFromConfig(array $config, $requestedName)
    {
        $collection = $config['collection_class'] ?? Paginator::class;
        if (! class_exists($collection)) {
            throw new ServiceNotCreatedException(sprintf(
                'Unable to create instance for service "%s"; collection class "%s" cannot be found',
                $requestedName,
                $collection
            ));
        }
        return $collection;
    }

    /**
     * Retrieve the resource class based on the provided configuration.
     *
     * Defaults to Laminas\ApiTools\DbConnectedResource.
     *
     * @param string $requestedName
     * @return string
     * @throws ServiceNotCreatedException If the discovered resource class
     *     does not exist or is not a subclass of DbConnectedResource.
     */
    protected function getResourceClassFromConfig(array $config, $requestedName)
    {
        $defaultClass  = DbConnectedResource::class;
        $resourceClass = $config['resource_class'] ?? $defaultClass;
        if (
            $resourceClass !== $defaultClass
            && (! class_exists($resourceClass) || ! is_subclass_of($resourceClass, $defaultClass))
        ) {
            throw new ServiceNotCreatedException(sprintf(
                'Unable to create instance for service "%s"; resource class "%s" cannot be found or does not extend %s',
                $requestedName,
                $resourceClass,
                $defaultClass
            ));
        }
        return $resourceClass;
    }
}
