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


use RuntimeException;
use Exception;
use UnexpectedValueException;
use SplStack;
use SplDoublyLinkedList;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Key\Exception\AppException;
use Key\Handlers\Error;
use Key\Handlers\NotFound;
use Key\Handlers\ServiceUnavailable;
use Key\Http\Response;

/**
 * Primary class with which you instantiate,
 * configure, and run a Framework application.
 *
 * @package Key
 * @author Guanghui Li <liguanghui2006@163.com>
 */
class App
{
    const VERSION = '0.1.0';

    /**
     * @var string
     */
    protected $currentApp;

    /**
     * @var SplStack
     */
    protected $stack;

    /**
     * Error handlers.
     *
     * @var array
     */
    protected $handlers = array();

    /**
     * Default output content type.
     *
     * @var string
     */
    protected $outputContentType = 'application/json;charset=UTF-8';

    /**
     * App start time.
     *
     * @var int
     */
    protected $appStartTime = 0;

    /** @var \Pimple\Container */
    protected $container;

    /**
     * App construct.
     *
     */
    public function __construct($basepath = null)
    {

        // set_error_handler([&$this, 'handler']);
        if (is_null($basepath)) {
            $basepath = dirname(dirname(dirname(dirname(__FILE__))));
        }

        $this->appStart();

        $this->handlers['errorHandler'] = function() {
            return new Error;
        };
        $this->handlers['notFoundHandler'] = function() {
            return new NotFound;
        };
        $this->handlers['serviceUnavailableHandler'] = function() {
            return new ServiceUnavailable;
        };

        try {
            $this->container = new Container([]);
            $provider = new ServiceProvider();
            $provider->register($this->container);

            $this->container['basepath'] = $basepath;
            $this->container['request_id'] = create_uuid();

            // Start the session
            // $this->container['session']->start();
        } catch (Exception $ex) {
            //throw new AppException('code: ' . $ex->getCode() . ' Message: ' . $ex->getMessage());
            $this->handleException($ex, $this->container['request'], $this->container['response']);
        }

        register_shutdown_function(array($this, 'appShutdown'));
    }

    /**
     * Get app container;
     * @return Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Init the stack.
     *
     * @param callable $kernel
     * @throws RuntimeException
     */
    protected function seedMiddlewareStack($kernel = null)
    {
        if (!is_null($this->stack)) {
            throw new RuntimeException('MiddlewareStack can only be seeded once.');
        }
        if ($kernel === null) {
            if (PHP_SAPI === 'cli' || defined('STDIN')) {
                // error_log('[seedMiddlewareStack] CLI mode');
                $kernel = function(\Pimple\Container $container) {
                    // error_log('-----------------------------');
                    // // $db = $container['db'];
                    // // error_log('1111############' . var_export($db, true));
                    // $g = $container['db']['global'];
                    // error_log('222############' . var_export($g, true));
                    // $row = $g->fetchRow('test', []);
                    // error_log('333############' . var_export($row, true));

                    // $g1 = $container['db']['global'];
                    // $row1 = $g1->fetchRow('test', ['aid' => 1000]);
                    // error_log('333############' . var_export($row1, true));

                    return $container['response'];
                };
            } else {
                $kernel = $this;
            }
        }
        $this->stack = new SplStack;
        $this->stack->setIteratorMode(SplDoublyLinkedList::IT_MODE_LIFO | SplDoublyLinkedList::IT_MODE_KEEP);
        $this->stack[] = $kernel;
    }

    /**
     * Add a middleware to the stack.
     *
     * @param callable $callable
     * @return self
     * @throws RuntimeException
     */
    public function addMiddlewareStack($callable)
    {

        if (is_null($this->stack)) {
            $this->seedMiddlewareStack();
        }
        if (is_string($callable)) {
            $callable = new $callable();
        }
        $next = $this->stack->top();
        $this->stack[] = function (\Pimple\Container $container) use ($callable, $next) {
            //error_log(sprintf('[App] >>>>>>>>>>>>>Middleware %s invoke<<<<<<<<<<<<<<<', get_class($callable)));
            $result = call_user_func($callable, $container, $next);
            if ($result instanceof ResponseInterface === false) {
                throw new UnexpectedValueException(
                    'Middleware must return instance of \Psr\Http\Message\ResponseInterface'
                );
            }

            return $result;
        };

        return $this;
    }

