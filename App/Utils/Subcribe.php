<?php

namespace App\Utils;

use App\Models\User;
use EasySwoole\EasySwoole\Config;

class Subcribe
{
    /**
     * 订阅链接汇总
     *
     * @param User $user 用户
     * @param int  $int  当前用户访问的订阅类型
     *
     * @return array
     */
    public static function getSubinfo(User $user, $int = 0)
    {
        if ($int == 0) {
            $int = '';
        }
        $userapiUrl = Config::getInstance()->getConf('subUrl') . $user->getSubToken();
        $return_info = [
            'link'            => '',
            // sub
            'ss'              => '?sub=2',
            'ssr'             => '?sub=1',
            'v2ray'           => '?sub=3',
            'trojan'          => '?sub=4',
            // apps
            'ssa'             => '?list=ssa',
            'ssd'             => '?ssd=1',
            'clash'           => '?clash=1',
            'clash_provider'  => '?list=clash',
            'clashr'          => '?clash=2',
            'clashr_provider' => '?list=clashr',
            'surge'           => '?surge=' . $int,
            'surge_node'      => '?list=surge',
            'surge2'          => '?surge=2',
            'surge3'          => '?surge=3',
            'surge4'          => '?surge=4',
            'surfboard'       => '?surfboard=1',
            'quantumult'      => '?quantumult=' . $int,
            'quantumult_v2'   => '?list=quantumult',
            'quantumult_sub'  => '?quantumult=2',
            'quantumult_conf' => '?quantumult=3',
            'quantumultx'     => '?list=quantumultx',
            'shadowrocket'    => '?list=shadowrocket',
            'kitsunebi'       => '?list=kitsunebi'
        ];

        return array_map(
            function ($item) use ($userapiUrl) {
                return ($userapiUrl . $item);
            },
            $return_info
        );
    }

    /**
     * Undocumented function
     *
     * @param array  $item
     * @param string $list
     */
    public static function getListItem(array $item, string $list)
    {
        $return = null;
        switch ($list) {
            case 'ss':
                $return = AppURI::getItemUrl($item, 1);
                break;
            case 'ssr':
                $return = AppURI::getItemUrl($item, 0);
                break;
            case 'ssa':
                $return = AppURI::getSSJSON($item);
                break;
            case 'surge':
                $return = AppURI::getSurgeURI($item, 3);
                break;
            case 'clash':
                $return = AppURI::getClashURI($item);
                break;
            case 'clashr':
                $return = AppURI::getClashURI($item, true);
                break;
            case 'v2rayn':
                $return = AppURI::getV2RayNURI($item);
                break;
            case 'trojan':
                $return = AppURI::getTrojanURI($item);
                break;
            case 'kitsunebi':
                $return = AppURI::getKitsunebiURI($item);
                break;
            case 'quantumult':
                $return = AppURI::getQuantumultURI($item, true);
                break;
            case 'quantumultx':
                $return = AppURI::getQuantumultXURI($item);
                break;
            case 'shadowrocket':
                $return = AppURI::getShadowrocketURI($item);
                break;
        }
        return $return;
    }

    /**
     * Undocumented function
     *
     * @param User   $user
     * @param string $list
     * @param array  $opts
     * @param array  $Rule
     */
    public static function getLists(User $user, string $list, array $opts, array $Rule)
    {
        $list = strtolower($list);
        if ($list == 'ssa') {
            $Rule['type'] = 'ss';
        }
        if ($list == 'quantumult') {
            $Rule['type'] = 'vmess';
        }
        if ($list == 'shadowrocket') {
            // Shadowrocket 自带 emoji
            $Rule['emoji'] = false;
        }
        $items = $user->getNew_AllItems($Rule);
        $return = [];
        if ($Rule['extend'] === true) {
            switch ($list) {
                case 'ssa':
                case 'clash':
                case 'clashr':
                    $return = array_merge($return, self::getListExtend($user, $list));
                    break;
                default:
                    $return[] = implode(PHP_EOL, self::getListExtend($user, $list));
                    break;
            }
        }
        foreach ($items as $item) {
            $out = self::getListItem($item, $list);
            if ($out != null) {
                $return[] = $out;
            }
        }
        switch ($list) {
            case 'ssa':
                return json_encode($return, 320);
                break;
            case 'clash':
            case 'clashr':
                return \Symfony\Component\Yaml\Yaml::dump(['proxies' => $return], 4, 2);
            case 'kitsunebi':
            case 'quantumult':
            case 'shadowrocket':
                return base64_encode(implode(PHP_EOL, $return));
            default:
                return implode(PHP_EOL, $return);
        }
    }

