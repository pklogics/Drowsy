<?php
/**
 * LibDrowsy
 *
 * PHP version 5.3+
 *
 * @category REST
 * @package  Drowsy
 * @author   Patrick <patrick@pklogics.com>
 * @license  http://www.php.net/license/3_01.txt  PHP License 3.01
 * @link     http://www.pklogics.com/Drowsy
 */

/**
 * Drowsy - A powerful REST library for PHP
 *
 * Drowsy is a library for PHP which implements the entire REST specification and
 * simplifies the process of dispatching complex HTTP requests. Drowsy provides
 * excellent support for RESTful JSON and RESTful XML by parsing the response data
 * and initializing the appropriate object model (DOMDocument or JSON Object). Drowsy
 * contains two HTTP engines which can be used interchangably, cURL and fsock. It can
 * also handle binary uploads and downloads on GET, POST and PUT requests. For
 * tutorials and code examples, see the Drowsy project website at
 * http://www.pklogics.com/Drowsy. To report bugs or feature requests, please email
 * the author at patrick@pklogics.com.
 *
 * @category REST
 * @package  Drowsy
 * @author   Patrick <patrick@pklogics.com>
 * @license  http://www.php.net/license/3_01.txt  PHP License 3.01
 * @link     http://www.pklogics.com/Drowsy
 */
class libdrowsy
{
    const HTTP_GET    = 'GET';
    const HTTP_POST   = 'POST';
    const HTTP_PUT    = 'PUT';
    const HTTP_DELETE = 'DELETE';

    const HTTPENGINE_CURL  = 'CURL';
    const HTTPENGINE_FSOCK = 'FSOCK';

    const RESTFUL_JSON    = 'application/json';
    const RESTFUL_XML     = 'application/xml';

    protected $httpEngine  = self::HTTPENGINE_CURL;
    protected $baseUrl     = null;
    protected $contentType = 'application/x-www-form-urlencoded';
    protected $acceptType  = '*/*';
    protected $username    = null;
    protected $password    = null;

    /**
     * Constructor
     *
     * @param string|null $baseUrl    Base URL to append to all REST requests
     * @param string|null $httpEngine HTTP engine identifier
     */
    public function __construct($baseUrl = null, $httpEngine = null)
    {
        if ($baseUrl !== null) {
            $this->setBaseUrl($baseUrl);
        }

        if ($httpEngine !== null) {
            $this->setHttpEngine($httpEngine);
        }
    }

    /**
     * Set Base URL
     *
     * @param string $baseUrl Base URL to append to all REST requests
     *
     * @return libdrowsy
     */
    public function setBaseUrl($baseUrl = 'http://localhost/')
    {
        if (parse_url($baseUrl) !== false) {
            $this->baseUrl = $baseUrl;
        } else {
            throw new InvalidArgumentException('Invalid base URL string!');
        }
        return $this;
    }

    /**
     * Get Base URL
     *
     * @return string Base URL to append to all REST requests
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * Set HTTP Engine
     *
     * @param string $httpEngine HTTP engine identifier
     *
     * @return libdrowsy
     *
     * @throws InvalidArgumentException
     */
    public function setHttpEngine($httpEngine = self::HTTPENGINE_CURL)
    {
        if ($httpEngine === self::HTTPENGINE_CURL
            || $httpEngine === self::HTTPENGINE_FSOCK
        ) {
            $this->httpEngine = $httpEngine;
        } else {
            throw new InvalidArgumentException('Invalid HTTP engine identifier!');
        }
        return $this;
    }

    /**
     * Get HTTP Engine
     *
     * @return string HTTP engine identifier
     */
    public function getHttpEngine()
    {
        return $this->httpEngine;
    }

    /**
     * Set MIME Content-Type
     *
     * @param string $contentType MIME content-type of request
     *
     * @return libdrowsy
     */
    public function setContentType(
        $contentType = 'application/x-www-form-urlencoded'
    ) {
        $this->contentType = (string)$contentType;
        return $this;
    }

    /**
     * Get MIME Content-Type
     *
     * @return string MIME content-type of request
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * Set MIME Accept Type
     *
     * @param string $acceptType MIME type(s) accepted in response
     *
     * @return libdrowsy
     */
    public function setAcceptType($acceptType = '*/*')
    {
        $this->acceptType = (string)$acceptType;
        return $this;
    }

    /**
     * Get MIME Accept Type
     *
     * @return string MIME type(s) accepted in response
     */
    public function getAcceptType()
    {
        return $this->acceptType;
    }

    /**
     * Set Username and Password for HTTP Basic Auth
     *
     * @param string $username Username
     * @param string $password Password
     *
     * @return libdrowsy
     */
    public function setCredentials($username = '', $password = '')
    {
        $this->username = (string)$username;
        $this->password = (string)$password;
        return $this;
    }

