<?php

namespace App\Utils;

use App\Models\User;
use EasySwoole\EasySwoole\Config;

class Tools
{
    /**
     * 获取连接信息的可用选项
     *
     * @param string $type
     */
    public static function getSupportParam($type): array
    {
        switch ($type) {
            case 'obfs':
                $list = array(
                    'plain',
                    'http_simple',
                    'http_simple_compatible',
                    'http_post',
                    'http_post_compatible',
                    'tls1.2_ticket_auth',
                    'tls1.2_ticket_auth_compatible',
                    'tls1.2_ticket_fastauth',
                    'tls1.2_ticket_fastauth_compatible',
                    'simple_obfs_http',
                    'simple_obfs_http_compatible',
                    'simple_obfs_tls',
                    'simple_obfs_tls_compatible'
                );
                return $list;
            case 'protocol':
                $list = array(
                    'origin',
                    'verify_deflate',
                    'auth_sha1_v4',
                    'auth_sha1_v4_compatible',
                    'auth_aes128_sha1',
                    'auth_aes128_md5',
                    'auth_chain_a',
                    'auth_chain_b',
                    'auth_chain_c',
                    'auth_chain_d',
                    'auth_chain_e',
                    'auth_chain_f'
                );
                return $list;
            case 'allow_none_protocol':
                $list = array(
                    'auth_chain_a',
                    'auth_chain_b',
                    'auth_chain_c',
                    'auth_chain_d',
                    'auth_chain_e',
                    'auth_chain_f'
                );
                return $list;
            case 'relay_able_protocol':
                $list = array(
                    'auth_aes128_md5',
                    'auth_aes128_sha1',
                    'auth_chain_a',
                    'auth_chain_b',
                    'auth_chain_c',
                    'auth_chain_d',
                    'auth_chain_e',
                    'auth_chain_f'
                );
                return $list;
            case 'ss_aead_method':
                $list = array(
                    'aes-128-gcm',
                    'aes-192-gcm',
                    'aes-256-gcm',
                    'chacha20-ietf-poly1305',
                    'xchacha20-ietf-poly1305'
                );
                return $list;
            case 'ss_obfs':
                $list = array(
                    'simple_obfs_http',
                    'simple_obfs_http_compatible',
                    'simple_obfs_tls',
                    'simple_obfs_tls_compatible'
                );
                return $list;
            default:
                $list = array(
                    'rc4-md5',
                    'rc4-md5-6',
                    'aes-128-cfb',
                    'aes-192-cfb',
                    'aes-256-cfb',
                    'aes-128-ctr',
                    'aes-192-ctr',
                    'aes-256-ctr',
                    'camellia-128-cfb',
                    'camellia-192-cfb',
                    'camellia-256-cfb',
                    'bf-cfb',
                    'cast5-cfb',
                    'des-cfb',
                    'des-ede3-cfb',
                    'idea-cfb',
                    'rc2-cfb',
                    'seed-cfb',
                    'salsa20',
                    'chacha20',
                    'xsalsa20',
                    'chacha20-ietf',
                    'aes-128-gcm',
                    'aes-192-gcm',
                    'none',
                    'aes-256-gcm',
                    'chacha20-ietf-poly1305',
                    'xchacha20-ietf-poly1305'
                );
                return $list;
        }
    }

    /**
     * 检测加密方式的允许范围
     *
     * - 1 SSR can
     * - 2 SS can
     * - 3 Both can
     *
     * @param string $method
     */
    public static function CanMethodConnect($method): int
    {
        $ss_aead_method_list = self::getSupportParam('ss_aead_method');
        if (in_array($method, $ss_aead_method_list)) {
            return 2;
        }
        return 3;
    }

    /**
     * 检测协议的允许范围
     *
     * - 1 SSR can
     * - 2 SS can
     * - 3 Both can
     *
     * @param string $protocol
     */
    public static function CanProtocolConnect($protocol): int
    {
        if ($protocol != 'origin') {
            if (strpos($protocol, '_compatible') === false) {
                return 1;
            }
            return 3;
        }
        return 3;
    }