    /**
     * Undocumented function
     *
     * @param User   $user
     * @param string $list
     */
    public static function getListExtend(User $user, string $list)
    {
        $return = [];
        $info_array = (count(Config::getInstance()->getConf('SUB.sub_message')) != 0 ? (array) Config::getInstance()->getConf('SUB.sub_message') : []);
        if (strtotime($user->expire_in) > time()) {
            if ($user->transfer_enable == 0) {
                $unusedTraffic = '剩余流量：0';
            } else {
                $unusedTraffic = '剩余流量：' . $user->unusedTraffic();
            }
            $expire_in = '过期时间：';
            if ($user->class_expire != '1989-06-04 00:05:00') {
                $userClassExpire = explode(' ', $user->class_expire);
                $expire_in .= $userClassExpire[0];
            } else {
                $expire_in .= '无限期';
            }
        } else {
            $unusedTraffic  = '账户已过期，请续费后使用';
            $expire_in      = '账户已过期，请续费后使用';
        }
        if (!in_array($list, ['quantumult', 'quantumultx', 'shadowrocket'])) {
            $info_array[] = $unusedTraffic;
            $info_array[] = $expire_in;
        }
        $baseUrl = explode('//', Config::getInstance()->getConf('baseUrl'))[1];
        $Extend = [
            'remark'          => '',
            'type'            => '',
            'add'             => $baseUrl,
            'address'         => $baseUrl,
            'port'            => 10086,
            'method'          => 'chacha20-ietf',
            'passwd'          => $user->passwd,
            'id'              => $user->getUuid(),
            'aid'             => 0,
            'net'             => 'tcp',
            'headerType'      => 'none',
            'host'            => '',
            'path'            => '/',
            'tls'             => '',
            'protocol'        => 'origin',
            'protocol_param'  => '',
            'obfs'            => 'plain',
            'obfs_param'      => '',
            'group'           => Config::getInstance()->getConf('appName')
        ];
        if ($list == 'shadowrocket') {
            $return[] = ('STATUS=' . $unusedTraffic . '.♥.' . $expire_in . PHP_EOL . 'REMARKS=' . Config::getInstance()->getConf('appName'));
        }
        foreach ($info_array as $remark) {
            $Extend['remark'] = $remark;
            if (in_array($list, ['kitsunebi', 'quantumult', 'v2rayn'])) {
                $Extend['type'] = 'vmess';
                $out = self::getListItem($Extend, $list);
            } elseif ($list == 'trojan') {
                $Extend['type'] = 'trojan';
                $out = self::getListItem($Extend, $list);
            } elseif ($list == 'ssr') {
                $Extend['type'] = 'ssr';
                $out = self::getListItem($Extend, $list);
            } else {
                $Extend['type'] = 'ss';
                $out = self::getListItem($Extend, $list);
            }
            if ($out !== null) $return[] = $out;
        }
        return $return;
    }

    /**
     * Surge 配置
     *
     * @param User  $user  用户
     * @param int   $surge 订阅类型
     * @param array $opts  request
     * @param array $Rule  节点筛选规则
     */
    public static function getSurge(User $user, int $surge, array $opts, array $Rule): string
    {
        $subInfo = self::getSubinfo($user, $surge);
        $userapiUrl = $subInfo['surge'];
        if ($surge != 4) {
            $Rule['type'] = 'ss';
        }
        $items = $user->getNew_AllItems($Rule);
        $Nodes = [];
        $All_Proxy = '';
        foreach ($items as $item) {
            $out = AppURI::getSurgeURI($item, $surge);
            if ($out !== null) {
                $Nodes[] = $item;
                $All_Proxy .= $out . PHP_EOL;
            }
        }
        $variable = ($surge == 2 ? 'Surge2_Profiles' : 'Surge_Profiles');
        if (isset($opts['profiles']) && in_array($opts['profiles'], array_keys($_ENV[$variable]))) {
            $Profiles = $opts['profiles'];
            $userapiUrl .= ('&profiles=' . $Profiles);
        } else {
            $Profiles = ($surge == 2 ? Config::getInstance()->getConf('SUB.Surge2_DefaultProfiles') : Config::getInstance()->getConf('SUB.Surge_DefaultProfiles'));
        }

        return Conf::getSurgeConfs($user, $All_Proxy, $Nodes, $_ENV[$variable][$Profiles], $Rule['URL']);
    }

