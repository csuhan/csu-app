<?php

/**
 * Created by PhpStorm.
 * User: han
 * Date: 2017/7/26
 * Time: 22:55
 */
use \Curl\Curl;
require APP_PATH.'/library/simple_html_dom.php';
class Notice
{
    private $site_url = 'http://tz.its.csu.edu.cn';
    private $base_url = 'http://tz.its.csu.edu.cn/Home/Release_TZTG/';
    private $article_url = 'http://tz.its.csu.edu.cn/Home/Release_TZTG_zd/';
    private $download_link = 'http://tz.its.csu.edu.cn/Home/FileDownload/';
    private $search_url = 'http://tz.its.csu.edu.cn/Home/Release_TZTG/0-';
    private $curl;

    //初始化
    public function __construct()
    {
        $this->curl = new Curl();
        //设置ip，模拟校内服务器，免登录
        $this->curl->setHeader('x-forwarded-for','202.197.71.84');
    }

    public function __destruct()
    {
        $this->curl->close();
    }

    //获取notice
    public function get_notice($req,$res,$args)
    {
        return $res->withJson($this->_get_notice($args['page_id']));
    }

    //获取article
    public function get_article($req,$res,$args)
    {
        return $res->withJson($this->_get_article($args['article_id']));
    }

    //搜索
    public function search($req,$res,$args)
    {
        return $res->withJson($this->_get_notice($args['page_id'],$args['key_word']));
    }





    //获取数据列表
    private function _get_notice($page_no = 1,$key_word = '',$dept = '')
    {

        //输出的结果
        $res = [
            'error'=>false,
            'errorCode'=>'',
            'errorMsg'=>'',
            'notices'=>[]
        ];

        //搜索,首次访问
        if($key_word!='' && $page_no==1)
        {
            $this->curl->get($this->search_url.urlencode($key_word));
        }
        //列表，或者非首次访问搜索
        else{
            if($page_no==1) $page = '';
            else $page = $page_no-1;
            $this->curl->get($this->base_url.$page);

        }

        //错误则返回
        if($this->curl->error) {
            $res['error'] = true;
            $res['errorCode'] = $this->curl->errorCode;
            $res['errorMsg'] = $this->curl->errorMessage;
            return $res;
        }
        //当前页面
        $res['nowPage'] = (int)$page_no;
        preg_match('/共有数据：(\d*)条(.*)共(\d*)页/',$this->curl->response,$matches);
        if(isset($matches[3]))
        {
            //总数据条数
            $res['total'] = (int)$matches[1];
            //总页数
            $res['totalPage'] = (int)$matches[3];

            //判断数据是否为空
            if($matches[1] == 0)
            {
                $res['notices'] = '';
                $res['error'] = true;
                $res['errorCode'] = -1;
                $res['errorMsg'] = '未查询到数据';
                return $res;
            }
        }

        //匹配数据
        $html_dom = str_get_html($this->curl->response);
        $lists = $html_dom->find('table.grid .trs',0)->children();
        foreach ($lists as $index => $tr)
        {
            preg_match('/Release_TZTG_zd\/(.*)\', \'\', \'left/',$tr->children(4)->children(0)->onclick,$id);
            $res['notices'][$index]['id'] =  $id[1];
            $res['notices'][$index]['title'] = str_replace('&nbsp;','',trim($tr->children(4)->children(0)->plaintext));
            $res['notices'][$index]['dept'] =  trim($tr->children(5)->plaintext);
            $res['notices'][$index]['viewer'] = trim($tr->children(6)->plaintext);
            $res['notices'][$index]['pubtime'] = trim($tr->children(7)->plaintext);
        }
        //输出
        return $res;
    }

    //获取页面数据
    private function _get_article($id)
    {
        //输出的结果
        $res = [
            'error'=>false,
            'errorCode'=>'',
            'errorMsg'=>'',
            'article'=>[],
            'hasDownload'=>false,
            'downloads'=>[],
        ];

        $this->curl->get($this->article_url.$id);
        if($this->curl->error) {
            $res['error'] = true;
            $res['errorCode'] = $this->curl->errorCode;
            $res['errorMsg'] = $this->curl->errorMessage;
            return $res;
        }
        $html_dom = str_get_html($this->curl->response);
        $table = $html_dom->find('table',2);
        $article = $table->find('tr',2);
        //文章
        $res['article'] = $this->parse_article($article);

        $downloads = $table->find('tr',3)->find('a');

        //无附件
        if(empty($downloads))
        {
            $res['hasDownload'] = false;
        }
        else{
            $res['hasDownload'] = true;
            foreach ($downloads as $index => $val)
            {
                $href = explode('FileDownload/',$val->href);
                $link = $href[1];
                $res['downloads'][$index] = [
                    'title'=>trim($val->plaintext),
                    'link' =>$this->download_link.$link
                ];
            }
        }
        return $res;
    }


    private function parse_article($article)
    {
        $temp = trim($article);
        //解决图片
        $temp =  str_replace('/FileUpload',$this->site_url.'/FileUpload',$temp);
        return $temp;
    }
}