<?php

namespace App\HttpController;

use App\Models\Link;
use App\Utils\Subcribe;
use EasySwoole\Http\AbstractInterface\Controller;
use EasySwoole\EasySwoole\Config;
use EasySwoole\EasySwoole\ServerManager;

class Links extends Controller
{
    public function index()
    {
        // 查询 token 对应记录
        $sToken = Link::where('type', 11)->where('token', $this->request()->getQueryParam('token'))->first();
        if ($sToken === null) {
            // token 不存在
            return $this->response()
                ->withStatus(404)
                ->write('not found.');
        }

        // 取得 token 对应用户
        $user = $sToken->getUser();
        if ($user === null) {
            // 用户不存在
            // 删除该 token 记录
            $sToken->delete();
            return $this->response()
                ->withStatus(404)
                ->write('not found user.');
        }

        // 配置
        $config        = Config::getInstance();

        // 筛选节点的类型
        $Rule['type']  = $this->requestQuery('type', 'all');

        // 合并订阅以及单端口
        $Rule['is_mu'] = (int) $this->requestQuery('mu', $config->getConf('SUB.mergeSub'));

        // 获取指定等级的节点
        if ($this->requestFilled('class')) {
            $class = trim(urldecode($this->requestQuery('class')));
            $Rule['content']['class'] = array_map(
                function ($item) {
                    return (int) $item;
                },
                explode('-', $class)
            );
        }

        // 去除指定等级的节点
        if ($this->requestFilled('noclass')) {
            $noclass = trim(urldecode($this->requestQuery('noclass')));
            $Rule['content']['noclass'] = array_map(
                function ($item) {
                    return (int) $item;
                },
                explode('-', $noclass)
            );
        }

        // 使用正则对节点名称进行筛选
        if ($this->requestFilled('regex')) {
            $Rule['content']['regex'] = trim(urldecode($this->requestQuery('regex')));
        }

        // 为节点名称添加 Emoji
        $Rule['emoji']  = (bool) $this->requestQuery('emoji', $config->getConf('SUB.addEmoji'));

        // 控制订阅中显示流量以及到期时间等
        $Rule['extend'] = (bool) $this->requestQuery('extend', $config->getConf('SUB.extend'));

        // 当前访问 URL
        $Rule['URL'] = $config->getConf('baseUrl') . $this->request()->getServerParams()['request_uri'] . '?'. $this->request()->getServerParams()['query_string'];

        // 订阅类型
        $subscribe_type = '';

        $opts = $this->request()->getQueryParams();

        $sub_type_array = ['list', 'ssd', 'clash', 'surge', 'surfboard', 'quantumult', 'quantumultx', 'sub'];
        foreach ($sub_type_array as $key) {
            if (isset($opts[$key])) {
                $query_value = $opts[$key];
                if ($query_value != '0' && $query_value != '') {

                    // 兼容代码开始
                    if ($key == 'sub' && $query_value > 4) {
                        $query_value = 1;
                    }
                    // 兼容代码结束

                    if ($key == 'list') {
                        $SubscribeExtend = $this->getSubscribeExtend($query_value);
                    } else {
                        $SubscribeExtend = $this->getSubscribeExtend($key, $query_value);
                    }
                    $filename = $SubscribeExtend['filename'] . '_' . time() . '.' . $SubscribeExtend['suffix'];
                    $subscribe_type = $SubscribeExtend['filename'];

                    $class = ('get' . $SubscribeExtend['class']);
                    $content = Subcribe::$class($user, $query_value, $opts, $Rule);
                    break;
                }
                continue;
            }
        }

        $this->response()->withHeader('Content-type', ' application/octet-stream; charset=utf-8');
        $this->response()->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
        $this->response()->withHeader('Content-Disposition', ' attachment; filename=' . $filename);
        $this->response()->withHeader(
            'Subscription-Userinfo',
            (' upload=' . $user->u
                . '; download=' . $user->d
                . '; total=' . $user->transfer_enable
                . '; expire=' . strtotime($user->class_expire))
        );
        $this->response()->write($content);

        // 记录订阅日志
        if ($config->getConf('SUB.Log') === true) {
            $user->addSubLog($subscribe_type, $this->getRemoteIP(), $this->request()->getHeader('user-agent'));
        }

        return $this->response()->end();
    }

    /**
     * 获取订阅类型的文件名
     *
     * @param string $type  订阅类型
     * @param string $value
     */
    public function getSubscribeExtend(string $type, string $value = null): array
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
                $strArray = [
                    1 => 'ssr',
                    2 => 'ss',
                    3 => 'v2rayn',
                    4 => 'trojan',
                ];
                $str = (!in_array($value, $strArray) ? $strArray[$value] : $strArray[1]);
                $return = self::getSubscribeExtend($str);
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
     * 判断查询字符串是否存在且不为空
     *
     * @param mixed $key
     */
    public function requestFilled($key): bool
    {
        $params = $this->request()->getQueryParams();
        $keys = is_array($key) ? $key : func_get_args();
        foreach ($keys as $value) {
            if (!isset($params[$value])) {
                return false;
            }
            if ($params[$value] == '') {
                return false;
            }
        }
        return true;
    }

    /**
     * 获取查询字符串或返回默认值
     *
     * @param string $key
     * @param mixed  $default
     */
    public function requestQuery(string $key, $default = null)
    {
        if (isset($this->request()->getQueryParams()[$key])) {
            return $this->request()->getQueryParams()[$key];
        }
        if ($default !== null) {
            return $default;
        }
        return null;
    }

    /**
     * 取得客户端 IP
     */
    public function getRemoteIP(): string
    {
        $info = ServerManager::getInstance()
            ->getSwooleServer()
            ->connection_info(
                $this->request()->getSwooleRequest()->fd
            );
        return $info['remote_ip'];
    }
}
