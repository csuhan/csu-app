<?php

/**
 * Created by PhpStorm.
 * User: han
 * Date: 2017/8/16
 * Time: 9:55
 */
require APP_PATH.'/library/simple_html_dom.php';
use Curl\Curl;

class Jwc
{
    //全局curl
    private $curl;
    private $cookies;
    private $base_url = 'http://csujwc.its.csu.edu.cn/';

    public function __construct()
    {
        //初始化
        $this->curl = new Curl();
        $this->curl->setTimeout(120);
        $this->curl->setUserAgent('Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.90 Safari/537.36');

    }

    //成绩查询
    public function searchGrade($req,$response,$args)
    {
        $res = $this->_searchGrade($args['id'],$args['pwd']);
        return $response->withJson($res);
    }

    //课表查询
    public function searchClass($req,$response,$args)
    {
        if($args['zc'] == '0') $args['zc'] = '';
        $res = $this->_searchClass($args['id'],$args['pwd'],$args['xq'],$args['zc']);
        return $response->withJson($res);
    }

    //验证是否登录成功
    public function login($req,$response,$args)
    {
        return $response->withJson($this->_login($args['id'],$args['pwd']));
    }

    //登录教务系统
    /**
     * @param $id
     * @param $pwd
     * @return array
     */
    private function _login($id, $pwd)
    {
        $res = [
            'error'=>false,
            'errorMsg'=>''
        ];

        //请求登陆页面，获取cookie
        $this->curl->get($this->base_url.'jsxsd/');
        //获取cookie，否则无法登陆
        $this->cookies = $this->curl->responseCookies;
        //设置cookie
        $this->curl->setCookies($this->cookies);
        //拼接账号密码
        $encoded = base64_encode($id).'%%%'.base64_encode($pwd);

        //登录
        $this->curl->post($this->base_url.'jsxsd/xk/LoginToXk',[
            'encoded'=>$encoded,
        ]);
        //判断是否登陆
        //网络错误
        if($this->curl->error)
        {
            $res['error'] = true;
            $res['errorMsg'] = '服务器出了点问题，重试一下？';
            return $res;
        }

        //登陆失败
        if(strpos($this->curl->response,'用户名或密码错误') !== false)
        {
            $res['error'] = true;
            $res['errorMsg'] = '用户名或密码错误';
            return $res;
        }
        //登陆成功
        if($this->curl->httpStatusCode == 302 && $this->curl->responseHeaders['location'] == 'http://csujwc.its.csu.edu.cn/jsxsd/framework/xsMain.jsp')
            return $res;
        else{ //未知错误
            $res['error'] = true;
            $res['errorMsg'] = '请确认输入信息无误';
            return $res;
        }
    }

    //查询成绩
    /**
     * @param $id
     * @param $pwd
     * @return array
     */
    private function _searchGrade($id, $pwd)
    {
        $res = [
            'error' =>false,
            'errorMsg'=>'',
            'grades'=>[]
        ];
        //登录先
        $login = $this->_login($id,$pwd);
        //登陆失败
        if($login['error'])
        {
            $res['error'] = true;
            $res['errorMsg'] = $login['errorMsg'];
            return $res;
        }

        //获取分数页面，确保正确获取
        $this->curl->get($this->base_url.'jsxsd/kscj/yscjcx_list');
        //未知原因失败
        if(strpos($this->curl->response,'学生个人考试成绩') === false)
        {
            $res['error'] = true;
            $res['errorMsg'] = '服务器出了点问题，重试一下？';
            return $res;
        }
        //查询成功,解析页面
        $html = str_get_html($this->curl->response);
        $trs = $html->find('table#dataList>tr');
        foreach ($trs as $key => $value)
        {
            if($key==0 || $key==1 || isset( $value->children(10)->plaintext )===false )
                continue;
            $res['grades'][$key] = [
              'id'=>$value->children(0)->plaintext,
                'cxxq'=>$value->children(1)->plaintext,
                'hdxq'=>$value->children(2)->plaintext,
                'kc'=>$value->children(3)->plaintext,
                'gccj'=>$value->children(4)->plaintext,
                'qmcj'=>$value->children(5)->plaintext,
                'cj'=>$value->children(6)->plaintext,
                'xf'=>$value->children(7)->plaintext,
                'kcsx'=>$value->children(9)->plaintext,
                'kcxz'=>$value->children(10)->plaintext,

            ];
        }
        $res['grades'] = array_reverse($res['grades']);
        return $res;


    }

