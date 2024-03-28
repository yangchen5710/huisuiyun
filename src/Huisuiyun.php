<?php

namespace Ycstar\Huisuiyun;

use GuzzleHttp\Client;
use Ycstar\Huisuiyun\Exceptions\InvalidArgumentException;
use Ycstar\Huisuiyun\Exceptions\InvalidResponseException;

class Huisuiyun
{
    protected $host;

    protected $aKey; //AK字符串，即在慧穗云系统获取的秘钥AK

    //secret字符串
    //由在慧穗云系统获取的秘钥AK和SK拼接后，再经MD5的32位小写加密后生成。
    //这个拼接生成的操作需要您自己完成，例如：AK值为123，SK值为456，secret字符串值为123456经MD5 32位小写加密后的值
    protected $sKey;

    protected $type = 2; //1 ：ISV等级AKSK 2 慧穗云等级AKSK

    protected $token;

    protected $taxNo; //ISV token调用接口时为必填，填写内容为操作公司的税号

    protected $client;

    public function __construct(array $config)
    {
        if (!isset($config['host'])) {
            throw new InvalidArgumentException("Missing Config -- [host]");
        }

        if (!isset($config['ak'])) {
            throw new InvalidArgumentException("Missing Config -- [ak]");
        }

        if (!isset($config['sk'])) {
            throw new InvalidArgumentException("Missing Config -- [sk]");
        }
        $this->host = $config['host'];
        $this->aKey = $config['ak'];
        $this->sKey = $config['sk'];

        if(isset($config['type'])){
            $this->type = $config['type'];
            if($this->type == 1){
                if(!isset($config['taxno'])){
                    throw new InvalidArgumentException("Missing Config -- [taxno]");
                }
                $this->taxNo = $config['taxno'];
            }
        }
    }

    /**
     * 同步接口-根据ak和sk获取令牌
     * @param int $forceUpdate 强制更新标记 0.不更新 1.强制更新 默认不更新
     * @return array
     * @throws InvalidResponseException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getToken(int $forceUpdate = 0): array
    {
        $response = $this->getHttpClient()->post('/api/v2/agent/common/cdk/getToken', [
            'json' => [
                'akString' => $this->aKey,
                'secretString' => strtolower(md5($this->aKey.$this->sKey)),
                'type' => $this->type,
                'forceUpdate' => $forceUpdate,
            ],
        ])->getBody()->getContents();
        $result = json_decode($response, true);
        $code = $result['code'] ?? '1';
        if($code != '200'){
            throw new InvalidResponseException($result['message'] ?? 'Invalid Response', $code);
        }
        $token = $result['data'] ?? '';
        if(!$token){
            throw new InvalidResponseException('token is empty');
        }
        $expireDate = $result['message'];
        $expireIn = strtotime($expireDate) - time();
        $this->token = $token;
        return ['token' => $token, 'expire_in' => $expireIn];
    }

    /**
     * 设置token
     * @param string $token
     * @return void
     */
    public function setToken(string $token)
    {
        $this->token = $token;
    }

    /**
     * 同步接口-智能抬头
     * @param string $companyName
     * @return array
     * @throws InvalidArgumentException
     * @throws InvalidResponseException
     */
    public function queryAddressee(string $companyName)
    {
        $options = [
            'companyName' => $companyName,
        ];
        $result = $this->doRequest('get', '/api/v2/agent/cdk/addressee/query', ['json' => $options]);
        return $result;
    }

    /**
     * 异步接口-结算单同步新增并异步开票
     * @param int $invoiceType 发票类型 1 增值税专用发票 2增值税普通发票 3增值税电子普通发票 4增值税电子专用发票 7 全电发票(普通发票) 8 全电发票(专用发票) 9 全电纸质发票(普通发票) 10 全电纸质发票(专用发票)
     * @param string $taxNo 销方税号 长度在9-20位之间
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws InvalidResponseException
     */
    public function addAgentSettlement(int $invoiceType, string $taxNo, array $params = []): array
    {
        $options = [
            'invoiceType' => $invoiceType,
            'taxNo' => $taxNo,
        ];
        if(!empty($params)){
            $options = array_merge($options, $params);
        }
        $result = $this->doRequest('post', '/api/v2/agent/cdk/agent-settlement/add', ['json' => $options]);
        return $result;
    }

    /**
     * 异步接口-根据SID重新获取结果或强制重开
     * @param string $sid 唯一标识
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws InvalidResponseException
     */
    public function invoiceReGetResultBySid($sid, array $params = []): array
    {
        $options = [
            'sid' => $sid,
        ];
        if(!empty($params)){
            $options = array_merge($options, $params);
        }
        $result = $this->doRequest('post', '/api/v2/agent/cdk/invoice/reGetResultBySid/async', ['json' => $options]);
        return $result;
    }

    /**
     * 红字信息表 同步接口-红字信息表填开
     * @param string $machineNo 销方设备号(税盘编号)
     * @param int $applicationRole 红字信息表申请类型 1购买方申请 2销售方申请
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws InvalidResponseException
     */
    public function saveRedInvoiceInfo(string $machineNo, int $applicationRole, array $params = []): array
    {
        $options = [
            'machineNo' => $machineNo,
            'applicationRole' => $applicationRole
        ];
        if(!empty($params)){
            $options = array_merge($options, $params);
        }
        $result = $this->doRequest('post', '/api/v2/agent/cdk/invoice/red/saveRedInvoiceInfo', ['json' => $options]);
        return $result;
    }

