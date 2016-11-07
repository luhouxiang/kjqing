<?php
namespace Mobile\Controller;
use Home\Logic\UsersLogic;
use Think\Page;
use Think\Verify;

class DistributController extends MobileBaseController {
        /*
        * 初始化操作
        */
    public function _initialize() {
        parent::_initialize();
        if(session('?user'))
        {
        	$user = session('user');
        	$this->user = $user;
        	$this->user_id = $user['user_id'];
        	$this->assign('user',$user); //存储用户信息
        }        
        $nologin = array(
        	'login','pop_login','do_login','logout','verify','set_pwd','finished',
        	'verifyHandle','reg','send_sms_reg_code','find_pwd','check_validate_code',
        	'forget_pwd','check_captcha','check_username','send_validate_code',
        );
        if(!$this->user_id && !in_array(ACTION_NAME,$nologin)){
        	header("location:".U('Mobile/User/login'));
        	exit;
        }
        
        $order_count = M('order')->where("user_id = {$this->user_id}")->count(); // 我的订单数
//         $goods_collect_count = M('goods_collect')->where("user_id = {$this->user_id}")->count(); // 我的商品收藏
		$goods_collect_sql = "SELECT c.* FROM __PREFIX__goods_collect c ".
            "inner JOIN __PREFIX__goods g ON g.goods_id = c.goods_id ".
            "WHERE c.user_id = {$this->user_id} ";
		$goods_collect = M()->query($goods_collect_sql);
		$goods_collect_count = count($goods_collect);
        $comment_count = M('comment')->where("user_id = {$this->user_id}");//  我的评论数
        $coupon_count = M('coupon_list')->where("uid = {$this->user_id}")->count(); // 我的优惠券数量        
        $first_nickname = M('users')->where("user_id = {$this->user['first_leader']}")->getField('nickname');        
        $level_name = M('user_level')->where("level_id = {$this->user['level']}")->getField('level_name'); // 等级名称
        $this->assign('level_name',$level_name);        
        $this->assign('first_nickname',$first_nickname);        
        $this->assign('order_count',$order_count);
        $this->assign('goods_collect_count',$goods_collect_count);
        $this->assign('comment_count',$comment_count);
        $this->assign('coupon_count',$coupon_count); 
    }
  
    /**
     * 分销用户中心首页
     */
    public function index(){
        // 销售额 和 我的奖励
        $result = M()->query("select sum(goods_price) as goods_price, sum(money) as money from __PREFIX__rebate_log where user_id = {$this->user_id} and status = 2");        
        $result = $result[0];
        $time = time();
        $result['goods_price'] = $result['goods_price'] ? $result['goods_price'] : 0;
        $result['money'] = $result['money'] ? $result['money'] : 0;        
                
         $lower_count[1] = M('users')->where("first_leader = {$this->user_id}")->count();
         $lower_count[2] = M('users')->where("second_leader = {$this->user_id}")->count();
         $lower_count[3] = M('users')->where("third_leader = {$this->user_id}")->count();
         
        // 我的下线 订单数
        $result2 = M()->query("select status,count(1) as c , sum(goods_price) as goods_price from `__PREFIX__rebate_log` where user_id = {$this->user_id} group by status");        
        $level_order = convert_arr_key($result2, 'status');
        for($i = 0; $i <= 5; $i++)
        {
            $level_order[$i]['c'] = $level_order[$i]['c'] ? $level_order[$i]['c'] : 0;
            $level_order[$i]['goods_price'] = $level_order[$i]['goods_price'] ? $level_order[$i]['goods_price'] : 0;
        }
        
        $withdrawals_money = M('withdrawals')->where("user_id = {$this->user_id} and `status` = 1")->sum('money');
//         $queue = M()->query("select * from __PREFIX__user_queue where is_receive = 0 and user_id = {$this->user_id} order by join_time desc limit 5 ");
        
        //print_r($level_order);
        $this->assign('level_order',$level_order); // 下线订单        
        $this->assign('lower_count',$lower_count); // 下线人数        
        $this->assign('sales_volume',$result['goods_price']); // 销售额
        $this->assign('reward',$result['money']);// 奖励
        $this->assign('withdrawals_money',$withdrawals_money);// 已提现财富
//         $this->assign('queues',$queue);// 排队
                
        $this->display();
    }
    
