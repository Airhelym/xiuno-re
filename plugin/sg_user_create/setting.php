<?php
/*
	Xiuno BBS 4.0 会员批量注册
	查鸽信息网 http://cha.sgahz.net
*/
!defined('DEBUG') AND exit('Access Denied.');
if($method == 'GET') {
	$grouparr = arrlist_key_values($grouplist, 'gid', 'name');
	$input['_gid'] = form_select('_gid', $grouparr, 101);
	include _include(APP_PATH.'plugin/sg_user_create/setting.htm');
	
} elseif ($method == 'POST') {
		
	$username = param('username');
	$usernames = explode("\r\n",$username);
	$password = param('password');
	$email_on = param('email_on');

	$_gid = param('_gid');
	$i = 0;

	foreach($usernames as $key=>$username){
		$i++;

		$username = $username; 
		$password = $password; 
		$email_qq = $time + $i;
		$email = empty($email_on) ? "$username@qq.com" : "qq_$email_qq@qq.com";
		$_user_email = user_read_by_email($email);
		$_user_name = user_read_by_username($username);
		if(empty($_user_email) && empty($_user_name)) {
			$salt = xn_rand(16);
			$r = user_create(array(
				'username'=>$username,
				'password'=>md5(md5($password).$salt),
				'salt'=>$salt,
				'gid'=>$_gid,
				'email'=>$email,
				'create_ip'=>$longip,
				'create_date'=>$time
			));
		}
	}	
	message(0, lang('create_successfully'));
		
}

?>