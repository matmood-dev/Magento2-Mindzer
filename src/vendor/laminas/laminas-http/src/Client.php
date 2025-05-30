<?php

namespace Laminas\Http;

use ArrayIterator;
use Laminas\Http\Client\Adapter\Curl;
use Laminas\Http\Client\Adapter\Socket;
use Laminas\Http\Client\Exception\RuntimeException;
use Laminas\Http\Header\SetCookie;
use Laminas\Stdlib\ArrayUtils;
use Laminas\Stdlib\DispatchableInterface;
use Laminas\Stdlib\ErrorHandler;
use Laminas\Stdlib\RequestInterface;
use Laminas\Stdlib\ResponseInterface;
use Laminas\Uri\Http;
use Traversable;

use function array_merge;
use function base64_encode;
use function basename;
use function class_exists;
use function defined;
use function explode;
use function fclose;
use function file_get_contents;
use function finfo_file;
use function finfo_open;
use function fopen;
use function fstat;
use function func_get_arg;
use function func_num_args;
use function function_exists;
use function http_build_query;
use function in_array;
use function ini_get;
use function is_array;
use function is_int;
use function is_resource;
use function is_string;
use function md5;
use function microtime;
use function mime_content_type;
use function preg_match;
use function preg_quote;
use function rewind;
use function rtrim;
use function sprintf;
use function str_replace;
use function stream_get_meta_data;
use function stripos;
use function strlen;
use function strpos;
use function strrpos;
use function strtolower;
use function strtoupper;
use function substr;
use function sys_get_temp_dir;
use function tempnam;
use function trim;

use const CURLAUTH_DIGEST;
use const CURLOPT_HTTPAUTH;
use const CURLOPT_USERPWD;
use const FILEINFO_MIME;

/**
 * Http client
 */
class Client implements DispatchableInterface
{
    /**
     * @const string Supported HTTP Authentication methods
     */
    public const AUTH_BASIC  = 'basic';
    public const AUTH_DIGEST = 'digest';

    /**
     * @const string POST data encoding methods
     */
    public const ENC_URLENCODED = 'application/x-www-form-urlencoded';
    public const ENC_FORMDATA   = 'multipart/form-data';

    /**
     * @const string DIGEST Authentication
     */
    public const DIGEST_REALM  = 'realm';
    public const DIGEST_QOP    = 'qop';
    public const DIGEST_NONCE  = 'nonce';
    public const DIGEST_OPAQUE = 'opaque';
    public const DIGEST_NC     = 'nc';
    public const DIGEST_CNONCE = 'cnonce';

    /** @var Response */
    protected $response;

    /** @var Request */
    protected $request;

    /** @var Client\Adapter\AdapterInterface */
    protected $adapter;

    /** @var array */
    protected $auth = [];

    /** @var string */
    protected $streamName;

    /** @var resource|null */
    protected $streamHandle;

    /** @var array of Header\SetCookie */
    protected $cookies = [];

    /** @var string */
    protected $encType = '';

    /** @var Request */
    protected $lastRawRequest;

    /** @var Response */
    protected $lastRawResponse;

    /** @var int */
    protected $redirectCounter = 0;

    /**
     * Configuration array, set using the constructor or using ::setOptions()
     *
     * @var array
     */
    protected $config = [
        'maxredirects'    => 5,
        'strictredirects' => false,
        'useragent'       => 'Laminas_Http_Client',
        'timeout'         => 10,
        'connecttimeout'  => null,
        'adapter'         => Socket::class,
        'httpversion'     => Request::VERSION_11,
        'storeresponse'   => true,
        'keepalive'       => false,
        'outputstream'    => false,
        'encodecookies'   => true,
        'argseparator'    => null,
        'rfc3986strict'   => false,
        'sslcafile'       => null,
        'sslcapath'       => null,
    ];

    /**
     * Fileinfo magic database resource
     *
     * This variable is populated the first time _detectFileMimeType is called
     * and is then reused on every call to this method
     *
     * @var resource
     */
    protected static $fileInfoDb;

    /**
     * Constructor
     *
     * @param string $uri
     * @param array|Traversable $options
     */
    public function __construct($uri = null, $options = null)
    {
        if ($uri !== null) {
            $this->setUri($uri);
        }
        if ($options !== null) {
            $this->setOptions($options);
        }
    }

    /**
     * Set configuration parameters for this HTTP client
     *
     * @param  array|Traversable $options
     * @return $this
     * @throws Client\Exception\InvalidArgumentException
     */
    public function setOptions($options = [])
    {
        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        }
        if (! is_array($options)) {
            throw new Client\Exception\InvalidArgumentException('Config parameter is not valid');
        }

        /** Config Key Normalization */
        foreach ($options as $k => $v) {
            $this->config[str_replace(['-', '_', ' ', '.'], '', strtolower($k))] = $v; // replace w/ normalized
        }

