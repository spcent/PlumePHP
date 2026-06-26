<?php

declare(strict_types=1);

/**
 * Resolves a sequence of URL path segments to an action file on the filesystem.
 *
 * Maintains a per-process static cache of file_exists() results so that repeated
 * stat() calls for the same paths are avoided in long-lived Worker processes.
 */
class ActionLocator
{
    /** @var array<string, bool> Per-process file-existence cache */
    private static array $pathCache = [];

    /**
     * Walk URL path segments starting after the module segment (index 1) and
     * find the deepest action file that matches.
     *
     * Directory segments are followed; the first segment whose name matches an
     * existing `.action.php` file terminates the walk.  A trailing empty segment
     * maps to `index.action.php`.
     *
     * @param string   $module      Module name
     * @param string[] $pathnames   All URL segments (index 0 is always empty for leading /)
     * @param string   $requestUri  Original REQUEST_URI (used in 404 messages)
     *
     * @return array{file: string, actionFile: string, stopIndex: int}
     *
     * @throws never — calls PlumePHP::app()->_halt(404, …) on any mismatch
     */
    public static function locate(string $module, array $pathnames, string $requestUri): array
    {
        $namecount = count($pathnames);
        $filepath  = APP_PATH . DS . $module . DS . 'actions';
        $file      = '';
        $preg      = '/^([a-z]+)[a-z0-9_]*$/i';
        $stopIndex = 1;

        for ($index = 2; $index < $namecount; $index++) {
            $name = trim($pathnames[$index]);
            $stopIndex = $index;

            // Validate segment: only alphanumeric + underscore, starts with letter
            if ($name !== '' && (!preg_match($preg, $name) || strlen($name) > 15)) {
                throw new ActionException(404,
                    "!!! 404(invalid) !!! uri: {$requestUri}, name: {$name}");
            }

            // Empty segment → look for index.action.php in current directory
            if ($name === '') {
                $indexPath = $filepath . DS . 'index.action.php';
                if (!(self::$pathCache[$indexPath] ??= file_exists($indexPath))) {
                    throw new ActionException(404,
                        "!!! 404(missing index) !!! uri: {$requestUri}");
                }
                $file .= DS . 'index';
                break;
            }

            // Try to descend into a subdirectory named $name
            $dirPath = $filepath . DS . $name;
            if (self::$pathCache[$dirPath] ??= file_exists($dirPath)) {
                $filepath .= DS . $name;
                $file     .= DS . $name;
                continue;
            }

            // Try $name as an action file
            $filePath = $dirPath . '.action.php';
            if (self::$pathCache[$filePath] ??= file_exists($filePath)) {
                $file .= DS . $name;
                break;
            }

            throw new ActionException(404,
                "!!! 404 !!! uri={$requestUri} parseto:" . substr($filePath, strlen(APP_PATH)));
        }

        $file = trim($file, DS);
        if ($file === '') {
            $file = 'index';
        }

        $actionFile = APP_PATH . DS . $module . DS . 'actions' . DS . $file . '.action.php';
        if (!(self::$pathCache[$actionFile] ??= file_exists($actionFile))) {
            throw new ActionException(404,
                "!!! 404(missing action file) !!! uri: {$requestUri}"
                . ' action file: ' . substr($actionFile, strlen(APP_PATH)));
        }

        return [
            'file'       => $file,
            'actionFile' => $actionFile,
            'stopIndex'  => $stopIndex,
        ];
    }

    /**
     * Pre-warm or invalidate the path cache (useful in tests).
     *
     * @param array<string, bool>|null $entries Pass null to clear the entire cache
     */
    public static function warmCache(?array $entries = null): void
    {
        if ($entries === null) {
            self::$pathCache = [];
        } else {
            self::$pathCache = array_merge(self::$pathCache, $entries);
        }
    }
}
