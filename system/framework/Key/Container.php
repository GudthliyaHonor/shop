<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

namespace Key;


use Pimple\ServiceProviderInterface;

class Container extends \Pimple\Container
{
    protected $serviceProviders = [];

    /**
     * Registers a service provider.
     *
     * @param ServiceProviderInterface $provider A ServiceProviderInterface instance
     * @param array $values An array of values that customizes the provider
     *
     * @return static
     */
    public function register(ServiceProviderInterface $provider, array $values = array())
    {
        if ($registered = $this->getProvider($provider)) {
            return $registered;
        }

        $this->markAsRegistered($provider);

        return parent::register($provider, $values);
    }

    /**
     * Mark as the provider as registered.
     *
     * @param \Pimple\ServiceProviderInterface $provider
     */
    public function markAsRegistered($provider)
    {
        $this->serviceProviders[] = $provider;
    }

    /**
     * Get the registered service provider.
     *
     * @param \Pimple\ServiceProviderInterface|string $provider
     * @return mixed|null
     */
    public function getProvider($provider)
    {
        $providers = $this->getProviders($provider);
        return $providers ? $providers[0] : null;
    }

    /**
     * Get the registered service providers.
     *
     * @param \Pimple\ServiceProviderInterface|string $provider
     * @return array
     */
    public function getProviders($provider)
    {
        $name = is_string($provider) ? $provider : get_class($provider);
        $found = [];
        foreach($this->serviceProviders as $serviceProvider) {
            if ($serviceProvider instanceof $name) {
                $found[] = $serviceProvider;
            }
        }
        return $found;
    }
}