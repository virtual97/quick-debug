<?php
require_once 'func.php';

/**
 * Autoload function
 *
 * @param string $class
 * @return bool
 */
function autoLoadFunc($class)
{
    $classPath = str_replace('_', '/', $class) . '.php';
    $paths = explode(PATH_SEPARATOR, get_include_path());
    foreach ($paths as $p) {
        $p = rtrim($p, '\\/');
        $filename = $p . DIRECTORY_SEPARATOR . $classPath;
        if (file_exists($filename)) {
            require_once $classPath;
            return true;
        }
    }
    $paths = implode("\n", $paths);
    qqq1("Class '$class' not found in paths: \n$paths", 0, 1);
    exit;
}
spl_autoload_register('autoLoadFunc'); // As of PHP 5.3.0
