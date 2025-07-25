<?php

// autoload_real.php @generated by Composer

class ComposerAutoloaderInit50a491f6a3fb7bab16e6f700d31ff1f9
{
    private static $loader;

    public static function loadClassLoader($class)
    {
        if ('Composer\Autoload\ClassLoader' === $class) {
            require __DIR__ . '/ClassLoader.php';
        }
    }

    /**
     * @return \Composer\Autoload\ClassLoader
     */
    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }

        require __DIR__ . '/platform_check.php';

        spl_autoload_register(array('ComposerAutoloaderInit50a491f6a3fb7bab16e6f700d31ff1f9', 'loadClassLoader'), true, true);
        self::$loader = $loader = new \Composer\Autoload\ClassLoader(\dirname(__DIR__));
        spl_autoload_unregister(array('ComposerAutoloaderInit50a491f6a3fb7bab16e6f700d31ff1f9', 'loadClassLoader'));

        require __DIR__ . '/autoload_static.php';
        call_user_func(\Composer\Autoload\ComposerStaticInit50a491f6a3fb7bab16e6f700d31ff1f9::getInitializer($loader));

        $loader->register(true);

        return $loader;
    }
}
