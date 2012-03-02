<?php
/**
 * Drowsy - Powerful REST library for PHP
 *
 * @package Drowsy
 * @author  Patrick <patrick@pklogics.com>
 * @link    http://www.pklogics.com/Drowsy
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
    const RESTFUL_ATOMPUB = 'application/atom+xml';

    protected $httpEngine  = self::HTTPENGINE_CURL;
    protected $baseUrl     = null;
    protected $contentType = 'application/x-www-form-urlencoded';
    protected $acceptType  = '*/*';
    protected $username    = null;
    protected $password    = null;

    public function __construct($baseUrl = null, $httpEngine = null)
    {
        if ($baseUrl !== null) {
            $this->setBaseUrl($baseUrl);
        }

        if ($httpEngine !== null) {
            $this->setHttpEngine($httpEngine);
        }
    }

    public function setBaseUrl($baseUrl = 'http://localhost/')
    {
        if (parse_url($baseUrl) !== false) {
            $this->baseUrl = $baseUrl;
        } else {
            throw new InvalidArgumentException('Invalid base URL string!');
        }
        return $this;
    }

    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    public function setHttpEngine($httpEngine = self::HTTPENGINE_CURL)
    {
        if ($httpEngine === self::HTTPENGINE_CURL || $httpEngine === self::HTTPENGINE_FSOCK) {
            $this->httpEngine = $httpEngine;
        } else {
            throw new InvalidArgumentException('Invalid HTTP engine identifier!');
        }
        return $this;
    }

    public function getHttpEngine()
    {
        return $this->httpEngine;
    }

    public function setContentType($contentType = 'application/x-www-form-urlencoded')
    {
        $this->contentType = (string)$contentType;
        return $this;
    }

    public function getContentType()
    {
        return $this->contentType;
    }

    public function setAcceptType($acceptType = '*/*')
    {
        $this->acceptType = (string)$acceptType;
        return $this;
    }

    public function getAcceptType()
    {
        return $this->acceptType;
    }

    public function setCredentials($username = '', $password = '')
    {
        $this->username = (string)$username;
        $this->password = (string)$password;
        return $this;
    }

    public function doGet($url = null)
    {
        return $this->doRequest(self::HTTP_GET, $url);
    }

    public function doPost($url = null, $data = null)
    {
        return $this->doRequest(self::HTTP_POST, $url, $data);
    }

    public function doPut($url = null, $contents = null)
    {
        if (!is_resource($contents)) {
            $data = $contents;
            $contents = fopen('php://temp/maxmemory:256000', 'w');
            fwrite($contents, $data);
            rewind($contents);
        }

        $response = $this->doRequest(self::HTTP_PUT, $url, $contents);

        fclose($contents);

        return $response;
    }

    public function doDelete($url = null)
    {
        return $this->doRequest(self::HTTP_DELETE, $url);
    }

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

    protected function doCurlRequest($method, $url, $data)
    {
        $http = curl_init($url);

        curl_setopt($http, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($http, CURLOPT_FOLLOWLOCATION, true);

        if ($this->username !== null && $this->password !== null) {
            curl_setopt($http, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
            curl_setopt($http, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        }

        switch($method) {
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

    protected function doFsockRequest($method, $url, $data)
    {
        $url = parse_url($url);

        $request = "{$method} {$url['path']} HTTP/1.1\r\n";
        $request .= "Host: {$url['host']}\r\n";
        $request .= "Content-Type: {$this->contentType}\r\n";
        $request .= "Accept: {$this->acceptType}\r\n";
        $request .= "Connection: Close\r\n\r\n";

        $http = fsockopen($url['host'], $url['port']);

        fwrite($http, $request);

        $response = '';
        while(!feof($http)) {
            $response .= fread($http, 1024);
        }

        fclose($http);

        return $response;
    }
}