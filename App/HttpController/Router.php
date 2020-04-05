<?php

namespace App\HttpController;

use EasySwoole\Http\AbstractInterface\AbstractRouter;
use FastRoute\RouteCollector;
use EasySwoole\Http\Request;
use EasySwoole\Http\Response;

class Router extends AbstractRouter
{
    function initialize(RouteCollector $routeCollector)
    {
        $this->setGlobalMode(true);

        $routeCollector->get('/', function (Request $request, Response $response) {
            $response->write('<pre style="text-align:center; height:100px; line-height:100px; word-wrap: break-word; white-space: pre-wrap;">geekSubcribeX backend</pre>');
            return false;
        });

        $routeCollector->get('/link/{token}', '/Links');

        $this->setRouterNotFoundCallBack(function (Request $request, Response $response) {
            $response->withStatus(404);
            $response->write('<pre style="text-align:center; height:100px; line-height:100px; word-wrap: break-word; white-space: pre-wrap;">404 not found</pre>');
            return false;
        });
    }
}
