<?php
/**
 * A request option
 * @author Daniel Mason
 * @copyright Daniel Mason, 2014
 */

namespace AyeAye\Api;

/**
 * Describes every detail of a request to the server
 * @package AyeAye\Api
 */
class Request implements \JsonSerializable
{

    // HTTP verbs as defined in http://www.ietf.org/rfc/rfc2616
    const METHOD_GET = 'GET';
    const METHOD_HEAD = 'HEAD';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_DELETE = 'DELETE';
    const METHOD_TRACE = 'TRACE';
    const METHOD_OPTIONS = 'OPTIONS';
    const METHOD_CONNECT = 'CONNECT';
    const METHOD_PATCH = 'PATCH';

    const DEFAULT_FORMAT = 'json';

    /**
     * A list of accepted HTTP verbs. By default everything is accepted
     * however, you could extend Request and provide a different list.
     * @var array
     */
    public static $allowedMethods = array(
        self::METHOD_GET,
        self::METHOD_HEAD,
        self::METHOD_POST,
        self::METHOD_PUT,
        self::METHOD_DELETE,
        self::METHOD_TRACE,
        self::METHOD_OPTIONS,
        self::METHOD_CONNECT,
        self::METHOD_PATCH,
    );

    /**
     * The method of request
     * @var string
     */
    protected $requestMethod = self::METHOD_GET;

    /**
     * @var string
     */
    protected $requestedUri = '';

    /**
     * The requested uri as an array
     * @var array
     */
    protected $requestChain = null;

    /**
     * The format for requested data
     * @var string Defaults to 'json'
     */
    protected $requestedFormat;

    /**
     * An amalgamation of all parameters sent in any way
     * @var array
     */
    protected $parameters = array();

    /**
     * Parameters sent as GET, POST, SESSION, COOKIE
     * @var array
     */
    protected $request = array();

    /**
     * Parameters sent in the header
     * @var array
     */
    protected $header = array();

    /**
     * The contents of the body of a request represented as an object
     * @var \stdClass
     */
    protected $body;

    /**
     * Used to trim the starting path (such as /api) from the front of the request
     * @var string
     */
    protected $baseUrl;

    /**
     * Create a Request object. You can override any request information
     * @param string $requestedMethod
     * @param string $requestedUri
     * @param array $request
     * @param array $header
     * @param string $bodyText
     * @param string $baseUrl
     */
    public function __construct(
        $requestedMethod = null,
        $requestedUri = null,
        array $request = null,
        array $header = null,
        $bodyText = null,
        $baseUrl = null
    ) {

        $this->setBaseUrl($baseUrl);

        if ($requestedMethod) {
            $this->requestMethod = $requestedMethod;
        } elseif (array_key_exists('REQUEST_METHOD', $_SERVER)) {
            $this->requestMethod = $_SERVER['REQUEST_METHOD'];
        }

        if ($requestedUri) {
            $this->requestedUri = $requestedUri;
        } elseif (array_key_exists('REQUEST_URI', $_SERVER)) {
            $this->requestedUri = $_SERVER['REQUEST_URI'];
        }


        // Set parameters
        if (is_null($request)) {
            $request = $_REQUEST;
        }
        $this->request = $request;

        if (is_null($header)) {
            $header = $_SERVER;
        }
        $this->header = $this->parseHeader($header);

        if (is_null($bodyText)) {
            $bodyText = $this->readBody();
        }
        $this->body = $this->stringToObject($bodyText);

        $this->addParameters($this->request);
        $this->addParameters($this->header);
        $this->addParameters($this->body);

    }

    /**
     * Parse headers
     * @param string[] $headers
     * @return string
     */
    public function parseHeader(array $headers = array())
    {
        $processedHeaders = array();
        foreach ($headers as $key => $value) {
            if (substr($key, 0, 5) == 'HTTP_') {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $processedHeaders[$name] = $value;
            } elseif ($key == 'CONTENT_TYPE') {
                $processedHeaders['Content-Type'] = $value;
            } elseif ($key == 'CONTENT_LENGTH') {
                $processedHeaders['Content-Length'] = $value;
            }
        }
        return $processedHeaders;
    }

    /**
     * Reads in the body of the request
     * @return string
     */
    protected function readBody()
    {
        if (function_exists('http_get_request_body')) {
            return http_get_request_body();
        }
        return @file_get_contents('php://input');
    }

