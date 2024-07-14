<?php

namespace Solo\Database;

use PDO;
use PDOStatement;

class DatabaseCore
{
    protected PDO $pdo;
    protected PDOStatement $pdoStatement;

    private const DSN_PATTERNS = [
        'mysql' => 'mysql:host=%s;port=%d;dbname=%s',
        'pgsql' => 'pgsql:host=%s;port=%d;dbname=%s',
        'dblib' => 'dblib:host=%s:%d;dbname=%s',
        'mssql' => 'sqlsrv:Server=%s,%d;Database=%s',
        'cubrid' => 'cubrid:host=%s;port=%d;dbname=%s'
    ];

    private string $logLocation = __DIR__ . '/logs';
    private bool $logErrors = true;

    public function connect(string $hostname, string $username, string $password, string $dbname, string $type = 'mysql', int $port = 3306, array $options = []): self
    {
        $dsn = sprintf(self::DSN_PATTERNS[$type], $hostname, $port, $dbname);
        if ($type === 'mysql') {
            $options[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
            $options[PDO::ATTR_EMULATE_PREPARES] = true;
        }
        try {
            $this->pdo = new PDO($dsn, $username, $password, $options);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            $this->error($e->getMessage());
        }
        return $this;
    }

    public function setLogLocation(string $location): self
    {
        $this->logLocation = $location;
        $this->ensureLogLocationExists();
        return $this;
    }

    public function setLogErrors(bool $logErrors): self
    {
        $this->logErrors = $logErrors;
        return $this;
    }

    protected function ensureLogLocationExists(): void
    {
        if (!is_dir($this->logLocation)) {
            mkdir($this->logLocation, 0777, true);
            file_put_contents($this->logLocation . '/.htaccess', "order deny,allow\ndeny from all");
        }
    }

    /**
     * @throws \Exception
     */
    protected function error(string $message): void
    {
        if ($this->logErrors) {
            $logString = sprintf("[%s] Error: %s%s----------------------%s", date('d/m/Y H:i:s'), $message, PHP_EOL, PHP_EOL);
            file_put_contents($this->logLocation . '/sql.txt', $logString, FILE_APPEND);
        }
        throw new \Exception($message);
    }
}
