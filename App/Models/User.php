<?php

namespace App\Models;

use App\Utils\{
    Conf,
    Subcribe,
    Tools
};
use EasySwoole\EasySwoole\Config;
use Illuminate\Database\Capsule\Manager;
use voku\helper\AntiXSS;
use Ramsey\Uuid\Uuid;

class User extends Models
{
    /**
     * 与模型关联的表名
     *
     * @var string
     */
    protected $table = 'user';

    protected $casts = [
        't'               => 'float',
        'u'               => 'float',
        'd'               => 'float',
        'port'            => 'int',
        'transfer_enable' => 'float',
        'enable'          => 'int',
        'is_admin'        => 'boolean',
        'is_multi_user'   => 'int',
        'node_speedlimit' => 'float',
        'sendDailyMail'   => 'int'
    ];

    public function getUuid()
    {
        return Uuid::uuid3(
            Uuid::NAMESPACE_DNS,
            $this->attributes['id'] . '|' . $this->attributes['passwd']
        )->toString();
    }

    public function getMuMd5()
    {
        $str = str_replace(
            array('%id', '%suffix'),
            array($this->attributes['id'], Config::getInstance()->getConf('mu_suffix')),
            Config::getInstance()->getConf('mu_regex')
        );
        preg_match_all("|%-?[1-9]\d*m|U", $str, $matches, PREG_PATTERN_ORDER);
        foreach ($matches[0] as $key) {
            $key_match = str_replace(array('%', 'm'), '', $key);
            $md5 = substr(
                MD5($this->attributes['id'] . $this->attributes['passwd'] . $this->attributes['method'] . $this->attributes['obfs'] . $this->attributes['protocol']),
                ($key_match < 0 ? $key_match : 0),
                abs($key_match)
            );
            $str = str_replace($key, $md5, $str);
        }
        return $str;
    }

    /*
     * 剩余流量
     */
    public function unusedTraffic()
    {
        $total = $this->attributes['u'] + $this->attributes['d'];
        $transfer_enable = $this->attributes['transfer_enable'];
        return Tools::flowAutoShow($transfer_enable - $total);
    }

    /**
     * 获取全部节点对象
     *
     * @param mixed $sort  数值或数组
     * @param array $rules 节点筛选规则
     */
    public function getNodes($sort, array $rules = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = Node::query();
        if (is_array($sort)) {
            $query->whereIn('sort', $sort);
        } else {
            $query->where('sort', $sort);
        }
        if (!$this->is_admin) {
            $group = ($this->node_group != 0 ? [0, $this->node_group] : [0]);
            $query->whereIn('node_group', $group)
                ->where('node_class', '<=', $this->class);
        }
        // 等级筛选
        if (isset($rules['content']['class']) && count($rules['content']['class']) > 0) {
            $query->whereIn('node_class', $rules['content']['class']);
        }
        if (isset($rules['content']['noclass']) && count($rules['content']['noclass']) > 0) {
            $query->whereNotIn('node_class', $rules['content']['noclass']);
        }
        // 等级筛选 end
        $nodes = $query->where('type', '1')
            ->orderBy('name')->get();

        return $nodes;
    }

