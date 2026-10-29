<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Error;

use Tracy\Logger;
use Tracy\Debugger;

/**
 * Points Tracy logger to a temporary directory and reads what was logged
 */
trait LogDirectory
{
    private string $logDirectory;

    private ?string $previousLogDirectory;

    protected function setUp(): void
    {
        $this->previousLogDirectory = Debugger::$logDirectory;
        $this->logDirectory = sys_get_temp_dir() . '/nette-api-log-' . bin2hex(random_bytes(4));
        mkdir($this->logDirectory);
        Debugger::$logDirectory = $this->logDirectory;
        Debugger::setLogger(new Logger($this->logDirectory));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->logDirectory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->logDirectory);
        Debugger::$logDirectory = $this->previousLogDirectory;
    }

    private function logged(string $level): string
    {
        $file = $this->logDirectory . '/' . $level . '.log';
        return is_file($file) ? (string) file_get_contents($file) : '';
    }
}
