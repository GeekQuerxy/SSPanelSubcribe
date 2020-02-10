<?php

namespace App\Command;

/***
 * Class XCat
 * @package App\Command
 */

use App\Utils\Tools;
use App\Services\Config;

class XCat
{
    public $argv;

    public function __construct($argv)
    {
        $this->argv = $argv;
    }

    public function boot()
    {
        switch ($this->argv[1]) {
            case ('initQQWry'):
                return $this->initQQWry();
            case ('update'):
                return Update::update($this);
            case ('delSubCache'):
                return $this->delSubCache();
            default:
                return $this->defaultAction();
        }
    }

    public function defaultAction()
    {
        echo (PHP_EOL . '用法： php xcat [选项]' . PHP_EOL);
        echo ('常用选项:' . PHP_EOL);
        echo ('  initQQWry - 下载 IP 解析库' . PHP_EOL);
        echo ('  update - 更新并迁移配置' . PHP_EOL);
    }

    public function initQQWry()
    {
        echo ('开始下载纯真 IP 数据库....');
        $qqwry = file_get_contents('https://qqwry.mirror.noc.one/QQWry.Dat?from=sspanel_uim');
        if ($qqwry != '') {
            $fp = fopen(BASE_PATH . '/storage/qqwry.dat', 'wb');
            if ($fp) {
                fwrite($fp, $qqwry);
                fclose($fp);
                echo ('纯真 IP 数据库下载成功！');
            } else {
                echo ('纯真 IP 数据库保存失败！');
            }
        } else {
            echo ('下载失败！请重试，或在 https://github.com/SukkaW/qqwry-mirror/issues/new 反馈！');
        }
    }

    public function delSubCache()
    {
        if (Config::get('enable_sub_cache') === true) {
            if (Tools::delSubCache() === true) {
                echo (PHP_EOL . '订阅缓存清理成功.' . PHP_EOL);
            } else {
                echo (PHP_EOL . '订阅缓存清理失败.' . PHP_EOL);
            }
        } else {
            echo (PHP_EOL . '订阅缓存未开启.' . PHP_EOL);
        }
    }
}
