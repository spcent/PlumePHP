<?php

declare(strict_types=1);

/**
 * Resolves an action class name from a module name and file path.
 *
 * Supports two naming conventions side-by-side:
 *
 *   Legacy  : web_user_profile_action
 *   PSR-4   : App\Web\Actions\User\ProfileAction
 *
 * Checks class_exists() for each; falls back to the legacy name so that the
 * caller's error message is still human-readable.
 */
class ActionNaming
{
    /**
     * @param string $module Module name (e.g. "web")
     * @param string $file   Relative file path without extension (e.g. "user/profile")
     *
     * @return string The resolved class name, or the legacy name if neither exists
     */
    public static function resolve(string $module, string $file): string
    {
        $legacy = self::toLegacy($module, $file);
        $psr4   = self::toPsr4($module, $file);

        if (class_exists($legacy)) {
            return $legacy;
        }
        if (class_exists($psr4)) {
            return $psr4;
        }

        return $legacy;
    }

    /**
     * Build the legacy underscore class name.
     * e.g. module=web, file=user/profile → web_user_profile_action
     */
    public static function toLegacy(string $module, string $file): string
    {
        return $module . '_' . str_replace(DS, '_', $file) . '_action';
    }

    /**
     * Build the PSR-4 namespaced class name.
     * e.g. module=web, file=user/profile → App\Web\Actions\User\ProfileAction
     */
    public static function toPsr4(string $module, string $file): string
    {
        $segments = array_map('ucfirst', explode(DS, $file));
        return 'App\\' . ucfirst($module) . '\\Actions\\' . implode('\\', $segments) . 'Action';
    }
}
