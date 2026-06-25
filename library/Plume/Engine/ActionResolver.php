<?php

declare(strict_types=1);

/**
 * Parses a REQUEST_URI into module, URL path, path segments, and initial GET args.
 * Handles virtual-directory prefix stripping and path-alias rewriting.
 */
class ActionResolver
{
    /**
     * @param string     $requestUri Full REQUEST_URI (may include query string)
     * @param string     $vdname     Virtual-directory prefix (config VDNAME)
     * @param array|null $pathAlias  Path-alias map (config PATH_ALIAS)
     *
     * @return array{urlPath: string, pathnames: string[], args: array<string,string>}
     */
    public static function parse(string $requestUri, string $vdname = '', ?array $pathAlias = null): array
    {
        // Strip virtual-directory prefix
        if ($vdname !== '') {
            $urlPath = substr($requestUri, strlen('/' . $vdname));
            if ($urlPath === false) {
                $urlPath = $requestUri;
            }
        } else {
            $urlPath = $requestUri;
        }

        // Apply path aliases (first match wins)
        if (is_array($pathAlias)) {
            foreach ($pathAlias as $k => $v) {
                if (str_contains($urlPath, (string) $k)) {
                    $urlPath = str_replace((string) $k, (string) $v, $urlPath);
                    break;
                }
            }
        }

        // Extract and strip query string
        $args = [];
        $qpos = strpos($urlPath, '?');
        if ($qpos !== false) {
            parse_str(substr($urlPath, $qpos + 1), $args);
            $urlPath = substr($urlPath, 0, $qpos);
        }

        // Split into segments (max 64 to prevent abuse)
        $pathnames = explode('/', $urlPath, 64);

        return [
            'urlPath'   => $urlPath,
            'pathnames' => $pathnames,
            'args'      => $args,
        ];
    }

    /**
     * Extracts the module name from parsed path segments.
     * Falls back to $defaultModule when the first segment is empty or "index.php".
     */
    public static function extractModule(array $pathnames, string $defaultModule = 'web'): string
    {
        $first = trim($pathnames[1] ?? '');
        if ($first === '' || $first === 'index.php') {
            return $defaultModule;
        }
        return $first;
    }

    /**
     * Collects leftover URL segments (after the action segment) as key→value pairs
     * and merges them into $baseArgs.
     *
     * @param string[] $pathnames Full segment array
     * @param int      $stopIndex Index of the matched action segment
     * @param array    $baseArgs  Query-string args already parsed
     *
     * @return array Merged args
     */
    public static function collectTailArgs(array $pathnames, int $stopIndex, array $baseArgs): array
    {
        $count = count($pathnames);
        for ($i = $stopIndex + 1; $i < $count; $i += 2) {
            $k          = $pathnames[$i];
            $v          = ($i + 1 < $count) ? $pathnames[$i + 1] : null;
            $baseArgs[$k] = $v;
        }
        return $baseArgs;
    }
}
