<?php

  namespace ExtMysql;

  class Statement {

    const BIND_AUTO    = 0;
    const BIND_STRING  = 1;
    const BIND_NUMBER  = 2;
    const BIND_NULL    = 4;

    const FETCH_ASSOC  = 1;
    const FETCH_NUM    = 2;
    const FETCH_BOTH   = 4;
    const FETCH_OBJECT = 8;

    /**
     * @const Connection $connection
     */
    private $connection;

    /**
     * @var array $queryParts
     */
    private $queryParts = array();

    /**
     * @var array $placeHolders
     */
    private $placeHolders = array();

    /**
     * @var array $boundRefs
     */
    private $boundRefs = array();

    /**
     * @var resource $result
     */
    private $result;

    /**
     * @var int $rowCount
     */
    private $rowCount;

    /**
     * @var string $resultClass
     */
    private $resultClass;

    /**
     * @var int $defaultFetchMode
     */
    private $defaultFetchMode = self::FETCH_ASSOC;

    /**
     * @param Connection $connection
     * @param string $query
     * @param bool $execute
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \RuntimeException
     */
    public function __construct(Connection $connection, $query, $execute = FALSE) {
      $this->connection = $connection;
      if ($execute) {
        $this->queryParts[] = $query;
        $this->execute();
      } else {
        $this->prepareQuery($query);
      }
    }

    /**
     * @param string $token
     * @return bool
     */
    private function isQuotedString($token) {
      return in_array($token[0], array('"', "'")) && $token[0] == $token[strlen($token) - 1];
    }

    /**
     * @param string $token
     */
    private function prepareToken($token) {
      if ($this->isQuotedString($token)) {
        $this->queryParts[] = $token;
      } else {
        $expr = '/(:[a-z]\w+)/i';
        $parts = preg_split($expr, $token, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        foreach ($parts as $part) {
          if ($part[0] == ':') {
            $name = ltrim($part, ':');
            $this->placeHolders[$name] = '';
            $this->queryParts[] = &$this->placeHolders[$name];
          } else {
            $this->queryParts[] = $part;
          }
        }
      }
    }

    /**
     * @param string $query
     */
    private function prepareQuery($query) {
      $expr = '/("(?:[^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\'(?:[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)\')/';
      $tokens = preg_split($expr, $query, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

      foreach ($tokens as $token) {
        $this->prepareToken($token);
      }
    }

    /**
     * @param mixed $value
     * @param int $mode
     * @return string
     */
    private function sanitizeParam($value, $mode) {
      if ($mode == self::BIND_AUTO) {
        if (is_int($value) || is_float($value) || is_bool($value)) {
          $mode = self::BIND_NUMBER;
        } else if ($value === NULL) {
          $mode = self::BIND_NULL;
        } else {
          $mode = self::BIND_STRING;
        }
      }

      switch ($mode) {
        case self::BIND_NUMBER:
          if (is_int($value) || is_float($value)) {
            $result = $value;
          } else if (is_bool($value)) {
            $result = (int) $value;
          } else {
            $result = (float)(string) $value; // force invokation of __toString on objects
          }
          return rtrim($result, '.0');

        case self::BIND_NULL:
          return 'NULL';

        case self::BIND_STRING:
        default:
          return "'".mysql_real_escape_string($value, $this->connection->getLink())."'";
      }
    }

    /**
     * @param string $name
     * @param mixed $value
     * @param int $mode
     * @throws \InvalidArgumentException
     */
    public function bindValue($name, $value, $mode = self::BIND_AUTO) {
      $name = ltrim($name, ':');
      if (!isset($this->placeHolders[$name])) {
        throw new \InvalidArgumentException('Unknown parameter name '.$name);
      }

      unset($this->boundRefs[$name]);
      $this->placeHolders[$name] = $this->sanitizeParam($value, $mode);
    }

    /**
     * @param string $name
     * @param mixed $var
     * @param int $mode
     * @throws \InvalidArgumentException
     */
    public function bindParam($name, &$var, $mode = self::BIND_AUTO) {
      $name = ltrim($name, ':');
      if (!isset($this->placeHolders[$name])) {
        throw new \InvalidArgumentException('Unknown parameter name '.$name);
      }

      $this->boundRefs[$name] = array(&$var, $mode);
    }

    /**
     * @param array $params
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \RuntimeException
     */
    public function execute(array $params = array()) {
      foreach ($this->boundRefs as $name => $value) {
        $this->bindValue($name, $value[0], $value[1]);
      }

      foreach ($params as $name => $value) {
        $this->bindValue($name, $value);
      }

      $missing = array_search('', $this->placeHolders, TRUE);
      if ($missing !== FALSE) {
        throw new \LogicException('Cannot execute query, no value bound to parameter '.$missing);
      }

      $link = $this->connection->getLink();
      $query = implode('', $this->queryParts);

      $result = @mysql_query($query, $link);
      if (!$result) {
        throw new \RuntimeException('Query execution failed: '.mysql_error($link));
      }

      if (is_resource($result)) {
        $this->rowCount = mysql_num_rows($result);
      } else {
        $this->rowCount = mysql_affected_rows($link);
      }

      $this->result = $result;
    }

    /**
     * @return int
     */
    public function rowCount() {
      return $this->rowCount;
    }

    /**
     * @param int $mode
     * @return array|object
     * @throws \LogicException
     */
    public function fetch($mode = NULL) {
      if (!isset($this->result)) {
        throw new \LogicException('Cannot fetch results from an unexecuted or closed statement');
      } else if (!$this->result) {
        throw new \LogicException('Cannot fetch results from an unsuccessful statement');
      } else if (!is_resource($this->result)) {
        throw new \LogicException('Statement did not return a result set to fetch');
      }

      if (!isset($mode)) {
        $mode = $this->defaultFetchMode;
      }

      switch ($mode) {
        case self::FETCH_NUM:
          return mysql_fetch_row($this->result);

        case self::FETCH_BOTH:
          return mysql_fetch_array($this->result, MYSQL_BOTH);

        case self::FETCH_OBJECT:
          return mysql_fetch_object($this->result, $this->resultClass);

        case self::FETCH_ASSOC:
        default:
          return mysql_fetch_assoc($this->result);
      }
    }

    /**
     * @param int $mode
     * @return array
     * @throws \LogicException
     */
    public function fetchAll($mode = NULL) {
      $this->seek(0);

      $rows = array();
      while ($row = $this->fetch($mode)) {
        $rows[] = $row;
      }

      $this->close();

      return $rows;
    }

    /**
     * @param int $columnNumber
     * @return mixed
     * @throws \LogicException
     */
    public function fetchColumn($columnNumber = 0) {
      $row = $this->fetch(self::FETCH_NUM);
      return isset($row[$columnNumber]) ? $row[$columnNumber] : FALSE;
    }

    /**
     * @param int $row
     * @throws \LogicException
     */
    public function seek($row) {
      if (!isset($this->result)) {
        throw new \LogicException('Cannot seek results from an unexecuted or closed statement');
      } else if (!$this->result) {
        throw new \LogicException('Cannot seek results from an unsuccessful statement');
      } else if (!is_resource($this->result)) {
        throw new \LogicException('Statement did not return a result set to seek');
      }

      mysql_data_seek($this->result, $row);
    }

    public function close() {
      if (isset($this->result) && is_resource($this->result)) {
        mysql_free_result($this->result);
      }
      $this->result = NULL;
    }

    /**
     * @return resource
     */
    public function getResult() {
      return $this->result;
    }

    /**
     * @param string $className
     */
    public function setResultClass($className) {
      $this->resultClass = $resultClass;
    }

    /**
     * @return string
     */
    public function getResultClass() {
      return $this->resultClass;
    }

    /**
     * @param int $mode
     */
    public function setDefaultFetchMode($mode) {
      $this->defaultFetchMode = $mode;
    }

    /**
     * @return int
     */
    public function getDefaultFetchMode() {
      return $this->defaultFetchMode;
    }

  }
