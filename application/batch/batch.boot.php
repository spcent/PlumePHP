<?php

PlumePHP::app()->path(PLUME_PHP_PATH . DS . 'library' . DS . 'core');

class batch_base_cmd
{
    protected function log(string $msg, string $level = 'INFO'): void
    {
        echo '[' . date('H:i:s') . "][{$level}] {$msg}" . PHP_EOL;
        L($msg, [], $level);
    }

    protected function confirm(string $question): bool
    {
        echo $question . ' [y/N] ';
        $answer = strtolower(trim(fgets(STDIN)));
        return $answer === 'y' || $answer === 'yes';
    }
}