    /**
     * 红字信息表 同步接口-红字信息表查询
     * @param int $applicationRole 红字信息表申请类型 1购买方申请 2销售方申请
     * @param int $current 页数
     * @param int $size 每页条数
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws InvalidResponseException
     */
    public function getRedInvoiceInfo(int $applicationRole, int $current, int $size, array $params = []): array
    {
        $options = [
            'applicationRole' => $applicationRole,
            'current' => $current,
            'size' => $size
        ];
        if(!empty($params)){
            $options = array_merge($options, $params);
        }
        $result = $this->doRequest('post', '/api/v2/agent/cdk/invoice/red/getRedInvoiceInfo', ['json' => $options]);
        return $result;
    }

    /**
     * 全电发票 同步接口-红字信息表填开
     * @param string $taxNoType 录入方身份 01-销方 02-购方
     * @param string $invoiceNo 蓝字发票号码
     * @param int $invoiceType 红字发票类型 7 全电发票(普通发票) 8 全电发票(专用发票)
     * @param string $reason 红冲原因 03-开票有误 04-销货退回 05-销售折让 06-应税服务中止
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws InvalidResponseException
     */
    public function allElectricRedApply(string $taxNoType, string $invoiceNo, int $invoiceType, string $reason, array $params = [])
    {
        $options = [
            'taxNoType' => $taxNoType,
            'invoiceNo' => $invoiceNo,
            'invoiceType' => $invoiceType,
            'reason' => $reason
        ];
        if(!empty($params)){
            $options = array_merge($options, $params);
        }
        $result = $this->doRequest('post', '/api/v2/agent/cdk/allElectric/red/apply', ['json' => $options]);
        return $result;
    }

    /**
     * 全电发票 同步接口-红字信息表查询
     * @param int $current 页数
     * @param string $taxNoType 税号标志 01-销方 02-购方
     * @param string $startDate 开始日期 格式 "Y-m-d H:i:s"
     * @param string $endDate 结束日期 格式 "Y-m-d H:i:s"
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws InvalidResponseException
     */
    public function allElectricRedList(int $current, string $taxNoType, string $startDate, string $endDate, array $params = [])
    {
        $options = [
            'current' => $current,
            'taxNoType' => $taxNoType,
            'startDate' => $startDate,
            'endDate' => $endDate
        ];
        if(!empty($params)){
            $options = array_merge($options, $params);
        }
        $result = $this->doRequest('post', '/api/v2/agent/cdk/allElectric/red/list', ['json' => $options]);
        return $result;
    }

    /**
     * 异步接口-全电发票红冲
     * @param int $invoiceType 发票类型 7 全电发票(普通发票) 8 全电发票(专用发票)
     * @param string $invoiceNo 发票号码
     * @param string $redApplicationId 红字信息表UUID
     * @param array $params
     * @return array
     * @throws InvalidArgumentException
     * @throws InvalidResponseException
     */
    public function allElectricCreateRedInvoice(int $invoiceType, string $invoiceNo, string $redApplicationId, array $params = []): array
    {
        $options = [
            'invoiceType' => $invoiceType,
            'invoiceNo' => $invoiceNo,
            'redApplicationId' => $redApplicationId
        ];
        if(!empty($params)){
            $options = array_merge($options, $params);
        }
        $result = $this->doRequest('post', '/api/v2/agent/cdk/allElectric/create/redInvoice', ['json' => $options]);
        return $result;
    }

    /**
     * 同步接口-销项电票版式文件获取
     * @param string $invoiceNo 发票号码
     * @param array $params 发票代码 税控票必传 ['invoiceCode' => '']
     * @return array
     * @throws InvalidArgumentException
     * @throws InvalidResponseException
     */
    public function getLayoutFile(string $invoiceNo, array $params = []): array
    {
        $options = [
            'invoiceNo' => $invoiceNo
        ];
        if(!empty($params)){
            $options = array_merge($options, $params);
        }
        $result = $this->doRequest('post', '/api/v2/agent/cdk/invoice/getLayoutFile', ['json' => $options]);
        return $result;
    }

    private function doRequest(string $method, $uri = '', array $options = []): array
    {
        if(!$this->token){
            throw new InvalidArgumentException('token is empty');
        }
        try {
            $options['headers'] = [
                'X-Access-Token' => $this->token,
            ];
            if($this->type == 1){
                $options['headers']['X-Tax-Token'] = $this->taxNo;
            }
            if(isset($options['serialNo'])){
                $options['headers']['X-Serial-Token'] = $options['serialNo'];
                unset($options['serialNo']);
            }
            $response = $this->getHttpClient()->request($method, $uri, $options)->getBody()->getContents();
            $result = json_decode($response, true);
            $code = $result['code'] ?? '1';
            if($code != '200'){
                throw new InvalidResponseException($result['message'] ?? 'Invalid Response', $code);
            }
            return $result;
        } catch (\Exception $e){
            throw new InvalidResponseException($e->getMessage(), $e->getCode());
        }
    }

    private function getHttpClient()
    {
        if(!$this->client){
            return new Client(['base_uri' => $this->host]);
        }
        return $this->client;
    }

}