    /**
     * Run an HTTP GET request
     *
     * @param string|null $url URL to process the request
     *
     * @return string Reponse data from the server
     */
    public function doGet($url = null)
    {
        return $this->doRequest(self::HTTP_GET, $url);
    }

    /**
     * Run an HTTP POST request
     *
     * @param string|null $url  URL to process the request
     * @param string|null $data Data to be sent to the server
     *
     * @return string Reponse data from the server
     */
    public function doPost($url = null, $data = null)
    {
        return $this->doRequest(self::HTTP_POST, $url, $data);
    }

    /**
     * Run an HTTP PUT request
     *
     * @param string|null          $url  URL to process the request
     * @param string|resource|null $data Resource or data to be PUT onto the server
     *
     * @return string Reponse data from the server
     */
    public function doPut($url = null, $data = null)
    {
        if (!is_resource($data)) {
            $contents = $data;
            $data = fopen('php://temp/maxmemory:256000', 'w');
            fwrite($data, $contents);
            rewind($data);
        }

        $response = $this->doRequest(self::HTTP_PUT, $url, $data);

        fclose($data);

        return $response;
    }

    /**
     * Run an HTTP DELETE request
     *
     * @param string|null $url URL to process the request
     *
     * @return string Reponse data from the server
     */
    public function doDelete($url = null)
    {
        return $this->doRequest(self::HTTP_DELETE, $url);
    }

    /**
     * Run an HTTP request
     *
     * @param string               $method HTTP/1.1 request type
     * @param string|null          $url    URL to process the request
     * @param string|resource|null $data   Data to be sent to the server
     *
     * @return string Response data from the server
     *
     * @throws UnexpectedValueException
     */
    protected function doRequest($method = self::HTTP_GET, $url = null, $data = null)
    {
        if ($url === null) {
            $url = $this->baseUrl;
        } elseif ($this->baseUrl !== null) {
            $url = $this->baseUrl . $url;
        }

        if ($this->httpEngine === self::HTTPENGINE_CURL) {
            $response = $this->doCurlRequest($method, $url, $data);
        } elseif ($this->httpEngine === self::HTTPENGINE_FSOCK) {
            $response = $this->doFsockRequest($method, $url, $data);
        } else {
            throw new UnexpectedValueException('Invalid HTTP engine identifier!');
        }

        return $response;
    }

    /**
     * Run an HTTP request using cURL
     *
     * @param string               $method HTTP/1.1 request type
     * @param string|null          $url    URL to process the request
     * @param string|resource|null $data   Data to be sent to the server
     *
     * @return string Response data from the server
     */
    protected function doCurlRequest($method, $url, $data)
    {
        $http = curl_init($url);

        curl_setopt($http, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($http, CURLOPT_FOLLOWLOCATION, true);

        if ($this->username !== null && $this->password !== null) {
            curl_setopt($http, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt(
                $http, CURLOPT_USERPWD,
                $this->username . ':' . $this->password
            );
        }

        switch ($method) {
        case self::HTTP_GET:
                curl_setopt($http, CURLOPT_HTTPGET, true);
            break;
        case self::HTTP_POST:
                curl_setopt($http, CURLOPT_POST, true);
                curl_setopt($http, CURLOPT_POSTFIELDS, $data);
            break;
        case self::HTTP_PUT:
                curl_setopt($http, CURLOPT_PUT, true);
                curl_setopt($http, CURLOPT_INFILE, $data);
                $size = fstat($data);
                $size = $size['size'];
                curl_setopt($http, CURLOPT_INFILESIZE, $size);
            break;
        case self::HTTP_DELETE:
                curl_setopt($http, CURLOPT_CUSTOMREQUEST, 'DELETE');
            break;
        }

        $response = curl_exec($http);

        return $response;
    }

    /**
     * Run an HTTP request using fsock
     *
     * @param string               $method HTTP/1.1 request type
     * @param string|null          $url    URL to process the request
     * @param string|resource|null $data   Data to be sent to the server
     *
     * @return string Response data from the server
     */
    protected function doFsockRequest($method, $url, $data)
    {
        $url = parse_url($url);

        $request = "{$method} {$url['path']} HTTP/1.1\r\n";
        $request .= "Host: {$url['host']}\r\n";
        $request .= "Content-Type: {$this->contentType}\r\n";
        $request .= "Accept: {$this->acceptType}\r\n";
        $request .= "Connection: Close\r\n\r\n";

        if ($method === self::HTTP_POST) {
            $request .= $data;
        } elseif ($method === self::HTTP_PUT) {
            while (!feof($data)) {
                $request .= fread($data, 1024);
            }
        }

        $http = fsockopen($url['host'], $url['port']);

        fwrite($http, $request);

        $response = '';
        while (!feof($http)) {
            $response .= fread($http, 1024);
        }

        fclose($http);

        return $response;
    }
}