    /**
     * Run the app.
     *
     * @param bool|false $silent if true, just return response object
     * @return \Psr\Http\Message\ResponseInterface
     * @throws Exception
     */
    public function run($silent = false)
    {
        $request = $this->container['request'];
        $response = $this->container['response'];

        try {
            $response = $this->process();
        } catch (Exception $ex) {
            // if ($ex->getCode() != Constants::SYS_REQ_AUTH) {
            //     $this->container['logger']->error('[run] Exception ' . $ex->getCode() . ' ' . $ex->getMessage() . PHP_EOL . $ex->getTraceAsString());
            // } else {
            //     $this->container['logger']->error('[run] Exception ' . $ex->getCode() . ' ' . $ex->getMessage());
            // }
            $this->logToFile($ex);
            $response = $this->handleException($ex, $request, $response);
        }

        if (!$silent) {
            $this->respond($response);
        }

        return $response;
    }

    /**
     * Process the request.
     *
     * @return ResponseInterface
     * @throws \Key\Exception\AppException
     * @throws \Key\Exception\SessionException
     */
    protected function process()
    {

        $response = $this->callMiddlewareStack();

        return $response;
    }

    /**
     * Call the middleware of the stack.
     *
     * @return ResponseInterface
     */
    protected function callMiddlewareStack()
    {
        if (is_null($this->stack)) {
            $this->seedMiddlewareStack();
        }

        $this->stack->rewind();

        /** @var \Closure $start */
        $start = $this->stack->top();
        $resp = $start($this->container);

        return $resp;
    }

    /**
     *  App invoke.
     *
     * @param \Pimple\Container $container
     * @return mixed
     */
    public function __invoke(\Pimple\Container $container, $next = null)
    {
        //error_log('[App] ############## App invoke ##############');
        // TODO:...
        return $container['response'];
    }

    /**
     * Respond the request.
     *
     * @param Response $response
     */
    protected function respond(Response $response)
    {
        $encrypt = 0;
        /** @var \Key\Http\Request $request */
        $request = $this->container['request'];
        $clientVersion = $request->getHeader('App-Version');
        if ($clientVersion) {
            $clientVersion = $clientVersion[0];
            if (version_compare($clientVersion, env('RESPONSE_ENCRYPT_MIN_VERSION', '3.4.3')) >= 0) {
                $encrypt = 1;
            }
        }

        if (!headers_sent()) {
            // Status
            header(sprintf(
                'HTTP/%s %s %s',
                $response->getProtocolVersion(),
                $response->getStatusCode(),
                $response->getReasonPhrase()
            ));

            // Headers
            foreach ($response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    header(sprintf('%s: %s', $name, $value), false);
                }
            }
            if ($encrypt) {
                header('Content-Type: text/plain');
            }
            //header('strict-transport-security: max-age=16070400; includeSubDomains');
            //header('x-frame-options: SAMEORIGIN');
        } else {
            //error_log('[WARNING] headers have been sent already!');
        }

        $body = $response->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        $contents = $body->getContents();

        // Encrypt
        if ($encrypt) {
            exit(base64_encode($contents));
        }