	/**
	 * 将积分加入排队  业务逻辑修改，暂时不处理
	 * @deprecated
	 */
    /* public function addQueue(){
    	
    	$user_pay_points = $this->user['pay_points'];
    	$pay_points =  I('pay_points');
    	if($pay_points > 0 and $user_pay_points < $pay_points){
    		$result["msg"] = "您的积分不足！";
    		$result["status"] = "error";
    		$this->ajaxReturn(json_decode($result));
    	}
    	$now_points = 0 - ($pay_points == 0 ? $user_pay_points : $pay_points);
    	accountLog($this->user_id, 0 ,$now_points,"加入排队积分: ".$now_points );
    	
    	$user_queue = array(
    			"user_id" => $this->user_id,
    			"join_time" => time(),
    			"receive_amount" => $pay_points
    	);
    	M('user_queue')->add($user_queue);
    	
    	$userLogic = new UsersLogic();
    	$this->user = $userLogic->get_info($this->user_id); // 获取用户信息
//     	session('user',$userLogic->get_info($this->user_id));
    	$user_pay_points = $this->user['pay_points'];
    	
    	$result["msg"] = "添加成功！";
    	$result["status"] = "success";
    	$result["now_points"] = $user_pay_points;
    	$this->ajaxReturn($result);
    	
    } */
    
    /**
     * 我的排队
     */
    public function queue(){
//     	$queue_lst = M("user_queue")->where(" is_receive in ( 0 , 1 ) ")->order("is_receive asc,sequence asc, join_time desc ")->select();
//     	$user_queue_lst = M("user_queue")->where("is_receive in ( 0 , 1 ) and user_id = $this->user_id ")->order("is_receive asc,sequence asc, join_time desc ")->select();
    	$usSql = "select uq.*,us.nickname from __PREFIX__user_queue uq inner join __PREFIX__users us on uq.user_id = us.user_id 
    			where is_receive in ( 0 , 1 ) order by is_receive desc,us.weights asc, join_time desc";
    	$queue_lst = M()->query($usSql);
    	$usSql = "select uq.*,us.nickname from __PREFIX__user_queue uq inner join __PREFIX__users us on uq.user_id = us.user_id 
    			where is_receive in ( 0 , 1 ) and uq.user_id = $this->user_id order by is_receive desc,us.weights asc, join_time desc";
    	$user_queue_lst = M()->query($usSql);
    	foreach ($user_queue_lst as $k1 => $v1){
    		foreach ($queue_lst as $k2 => $v2){
    			if($v1["sequence"] == $v2["sequence"]){
    				$user_queue_lst[$k1]["index"]= $k2 + 1;
    				$queue_lst[$k2]["color"] = "red";
    				break;
    			}
    		}
    	}
    	
//     	$page = new Page(count($queue_lst));
//     	$this->assign('page',$page->show());
    	
    	$queued_count =  M("user_queue")->where("is_receive = 2 ")->count();
    	$this->assign('queue_lst',$queue_lst);
    	$this->assign('user_queue_lst',$user_queue_lst);
    	$this->assign('queued_count',$queued_count);
    	$this->display();
    }
	
