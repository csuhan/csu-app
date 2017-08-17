<?php

/**
 * Created by PhpStorm.
 * User: han
 * Date: 2017/8/1
 * Time: 12:09
 */
class CrossSiteMiddleWare
{
    public function __invoke($req,$res,$next)
    {
        $res = $next($req, $res);
        $res= $res->withAddedHeader("Access-Control-Allow-Origin","*")
            ->withAddedHeader("Access-Control-Allow-Headers",'Origin, X-Requested-With, Content-Type, Accept');
        return $res;
    }
}