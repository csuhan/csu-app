<?php

/**
 * Created by PhpStorm.
 * User: han
 * Date: 2017/8/3
 * Time: 18:53
 */
use \Curl\Curl;
require APP_PATH.'/library/simple_html_dom.php';
class Cet
{
    private $CET_INFO_URL = 'http://202.197.61.241/cetmodifyb.asp';

    //查询四六级成绩
    public function search($req,$response,$args)
    {
        $res = [
            'error' => false,
            'errorMsg' =>'',
            'info'=>[],
            'score'=>[]
        ];

        $cet_info = $this->_search_cet_info($args['stu_no']);
        //准考证号查询错误
        if($cet_info['error']){
            $res['error'] = true;
            $res['errorMsg'] = $cet_info['errorMsg'];
            return $response->withJson($res);
        }
        $res['info'] = $cet_info['info'];
        //查询分数
        //TODO:临时测试
        $score_info = $this->_searchByzkzh($cet_info['info']['zkzh'],$cet_info['info']['name']);
//        $score_info = $this->_searchByzkzh("430021162223408","杨敏");
        if($score_info['error'])
        {
            $res['error'] = true;
            $res['errorMsg'] = $score_info['errorMsg'];
            return $response->withJson($res);
        }
        $res['score'] = $score_info['score'];

        return $response->withJson($res);

    }

    //查询准考证号
    public function searchzkzh($req,$response,$args)
    {
        return $response->withJson($this->_search_cet_info($args['stu_no']));
    }


    //查询四六级考试信息
    private function _search_cet_info($id)
    {
        $res = [
            'error'=>false,
            'errorMsg'=>'',
            'info'=>[],
        ];

        $curl = new Curl();
        //超时改为60秒,校内服务器太渣渣
        $curl->setDefaultTimeout(120);


        //尝试考试级别，4级或者6级
        $cet_type = [iconv('utf-8','gb2312','四级'),iconv('utf-8','gb2312','六级')];
        for ($i = 0;$i<2;$i++)
        {
            $curl->post($this->CET_INFO_URL,[
                'username'=>$id,
                'bmlb'=>$cet_type[$i]
            ]);

            if($curl->error)
            {
                $res['error']  = true;
                $res['errorMsg'] = "校内服务器出了点问题，重试一下？";//$curl->errorMessage;
                return $res; //返回
            }

            //判断
            if($curl->responseHeaders['Content-Length'] < 1000) //不是此等级|四级
                continue;
            else //是四级或者6级
            {
                break; //停止继续查询考试级别
            }
        }

        //开始解析考试信息
        preg_match("/id=zkz value=\"(.*)\" size=/", $curl->response,$zkzh);
        preg_match("/id=zkz0 value=\"(.*)\" size=25/", $curl->response,$name);
        preg_match("/id=bm0 value=\"(.*)\" size=/",$curl->response,$type);

        if(!isset($zkzh[1]) && !isset($name[1]) && !isset($type[1])) //未匹配到，莫名错误|此人根本没有参加考试,md
        {
                $res['error'] = true;
                $res['errorMsg'] = '没有找到你的信息哎，重试一下？';
                return $res;
        }
        //匹配到
        $res['info']['zkzh'] = $zkzh[1];
        $res['info']['name'] = iconv("gbk","utf-8//IGNORE",trim($name[1]));
        $res['info']['type'] = iconv("gbk","utf-8//IGNORE",trim($type[1]));

        $curl->close();

        return $res;
    }

    private function _searchByzkzh($zkzh,$name)
    {
        //结果数组
        $res = [
            'error' => false,
            'errorMsg' => '',
            'score' => []
        ];

        $curl = new Curl();
        $curl->setReferer('http://www.chsi.com.cn/cet/');
        $curl->setUserAgent('Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.90 Safari/537.36');
        $curl->setTimeout(120);
        //请求
        $curl->get('http://www.chsi.com.cn/cet/query',[
            'zkzh'=>$zkzh,
            'xm'=>$name
        ]);

        //网络错误类
        if($curl->error)
        {
            $res['error'] = true;
            $res['errorMsg'] = '服务器出了点问题，重试一下？';
            return $res;
        }
        //判断是否已经查询出结果，还是有验证码
        if(strpos($curl->response,'笔试成绩') === false)//有错误，已经被屏蔽
        {
            $res['error'] = true;
            $res['errorMsg'] = '当前查询人数较多，请选择官方入口查询';
            return $res;
        }

        //开始解析
        $html = str_get_html($curl->response);
        $form = $html->find('.m_cnt_m',0)->find('tr>td');
        foreach ($form as $key => $value)
        {
            $res['score'][$key]=trim($value->plaintext);
        }
        $curl->close();
        return $res;

    }


    //TODO:99宿舍接口已废弃
//    public function searchByzkzh($req,$response,$args)
//    {
//        $res = [
//            'error'=>false,
//            'errorMsg' =>'',
//            'info'=>[],
//            'score'=>[],
//        ];
//        $cet_score = $this->_search_cet_score($args['zkzh'],mb_substr($args['name'],0,2));
//        //分数查询出错
//        if($cet_score['error'])
//        {
//            $res = [
//                'error'=>true,
//                'errorMsg' =>$cet_score['errorMsg'],
//            ];
//
//            return $response->withJson($res);
//        }
//
//        $type = substr($args['zkzh'],9,1);
//        if($type == 1) $res['info']['type'] = '四级';
//        if($type == 2) $res['info']['type'] = '六级';
//
//        $res['score'] = $cet_score['score'];
//        return $response->withJson($res);
//    }
//
//


    //查询四六级考试成绩
    //TODO:99宿舍接口已失效
//    private function _search_cet_score($zkzh,$name)
//    {
//        $res = [
//            'error'=>false,
//            'errorMsg'=>'',
//            'score'=>[],
//        ];
//
//        $curl = new Curl();
//        $curl->setDefaultTimeout(60);
//
//        //添加referer，否则返回1
//        $curl->setReferer('http://cet.99sushe.com/');
//
//        $curl->post($this->CET_SCORE_URL.$zkzh,[
//            "id"=>$zkzh,
//            "name"=>iconv('utf-8','gb2312',$name)
//        ]);
//
//        //网络错误
//        if($curl->error)
//        {
//            $res['error'] = true;
//            $res['errorMsg'] = '服务器开小差了，重试一下？';
//            return $res;
//        }
//
//        $response = $curl->response;
//        $curl->close();
//
//        $temp = explode(',',iconv('gb2312','utf-8',$response) );
//        //解析错误码处理
//        if(!isset($temp[10]))
//        {
//            $res['error'] = true;
//            $res['errorMsg'] = '无法找到对应的分数,请确认你输入的准考证号及姓名无误';
//            return $res;
//        }
//        //正常解析
//        $res['score'] = [
//            'zkzh'=>$temp[1],
//            'listen'=>$temp[2],
//            'read'=>$temp[3],
//            'write'=>$temp[4],
//            'total'=>$temp[5],
//            'school'=>$temp[6],
//            'name'=>$temp[7],
//        ];
//
//        //口语考试
//        if($temp[9]=='--') //未参加口语考试
//        {
//            $res['score']['speakingId']='--';
//            $res['score']['speakingScore'] = '--';
//        }
//        return $res;
//    }


}