    /**
     * Tries to turn a string of data into an object. Accepts json, xml or a php serialised object
     * Failing all else it will return a standard class with the string attached to data
     * eg. $this->stringObject('fail')->body == 'fail'
     * @param string $string a string of data
     * @throws \Exception
     * @return \stdClass
     */
    public function stringToObject($string)
    {
        if (!$string) {
            return new \stdClass();
        }
        // Json
        if ($jsonObject = json_decode($string)) {
            return $jsonObject;
        }
        // Xml
        if ($xmlObject = @simplexml_load_string($string)) {
            return $xmlObject;
        }
        // Php
        if ($phpObject = @unserialize($string)) {
            return $phpObject;
        }

        $object = new \stdClass();
        $object->text = $string;
        return $object;
    }

    /**
     * The http method being used
     * @return string
     */
    public function getMethod()
    {
        return $this->requestMethod;
    }

    /**
     * Look for the given parameter anywhere in the request
     * @param string $key
     * @param bool $default
     * @return mixed
     */
    public function getParameter($key, $default = null)
    {
        // Request _should_ contain get, post and cookies
        if (array_key_exists($key, $this->parameters)) {
            return $this->parameters[$key];
        }
        return $default;
    }

    /**
     * Returns all parameters. Does not return header or body parameters, maybe it should
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Get the requested route
     * @return string[]
     */
    public function getRequestChain()
    {
        if (is_null($this->requestChain)) {
            $this->requestChain = $this->getRequestChainFromUri($this->requestedUri);
        }
        return $this->requestChain;
    }

    /**
     * Gets the expected response format
     * @return string
     */
    public function getFormat()
    {
        if (is_null($this->requestedFormat)) {
            $this->requestedFormat = $this->getFormatFromUri($this->requestedUri);
            if (is_null($this->requestedFormat)) {
                $this->requestedFormat = static::DEFAULT_FORMAT;
            }
        }
        return $this->requestedFormat;
    }

    /**
     * Used by PHP to get json object
     * @return array|mixed
     */
    public function jsonSerialize()
    {
        return [
            'method' => $this->getMethod(),
            'requestedUri' => $this->requestedUri,
            'parameters' => $this->getParameters()
        ];
    }

    /**
     * Get the format from the url
     * @param $requestedUri
     * @return string|null
     */
    public function getFormatFromUri($requestedUri)
    {
        $uriParts = explode('?', $requestedUri, 2);
        $uriWithoutGet = reset($uriParts);
        $uriAndFormat = explode('.', $uriWithoutGet);
        if (count($uriAndFormat) >= 2) {
            return end($uriAndFormat);
        }
        return null;
    }

    /**
     * Breaks a url into useful parts
     * @param string $requestedUri
     * @return string[]
     */
    protected function getRequestChainFromUri($requestedUri)
    {
        // Trim any get variables and the requested format, eg: /requested/uri.format?get=variables
        $requestedUri = preg_replace('/[\?\.].*$/', '', $requestedUri);
        // Clear the base url
        $requestedUri = $this->removeBaseUrl($requestedUri, $this->baseUrl);

        $requestChain = explode('/', $requestedUri);

        if (!$requestChain[0]) {
            unset($requestChain[0]);
        }

        return $requestChain;
    }

    protected function removeBaseUrl($url, $baseUrl)
    {
        $url = trim($url, '/');
        $baseUrl = trim($baseUrl, '/');
        if (substr($url, 0, strlen($baseUrl)) == $baseUrl) {
            $url = substr($url, strlen($baseUrl));
        }
        return $url;
    }


    /**
     * Add a set of parameters to the Request
     * @param array|object $newParameters
     * @param bool $overwrite
     * @throws \Exception
     * @returns $this
     */
    public function addParameters($newParameters, $overwrite = true)
    {
        if (is_scalar($newParameters)) {
            throw new \Exception('Add parameters parameter newParameters can not be scalar');
        }
        foreach ($newParameters as $field => $value) {
            $this->addParameter($field, $value, $overwrite);
        }
        return $this;
    }

    /**
     * Add a parameter
     * @param $name
     * @param $value
     * @param bool $overwrite Overwrite existing parameters
     * @return bool Returns true of value was set
     * @throws \Exception
     */
    public function addParameter($name, $value, $overwrite = true)
    {
        if (!is_scalar($name)) {
            throw new \Exception('Add parameter: parameter name must be scalar');
        }
        if (!$overwrite && array_key_exists($name, $this->parameters)) {
            return false;
        }
        $this->parameters[$name] = $value;
        return true;
    }

    /**
     * Set the base url for the request. This will be removed from the request chain
     * @param $baseUrl
     * @return $this
     * @throws \Exception
     */
    public function setBaseUrl($baseUrl)
    {
        if (!is_null($baseUrl) && !is_string($baseUrl)) {
            throw new \Exception('baseUrl must be a string');
        }
        $this->baseUrl = $baseUrl;
        return $this;
    }
}
