<?php

/**
* FilmTiedApi API Library
*/

class FilmTiedApiException extends Exception
{}


class FilmTiedApi
{
    /**
     * Pnop api server address
     * @var String
     */
    protected $_apiServerAddress = "http://api.filmtied.com";

    /**
     * User agent
     * @var String
     */
    protected $_userAgent = "FilmTied API PHP Library";

    /**
     * Library version
     * @var String
     */
    protected $_version = "1.0";

    /**
    * Connection Timeout
    * @var Integer
    */
    protected $_connectionTimeOut = 5;

    /**
     * Timeout for getting data
     * @var Integer
     */
    protected $_timeOut = 5;

    /**
     * jsonrpc
     * @var String
     */
    protected $_jsonrpc = '2.0';

    /**
     * Cache
     * @var Resource
     */
    protected $_cache;

    /**
     * Cache Type
     * @var String
     */
    protected $_cacheType;

    /**
     * Cache Expiration
     * @var Integer
     */
    protected $_cacheExpiration = 600;

    /**
     * json error messages
     */
    protected $_jsonErrorMessages = array(
        JSON_ERROR_NONE             => 'No error has occurred',
        JSON_ERROR_DEPTH            => 'The maximum stack depth has been exceeded',
        JSON_ERROR_STATE_MISMATCH   => 'Invalid or malformed JSON',
        JSON_ERROR_CTRL_CHAR        => 'Control character error, possibly incorrectly encoded',
        JSON_ERROR_SYNTAX           => 'Syntax error',
        JSON_ERROR_UTF8             => 'Malformed UTF-8 characters, possibly incorrectly encoded'
    );

    /**
     * Constructor
     * @param String $token
     * @param Array $params:
     *        apiServerAddress   - (string) our api server url, hardcoded in this file, only for testing purposes (optional)
     *        connectionTimeOut  - (int) connection timeout, default 5s (optional)
     *        timeOut            - (int) data retrive timeout, default 5s (optional)
     *        cache              - (string) users cache server type: 'memcache' or 'memcached', enter to enable caching (optional)
     *        cacheServerAddress - (string) users cache server adres (optional, required if cache is enabled)
     *        cacheServerPort    - (int) users cache server port (optional, required if cache is enabled )
     *        cacheExpiration    - (string) user cache expiration time, default 600s (10 min) (optional)
     *
     *
     * @example
     *
     *    $token = '#someSecredToken#';
     *
     *    $params = array(
     *        'cache'                 => 'memcache',
     *        'cacheServerAddress'    => '127.0.0.1',
     *        'cacheServerPort'       => '11211',
     *    );
     *
     *    $filmTiedApi = new FilmTiedApi($token, $params);
     *
     */
    public function __construct($token, $params = null)
    {
        if (!function_exists('json_encode') || !function_exists('json_decode')) {
            throw new FilmTiedApiException('This software requires json_encode and json_decode functions (PHP 5.2)');
        }

        if (!function_exists('curl_init')) {
            throw new FilmTiedApiException('This software requires cURL extension.');
        }

        $this->_token = $token;

        if ($params && is_array($params) && count($params) > 0) {
            if (isset($params['apiServerAddress'])) $this->_apiServerAddress = $params['apiServerAddress'];
            if (isset($params['connectionTimeOut'])) $this->_connectionTimeOut = (int)$params['connectionTimeOut'];
            if (isset($params['timeOut'])) $this->_timeOut = (int)$params['timeOut'];
            if (isset($params['cache']) && isset($params['cacheServerAddress']) && isset($params['cacheServerPort'])) {
                if ($params['cache'] == 'memcache') {
                    $this->_cache = new Memcache();
                    $this->_cache->connect($params['cacheServerAddress'], (int)$params['cacheServerPort']);
                    $this->_cacheType = 'Memcache';
                } elseif ($params['cache'] == 'memcached')  {
                    $this->_cache = new Memcached();
                    $this->_cache->addServer($params['cacheServerAddress'], (int)$params['cacheServerPort']);
                    $this->_cacheType = 'Memcached';
                }

                if (isset($params['cacheExpiration'])) {
                    $this->_cacheExpiration = (int)$params['cacheExpiration'];
                }
            }
        }
    }

