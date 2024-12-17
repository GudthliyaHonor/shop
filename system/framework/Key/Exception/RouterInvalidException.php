<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2022 yidianzhishi.com
 * @version 0.1.0
 * @link https://www.yidianzhishi.com
 */
namespace Key\Exception;


use Key\Constants;

/**
 * Class RouterInvalidException
 * @package Key\Exception
 */
class RouterInvalidException extends AppException
{
    /**
     * @param \Key\Container $container
     */
    public function __construct($container)
    {
        $request = $container['request'];
        parent::__construct($container['translator']->get('system:invalid:path'), Constants::SYS_ROUTE_INVALID_PATH, null);
        $container['logger']->error(sprintf('Route Not Found: %s %s', $request->getMethod(), $request->getUri()));
        // $container['logger']->error(sprintf('Request uri: %s', $uri));
        // $this->container['logger']->error(sprintf('Request path: %s', $originalPath));
        $container['logger']->error(sprintf('Request ip: %s', get_request_ip()));
    }
}