<?php
$thispage = "unblinding" ; 
include($_SERVER['DOCUMENT_ROOT']."/include/config.php");
require_once("control/include/lib/nusoap.php");
date_default_timezone_set("Asia/Shanghai");//设定时间地区
if($action=="get_mobile_plist"){
	get_mobile_plist();
}else if ($action=="send_by_sms"){
	send_by_sms();
}else if ($action=="get_pwd_type"){
	get_pwd_type();
}else if ($action=="get_case_bym"){
	get_case_bym();
}

00000002   modify 
dev 00000002  modify 
dev-1 00000003

//根据传入的 试验编号和密码，判断所属的类型
function get_pwd_type(){
	global $case_code,$case_pwd;
	/*根据该密码判断是何种类型的揭盲，pwd_type 0:普通 1:申请备用疫苗 */
	//0: 不同判断剂数  1：要判断该试验编号盲底的数量，大于1的话，需要输入剂数
	$sql = "select a.pwd_flag,a.bln_jk,a.project_id,b.name as pname from edc_case_project_unbind_pwd as a inner join edc_project as b on a.project_id=b.id  where a.md_pwd='".$case_pwd."'";
	$result = mysql_query($sql);
	$nums = mysql_num_rows($result);
	
	$arr_out['pwd_count'] = $nums; //是否存在该密码
	$arr_out['pwd_jk'] = 0 ;//默认没有揭开
	$arr_out['case_code_count'] = 0 ;//试验编号的盲底数量
	
	
	while ($rs= mysql_fetch_array($result)){
			$arr_out['pwd_type']= $rs["pwd_flag"];
			$arr_out['pwd_jk']= $rs["bln_jk"];
			$arr_out['pwd_project_id'] = $rs["project_id"];
			$arr_out['pwd_project_name'] = $rs["pname"];
	}
	//如果pwd_type=1 ,申请疫苗类并且没有揭开的，判断是否需要输入剂数
	if ($arr_out['pwd_type'] == "1"){
		$sql = "select * from edc_case_unbinding_by_md where  project_id='".$arr_out["pwd_project_id"]."' and  case_code='".$case_code."'";
		$result = mysql_query($sql);
		$nums = mysql_num_rows($result);
		$arr_out['case_sql'] = $sql;
		$arr_out['case_code_count'] = $nums; //该试验编号的盲底数量（没有被使用的)
	}

	
	echo json_encode($arr_out);	
}