    /**
     * 我的兑换记录
     */
    public function integral_record(){
    	$usSql = "select uql.*,us.nickname from __PREFIX__user_queue_log uql 
    			inner join __PREFIX__user_queue uq on uql.queue_id = uq.queue_id 
    			inner join __PREFIX__users us on uq.user_id = us.user_id 
    			where us.user_id = $this->user_id
    			order by receive_time desc";
    	$queue_lst = M()->query($usSql);
    	$this->assign('queue_lst',$queue_lst);
    	$this->display();
    }
    
    
    /**
     * 将积分兑换为股票
     */
    public function addStock(){

    	$result = $this->verifyMobile();
    	if($result["status"] == "error"){
    		$this->ajaxReturn(json_encode($result));
    	}
    	
    	$user_pay_points = $this->user['pay_points'];
    	$pay_points =  I('pay_points');
    	$stock =  $pay_points; // 暂定兑换股票的值为积分值
    	if($pay_points > 0 and $user_pay_points < $pay_points){
    		$result = array("status"=>"error","msg"=>"您的积分不足！");
    		$this->ajaxReturn(json_encode($result));
    	}
    	
    	$stock_arr = session("stock_arr");
    	$time = $stock_arr["time"] ;
    	$now_time = time();
    	if(($now_time - $time) > 300){
    		$result = array("status"=>"error","msg"=>"您的股票价格已失效，请重新刷新！");
    		$this->ajaxReturn(json_encode($result));
    	}
    	
    	$now_points = 0 - ($pay_points == 0 ? $user_pay_points : $pay_points);
    	accountLog($this->user_id, 0 ,$now_points,"加入股票积分: ".$now_points );
    	$stock = $stock_arr["stock"];
    	$add_stock = ($pay_points/100)*$stock;
    	
    	$tpStock = M("stock")->find();
    	$tpStock["exchange_stock_count"] = $tpStock["exchange_stock_count"] + $add_stock;//兑换股票
    	M("stock")->save($tpStock);
    	
    	$user_stock = array(
    			"user_id" => $this->user_id,
    			"add_time" => time(),
    			"pay_points" => $pay_points,
   				"status" => "2",
    			"stock_amount" => $add_stock
    	);
    	M('user_stock_log')->add($user_stock);
    	
    	$sql = "UPDATE __PREFIX__users SET stock = stock + $add_stock WHERE user_id = $this->user_id";
    	D('users')->execute($sql);
    	
    	$user_pay_points = $this->user['pay_points'];
    	$result = array("status"=>"success","msg"=>"添加成功！","now_points"=>$user_pay_points);
    	$this->ajaxReturn(json_encode($result));
    }
    
    /**
     * 股票兑换记录
     */
    public function queryStock(){
    	
    	$system_amount = M("system_amount")->find();
    	
    	$count = M('user_stock_log')->where("user_id=".$this->user_id)->count();
    	$Page = new Page($count,10);
    	$usl_lst = M('user_stock_log')->where("user_id=".$this->user_id)->order('add_time desc')->limit($Page->firstRow.','.$Page->listRows)->select();
    	if($_GET['is_ajax'])
    	{
    		$result['usl_lst'] = $usl_lst;
    		$this->ajaxReturn($result);
    		exit;
    	}
    	
    	$this->assign('usl_lst',$usl_lst);
    	$this->assign('system_amount',$system_amount);
    	$this->display();
    }
    
    public function addStockView(){
    	$url = "http://www.83100.com/Api?action=ticker&market=ltg_ltc";
    	$price = 0.0;
    	 
    	$stock_str = file_get_contents($url);
    	$stock_str = mb_convert_encoding($stock_str,'UTF-8','ASCII,UTF-8,ISO-8859-1');
    	if(substr($stock_str,0,3) == pack("CCC",0xEF,0xBB,0xBF))
    		$stock_str = substr($stock_str,3);
    		$stock_json = json_decode($stock_str,true);
    		if($stock_json["code"]==0){
    			$price = $stock_json["msg"]["price"];
    	}
    	$letCoin_fee = tpCache('basic.letCoin_fee');
    	
    	if($price == 0){
    		$stock = 200;
    	}else {
	    	$stock = 100/($price*$letCoin_fee);
	    	$stock = round($stock,4);
    	}
    	$stock_arr = array(
    			"stock"=>$stock,
    			"time"=>time()
    	);
    	session("stock_arr",$stock_arr);
    		 
    	$this->assign('stock',$stock);
    	$this->display();
    }
    /**
     * 下线列表
     */
    public function lower_list(){
        $level = I('get.level',1);         
        $q = I('post.q','','trim');
        $condition = array(1=>'first_leader',2=>'second_leader',3=>'third_leader');
        
        $where = "{$condition[$level]} = {$this->user_id}";
        $q && $where .= " and (nickname like '%$q%' or user_id = '$q' or mobile = '$q')";
        
        $count = M('users')->where($where)->count();               
        $page = new Page($count,10);
        $list = M('users')->where($where)->limit("{$page->firstRow},{$page->listRows}")->order('user_id desc')->select();
        
        $this->assign('count', $count);// 总人数
        $this->assign('page', $page->show());// 赋值分页输出
        $this->assign('list',$list); // 下线
        if($_GET['is_ajax'])
        {
            $this->display('ajax_lower_list');
            exit;
        }                
        $this->display();
    }    
    