    /**
     * Quantumult 配置
     *
     * @param User  $user       用户
     * @param int   $quantumult 订阅类型
     * @param array $opts       request
     * @param array $Rule       节点筛选规则
     */
    public static function getQuantumult(User $user, int $quantumult, array $opts, array $Rule): string
    {
        switch ($quantumult) {
            case 2:
                $subUrl = self::getSubinfo($user, 0);
                $str = [
                    '[SERVER]',
                    '',
                    '[SOURCE]',
                    Config::getInstance()->getConf('appName') . ', server ,' . $subUrl['ssr'] . ', false, true, false',
                    Config::getInstance()->getConf('appName') . '_ss, server ,' . $subUrl['ss'] . ', false, true, false',
                    Config::getInstance()->getConf('appName') . '_VMess, server ,' . $subUrl['quantumult_v2'] . ', false, true, false',
                    'Hackl0us Rules, filter, https://raw.githubusercontent.com/Hackl0us/Surge-Rule-Snippets/master/LAZY_RULES/Quantumult.conf, true',
                    '',
                    '[DNS]',
                    'system, 119.29.29.29, 223.6.6.6, 114.114.114.114',
                    '',
                    '[STATE]',
                    'STATE,AUTO'
                ];
                return implode(PHP_EOL, $str);
                break;
            case 3:
                $items = $user->getNew_AllItems($Rule);
                break;
            default:
                return self::getLists($user, 'quantumult', $opts, $Rule);
                break;
        }

        $All_Proxy          = '';
        $All_Proxy_name     = '';
        $BackChina_name     = '';
        foreach ($items as $item) {
            $out = AppURI::getQuantumultURI($item);
            if ($out !== null) {
                $All_Proxy .= $out . PHP_EOL;
                if (strpos($item['remark'], '回国') || strpos($item['remark'], 'China')) {
                    $BackChina_name .= "\n" . $item['remark'];
                } else {
                    $All_Proxy_name .= "\n" . $item['remark'];
                }
            }
        }
        $conf = [
            '[SERVER]',
            $All_Proxy,
            '',
            '[POLICY]',
            base64_encode("🍃 Proxy  :  static, 🏃 Auto\n🏃 Auto\n🚀 Direct\n" . $All_Proxy_name),
            base64_encode("🍂 Domestic  :  static, 🚀 Direct\n🚀 Direct\n🍃 Proxy\n" . $BackChina_name),
            base64_encode("☁️ Others  :   static, 🍃 Proxy\n🚀 Direct\n🍃 Proxy"),
            base64_encode("🚀 Direct : static, DIRECT\nDIRECT"),
            base64_encode("🍎 Only  :  static, 🚀 Direct\n🚀 Direct\n🍃 Proxy"),
            base64_encode("🏃 Auto  :  auto\n" . $All_Proxy_name),
            '',
            Conf::getRule(['source' => 'quantumult/quantumult.tpl'])
        ];

        return implode(PHP_EOL, $conf);
    }

    /**
     * QuantumultX 配置
     *
     * @param User  $user        用户
     * @param int   $quantumultx 订阅类型
     * @param array $opts        request
     * @param array $Rule        节点筛选规则
     */
    public static function getQuantumultX(User $user, int $quantumultx, array $opts, array $Rule): string
    {
        return '';
    }

    /**
     * Surfboard 配置
     *
     * @param User  $user      用户
     * @param int   $surfboard 订阅类型
     * @param array $opts      request
     * @param array $Rule      节点筛选规则
     */
    public static function getSurfboard(User $user, int $surfboard, array $opts, array $Rule): string
    {
        $subInfo = self::getSubinfo($user, 0);
        $userapiUrl = $subInfo['surfboard'];
        $Nodes = [];
        $All_Proxy = '';
        $items = $user->getNew_AllItems($Rule);
        foreach ($items as $item) {
            $out = AppURI::getSurfboardURI($item);
            if ($out !== null) {
                $Nodes[] = $item;
                $All_Proxy .= $out . PHP_EOL;
            }
        }
        if (isset($opts['profiles']) && in_array($opts['profiles'], array_keys($_ENV['Surfboard_Profiles']))) {
            $Profiles = $opts['profiles'];
            $userapiUrl .= ('&profiles=' . $Profiles);
        } else {
            $Profiles = Config::getInstance()->getConf('SUB.Surfboard_DefaultProfiles'); // 默认策略组
        }

        return Conf::getSurgeConfs($user, $All_Proxy, $Nodes, $_ENV['Surfboard_Profiles'][$Profiles], $Rule['URL']);
    }

