<?php

//Thanks to http://blog.csdn.net/jollyjumper/article/details/9823047

namespace App\Controllers;

use App\Models\{Link, User, UserSubscribeLog};
use App\Utils\{URL, Tools, AppURI, ConfRender};
use App\Services\{Config, AppsProfiles};
use Ramsey\Uuid\Uuid;

/**
 *  LinkController
 */
class LinkController extends BaseController
{
    public static function GenerateRandomLink()
    {
        for ($i = 0; $i < 10; $i++) {
            $token = Tools::genRandomChar(16);
            $Elink = Link::where('token', '=', $token)->first();
            if ($Elink == null) {
                return $token;
            }
        }

        return "couldn't alloc token";
    }

    public static function GenerateSSRSubCode($userid, $without_mu)
    {
        $Elink = Link::where('type', '=', 11)->where('userid', '=', $userid)->where('geo', $without_mu)->first();
        if ($Elink != null) {
            return $Elink->token;
        }
        $NLink = new Link();
        $NLink->type = 11;
        $NLink->address = '';
        $NLink->port = 0;
        $NLink->ios = 0;
        $NLink->geo = $without_mu;
        $NLink->method = '';
        $NLink->userid = $userid;
        $NLink->token = self::GenerateRandomLink();
        $NLink->save();

        return $NLink->token;
    }

    public static function GetContent($request, $response, $args)
    {
        $token = $args['token'];

        //$builder->getPhrase();
        $Elink = Link::where('type', 11)->where('token', '=', $token)->first();
        if ($Elink == null) {
            return null;
        }

        $user = User::where('id', $Elink->userid)->first();
        if ($user == null) {
            return null;
        }

        $opts = $request->getQueryParams();

        // 筛选节点部分
        $Rule['type'] = (isset($opts['type']) ? trim($opts['type']) : 'all');
        $Rule['is_mu'] = (Config::get('mergeSub') === true ? 1 : 0);
        if (isset($opts['mu'])) $Rule['is_mu'] = (int) $opts['mu'];

        if (isset($opts['class'])) {
            $class = trim(urldecode($opts['class']));
            $Rule['content']['class'] = array_map(
                function ($item) {
                    return (int) $item;
                },
                explode('+', $class)
            );
        }
        if (isset($opts['noclass'])) {
            $noclass = trim(urldecode($opts['noclass']));
            $Rule['content']['noclass'] = array_map(
                function ($item) {
                    return (int) $item;
                },
                explode('+', $noclass)
            );
        }
        if (isset($opts['regex'])) {
            $Rule['content']['regex'] = trim(urldecode($opts['regex']));
        }

        // Emoji
        $Rule['emoji'] = Config::get('add_emoji_to_node_name');
        if (isset($opts['emoji'])) $Rule['emoji'] = (bool) $opts['emoji'];
        // 显示流量以及到期时间等
        $Rule['extend'] = Config::get('enable_sub_extend');
        if (isset($opts['extend'])) $Rule['extend'] = (bool) $opts['extend'];

        // 兼容原版
        if (isset($opts['mu'])) {
            $mu = (int) $opts['mu'];
            switch ($mu) {
                case 0:
                    $opts['sub'] = 1;
                    break;
                case 1:
                    $opts['sub'] = 1;
                    break;
                case 2:
                    $opts['sub'] = 3;
                    break;
                case 3:
                    $opts['ssd'] = 1;
                    break;
                case 4:
                    $opts['clash'] = 1;
                    break;
            }
        }

        // 订阅类型
        $subscribe_type = '';

        // 请求路径以及查询参数
        $path = ($request->getUri()->getPath() . $request->getUri()->getQuery());

        $getBody = '';

        $sub_type_array = ['list', 'ssd', 'clash', 'surge', 'kitsunebi', 'surfboard', 'quantumult', 'quantumultx', 'shadowrocket', 'sub'];
        foreach ($sub_type_array as $key) {
            if (isset($opts[$key])) {
                $query_value = $opts[$key];
                if ($query_value != '0' && $query_value != '') {
                    // 兼容代码开始
                    if ($key == 'sub' && $query_value > 3) {
                        $query_value = 1;
                    }
                    if ($key == 'surge' && $query_value == '1') {
                        $key = 'list';
                        $query_value = 'surge';
                    }
                    if ($key == 'quantumult' && $query_value == '1') {
                        $key = 'list';
                        $query_value = 'quantumult';
                    }
                    // 兼容代码结束
                    if ($key == 'list') {
                        $SubscribeExtend = self::getSubscribeExtend($query_value);
                    } else {
                        $SubscribeExtend = self::getSubscribeExtend($key, $query_value);
                    }
                    $filename = $SubscribeExtend['filename'] . '_' . time() . '.' . $SubscribeExtend['suffix'];
                    $subscribe_type = $SubscribeExtend['filename'];
                    $Cache = false;
                    $class = ('get' . $SubscribeExtend['class']);
                    if (Config::get('enable_sub_cache') === true) {
                        $Cache = true;
                        $content = self::getSubscribeCache($user, $path);
                        if ($content === false) {
                            $Cache = false;
                            $content = self::$class($user, $query_value, $opts, $Rule);
                        }
                        self::SubscribeCache($user, $path, $content);
                    } else {
                        $content = self::$class($user, $query_value, $opts, $Rule);
                    }
                    $getBody = self::getBody(
                        $user,
                        $response,
                        $content,
                        $filename,
                        $Cache
                    );
                    break;
                }
                continue;
            }
        }

        // 记录订阅日志
        if (Config::get('subscribeLog') === true && $getBody != '') {
            self::Subscribe_log($user, $subscribe_type, $request->getHeaderLine('User-Agent'));
        }

        return $getBody;
    }

