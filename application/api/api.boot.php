<?php

PlumePHP::app()->path(PLUME_PHP_PATH . DS . 'library' . DS . 'core');

abstract class api_base_action extends \Plume\Libs\Action
{
    protected $csrfValidate = false;
    protected $requireAuth  = true;

    protected ?array $authUser = null;

    public function init(): bool
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->requireAuth) {
            return true;
        }

        $token = $this->getBearerToken();
        if (!$token) {
            $this->error('Missing Authorization header', 401, true);
        }

        $payload = authcode($token, 'DECODE', C('API_TOKEN_SECRET') ?: 'plume-api-secret');
        if (!$payload) {
            $this->error('Invalid or expired token', 401, true);
        }

        $data = json_decode($payload, true);
        if (!$data || empty($data['uid']) || $data['exp'] < time()) {
            $this->error('Token expired', 401, true);
        }

        $this->authUser = $data;
        return true;
    }

    public function execute()
    {
        if ($this->init()) {
            return $this->invoke();
        }
    }

    abstract public function invoke();

    private function getBearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (strncasecmp($header, 'Bearer ', 7) === 0) {
            return substr($header, 7);
        }
        return null;
    }

    protected function makeToken(int $uid, string $name, int $ttl = 86400 * 7): string
    {
        $payload = json_encode([
            'uid'  => $uid,
            'name' => $name,
            'exp'  => time() + $ttl,
        ]);
        return authcode($payload, 'ENCODE', C('API_TOKEN_SECRET') ?: 'plume-api-secret', $ttl);
    }
}
