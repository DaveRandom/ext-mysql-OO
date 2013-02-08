<?php

  namespace ExtMysql;

  class Connection {

    /**
     * @var string $host
     */
    private $host;

    /**
     * @var int $port
     */
    private $port;

    /**
     * @var string $socket
     */
    private $socket;

    /**
     * @var string $dbName
     */
    private $dbName;

    /**
     * @var resource $link
     */
    private $link;

    /**
     * @param string $query
     * @return bool
     */
    private function interceptSetNames($query) {
      return (bool) preg_match('/^\s*SET\s+NAMES\s+(?:.+?)\s*;?$/i', $query);
    }

    /**
     * @param string $host
     * @param string $dbName
     * @param int $port
     */
    public function __construct($host = NULL, $dbName = NULL, $port = NULL) {
      if (isset($host)) {
        $this->setHost($host);
      }
      if (isset($dbName)) {
        $this->setDBName($dbName);
      }
      if (isset($port)) {
        $this->setPort($port);
      }
    }

    /**
     * @param string $host
     */
    public function setHost($host) {
      $this->host = $host;
    }

    /**
     * @return string
     */
    public function getHost() {
      return $this->host;
    }

    /**
     * @param int $port
     */
    public function setPort($port) {
      $this->port = (int) $port;
    }

    /**
     * @return int
     */
    public function getPort() {
      return $this->port;
    }

    /**
     * @param string $socket
     */
    public function setSocket($socket) {
      $this->socket = $socket;
    }

    /**
     * @return string
     */
    public function getSocket() {
      return $this->socket;
    }

    /**
     * @param string $dbName
     * @throws \RuntimeException
     */
    public function setDBName($dbName) {
      $this->dbName = $dbName;

      if (isset($this->link)) {
        if (!@mysql_select_db($this->dbName, $this->link)) {
          $err = error_get_last();
          throw new \RuntimeException('Unable to set active database: '.mysql_error($this->link));
        }
      }
    }

    /**
     * @return string
     */
    public function getDBName() {
      return $this->dbName;
    }

    /**
     * @return resource
     */
    public function getLink() {
      return $this->link;
    }

    /**
     * @param string $charSet
     * @throws \LogicException
     * @throws \RuntimeException
     */
    public function setCharset($charSet) {
      if (!isset($this->link)) {
        throw new \LogicException('Cannot set the character set of an inactive connection');
      }

      if (!@mysql_set_charset($charSet, $this->link)) {
        $err = error_get_last();
        throw new \RuntimeException('Unable to set connection character set: '.mysql_error($this->link));
      }
    }

    /**
     * @param string $user
     * @param string $pass
     * @throws \RuntimeException
     */
    public function connect($user, $pass) {
      $host = $this->host;
      if ($this->port != 3306) {
        $this->host .= ':'.$this->port;
      } else if (isset($this->socket)) {
        $this->host .= ':'.$this->socket;
      }

      $link = @mysql_connect($host, $user, $pass);
      if (!$link) {
        $err = error_get_last();
        throw new \RuntimeException('Unable to connect to database: '.$err['message']);
      }

      if (isset($this->dbName)) {
        if (!@mysql_select_db($this->dbName, $link)) {
          $err = error_get_last();
          throw new \RuntimeException('Unable to set active database: '.mysql_error($link));
        }
      }

      $this->link = $link;
    }

    public function close() {
      if ($this->link) {
        mysql_close($this->link);
        $this->link = NULL;
      }
    }

    /**
     * @param string $query
     * @throws \LogicException
     * @throws \InvalidArgumentException
     * @return ExtMysqlStatement
     */
    public function prepare($query) {
      if (!isset($this->link)) {
        throw new \LogicException('Database must be connected before a query can be prepared');
      }

      if ($this->interceptSetNames($query)) {
        throw new \InvalidArgumentException('Use setCharset() instead of SET NAMES query');
      }

      return new ExtMysqlStatement($this, $query);
    }

    /**
     * @param string $query
     * @throws \LogicException
     * @throws \InvalidArgumentException
     * @return ExtMysqlStatement
     */
    public function query($query) {
      if (!isset($this->link)) {
        throw new \LogicException('Database must be connected before a query can be executed');
      }

      if ($this->interceptSetNames($query)) {
        throw new \InvalidArgumentException('Use setCharset() instead of SET NAMES query');
      }

      return new ExtMysqlStatement($this, $query, TRUE);
    }

    /**
     * @return int
     */
    public function errNo() {
      return mysql_errno($this->link);
    }

    /**
     * @return string
     */
    public function errStr() {
      return mysql_error($this->link);
    }

  }