        // Pass configuration options to the adapter if it exists
        if ($this->adapter instanceof Client\Adapter\AdapterInterface) {
            $this->adapter->setOptions($options);
        }

        return $this;
    }

    /**
     * Load the connection adapter
     *
     * While this method is not called more than one for a client, it is
     * separated from ->request() to preserve logic and readability
     *
     * @param  Client\Adapter\AdapterInterface|string $adapter
     * @return $this
     * @throws Client\Exception\InvalidArgumentException
     */
    public function setAdapter($adapter)
    {
        if (is_string($adapter)) {
            if (! class_exists($adapter)) {
                throw new Client\Exception\InvalidArgumentException(
                    'Unable to locate adapter class "' . $adapter . '"'
                );
            }
            $adapter = new $adapter();
        }

        if (! $adapter instanceof Client\Adapter\AdapterInterface) {
            throw new Client\Exception\InvalidArgumentException('Passed adapter is not a HTTP connection adapter');
        }

        $this->adapter = $adapter;
        $config        = $this->config;
        unset($config['adapter']);
        $this->adapter->setOptions($config);
        return $this;
    }

    /**
     * Load the connection adapter
     *
     * @return Client\Adapter\AdapterInterface
     */
    public function getAdapter()
    {
        if (! $this->adapter) {
            $this->setAdapter($this->config['adapter']);
        }

        return $this->adapter;
    }

    /**
     * Set request
     *
     * @return $this
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Get Request
     *
     * @return Request
     */
    public function getRequest()
    {
        if (empty($this->request)) {
            $this->request = new Request();
            $this->request->setAllowCustomMethods(false);
        }
        return $this->request;
    }

    /**
     * Set response
     *
     * @return $this
     */
    public function setResponse(Response $response)
    {
        $this->response = $response;
        return $this;
    }

    /**
     * Get Response
     *
     * @return Response
     */
    public function getResponse()
    {
        if (empty($this->response)) {
            $this->response = new Response();
        }
        return $this->response;
    }

    /**
     * Get the last request (as a string)
     *
     * @return string
     */
    public function getLastRawRequest()
    {
        return $this->lastRawRequest;
    }

    /**
     * Get the last response (as a string)
     *
     * @return string
     */
    public function getLastRawResponse()
    {
        return $this->lastRawResponse;
    }

    /**
     * Get the redirections count
     *
     * @return int
     */
    public function getRedirectionsCount()
    {
        return $this->redirectCounter;
    }

    /**
     * Set Uri (to the request)
     *
     * @param string|Http $uri
     * @return $this
     */
    public function setUri($uri)
    {
        if (! empty($uri)) {
            // remember host of last request
            $lastHost = $this->getRequest()->getUri()->getHost();
            $this->getRequest()->setUri($uri);

            // if host changed, the HTTP authentication should be cleared for security
            // reasons, see #4215 for a discussion - currently authentication is also
            // cleared for peer subdomains due to technical limits
            $nextHost = $this->getRequest()->getUri()->getHost();
            if (! empty($lastHost) && ! preg_match('/' . preg_quote($lastHost, '/') . '$/i', $nextHost)) {
                $this->clearAuth();
            }

            $uri      = $this->getUri();
            $user     = $uri->getUser();
            $password = $uri->getPassword();

            // Set auth if username and password has been specified in the uri
            if ($user && $password) {
                $this->setAuth($user, $password);
            }

            // We have no ports, set the defaults
            if (! $uri->getPort() && $uri->isAbsolute()) {
                $uri->setPort($uri->getScheme() === 'https' ? 443 : 80);
            }
        }
        return $this;
    }

    /**
     * Get uri (from the request)
     *
     * @return Http
     */
    public function getUri()
    {
        return $this->getRequest()->getUri();
    }

    /**
     * Set the HTTP method (to the request)
     *
     * @param string $method
     * @return $this
     */
    public function setMethod($method)
    {
        $method = $this->getRequest()->setMethod($method)->getMethod();

        if (
            empty($this->encType)
            && in_array(
                $method,
                [
                    Request::METHOD_POST,
                    Request::METHOD_PUT,
                    Request::METHOD_DELETE,
                    Request::METHOD_PATCH,
                    Request::METHOD_OPTIONS,
                ],
                true
            )
        ) {
            $this->setEncType(self::ENC_URLENCODED);
        }

        return $this;
    }

    /**
     * Get the HTTP method
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->getRequest()->getMethod();
    }

    /**
     * Set the query string argument separator
     *
     * @param string $argSeparator
     * @return $this
     */
    public function setArgSeparator($argSeparator)
    {
        $this->setOptions(['argseparator' => $argSeparator]);
        return $this;
    }

    /**
     * Get the query string argument separator
     *
     * @return string
     */
    public function getArgSeparator()
    {
        $argSeparator = $this->config['argseparator'];
        if (empty($argSeparator)) {
            $argSeparator = ini_get('arg_separator.output');
            $this->setArgSeparator($argSeparator);
        }
        return $argSeparator;
    }

    /**
     * Set the encoding type and the boundary (if any)
     *
     * @param string $encType
     * @param string $boundary
     * @return $this
     */
    public function setEncType($encType, $boundary = null)
    {
        if (null === $encType || empty($encType)) {
            $this->encType = null;
            return $this;
        }

        if (! empty($boundary)) {
            $encType .= sprintf('; boundary=%s', $boundary);
        }

        $this->encType = $encType;
        return $this;
    }

    /**
     * Get the encoding type
     *
     * @return string
     */
    public function getEncType()
    {
        return $this->encType;
    }

    /**
     * Set raw body (for advanced use cases)
     *
     * @param string $body
     * @return $this
     */
    public function setRawBody($body)
    {
        $this->getRequest()->setContent($body);
        return $this;
    }

    /**
     * Set the POST parameters
     *
     * @return $this
     */
    public function setParameterPost(array $post)
    {
        $this->getRequest()->getPost()->fromArray($post);
        return $this;
    }

    /**
     * Set the GET parameters
     *
     * @return $this
     */
    public function setParameterGet(array $query)
    {
        $this->getRequest()->getQuery()->fromArray($query);
        return $this;
    }

    /**
     * Reset all the HTTP parameters (request, response, etc)
     *
     * @param  bool   $clearCookies  Also clear all valid cookies? (defaults to false)
     * @return $this
     */
    public function resetParameters($clearCookies = false)
    {
        $clearAuth = true;
        if (func_num_args() > 1) {
            $clearAuth = func_get_arg(1);
        }

        $uri = $this->getUri();

        $this->streamName      = null;
        $this->encType         = null;
        $this->request         = null;
        $this->response        = null;
        $this->lastRawRequest  = null;
        $this->lastRawResponse = null;

        $this->setUri($uri);

        if ($clearCookies) {
            $this->clearCookies();
        }

        if ($clearAuth) {
            $this->clearAuth();
        }

        return $this;
    }

    /**
     * Return the current cookies
     *
     * @return array
     */
    public function getCookies()
    {
        return $this->cookies;
    }

    /**
     * Get the cookie Id (name+domain+path)
     *
     * @param  SetCookie|Header\Cookie $cookie
     * @return string|bool
     */
    protected function getCookieId($cookie)
    {
        if ($cookie instanceof Header\SetCookie || $cookie instanceof Header\Cookie) {
            return $cookie->getName() . $cookie->getDomain() . $cookie->getPath();
        }
        return false;
    }

    /**
     * Add a cookie
     *
     * @param array|ArrayIterator|SetCookie|string $cookie
     * @param string  $value
     * @param string  $expire
     * @param string  $path
     * @param string  $domain
     * @param  bool $secure
     * @param  bool $httponly
     * @param string  $maxAge
     * @param string  $version
     * @throws Exception\InvalidArgumentException
     * @return $this
     */
    public function addCookie(
        $cookie,
        $value = null,
        $expire = null,
        $path = null,
        $domain = null,
        $secure = false,
        $httponly = true,
        $maxAge = null,
        $version = null
    ) {
        if (is_array($cookie) || $cookie instanceof ArrayIterator) {
            foreach ($cookie as $setCookie) {
                if ($setCookie instanceof Header\SetCookie) {
                    $this->cookies[$this->getCookieId($setCookie)] = $setCookie;
                } else {
                    throw new Exception\InvalidArgumentException('The cookie parameter is not a valid Set-Cookie type');
                }
            }
        } elseif (is_string($cookie) && $value !== null) {
            $setCookie                                     = new SetCookie(
                $cookie,
                $value,
                $expire,
                $path,
                $domain,
                $secure,
                $httponly,
                $maxAge,
                $version
            );
            $this->cookies[$this->getCookieId($setCookie)] = $setCookie;
        } elseif ($cookie instanceof Header\SetCookie) {
            $this->cookies[$this->getCookieId($cookie)] = $cookie;
        } else {
            throw new Exception\InvalidArgumentException('Invalid parameter type passed as Cookie');
        }
        return $this;
    }

    /**
     * Set an array of cookies
     *
     * @param  array|SetCookie[] $cookies Cookies as name=>value pairs or instances of SetCookie.
     * @throws Exception\InvalidArgumentException
     * @return $this
     */
    public function setCookies($cookies)
    {
        if (is_array($cookies)) {
            $this->clearCookies();
            foreach ($cookies as $name => $value) {
                if ($value instanceof SetCookie) {
                    $this->addCookie($value);
                } else {
                    $this->addCookie($name, $value);
                }
            }
        } else {
            throw new Exception\InvalidArgumentException('Invalid cookies passed as parameter, it must be an array');
        }
        return $this;
    }

    /**
     * Clear all the cookies
     */
    public function clearCookies()
    {
        $this->cookies = [];
    }

    /**
     * Set the headers (for the request)
     *
     * @param  Headers|array $headers
     * @throws Exception\InvalidArgumentException
     * @return $this
     */
    public function setHeaders($headers)
    {
        if (is_array($headers)) {
            $newHeaders = new Headers();
            $newHeaders->addHeaders($headers);
            $this->getRequest()->setHeaders($newHeaders);
        } elseif ($headers instanceof Headers) {
            $this->getRequest()->setHeaders($headers);
        } else {
            throw new Exception\InvalidArgumentException('Invalid parameter headers passed');
        }
        return $this;
    }

    /**
     * Check if exists the header type specified
     *
     * @param  string $name
     * @return bool
     */
    public function hasHeader($name)
    {
        $headers = $this->getRequest()->getHeaders();

        if ($headers instanceof Headers) {
            return $headers->has($name);
        }

        return false;
    }

    /**
     * Get the header value of the request
     *
     * @param  string $name
     * @return string|bool
     */
    public function getHeader($name)
    {
        $headers = $this->getRequest()->getHeaders();

        if ($headers instanceof Headers) {
            if ($headers->get($name)) {
                return $headers->get($name)->getFieldValue();
            }
        }
        return false;
    }

    /**
     * Set streaming for received data
     *
     * @param string|bool $streamfile Stream file, true for temp file, false/null for no streaming
     * @return $this
     */
    public function setStream($streamfile = true)
    {
        $this->setOptions(['outputstream' => $streamfile]);
        return $this;
    }

    /**
     * Get status of streaming for received data
     *
     * @return bool|string
     */
    public function getStream()
    {
        if (null !== $this->streamName) {
            return $this->streamName;
        }

        return $this->config['outputstream'];
    }

    /**
     * Create temporary stream
     *
     * @return resource
     * @throws Exception\RuntimeException
     */
    protected function openTempStream()
    {
        $this->streamName = $this->config['outputstream'];

        if (! is_string($this->streamName)) {
            // If name is not given, create temp name
            $this->streamName = tempnam(
                $this->config['streamtmpdir'] ?? sys_get_temp_dir(),
                self::class
            );
        }

        ErrorHandler::start();
        $fp    = fopen($this->streamName, 'w+b');
        $error = ErrorHandler::stop();
        if (false === $fp) {
            if ($this->adapter instanceof Client\Adapter\AdapterInterface) {
                $this->adapter->close();
            }
            throw new Exception\RuntimeException(sprintf('Could not open temp file %s', $this->streamName), 0, $error);
        }

        return $fp;
    }

    /**
     * Create a HTTP authentication "Authorization:" header according to the
     * specified user, password and authentication method.
     *
     * @param string $user
     * @param string $password
     * @param string $type
     * @throws Exception\InvalidArgumentException
     * @return $this
     */
    public function setAuth($user, $password, $type = self::AUTH_BASIC)
    {
        if (! defined('static::AUTH_' . strtoupper($type))) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Invalid or not supported authentication type: \'%s\'',
                $type
            ));
        }

        if (empty($user)) {
            throw new Exception\InvalidArgumentException('The username cannot be empty');
        }

        $this->auth = [
            'user'     => $user,
            'password' => $password,
            'type'     => $type,
        ];

        return $this;
    }

    /**
     * Clear http authentication
     */
    public function clearAuth()
    {
        $this->auth = [];
    }

    /**
     * Calculate the response value according to the HTTP authentication type
     *
     * @see http://www.faqs.org/rfcs/rfc2617.html
     *
     * @param string $user
     * @param string $password
     * @param string $type
     * @param array $digest
     * @param null|string $entityBody
     * @throws Exception\InvalidArgumentException
     * @return string|bool
     */
    protected function calcAuthDigest($user, $password, $type = self::AUTH_BASIC, $digest = [], $entityBody = null)
    {
        if (! defined('self::AUTH_' . strtoupper($type))) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Invalid or not supported authentication type: \'%s\'',
                $type
            ));
        }
        $response = false;
        switch (strtolower($type)) {
            case self::AUTH_BASIC:
                // In basic authentication, the user name cannot contain ":"
                if (strpos($user, ':') !== false) {
                    throw new Exception\InvalidArgumentException(
                        'The user name cannot contain \':\' in Basic HTTP authentication'
                    );
                }
                $response = base64_encode($user . ':' . $password);
                break;
            case self::AUTH_DIGEST:
                if (empty($digest)) {
                    throw new Exception\InvalidArgumentException('The digest cannot be empty');
                }
                foreach ($digest as $key => $value) {
                    if (! defined('self::DIGEST_' . strtoupper($key))) {
                        throw new Exception\InvalidArgumentException(sprintf(
                            'Invalid or not supported digest authentication parameter: \'%s\'',
                            $key
                        ));
                    }
                }
                $ha1 = md5($user . ':' . $digest['realm'] . ':' . $password);
                if (empty($digest['qop']) || strtolower($digest['qop']) === 'auth') {
                    $ha2 = md5($this->getMethod() . ':' . $this->getUri()->getPath());
                } elseif (strtolower($digest['qop']) === 'auth-int') {
                    if (empty($entityBody)) {
                        throw new Exception\InvalidArgumentException(
                            'I cannot use the auth-int digest authentication without the entity body'
                        );
                    }
                    $ha2 = md5($this->getMethod() . ':' . $this->getUri()->getPath() . ':' . md5($entityBody));
                }
                if (empty($digest['qop'])) {
                    $response = md5($ha1 . ':' . $digest['nonce'] . ':' . $ha2);
                } else {
                    $response = md5($ha1 . ':' . $digest['nonce'] . ':' . $digest['nc']
                                    . ':' . $digest['cnonce'] . ':' . $digest['qoc'] . ':' . $ha2);
                }
                break;
        }
        return $response;
    }

    /**
     * Dispatch
     *
     * @return ResponseInterface
     */
    public function dispatch(RequestInterface $request, ?ResponseInterface $response = null)
    {
        return $this->send($request);
    }

    /**
     * Send HTTP request
     *
     * @return Response
     * @throws Exception\RuntimeException
     * @throws RuntimeException
     */
    public function send(?Request $request = null)
    {
        if ($request !== null) {
            $this->setRequest($request);
        }

        $this->redirectCounter = 0;

        $adapter = $this->getAdapter();

        // Send the first request. If redirected, continue.
        do {
            // uri
            $uri = $this->getUri();

            // query
            $query = $this->getRequest()->getQuery();

            if (! empty($query)) {
                $queryArray = $query->toArray();

                if (! empty($queryArray)) {
                    $newUri      = $uri->toString();
                    $queryString = http_build_query($queryArray, '', $this->getArgSeparator());

                    if ($this->config['rfc3986strict']) {
                        $queryString = str_replace('+', '%20', $queryString);
                    }

                    if (strpos($newUri, '?') !== false) {
                        $newUri .= $this->getArgSeparator() . $queryString;
                    } else {
                        $newUri .= '?' . $queryString;
                    }

                    $uri = new Http($newUri);
                }
            }
            // If we have no ports, set the defaults
            if (! $uri->getPort() && $uri->isAbsolute()) {
                $uri->setPort($uri->getScheme() === 'https' ? 443 : 80);
            }

            // method
            $method = $this->getRequest()->getMethod();

            // this is so the correct Encoding Type is set
            $this->setMethod($method);

            // body
            $body = $this->prepareBody();

            // headers
            $headers = $this->prepareHeaders($body, $uri);

            $secure = $uri->getScheme() === 'https';

            // cookies
            $cookie = $this->prepareCookies($uri->getHost(), $uri->getPath(), $secure);
            if ($cookie->getFieldValue()) {
                $headers['Cookie'] = $cookie->getFieldValue();
            }

            // check that adapter supports streaming before using it
            if (is_resource($body) && ! $adapter instanceof Client\Adapter\StreamInterface) {
                throw new RuntimeException('Adapter does not support streaming');
            }

            $this->streamHandle = null;
            // calling protected method to allow extending classes
            // to wrap the interaction with the adapter
            $response           = $this->doRequest($uri, $method, $secure, $headers, $body);
            $stream             = $this->streamHandle;
            $this->streamHandle = null;

            if (! $response) {
                if ($stream !== null) {
                    fclose($stream);
                }
                throw new Exception\RuntimeException('Unable to read response, or response is empty');
            }

            if ($this->config['storeresponse']) {
                $this->lastRawResponse = $response;
            } else {
                $this->lastRawResponse = null;
            }

            if ($this->config['outputstream']) {
                if ($stream === null) {
                    $stream = $this->getStream();
                    if (! is_resource($stream) && is_string($stream)) {
                        $stream = fopen($stream, 'r');
                    }
                }
                $streamMetaData = stream_get_meta_data($stream);
                if ($streamMetaData['seekable']) {
                    rewind($stream);
                }
                // cleanup the adapter
                $adapter->setOutputStream(null);
                $response = Response\Stream::fromStream($response, $stream);
                $response->setStreamName($this->streamName);
                if (! is_string($this->config['outputstream'])) {
                    // we used temp name, will need to clean up
                    $response->setCleanup(true);
                }
            } else {
                $response = $this->getResponse()->fromString($response);
            }

            // Get the cookies from response (if any)
            $setCookies = $response->getCookie();
            if (! empty($setCookies)) {
                $this->addCookie($setCookies);
            }

            // If we got redirected, look for the Location header
            if ($response->isRedirect() && ($response->getHeaders()->has('Location'))) {
                // Avoid problems with buggy servers that add whitespace at the
                // end of some headers
                $location = trim($response->getHeaders()->get('Location')->getFieldValue());

                // Check whether we send the exact same request again, or drop the parameters
                // and send a GET request
                if (
                    $response->getStatusCode() === 303
                    || ((! $this->config['strictredirects'])
                        && ($response->getStatusCode() === 302 || $response->getStatusCode() === 301))
                ) {
                    $this->resetParameters(false, false);
                    $this->setMethod(Request::METHOD_GET);
                }

                // If we got a well formed absolute URI
                if (
                    ($scheme = substr($location, 0, 6))
                    && ($scheme === 'http:/' || $scheme === 'https:')
                ) {
                    // setURI() clears parameters if host changed, see #4215
                    $this->setUri($location);
                } else {
                    // Split into path and query and set the query
                    if (strpos($location, '?') !== false) {
                        [$location, $query] = explode('?', $location, 2);
                    } else {
                        $query = '';
                    }
                    $this->getUri()->setQuery($query);

                    // Else, if we got just an absolute path, set it
                    if (strpos($location, '/') === 0) {
                        $this->getUri()->setPath($location);
                        // Else, assume we have a relative path
                    } else {
                        // Get the current path directory, removing any trailing slashes
                        $path = $this->getUri()->getPath();
                        $path = rtrim(substr($path, 0, strrpos($path, '/')), '/');
                        $this->getUri()->setPath($path . '/' . $location);
                    }
                }
                ++$this->redirectCounter;
            } else {
                // If we didn't get any location, stop redirecting
                break;
            }
        } while ($this->redirectCounter <= $this->config['maxredirects']);

        $this->response = $response;
        return $response;
    }

    /**
     * Fully reset the HTTP client (auth, cookies, request, response, etc.)
     *
     * @return $this
     */
    public function reset()
    {
        $this->resetParameters();
        $this->clearAuth();
        $this->clearCookies();

        return $this;
    }

    /**
     * Set a file to upload (using a POST request)
     *
     * Can be used in two ways:
     *
     * 1. $data is null (default): $filename is treated as the name if a local file which
     * will be read and sent. Will try to guess the content type using mime_content_type().
     * 2. $data is set - $filename is sent as the file name, but $data is sent as the file
     * contents and no file is read from the file system. In this case, you need to
     * manually set the Content-Type ($ctype) or it will default to
     * application/octet-stream.
     *
     * @param  string $filename Name of file to upload, or name to save as
     * @param  string $formname Name of form element to send as
     * @param  string $data Data to send (if null, $filename is read and sent)
     * @param  string $ctype Content type to use (if $data is set and $ctype is
     *                null, will be application/octet-stream)
     * @return $this
     * @throws Exception\RuntimeException
     */
    public function setFileUpload($filename, $formname, $data = null, $ctype = null)
    {
        if ($data === null) {
            ErrorHandler::start();
            $data  = file_get_contents($filename);
            $error = ErrorHandler::stop();
            if ($data === false) {
                throw new Exception\RuntimeException(sprintf(
                    'Unable to read file \'%s\' for upload',
                    $filename
                ), 0, $error);
            }
            if (! $ctype) {
                $ctype = $this->detectFileMimeType($filename);
            }
        }

        $this->getRequest()->getFiles()->set($filename, [
            'formname' => $formname,
            'filename' => basename($filename),
            'ctype'    => $ctype,
            'data'     => $data,
        ]);

        return $this;
    }

    /**
     * Remove a file to upload
     *
     * @param  string $filename
     * @return bool
     */
    public function removeFileUpload($filename)
    {
        $file = $this->getRequest()->getFiles()->get($filename);
        if (! empty($file)) {
            $this->getRequest()->getFiles()->set($filename, null);
            return true;
        }
        return false;
    }

    /**
     * Prepare Cookies
     *
     * @param   string $domain
     * @param   string $path
     * @param   bool $secure
     * @return  Header\Cookie|bool
     */
    protected function prepareCookies($domain, $path, $secure)
    {
        $validCookies = [];

        if (! empty($this->cookies)) {
            foreach ($this->cookies as $id => $cookie) {
                if ($cookie->isExpired()) {
                    unset($this->cookies[$id]);
                    continue;
                }

                if ($cookie->isValidForRequest($domain, $path, $secure)) {
                    // OAM hack some domains try to set the cookie multiple times
                    $validCookies[$cookie->getName()] = $cookie;
                }
            }
        }

        $cookies = Header\Cookie::fromSetCookieArray($validCookies);
        $cookies->setEncodeValue($this->config['encodecookies']);

        return $cookies;
    }

    /**
     * Prepare the request headers
     *
     * @param resource|string $body
     * @param Http $uri
     * @return array
     * @throws Exception\RuntimeException
     */
    protected function prepareHeaders($body, $uri)
    {
        $headers = [];

        // Set the host header
        if ($this->config['httpversion'] === Request::VERSION_11) {
            $host = $uri->getHost();
            // If the port is not default, add it
            if (
                ! (($uri->getScheme() === 'http' && $uri->getPort() === 80)
                || ($uri->getScheme() === 'https' && $uri->getPort() === 443))
            ) {
                $host .= ':' . $uri->getPort();
            }

            $headers['Host'] = $host;
        }

        // Set the connection header
        if (! $this->getRequest()->getHeaders()->has('Connection')) {
            if (! $this->config['keepalive']) {
                $headers['Connection'] = 'close';
            }
        }

        // Set the Accept-encoding header if not set - depending on whether
        // zlib is available or not.
        if (! $this->getRequest()->getHeaders()->has('Accept-Encoding')) {
            if (empty($this->config['outputstream']) && function_exists('gzinflate')) {
                $headers['Accept-Encoding'] = 'gzip, deflate';
            } else {
                $headers['Accept-Encoding'] = 'identity';
            }
        }

        // Set the user agent header
        if (! $this->getRequest()->getHeaders()->has('User-Agent') && isset($this->config['useragent'])) {
            $headers['User-Agent'] = $this->config['useragent'];
        }

        // Set HTTP authentication if needed
        if (! empty($this->auth)) {
            switch ($this->auth['type']) {
                case self::AUTH_BASIC:
                    $auth = $this->calcAuthDigest($this->auth['user'], $this->auth['password'], $this->auth['type']);
                    if ($auth !== false) {
                        $headers['Authorization'] = 'Basic ' . $auth;
                    }
                    break;
                case self::AUTH_DIGEST:
                    if (! $this->adapter instanceof Client\Adapter\Curl) {
                        throw new Exception\RuntimeException(sprintf(
                            'The digest authentication is only available for curl adapters (%s)',
                            Curl::class
                        ));
                    }

                    $this->adapter->setCurlOption(CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
                    $this->adapter->setCurlOption(CURLOPT_USERPWD, $this->auth['user'] . ':' . $this->auth['password']);
            }
        }

        // Content-type
        $encType = $this->getEncType();
        if (! empty($encType)) {
            $headers['Content-Type'] = $encType;
        }

        if (! empty($body)) {
            if (is_resource($body)) {
                $fstat                     = fstat($body);
                $headers['Content-Length'] = $fstat['size'];
            } else {
                $headers['Content-Length'] = strlen($body);
            }
        }

        // Merge the headers of the request (if any)
        // here we need right 'http field' and not lowercase letters
        $requestHeaders = $this->getRequest()->getHeaders();
        foreach ($requestHeaders as $requestHeaderElement) {
            $headers[$requestHeaderElement->getFieldName()] = $requestHeaderElement->getFieldValue();
        }
        return $headers;
    }

    /**
     * Prepare the request body (for PATCH, POST and PUT requests)
     *
     * @return string
     * @throws RuntimeException
     */
    protected function prepareBody()
    {
        // According to RFC2616, a TRACE request should not have a body.
        if ($this->getRequest()->isTrace()) {
            return '';
        }

        $rawBody = $this->getRequest()->getContent();
        if (! empty($rawBody)) {
            return $rawBody;
        }

        $body     = '';
        $hasFiles = false;

        if (! $this->getRequest()->getHeaders()->has('Content-Type')) {
            $hasFiles = ! empty($this->getRequest()->getFiles()->toArray());
            // If we have files to upload, force encType to multipart/form-data
            if ($hasFiles) {
                $this->setEncType(self::ENC_FORMDATA);
            }
        } else {
            $this->setEncType($this->getHeader('Content-Type'));
        }

        // If we have POST parameters or files, encode and add them to the body
        if (! empty($this->getRequest()->getPost()->toArray()) || $hasFiles) {
            if (stripos($this->getEncType(), self::ENC_FORMDATA) === 0) {
                $boundary = '---ZENDHTTPCLIENT-' . md5(microtime());
                $this->setEncType(self::ENC_FORMDATA, $boundary);

                // Get POST parameters and encode them
                $params = self::flattenParametersArray($this->getRequest()->getPost()->toArray());
                foreach ($params as $pp) {
                    $body .= $this->encodeFormData($boundary, $pp[0], $pp[1]);
                }

                // Encode files
                foreach ($this->getRequest()->getFiles()->toArray() as $file) {
                    $fhead = ['Content-Type' => $file['ctype']];
                    $body .= $this->encodeFormData(
                        $boundary,
                        $file['formname'],
                        $file['data'],
                        $file['filename'],
                        $fhead
                    );
                }
                $body .= '--' . $boundary . '--' . "\r\n";
            } elseif (stripos($this->getEncType(), self::ENC_URLENCODED) === 0) {
                // Encode body as application/x-www-form-urlencoded
                $body = http_build_query($this->getRequest()->getPost()->toArray(), '', '&');
            } else {
                throw new RuntimeException(sprintf(
                    'Cannot handle content type \'%s\' automatically',
                    $this->encType
                ));
            }
        }

        return $body;
    }

    /**
     * Attempt to detect the MIME type of a file using available extensions
     *
     * This method will try to detect the MIME type of a file. If the fileinfo
     * extension is available, it will be used. If not, the mime_magic
     * extension which is deprecated but is still available in many PHP setups
     * will be tried.
     *
     * If neither extension is available, the default application/octet-stream
     * MIME type will be returned
     *
     * @param string $file File path
     * @return string MIME type
     */
    protected function detectFileMimeType($file)
    {
        $type = null;

        // First try with fileinfo functions
        if (function_exists('finfo_open')) {
            if (static::$fileInfoDb === null) {
                ErrorHandler::start();
                static::$fileInfoDb = finfo_open(FILEINFO_MIME);
                ErrorHandler::stop();
            }

            if (static::$fileInfoDb) {
                $type = finfo_file(static::$fileInfoDb, $file);
            }
        } elseif (function_exists('mime_content_type')) {
            $type = mime_content_type($file);
        }

        // Fallback to the default application/octet-stream
        if (! $type) {
            $type = 'application/octet-stream';
        }

        return $type;
    }

    /**
     * Encode data to a multipart/form-data part suitable for a POST request.
     *
     * @param string $boundary
     * @param string $name
     * @param mixed $value
     * @param string $filename
     * @param array $headers Associative array of optional headers @example ("Content-Transfer-Encoding" => "binary")
     * @return string
     */
    public function encodeFormData($boundary, $name, $value, $filename = null, $headers = [])
    {
        $ret = '--' . $boundary . "\r\n"
            . 'Content-Disposition: form-data; name="' . $name . '"';

        if ($filename) {
            $ret .= '; filename="' . $filename . '"';
        }
        $ret .= "\r\n";

        foreach ($headers as $hname => $hvalue) {
            $ret .= $hname . ': ' . $hvalue . "\r\n";
        }
        $ret .= "\r\n";
        $ret .= $value . "\r\n";

        return $ret;
    }

    /**
     * Convert an array of parameters into a flat array of (key, value) pairs
     *
     * Will flatten a potentially multi-dimentional array of parameters (such
     * as POST parameters) into a flat array of (key, value) paris. In case
     * of multi-dimentional arrays, square brackets ([]) will be added to the
     * key to indicate an array.
     *
     * @since 1.9
     * @param array $parray
     * @param string $prefix
     * @return array
     */
    protected function flattenParametersArray($parray, $prefix = null)
    {
        if (! is_array($parray)) {
            return $parray;
        }

        $parameters = [];

        foreach ($parray as $name => $value) {
            // Calculate array key
            if ($prefix) {
                if (is_int($name)) {
                    $key = $prefix . '[]';
                } else {
                    $key = $prefix . sprintf('[%s]', $name);
                }
            } else {
                $key = $name;
            }

            if (is_array($value)) {
                $parameters = array_merge($parameters, $this->flattenParametersArray($value, $key));
            } else {
                $parameters[] = [$key, $value];
            }
        }

        return $parameters;
    }

    /**
     * Separating this from send method allows subclasses to wrap
     * the interaction with the adapter
     *
     * @param string $method
     * @param  bool $secure
     * @param array $headers
     * @param string $body
     * @return string the raw response
     * @throws Exception\RuntimeException
     */
    protected function doRequest(Http $uri, $method, $secure = false, $headers = [], $body = '')
    {
        // Open the connection, send the request and read the response
        $this->adapter->connect($uri->getHost(), $uri->getPort(), $secure);

        if ($this->config['outputstream']) {
            if ($this->adapter instanceof Client\Adapter\StreamInterface) {
                $this->streamHandle = $this->openTempStream();
                $this->adapter->setOutputStream($this->streamHandle);
            } else {
                throw new Exception\RuntimeException('Adapter does not support streaming');
            }
        }
        // HTTP connection
        $this->lastRawRequest = $this->adapter->write(
            $method,
            $uri,
            $this->config['httpversion'],
            $headers,
            $body
        );

        return $this->adapter->read();
    }

    /**
     * Create a HTTP authentication "Authorization:" header according to the
     * specified user, password and authentication method.
     *
     * @see http://www.faqs.org/rfcs/rfc2617.html
     *
     * @param string $user
     * @param string $password
     * @param string $type
     * @return string
     * @throws Client\Exception\InvalidArgumentException
     */
    public static function encodeAuthHeader($user, $password, $type = self::AUTH_BASIC)
    {
        switch ($type) {
            case self::AUTH_BASIC:
                // In basic authentication, the user name cannot contain ":"
                if (strpos($user, ':') !== false) {
                    throw new Client\Exception\InvalidArgumentException(
                        'The user name cannot contain \':\' in \'Basic\' HTTP authentication'
                    );
                }

                return 'Basic ' . base64_encode($user . ':' . $password);

            //case self::AUTH_DIGEST:
                /**
                 * @todo Implement digest authentication
                 */
                //    break;

            default:
                throw new Client\Exception\InvalidArgumentException(sprintf(
                    'Not a supported HTTP authentication type: \'%s\'',
                    $type
                ));
        }
    }
}