function send_by_sms(){ //发送备用秒短信
	global $mobile,$pid;
	$int_expire = 10;
	//发送过短信的，发送时间和当前时间超过 30分钟，就把send_sms_time清空   UNIX_TIMESTAMP(now())  FROM_UNIXTIME(1156219870); unix_timestamp('2011-11-11 12:13:12')
	$sql = "select md_pwd from edc_case_project_unbind_pwd where project_id='".$pid."' and  send_sms_time>0 and (UNIX_TIMESTAMP(now())-send_sms_time)/60 >".$int_expire;
	$result = mysql_query($sql);
	$nums = mysql_num_rows($result);
	if ($nums > 0){
		//有超时的，就更新掉
		$sql = "update  edc_case_project_unbind_pwd set send_sms_time=0 where project_id='".$pid."' and  send_sms_time>0 and (UNIX_TIMESTAMP(now())-send_sms_time)/60 >".$int_expire;
		mysql_query($sql);
	}
	
	$sql = "select a.md_pwd,b.name as pname from edc_case_project_unbind_pwd as a inner join edc_project as b on a.project_id=b.id  where a.project_id='".$pid."' and a.pwd_flag='1' and a.bln_jk='0' and IFNULL(send_sms_time,0) =0    limit 1 ";
	$result = mysql_query($sql);
	$nums = mysql_num_rows($result);

	if ($nums == 0 ) {
		$content= "该项目的备用编号随机密码已经使用完毕，请通知管理员！";
		sendsms($mobile,$content);
	}else{
		$rs = mysql_fetch_array($result);
		$content= "试验项目:".$rs["pname"]."\r\n*备用编号随机密码*:\r\n".$rs["md_pwd"]." \r\n十分钟有效，网址：http://www.epiedc.com/by.php?ubcode=".$rs["md_pwd"]."*\r\n";
		
		//更新该密码的 send_sms_time
		$sql = "update edc_case_project_unbind_pwd set send_sms_mobile='".$mobile."', send_sms_time = UNIX_TIMESTAMP(now()) where project_id='".$pid."' and  md_pwd = '".$rs["md_pwd"]."'";
		mysql_query($sql);
		//插入历史记录
		$sql = " insert into edc_case_mdpwd_sms(md_pwd,pwd_flag,sms_mobile,sms_time) values ( '".$rs["md_pwd"]."','1','".$mobile."','".date("Y-m-d H:i:s")."')";
		mysql_query($sql);
		
		sendsms($mobile,$content);
	}
	
	echo "1";
}
function get_mobile_plist(){
	global $mobile,$dn;
	$sql = " select b.project_id,c.`name` as pname ,a.mobile from  edc_user as a left join edc_project_user as b on a.id=b.user_id left join edc_project as c on b.project_id=c.id where c.status='1' and   a.mobile='".$mobile."'";
	$result = mysql_query($sql);
	//$arr_out["sql"] = $sql;
	$nums = mysql_num_rows($result);
	$count = 1;
	while ($rs = mysql_fetch_array($result)){
		$arrp[$rs["project_id"]] =$rs["pname"];
		$count++;
	}
	$arr_out["nums"] = $nums;
	$arr_out["pn"] = $arrp;
	
	echo json_encode($arr_out);
}

function sendsms($mobile,$content){
	
	
	$str_m_list =$mobile;
	$presendTime =date("Y-m-d H:i:s");
	$isVoice = "0|0|0|0";
	$username = "68304:e01";
	$password = "e01186";
	$from = "";
	$to = $str_m_list;
	$parameters	= array($username,$password,$from,$to,$content,$presendTime,$isVoice);

	$client = new soapclient('http://ws.iems.net.cn/GeneralSMS/ws/SmsInterface?wsdl',true);
	$client->soap_defencoding = 'utf-8';   
	$client->decode_utf8 = false;   
	$client->xml_encoding = 'utf-8'; 	
	$str=$client->call('clusterSend',$parameters);
	
	if ($err=$client->getError()) {
	//echo " 错误 :",$err;
	}else{
		//echo "OK";	
	}
}
/*-------------------------------------------------------------*/
// 揭盲处理
// +----------------------------------------------------------------------+
// | 该过程只是针对备用苗的                                               |
// +----------------------------------------------------------------------+

