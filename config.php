<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 站点配置
    |--------------------------------------------------------------------------
    */

    // 站点名称
    'appName' => 'geekSubcribeX',

    // 站点地址
    'baseUrl' => 'https://sspanel',

    // 订阅地址
    'subUrl'  => 'https://sspanel/link/',

    // 单端口多用户混淆参数后缀，可以随意修改，但请保持前后端一致
    'mu_suffix' => 'microsoft.com',

    // 单端口多用户混淆参数表达式，%5m代表取用户特征 md5 的前五位，%id 代表用户id, %suffix 代表上面这个后缀
    'mu_regex' => '%5m%id.%suffix',

    // 不安全中转模式，这个开启之后使用除了 auth_aes128_md5 或者 auth_aes128_sha1 以外的协议地用户也可以设置和使用中转
    'relay_insecure_mode' => false,

    /*
    |--------------------------------------------------------------------------
    | 数据库配置
    |--------------------------------------------------------------------------
    */

    'database' => [
        'driver'    => 'mysql',
        'host'      => '127.0.0.1',
        'port'      => 3306,
        'database'  => 'sspanel',
        'username'  => 'sspanel',
        'password'  => 'sspanel',
        'charset'   => 'utf8',
        'collation' => 'utf8_general_ci',
        'prefix'    => ''
    ],

    /*
    |--------------------------------------------------------------------------
    | 订阅配置
    |--------------------------------------------------------------------------
    */

    'SUB' => [

        // 订阅记录
        'Log'      => false,

        // 合并订阅
        'mergeSub' => true,

        // 订阅中的营销信息
        'sub_message'           => [],

        // 关闭 SS/SSR 单端口节点的端口显示
        'disable_sub_mu_port'   => true,

        // 为 SS 节点名称中添加站点名
        'add_appName_to_ss_uri' => false,

        // Clash 默认配置方案
        'Clash_DefaultProfiles'     => 'default',

        // Surge 默认配置方案
        'Surge_DefaultProfiles'     => 'default',

        // Surge2 默认配置方案
        'Surge2_DefaultProfiles'    => 'default',

        // Surfboard 默认配置方案
        'Surfboard_DefaultProfiles' => 'default',
    ],


    /*
    |--------------------------------------------------------------------------
    | 服务配置
    |--------------------------------------------------------------------------
    */

    'SERVER_NAME' => "geekSubcribeX",
    'MAIN_SERVER' => [
        'LISTEN_ADDRESS' => '0.0.0.0',
        'PORT'           => 9501,
        'SERVER_TYPE'    => EASYSWOOLE_WEB_SERVER, //可选为 EASYSWOOLE_SERVER  EASYSWOOLE_WEB_SERVER EASYSWOOLE_WEB_SOCKET_SERVER,EASYSWOOLE_REDIS_SERVER
        'SOCK_TYPE'      => SWOOLE_TCP,
        'RUN_MODEL'      => SWOOLE_PROCESS,
        'SETTING'        => [
            'worker_num'    => 8,
            'reload_async'  => true,
            'max_wait_time' => 3
        ],
        'TASK'           => [
            'workerNum'     => 4,
            'maxRunningNum' => 128,
            'timeout'       => 15
        ]
    ],
    'TEMP_DIR' => null,
    'LOG_DIR'  => null,
];