    /**
     * Clash 配置
     *
     * @param User  $user  用户
     * @param int   $clash 订阅类型
     * @param array $opts  request
     * @param array $Rule  节点筛选规则
     */
    public static function getClash(User $user, int $clash, array $opts, array $Rule): string
    {
        $subInfo = self::getSubinfo($user, $clash);
        $userapiUrl = $subInfo['clash'];
        $ssr_support = ($clash == 2 ? true : false);
        $items = $user->getNew_AllItems($Rule);
        $Proxys = [];
        foreach ($items as $item) {
            $Proxy = AppURI::getClashURI($item, $ssr_support);
            if ($Proxy !== null) {
                $Proxys[] = $Proxy;
            }
        }
        if (isset($opts['profiles']) && in_array($opts['profiles'], array_keys($_ENV['Clash_Profiles']))) {
            $Profiles = $opts['profiles'];
            $userapiUrl .= ('&profiles=' . $Profiles);
        } else {
            $Profiles = Config::getInstance()->getConf('SUB.Clash_DefaultProfiles'); // 默认策略组
        }

        return Conf::getClashConfs($user, $Proxys, $_ENV['Clash_Profiles'][$Profiles]);
    }

    /**
     * SSD 订阅
     *
     * @param User  $user 用户
     * @param int   $ssd  订阅类型
     * @param array $opts request
     * @param array $Rule 节点筛选规则
     */
    public static function getSSD(User $user, int $ssd, array $opts, array $Rule):? string
    {
        if (!Tools::SSCanConnect($user)) {
            return null;
        }
        $array_all                  = [];
        $array_all['airport']       = Config::getInstance()->getConf('appName');
        $array_all['port']          = $user->port;
        $array_all['encryption']    = $user->method;
        $array_all['password']      = $user->passwd;
        $array_all['traffic_used']  = Tools::flowToGB($user->u + $user->d);
        $array_all['traffic_total'] = Tools::flowToGB($user->transfer_enable);
        $array_all['expiry']        = $user->class_expire;
        $array_all['url']           = self::getSubinfo($user, 0)['ssd'];
        $plugin_options             = '';
        if (strpos($user->obfs, 'http') != false) {
            $plugin_options = 'obfs=http';
        }
        if (strpos($user->obfs, 'tls') != false) {
            $plugin_options = 'obfs=tls';
        }
        if ($plugin_options != '') {
            $array_all['plugin'] = 'simple-obfs';
            $array_all['plugin_options'] = $plugin_options;
            if ($user->obfs_param != '') {
                $array_all['plugin_options'] .= ';obfs-host=' . $user->obfs_param;
            }
        }
        $array_server = [];
        $server_index = 1;
        $Rule['type'] = 'ss';
        $nodes = $user->getNew_AllItems($Rule);
        foreach ($nodes as $item) {
            if ($item['type'] != 'ss') continue;
            $server = AppURI::getSSDURI($item);
            if ($server !== null) {
                $server['id'] = $server_index;
                $array_server[] = $server;
                $server_index++;
            }
        }
        $array_all['servers'] = $array_server;
        $json_all = json_encode($array_all, 320);

        return 'ssd://' . base64_encode($json_all);
    }

    /**
     * 通用订阅，ssr & v2rayn
     *
     * @param User   $user 用户
     * @param int    $sub  订阅类型
     * @param array  $opts request
     * @param array  $Rule 节点筛选规则
     */
    public static function getSub(User $user, int $sub, array $opts, array $Rule): string
    {
        $return_url = '';
        switch ($sub) {
            case 2: // SS
                $Rule['type'] = 'ss';
                $getListExtend = $Rule['extend'] ? self::getListExtend($user, 'ss') : [];
                break;
            case 3: // V2
                $Rule['type'] = 'vmess';
                $getListExtend = $Rule['extend'] ? self::getListExtend($user, 'v2rayn') : [];
                break;
            case 4: // Trojan
                $Rule['type'] = 'trojan';
                $getListExtend = $Rule['extend'] ? self::getListExtend($user, 'trojan') : [];
                break;
            default: // SSR
                $Rule['type'] = 'ssr';
                $getListExtend = $Rule['extend'] ? self::getListExtend($user, 'ssr') : [];
                break;
        }
        if ($Rule['extend']) {
            $return_url .= implode(PHP_EOL, $getListExtend) . PHP_EOL;
        }
        $return_url .= $user->get_NewAllUrl($Rule);
        return base64_encode($return_url);
    }
}
