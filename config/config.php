<?php if (!defined('PLUME_PHP_PATH')) exit('No direct script access allowed');

if (!function_exists('DB')) {
    /**
     * DB方法，返回数据库Medoo实例，方便使用db
     * @param array|string $options 如果为string，则是键值
     * @return Medoo
     */
    function DB($options = [])
    {
        static $_instance = [];
        $conf = [];
        $cfgdb = C('DB_CONF');
        if (count($cfgdb) < 1) throw new \Exception('db config error');
        if (!empty($options) || is_string($options)) {
            $key = $options;
        } else {
            $key = empty($options['key']) ? key($cfgdb) : $options['key'];
        }

        if (isset($_instance[$key])) {
            return $_instance[$key];
        }

        $defaultConf = $cfgdb[$key];
        $conf['port'] = isset($options['db_port']) ? $options['db_port'] : $defaultConf['db_port'];
        $conf['charset'] = isset($options['db_charset']) ? $options['db_charset'] : $defaultConf['db_charset'];
        $conf['database_type'] = 'mysql';
        $conf['database_name'] = isset($options['db_name']) ? $options['db_name'] : $defaultConf['db_name'];
        $conf['server'] = isset($options['db_server']) ? $options['db_server'] : $defaultConf['db_server'];
        $conf['username'] = isset($options['db_user']) ? $options['db_user'] : $defaultConf['db_user'];
        $conf['password'] = isset($options['db_password']) ? $options['db_password'] : $defaultConf['db_password'];
        $conf['prefix'] = isset($options['db_prefix']) ? $options['db_prefix'] : $defaultConf['db_prefix'];
        $_instance[$key] = new \Plume\Libs\Medoo($conf);
        return $_instance[$key];
    }
}

return [
    'USE_SESSION' => TRUE,                      // 是否开启session，默认false
    'TIME_ZONE' => 'Asia/Shanghai',
    //[虚拟目录]	如果应用是部署在虚拟目录下，则指定虚拟目录的名字，否则请保持为空
    'VDNAME' => '',
    'JSSDK_VERSION' => '201710290813',
    'ASSETS_VERSION' => '20180205',
    'DB_CONF' => [
        'master'=>[
            'db_server'   => '127.0.0.1',               // 数据库主机地址
            'db_port'     => '3306',                    // 数据库端口，默认为3306
            'db_user'     => 'root',                    // 数据库用户名
            'db_password' => 'root',                    // 数据库密码
            'db_name'     => 'beijing_coins',           // 数据库名
            'db_charset'  => 'utf8mb4',                 // 数据库编码，默认utf8mb4，为了支持emoji表情
            'db_prefix'   => 'bj_',                     // 前缀
        ]
    ],
];
