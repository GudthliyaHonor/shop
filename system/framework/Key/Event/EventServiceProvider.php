<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.2.0
 * @link http://www.keylogic.com
 */
namespace Key\Event;


use Key\Queue\QueueManager;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class EventServiceProvider implements ServiceProviderInterface
{

    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $pimple A container instance
     */
    public function register(Container $pimple)
    {
        $pimple['events'] = function () use($pimple) {
            return (new Dispatcher($pimple))->setQueueResolver(function() use ($pimple) {
                return new QueueManager($pimple);
            });
        };
    }

    public function boot()
    {
        error_log('#################EventServiceProvider boot called');
    }
}