function get_case_bym(){
	global  $LANG,$lan,$case_code,$case_pwd,$case_mobile,$case_user,$case_company,$case_jishu,$checkcode;
	//先判断验证码
	if(strtolower($checkcode) != $_SESSION["validate"]){
		$arr_out["ERROR"] =$LANG["362_".$lan];//  "验证码填写不正确." ; //
		echo json_encode($arr_out);	
		exit ; 
	}
	if ($case_jishu == "" ) {
		$case_jishu ="1";	
	}
	//检查该密码是否存在,如果存在，看是哪个项目，同时判断密码类型 pwd_flag 0:一般揭盲 1：备用苗揭盲
	//send_sms_time 为空或者（不为空，并且send_sms_mobile=当前号码)
	$sql = "select a.*,b.name as 'projectname' ,c.case_code from edc_case_project_unbind_pwd  as  a " ;
	$sql .= " inner join edc_project as b on a.project_id=b.id left join edc_case_unbinding_md as c on c.unbinding_pwd_id=a.id  " ;
	$sql .= "  where a.md_pwd='".$case_pwd."' and a.pwd_flag='1'  " ; //and (IFNULL(send_sms_time,0) = 0 or (send_sms_time>0 and send_sms_mobile='".$case_mobile."'))";
	$result = mysql_query($sql);
	if ($rs = mysql_fetch_array($result)){
		$s_pwd_id =  $rs["id"]; //该密码的ID
		$s_project_id =  $rs["project_id"]; //项目ID
		$s_project_name = $rs["projectname"]; //项目名称
		$s_jk = $rs["bln_jk"]; //是否已经被使用
		$s_jk_date= $rs["jk_date"]; //使用日期
		$s_jk_user=$rs["jk_user"]; //使用人
		$s_dn= $rs["case_code"]; //使用该密码的试验编号
		$s_mobile= $rs["jk_mobile"]; //使用该密码的手机号
		$s_pwd_flag = $rs["pwd_flag"]; //密码类型 0:紧急揭盲 1:备用编号
		$s_send_sms_mobile =  $rs["send_sms_mobile"];
		$s_send_sms_time =  $rs["send_sms_time"];
	}else{ //没有找到该密码的信息
		$arr_out["ERROR"] =  $LANG["354_".$lan]; //"该随机密码不存在." ; //密码没有找到
		echo json_encode($arr_out);	
		exit;
	}
	//判断如果使用了别人占用的密码
	if (($rs["send_sms_time"] >0) && ($rs["send_sms_mobile"]!=$case_mobile)){
		$arr_out["ERROR"] = $LANG["329_".$lan]; //  "该随机密码被其他用户占用." ; //密码没有找到
		echo json_encode($arr_out);	
		exit;
	}
	
	
	//判断该密码是否已经被使用
	if ($s_jk == "1"){
		$arr_out["ERROR"] = $LANG["380_".$lan]; // "该随机密码已经被使用." ; //密码已经被使用
		echo json_encode($arr_out);	
		exit ; 
	}
	//判断是否有填写试验单位，因备用编号分配需要知道试验单位编码
	if ($case_company == ""){
		$arr_out["ERROR"] = $LANG["310_".$lan]; // "请填写试验单位." ; //疫苗类的申请试验单位没有填写
		echo json_encode($arr_out);	
		exit ; 
	}	
	
	$sql = "select id,case_code,bln_jk,jishu,unbinding,unbinding_k  from edc_case_unbinding_by_md " ;
	$sql .= " where project_id='".$s_project_id."' and case_code='".$case_code."'";
	$result = mysql_query($sql);
	$nums = mysql_num_rows($result); //试验编号的数量
	$rs = mysql_fetch_array($result); 
	//判断研究编号是否存在
	if ($nums == 0){
		$arr_out["ERROR"] =$LANG["373_".$lan]; // "该研究编号不存在." ; //该试验编号没有找到
		$arr_out["sql"] = $sql;
		echo json_encode($arr_out);	
		exit ; 
	}

	$arr_out["jishu"]= $nums; //记录该试验编号的总剂数
	if ($nums == 1 ){ //该研究编号的盲底只有一条的时候，不需要输入剂数
		if ($rs["bln_jk"] == "1"){ //直接判断该研究编号是否已经被使用过
			$arr_out["ERROR"] =  $LANG["376_".$lan]; // "该研究编号已经被使用." ; //试验编号已经被揭开了
			echo json_encode($arr_out);	
			exit ;
		}
	}else{ //如果该研究编号对应多条盲底，则需要填写剂数
		//判断是否有填写剂数
		if ($case_jishu == "" ){
			$arr_out["ERROR"] =  $LANG["372_".$lan]; // "该研究编号存在多剂数，请填写剂数." ; //不止一条并且没有填写剂数
			echo json_encode($arr_out);	
			exit ;
		}
		//判断该研究编号的该剂数数据
		$sql = "select id,case_code,bln_jk,jishu,unbinding,unbinding_k  from edc_case_unbinding_by_md " ;
		$sql .= " where project_id='".$s_project_id."' and jishu='".$case_jishu."' and  case_code='".$case_code."'";
		$result = mysql_query($sql);
		$nums = mysql_num_rows($result); 
		if ($nums == 0 ){
			$arr_out["ERROR"] =  $LANG["377_".$lan]; // "该研究编号不存在该剂数." ; //当前试验编号的剂数没有数据
			
			echo json_encode($arr_out);	
			exit ;	
		}else{ //该研究编号该剂数有数据
			$rs = mysql_fetch_array($result); 
			if ($rs["bln_jk"] == "1"){
				$arr_out["ERROR"] =  $LANG["367_".$lan]; // "该研究编码当前剂数已经被使用." ; //该试验编号的该剂数已经被使用了
				echo json_encode($arr_out);	
				exit ;	
			}
		}
		
	}
	
	//获取该试验编号该剂数的盲底
	$s_by_md_id = $rs["id"];//该研究编号该剂数的ID号
	$s_by_md_jiami = $rs["unbinding"] ;//该研究编号该剂数的盲底的加密字符串
	$s_by_md_jiami_key =$rs["unbinding_k"] ;//该研究编号该剂数的盲底的加密KEY
	$s_by_md = getdecrymd($s_by_md_jiami,$s_by_md_jiami_key);	//该研究编号该剂数的盲底
	$arr_out["by_md"] = $s_by_md ; //保存该盲底的信息到字符串
	
	//寻找备用编号
	$sql = "select case_code,unbinding,unbinding_k,id from edc_case_unbinding_spare_md " ;
	$sql .= " where vendorcode='".$case_company."' and  project_id='".$s_project_id."' and bln_jk ='0' order by case_code ";
	$result = mysql_query($sql);
	$nums = mysql_num_rows($result);
	if ($nums == 0 ){ //寻找该试验单位该项目的未使用的备用编号数据
		$arr_out["ERROR"] =  $LANG["328_".$lan]; // "该试验单位未分配备用编号." ; //在该单位中没有找到备用苗
		echo json_encode($arr_out);	
		exit ;	
	}else{
		$str_bym_md_id = "" ;//备用苗的ID号
		$str_bym_md_code = "";//备用苗的药物编号
		while ($rs = mysql_fetch_array($result)){
			$str_case_md = $rs["unbinding"] ;
			$str_case_md_k =$rs["unbinding_k"] ;
			$str_bym_md = getdecrymd($str_case_md,$str_case_md_k);					
			if ($s_by_md == $str_bym_md){
				$str_bym_md_id = $rs["id"];
				$str_bym_md_code = $rs["case_code"];
				break;
			}
		}
		//判断是否已经找到备用编号
		if ($str_bym_md_id == ""){
			$arr_out["ERROR"] = $LANG["366_".$lan]; //  "该试验单位中未找到该研究编号该剂数的备用编号." ; //该单位中该试验号盲底的备用苗已经使用完成了
			echo json_encode($arr_out);	
			exit ;	
		}else{//已经找到备用苗了,各种更新
			
			//更新揭盲密码
			$sql1 = "update edc_case_project_unbind_pwd set bln_jk='1' ,jk_date='".date('Y-m-d H:i:s')."',jk_user='".$case_user."',jk_mobile='".$case_mobile."',jk_ip='".$_SERVER["REMOTE_ADDR"]."',jk_company='".$case_company."' where id='".$s_pwd_id."'";
			//更新疫苗盲底数据表
			$sql2 = "update edc_case_unbinding_by_md set bln_jk='1' ,unbinding_pwd_id='".$s_pwd_id."' ,beiyong_code='".$str_bym_md_code."' where id='".$s_by_md_id."'";
			//更新备用苗数据表
			$sql3 = "update edc_case_unbinding_spare_md set bln_jk='1' ,unbinding_pwd_id='".$s_pwd_id."' ,unbinding_pwd='".$case_pwd."',drug_code='".$case_code."',drug_jishu='".$case_jishu."',vendorcode='".$case_company."' where   id='".$str_bym_md_id."'";
			mysql_query($sql1);
			mysql_query($sql2);
			mysql_query($sql3);
			$arr_out["sql1"] = $sql1;
			$arr_out["sql2"] = $sql2;
			$arr_out["sql3"] = $sql3;
		}
		//输出结果 global  $case_code,$case_pwd,$case_mobile,$case_user,$case_company,$case_jishu,$checkcode;
		$arr_out["ERROR"] = "" ; //清空错误
		$arr_out["c_p_name"] = $s_project_name; //项目名称
		$arr_out["c_dn"]=$case_code ; //试验编号
		$arr_out["c_mobile"]=$case_mobile ; //手机号
		$arr_out["c_user"]=$case_user ; //揭盲用户
		$arr_out["c_company"]=$case_company; //揭盲试验单位
		$arr_out["c_pwd_flag"] = $s_pwd_flag ; //密码类型 0:一般 1:申请备用苗
		$arr_out["c_jici"]= $case_jishu; //剂数
		$arr_out["c_bym_code"]= $str_bym_md_code; //备用编号
		echo json_encode($arr_out);	 				
				
	}
	
	
	
	
	
}