    /**
     * 下线订单列表
     */
    public function order_list(){
        $status = I('get.status',0);        
        $where = " user_id = {$this->user_id} and status in($status)";        
        $count = M('rebate_log')->where($where)->count();               
        $page = new Page($count,10);
        $list = M('rebate_log')->where($where)->order('id desc')->limit("{$page->firstRow},{$page->listRows}")->select();
        $user_id_list = get_arr_column($list, 'buy_user_id');
        if(!empty($user_id_list))
            $userList = M('users')->where("user_id in (".  implode(',', $user_id_list).")")->getField('user_id,nickname,mobile,head_pic');                        
        
        $this->assign('count', $count);// 总人数
        $this->assign('page', $page->show());// 赋值分页输出        
        $this->assign('userList',$userList); // 
        $this->assign('list',$list); // 下线
        if($_GET['is_ajax'])
        {
            $this->display('ajax_order_list');
            exit;
        }                
        $this->display();
    } 
    
    /**
     * 申请提现记录
     */
    public function withdrawals(){
        
    	C('TOKEN_ON',true);
    	if(IS_POST)
    	{
                $this->verifyHandle('withdrawals');                
    		$data = I('post.');
    		$data['user_id'] = $this->user_id;    		    		
    		$data['create_time'] = time();                
                $distribut_min = tpCache('distribut.min'); // 最少提现额度
                if($data['money'] < $distribut_min)
                {
                        $this->error('每次最少提现额度'.$distribut_min);
                        exit;
                }
                if($data['money'] > $this->user['user_money'])
                {
                        $this->error("你最多可提现{$this->user['user_money']}账户余额.");
                        exit;
                }     
                 
    		if(M('withdrawals')->add($data)){
    			$this->success("已提交申请");
                        exit;
    		}else{
    			$this->error('提交失败,联系客服!');
                        exit;
    		}
    	}
        
        $where = " user_id = {$this->user_id}";        
        $count = M('withdrawals')->where($where)->count();
        $page = new Page($count,16);
        $list = M('withdrawals')->where($where)->limit("{$page->firstRow},{$page->listRows}")->select();
          
        $this->assign('page', $page->show());// 赋值分页输出
        $this->assign('list',$list); // 下线
        if($_GET['is_ajax'])
        {
            $this->display('ajaxx_withdrawals_list');
            exit;
        }                
        $this->display();        
    }            
    
   	public function apply_virtual_wallet(){
   		C('TOKEN_ON',true);
   		if(IS_POST)
   		{
   			$result = $this->verifyMobile();
   			if($result["status"] == "error"){
   				$this->error("验证码错误：{$result['msg']}");
   				exit;
   			}
   			
   			$data = I('post.');
   			$data['user_id'] = $this->user_id;
   			$data['create_time'] = time();
   			if($data['money'] > $this->user['stock'])
   			{
   				$this->error("你最多可提现{$this->user['stock']}账户余额.");
   				exit;
   			}
   			
   			$virtual_wallet_moneys = M('user_virtual_wallet')->where("status = 1")->sum("money");
   			if($virtual_wallet_moneys > 99999999){
   			}
   			
   			$url = "http://218.93.206.16:18080/Api.aspx?action=api_pay&minconf=1&account=my_name&accountpass=my_pass&my_address=";
   			$my_address = "LRh7Uqc43B8j3kXjQAW9VhKLLMyR5MzDK7";
   			$to = "&to=".$data["wallet_addr"];
   			$amount = "&amount=".$data["money"];
   			$url = $url.$my_address.$to.$amount;
   			
   			$stock_str = file_get_contents($url);
   			$stock_str = mb_convert_encoding($stock_str,'UTF-8','ASCII,UTF-8,ISO-8859-1');
   			if(substr($stock_str,0,3) == pack("CCC",0xEF,0xBB,0xBF))
   				$stock_str = substr($stock_str,3);
   			
   			$stock_json = json_decode($stock_str,true);
   			if($stock_json["code"]==0){
   				$data["status"] = "1" ;
   			}else{
   				$data["status"] = "2" ;
   			}
   			$data["my_address"] = $my_address ;
   			if(M('user_virtual_wallet')->add($data)){
   				$tpStock = M("stock")->find();
   				$tpStock["pay_stock_count"] = $tpStock["pay_stock_count"] + $data['money']; //提取股票
   				M("stock")->save($tpStock);
   				
   				$user_stock = array(
   						"user_id" => $this->user_id,
   						"add_time" => time(),
   						"status" => "2",
   						"stock_amount" => $data['money']
   				);
   				M('user_stock_log')->add($user_stock);
   				
   				$sql = "UPDATE __PREFIX__users SET stock = stock - ".$data['money']." WHERE user_id = $this->user_id";
   				D('users')->execute($sql);
   				
   				
   				$this->success("提取成功！");
   				exit;
   			}else{
   				$this->error('提交失败,联系客服!');
   				exit;
   			}
   		}
   		$where = " user_id = {$this->user_id}";
   		$count = M('user_virtual_wallet')->where($where)->count();
   		$page = new Page($count,16);
   		$list = M('user_virtual_wallet')->where($where)->limit("{$page->firstRow},{$page->listRows}")->select();
   		
   		$this->assign('page', $page->show());// 赋值分页输出
   		$this->assign('list',$list); // 下线
   		if($_GET['is_ajax'])
   		{
   			$this->display('ajaxx_withdrawals_list');
   			exit;
   		}
   		$this->display();
   	}
    
