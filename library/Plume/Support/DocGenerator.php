<?php

declare(strict_types=1);

/**
 * Lightweight OpenAPI 3.0 document generator for PlumePHP.
 *
 * Scans all action files in the application directory and parses PHPDoc
 * @api annotations to build an OpenAPI 3.0 JSON/YAML spec.
 *
 * Annotation format (inside any method docblock in an action class):
 *
 *   @api GET /api/users/{id}
 *   @summary Get a user by ID
 *   @tag users
 *   @param int $id User ID (path)
 *   @param string $fields Comma-separated field list (query)
 *   @response 200 {"code":0,"data":{"id":1,"name":"John"}}
 *   @response 404 {"code":404,"msg":"Not found"}
 *   @auth bearer
 *
 * CLI usage:
 *   php public/index.php -m doc -c generate --format json   > openapi.json
 *   php public/index.php -m doc -c generate --format yaml   > openapi.yaml
 *
 * Programmatic usage:
 *   $doc = PlumeDocGenerator::generate('/path/to/application');
 *   echo json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
 */
class PlumeDocGenerator
{
    /** @var array Accumulated OpenAPI paths */
    private array $paths = [];

    /** @var array Unique tag names */
    private array $tags = [];

    /**
     * Scan $appPath for action files, parse annotations, return OpenAPI array.
     *
     * @param string $appPath   Path to the application/ directory
     * @param array  $info      Override the OpenAPI info block
     *
     * @return array OpenAPI 3.0 document as a PHP array
     */
    public static function generate(string $appPath, array $info = []): array
    {
        $gen = new self();
        $gen->scanDirectory($appPath);
        return $gen->buildDocument($info);
    }

    /**
     * Scan a directory tree for *.action.php files and parse their annotations.
     */
    private function scanDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            if (!str_ends_with($file->getFilename(), '.action.php')) {
                continue;
            }

            $this->parseFile((string) $file->getRealPath());
        }
    }

    /**
     * Parse a single action file for @api annotations.
     */
    private function parseFile(string $path): void
    {
        $source = file_get_contents($path);
        if ($source === false) {
            return;
        }

        // Extract all docblocks that contain an @api tag
        if (!preg_match_all('/\/\*\*(.*?)\*\//s', $source, $blocks)) {
            return;
        }

        foreach ($blocks[1] as $block) {
            if (!str_contains($block, '@api')) {
                continue;
            }
            $this->parseDocblock($block);
        }
    }

    /**
     * Parse a single docblock into a path entry.
     */
    private function parseDocblock(string $block): void
    {
        // Clean up leading "* " from each line
        $lines = array_map(
            fn (string $l) => ltrim(ltrim($l), "* \t"),
            explode("\n", $block)
        );

        $method   = null;
        $path     = null;
        $summary  = '';
        $tag      = null;
        $params   = [];
        $responses = [];
        $security  = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // @api METHOD /path
            if (preg_match('/^@api\s+(GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS)\s+(\S+)/i', $line, $m)) {
                $method = strtolower($m[1]);
                $path   = $m[2];
                continue;
            }

            // @summary Short description
            if (preg_match('/^@summary\s+(.+)/', $line, $m)) {
                $summary = trim($m[1]);
                continue;
            }

            // @tag tagName
            if (preg_match('/^@tag\s+(\S+)/', $line, $m)) {
                $tag = $m[1];
                $this->tags[$tag] = true;
                continue;
            }

            // @param type $name Description (location)
            // location: path | query | header | cookie — default query
            if (preg_match('/^@param\s+(\S+)\s+\$(\S+)\s*(.*)?/', $line, $m)) {
                $paramType = $m[1];
                $paramName = $m[2];
                $rest      = trim($m[3] ?? '');

                // Check if location is specified in parentheses at end of description
                $in = 'query';
                if (preg_match('/\((\w+)\)\s*$/', $rest, $loc)) {
                    $in   = strtolower($loc[1]);
                    $rest = trim(substr($rest, 0, -strlen($loc[0])));
                }

                // Auto-detect path params from {paramName} in path
                if ($path && str_contains($path, '{' . $paramName . '}')) {
                    $in = 'path';
                }

                $params[] = [
                    'name'        => $paramName,
                    'in'          => $in,
                    'description' => $rest,
                    'required'    => ($in === 'path'),
                    'schema'      => ['type' => $this->mapType($paramType)],
                ];
                continue;
            }

            // @response statusCode {"example": "json"}
            if (preg_match('/^@response\s+(\d+)\s*(.*)/', $line, $m)) {
                $code    = (int) $m[1];
                $example = trim($m[2]);
                $decoded = $example ? @json_decode($example, true) : null;
                $responses[$code] = [
                    'description' => $this->defaultStatusDescription($code),
                    'content'     => [
                        'application/json' => [
                            'example' => $decoded ?? $example,
                        ],
                    ],
                ];
                continue;
            }

            // @auth bearer|apiKey|basic
            if (preg_match('/^@auth\s+(\S+)/', $line, $m)) {
                $scheme = strtolower($m[1]);
                $security[] = [$scheme => []];
            }
        }

        if ($method === null || $path === null) {
            return;
        }

        if (empty($responses)) {
            $responses[200] = ['description' => 'Success'];
        }

        $operation = array_filter([
            'summary'    => $summary ?: null,
            'tags'       => $tag ? [$tag] : [],
            'parameters' => $params ?: [],
            'responses'  => $responses,
            'security'   => $security ?: [],
        ]);

        $this->paths[$path][$method] = $operation;
    }

    /**
     * Build the final OpenAPI document array.
     */
    private function buildDocument(array $info): array
    {
        ksort($this->paths);

        return [
            'openapi' => '3.0.3',
            'info'    => array_merge([
                'title'       => 'PlumePHP API',
                'description' => 'Auto-generated from @api annotations',
                'version'     => defined('PLUME_VERSION') ? PLUME_VERSION : '1.0.0',
            ], $info),
            'tags'    => array_values(array_map(
                fn (string $name) => ['name' => $name],
                array_keys($this->tags)
            )),
            'paths'   => $this->paths,
            'components' => [
                'securitySchemes' => [
                    'bearer' => ['type' => 'http', 'scheme' => 'bearer'],
                    'apiKey' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-Api-Key'],
                    'basic'  => ['type' => 'http', 'scheme' => 'basic'],
                ],
            ],
        ];
    }

    /**
     * Map PHP type hints to OpenAPI schema types.
     */
    private function mapType(string $phpType): string
    {
        return match (strtolower($phpType)) {
            'int', 'integer'     => 'integer',
            'float', 'double'    => 'number',
            'bool', 'boolean'    => 'boolean',
            'array'              => 'array',
            default              => 'string',
        };
    }

    /**
     * Default description for common HTTP status codes.
     */
    private function defaultStatusDescription(int $code): string
    {
        return match ($code) {
            200 => 'Success',
            201 => 'Created',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            default => 'Response',
        };
    }
}
