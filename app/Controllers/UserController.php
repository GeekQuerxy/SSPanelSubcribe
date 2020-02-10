<?php

namespace App\Controllers;

use App\Services\Config;
use App\Models\User;
use App\Models\Token;
use App\Utils\Tools;
use Slim\Http\{Request, Response};

/**
 *  UserController
 */
class UserController extends BaseController
{
    /**
     * 获取包含订阅信息的客户端压缩档
     *
     * @param Request  $request
     * @param Response $response
     * @param array    $args
     */
    public function getPcClient($request, $response, $args)
    {
        $zipArc = new \ZipArchive();
        $user_token = LinkController::GenerateSSRSubCode($this->user->id, 0);
        $type = trim($request->getQueryParams()['type']);
        // 临时文件存放路径
        $temp_file_path = '../storage/';
        // 客户端文件存放路径
        $client_path = '../resources/clients/';
        switch ($type) {
            case 'ss-win':
                $temp_file_path .= $type . '_' . $user_token . '.zip';
                $user_config_file_name = 'gui-config.json';
                $content = LinkController::getSSPcConf($this->user);
                $client_path .= $type . '/';
                break;
            case 'ssd-win':
                $temp_file_path .= $type . '_' . $user_token . '.zip';
                $user_config_file_name = 'gui-config.json';
                $content = LinkController::getSSDPcConf($this->user);
                $client_path .= $type . '/';
                break;
            case 'ssr-win':
                $temp_file_path .= $type . '_' . $user_token . '.zip';
                $user_config_file_name = 'gui-config.json';
                $content = LinkController::getSSRPcConf($this->user);
                $client_path .= $type . '/';
                break;
            case 'v2rayn-win':
                $temp_file_path .= $type . '_' . $user_token . '.zip';
                $user_config_file_name = 'guiNConfig.json';
                $content = LinkController::getV2RayPcNConf($this->user);
                $client_path .= $type . '/';
                break;
            default:
                return 'gg';
        }
        // 文件存在则先删除
        if (is_file($temp_file_path)) {
            unlink($temp_file_path);
        }
        // 超链接文件内容
        $site_url_content = '[InternetShortcut]' . PHP_EOL . 'URL=' . Config::get('baseUrl');
        // 创建 zip 并添加内容
        $zipArc->open($temp_file_path, \ZipArchive::CREATE);
        $zipArc->addFromString($user_config_file_name, $content);
        $zipArc->addFromString('点击访问_' . Config::get('appName') . '.url', $site_url_content);
        Tools::folderToZip($client_path, $zipArc, strlen($client_path));
        $zipArc->close();

        $newResponse = $response->withHeader('Content-type', ' application/octet-stream')->withHeader('Content-Disposition', ' attachment; filename=' . $type . '.zip');
        $newResponse->write(file_get_contents($temp_file_path));
        unlink($temp_file_path);

        return $newResponse;
    }

    public function getClientfromToken($request, $response, $args)
    {
        $token = $args['token'];
        $Etoken = Token::where('token', '=', $token)->where('create_time', '>', time() - 60 * 10)->first();
        if ($Etoken == null) {
            return '下载链接已失效，请刷新页面后重新点击.';
        }
        $user = User::find($Etoken->user_id);
        if ($user == null) {
            return null;
        }
        $this->user = $user;
        return self::getPcClient($request, $response, $args);
    }
}