    /**
     * 检测混淆的允许范围
     *
     * - 1 SSR can
     * - 2 SS can
     * - 3 Both can
     * - 4 Both can, But ssr need set obfs to plain
     * - 5 Both can, But ss need set obfs to plain
     *
     * @param string $obfs
     */
    public static function CanObfsConnect($obfs): int
    {
        if ($obfs != 'plain') {
            //SS obfs only
            $ss_obfs = self::getSupportParam('ss_obfs');
            if (in_array($obfs, $ss_obfs)) {
                if (strpos($obfs, '_compatible') === false) {
                    return 2;
                }
                return 4; //SSR need origin plain
            }
            if (strpos($obfs, '_compatible') === false) {
                return 1;
            }
            return 5; //SS need plain
        }
        return 3;
    }

    /**
     * 检测用户是否可连接 SS
     *
     * @param User $user
     * @param int          $mu_port
     */
    public static function SSCanConnect($user, $mu_port = 0): bool
    {
        if ($mu_port != 0) {
            $mu_user = User::where('port', '=', $mu_port)->where('is_multi_user', '<>', 0)->first();
            if ($mu_user == null) {
                return false;
            }
            return self::SSCanConnect($mu_user);
        }
        return self::CanMethodConnect($user->method) >= 2 && self::CanProtocolConnect($user->protocol) >= 2 && self::CanObfsConnect($user->obfs) >= 2;
    }

    /**
     * 检测用户是否可连接 SSR
     *
     * @param User $user
     * @param int          $mu_port
     */
    public static function SSRCanConnect($user, $mu_port = 0): bool
    {
        if ($mu_port != 0) {
            $mu_user = User::where('port', '=', $mu_port)->where('is_multi_user', '<>', 0)->first();
            if ($mu_user == null) {
                return false;
            }
            return self::SSRCanConnect($mu_user);
        }
        return self::CanMethodConnect($user->method) != 2 && self::CanProtocolConnect($user->protocol) != 2 && self::CanObfsConnect($user->obfs) != 2;
    }

    /**
     * 获取用户的 SS 连接信息
     *
     * @param User $user
     */
    public static function getSSConnectInfo($user): User
    {
        $new_user = clone $user;
        if (self::CanObfsConnect($new_user->obfs) == 5) {
            $new_user->obfs       = 'plain';
            $new_user->obfs_param = '';
        }
        if (self::CanProtocolConnect($new_user->protocol) == 3) {
            $new_user->protocol       = 'origin';
            $new_user->protocol_param = '';
        }
        $new_user->obfs     = str_replace('_compatible', '', $new_user->obfs);
        $new_user->protocol = str_replace('_compatible', '', $new_user->protocol);
        return $new_user;
    }

    /**
     * 获取用户的 SSR 连接信息
     *
     * @param User $user
     */
    public static function getSSRConnectInfo($user): User
    {
        $new_user = clone $user;
        if (self::CanObfsConnect($new_user->obfs) == 4) {
            $new_user->obfs       = 'plain';
            $new_user->obfs_param = '';
        }
        $new_user->obfs     = str_replace('_compatible', '', $new_user->obfs);
        $new_user->protocol = str_replace('_compatible', '', $new_user->protocol);
        return $new_user;
    }

    public static function parse_args($origin)
    {
        // parse xxx=xxx|xxx=xxx to array(xxx => xxx, xxx => xxx)
        $args_explode = explode('|', $origin);
        $return_array = [];
        foreach ($args_explode as $arg) {
            $split_point = strpos($arg, '=');

            $return_array[substr($arg, 0, $split_point)] = substr($arg, $split_point + 1);
        }
        return $return_array;
    }

    /**
     * @param $traffic
     * @return float
     */
    public static function flowToGB($traffic)
    {
        $gb = 1048576 * 1024;
        return $traffic / $gb;
    }

    public static function base64_url_encode($input)
    {
        return strtr(base64_encode($input), array('+' => '-', '/' => '_', '=' => ''));
    }

