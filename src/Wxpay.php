<?php

namespace Wxpay;
class Wxpay
{
    const APPID = "";
    const MCHKEY = "";
    /**
     * 通过post方式获取资源这是1.0.1版本
     * @param $url
     * @param $data
     * @return bool|string
     */
    public function postCurl($url,$data)
    {
        //初始化
        $curl = curl_init();
        //设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HEADER, 0);
        //设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        //设置post方式提交
        curl_setopt($curl, CURLOPT_POST, 1);
        //设置post数据
        $post_data = $data;
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
        //执行命令
        $data = curl_exec($curl);
        //关闭URL请求
        curl_close($curl);
        //显示获得的数据
        return $data;
    }

    /**
     * xml转array
     * @param $xml
     * @return mixed
     */
    private function xml($xml)
    {

        libxml_disable_entity_loader(true);
        $values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $values;

    }

    /**
     * 生成随机字符串
     * @return string
     */
    private function nonce_str(){
        $result = '';
        $str = 'QWERTYUIOPASDFGHJKLZXVBNMqwertyuioplkjhgfdsamnbvcxz';
        for ($i=0;$i<32;$i++){
            $result .= $str[rand(0,48)];
        }
        return $result;

    }

    /**
     * 生成订单号
     * @param $openid
     * @return string
     */
    private function order_num($openid)
    {
        return md5($openid.time().rand(100,999));
    }

    /**
     * 获取签名
     * @param $data
     * @return string
     */
    private function sign($data)
    {
        $stringA = '';
        foreach ($data as $kk=>$item){
            if(!$item)continue;
            if($stringA){
                $stringA .= '&'.$kk.'='.$item;
            }else{
                $stringA = $kk.'='.$item;
            }
        }
        $wx_key = self::MCHKEY;
        $stringSignTemp  = $stringA.'&key='.$wx_key;
        return strtoupper(md5($stringSignTemp));
    }

    /**
     * 实现微信支付功能
     * @param int $total_fee
     * @param $body
     * @param $openid
     * @param $ip
     * @return array
     */
    public function wxpay($total_fee=0,$body,$openid,$ip)
    {
        $post = [];
        $post['appid'] = self::APPID;
        $post['body'] = $body;
        $post['mch_id'] = self::MCHKEY;
        $post['nonce_str'] = $this->nonce_str();
        $post['notify_url'] = config('weixin.notify_url');
        $post['openid'] = $openid;
        $post['out_trade_no'] = $this->order_num($openid);
        $post['spbill_create_ip'] = $ip;
        $post['total_fee'] = $total_fee;
        $post['trade_type'] = "JSAPI";
        $sign = $this->sign($post);
        $post_xml = '<xml>
            <appid>'.$post['appid'].'</appid>
            <body>'.$body.'</body>
            <mch_id>'.$post['mch_id'].'</mch_id>
            <nonce_str>'.$post['nonce_str'].'</nonce_str>
            <notify_url>'.$post['notify_url'].'</notify_url>
            <openid>'.$openid.'</openid>
            <out_trade_no>'.$post['out_trade_no'].'</out_trade_no>
            <spbill_create_ip>'.$post['spbill_create_ip'].'</spbill_create_ip>
            <total_fee>'.$total_fee.'</total_fee>
            <trade_type>'.$post['trade_type'].'</trade_type>
            <sign>'.$sign.'</sign>
            </xml> ';
        $url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $xml = $this->postCurl($url,$post_xml);
        $array_data = $this->xml($xml);
        $return_array = [];
        if($array_data['return_code'] == 'SUCCESS' && $array_data['return_msg'] == "OK"){
            $time = strval(time());
            $tmp = [];
            $tmp['appId']  = config('weixin.appid');
            $tmp['nonceStr']  = $post['nonce_str'];
            $tmp['package'] = 'prepay_id='.$array_data['prepay_id'];
            $tmp['signType'] = 'MD5';
            $tmp['timeStamp'] = $time;
            //返回的数据
            $return_array['state'] = 200;
            $return_array['timeStamp'] = $time;
            $return_array['nonceStr'] = $post['nonce_str'];
            $return_array['signType'] = "MD5";
            $return_array['package'] = 'prepay_id='.$array_data['prepay_id'];
            $return_array['paySign'] = $this->sign($tmp);
            $return_array['out_trade_no'] = $post['out_trade_no'];
        }else{
            $return_array['state'] = 0;
            $return_array['text'] = 'error';
            $return_array['return_code'] = $array_data['return_code'];
            $return_array['return_msg'] = $array_data['return_msg'];
        }
        return $return_array;
    }
}