<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

require './vendor/autoload.php';

//定义应用目录
define('APP_PATH' ,realpath('./app/'));

//配置文件
$config = require(APP_PATH.'/config/config.php');
//初始化应用实例
$app = new \Slim\App($config);

//注册autoload
spl_autoload_register(function ($className){
    //类库，中间件
    if(is_file(APP_PATH.'/classes/'.$className.'.php'))
    {
        require(APP_PATH.'/classes/'.$className.'.php');
    }
    //控制器
    if(is_file(APP_PATH.'/controllers/'.$className.'.php'))
    {
        require(APP_PATH.'/controllers/'.$className.'.php');

    }
});


//加载路由
$app->any('/',function (){
    echo "hello";
});
//校车查询
$app->any('/bus','\Bus:search');
//公告通知
$app->any('/notice/{page_id}','\Notice:get_notice');
$app->any('/notice/article/{article_id}','\Notice:get_article');
$app->any('/notice/search/{key_word}/{page_id}','\Notice:search');
//四六级查询
$app->any('/cet/{stu_no}','\Cet:search');
$app->any('/cet/zkzh/{stu_no}','\Cet:searchzkzh');

//计算机等级查询
$app->any('/ncre/{jsjkcc}/{sfzh}/{bmlb}','\Ncre:search');

//成绩查询
$app->any('/jwclogin/{id}/{pwd}','\Jwc:login');
$app->any('/grade/{id}/{pwd}','\Jwc:searchGrade');
$app->any('/class/{id}/{pwd}/{xq}/{zc}','\Jwc:searchClass');
//测试
$app->any('/test','\Test:testcet');


//加载中间件
//跨域请求，用于调试
$app->add(new \CrossSiteMiddleWare);

$app->run();