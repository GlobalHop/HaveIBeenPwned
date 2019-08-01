<?php

namespace xsist10\HaveIBeenPwned;

use Psr\Log\LoggerInterface;
use xsist10\HaveIBeenPwned\Adapter\Adapter;
use xsist10\HaveIBeenPwned\Adapter\Curl;
use xsist10\HaveIBeenPwned\Response\AccountResponse;
use xsist10\HaveIBeenPwned\Response\BreachResponse;
use xsist10\HaveIBeenPwned\Response\PasteResponse;
use xsist10\HaveIBeenPwned\Response\DataClassResponse;
use xsist10\HaveIBeenPwned\Response\PasswordResponse;
use Psr\Log\NullLogger;

class HaveIBeenPwned
{
    private static $base_url = "https://haveibeenpwned.com/api/v3/";

    private static $password_url = "https://api.pwnedpasswords.com/";

    protected $adapter;

    /**
     * @var string|null
     */
    private $apiKey;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * HaveIBeenPwned constructor.
     *
     * @param Adapter|null         $adapter
     * @param null                 $apiKey
     * @param LoggerInterface|null $logger
     */
    public function __construct(Adapter $adapter = null, $apiKey = null, LoggerInterface $logger = null)
    {
        $this->adapter = $adapter;
        $this->apiKey = $apiKey;
        $this->logger = $logger ? $logger : new NullLogger();
    }

    /**
     * Return the adapter being used to connect to the remote server
     *
     * @return Adapter
     */
    protected function getAdapter()
    {
        // Backwards comparability as I won't bump the version number for this
        // yet. When I add PHP 7 support I'll bump it and remove this.
        if (!$this->adapter) {
            $this->adapter = new Curl($this->apiKey);
            $this->adapter->setLogger($this->logger);
        }

        return $this->adapter;
    }

    /**
     * Set a new adapter for the HaveIBeenPwned client
     *
     * @param Adapter $adapter Which adapter to use?
     */
    public function setAdapter(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    protected function get($url)
    {
        $body = $this->getAdapter()->get(self::$base_url . $url);

        return json_decode($body ? $body : '[]', true);
    }

    public function checkAccount($account, $allData = false)
    {
        $queryParams = '';
        if ($allData) {
            $queryParams = '?truncateResponse=false';
        }

        return new AccountResponse($this->get("breachedaccount/" . urlencode($account) . $queryParams));
    }

    public function getBreaches()
    {
        $breachArray = [];
        $result = $this->get("breaches");
        foreach ($result as $breach) {
            $breachArray[] = new BreachResponse($breach);
        }

        return $breachArray;
    }

    public function getBreach($name)
    {
        return new BreachResponse($this->get("breach/" . urlencode($name)));
    }

    public function getDataClasses()
    {
        return new DataClassResponse($this->get("dataclasses"));
    }

    public function getPasteAccount($account)
    {
        $pasteArray = [];
        $result = $this->get("pasteaccount/" . urlencode($account));
        foreach ($result as $paste) {
            $pasteArray[] = new PasteResponse($paste);
        }

        return $pasteArray;
    }

    public function isPasswordCompromised($password)
    {
        $sha1 = strtoupper(sha1($password));
        $fragment = substr($sha1, 0, 5);

        $body = $this->getAdapter()->get(self::$password_url . "range/" . urlencode($fragment));

        return new PasswordResponse($body, $password);
    }
}