    /**
     * 获取全部节点
     *
     * ```
     * $Rule = [
     *      'type'    => 'all | ss | ssr | vmess | trojan',
     *      'emoji'   => false,
     *      'is_mu'   => 1,
     *      'content' => [
     *          'noclass' => [0, 1, 2],
     *          'class'   => [0, 1, 2],
     *          'regex'   => '.*香港.*HKBN.*',
     *      ]
     * ]
     * ```
     *
     * @param array $Rule 节点筛选规则
     */
    public function getNew_AllItems(array $Rule): array
    {
        $is_ss = [0];
        $is_mu = (isset($Rule['is_mu']) ? $Rule['is_mu'] : (int) Config::getInstance()->getConf('SUB.mergeSub'));
        $emoji = (isset($Rule['emoji']) ? $Rule['emoji'] : false);

        switch ($Rule['type']) {
            case 'ss':
                $sort = [0, 10, 13];
                $is_ss = [1];
                break;
            case 'ssr':
                $sort = [0, 10];
                break;
            case 'vmess':
                $sort = [11, 12];
                break;
            case 'trojan':
                $sort = [14];
                break;
            default:
                $Rule['type'] = 'all';
                $sort = [0, 10, 11, 12, 13, 14];
                $is_ss = [0, 1];
                break;
        }

        // 获取节点
        $nodes = $this->getNodes($sort, $Rule);

        // 单端口 sort = 9
        $mu_nodes = [];
        if ($is_mu != 0 && in_array($Rule['type'], ['all', 'ss', 'ssr'])) {
            $mu_node_query = Node::query();
            $mu_node_query->where('sort', 9)->where('type', '1');
            if ($is_mu != 1) {
                $mu_node_query->where('server', $is_mu);
            }
            if (!$this->is_admin) {
                $group = ($this->node_group != 0 ? [0, $this->node_group] : [0]);
                $mu_node_query->where('node_class', '<=', $this->class)
                    ->whereIn('node_group', $group);
            }
            $mu_nodes = $mu_node_query->get();
        }

        // 获取适用于用户的中转规则
        $relay_rules = $this->getRelays();

        $return_array = [];
        foreach ($nodes as $node) {
            if (isset($Rule['content']['regex']) && $Rule['content']['regex'] != '') {
                // 节点名称筛选
                if (
                    Conf::getMatchProxy(
                        [
                            'remark' => $node->name
                        ],
                        [
                            'content' => [
                                'regex' => $Rule['content']['regex']
                            ]
                        ]
                    ) === null
                ) {
                    continue;
                }
            }
            // 筛选 End

            // 其他类型单端口节点
            if (in_array($node->sort, [11, 12, 13, 14])) {
                $node_class = [
                    11 => 'getV2RayItem',           // V2Ray
                    12 => 'getV2RayItem',           // V2Ray
                    13 => 'getV2RayPluginItem',     // Rico SS (V2RayPlugin && obfs)
                    14 => 'getTrojanItem',          // Trojan
                ];
                $class = $node_class[$node->sort];
                $item = $node->$class($this, 0, 0, 0, $emoji);
                if ($item != null) {
                    $return_array[] = $item;
                }
                continue;
            }
            // 其他类型单端口节点 End

            // SS 节点
            if (in_array($node->sort, [0, 10])) {
                // 节点非只启用单端口 && 只获取普通端口
                if ($node->mu_only != 1 && ($is_mu == 0 || ($is_mu != 0 && Config::getInstance()->getConf('SUB.mergeSub') === true))) {
                    foreach ($is_ss as $ss) {
                        if ($node->sort == 10) {
                            // SS 中转
                            $relay_rule_id = 0;
                            $relay_rule = Tools::pick_out_relay_rule($node->id, $this->port, $relay_rules);
                            if ($relay_rule != null && $relay_rule->dist_node() != null) {
                                $relay_rule_id = $relay_rule->id;
                            }
                            $item = $node->getItem($this, 0, $relay_rule_id, $ss, $emoji);
                        } else {
                            // SS 非中转
                            $item = $node->getItem($this, 0, 0, $ss, $emoji);
                        }
                        if ($item != null) {
                            $return_array[] = $item;
                        }
                    }
                }
                // 获取 SS 普通端口 End

                // 非只启用普通端口 && 获取单端口
                if ($node->mu_only != -1 && $is_mu != 0) {
                    foreach ($is_ss as $ss) {
                        foreach ($mu_nodes as $mu_node) {
                            if ($node->sort == 10) {
                                // SS 中转
                                $relay_rule_id = 0;
                                $relay_rule = Tools::pick_out_relay_rule($node->id, $mu_node->server, $relay_rules);
                                if ($relay_rule != null && $relay_rule->dist_node() != null) {
                                    $relay_rule_id = $relay_rule->id;
                                }
                                $item = $node->getItem($this, $mu_node->server, $relay_rule_id, $ss, $emoji);
                            } else {
                                // SS 非中转
                                $item = $node->getItem($this, $mu_node->server, 0, $ss, $emoji);
                            }
                            if ($item != null) {
                                $return_array[] = $item;
                            }
                        }
                    }
                }
                // 获取 SS 单端口 End
            }
            // SS 节点 End
        }

        return $return_array;
    }

    /**
     * 获取全部节点 Url
     *
     * ```
     *  $Rule = [
     *      'type'    => 'ss | ssr | vmess',
     *      'emoji'   => false,
     *      'is_mu'   => 1,
     *      'content' => [
     *          'noclass' => [0, 1, 2],
     *          'class'   => [0, 1, 2],
     *          'regex'   => '.*香港.*HKBN.*',
     *      ]
     *  ]
     * ```
     *
     * @param array $Rule 节点筛选规则
     */
    public function get_NewAllUrl(array $Rule): string
    {
        $return_url = '';
        if (strtotime($this->expire_in) < time()) {
            return $return_url;
        }
        $items = $this->getNew_AllItems($Rule);
        foreach ($items as $item) {
            if ($item['type'] == 'vmess') {
                $out = Subcribe::getListItem($item, 'v2rayn');
            } else {
                $out = Subcribe::getListItem($item, $Rule['type']);
            }
            if ($out !== null) {
                $return_url .= $out . PHP_EOL;
            }
        }
        return $return_url;
    }

    /**
     * 获取订阅链接 Token
     */
    public function getSubToken():? string
    {
        $Elink = Link::where('type', 11)->where('userid', $this->id)->first();
        return ($Elink != null ? $Elink->token : null);
    }

    /**
     * 添加订阅记录
     *
     * @param string $type
     * @param string $ip
     * @param string $ua
     */
    public function addSubLog($type, $ip, $ua): void
    {
        Manager::table('user_subscribe_log')
            ->insert(
                [
                    'user_name'           => $this->user_name,
                    'user_id'             => $this->id,
                    'email'               => $this->email,
                    'subscribe_type'      => $type,
                    'request_ip'          => $ip,
                    'request_time'        => date('Y-m-d H:i:s'),
                    'request_user_agent'  => (new AntiXSS())->xss_clean($ua)
                ]
            );
    }

    /**
     * 获取转发规则
     */
    public function getRelays()
    {
        return (!Tools::is_protocol_relay($this)
            ? []
            : Relay::where('user_id', $this->id)->orwhere('user_id', 0)->orderBy('id', 'asc')->get());
    }
}
