<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

namespace Key\Middleware;


use Closure;
use Key\Http\Environment;
use Key\Util\Tools;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Key\Abstracts\Middleware;
use Key\Exception\AppException;
use Key\Constants;

/**
 * Class Home
 * @package Key\Middleware
 * @deprecated
 */
class MicroService extends Middleware
{

    /**
     * @param string $name Header name
     * @param ServerRequestInterface $request
     * @return null
     */
    protected function getOneHeader($name, $request)
    {
        if ($header = $request->getHeader($name)) {
            return $header[0];
        }

        return null;
    }

    /**
     * Home invoke.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param Closure $next
     * @return \Psr\Http\Message\MessageInterface|ResponseInterface
     * @throws AppException
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, Closure $next)
    {
        global $CONFIG;

        $currentApp = $request->getAttribute('currentApp');
        $currentSvc = $request->getAttribute('currentSvc');

        $skips= isset($CONFIG->skipAppVerify) && $CONFIG->skipAppVerify ? $CONFIG->skipAppVerify : array();

        if ($currentApp !== $CONFIG->defaultApp && !in_array($currentSvc, $skips)) {
            $env = new Environment($_SERVER);
            $host = $env->get('HTTP_HOST');

            $scheme = (isset($_SERVER['HTTPS']) ? 'https' : 'http');

            Tools::log(sprintf('[MicroService] %s://%s', $scheme, $host));

            $currentSvc = null;
            foreach ($CONFIG->remoteServices as $key => $svc) {
                if (strpos($svc, $scheme . '://' . $host) === 0) {
                    $currentSvc = $key;
                    break;
                }
            }

            $service = null;
            $source = null;
            $salt = null;
            $token = null;

            $msg = null;
            if ($currentSvc) {
                // check headers
                $service = $this->getOneHeader('Talentyun-Service', $request);
                if ($service && $service == $currentSvc) {
                    $source = $this->getOneHeader('Talentyun-Source', $request);
                    $salt = $this->getOneHeader('Talentyun-Salt', $request);
                    $token = $this->getOneHeader('Talentyun-Token', $request);

                    //error_log('###########'.$service.'>>>'.$source.'####'.$salt.'$$$$'.$token);
                    if ($source) {
                        if (isset($CONFIG->appIds[$source])) {
                            if ($token && $token == sha1($CONFIG->appIds[$service] . $CONFIG->appIds[$source] . $salt)) {

                                // Admin access
                                if ($source === 'admin') {
                                    /** @var \Key\Middleware\Session $session */
                                    $session = isset($CONFIG->session) && $CONFIG->session ? $CONFIG->session : null;
                                    if ($session) {
                                        if (method_exists($request, 'getParam')) {
                                            $aid = $request->getParam('aid', -1);
                                        } else {
                                            $parsedBody = $request->getParsedBody();
                                            $aid = $parsedBody && isset($parsedBody['aid']) ? (int) $parsedBody['aid'] : -1;
                                        }

                                        $session->set(Constants::SESSION_USR_KEY, array(
                                            'aid' => $aid,
                                            'id' => -1,
                                            'username' => '__ADMIN__',
                                            'real_name' => '__ADMIN__'
                                        ));
                                        $session->set(Constants::SESSION_ACCOUNT_KEY, array(
                                            'id' => $aid
                                        ));
                                    } else {
                                        Tools::log('[MicroService] Admin mode, session not init');
                                    }
                                }

                                return $next($request, $response);
                            } else {
                                // Invalid token
                                $msg = 'Invalid token';
                            }
                        } else {
                            $msg = 'Invalid source service';
                        }
                    } else {
                        $msg = 'Source service not found';
                    }

                } else {
                    $msg = 'Invalid request service';
                }
            } else {
                $msg = 'Micro service not found';
            }

            Tools::log('[MicroService] Server: ' . var_export($_SERVER, true));
            Tools::log('[MicroService] currentSvc: ' . $currentSvc);
            Tools::log('[MicroService] currentApp: ' . $currentApp);
            Tools::log('[MicroService] service: ' . (isset($service) ? $service : null));
            Tools::log('[MicroService] source:' . (isset($source) ? $source : null));
            Tools::log('[MicroService] salt:' . (isset($salt) ? $salt : null));
            Tools::log('[MicroService] token:' . (isset($token) ? $token : null));

            throw new AppException($msg);
        }


        return $next($request, $response);
    }

}