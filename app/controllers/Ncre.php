<?php

/**
 * Created by PhpStorm.
 * User: han
 * Date: 2017/8/15
 * Time: 22:31
 */
use Curl\Curl;

class Ncre
{
    public function search($req,$response,$args)
    {
       return $response->withJson(
           $this->_searchNCRE(
               $args['jsjkcc'],
               $args['sfzh'],
               $args['bmlb']
           ));
    }

    //查询四六级
    private function _searchNCRE($jsjkcc,$sfzh,$bmlb)
    {
        $res = [
            'error'=>false,
            "errorMsg" =>'',
            'ncre_info'=>[],
        ];

        $curl = new Curl();
        $curl->setTimeout(120);
        $curl->post('http://exam.csu.edu.cn/searchfen.asp',[
            'jsjkcc'=>$jsjkcc,
            'sfzh'=>$sfzh,
            'bmlb'=>$bmlb
        ]);

        if($curl->error)
        {
            $res['error'] = true;
            $res['errorMsg'] = '服务器出了点问题，重试一下？';
            return $res;
        }

        $response = iconv('gbk','utf-8',$curl->response);
        //查询失败，信息输入有误
        if(strpos($response,'jsjfen.asp')=== false)
        {
            $res['error'] = true;
            $res['errorMsg'] = '没有查询到成绩，请确认输入信息无误';
            return $res;
        }
        //解析
        preg_match('/xm=(.*)&amp;zkzh=(.*)&amp;zcj=(.*)&amp;zsbh=(.*)">此处/',$response,$matches);
        $temp = '';
        switch ($matches[3])
        {
            case 0:
                $temp = '不及格';
                break;
            case 1:
                $temp = '及格';
                break;
            case 2:
                $temp = '良好';
                break;
            case 3:
                $temp = '优秀';
                break;
            default:
                $temp = '--';
        }
        $res['ncre_info'] = [
            'name'=>$matches[1],
            'zkzh'=>$matches[2],
            'type' =>$temp,
            'zsbh'=>urldecode($matches[4])
        ];

        return $res;

    }
}