    public static function base64_url_decode($input)
    {
        return base64_decode(strtr($input, '-_', '+/'));
    }

    public static function pick_out_relay_rule($relay_node_id, $port, $ruleset)
    {
        $match_rule = null;
        foreach ($ruleset as $single_rule) {
            if (($single_rule->port == $port || $single_rule->port == 0) && ($single_rule->source_node_id == 0 || $single_rule->source_node_id == $relay_node_id)) {
                $has_higher_priority = false;
                foreach ($ruleset as $priority_rule) {
                    if (($priority_rule->port == $port || $priority_rule->port == 0) && ($priority_rule->source_node_id == 0 || $priority_rule->source_node_id == $relay_node_id)) {
                        if (($priority_rule->priority > $single_rule->priority && $priority_rule->id != $single_rule->id) || ($priority_rule->priority == $single_rule->priority && $priority_rule->id < $single_rule->id)) {
                            $has_higher_priority = true;
                            continue;
                        }
                    }
                }
                if ($has_higher_priority) {
                    continue;
                }
                $match_rule = $single_rule;
            }
        }
        if (($match_rule != null) && $match_rule->dist_node_id == -1) {
            return null;
        }
        return $match_rule;
    }

    public static function is_protocol_relay($user)
    {
        return true;

        $relay_able_list = self::getSupportParam('relay_able_protocol');

        return in_array($user->protocol, $relay_able_list) || Config::getInstance()->getConf('relay_insecure_mode') == true;
    }

    public static function v2Array($node)
    {
        $server = explode(';', $node);
        $item = [
            'host' => '',
            'path' => '',
            'tls' => '',
            'verify_cert' => true
        ];
        $item['add'] = $server[0];
        if ($server[1] == '0' || $server[1] == '') {
            $item['port'] = 443;
        } else {
            $item['port'] = (int) $server[1];
        }
        $item['aid'] = (int) $server[2];
        $item['net'] = 'tcp';
        $item['headerType'] = 'none';
        if (count($server) >= 4) {
            $item['net'] = $server[3];
            if ($item['net'] == 'ws') {
                $item['path'] = '/';
            } elseif ($item['net'] == 'tls') {
                $item['tls'] = 'tls';
            }
        }
        if (count($server) >= 5) {
            if (in_array($item['net'], array('kcp', 'http', 'mkcp'))) {
                $item['headerType'] = $server[4];
            } elseif ($server[4] == 'ws') {
                $item['net'] = 'ws';
            } elseif ($server[4] == 'tls') {
                $item['tls'] = 'tls';
            }
        }
        if (count($server) >= 6 && $server[5] != '') {
            $item = array_merge($item, self::parse_args($server[5]));
            if (array_key_exists('server', $item)) {
                $item['add'] = $item['server'];
                unset($item['server']);
            }
            if (array_key_exists('relayserver', $item)) {
                $item['localserver'] = $item['add'];
                $item['add'] = $item['relayserver'];
                unset($item['relayserver']);
                if ($item['tls'] == 'tls') {
                    $item['verify_cert'] = false;
                }
            }
            if (array_key_exists('outside_port', $item)) {
                $item['port'] = (int) $item['outside_port'];
                unset($item['outside_port']);
            }
            if (isset($item['inside_port'])) {
                unset($item['inside_port']);
            }
        }
        return $item;
    }