    /**
    * Make request
    *
    * @param String $method
    * @param Array $params
    * @return void
    */
    protected function processRequest($jsonData)
    {
        if ($this->_cache) {
            $cacheName = 'PnopApi_' . md5($jsonData);
            $result = $this->_cache->get($cacheName);
        }

        if (empty($result)) {
            $ch = curl_init($this->_apiServerAddress);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_USERAGENT, $this->_userAgent." v.".$this->_version);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->_timeOut);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->_connectionTimeOut);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData))
            );

            $result = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new FilmTiedApiException(curl_error($ch));
            }

            curl_close($ch);

            if (!empty($result) && $this->_cache) {
                if ($this->_cacheType == 'Memcache') {
                    $this->_cache->set($cacheName, $result, false, $this->_cacheExpiration);
                } else {
                    $this->_cache->set($cacheName, $result, $this->_cacheExpiration);
                }
            }
        }

        return $result;
    }

    /**
    * prepare json
    *
    * @param String $method
    * @param Array $params
    * @return void
    */
    protected function prepareJson($method, $params)
    {
        if (!$method || !is_array($params)) {
            return null;
        }

        $params['token'] = $this->_token;

        $data = array(
            'jsonrpc'   => $this->_jsonrpc,
            'method'    => $method,
            'params'    => $params,
            'id'        => $this->_cache ? 1 : microtime(true)
        );

        $jsonData = json_encode($data);

        if (empty($jsonData)) {
            throw new FilmTiedApiException('json_encode: ' . $this->_jsonErrorMessages[json_last_error()]);
        }

        return $jsonData;
    }

    /**
    * process outpur json
    *
    * @param String $method
    * @param Array $params
    * @return void
    */
    protected function getResult($jsonData)
    {
        $data = json_decode($jsonData, true);

        if (empty($data)) {
            throw new FilmTiedApiException('json_decode: ' . $this->_jsonErrorMessages[json_last_error()]);
        }

        if (array_key_exists('result', $data)) {
            return $data['result'];
        } elseif (array_key_exists('error', $data)) {
            throw new FilmTiedApiException($data['error']['message']);
        }

        return '';
    }

    /**
     * Find FilmTied item corresponding to imdb.com url
     *
     * @param String $imdbUrl
     * @return String
     */
    public function changeUrl($imdbUrl)
    {
        if (!$imdbUrl) {
            throw new FilmTiedApiException('Missing required param.');
        }

        $jsonData = $this->prepareJson('changeUrl', array('url' => trim($imdbUrl)));
        $jsonResult = $this->processRequest($jsonData);
        $data = $this->getResult($jsonResult);

        return $data;
    }


    /**
     * Get FilmTied item data by url
     *
     * @param String $url
     * @param int $imageSize [1,2,3]
     * @return Array
     */
    public function get($url, $imageSize = 2)
    {
        if (!$url) {
            throw new FilmTiedApiException('Missing required param.');
        }

        $params = array('url' => $url);

        if ($imageSize && is_numeric($imageSize)) {
            $params['imageSize'] = (int) $imageSize;
        }

        $jsonData = $this->prepareJson('get', $params);
        $jsonResult = $this->processRequest($jsonData);
        $data = $this->getResult($jsonResult);

        return $data;
    }

    /**
     * Search FilmTied for given query
     *
     * @param string $query search phrase
     * @param int $page
     * @param int $limit
     * @param string $type [movies|tv-series]
     * @param int $imageSize [1,2,3]
     * @return array
     */
    public function search($query, $page = 1, $limit = 15, $type = null, $imageSize = 2)
    {
        $query = trim($query);

        if (!$query) {
            throw new FilmTiedApiException('Missing required param.');
        }

        $params = array('query' => $query);

        if ($page && is_numeric($page)) {
            $params['page'] = (int) $page;
        }

        if ($limit && is_numeric($limit)) {
            $params['limit'] = (int) $limit;
        }

        if ($imageSize && is_numeric($imageSize)) {
            $params['imageSize'] = (int) $imageSize;
        }

        if ($type && in_array($type, array('movies', 'tv-series'))) {
            $params['type'] = $type;
        }

        $jsonData = $this->prepareJson('search', $params);
        $jsonResult = $this->processRequest($jsonData);
        $data = $this->getResult($jsonResult);

        return $data;
    }
}


?>
