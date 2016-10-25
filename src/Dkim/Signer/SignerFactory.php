<?php

namespace Dkim\Signer;

use Zend\ServiceManager\Factory\FactoryInterface;

class SignerFactory implements FactoryInterface
{

    public function __invoke(\Interop\Container\ContainerInterface $container, $requestedName, array $options = null)
    {
        $signer = new Signer($container->get('Config'));
        return $signer;
    }

}