    /**
     * 获取订阅文件缓存
     *
     * @param object $user 用户
     * @param string $path 路径以及查询参数
     *
     */
    private static function getSubscribeCache($user, $path)
    {
        $user_path = (BASE_PATH . '/storage/SubscribeCache/' . $user->id . '/');
        if (!is_dir($user_path)) mkdir($user_path);
        $user_path_hash = ($user_path . Uuid::uuid3(Uuid::NAMESPACE_DNS, $path)->toString());
        if (!is_file($user_path_hash)) return false;
        $filemtime = filemtime($user_path_hash);
        if ($filemtime === false) {
            unlink($user_path_hash);
            return false;
        }
        if ((time() - $filemtime) >= (Config::get('sub_cache_time') * 60)) {
            unlink($user_path_hash);
            return false;
        }

        return file_get_contents($user_path_hash);
    }

    /**
     * 获取订阅类型的文件名
     *
     * @param string      $type  订阅类型
     * @param string|null $value 值
     *
     * @return array
     */
    public static function getSubscribeExtend($type, $value = null)
    {
        switch ($type) {
            case 'ss':
                $return = [
                    'filename' => 'SS',
                    'suffix'   => 'txt',
                    'class'    => 'Sub'
                ];
                break;
            case 'ssa':
                $return = [
                    'filename' => 'SSA',
                    'suffix'   => 'json',
                    'class'    => 'Lists'
                ];
                break;
            case 'ssd':
                $return = [
                    'filename' => 'SSD',
                    'suffix'   => 'txt',
                    'class'    => 'SSD'
                ];
                break;
            case 'ssr':
                $return = [
                    'filename' => 'SSR',
                    'suffix'   => 'txt',
                    'class'    => 'Sub'
                ];
                break;
            case 'sub':
                if ((int) $value == 3) {
                    $return = self::getSubscribeExtend('v2rayn');
                } elseif ((int) $value == 2) {
                    $return = self::getSubscribeExtend('ss');
                } else {
                    $return = self::getSubscribeExtend('ssr');
                }
                break;
            case 'clash':
                if ($value !== null) {
                    if ((int) $value == 2) {
                        $return = self::getSubscribeExtend('clashr');
                        $return['class'] = 'Clash';
                    } else {
                        $return = self::getSubscribeExtend('clash');
                        $return['class'] = 'Clash';
                    }
                } else {
                    $return = [
                        'filename' => 'Clash',
                        'suffix'   => 'yaml',
                        'class'    => 'Lists'
                    ];
                }
                break;
            case 'surge':
                if ($value !== null) {
                    $return = [
                        'filename' => 'Surge',
                        'suffix'   => 'conf',
                        'class'    => 'Surge'
                    ];
                    $return['filename'] .= $value;
                } else {
                    $return = [
                        'filename' => 'SurgeList',
                        'suffix'   => 'list',
                        'class'    => 'Lists'
                    ];
                }
                break;
            case 'clashr':
                $return = [
                    'filename' => 'ClashR',
                    'suffix'   => 'yaml',
                    'class'    => 'Lists'
                ];
                break;
            case 'v2rayn':
                $return = [
                    'filename' => 'V2RayN',
                    'suffix'   => 'txt',
                    'class'    => 'Sub'
                ];
                break;
            case 'kitsunebi':
                $return = [
                    'filename' => 'Kitsunebi',
                    'suffix'   => 'txt',
                    'class'    => 'Lists'
                ];
                if ($value !== null) {
                    $return['class'] = 'Kitsunebi';
                }
                break;
            case 'surfboard':
                $return = [
                    'filename' => 'Surfboard',
                    'suffix'   => 'conf',
                    'class'    => 'Surfboard'
                ];
                break;
            case 'quantumult':
                if ($value !== null) {
                    if ((int) $value == 2) {
                        $return = self::getSubscribeExtend('quantumult_sub');
                    } else {
                        $return = self::getSubscribeExtend('quantumult_conf');
                    }
                } else {
                    $return = [
                        'filename' => 'Quantumult',
                        'suffix'   => 'conf',
                        'class'    => 'Lists'
                    ];
                }
                break;
            case 'quantumultx':
                $return = [
                    'filename' => 'QuantumultX',
                    'suffix'   => 'txt',
                    'class'    => 'Lists'
                ];
                if ($value !== null) {
                    $return['class'] = 'QuantumultX';
                }
                break;
            case 'shadowrocket':
                $return = [
                    'filename' => 'Shadowrocket',
                    'suffix'   => 'txt',
                    'class'    => 'Lists'
                ];
                if ($value !== null) {
                    $return['class'] = 'Shadowrocket';
                }
                break;
            case 'clash_provider':
                $return = [
                    'filename' => 'ClashProvider',
                    'suffix'   => 'yaml',
                    'class'    => 'Lists'
                ];
                break;
            case 'clashr_provider':
                $return = [
                    'filename' => 'ClashRProvider',
                    'suffix'   => 'yaml',
                    'class'    => 'Lists'
                ];
                break;
            case 'quantumult_sub':
                $return = [
                    'filename' => 'QuantumultSub',
                    'suffix'   => 'conf',
                    'class'    => 'Quantumult'
                ];
                break;
            case 'quantumult_conf':
                $return = [
                    'filename' => 'QuantumultConf',
                    'suffix'   => 'conf',
                    'class'    => 'Quantumult'
                ];
                break;
            default:
                $return = [
                    'filename' => 'UndefinedNode',
                    'suffix'   => 'txt',
                    'class'    => 'Sub'
                ];
                break;
        }
        return $return;
    }

