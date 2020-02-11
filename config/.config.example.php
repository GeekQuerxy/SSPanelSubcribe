<?php

// 基本设置
$_ENV['key']                    = '1145141919810';                  // !!! 瞎 jb 修改此key为随机字符串确保网站安全 !!!
$_ENV['debug']                  =  false;                           // 正式环境请确保为 false

$_ENV['appName']                = 'sspanel';                        // 站点名称
$_ENV['baseUrl']                = 'http://url.com';                 // 站点地址
$_ENV['subUrl']                 = $_ENV['baseUrl'] . '/link/';      // 订阅地址，如需和站点名称相同，请不要修改

// 数据库设置
$_ENV['db_driver']              = 'mysql';                          // 数据库程序
$_ENV['db_host']                = 'localhost';                      // 数据库地址
$_ENV['db_database']            = 'sspanel';                        // 数据库名
$_ENV['db_username']            = 'root';                           // 数据库用户名
$_ENV['db_password']            = 'sspanel';                        // 用户名对应的密码
$_ENV['db_charset']             = 'utf8';
$_ENV['db_collation']           = 'utf8_general_ci';
$_ENV['db_prefix']              = '';

// 单端混淆设置
$_ENV['mu_suffix']              = 'microsoft.com';                  // 单端口多用户混淆参数后缀，可以随意修改，但请保持前后端一致
$_ENV['mu_regex']               = '%5m%id.%suffix';                 // 单端口多用户混淆参数表达式，%5m代表取用户特征 md5 的前五位，%id 代表用户id,%suffix 代表上面这个后缀。

// 订阅中的公告信息
// 使用数组形式，将会添加在订阅列表的顶端
// 可用于为用户推送最新地址等信息，尽可能简短且数量不宜太多
$_ENV['sub_message']            = [];

// 订阅记录设置
$_ENV['subscribeLog']           = false;                            // 是否记录用户订阅日志

// 订阅配置
$_ENV['mergeSub']               = true;                             // 合并订阅设置 可选项 false / true
$_ENV['enable_sub_extend']      = true;                             // 是否开启订阅中默认显示流量剩余以及账户到期时间以及 sub_message 中的信息
$_ENV['disable_sub_mu_port']    = false;                            // 将订阅中单端口的信息去除
$_ENV['add_emoji_to_node_name'] = false;                            // 为部分订阅中默认添加 emoji
$_ENV['add_appName_to_ss_uri']  = true;                             // 为 SS 节点名称中添加站点名

// 订阅缓存设置
$_ENV['enable_sub_cache']       = false;                            // 订阅信息缓存
$_ENV['sub_cache_time']         = 360;                              // 订阅信息缓存有效时间 (分钟)
$_ENV['sub_cache_max_quantity'] = 15;                               // 每个用户订阅缓存最大数量，请基于磁盘空间考虑

// 不安全中转模式，这个开启之后使用除了 auth_aes128_md5 或者 auth_aes128_sha1 以外的协议地用户也可以设置和使用中转
$_ENV['relay_insecure_mode']    = false;                            // 强烈推荐不开启

// 其他设置
$_ENV['timeZone']               = 'PRC';                            // PRC 天朝时间  UTC 格林时间
$_ENV['pwdMethod']              = 'md5';                            // 密码加密 可选 md5, sha256, bcrypt, argon2i, argon2id（argon2i需要至少php7.2）
$_ENV['salt']                   = '';                               // 推荐配合 md5/sha256， bcrypt/argon2i/argon2id 会忽略此项

// 在套了CDN之后获取用户真实ip，如果您不知道这是什么，请不要乱动
if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $_SERVER['REMOTE_ADDR'] = $list[0];
}

// make replace _ENV with env
function findKeyName($name)
{
    global $_ENV;
    foreach ($_ENV as $configKey => $configValue) {
        if (strtoupper($configKey) == $name) {
            return $configKey;
        }
    }

    return NULL;
}

foreach (getenv() as $envKey => $envValue) {
    global $_ENV;
    $envUpKey = strtoupper($envKey);
    // Key starts with UIM_
    if (substr($envUpKey, 0, 4) == "UIM_") {
        // Vaild env key, set to _ENV
        $configKey = substr($envUpKey, 4);
        $realKey = findKeyName($configKey);
        if ($realKey != NULL) {
            $_ENV[$realKey] = $envValue;
        }
    }
}