        exit($contents);

    }

    /**
     * @param \Exception $ex
     */
    protected function logToFile($ex)
    {
        $request = $this->container['request'];
        if ($ex->getCode() != Constants::SYS_REQ_AUTH && $ex->getCode() != Constants::SYS_PERMISSION_FAULT) {
            $this->container['logger']->error('[App] Exception ' . $ex->getCode() . ' ' . $request->getUri() . ' ' . $ex->getMessage() . PHP_EOL . $ex->getTraceAsString());
        } else {
            $this->container['logger']->error('[App] Exception ' . $ex->getCode() . ' ' . $request->getUri() . ' ' . $ex->getMessage());
        }
    }

    /**
     * Handle the exception.
     *
     * @param Exception $ex
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return mixed
     * @throws Exception
     */
    protected function handleException(Exception $ex, ServerRequestInterface $request, ResponseInterface $response)
    {
        $params = array($request, $response, $ex);

        $className = get_class($ex);
        switch($className) {
            case 'Key\Exception\NotFoundException':
            case 'Key\Exception\RouterInvalidException':
                $handler = 'notFoundHandler';
                break;
            case 'Key\Exception\ServiceUnavailableException':
                $handler = 'serviceUnavailableHandler';
                break;
            default:
                $handler = 'errorHandler';
        }

        if (isset($this->handlers[$handler])) {
            $callable = $this->handlers[$handler];

            // call the registered handler
            return call_user_func_array($callable(), $params);
        }

        // No handlers found, so just throw the exception
        throw $ex;
    }

    /**
     * App start handler
     */
    public function appStart()
    {
        //$this->container['logger']->info('[App] start ################################'/*.date('T Y-m-d H:i:s')*/);
        //error_log('[process] Request time: '. date('Y-m-d h:i:s', $_SERVER['REQUEST_TIME']));
        //Tools::log('[App] Request URI: ' . $_SERVER['REQUEST_URI']);
        $this->appStartTime = microtime(true);
    }

    /**
     * App shutdown handler
     */
    public function appShutdown()
    {
        $this->handlerFatalError();

        $end = microtime(true);
        $elapsed = $end - $this->appStartTime;
        if ($elapsed > (float) env('APP_LOG_ELAPSED_MIN', 0.1)) {
            $this->container['logger']->info(sprintf('%s - %s %s Elapsed: %s',
            isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '-',
            isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD']: '-',
            isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '-',
            $elapsed));
        }
        //Tools::log('[App] end ################################'/*.date('T Y-m-d H:i:s')*/);
    }

    /**
     * Fatal error handler, such as 500 internal server error.
     */
    protected function handlerFatalError() {
        $_error = error_get_last();
        if ($_error && in_array($_error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR, E_ALL))) {
            $this->container['logger']->error(sprintf('*** Fatal error: %s in the file %s Line %s.', $_error['message'], $_error['file'], $_error['line']));
            $this->container['logger']->error(sprintf('*** Request: %s %s', $_SERVER['REQUEST_METHOD'], ($_SERVER["REQUEST_SCHEME"] ?: 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '')));

            if (!headers_sent()) {
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Allow-Methods: *');
                header('Access-Control-Max-Age: 60');
                header('Access-Control-Allow-Headers: *');

                header(sprintf('HTTP/%s %s %s', '1.1', '500', 'Internal Server Error'));
                $errorPage = env('PAGE_ERROR_500');
                if ($errorPage) {
                    header('Location: ' . $errorPage, false);
                } else {
                    echo sprintf('500 Internal Server Error(%s)', $_error['type']);
                }
            } else {
                error_log('[handlerFatalError] headers sent!');
            }
        }
    }

    public static function errorHandler($errno, $errstr, $errfile, $errline)
    {
        if (!(error_reporting() & $errno)) {
            // This error code is not included in error_reporting
            return;
        }

        switch ($errno) {
            case E_USER_ERROR:
                error_log('[E_USER_ERROR] Error No: ' . $errno . ' ' . $errstr . ' in ' . $errfile . ' at line ' . $errline);
                exit(1);
                break;
            case E_USER_WARNING:
                error_log('[E_USER_WARNING] Error No: ' . $errno . ' ' . $errstr . ' in ' . $errfile . ' at line ' . $errline);
                break;
            case E_USER_NOTICE:
                if (env('APP_ENV') == 'development') {
                    error_log('[E_USER_NOTICE] Error No: ' . $errno . ' ' . $errstr . ' in ' . $errfile . ' at line ' . $errline);
                }
                break;
            default:
                if (env('APP_ENV') == 'development') {
                    error_log('[Unknown error type] Error No: ' . $errno . ' ' . $errstr . ' in ' . $errfile . ' at line ' . $errline);
                }
        }
        return true;
    }

}