    //查询课表
    /**
     * @param $id 学工号
     * @param $pwd 密码
     * @param $zc 周次 1-30
     * @param $xq 学期 2015-2016-2
     * @return array
     */
    private function _searchClass($id, $pwd,$xq, $zc = '')
    {
        $res = [
            'error' =>false,
            'errorMsg'=>'',
            'classes'=>[],
            'beizhu'=>'',
        ];

        //登录
        $login = $this->_login($id,$pwd);
        //登陆失败
        if($login['error'])
        {
            $res['error'] = true;
            $res['errorMsg'] = $login['errorMsg'];
            return $res;
        }

        //获取分数页面，确保正确获取
        $this->curl->post($this->base_url.'jsxsd/xskb/xskb_list.do',[
            'zc'=>$zc,
            'xnxq01id'=>$xq,
            'sfFD'=>1
        ]);
        //未知原因失败
        if(strpos($this->curl->response,'学期理论课表') === false)
        {
            $res['error'] = true;
            $res['errorMsg'] = '服务器出了点问题，重试一下？';
            return $res;
        }

        //查询成功,解析页面
        $html = str_get_html($this->curl->response);
        $trs = $html->find('table#kbtable',0)->find('td>div.kbcontent');

        if(empty($trs)) //学期||周次选择有误
        {
            $res['error'] = true;
            $res['errorMsg'] = '本时段暂时没有课程';
            return $res;
        }
        //备注信息
        $res['beizhu'] = $html->find('table#kbtable',0)->last_child()->find('td',0)->plaintext;
        //添加课程信息
        foreach ($trs as $key=>$value)
        {
            $innertext = $value->innertext;
            if($innertext == '&nbsp;') //此时段无课程
            {
                $res['classes'][$key] = '0';
            }
            else if(strpos($innertext,'---------------------') !== false) //存在多节课程,只处理两节课
            {
                $ls = $value->find("font[title='老师']");
                $zcjc = $value->find("font[title='周次(节次)']");
                $js = $value->find("font[title='教室']");

                foreach ($ls as $i=>$l)
                {
                    $res['classes'][$key][$i] = [
                        'kcmc'=>$value->nodes[$i*10]->plaintext,
                        //老师
                        'ls'=>$l->plaintext,
                        //周次，节次
                        'zcjc'=>$zcjc[$i]->plaintext,
                        //体育课不存在上课地点
                        'js'=>isset($js[$i]) ? $js[$i]->plaintext : '0'
                    ];
                }
            }
            else{ //只有一节课程
                $ls = $value->find("font[title='老师']",0);
                $zcjc = $value->find("font[title='周次(节次)']",0);
                $js = $value->find("font[title='教室']",0);
                $res['classes'][$key] = [
                    //课程名称
                    'kcmc'=>$value->nodes[0]->plaintext,
                    //老师
                    'ls'=>$ls->plaintext,
                    //周次，节次
                    'zcjc'=>$zcjc->plaintext,
                    //体育课不存在上课地点
                    'js'=>isset($js) ? $js->plaintext : '0'
                ];
            }
        }

        //数组分组重排
        $temp_res = [];
        foreach ($res['classes'] as $key => $value)
        {
            $temp_res[(int)($key/7)][$key%7] = $value;
        }
        $res['classes'] = $temp_res;
        //返回结果
        return $res;
    }
}