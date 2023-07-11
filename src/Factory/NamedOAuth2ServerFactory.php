<?php

declare(strict_types=1);

namespace Laminas\ApiTools\MvcAuth\Factory;

use Laminas\ApiTools\OAuth2\Factory\OAuth2ServerInstanceFactory;
use Psr\Container\ContainerInterface;

/**
 * Override factory for the Laminas\ApiTools\OAuth2\Service\OAuth2Server service.
 *
 * This factory returns a factory that will allow retrieving a named
 * OAuth2\Server instance. It delegates to
 * Laminas\ApiTools\OAuth2\Factory\OAuth2ServerInstanceFactory after first marshaling the
 * correct configuration from api-tools-mvc-auth.authentication.adapters.
 */
class NamedOAuth2ServerFactory
{
    /**
     * @return callable
     */
    public function __invoke(ContainerInterface $container)
    {
        $config = $container->get('config');

        $oauth2Config  = $config['api-tools-oauth2'] ?? [];
        $mvcAuthConfig = $config['api-tools-mvc-auth']['authentication']['adapters'] ?? [];

        $servers = (object) ['application' => null, 'api' => []];
        return function ($type = null) use ($oauth2Config, $mvcAuthConfig, $container, $servers) {
            // Empty type == legacy configuration.
            if (empty($type)) {
                if ($servers->application) {
                    return $servers->application;
                }
                $factory                     = new OAuth2ServerInstanceFactory($oauth2Config, $container);
                return $servers->application = $factory();
            }

            if (isset($servers->api[$type])) {
                return $servers->api[$type];
            }

            foreach ($mvcAuthConfig as $name => $adapterConfig) {
                if (! isset($adapterConfig['storage']['route'])) {
                    // Not a api-tools-oauth2 config
                    continue;
                }

                if ($type !== $adapterConfig['storage']['route']) {
                    continue;
                }

                // Found!
                return $servers->api[$type] = OAuth2ServerFactory::factory(
                    $adapterConfig['storage'],
                    $container
                );
            }

            // At this point, a $type was specified, but no matching adapter
            // was found. Attempt to pull a global OAuth2 instance; if none is
            // present, this will raise an exception anyways.
            if ($servers->application) {
                return $servers->application;
            }
            $factory                     = new OAuth2ServerInstanceFactory($oauth2Config, $container);
            return $servers->application = $factory();
        };
    }
}
