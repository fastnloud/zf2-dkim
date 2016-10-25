<?php

namespace Dkim\Signer;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class SignerFactory implements FactoryInterface
{

    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $signer = new Signer($serviceLocator->get('Config'));
        return $signer;
    }
    
    public function __invoke(\Interop\Container\ContainerInterface $container, $requestedName, array $options = null)
    {
        $signer = new Signer($container->get('Config'));
        return $signer;
    }

}