    public static function ssv2Array($node)
    {
        $server = explode(';', $node);
        $item = [
            'host' => 'microsoft.com',
            'path' => '',
            'net' => 'ws',
            'tls' => ''
        ];
        $item['add'] = $server[0];
        if ($server[1] == '0' || $server[1] == '') {
            $item['port'] = 443;
        } else {
            $item['port'] = (int) $server[1];
        }
        if (count($server) >= 4) {
            $item['net'] = $server[3];
            if ($item['net'] == 'ws') {
                $item['path'] = '/';
            } elseif ($item['net'] == 'tls') {
                $item['tls'] = 'tls';
            }
        }
        if (count($server) >= 5 && $server[4] == 'ws') {
            $item['net'] = 'ws';
        } elseif (count($server) >= 5 && $server[4] == 'tls') {
            $item['tls'] = 'tls';
        }
        if (count($server) >= 6) {
            $item = array_merge($item, self::parse_args($server[5]));
            if (array_key_exists('server', $item)) {
                $item['add'] = $item['server'];
                unset($item['server']);
            }
            if (array_key_exists('relayserver', $item)) {
                $item['add'] = $item['relayserver'];
                unset($item['relayserver']);
            }
            if (array_key_exists('outside_port', $item)) {
                $item['port'] = (int) $item['outside_port'];
                unset($item['outside_port']);
            }
        }
        if ($item['net'] == 'obfs') {
            if (stripos($server[4], 'http') !== false) {
                $item['obfs'] = 'simple_obfs_http';
            }
            if (stripos($server[4], 'tls') !== false) {
                $item['obfs'] = 'simple_obfs_tls';
            }
        }
        return $item;
    }

    public static function OutPort($server, $node_name, $mu_port)
    {
        $node_server = explode(';', $server);
        $node_port = $mu_port;

        if (isset($node_server[1])) {
            if (strpos($node_server[1], 'port') !== false) {
                $item = self::parse_args($node_server[1]);
                if (strpos($item['port'], '#') !== false) { // 端口偏移，指定端口，格式：8.8.8.8;port=80#1080
                    if (strpos($item['port'], '+') !== false) { // 多个单端口节点，格式：8.8.8.8;port=80#1080+443#8443
                        $args_explode = explode('+', $item['port']);
                        foreach ($args_explode as $arg) {
                            if ((int) substr($arg, 0, strpos($arg, '#')) == $mu_port) {
                                $node_port = (int) substr($arg, strpos($arg, '#') + 1);
                            }
                        }
                    } else {
                        if ((int) substr($item['port'], 0, strpos($item['port'], '#')) == $mu_port) {
                            $node_port = (int) substr($item['port'], strpos($item['port'], '#') + 1);
                        }
                    }
                } else { // 端口偏移，偏移端口，格式：8.8.8.8;port=1000 or 8.8.8.8;port=-1000
                    $node_port = ($mu_port + (int) $item['port']);
                }
            }
        }

        return [
            'name' => (Config::getInstance()->getConf('SUB.disable_sub_mu_port') ? $node_name : $node_name . ' - ' . $node_port . ' 单端口'),
            'address' => $node_server[0],
            'port' => $node_port
        ];
    }

    public static function get_MuOutPortArray($server)
    {
        $type = 0; //偏移
        $port = []; //指定
        $node_server = explode(';', $server);
        if (isset($node_server[1])) {
            if (strpos($node_server[1], 'port') !== false) {
                $item = self::parse_args($node_server[1]);
                if (strpos($item['port'], '#') !== false) {
                    if (strpos($item['port'], '+') !== false) {
                        $args_explode = explode('+', $item['port']);
                        foreach ($args_explode as $arg) {
                            $port[substr($arg, 0, strpos($arg, '#'))] = (int) substr($arg, strpos($arg, '#') + 1);
                        }
                    } else {
                        $port[substr($item['port'], 0, strpos($item['port'], '#'))] = (int) substr($item['port'], strpos($item['port'], '#') + 1);
                    }
                } else {
                    $type = (int) $item['port'];
                }
            }
        }

        return [
            'type' => $type,
            'port' => $port
        ];
    }

