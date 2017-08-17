<?php
use \Curl\Curl;
require APP_PATH.'/library/simple_html_dom.php';
/**
 * Created by PhpStorm.
 * User: han
 * Date: 2017/8/1
 * Time: 12:28
 */
class Bus
{
    private $bus_url = 'http://app.its.csu.edu.cn/csu-app/cgi-bin/depa/depa?method=search';

    public function search($req,$res,$args)
    {
        $data = $this->parse_bus_data($req->getParsedBody());
       return  $res->withJson($data);

    }

    //解析校车数据
    private function parse_bus_data($data)
    {
        $res = [
            'error'=>false,
            'errorMsg'=>'',
            'buses'=>[],
        ];

        $curl = new Curl();
        $curl->post($this->bus_url,$data);
        //出现错误
        if($curl->error){
            $res['error'] = 1;
            $res['errorMsg'] = $curl->errorMessage;
            return $res;
        }

        //判断此时段是否有车辆
        if(strpos($curl->response,'您本次搜索没有查询到班车信息') !== false)
        {
            //不存在车辆
            $res['error'] = 2;
            $res['errorMsg'] = '没有找到哎，换个时间查查？*';
            return $res;
        }
        //开始解析html
        $html = str_get_html($curl->response);

        $buses = $html->find('.busClassDiv');

        foreach ($buses as $key => $bus)
        {
            //解析time,day
            $temp = $bus->children(0)->children(0)->plaintext;
            preg_match('/起站发车时间：(.*)&nbsp;&nbsp;(.*)/',$temp,$matches);
            $res['buses'][$key]['time'] = $matches[1];
            $res['buses'][$key]['day'] = $matches[2];

            //解析座位数，车辆数
            $temp = $bus->children(3)->plaintext;
            preg_match('/台数：(.*)台.*座位数：(.*)座/',$temp,$matches);
            $res['buses'][$key]['site'] = $matches[2];
            $res['buses'][$key]['num'] = $matches[1];

            //起始站点，结束站点
            $res['buses'][$key]['start'] = $data['startValue'];
            $res['buses'][$key]['end'] = $data['endValue'];

            //解析站点
            $temp = $bus->children(2)->children();

            foreach ($temp as $i => $item)
            {
                //$i = 1 为站点名，忽略
                if($i == 0) continue;
                $res['buses'][$key]['stations'][$i] = $item->plaintext;
            }

        }

        $curl->close();

        return $res;

    }
}