function get_case_bym_back_20150326_2134(){
	global  $LANG,$lan,$case_code,$case_pwd,$case_mobile,$case_user,$case_company,$case_jishu,$checkcode;
	//先判断验证码
	if(strtolower($checkcode) != $_SESSION["validate"]){
		$arr_out["ERROR"] =$LANG["362_".$lan];//  "验证码填写不正确." ; //
		echo json_encode($arr_out);	
		exit ; 
	}
	//验证该手机号是否在系统中
	//2014.08.12 申请备用无需登记手机，发送短信的时候，才验证
	/*
	$sql = "select name from edc_user where mobile='".$case_mobile."'";
	$result = mysql_query($sql);
	$nums = mysql_num_rows($result);
	if ($nums == 0 ){
		$arr_out["ERROR"] =  "该手机号系统中未做登记." ; //
		echo json_encode($arr_out);	
		exit ;
	}
	*/
	
	//检查该密码是否存在,如果存在，看是哪个项目，同时判断密码类型 pwd_flag 0:一般揭盲 1：备用苗揭盲
	//send_sms_time 为空或者（不为空，并且send_sms_mobile=当前号码)
	$sql = "select a.*,b.name as 'projectname' ,c.case_code from edc_case_project_unbind_pwd  as  a " ;
	$sql .= " inner join edc_project as b on a.project_id=b.id left join edc_case_unbinding_md as c on c.unbinding_pwd_id=a.id  " ;
	$sql .= "  where a.md_pwd='".$case_pwd."' and a.pwd_flag='1'  " ; //and (IFNULL(send_sms_time,0) = 0 or (send_sms_time>0 and send_sms_mobile='".$case_mobile."'))";
	$result = mysql_query($sql);
	if ($rs = mysql_fetch_array($result)){
		$s_pwd_id =  $rs["id"]; //该密码的ID
		$s_project_id =  $rs["project_id"]; //项目ID
		$s_project_name = $rs["projectname"]; //项目名称
		$s_jk = $rs["bln_jk"]; //是否已经被使用
		$s_jk_date= $rs["jk_date"]; //使用日期
		$s_jk_user=$rs["jk_user"]; //使用人
		$s_dn= $rs["case_code"]; //使用该密码的试验编号
		$s_mobile= $rs["jk_mobile"]; //使用该密码的手机号
		$s_pwd_flag = $rs["pwd_flag"]; //密码类型 0:紧急揭盲 1:备用编号
		$s_send_sms_mobile =  $rs["send_sms_mobile"];
		$s_send_sms_time =  $rs["send_sms_time"];
	}else{ //没有找到该密码的信息
		$arr_out["ERROR"] =  $LANG["354_".$lan]; //"该随机密码不存在." ; //密码没有找到
		echo json_encode($arr_out);	
		exit;
	}
	//判断如果使用了别人占用的密码
	if (($rs["send_sms_time"] >0) && ($rs["send_sms_mobile"]!=$case_mobile)){
		$arr_out["ERROR"] = $LANG["329_".$lan]; //  "该随机密码被其他用户占用." ; //密码没有找到
		echo json_encode($arr_out);	
		exit;
	}
	
	
	//判断该密码是否已经被使用
	if ($s_jk == "1"){
		$arr_out["ERROR"] = $LANG["380_".$lan]; // "该随机密码已经被使用." ; //密码已经被使用
		echo json_encode($arr_out);	
		exit ; 
	}
	//判断是否有填写试验单位，因备用编号分配需要知道试验单位编码
	if ($case_company == ""){
		$arr_out["ERROR"] = $LANG["310_".$lan]; // "请填写试验单位." ; //疫苗类的申请试验单位没有填写
		echo json_encode($arr_out);	
		exit ; 
	}	
	
	$sql = "select id,case_code,bln_jk,jishu,unbinding,unbinding_k  from edc_case_unbinding_by_md " ;
	$sql .= " where project_id='".$s_project_id."' and case_code='".$case_code."'";
	$result = mysql_query($sql);
	$nums = mysql_num_rows($result); //试验编号的数量
	$rs = mysql_fetch_array($result); 
	//判断研究编号是否存在
	if ($nums == 0){
		$arr_out["ERROR"] =$LANG["373_".$lan]; // "该研究编号不存在." ; //该试验编号没有找到
		$arr_out["sql"] = $sql;
		echo json_encode($arr_out);	
		exit ; 
	}

	$arr_out["jishu"]= $nums; //记录该试验编号的总剂数
	if ($nums == 1 ){ //该研究编号的盲底只有一条的时候，不需要输入剂数
		if ($rs["bln_jk"] == "1"){ //直接判断该研究编号是否已经被使用过
			$arr_out["ERROR"] =  $LANG["376_".$lan]; // "该研究编号已经被使用." ; //试验编号已经被揭开了
			echo json_encode($arr_out);	
			exit ;
		}
	}else{ //如果该研究编号对应多条盲底，则需要填写剂数
		//判断是否有填写剂数
		if ($case_jishu == "" ){
			$arr_out["ERROR"] =  $LANG["372_".$lan]; // "该研究编号存在多剂数，请填写剂数." ; //不止一条并且没有填写剂数
			echo json_encode($arr_out);	
			exit ;
		}
		//判断该研究编号的该剂数数据
		$sql = "select id,case_code,bln_jk,jishu,unbinding,unbinding_k  from edc_case_unbinding_by_md " ;
		$sql .= " where project_id='".$s_project_id."' and jishu='".$case_jishu."' and  case_code='".$case_code."'";
		$result = mysql_query($sql);
		$nums = mysql_num_rows($result); 
		if ($nums == 0 ){
			$arr_out["ERROR"] =  $LANG["377_".$lan]; // "该研究编号不存在该剂数." ; //当前试验编号的剂数没有数据
			
			echo json_encode($arr_out);	
			exit ;	
		}else{ //该研究编号该剂数有数据
			$rs = mysql_fetch_array($result); 
			if ($rs["bln_jk"] == "1"){
				$arr_out["ERROR"] =  $LANG["367_".$lan]; // "该研究编码当前剂数已经被使用." ; //该试验编号的该剂数已经被使用了
				echo json_encode($arr_out);	
				exit ;	
			}else{
				//获取该试验编号该剂数的盲底
				$s_by_md_id = $rs["id"];//该研究编号该剂数的ID号
				$s_by_md_jiami = $rs["unbinding"] ;//该研究编号该剂数的盲底的加密字符串
				$s_by_md_jiami_key =$rs["unbinding_k"] ;//该研究编号该剂数的盲底的加密KEY
				$s_by_md = getdecrymd($s_by_md_jiami,$s_by_md_jiami_key);	//该研究编号该剂数的盲底
				$arr_out["by_md"] = $s_by_md ; //保存该盲底的信息到字符串
			}
			//寻找备用编号
			$sql = "select case_code,unbinding,unbinding_k,id from edc_case_unbinding_spare_md " ;
			$sql .= " where vendorcode='".$case_company."' and  project_id='".$s_project_id."' and bln_jk ='0' order by case_code ";
			$result = mysql_query($sql);
			$nums = mysql_num_rows($result);
			if ($nums == 0 ){ //寻找该试验单位该项目的未使用的备用编号数据
				$arr_out["ERROR"] =  $LANG["328_".$lan]; // "该试验单位未分配备用编号." ; //在该单位中没有找到备用苗
				echo json_encode($arr_out);	
				exit ;	
			}else{
				$str_bym_md_id = "" ;//备用苗的ID号
				$str_bym_md_code = "";//备用苗的药物编号
				while ($rs = mysql_fetch_array($result)){
					$str_case_md = $rs["unbinding"] ;
					$str_case_md_k =$rs["unbinding_k"] ;
					$str_bym_md = getdecrymd($str_case_md,$str_case_md_k);					
					if ($s_by_md == $str_bym_md){
						$str_bym_md_id = $rs["id"];
						$str_bym_md_code = $rs["case_code"];
						break;
					}
				}
				//判断是否已经找到备用编号
				if ($str_bym_md_id == ""){
					$arr_out["ERROR"] = $LANG["366_".$lan]; //  "该试验单位中未找到该研究编号该剂数的备用编号." ; //该单位中该试验号盲底的备用苗已经使用完成了
					echo json_encode($arr_out);	
					exit ;	
				}else{//已经找到备用苗了,各种更新
					
					//更新揭盲密码
					$sql1 = "update edc_case_project_unbind_pwd set bln_jk='1' ,jk_date='".date('Y-m-d H:i:s')."',jk_user='".$case_user."',jk_mobile='".$case_mobile."',jk_ip='".$_SERVER["REMOTE_ADDR"]."',jk_company='".$case_company."' where id='".$s_pwd_id."'";
					//更新疫苗盲底数据表
					$sql2 = "update edc_case_unbinding_by_md set bln_jk='1' ,unbinding_pwd_id='".$s_pwd_id."' ,beiyong_code='".$str_bym_md_code."' where id='".$s_by_md_id."'";
					//更新备用苗数据表
					$sql3 = "update edc_case_unbinding_spare_md set bln_jk='1' ,unbinding_pwd_id='".$s_pwd_id."' ,unbinding_pwd='".$case_pwd."',drug_code='".$case_code."',drug_jishu='".$case_jishu."',vendorcode='".$case_company."' where   id='".$str_bym_md_id."'";
					mysql_query($sql1);
			     	mysql_query($sql2);
					mysql_query($sql3);
					$arr_out["sql1"] = $sql1;
					$arr_out["sql2"] = $sql2;
					$arr_out["sql3"] = $sql3;
				}
				//输出结果 global  $case_code,$case_pwd,$case_mobile,$case_user,$case_company,$case_jishu,$checkcode;
				$arr_out["ERROR"] = "" ; //清空错误
				$arr_out["c_p_name"] = $s_project_name; //项目名称
				$arr_out["c_dn"]=$case_code ; //试验编号
				$arr_out["c_mobile"]=$case_mobile ; //手机号
				$arr_out["c_user"]=$case_user ; //揭盲用户
				$arr_out["c_company"]=$case_company; //揭盲试验单位
				$arr_out["c_pwd_flag"] = $s_pwd_flag ; //密码类型 0:一般 1:申请备用苗
				$arr_out["c_jici"]= $case_jishu; //剂数
				$arr_out["c_bym_code"]= $str_bym_md_code; //备用编号
				echo json_encode($arr_out);	 				
						
			}
			
		}
		
	}
	
	//判断该研究编号是否已经被使用 
	
	
	
	//判断该研究编号的剂数是否存在
	
	//判断该研究编号的剂数是否已经被使用

	//找到该研究编号的盲底，然后带备用表中寻找，不存在的话，弹出没有备用编号
	
	//找到备用编号的话，继续
	
	
	
	
	
}
function getdecrymd($md,$mdk){ //根据加密盲底和盲底秘钥解密
		$arr_2 = (mbStrSplit(decrypt($md),1));
		$arr_key = explode(",",$mdk);
		
		$str_result = "" ;
		foreach($arr_key as $k => $v){
			$str_result .= $arr_2[$v];
		}	
		return $str_result;
}


?>
