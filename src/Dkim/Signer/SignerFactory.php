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

}