    /**
     * 订阅文件写入缓存
     *
     * @param object $user 用户
     * @param string $path 路径以及查询参数
     *
     */
    private static function SubscribeCache($user, $path, $content)
    {
        $user_path = (BASE_PATH . '/storage/SubscribeCache/' . $user->id . '/');
        if (!is_dir($user_path)) mkdir($user_path);
        $number = 0;
        $files = glob($user_path . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                $number++;
            }
        }
        if ($number >= Config::get('sub_cache_max_quantity') + 1) {
            Tools::delDirAndFile($user_path);
        }
        $user_path_hash = ($user_path . Uuid::uuid3(Uuid::NAMESPACE_DNS, $path)->toString());
        $file = fopen($user_path_hash, 'wb');
        fwrite($file, $content);
        fclose($file);
    }

    /**
     * 记录订阅日志
     *
     * @param object $user 用户
     * @param string $type 订阅类型
     * @param string $ua   UA
     *
     */
    private static function Subscribe_log($user, $type, $ua)
    {
        $log = new UserSubscribeLog();

        $log->user_name = $user->user_name;
        $log->user_id = $user->id;
        $log->email = $user->email;
        $log->subscribe_type = $type;
        $log->request_ip = $_SERVER['REMOTE_ADDR'];
        $log->request_time = date('Y-m-d H:i:s');
        $log->request_user_agent = $ua;
        $log->save();
    }

    /**
     * 响应内容
     *
     * @param object $user     用户
     * @param array  $response 响应体
     * @param string $content  订阅内容
     * @param string $filename 文件名
     *
     * @return string
     */
    public static function getBody($user, $response, $content, $filename, $Cache)
    {
        $CacheInfo = ($Cache === true ? 'HIT from Disktank' : 'MISS');
        $newResponse = $response
            ->withHeader(
                'Content-type',
                ' application/octet-stream; charset=utf-8'
            )
            ->withHeader(
                'Cache-Control',
                'no-store, no-cache, must-revalidate'
            )
            ->withHeader(
                'Content-Disposition',
                ' attachment; filename=' . $filename
            )
            ->withHeader(
                'X-Cache',
                ' ' . $CacheInfo
            )
            ->withHeader(
                'Subscription-Userinfo',
                (' upload=' . $user->u
                    . '; download=' . $user->d
                    . '; total=' . $user->transfer_enable
                    . '; expire=' . strtotime($user->class_expire))
            );
        $newResponse->write($content);

        return $newResponse;
    }

    /**
     * 订阅链接汇总
     *
     * @param object $user 用户
     * @param int    $int  当前用户访问的订阅类型
     *
     * @return array
     */
    public static function getSubinfo($user, $int = 0)
    {
        if ($int == 0) {
            $int = '';
        }
        $userapiUrl = Config::get('subUrl') . self::GenerateSSRSubCode($user->id, 0);
        $return_info = [
            'link'            => '',
            // sub
            'ss'              => '?sub=2',
            'ssr'             => '?sub=1',
            'v2ray'           => '?sub=3',
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

    public static function getListItem($item, $list)
    {
        $return = null;
        switch ($list) {
            case 'ss':
                $return = URL::getItemUrl($item, 1);
                break;
            case 'ssr':
                $return = URL::getItemUrl($item, 0);
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
                $item['ps'] = $item['remark'];
                $item['type'] = $item['headerType'];
                $return = 'vmess://' . base64_encode(json_encode($item, 320));
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

    public static function getLists($user, $list, $opts, $Rule)
    {
        $list = strtolower($list);
        if ($list == 'ssd') {
            return self::getSSD($user, 1, $opts, $Rule, false);
        }
        if ($list == 'ssa') {
            $Rule['type'] = 'ss';
        }
        if ($list == 'quantumult') {
            $Rule['type'] = 'vmess';
        }
        $items = URL::getNew_AllItems($user, $Rule);
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

    public static function getListExtend($user, $list)
    {
        $return = [];
        $info_array = (count(Config::get('sub_message')) != 0 ? (array) Config::get('sub_message') : []);
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
        $baseUrl = explode('//', Config::get('baseUrl'))[1];
        $Extend_ss = [
            'remark'    => '',
            'type'      => 'ss',
            'address'   => $baseUrl,
            'port'      => 10086,
            'method'    => 'chacha20-ietf-poly1305',
            'passwd'    => $user->passwd,
            'obfs'      => 'plain',
            'group'     => $_ENV['appName']
        ];
        $Extend_ssr = [
            'remark'    => '',
            'type'      => 'ssr',
            'address'   => $baseUrl,
            'port'      => 10086,
            'method'    => 'chacha20-ietf',
            'passwd'    => $user->passwd,
            'protocol'  => 'origin',
            'obfs'      => 'plain',
            'group'     => $_ENV['appName']
        ];
        $Extend_VMess = [
            'remark'    => '',
            'type'      => 'vmess',
            'add'       => $baseUrl,
            'port'      => 10086,
            'id'        => $user->getUuid(),
            'alterId'   => 0,
            'net'       => 'tcp'
        ];
        if ($list == 'shadowrocket') {
            $return[] = ('STATUS=' . $unusedTraffic . '.♥.' . $expire_in . PHP_EOL . 'REMARKS=' . Config::get('appName'));
        }
        foreach ($info_array as $remark) {
            $Extend_ss['remark']    = $remark;
            $Extend_ssr['remark']   = $remark;
            $Extend_VMess['remark'] = $remark;
            if (in_array($list, ['kitsunebi', 'quantumult', 'v2rayn'])) {
                $out = self::getListItem($Extend_VMess, $list);
            } elseif ($list == 'ssr') {
                $out = self::getListItem($Extend_ssr, $list);
            } else {
                $out = self::getListItem($Extend_ss, $list);
            }
            if ($out !== null) $return[] = $out;
        }
        return $return;
    }

    /**
     * Surge 配置
     *
     * @param object $user  用户
     * @param int    $surge 订阅类型
     * @param array  $opts  request
     * @param array  $Rule  节点筛选规则
     *
     * @return string
     */
    public static function getSurge($user, $surge, $opts, $Rule)
    {
        if ($surge == 1) {
            return self::getLists($user, 'surge', $opts, $Rule);
        }
        $subInfo = self::getSubinfo($user, $surge);
        $userapiUrl = $subInfo['surge'];
        $source = (isset($opts['source']) && $opts['source'] != '' ? true : false);
        if ($surge != 4) $Rule['type'] = 'ss';
        $items = URL::getNew_AllItems($user, $Rule);
        $All_Proxy = '';
        foreach ($items as $item) {
            $URI = AppURI::getSurgeURI($item, $surge);
            if ($URI !== null) $All_Proxy .= $URI . PHP_EOL;
        }
        if ($source) {
            $SourceURL = trim(urldecode($opts['source']));
            // 远程规则仅支持 github 以及 gitlab
            if (!preg_match('/^https:\/\/((gist\.)?github\.com|raw\.githubusercontent\.com|gitlab\.com)/i', $SourceURL)) {
                return '远程配置仅支持 (gist)github 以及 gitlab 的链接。';
            }
            $SourceContent = @file_get_contents($SourceURL);
            if ($SourceContent) {
                $Content = ConfController::YAML2Array($SourceContent);
                if (!is_array($Content)) {
                    return $Content;
                }
                return ConfController::getSurgeConfs(
                    $user,
                    $All_Proxy,
                    $items,
                    $Content
                );
            } else {
                return '远程配置下载失败。';
            }
        }
        if (isset($opts['profiles']) && in_array((string) $opts['profiles'], array_keys(AppsProfiles::Surge()))) {
            $Profiles = (string) trim($opts['profiles']);
            $userapiUrl .= ('&profiles=' . $Profiles);
        } else {
            $Profiles = '123456'; // 默认策略组
        }
        $ProxyGroups = ConfController::getSurgeConfProxyGroup($items, AppsProfiles::Surge()[$Profiles]['ProxyGroup']);
        $ProxyGroups = ConfController::fixSurgeProxyGroup($ProxyGroups, AppsProfiles::Surge()[$Profiles]['Checks']);
        $ProxyGroups = ConfController::getSurgeProxyGroup2String($ProxyGroups);

        $render = ConfRender::getTemplateRender();
        $render->assign('user', $user)
            ->assign('surge', $surge)
            ->assign('userapiUrl', $userapiUrl)
            ->assign('All_Proxy', $All_Proxy)
            ->assign('ProxyGroups', $ProxyGroups);

        return $render->fetch('surge.tpl');
    }

    /**
     * Quantumult 配置
     *
     * @param object $user       用户
     * @param int    $quantumult 订阅类型
     * @param array  $Rule       节点筛选规则
     *
     * @return string
     */
    public static function getQuantumult($user, $quantumult, $opts, $Rule)
    {
        $emoji = $Rule['emoji'];
        switch ($quantumult) {
            case 2:
                $subUrl = self::getSubinfo($user, 0);
                $str = [
                    '[SERVER]',
                    '',
                    '[SOURCE]',
                    Config::get('appName') . ', server ,' . $subUrl['ssr'] . ', false, true, false',
                    Config::get('appName') . '_ss, server ,' . $subUrl['ss'] . ', false, true, false',
                    Config::get('appName') . '_VMess, server ,' . $subUrl['quantumult_v2'] . ', false, true, false',
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
                $items = URL::getNew_AllItems($user, $Rule);
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
        $ProxyGroups = [
            'proxy_group'       => base64_encode("🍃 Proxy  :  static, 🏃 Auto\n🏃 Auto\n🚀 Direct\n" . $All_Proxy_name),
            'domestic_group'    => base64_encode("🍂 Domestic  :  static, 🚀 Direct\n🚀 Direct\n🍃 Proxy\n" . $BackChina_name),
            'others_group'      => base64_encode("☁️ Others  :   static, 🍃 Proxy\n🚀 Direct\n🍃 Proxy"),
            'direct_group'      => base64_encode("🚀 Direct : static, DIRECT\nDIRECT"),
            'apple_group'       => base64_encode("🍎 Only  :  static, 🚀 Direct\n🚀 Direct\n🍃 Proxy"),
            'auto_group'        => base64_encode("🏃 Auto  :  auto\n" . $All_Proxy_name),
        ];
        $render = ConfRender::getTemplateRender();
        $render->assign('All_Proxy', $All_Proxy)->assign('ProxyGroups', $ProxyGroups);

        return $render->fetch('quantumult.tpl');
    }

    /**
     * QuantumultX 配置
     *
     * @param object $user        用户
     * @param int    $quantumultx 订阅类型
     * @param array  $Rule        节点筛选规则
     *
     * @return string
     */
    public static function getQuantumultX($user, $quantumultx, $opts, $Rule)
    {
        switch ($quantumultx) {
            default:
                return self::getLists($user, 'quantumultx', $opts, $Rule);
                break;
        }
    }

    /**
     * Surfboard 配置
     *
     * @param object $user 用户
     * @param array  $opts request
     *
     * @return string
     */
    public static function getSurfboard($user, $surfboard, $opts, $Rule)
    {
        $subInfo = self::getSubinfo($user, 0);
        $userapiUrl = $subInfo['surfboard'];
        $All_Proxy = '';
        $Rule['type'] = 'ss';
        $items = URL::getNew_AllItems($user, $Rule);
        foreach ($items as $item) {
            $out = AppURI::getSurfboardURI($item);
            if ($out !== null) {
                $All_Proxy .= $out . PHP_EOL;
            }
        }
        if (isset($opts['profiles']) && in_array((string) $opts['profiles'], array_keys(AppsProfiles::Surfboard()))) {
            $Profiles = (string) trim($opts['profiles']);
            $userapiUrl .= ('&profiles=' . $Profiles);
        } else {
            $Profiles = '123456'; // 默认策略组
        }
        $ProxyGroups = ConfController::getSurgeConfProxyGroup($items, AppsProfiles::Surfboard()[$Profiles]['ProxyGroup']);
        $ProxyGroups = ConfController::fixSurgeProxyGroup($ProxyGroups, AppsProfiles::Surfboard()[$Profiles]['Checks']);
        $ProxyGroups = ConfController::getSurgeProxyGroup2String($ProxyGroups);

        $render = ConfRender::getTemplateRender();
        $render->assign('user', $user)
            ->assign('userapiUrl', $userapiUrl)
            ->assign('All_Proxy', $All_Proxy)
            ->assign('ProxyGroups', $ProxyGroups);

        return $render->fetch('surfboard.tpl');
    }

    /**
     * Clash 配置
     *
     * @param object $user  用户
     * @param int    $clash 订阅类型
     * @param array  $opts  request
     *
     * @return string
     */
    public static function getClash($user, $clash, $opts, $Rule)
    {
        $subInfo = self::getSubinfo($user, 0);
        $userapiUrl = $subInfo['clash'];
        $ssr_support = ($clash == 2 ? true : false);
        $items = URL::getNew_AllItems($user, $Rule);
        $Proxys = [];
        foreach ($items as $item) {
            $Proxy = AppURI::getClashURI($item, $ssr_support);
            if ($Proxy !== null) {
                if (isset($opts['source']) && $opts['source'] != '') {
                    $Proxy['class'] = $item['class'];
                }
                $Proxys[] = $Proxy;
            }
        }
        if (isset($opts['source']) && $opts['source'] != '') {
            $SourceURL = trim(urldecode($opts['source']));
            // 远程规则仅支持 github 以及 gitlab
            if (!preg_match('/^https:\/\/((gist\.)?github\.com|raw\.githubusercontent\.com|gitlab\.com)/i', $SourceURL)) {
                return '远程配置仅支持 (gist)github 以及 gitlab 的链接。';
            }
            $SourceContent = @file_get_contents($SourceURL);
            if ($SourceContent) {
                $Content = ConfController::YAML2Array($SourceContent);
                if (!is_array($Content)) {
                    return $Content;
                }
                return ConfController::getClashConfs(
                    $user,
                    $Proxys,
                    $Content
                );
            } else {
                return '远程配置下载失败。';
            }
        } else {
            if (isset($opts['profiles']) && in_array((string) $opts['profiles'], array_keys(AppsProfiles::Clash()))) {
                $Profiles = (string) trim($opts['profiles']);
                $userapiUrl .= ('&profiles=' . $Profiles);
            } else {
                $Profiles = '123456'; // 默认策略组
            }
            $ProxyGroups = ConfController::getClashConfProxyGroup($Proxys, AppsProfiles::Clash()[$Profiles]['ProxyGroup']);
            $ProxyGroups = ConfController::fixClashProxyGroup($ProxyGroups, AppsProfiles::Clash()[$Profiles]['Checks']);
            $ProxyGroups = ConfController::getClashProxyGroup2String($ProxyGroups);
        }

        $render = ConfRender::getTemplateRender();
        $render->assign('user', $user)
            ->assign('userapiUrl', $userapiUrl)
            ->assign('opts', $opts)
            ->assign('Proxys', $Proxys)
            ->assign('ProxyGroups', $ProxyGroups)
            ->assign('Profiles', $Profiles);

        return $render->fetch('clash.tpl');
    }

    /**
     * SSD 订阅
     *
     * @param object $user 用户
     *
     * @return string
     */
    public static function getSSD($user, $ssd, $opts, $Rule)
    {
        if (!URL::SSCanConnect($user)) {
            return null;
        }
        $array_all                  = [];
        $array_all['airport']       = Config::get('appName');
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
        $nodes = URL::getNew_AllItems($user, $Rule);
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
     * Shadowrocket 订阅
     *
     * @param object $user 用户
     * @param array  $opts request
     * @param array  $Rule 节点筛选规则
     *
     * @return string
     */
    public static function getShadowrocket($user, $shadowrocket, $opts, $Rule)
    {
        $Rule['emoji'] = false; // Shadowrocket 自带 emoji
        return self::getLists($user, 'shadowrocket', $opts, $Rule);
    }

    /**
     * Kitsunebi 订阅
     *
     * @param object $user 用户
     * @param array  $opts request
     * @param array  $Rule 节点筛选规则
     *
     * @return string
     */
    public static function getKitsunebi($user, $kitsunebi, $opts, $Rule)
    {
        return self::getLists($user, 'kitsunebi', $opts, $Rule);
    }

    public static function getSSPcConf($user)
    {
        $proxy = [];
        $items = array_merge(
            URL::getAllItems($user, 0, 1, 0),
            URL::getAllItems($user, 1, 1, 0),
            URL::getAllV2RayPluginItems($user)
        );
        foreach ($items as $item) {
            $proxy_plugin = '';
            $proxy_plugin_opts = '';
            if ($item['obfs'] == 'v2ray' || in_array($item['obfs'], Config::getSupportParam('ss_obfs'))) {
                if ($item['obfs'] == 'v2ray') {
                    $proxy_plugin .= 'v2ray';
                } else {
                    $proxy_plugin .= 'obfs-local';
                }
                if (strpos($item['obfs'], 'http') !== false) {
                    $proxy_plugin_opts .= 'obfs=http';
                } elseif (strpos($item['obfs'], 'tls') !== false) {
                    $proxy_plugin_opts .= 'obfs=tls';
                } else {
                    $proxy_plugin_opts .= 'v2ray;' . $item['obfs_param'];
                }
                if ($item['obfs_param'] != '' && $item['obfs'] != 'v2ray') {
                    $proxy_plugin_opts .= ';obfs-host=' . $item['obfs_param'];
                }
            }
            $proxy[] = [
                'remarks' => $item['remark'],
                'server' => $item['address'],
                'server_port' => $item['port'],
                'method' => $item['method'],
                'password' => $item['passwd'],
                'timeout' => 5,
                'plugin' => $proxy_plugin,
                'plugin_opts' => $proxy_plugin_opts
            ];
        }
        $config = [
            'configs' => $proxy,
            'strategy' => null,
            'index' => 0,
            'global' => false,
            'enabled' => true,
            'shareOverLan' => false,
            'isDefault' => false,
            'localPort' => 1080,
            'portableMode' => true,
            'pacUrl' => null,
            'useOnlinePac' => false,
            'secureLocalPac' => true,
            'availabilityStatistics' => false,
            'autoCheckUpdate' => true,
            'checkPreRelease' => false,
            'isVerboseLogging' => false,
            'logViewer' => [
                'topMost' => false,
                'wrapText' => false,
                'toolbarShown' => false,
                'Font' => 'Consolas, 8pt',
                'BackgroundColor' => 'Black',
                'TextColor' => 'White'
            ],
            'proxy' => [
                'useProxy' => false,
                'proxyType' => 0,
                'proxyServer' => '',
                'proxyPort' => 0,
                'proxyTimeout' => 3
            ],
            'hotkey' => [
                'SwitchSystemProxy' => '',
                'SwitchSystemProxyMode' => '',
                'SwitchAllowLan' => '',
                'ShowLogs' => '',
                'ServerMoveUp' => '',
                'ServerMoveDown' => '',
                'RegHotkeysAtStartup' => false
            ]
        ];

        return json_encode($config, JSON_PRETTY_PRINT);
    }

    public static function getSSRPcConf($user)
    {
        $proxy = [];
        $items = array_merge(
            URL::getAllItems($user, 0, 0, 0),
            URL::getAllItems($user, 1, 0, 0)
        );
        foreach ($items as $item) {
            $proxy[] = [
                'remarks' => $item['remark'],
                'server' => $item['address'],
                'server_port' => $item['port'],
                'method' => $item['method'],
                'obfs' => $item['obfs'],
                'obfsparam' => $item['obfs_param'],
                'remarks_base64' => base64_encode($item['remark']),
                'password' => $item['passwd'],
                'tcp_over_udp' => false,
                'udp_over_tcp' => false,
                'group' => Config::get('appName'),
                'protocol' => $item['protocol'],
                'protocolparam' => $item['protocol_param'],
                'obfs_udp' => false,
                'enable' => true
            ];
        }
        $config = [
            'configs' => $proxy,
            'index' => 0,
            'random' => true,
            'sysProxyMode' => 1,
            'shareOverLan' => false,
            'localPort' => 1080,
            'localAuthPassword' => Tools::genRandomChar(26),
            'dnsServer' => '',
            'reconnectTimes' => 2,
            'balanceAlgorithm' => 'LowException',
            'randomInGroup' => false,
            'TTL' => 0,
            'connectTimeout' => 5,
            'proxyRuleMode' => 2,
            'proxyEnable' => false,
            'pacDirectGoProxy' => false,
            'proxyType' => 0,
            'proxyHost' => '',
            'proxyPort' => 0,
            'proxyAuthUser' => '',
            'proxyAuthPass' => '',
            'proxyUserAgent' => '',
            'authUser' => '',
            'authPass' => '',
            'autoBan' => false,
            'sameHostForSameTarget' => false,
            'keepVisitTime' => 180,
            'isHideTips' => false,
            'nodeFeedAutoUpdate' => true,
            'serverSubscribes' => [
                [
                    'URL' => self::getSubinfo($user, 0)['ssr'],
                    'Group' => Config::get('appName'),
                    'LastUpdateTime' => 0
                ]
            ],
            'token' => [],
            'portMap' => []
        ];

        return json_encode($config, JSON_PRETTY_PRINT);
    }

    public static function getSSDPcConf($user)
    {
        $id = 1;
        $proxy = [];
        $items = array_merge(
            URL::getAllItems($user, 0, 1, 0),
            URL::getAllItems($user, 1, 1, 0),
            URL::getAllV2RayPluginItems($user)
        );
        foreach ($items as $item) {
            $proxy_plugin = '';
            $proxy_plugin_opts = '';
            if ($item['obfs'] == 'v2ray' || in_array($item['obfs'], Config::getSupportParam('ss_obfs'))) {
                if ($item['obfs'] == 'v2ray') {
                    $proxy_plugin .= 'v2ray';
                } else {
                    $proxy_plugin .= 'simple-obfs';
                }
                if (strpos($item['obfs'], 'http') !== false) {
                    $proxy_plugin_opts .= 'obfs=http';
                } elseif (strpos($item['obfs'], 'tls') !== false) {
                    $proxy_plugin_opts .= 'obfs=tls';
                } else {
                    $proxy_plugin_opts .= 'v2ray;' . $item['obfs_param'];
                }
                if ($item['obfs_param'] != '' && $item['obfs'] != 'v2ray') {
                    $proxy_plugin_opts .= ';obfs-host=' . $item['obfs_param'];
                }
            }
            $proxy[] = [
                'remarks' => $item['remark'],
                'server' => $item['address'],
                'server_port' => $item['port'],
                'password' => $item['passwd'],
                'method' => $item['method'],
                'plugin' => $proxy_plugin,
                'plugin_opts' => $proxy_plugin_opts,
                'plugin_args' => '',
                'timeout' => 5,
                'id' => $id,
                'ratio' => $item['ratio'],
                'subscription_url' => self::getSubinfo($user, 0)['ssd']
            ];
            $id++;
        }
        $plugin = '';
        $plugin_opts = '';
        if ($user->obfs == 'v2ray' || in_array($user->obfs, Config::getSupportParam('ss_obfs'))) {
            if ($user->obfs == 'v2ray') {
                $plugin .= 'v2ray';
            } else {
                $plugin .= 'simple-obfs';
            }
            if (strpos($user->obfs, 'http') !== false) {
                $plugin_opts .= 'obfs=http';
            } elseif (strpos($user->obfs, 'tls') !== false) {
                $plugin_opts .= 'obfs=tls';
            } else {
                $plugin_opts .= 'v2ray;' . $user->obfs_param;
            }
            if ($user->obfs_param != '' && $user->obfs != 'v2ray') {
                $plugin_opts .= ';obfs-host=' . $user->obfs_param;
            }
        }
        $config = [
            'configs' => $proxy,
            'strategy' => null,
            'index' => 0,
            'global' => false,
            'enabled' => true,
            'shareOverLan' => false,
            'isDefault' => false,
            'localPort' => 1080,
            'portableMode' => true,
            'pacUrl' => null,
            'useOnlinePac' => false,
            'secureLocalPac' => true,
            'availabilityStatistics' => false,
            'autoCheckUpdate' => true,
            'checkPreRelease' => false,
            'isVerboseLogging' => false,
            'logViewer' => [
                'topMost' => false,
                'wrapText' => false,
                'toolbarShown' => false,
                'Font' => 'Consolas, 8pt',
                'BackgroundColor' => 'Black',
                'TextColor' => 'White'
            ],
            'proxy' => [
                'useProxy' => false,
                'proxyType' => 0,
                'proxyServer' => '',
                'proxyPort' => 0,
                'proxyTimeout' => 3
            ],
            'hotkey' => [
                'SwitchSystemProxy' => '',
                'SwitchSystemProxyMode' => '',
                'SwitchAllowLan' => '',
                'ShowLogs' => '',
                'ServerMoveUp' => '',
                'ServerMoveDown' => '',
                'RegHotkeysAtStartup' => false
            ],
            'subscriptions' => [
                [
                    'airport' => Config::get('appName'),
                    'encryption' => $user->method,
                    'password' => $user->passwd,
                    'port' => $user->port,
                    'expiry' => $user->class_expire,
                    'traffic_used' => Tools::flowToGB($user->u + $user->d),
                    'traffic_total' => Tools::flowToGB($user->transfer_enable),
                    'url' => self::getSubinfo($user, 0)['ssd'],
                    'plugin' => $plugin,
                    'plugin_options' => $plugin_opts,
                    'plugin_arguments' => '',
                    'use_proxy' => false
                ]
            ]
        ];

        return json_encode($config, JSON_PRETTY_PRINT);
    }

    public static function getV2RayPcNConf($user)
    {
        $subUrl = self::getSubinfo($user, 0)['v2ray'];
        $subId = Uuid::uuid3(Uuid::NAMESPACE_DNS, $subUrl)->toString();
        $config = [
            'inbound' => [
                [
                    'localPort'         => 10808,
                    'protocol'          => 'socks',
                    'udpEnabled'        => true,
                    'sniffingEnabled'   => true,
                ],
            ],
            'logEnabled'        => false,
            'loglevel'          => 'warning',
            'index'             => 0,
            'vmess'             => [],
            'muxEnabled'        => false,
            'domainStrategy'    => 'IPIfNonMatch',
            'routingMode'       => '3',
            'useragent'         => [],
            'userdirect'        => [],
            'userblock'         => [],
            'kcpItem'           => [
                'mtu'               => 1350,
                'tti'               => 50,
                'uplinkCapacity'    => 12,
                'downlinkCapacity'  => 100,
                'congestion'        => false,
                'readBufferSize'    => 2,
                'writeBufferSize'   => 2,
            ],
            'listenerType'          => 0,
            'urlGFWList'            => 'https://raw.githubusercontent.com/gfwlist/gfwlist/master/gfwlist.txt',
            'allowLANConn'          => false,
            'enableStatistics'      => true,
            'statisticsFreshRate'   => 2000,
            'remoteDNS'             => '114.114.114.114,1.2.4.8,223.5.5.5,8,8,8,8',
            'subItem'               => [
                [
                    'id'        => $subId,
                    'remarks'   => $_ENV['appName'],
                    'url'       => $subUrl,
                    'enabled'   => true,
                ],
            ],
            'uiItem' => [
                'mainQRCodeWidth' => 600,
            ],
            'userPacRule' => []
        ];
        $Rule = [
            'type'   => 'vmess',
            'is_mu'  => ($_ENV['mergeSub'] === true ? 1 : 0),
            'emoji'  => $_ENV['add_emoji_to_node_name'],
            'extend' => $_ENV['enable_sub_extend'],
        ];
        $proxys = [];
        $items = URL::getNew_AllItems($user, $Rule);
        foreach ($items as $item) {
            if (!in_array($item['net'], ['tcp', 'ws', 'kcp', 'h2'])) {
                continue;
            }
            $proxy = [
                'configVersion'     => 2,
                'address'           => $item['add'],
                'port'              => $item['port'],
                'id'                => $item['id'],
                'alterId'           => $item['aid'],
                'security'          => 'auto',
                'network'           => $item['net'],
                'remarks'           => $item['remark'],
                'headerType'        => 'none',
                'requestHost'       => '',
                'path'              => '',
                'streamSecurity'    => '',
                'allowInsecure'     => '',
                'configType'        => 1,
                'testResult'        => '',
                'subid'             => $subId,
            ];
            switch ($item['net']) {
                case 'h2':
                    $proxy['requestHost'] = ($item['host'] != '' ? $item['host'] : $item['add']);
                    $proxy['path']        = $item['path'];
                    break;
                case 'ws':
                    $proxy['requestHost'] = ($item['host'] != '' ? $item['host'] : $item['add']);
                    $proxy['path']        = $item['path'];
                    break;
                case 'kcp':
                    $proxy['headerType']  = $item['type'];
                    break;
            }
            if ($item['tls'] == 'tls') {
                $proxy['streamSecurity'] = $item['tls'];
                if ($item['verify_cert'] == false){
                    $proxy['allowInsecure'] = 'true';
                }
            }
            $proxys[] = $proxy;
        }
        $config['vmess'] = $proxys;
        return json_encode($config, JSON_PRETTY_PRINT);
    }

    /**
     * 通用订阅，ssr & v2rayn
     *
     * @param object $user 用户
     * @param int    $sub  订阅类型
     * @param array  $opts request
     * @param array  $Rule 节点筛选规则
     *
     * @return string
     */
    public static function getSub($user, $sub, $opts, $Rule)
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
            default: // SSR
                $Rule['type'] = 'ssr';
                $getListExtend = $Rule['extend'] ? self::getListExtend($user, 'ssr') : [];
                break;
        }
        if ($Rule['extend']) {
            $return_url .= implode(PHP_EOL, $getListExtend) . PHP_EOL;
        }
        $return_url .= URL::get_NewAllUrl($user, $Rule);
        return base64_encode($return_url);
    }
}