    /**
     * 验证码验证
     * $id 验证码标示
     */
    private function verifyHandle($id)
    {
        $verify = new Verify();
        if (!$verify->check(I('post.verify_code'), $id ? $id : 'user_login')) {
            $this->error("验证码错误");
        }
    }
    
    /**
     * 验证手机号码
     */
    private function verifyMobile(){
    	$code = I('mobile_code','');
    	if(!$code){
    		$result = array("status"=>"error","msg"=>'请输入短信验证码');
    		return $result;
    	}
    		
    	if(empty($this->user['mobile'])){
    		$result = array("status"=>"error","msg"=>'请先录入您的手机号码');
    		return $result;
    	}
    	
    	$logic = new UsersLogic();
    	$check_code = $logic->sms_code_verify($this->user['mobile'],$code,$this->session_id);
    	if($check_code['status'] != 1){
    		$result = array("status"=>"error","msg"=>$check_code['msg']);
    		return $result; 
    	}
    }

    /**
     * 验证码获取
     */
    public function verify()
    {
        //验证码类型
        $type = I('get.type') ? I('get.type') : 'user_login';
        $config = array(
            'fontSize' => 40,
            'length' => 4,
            'useCurve' => true,
            'useNoise' => false,
        );
        $Verify = new Verify($config);
        $Verify->entry($type);
    }    
    
    /*
     *个人推广二维码 
     */
    public function qr_code(){
        $ShareLink = urlencode("http://{$_SERVER[HTTP_HOST]}/index.php?m=Mobile&c=Index&a=index&first_leader={$this->user_id}"); //默认分享链接                  
        if($this->user['is_distribut'] == 1)
            $this->assign('ShareLink',$ShareLink);
        $this->display();
    }  
    
    /* public function pay_queue(){
    	
    	$user_queues = M("user_queue")->where("is_receive != 2 and receive_amount < 1 ")->select();
    	foreach ($user_queues as $user_queue ){
    		
    		$users = M("users")->where(" user_id =  ".$user_queue["user_id"])->find();
    		$param = array(
					"receive_amount" => 0,
					"received_amount" => $user_queue["received_amount"] + $user_queue["receive_amount"],
					"is_receive" => 2
			);
    		M("user_queue")->where("queue_id=".$user_queue["queue_id"])->save($param);
    		
    		$param_log = array(
    				"queue_id" => $user_queue["queue_id"],
    				"receive_time" => time(),
    				"receive_amount" => $user_queue["receive_amount"] ,
    		);
    		M("user_queue_log")->add($param_log);
    		if($users["pay_points"] >= $user_queue["receive_amount"]){
    			accountLog($users["user_id"], $user_queue["receive_amount"] ,0-$user_queue["receive_amount"],"用户积分返现: ".$user_queue["receive_amount"] );
    		}
    	}
    	var_dump($user_queues);
    	
    } */
    
}