    // 请将冷门的国家或地区放置在上方，热门的中继起源放置在下方
    // 以便于兼容如：【上海 -> 美国】等节点名称
    private static $emoji = [
        "🇦🇷" => [
            "阿根廷"
        ],
        "🇦🇹" => [
            "奥地利",
            "维也纳"
        ],
        "🇦🇺" => [
            "澳大利亚",
            "悉尼"
        ],
        "🇧🇷" => [
            "巴西",
            "圣保罗"
        ],
        "🇨🇦" => [
            "加拿大",
            "蒙特利尔",
            "温哥华"
        ],
        "🇨🇭" => [
            "瑞士",
            "苏黎世"
        ],
        "🇩🇪" => [
            "德国",
            "法兰克福"
        ],
        "🇫🇮" => [
            "芬兰",
            "赫尔辛基"
        ],
        "🇫🇷" => [
            "法国",
            "巴黎"
        ],
        "🇬🇧" => [
            "英国",
            "伦敦"
        ],
        "🇮🇩" => [
            "印尼",
            "印度尼西亚",
            "雅加达"
        ],
        "🇮🇪" => [
            "爱尔兰",
            "都柏林"
        ],
        "🇮🇳" => [
            "印度",
            "孟买"
        ],
        "🇮🇹" => [
            "意大利",
            "米兰"
        ],
        "🇰🇵" => [
            "朝鲜"
        ],
        "🇲🇾" => [
            "马来西亚"
        ],
        "🇳🇱" => [
            "荷兰",
            "阿姆斯特丹"
        ],
        "🇵🇭" => [
            "菲律宾"
        ],
        "🇷🇴" => [
            "罗马尼亚"
        ],
        "🇷🇺" => [
            "俄罗斯",
            "伯力",
            "莫斯科",
            "圣彼得堡",
            "西伯利亚",
            "新西伯利亚"
        ],
        "🇸🇬" => [
            "新加坡"
        ],
        "🇹🇭" => [
            "泰国",
            "曼谷"
        ],
        "🇹🇷" => [
            "土耳其",
            "伊斯坦布尔"
        ],
        "🇺🇲" => [
            "美国",
            "波特兰",
            "俄勒冈",
            "凤凰城",
            "费利蒙",
            "硅谷",
            "拉斯维加斯",
            "洛杉矶",
            "圣克拉拉",
            "西雅图",
            "芝加哥",
            "沪美"
        ],
        "🇻🇳" => [
            "越南"
        ],
        "🇿🇦" => [
            "南非"
        ],
        "🇰🇷" => [
            "韩国",
            "首尔"
        ],
        "🇲🇴" => [
            "澳门"
        ],
        "🇯🇵" => [
            "日本",
            "东京",
            "大阪",
            "埼玉",
            "沪日"
        ],
        "🇹🇼" => [
            "台湾",
            "台北",
            "台中"
        ],
        "🇭🇰" => [
            "香港",
            "深港"
        ],
        "🇨🇳" => [
            "中国",
            "江苏",
            "北京",
            "上海",
            "深圳",
            "杭州",
            "徐州",
            "宁波",
            "镇江"
        ]
    ];

    public static function addEmoji($Name)
    {
        $done = [
            'index' => -1,
            'emoji' => ''
        ];
        foreach (self::$emoji as $key => $value) {
            foreach ($value as $item) {
                $index = strpos($Name, $item);
                if ($index !== false) {
                    $done['index'] = $index;
                    $done['emoji'] = $key;
                    continue 2;
                }
            }
        }
        return ($done['index'] == -1
            ? $Name
            : ($done['emoji'] . ' ' . $Name));
    }

    /**
     * 根据流量值自动转换单位输出
     */
    public static function flowAutoShow($value = 0)
    {
        $kb = 1024;
        $mb = 1048576;
        $gb = 1073741824;
        $tb = $gb * 1024;
        $pb = $tb * 1024;
        if (abs($value) > $pb) {
            return round($value / $pb, 2) . 'PB';
        }
        if (abs($value) > $tb) {
            return round($value / $tb, 2) . 'TB';
        }
        if (abs($value) > $gb) {
            return round($value / $gb, 2) . 'GB';
        }
        if (abs($value) > $mb) {
            return round($value / $mb, 2) . 'MB';
        }
        if (abs($value) > $kb) {
            return round($value / $kb, 2) . 'KB';
        }
        return round($value, 2) . 'B';
    }
}
