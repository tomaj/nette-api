<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\DI;

use Nette\Bridges\ApplicationDI\ApplicationExtension;
use Nette\Bridges\HttpDI\HttpExtension;
use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\Extensions\InjectExtension;
use Nette\DI\Config\Adapters\NeonAdapter;
use Tomaj\NetteApi\DI\NetteApiExtension;

/**
 * Compiles a real DI container from a neon string, the same way an application does
 */
final class ContainerFactory
{
    private static int $counter = 0;

    public static function compile(string $neon, ?NetteApiExtension $extension = null, bool $application = false): string
    {
        $compiler = new Compiler();
        $compiler->addExtension('http', new HttpExtension());
        $compiler->addExtension('inject', new InjectExtension());
        if ($application) {
            $compiler->addExtension('application', new ApplicationExtension(scanDirs: []));
        }
        $compiler->addExtension('api', $extension ?? new NetteApiExtension());
        $file = tempnam(sys_get_temp_dir(), 'api-neon');
        assert(is_string($file));
        file_put_contents($file, $neon);
        try {
            $compiler->addConfig(new NeonAdapter()->load($file));
        } finally {
            unlink($file);
        }
        $compiler->setClassName('ApiTestContainer' . ++self::$counter . '_' . bin2hex(random_bytes(4)));
        return $compiler->compile();
    }

    public static function create(string $neon, ?NetteApiExtension $extension = null, bool $application = false): Container
    {
        $code = self::compile($neon, $extension, $application);
        preg_match('~class (ApiTestContainer\w+)~', $code, $m);
        eval($code);
        $class = $m[1];
        $container = new $class();
        assert($container instanceof Container);
        return $container;
    }
}
