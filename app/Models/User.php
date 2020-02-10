<?php

namespace App\Models;

/**
 * User Model
 */

use App\Utils\Tools;
use App\Services\Config;
use Ramsey\Uuid\Uuid;

class User extends Model
{
    protected $connection = 'default';
    protected $table = 'user';

    public $isLogin;

    public $isAdmin;

    protected $casts = [
        't' => 'float',
        'u' => 'float',
        'd' => 'float',
        'port' => 'int',
        'transfer_enable' => 'float',
        'enable' => 'int',
        'is_admin' => 'boolean',
        'is_multi_user' => 'int',
        'node_speedlimit' => 'float',
    ];

    public function isAdmin()
    {
        return $this->attributes['is_admin'];
    }

    public function getMuMd5()
    {
        $str = str_replace(
            array('%id', '%suffix'),
            array($this->attributes['id'], Config::get('mu_suffix')),
            Config::get('mu_regex')
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

    public function getUuid()
    {
        return Uuid::uuid3(
            Uuid::NAMESPACE_DNS,
            $this->attributes['id'] . '|' . $this->attributes['passwd']
        )->toString();
    }

    /*
     * 总流量
     */
    public function enableTraffic()
    {
        $transfer_enable = $this->attributes['transfer_enable'];
        return Tools::flowAutoShow($transfer_enable);
    }

    /*
     * 总流量[GB]
     */
    public function enableTrafficInGB()
    {
        $transfer_enable = $this->attributes['transfer_enable'];
        return Tools::flowToGB($transfer_enable);
    }

    /*
     * 已用流量
     */
    public function usedTraffic()
    {
        $total = $this->attributes['u'] + $this->attributes['d'];
        return Tools::flowAutoShow($total);
    }

    /*
     * 已用流量占总流量的百分比
     */
    public function trafficUsagePercent()
    {
        $total = $this->attributes['u'] + $this->attributes['d'];
        $transferEnable = $this->attributes['transfer_enable'];
        if ($transferEnable == 0) {
            return 0;
        }
        $percent = $total / $transferEnable;
        $percent = round($percent, 2);
        $percent *= 100;
        return $percent;
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

    /*
     * 剩余流量占总流量的百分比
     */
    public function unusedTrafficPercent()
    {
        $transferEnable = $this->attributes['transfer_enable'];
        if ($transferEnable == 0) {
            return 0;
        }
        $unusedTraffic = $transferEnable - ($this->attributes['u'] + $this->attributes['d']);
        $percent = $unusedTraffic / $transferEnable;
        $percent = round($percent, 2);
        $percent *= 100;
        return $percent;
    }

    /*
     * 今天使用的流量
     */
    public function TodayusedTraffic()
    {
        $total = $this->attributes['u'] + $this->attributes['d'] - $this->attributes['last_day_t'];
        return Tools::flowAutoShow($total);
    }

    /*
     * 今天使用的流量占总流量的百分比
     */
    public function TodayusedTrafficPercent()
    {
        $transferEnable = $this->attributes['transfer_enable'];
        if ($transferEnable == 0) {
            return 0;
        }
        $TodayusedTraffic = $this->attributes['u'] + $this->attributes['d'] - $this->attributes['last_day_t'];
        $percent = $TodayusedTraffic / $transferEnable;
        $percent = round($percent, 2);
        $percent *= 100;
        return $percent;
    }

    /*
     * 今天之前已使用的流量
     */
    public function LastusedTraffic()
    {
        $total = $this->attributes['last_day_t'];
        return Tools::flowAutoShow($total);
    }

    /*
     * 今天之前已使用的流量占总流量的百分比
     */
    public function LastusedTrafficPercent()
    {
        $transferEnable = $this->attributes['transfer_enable'];
        if ($transferEnable == 0) {
            return 0;
        }
        $LastusedTraffic = $this->attributes['last_day_t'];
        $percent = $LastusedTraffic / $transferEnable;
        $percent = round($percent, 2);
        $percent *= 100;
        return $percent;
    }

    /**
     * 清理订阅缓存
     */
    public function cleanSubCache()
    {
        $id = $this->attributes['id'];
        $user_path = (BASE_PATH . '/storage/SubscribeCache/' . $id . '/');
        if (is_dir($user_path)) {
            Tools::delDirAndFile($user_path);
        }
    }
}
