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


use Pimple\Container;
use Key\Interfaces\ShouldQueue;

class Dispatcher
{
    /** @var Container  */
    protected $container;

    /**
     * The registered event listeners.
     *
     * @var array
     */
    protected $listeners = [];

    /**
     * The queue resolver instance.
     *
     * @var callable
     */
    protected $queueResolver;

    /**
     * Dispatcher constructor.
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container  = $container ?: new Container();
    }

    /**
     * Get all of the listeners for the given event name.
     *
     * @param string $eventName
     * @return array
     */
    public function getListeners($eventName)
    {
        $listeners = isset($this->listeners[$eventName]) ? $this->listeners[$eventName] : [];
        return $listeners;
    }

    public function listen($events, $listener)
    {
        foreach((array) $events as $event) {
            $this->listeners[$event][] = $this->makeListener($listener);
        }
    }

    protected function makeListener($listener)
    {
        if (is_string($listener)) {
            // TODO: create listener class from string

        } else {
            return function ($event, $payload) use($listener) {
                return $listener(...array_values($payload));
            };
        }
    }

    protected function createClassListener($listener)
    {
        return function ($event, $payload) use ($listener) {
            return call_user_func_array(
                $this->createClassCallable($listener), $payload
            );
        };
    }

    protected function createClassCallable($listener)
    {
        list($class, $method) = $this->parseClassCallable($listener);

        if ($this->handlerShouldBeQueued($class)) {
            return $this->createQueuedHandlerCallable($class, $method);
        } else {
            return [$this->container->make($class), $method];
        }
    }

    /**
     * Parsing Class@Method string.
     *
     * @param $listener
     */
    protected function parseClassCallable($listener)
    {

    }

    /**
     * Determine if the event handler class should be queued.
     *
     * @param string $class
     * @return bool
     */
    protected function handlerShouldBeQueued($class)
    {
        try {
            return (new \ReflectionClass($class))->implementsInterface(
                ShouldQueue::class
            );
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Returns
     */
    protected function createQueuedHandlerCallable()
    {

    }

    /**
     * Set the queue resolver implementation.
     *
     * @param callable $resolver
     * @return $this
     */
    public function setQueueResolver(callable $resolver)
    {
        $this->queueResolver = $resolver;

        return $this;
    }

    /**
     * Fire an event and call the listeners.
     *
     * @param string $event
     * @param array $payload
     * @param bool $halt
     * @return array|null
     */
    public function dispatch($event, $payload = [], $halt = false)
    {
        $listeners = $this->getListeners($event);

        $responses = [];

        foreach($listeners as $listener) {
            $response = $listener($event, [$payload]);

            // If a response is returned from the listener and event halting is enabled
            // we will just return this response, and not call the rest of the event listeners.
            // Otherwise we will add the response on the response list.
            if ($halt && !is_null($response)) {
                return $response;
            }

            if ($response === false) {
                break;
            }

            $responses[] = $response;
        }

        return $halt ? null : $responses;
    }

    public function fire($event, $payload = [])
    {
        return $this->dispatch($event, $payload);
    }
}