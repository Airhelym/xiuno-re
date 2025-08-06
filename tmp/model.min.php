<?php


// 如果环境支持，可以直接改为 redis get() set() 持久存储相关 API，提高速度。


// 无缓存
function kv__get($k) {
	$arr = db_find_one('kv', array('k'=>$k));
	return $arr ? xn_json_decode($arr['v']) : NULL;
}
function kv_get($k) {
	static $static = array();
	strlen($k) > 32 AND $k = md5($k);
	if(!isset($static[$k])) {
		$static[$k] = kv__get($k);
	}
	return $static[$k];
}
function kv_set($k, $v, $life = 0) {
	strlen($k) > 32 AND $k = md5($k);
	$arr = array(
		'k'=>$k,
		'v'=>xn_json_encode($v),
	);
	$r = db_replace('kv', $arr);
	return $r;
}
function kv_delete($k) {
	strlen($k) > 32 AND $k = md5($k);
	$r = db_delete('kv', array('k'=>$k));
	return $r;
}



// --------------------> kv + cache
function kv_cache_get($k) {
	$r = cache_get($k);
	if($r === NULL) {
		$r = kv_get($k);
	}
	return $r;
}
function kv_cache_set($k, $v, $life = 0) {
	cache_set($k, $v, $life);
	$r = kv_set($k, $v);
	return $r;
}
function kv_cache_delete($k) {
	cache_delete($k);
	$r = kv_delete($k);
	return $r;
}



// ------------> kv + cache + setting
$g_setting = FALSE;
function setting_get($k) {
	global $g_setting;
	$g_setting === FALSE AND $g_setting = kv_cache_get('setting', $g_setting);
	empty($g_setting) AND $g_setting = array();
	return array_value($g_setting, $k, NULL);
}
// 全站的设置，全局变量 $g_setting = array();
function setting_set($k, $v) {
	global $g_setting;
	$g_setting === FALSE AND $g_setting = kv_cache_get('setting', $g_setting);
	empty($g_setting) AND $g_setting = array();
	$g_setting[$k] = $v;
	return kv_cache_set('setting', $g_setting);
}
function setting_delete($k) {
	global $g_setting;
	$g_setting === FALSE AND $g_setting = kv_cache_get('setting', $g_setting);
	empty($g_setting) AND $g_setting = array();
	if(isset($g_setting[$k])) unset($g_setting[$k]);
	kv_cache_set('setting', $g_setting);
	return TRUE;
}


?><?php


/*
	mysql 模拟队列，顺序可能是乱的，不是严格意义上的队列
*/

// 提取整个队列
function queue_find($queueid, $page = 1, $pagesize = 100) {
	$arrlist = db_find('queue', array('queueid'=>$queueid), array(), $page, $pagesize);
	$ids = array();
	if($arrlist) {
		$ids = arrlist_values($arrlist, 'v');
	}
	return $ids;
}

// 添加到队列
function queue_push($queueid, $v, $expiry = 0) {
	global $time;
	$r = db_create('queue', array('queueid'=>$queueid, 'v'=>$v, 'expiry'=>($time + $expiry)));
	return $r;
}

// 弹出某个值
function queue_pop($queueid) {
	$r = db_find_one('queue', array('queueid'=>$queueid));
	if($r) {
		queue_delete($queueid, $r['v']);
	}
	return $r ? $r['v'] : FALSE;
}

// 删除某个值
function queue_delete($queueid, $v) {
	$r = db_delete('queue', array('queueid'=>$queueid, 'v'=>$v));
	return $r;
}

// 销毁某个队列
function queue_destory($queueid) {
	$r = db_delete('queue', array('queueid'=>$queueid));
	return $r;
}

function queue_count($queueid) {
	$n = db_count('queue', array('queueid'=>$queueid));
	return $n;
}

function queue_gc() {
	global $time;
	$r = db_delete('queue', array('expiry'=>array('<'=>$time)));
	return $r;
}


?><?php




// ------------> 最原生的 CURD，无关联其他数据。

function group__create($arr) {
	
	$r = db_create('group', $arr);
	
	return $r;
}

function group__update($gid, $arr) {
	
	$r = db_update('group', array('gid'=>$gid), $arr);
	
	return $r;
}

function group__read($gid) {
	
	$group = db_find_one('group', array('gid'=>$gid));
	
	return $group;
}

function group__delete($gid) {
	
	$r = db_delete('group', array('gid'=>$gid));
	
	return $r;
}

function group__find($cond = array(), $orderby = array(), $page = 1, $pagesize = 1000) {
	
	$grouplist = db_find('group', $cond, $orderby, $page, $pagesize, 'gid');
	
	return $grouplist;
}

// ------------> 关联 CURD，主要是强相关的数据，比如缓存。弱相关的大量数据需要另外处理。

function group_create($arr) {
	
	$r = group__create($arr);
	group_list_cache_delete();
	forum_access_padding($arr['gid'], TRUE); // 填充
	
	return $r;
}

function group_update($gid, $arr) {
	
	$r = group__update($gid, $arr);
	group_list_cache_delete();
	
	return $r;
}

function group_read($gid) {
	
	$group = group__read($gid);
	group_format($group);
	
	return $group;
}

function group_delete($gid) {
	
	$r = group__delete($gid);
	group_list_cache_delete();
	forum_access_padding($gid, FALSE); // 删除
	
	return $r;
}

function group_find($cond = array(), $orderby = array('gid'=>1), $page = 1, $pagesize = 1000) {
	
	$grouplist = group__find($cond, $orderby, $page, $pagesize);
	if($grouplist) foreach ($grouplist as &$group) group_format($group);
	
	return $grouplist;
}

// ------------> 其他方法

function group_format(&$group) {
	
	
}

function group_name($gid) {
	global $grouplist;
	return isset($grouplist[$gid]['name']) ? $grouplist[$gid]['name'] : '';
}


function group_count($cond = array()) {
	$n = db_count('group', $cond);
	
	return $n;
}

function group_maxid() {
	
	$n = db_maxid('group', 'gid');
	
	return $n;
}

// 从缓存中读取 forum_list 数据
function group_list_cache() {
	$grouplist = cache_get('grouplist');
	
	if($grouplist === NULL || $grouplist === FALSE) {
		$grouplist = group_find();
		cache_set('grouplist', $grouplist);
	}
	
	return $grouplist;
}

// 更新 forumlist 缓存
function group_list_cache_delete() {
	
	$r = cache_delete('grouplist');
	
	return $r;
}





?><?php



// 只能在当前 request 生命周期缓存，要跨进程，可以再加一层缓存： memcached/xcache/apc/
$g_static_users = array(); // 变量缓存



// ------------> 最原生的 CURD，无关联其他数据。

function user__create($arr) {
	
	$r = db_insert('user', $arr);
	
	return $r;
}

function user__update($uid, $update) {
	
	$r = db_update('user', array('uid'=>$uid), $update);
	
	return $r;
}

function user__read($uid) {
	
	$user = db_find_one('user', array('uid'=>$uid));
	
	return $user;
}

function user__delete($uid) {
	
	$r = db_delete('user', array('uid'=>$uid));
	
	return $r;
}

// ------------> 关联 CURD，主要是强相关的数据，比如缓存。弱相关的大量数据需要另外处理。

function user_create($arr) {
	
	global $conf;
	$r = user__create($arr);
	
	// 全站统计
	runtime_set('users+', 1);
	runtime_set('todayusers+', 1);
	
	
	return $r;
}

function user_update($uid, $arr) {
	
	global $conf, $g_static_users;
	$r = user__update($uid, $arr);
	$conf['cache']['type'] != 'mysql' AND cache_delete("user-$uid");
	isset($g_static_users[$uid]) AND $g_static_users[$uid] = array_merge($g_static_users[$uid], $arr);
	
	
	return $r;
}

function user_read($uid) {
	global $g_static_users;
	if(empty($uid)) return array();
	$uid = intval($uid);
	
	$user = user__read($uid);
	user_format($user);
	$g_static_users[$uid] = $user;
	
	return $user;
}


// 从缓存中读取，避免重复从数据库取数据，主要用来前端显示，可能有延迟。重要业务逻辑不要调用此函数，数据可能不准确，因为并没有清理缓存，针对 request 生命周期有效。
function user_read_cache($uid) {
	global $conf, $g_static_users;
	if(isset($g_static_users[$uid])) return $g_static_users[$uid];
	
	
	
	// 游客
	if($uid == 0) return user_guest();
	
	if($conf['cache']['type'] != 'mysql') {
		$r = cache_get("user-$uid");
		if($r === NULL) {
			$r = user_read($uid);
			cache_set("user-$uid", $r);
		}
	} else {
		$r = user_read($uid);
	}
	
	$g_static_users[$uid] = $r ? $r : user_guest();
	
	
	return $g_static_users[$uid];
}

function user_delete($uid) {
	global $conf, $g_static_users;
	
	
	// 清理主题帖
	$threadlist = mythread_find_by_uid($uid, 1, 1000);
	foreach($threadlist as $thread) {
		thread_delete($thread['tid']);
	}
	
	// 清理回帖
	post_delete_by_uid($uid);
	
	// 清理附件
	attach_delete_by_uid($uid);
	
	$r = user__delete($uid);
	
	$conf['cache']['type'] != 'mysql' AND cache_delete("user-$uid");
	if(isset($g_static_users[$uid])) unset($g_static_users[$uid]);
	
	// 全站统计
	runtime_set('users-', 1);
	
	
	return $r;
}

function user_find($cond = array(), $orderby = array(), $page = 1, $pagesize = 20) {
	global $g_static_users;
	
	$userlist = db_find('user', $cond, $orderby, $page, $pagesize);
	if($userlist) foreach ($userlist as &$user) {
		$g_static_users[$user['uid']] = $user;
		user_format($user);
	}
	
	return $userlist;
}

// ------------> 其他方法

function user_read_by_email($email) {
	global $g_static_users;
	
	$user = db_find_one('user', array('email'=>$email));
	user_format($user);
	$g_static_users[$user['uid']] = $user;
	
	return $user;
}

function user_read_by_username($username) {
	global $g_static_users;
	
	$user = db_find_one('user', array('username'=>$username));
	user_format($user);
	$g_static_users[$user['uid']] = $user;
	
	return $user;
}

function user_count($cond = array()) {
	
	$n = db_count('user', $cond);
	
	return $n;
}

function user_maxid($cond = array()) {
	
	$n = db_maxid('user', 'uid');
	
	return $n;
}

function user_format(&$user) {
	global $conf, $grouplist;
	if(empty($user)) return;

	
	
	$user['create_ip_fmt']   = long2ip($user['create_ip']);
	$user['create_date_fmt'] = empty($user['create_date']) ? '0000-00-00' : date('Y-m-d', $user['create_date']);
	$user['login_ip_fmt']    = long2ip($user['login_ip']);
	$user['login_date_fmt'] = empty($user['login_date']) ? '0000-00-00' : date('Y-m-d', $user['login_date']);
	
	$user['groupname'] = group_name($user['gid']);
	
	$dir = substr(sprintf("%09d", $user['uid']), 0, 3);
	
	$user['avatar_url'] = $user['avatar'] ? $conf['upload_url']."avatar/$dir/$user[uid].png?".$user['avatar'] : 'view/img/avatar.png';

	$user['online_status'] = 1;
	
}


function user_guest() {
	global $conf;
	static $guest = NULL;
	
	
	if($guest) return $guest; // 返回引用，节省内存。
	$guest = array (
		'uid' => 0,
		'gid' => 0,
		'groupname' => lang('guest_group'),
		'username' => lang('guest'),
		'avatar_url' => 'view/img/avatar.png',
		'create_ip_fmt' => '',
		'create_date_fmt' => '',
		'login_date_fmt' => '',
		'email' => '',
		
		'threads' => 0,
		'posts' => 0,
	);
	
	
	return $guest; // 防止内存拷贝
}

// 根据积分来调整用户组
function user_update_group($uid) {
	global $conf, $grouplist;
	$user = user_read_cache($uid);
	if($user['gid'] < 100) return FALSE;
	
	
	
	// 遍历 credits 范围，调整用户组
	foreach($grouplist as $group) {
		if($group['gid'] < 100) continue;
		$n = $user['posts'] + $user['threads']; // 根据发帖数
		
		if($n > $group['creditsfrom'] && $n < $group['creditsto']) {
			if($user['gid'] != $group['gid']) {
				user_update($uid, array('gid'=>$group['gid']));
				return TRUE;
			}
		}
	}
	
	
	return FALSE;
}

// uids: 1,2,3,4 -> array()
function user_find_by_uids($uids) {
	
	$uids = trim($uids);
	if(empty($uids)) return array();
	$arr = explode(',', $uids);
	$r = array();
	foreach($arr as $_uid) {
		$user = user_read_cache($_uid);
		if(empty($user)) continue;
		$r[$user['uid']] = $user;
	}
	
	return $r;
}

// 获取用户安全信息
function user_safe_info($user) {
	
	unset($user['password']);
	unset($user['email']);
	unset($user['salt']);
	unset($user['password_sms']);
	unset($user['idnumber']);
	unset($user['realname']);
	unset($user['qq']);
	unset($user['mobile']);
	unset($user['create_ip']);
	unset($user['create_ip_fmt']);
	unset($user['create_date']);
	unset($user['create_date_fmt']);
	unset($user['login_ip']);
	unset($user['login_date']);
	unset($user['login_ip_fmt']);
	unset($user['login_date_fmt']);
	unset($user['logins']);
	
	return $user;
}


// 用户
function user_token_get() {
	global $time;
	$_uid = user_token_get_do();
	
	
	
	if(!$_uid) {
		setcookie('bbs_token', '', $time - 86400, '');
	}
	
	
	
	return $_uid;
}

// 用户
function user_token_get_do() {
	global $time, $ip, $conf;
	$token = param('bbs_token');
	
	
	
	if(empty($token)) return FALSE;
	$tokenkey = md5(xn_key());
	$s = xn_decrypt($token, $tokenkey);
	if(empty($s)) return FALSE;
	$arr = explode("\t", $s);
	if(count($arr) != 3) return FALSE;
	list($_ip, $_time, $_uid) = $arr;
	//if($ip != $_ip) return FALSE;
	//if($time - $_time > 86400) return FALSE;
	
	
	
	return $_uid;	
}

// 设置 token，防止 sid 过期后被删除
function user_token_set($uid) {
	global $time, $conf;
	if(empty($uid)) return;
	$token = user_token_gen($uid);
	setcookie('bbs_token', $token, $time + 8640000, $conf['cookie_path']);
	
	
}

function user_token_clear() {
	global $time, $conf;
	setcookie('bbs_token', '', $time - 8640000, $conf['cookie_path']);
	
	
}

function user_token_gen($uid) {
	global $ip, $time, $conf;
	
	
	
	$tokenkey = md5(xn_key());
	$token = xn_encrypt("$ip	$time	$uid", $tokenkey);
	
	
	
	return $token;
}


// 前台登录验证
function user_login_check() {
	global $user;
	
	
	
	empty($user) AND http_location(url('user-login'));
	
	
}




?><?php




// ------------> 最原生的 CURD，无关联其他数据。

function forum__create($arr) {
	
	$r = db_create('forum', $arr);
	
	return $r;
}

function forum__update($fid, $arr) {
	
	$r = db_update('forum', array('fid'=>$fid), $arr);
	
	return $r;
}

function forum__read($fid) {
	
	$forum = db_find_one('forum', array('fid'=>$fid));
	
	return $forum;
}

function forum__delete($fid) {
	
	$r = db_delete('forum', array('fid'=>$fid));
	
	return $r;
}

function forum__find($cond = array(), $orderby = array(), $page = 1, $pagesize = 1000) {
	
	$forumlist = db_find('forum', $cond, $orderby, $page, $pagesize, 'fid');
	
	return $forumlist;
}

// ------------> 关联 CURD，主要是强相关的数据，比如缓存。弱相关的大量数据需要另外处理。

function forum_create($arr) {
	
	$r = forum__create($arr);
	forum_list_cache_delete();
	
	return $r;
}

function forum_update($fid, $arr) {
	
	$r = forum__update($fid, $arr);
	forum_list_cache_delete();
	
	return $r;
}

function forum_read($fid) {
	
	global $conf, $forumlist;
	if($conf['cache']['enable']) {
		return empty($forumlist[$fid]) ? array() : $forumlist[$fid];
	} else {
		$forum = forum__read($fid);
		forum_format($forum);
		return $forum;
	}
	
}

// 关联数据删除
function forum_delete($fid) {
	//  把板块下所有的帖子都查找出来，此处数据量大可能会超时，所以不要删除帖子特别多的板块
	$cond = array('fid'=>$fid);
	$threadlist = db_find('thread', $cond, array(), 1, 1000000, '', array('tid', 'uid'));
	
	
	
	foreach ($threadlist as $thread) {
		thread_delete($thread['tid']);
	}
	
	$r = forum__delete($fid);
	
	forum_access_delete_by_fid($fid);
	
	forum_list_cache_delete();
	
	return $r;
}

function forum_find($cond = array(), $orderby = array('rank'=>-1), $page = 1, $pagesize = 1000) {
	
	$forumlist = forum__find($cond, $orderby, $page, $pagesize);
	if($forumlist) foreach ($forumlist as &$forum) forum_format($forum);
	
	return $forumlist;
}

// ------------> 其他方法

function forum_format(&$forum) {
	global $conf;
	if(empty($forum)) return;
	
	
	
	$forum['create_date_fmt'] = date('Y-n-j', $forum['create_date']);
	$forum['icon_url'] = $forum['icon'] ? $conf['upload_url']."forum/$forum[fid].png" : 'view/img/forum.png';
	$forum['accesslist'] = $forum['accesson'] ? forum_access_find_by_fid($forum['fid']) : array();
	$forum['modlist'] = array();
	if($forum['moduids']) {
		$modlist = user_find_by_uids($forum['moduids']);
		foreach($modlist as &$mod) $mod = user_safe_info($mod);
		$forum['modlist'] = $modlist;
	}
	
}

function forum_count($cond = array()) {
	
	$n = db_count('forum', $cond);
	
	return $n;
}

function forum_maxid() {
	
	$n = db_maxid('forum', 'fid');
	
	return $n;
}

// 从缓存中读取 forum_list 数据x
function forum_list_cache() {
	global $conf, $forumlist;
	$forumlist = cache_get('forumlist');
	
	
	
	if($forumlist === NULL) {
		$forumlist = forum_find();
		cache_set('forumlist', $forumlist, 60);
	}
	
	return $forumlist;
}

// 更新 forumlist 缓存
function forum_list_cache_delete() {
	global $conf;
	static $deleted = FALSE;
	if($deleted) return;
	
	
	
	cache_delete('forumlist');
	$deleted = TRUE;
	
}

// 对 $forumlist 权限过滤，查看权限没有，则隐藏
function forum_list_access_filter($forumlist, $gid, $allow = 'allowread') {
	global $conf, $grouplist;
	if(empty($forumlist)) return array();
	if($gid == 1) return $forumlist;
	$forumlist_filter = $forumlist;
	$group = $grouplist[$gid];
	
	
	
	foreach($forumlist_filter as $fid=>$forum) {
		if(empty($forum['accesson']) && empty($group[$allow]) || !empty($forum['accesson']) && empty($forum['accesslist'][$gid][$allow])) {
			unset($forumlist_filter[$fid]);
			unset($forumlist_filter[$fid]['modlist']);
		}
		unset($forumlist_filter[$fid]['accesslist']);
	}
	
	return $forumlist_filter;
}

function forum_filter_moduid($moduids) {
	$moduids = trim($moduids);
	if(empty($moduids)) return '';
	$arr = explode(',', $moduids);
	$r = array();
	foreach($arr as $_uid) {
		$_uid = intval($_uid);
		$_user = user_read($_uid);
		if(empty($_user)) continue;
		if($_user['gid'] > 4) continue;
		$r[] = $_uid;
	}
	return implode(',', $r);
}


function forum_safe_info($forum) {
	
	//unset($forum['moduids']);
	
	return $forum;
}




?><?php




// ------------> 最原生的 CURD，无关联其他数据。

function forum_access__create($arr) {
	
	$r = db_create('forum_access', $arr);
	
	return $r;
}

function forum_access__update($fid, $gid, $arr) {
	
	$r = db_update('forum_access', array('fid'=>$fid, 'gid'=>$gid), $arr);
	
	return $r;
}

function forum_access__read($fid, $gid) {
	
	$access = db_find_one('forum_access', array('fid'=>$fid, 'gid'=>$gid));
	
	return $access;
}

function forum_access__delete($fid, $gid) {
	
	$r = db_delete('forum_access', array('fid'=>$fid, 'gid'=>$gid));
	
	return $r;
}

function forum_access__find($cond = array(), $orderby = array(), $page = 1, $pagesize = 20) {
	
	$accesslist = db_find('forum_access', $cond, $orderby, $page, $pagesize);
	
	return $accesslist;
}

// ------------> 关联 CURD，主要是强相关的数据，比如缓存。弱相关的大量数据需要另外处理。

function forum_access_create($arr) {
	
	$r = forum_access__create($arr);
	
	return $r;
}

function forum_access_update($fid, $gid, $arr) {
	
	$r = forum_access__update($fid, $gid, $arr);
	
	return $r;
}

// 不存在，则创建一条
function forum_access_replace($fid, $gid, $arr) {
	
	$access = forum_access__read($fid, $gid);
	if(empty($access)) {
		$arr['fid'] = $fid;
		$arr['gid'] = $gid;
		$r = forum_access__create($arr);
	} else {
		$r = forum_access__update($fid, $gid, $arr);
	}
	
	return $r;
}

// 根据 gid 补充 forum_access
function forum_access_padding($gid, $fill = FALSE) {
	
	$forumlist = forum_list_cache();
	foreach($forumlist as $fid=>$forum) {
		if(!$forum['accesson']) continue;
		$fill ? forum_access_create(array('fid'=>$fid, 'gid'=>$gid)) : forum_access_delete($fid, $gid);
	}
	
}

function forum_access_read($fid, $gid) {
	
	$access = forum_access__read($fid, $gid);
	forum_access_format($access);
	
	return $access;
}

function forum_access_delete($fid, $gid) {
	
	$r = forum_access__delete($fid, $gid);
	
	return $r;
}

function forum_access_delete_by_fid($fid) {
	
	$accesslist = forum_access_find_by_fid($fid);
	foreach ($accesslist as $access) {
		forum_access_delete($access['fid'], $access['gid']);
	}
	
}

function forum_access_find($cond = array(), $orderby = array(), $page = 1, $pagesize = 20) {
	
	$accesslist = forum_access__find($cond, $orderby, $page, $pagesize);
	if($accesslist) foreach ($accesslist as &$access) forum_access_format($access);
	
	return $accesslist;
}

function forum_access_find_by_fid($fid) {
	
	$cond = array('fid'=>$fid);
	$orderby = array('gid'=>1);
	$accesslist = db_find('forum_access', $cond, $orderby, 1, 100, 'gid');
	
	return $accesslist;
}

// 普通用户权限判断: allowread, allowthread, allowpost, allowattach, allowdown
function forum_access_user($fid, $gid, $access) {
	
	global $conf, $grouplist, $forumlist;
	if(empty($forumlist[$fid])) return FALSE;
	$group = $grouplist[$gid];
	$forum = $forumlist[$fid];
	if($forum['accesson']) {
		$r = (!isset($group[$access]) || $group[$access]) && !empty($forum['accesslist'][$gid][$access]);
	} else {
		$r = !empty($group[$access]);
	}
	
	return $r;
}

// 板块斑竹权限判断: allowtop, allowmove, allowupdate, allowdelete, allowbanuser, allowviewip, allowdeleteuser
function forum_access_mod($fid, $gid, $access) {
	
	global $uid, $conf, $grouplist, $forumlist;
	
	// 结果缓存，加速判断！
	static $result = array();
	$k = "$fid-$gid-$access";
	if(isset($result[$k])) return $result[$k];
	
	if($gid == 1 || $gid == 2) return TRUE; // 管理员有所有权限
	if($gid == 3 || $gid == 4) {
		$group = $grouplist[$gid];
		$forum = $forumlist[$fid];
		$r = !empty($group[$access]) && in_string($uid, $forum['moduids']);
	} else {
		$r = FALSE;
	}
	$result[$k] = $r;
	
	return $r;
}

function forum_is_mod($fid, $gid, $uid) {
	
	global $conf, $grouplist, $forumlist;
	if($gid == 1 || $gid == 2) return TRUE; // 管理员有所有权限
	if($gid == 3 || $gid == 4) {
		if($fid == 0) return TRUE; // 此处不严谨！
		$group = $grouplist[$gid];
		$forum = $forumlist[$fid];
		return in_string($uid, $forum['moduids']);
	}
	
	return FALSE;
}

// ------------> 其他方法

function forum_access_format(&$access) {
	
	if(empty($access)) return;
	
}

function forum_access_count($cond = array()) {
	
	$n = db_count('forum_access', $cond);
	
	return $n;
}





?><?php




// ------------> 最原生的 CURD，无关联其他数据。

function thread__create($arr) {
	
	$r = db_insert('thread', $arr);
	
	return $r;
}

function thread__update($tid, $arr) {
	
	$r = db_update('thread', array('tid'=>$tid), $arr);
	
	return $r;
}

function thread__read($tid) {
	
	$thread = db_find_one('thread', array('tid'=>$tid));
	
	return $thread;
}

function thread__delete($tid) {
	
	$r = db_delete('thread', array('tid'=>$tid));
	
	return $r;
}

function thread__find($cond = array(), $orderby = array(), $page = 1, $pagesize = 20) {
	
	
	$arrlist = db_find('thread', $cond, $orderby, $page, $pagesize, 'tid', array('tid'));
	if(empty($arrlist)) return array();
	
	$tidarr = arrlist_values($arrlist, 'tid');
	$threadlist = db_find('thread', array('tid'=>$tidarr), $orderby, 1, $pagesize, 'tid');
	
	
	return $threadlist;
}

function thread_create($arr, &$pid) {
	global $conf, $gid;
	$fid = $arr['fid'];
	$uid = $arr['uid'];
	$subject = $arr['subject'];
	$message = $arr['message'];
	$time = $arr['time'];
	$longip = $arr['longip'];
	$doctype = $arr['doctype'];
	
	# 论坛帖子数据，一页显示，不分页。
	$post = array(
		'tid'=>0,
		'isfirst'=>1,
		'uid'=>$uid,
		'create_date'=>$time,
		'userip'=>$longip,
		'message'=>$message,
		'doctype'=>$doctype,
	);
	
	
	
	$pid = post__create($post, $gid);
	if($pid === FALSE) return FALSE;
	
	// 创建主题
	$thread = array (
		'fid'=>$fid,
		'subject'=>$subject,
		'uid'=>$uid,
		'create_date'=>$time,
		'last_date'=>$time,
		'firstpid'=>$pid,
		'lastpid'=>$pid,
		'userip'=>$longip,
	);
	
	
	
	$tid = thread__create($thread);
	if($tid === FALSE) {
		post__delete($pid);
		return FALSE;
	}
	// 板块总数+1, 用户发帖+1
	
	// 更新统计数据
	$uid AND user__update($uid, array('threads+'=>1));
	forum__update($fid, array('threads+'=>1, 'todaythreads+'=>1));
	
	// 关联
	post__update($pid, array('tid'=>$tid), $tid);

	// 我参与的发帖
	$uid AND mythread_create($uid, $tid);
	
	// 关联附件
	attach_assoc_post($pid);
	
	// 全站发帖数
	runtime_set('threads+', 1);
	runtime_set('todaythreads+', 1);
	
	// 更新板块信息。
	forum_list_cache_delete();
	
	
	
	return $tid;
}

// 不要在大循环里调用此函数！比较耗费资源。
function thread_update($tid, $arr) {
	global $conf;
	$thread = thread__read($tid);
	
	
	
	if(isset($arr['subject']) && $arr['subject'] != $thread['subject']) {
		$thread['top'] > 0 AND thread_top_cache_delete();
	}
	
	// 更改 fid, 移动主题，相关资源也需要更新
	if(isset($arr['fid']) && $arr['fid'] != $thread['fid']) {
		forum__update($arr['fid'], array('threads+'=>1));
		forum__update($thread['fid'], array('threads-'=>1));
		thread_top_update_by_tid($tid, $arr['fid']);
	}
	
	if(!$arr) return TRUE;
	
	$r = thread__update($tid, $arr);
	
	
	return $r;
}

// views + 1
function thread_inc_views($tid, $n = 1) {
	
	global $conf;
	if(!$conf['update_views_on']) return TRUE;
	$sqladd = strpos($conf['db']['type'], 'mysql') === FALSE ? '' : ' LOW_PRIORITY';
	$r = db_exec("UPDATE$sqladd `bbs_thread` SET views=views+$n WHERE tid='$tid'");
	
	return $r;
}

function thread_read($tid) {
	
	$thread = thread__read($tid);
	thread_format($thread);
	
	return $thread;
}

// 从缓存中读取，避免重复从数据库取数据，主要用来前端显示，可能有延迟。重要业务逻辑不要调用此函数，数据可能不准确，因为并没有清理缓存，针对 request 生命周期有效。
function thread_read_cache($tid) {
	
	static $cache = array(); // 用静态变量只能在当前 request 生命周期缓存，要跨进程，可以再加一层缓存： memcached/xcache/apc/
	if(isset($cache[$tid])) return $cache[$tid];
	$cache[$tid] = thread_read($tid);
	
	return $cache[$tid];
}

// 删除主题
function thread_delete($tid) {
	global $conf;
	$thread = thread__read($tid);
	if(empty($thread)) return TRUE;
	$fid = $thread['fid'];
	$uid = $thread['uid'];
	
	
	
	// 删除所有回帖，同时更新 posts 统计数
	$n = post_delete_by_tid($tid);
	
	// 删除我的主题
	$uid AND mythread_delete($uid, $tid);
	
	// 清除相关缓存
	forum_list_cache_delete();
	
	$r = thread__delete($tid);
	if($r === FALSE) return FALSE;
	
	// 更新统计
	forum__update($fid, array('threads-'=>1));
	user__update($uid, array('threads-'=>1));
	
	// 全站统计
	runtime_set('threads-', 1);
	
	
	
	return $r;
}

function thread_find($cond = array(), $orderby = array(), $page = 1, $pagesize = 20) {
	
	$threadlist = thread__find($cond, $orderby, $page, $pagesize);
	if($threadlist) foreach ($threadlist as &$thread) thread_format($thread);
	
	return $threadlist;
}

// $order: tid/lastpid
// 按照: 发帖时间/最后回复时间 倒序，不包含置顶帖
function thread__find_by_fid($fid, $page = 1, $pagesize = 20, $order = 'lastpid') {
	global $conf, $forumlist, $runtime;
	$forum = $fid ? $forumlist[$fid] : array();
	$threads = empty($forum) ? $runtime['threads'] : $forum['threads'];
	
	
	
	$cond = array();
	$fid AND $cond['fid'] = $fid;
	
	$desc = TRUE;
	$limitpage = 50000; // 如果需要防止 CC 攻击，可以调整为 5000
	if($page > 100) {
		$totalpage = ceil($threads / $pagesize);
		$halfpage = ceil($totalpage / 2);
		if($halfpage > $limitpage && $page < ($totalpage - $limitpage)) {
			$page = $limitpage;
		}
		if($page > $halfpage) {
			$page = max(1, $totalpage - $page + 1) ;
			$threadlist = thread_find($cond, array($order=>1), $page, $pagesize);
			$threadlist = array_reverse($threadlist, TRUE);
			$desc = FALSE;
		}
	}
	if($desc) {
		$orderby = array($order=>-1);
		$threadlist = thread_find($cond, $orderby, $page, $pagesize);
	}
	
	
	
	return $threadlist;
}

// $order: tid/lastpid
// 按照: 发帖时间/最后回复时间 倒序，包含置顶帖
function thread_find_by_fid($fid, $page = 1, $pagesize = 20, $order = 'lastpid') {
	global $conf, $forumlist, $runtime;

	

	$threadlist = thread__find_by_fid($fid, $page, $pagesize, $order);

	
	
	// 查找置顶帖
	if($order == $conf['order_default'] && $page == 1) {
		$toplist3 = thread_top_find(0);
		$toplist1 = thread_top_find($fid);
		//$toplist = thread_top_find($fid);
		$threadlist = $toplist3 + $toplist1 + $threadlist;
	}
	
	
	return $threadlist;
}

// 从多个版块获取列表数据
function thread_find_by_fids($fids, $page = 1, $pagesize = 20, $order = 'lastpid', $threads = FALSE) {
	
	
	
	$threadlist = thread_find(array('fid'=>$fids), array($order=>-1), $page, $pagesize);
	
	
	
	return $threadlist;
}

// 默认搜索标题
function thread_find_by_keyword($keyword) {
	
	$threadlist = db_find('thread', array('subject'=>array('LIKE'=>$keyword)), array(), 1, 60);
	$threadlist = arrlist_multisort($threadlist, 'tid', FALSE); // 用 PHP 排序，mysql 排序消耗太大。
	if($threadlist) {
		foreach ($threadlist as &$thread) {
			thread_format($thread);
			$thread['subject'] = post_highlight_keyword($thread['subject'], $keyword);
		}
	}
	
	return $threadlist;
}


function thread_format(&$thread) {
	
	global $conf, $forumlist;
	if(empty($thread)) return;
	
	
	
	$thread['create_date_fmt'] = humandate($thread['create_date']);
	$thread['last_date_fmt'] = humandate($thread['last_date']);
	
	$user = user_read_cache($thread['uid']);
	$thread['username'] = $user['username'];
	$thread['user_avatar_url'] = $user['avatar_url'];
	$thread['user'] = $user;
	
	$forum = isset($forumlist[$thread['fid']]) ? $forumlist[$thread['fid']] : array('name'=>'');
	$thread['forumname'] = $forum['name'];
	
	if($thread['last_date'] == $thread['create_date']) {
		//$thread['last_date'] = 0;
		$thread['last_date_fmt'] = '';
		$thread['lastuid'] = 0;
		$thread['lastusername'] = '';
	} else {
		$lastuser = $thread['lastuid'] ? user_read_cache($thread['lastuid']) : array();
		$thread['lastusername'] = $thread['lastuid'] ? $lastuser['username'] : lang('guest');
	}
	
	$thread['url'] = "thread-$thread[tid].htm";
	$thread['user_url'] = "user-$thread[uid]".($thread['uid'] ? '' : "-$thread[firstpid]").".htm";
	
	$thread['top_class'] = $thread['top'] ? 'top_'.$thread['top'] : '';

	$thread['pages'] = ceil($thread['posts'] / $conf['postlist_pagesize']);
		
	
}

function thread_format_last_date(&$thread) {
	
	if($thread['last_date'] != $thread['create_date']) {
		$thread['last_date_fmt'] = humandate($thread['last_date']);
	} else {
		$thread['create_date_fmt'] = humandate($thread['create_date']);
	}
	
}

function thread_count($cond = array()) {
	
	$n = db_count('thread', $cond);
	
	return $n;
}

function thread_maxid() {
	
	$n = db_maxid('thread', 'tid');
	
	return $n;
}

function thread_safe_info($thread) {
	
	unset($thread['userip']);
	if(!empty($thread['user'])) {
		$thread['user'] = user_safe_info($thread['user']);
	}
	
	return $thread;
}

function thread_get_level($n, $levelarr) {
	
	foreach($levelarr as $k=>$level) {
		if($n <= $level) return $k;
	}
	
	return $k;
}


// 对 $threadlist 权限过滤
function thread_list_access_filter(&$threadlist, $gid) {
	global $conf, $forumlist;
	if(empty($threadlist)) return;
	
	
	
	foreach($threadlist as $tid=>$thread) {
		if(empty($forumlist[$thread['fid']]['accesson'])) continue;
		if($thread['top'] > 0) continue;
		if(!forum_access_user($thread['fid'], $gid, 'allowread')) {
			unset($threadlist[$tid]);
		}
	}
	
}

function thread_find_by_tids($tids, $order = array('lastpid'=>-1)) {
	
	//$start = ($page - 1) * $pagesize;
	//$tids = array_slice($tids, $start, $pagesize);
	if(!$tids) return array();
	$threadlist = db_find('thread', array('tid'=>$tids), $order, 1, 1000, 'tid');
	if($threadlist) foreach($threadlist as &$thread) thread_format($thread);
	
	return $threadlist;
}

// 查找 lastpid
function thread_find_lastpid($tid) {
	$arr = db_find_one("post", array('tid'=>$tid), array('pid'=>-1), array('pid'));
	$lastpid = empty($arr) ? 0 : $arr['pid'];
	return $lastpid;
}

// 更新最后的 uid pid
function thread_update_last($tid) {
	$lastpid = thread_find_lastpid($tid);
	if(empty($lastpid)) return;
	
	$lastpost = post__read($lastpid);
	if(empty($lastpost)) return;
	
	$r = thread__update($tid, array('lastpid'=>$lastpid, 'lastuid'=>$lastpost['uid'], 'last_date'=>$lastpost['create_date']));

	return $r;
}




?><?php




// 置顶主题

function thread_top_change($tid, $top = 0) {
	
	$thread = thread__read($tid);
	if(empty($thread)) return FALSE;
	if($top != $thread['top']) {
		thread__update($tid, array('top'=>$top));
		$fid = $thread['fid'];
		$tid = $thread['tid'];
		thread_top_cache_delete();
		
		$arr = array('fid'=>$fid, 'tid'=>$tid, 'top'=>$top);
		$r = db_replace('thread_top', $arr);
		return $r;
	}
	
	return FALSE;
}

function thread_top_delete($tid) {
	
	$r = db_delete('thread_top', array('tid'=>$tid));
	
	return $r;
}

function thread_top_find($fid = 0) {
	
	if($fid == 0) {
		$threadlist = db_find('thread_top', array('top'=>3), array('tid'=>-1), 1, 100, 'tid');
	} else {
		$threadlist = db_find('thread_top', array('fid'=>$fid, 'top'=>1), array('tid'=>-1), 1, 100, 'tid');
	}
	$tids = arrlist_values($threadlist, 'tid');
	$threadlist = thread_find_by_tids($tids);
	
	return $threadlist;
}

function thread_top_find_cache() {
	
	global $conf;
	$threadlist = cache_get('thread_top_list');
	if($threadlist === NULL) {
		$threadlist = thread_top_find();
		cache_set('thread_top_list', $threadlist);
	} else {
		// 重新格式化时间
		foreach($threadlist as &$thread) {
			thread_format_last_date($thread);
		}
	}
	
	return $threadlist;
}

function thread_top_cache_delete() {
	
	global $conf;
	static $deleted = FALSE;
	if($deleted) return;
	cache_delete('thread_top_list');
	$deleted = TRUE;
	
}

function thread_top_update_by_tid($tid, $newfid) {
	
	$r = db_update('thread_top', array('tid'=>$tid), array('fid'=>$newfid));
	
	return $r;
}





?><?php




// ------------> 最原生的 CURD，无关联其他数据。

// 只用传 message, message_fmt 自动生成
function post__create($arr, $gid) {
	
	
	post_message_fmt($arr, $gid);
	
	
	
	$r = db_insert('post', $arr);
	
	return $r;
}

function post__update($pid, $arr) {
	
	$r = db_update('post', array('pid'=>$pid), $arr);
	
	return $r;
}

function post__read($pid) {
	
	$post = db_find_one('post', array('pid'=>$pid));
	
	return $post;
}

function post__delete($pid) {
	
	$r = db_delete('post', array('pid'=>$pid));
	
	return $r;
}

function post__find($cond = array(), $orderby = array(), $page = 1, $pagesize = 20) {
	
	$postlist = db_find('post', $cond, $orderby, $page, $pagesize, 'pid');
	
	return $postlist;
}

// ------------> 关联 CURD，主要是强相关的数据，比如缓存。弱相关的大量数据需要另外处理。

// 回帖
function post_create($arr, $fid, $gid) {
	global $conf, $time;
	
	
	
	$pid = post__create($arr, $gid);
	if(!$pid) return $pid;
	
	$tid = $arr['tid'];
	$uid = $arr['uid'];

	// 回帖
	if($tid > 0) {
		
		// todo: 如果是老帖，不更新 lastpid
		thread__update($tid, array('posts+'=>1, 'lastpid'=>$pid, 'lastuid'=>$uid, 'last_date'=>$time));
		$uid AND user__update($uid, array('posts+'=>1));
	
		runtime_set('posts+', 1);
		runtime_set('todayposts+', 1);
		forum__update($fid, array('todayposts+'=>1));
	}
	
	//post_list_cache_delete($tid);
	
	// 更新板块信息。
	forum_list_cache_delete();
	
	// 关联附件
	$message = $arr['message'];
	attach_assoc_post($pid);
	
	// 更新用户的用户组
	user_update_group($uid);
	
	
	
	return $pid;
}

// 编辑回帖
function post_update($pid, $arr, $tid = 0) {
	global $conf, $user, $gid;

	$post = post__read($pid);
	if(empty($post)) return FALSE;
	$tid = $post['tid'];
	$uid = $post['uid'];
	$isfirst = $post['isfirst'];
	
	

	
	post_message_fmt($arr, $gid);
	
	
	
	$r = post__update($pid, $arr);
	
	attach_assoc_post($pid);
	
	
	return $r;
}

function post_read($pid) {
	
	$post = post__read($pid);
	post_format($post);
	
	return $post;
}

// 从缓存中读取，避免重复从数据库取数据，主要用来前端显示，可能有延迟。重要业务逻辑不要调用此函数，数据可能不准确，因为并没有清理缓存，针对 request 生命周期有效。
function post_read_cache($pid) {
	
	static $cache = array(); // 用静态变量只能在当前 request 生命周期缓存，要跨进程，可以再加一层缓存： memcached/xcache/apc/
	if(isset($cache[$pid])) return $cache[$pid];
	$cache[$pid] = post_read($pid);
	
	return $cache[$pid];
}

// $tid 用来清理缓存
function post_delete($pid) {
	global $conf;
	$post = post_read_cache($pid);
	if(empty($post)) return TRUE; // 已经不存在了。
	
	$tid = $post['tid'];
	$uid = $post['uid'];
	$thread = thread_read_cache($tid);
	$fid = $thread['fid'];
	
	
	
	if(!$post['isfirst']) {
		thread__update($tid, array('posts-'=>1));
		$uid AND user__update($uid, array('posts-'=>1));
		runtime_set('posts-', 1);
	} else {
		//post_list_cache_delete($tid);
	}
	
	($post['images'] || $post['files']) AND attach_delete_by_pid($pid);
	
	$r = post__delete($pid);

	// 更新最后的 lastpid
	if($r && !$post['isfirst'] && $pid == $thread['lastpid']) {
		thread_update_last($tid);
	}
	
	
	return $r;
}

// 此处有可能会超时
function post_delete_by_tid($tid) {
	
	$postlist = post_find_by_tid($tid);
	foreach($postlist as $post) {
		post_delete($post['pid']);
	}
	
	return count($postlist);
}

// 此处有可能会超时，并且导致统计不准确，需要重建统计数
function post_delete_by_uid($uid) {
	
	$r = db_delete('post', array('uid'=>$uid));
	
	return $r;
}

function post_find($cond = array(), $orderby = array(), $page = 1, $pagesize = 20) {
	
	$postlist = post__find($cond, $orderby, $page, $pagesize);
	$floor = 1;
	if($postlist) foreach($postlist as &$post) {
		$post['floor'] = $floor++;
		post_format($post);
	}
	
	return $postlist;
}

// 此处有缓存，是否有必要？
function post_find_by_tid($tid, $page = 1, $pagesize = 50) {
	global $conf;
	
	
	
	$postlist = post__find(array('tid'=>$tid), array('pid'=>1), $page, $pagesize);
	
	if($postlist) {
		$floor = ($page - 1)* $pagesize + 1;
		foreach($postlist as &$post) {
			$post['floor'] = $floor++;
			post_format($post);
		}
	}
	
	
	return $postlist;
}

// <img src="/view/img/face/1.gif"/>
// <blockquote class="blockquote">
function user_post_message_format(&$s) {
	if(xn_strlen($s) < 100) return;
	$s = preg_replace('#<blockquote\s+class="blockquote">.*?</blockquote>#is', '', $s);
	$s = str_ireplace(array('<br>', '<br />', '<br/>', '</p>', '</tr>', '</div>', '</li>', '</dd>'. '</dt>'), "\r\n", $s);
	$s = str_ireplace(array('&nbsp;'), " ", $s);
	$s = strip_tags($s);
	$s = preg_replace('#[\r\n]+#', "\n", $s);
	$s = xn_substr(trim($s), 0, 100);
	$s = str_replace("\n", '<br>', $s);
}


/*
function post_list_cache_delete($tid) {
	
	global $conf;
	$r = cache_delete("postlist_$tid");
	
	return $r;
}*/

// ------------> 其他方法

function post_count($cond = array()) {
	
	$n = db_count('post', $cond);
	
	return $n;
}

function post_maxid() {
	
	$n = db_maxid('post', 'pid');
	
	return $n;
}

function post_safe_info($post) {
	
	unset($post['userip']);
	if(!empty($post['user'])) {
		$post['user'] = user_safe_info($post['user']);
	}
	
	return $post;
}

function post_find_by_pids($pids, $order = array('pid'=>-1)) {
	
	if(!$pids) return array();
	$postlist = db_find('post', array('pid'=>$pids), $order, 1, 1000, 'pid');
	if($postlist) foreach($postlist as &$post) post_format($post);
	
	return $postlist;
}


function post_highlight_keyword($str, $k) {
	
	$r = str_ireplace($k, '<span class="red">'.$k.'</span>', $str);
	
	return $r;
}

// 公用的附件模板，采用函数，效率比 include 高。
function post_file_list_html($filelist, $include_delete = FALSE) {
	if(empty($filelist)) return '';
	
	
	
	$s = '<fieldset class="fieldset">'."\r\n";
	$s .= '<legend>上传的附件：</legend>'."\r\n";
	$s .= '<ul class="attachlist">'."\r\n";
	foreach ($filelist as &$attach) {
		$s .= '<li aid="'.$attach['aid'].'">'."\r\n";
		$s .= '		<a href="'.url("attach-download-$attach[aid]").'" target="_blank">'."\r\n";
		$s .= '			<i class="icon filetype '.$attach['filetype'].'"></i>'."\r\n";
		$s .= '			'.$attach['orgfilename']."\r\n";
		$s .= '		</a>'."\r\n";
		
		$include_delete AND $s .= '		<a href="javascript:void(0)" class="delete ml-3"><i class="icon-remove"></i> '.lang('delete').'</a>'."\r\n";
		
		$s .= '</li>'."\r\n";
	};
	$s .= '</ul>'."\r\n";
	$s .= '</fieldset>'."\r\n";
	
	
	
	return $s;
}

function post_format(&$post) {
	global $conf, $uid, $sid, $gid, $longip;
	if(empty($post)) return;
	$post['create_date_fmt'] = humandate($post['create_date']);
	
	$user = user_read_cache($post['uid']);
	
	
	
	$post['username'] = array_value($user, 'username');
	$post['user_avatar_url'] = array_value($user, 'avatar_url');
	$post['user'] = $user ? $user : user_guest();
	!isset($post['floor']) AND  $post['floor'] = '';
	
	$thread = thread_read_cache($post['tid']);
	
	// 权限判断
	$post['allowupdate'] = ($uid == $post['uid']) || forum_access_mod($thread['fid'], $gid, 'allowupdate');
	$post['allowdelete'] = ($uid == $post['uid']) || forum_access_mod($thread['fid'], $gid, 'allowdelete');
	
	$post['user_url'] = url("user-$post[uid]".($post['uid'] ? '' : "-$post[pid]"));
	
	if($post['files'] > 0) {
		list($attachlist, $imagelist, $filelist) = attach_find_by_pid($post['pid']);
		$post['filelist'] = $filelist;
	} else {
		$post['filelist'] = array();
	}

	$post['classname'] = 'post';
	
	

}

// 写入时格式化
function post_message_fmt(&$arr, $gid) {
	
	

	// 超长内容截取
	$arr['message'] = xn_substr($arr['message'], 0, 2028000);
	
	// 格式转换: 类型，0: html, 1: txt; 2: markdown; 3: ubb
	$arr['message_fmt'] = htmlspecialchars($arr['message']);
	
	// 入库的时候进行转换，编辑的时候，自行调取 message, 或者 message_fmt
	$arr['doctype'] == 0 && $arr['message_fmt'] = ($gid == 1 ? $arr['message'] : xn_html_safe($arr['message']));
	$arr['doctype'] == 1 && $arr['message_fmt'] = xn_txt_to_html($arr['message']);
	
	
	
	// 对引用进行处理
	!empty($arr['quotepid']) && $arr['quotepid'] > 0 && $arr['message_fmt'] = post_quote($arr['quotepid']).$arr['message_fmt'];
}

// 获取内容的简介 0: html, 1: txt; 2: markdown; 3: ubb
function post_brief($s, $len = 100) {
	
	$s = strip_tags($s);
	$s = htmlspecialchars($s);
	$more = xn_strlen($s) > $len ? ' ... ' : '';
	$s = xn_substr($s, 0, $len).$more;
	
	return $s;
}

// 对内容进行引用
function post_quote($quotepid) {
	$quotepost = post__read($quotepid);
	if(empty($quotepost)) return '';
	$uid = $quotepost['uid'];
	$s = $quotepost['message'];
	
	
	
	$s = post_brief($s, 100);
	$userhref = url("user-$uid");
	$user = user_read_cache($uid);
	$r = '<blockquote class="blockquote">
		<a href="'.$userhref.'" class="text-small text-muted user">
			<img class="avatar-1" src="'.$user['avatar_url'].'">
			'.$user['username'].'
		</a>
		'.$s.'
		</blockquote>';
	
	return $r;
}


// 对 $threadlist 权限过滤
function post_list_access_filter(&$postlist, $gid) {
	global $conf, $forumlist;
	if(empty($postlist)) return;
	
	
	
	foreach($postlist as $pid=>$post) {
		$thread = thread__read($post['tid']);
		$fid = $thread['fid'];
		if(empty($forumlist[$fid]['accesson'])) continue;
		if($thread['top'] > 0) continue;
		if(!forum_access_user($fid, $gid, 'allowread')) {
			unset($postlist[$pid]);
		}
	}
	
}




?><?php




// ------------> 最原生的 CURD，无关联其他数据。

function attach__create($arr) {
	
	$r = db_create('attach', $arr);
	
	return $r;
}

function attach__update($aid, $arr) {
	
	$r = db_update('attach', array('aid'=>$aid), $arr);
	
	return $r;
}

function attach__read($aid) {
	
	$attach = db_find_one('attach', array('aid'=>$aid));
	
	return $attach;
}

function attach__delete($aid) {
	
	$r = db_delete('attach', array('aid'=>$aid));
	
	return $r;
}

function attach__find($cond = array(), $orderby = array(), $page = 1, $pagesize = 20) {
	
	$attachlist = db_find('attach', $cond, $orderby, $page, $pagesize);
	
	return $attachlist;
}

// ------------> 关联 CURD，主要是强相关的数据，比如缓存。弱相关的大量数据需要另外处理。

function attach_create($arr) {
	
	$r = attach__create($arr);
	
	return $r;
}

function attach_update($aid, $arr) {
	
	$r = attach__update($aid, $arr);
	
	return $r;
}

function attach_read($aid) {
	
	$attach = attach__read($aid);
	attach_format($attach);
	
	return $attach;
}

function attach_delete($aid) {
	
	global $conf;
	$attach = attach_read($aid);
	$path = $conf['upload_path'].'attach/'.$attach['filename'];
	file_exists($path) AND unlink($path);
	
	$r = attach__delete($aid);
	
	return $r;
}

function attach_delete_by_pid($pid) {
	global $conf;
	list($attachlist, $imagelist, $filelist) = attach_find_by_pid($pid);
	
	foreach($attachlist as $attach) {
		$path = $conf['upload_path'].'attach/'.$attach['filename'];
		file_exists($path) AND unlink($path);
		attach__delete($attach['aid']);
	}
	
	return count($attachlist);
}

function attach_delete_by_uid($uid) {
	global $conf;
	
	$attachlist = db_find('attach', array('uid'=>$uid), array(), 1, 9000);
	foreach ($attachlist as $attach) {
		$path = $conf['upload_path'].'attach/'.$attach['filename'];
		file_exists($path) AND unlink($path);
		attach__delete($attach['aid']);
	}
	
}

function attach_find($cond = array(), $orderby = array(), $page = 1, $pagesize = 20) {
	
	$attachlist = attach__find($cond, $orderby, $page, $pagesize);
	if($attachlist) foreach ($attachlist as &$attach) attach_format($attach);
	
	return $attachlist;
}

// 获取 $filelist $imagelist
function attach_find_by_pid($pid) {
	$attachlist = $imagelist = $filelist = array();
	
	$attachlist = attach__find(array('pid'=>$pid), array(), 1, 1000);
	if($attachlist) {
		foreach ($attachlist as $attach) {
			attach_format($attach);
			$attach['isimage'] ? ($imagelist[] = $attach) : ($filelist[] = $attach);
		}
	}
	
	return array($attachlist, $imagelist, $filelist);
}

// ------------> 其他方法

function attach_format(&$attach) {
	global $conf;
	if(empty($attach)) return;
	
	$attach['create_date_fmt'] = date('Y-n-j', $attach['create_date']);
	$attach['url'] = $conf['upload_url'].'attach/'.$attach['filename'];
	
}

function attach_count($cond = array()) {
	
	$cond = db_cond_to_sqladd($cond);
	$n = db_count('attach', $cond);
	
	return $n;
}

function attach_type($name, $types) {
	
	$ext = file_ext($name);
	foreach($types as $type=>$exts) {
		if($type == 'all') continue;
		if(in_array($ext, $exts)) {
			return $type;
		}
	}
	
	return 'other';
}

// 扫描垃圾的附件，每日清理一次
function attach_gc() {
	global $time, $conf;
	
	$tmpfiles = glob($conf['upload_path'].'tmp/*.*');
	if(is_array($tmpfiles)) {
		foreach($tmpfiles as $file) {
			// 清理超过一天还没处理的临时文件
			if($time - filemtime($file) > 86400) {
				unlink($file);
			}
		}
	}
	
}

// 关联 session 中的临时文件，并不会重新统计 images, files
function attach_assoc_post($pid) {
	global $uid, $time, $conf;
	$sess_tmp_files = _SESSION('tmp_files');
	//if(empty($tmp_files)) return;
	
	$post = post__read($pid);
	if(empty($post)) return;
	
	
	
	$tid = $post['tid'];
	$post['message_old'] = $post['message_fmt'];
	
	// 把临时文件 upload/tmp/xxx.xxx 也处理了
	//preg_match_all('#src="upload/tmp/(\w+\.\w+)"#', $post['message_old'], $m);
	//$use_tmp_files = $m[1]; // 实际使用的临时文件，不用的全部删除？如果是两个帖子一起编辑？
	
	// 将 session 中的数据和 message 中的数据合并。
	//$tmp_files = array_unique(array_merge($sess_tmp_files, $use_tmp_files));
	
	$attach_dir_save_rule = array_value($conf, 'attach_dir_save_rule', 'Ym');
	
	$tmp_files = $sess_tmp_files;
	if($tmp_files) {
		foreach($tmp_files as $file) {
			
			// 将文件移动到 upload/attach 目录
			$filename = file_name($file['url']);
			
			$day = date($attach_dir_save_rule, $time);
			$path = $conf['upload_path'].'attach/'.$day;
			$url = $conf['upload_url'].'attach/'.$day;
			!is_dir($path) AND mkdir($path, 0777, TRUE);
			
			$destfile = $path.'/'.$filename;
			$desturl = $url.'/'.$filename;
			$r = xn_copy($file['path'], $destfile);
			!$r AND xn_log("xn_copy($file[path]), $destfile) failed, pid:$pid, tid:$tid", 'php_error');
			if(is_file($destfile) && filesize($destfile) == filesize($file['path'])) {
				@unlink($file['path']);
			}
			$arr = array(
				'tid'=>$tid,
				'pid'=>$pid,
				'uid'=>$uid,
				'filesize'=>$file['filesize'],
				'width'=>$file['width'],
				'height'=>$file['height'],
				'filename'=>"$day/$filename",
				'orgfilename'=>$file['orgfilename'],
				'filetype'=>$file['filetype'],
				'create_date'=>$time,
				'comment'=>'',
				'downloads'=>0,
				'isimage'=>$file['isimage']
			);
			
			// 插入后，进行关联
			$aid = attach_create($arr);
			$post['message'] = str_replace($file['url'], $desturl, $post['message']);
			$post['message_fmt'] = str_replace($file['url'], $desturl, $post['message_fmt']);
			
		}
	}

	// 清空 session
	$_SESSION['tmp_files'] = array();
	
	$post['message_old'] != $post['message_fmt'] AND post__update($pid, array('message'=>$post['message'], 'message_fmt'=>$post['message_fmt']));
	
	// 处理不在 message 中的图片，删除掉没有插入的图片附件
	/*
	list($attachlist, $imagelist, $filelist) = attach_find_by_pid($pid);
	foreach($imagelist as $k=>$attach) {
		$url = $conf['upload_url'].'attach/'.$attach['filename'];
		if(strpos($post['message_fmt'], $url) === FALSE) {
			unset($imagelist[$k]);
			attach_delete($attach['aid']);
		}
	}
	*/
	
	// 更新 images files
	list($attachlist, $imagelist, $filelist) = attach_find_by_pid($pid);
	$images = count($imagelist);
	$files = count($filelist);
	$post['isfirst'] AND thread__update($tid, array('images'=>$images, 'files'=>$files));
	post__update($pid, array('images'=>$images, 'files'=>$files));
	
	
	
	return TRUE;
}





?><?php




function is_word($s) {
	
	$r = preg_match('#^\\w{1,32}$#', $s, $m);
	return $r;
}

function is_mobile($mobile, &$err) {
	
	if(!preg_match('#^\d{11}$#', $mobile)) {
		$err = lang('mobile_format_mismatch');
		return FALSE;
	}
	
	return TRUE;
}

function is_email($email, &$err) {
	
	$len = mb_strlen($email, 'UTF-8');
	if(strlen($len) > 32) {
		$err = lang('email_too_long', array('length'=>$len));
		return FALSE;
	} elseif(!preg_match('/^[\w\-\.]+@[\w\-\.]+(\.\w+)+$/i', $email)) {
		$err = lang('email_format_mismatch');
		return FALSE;
	}
	
	return TRUE;
}

function is_username($username, &$err = '') {
	
	$len = mb_strlen($username, 'UTF-8');
	if($len > 16) {
		$err = lang('username_too_long', array('length'=>$len));
		return FALSE;
	} elseif(strpos($username, ' ') !== FALSE || strpos($username, '　') !== FALSE) {
		$err = lang('username_cant_include_cn_space');
		return FALSE;
	} elseif(!preg_match('#^[\w\x{4E00}-\x{9FA5}\x{1100}-\x{11FF}\x{3130}-\x{318F}\x{AC00}-\x{D7AF}]+$#u', $username)) {
		// 4E00-9FA5(中文)  1100-11FF(朝鲜文) 3130-318F(朝鲜文兼容字母) AC00-D7AF(朝鲜文音节)
		// 4E00-9FA5(chinese)  1100-11FF(korea) 3130-318F(korea compatiable word) AC00-D7AF(korea)
		$err = lang('username_format_mismatch');
		return FALSE;
	}
	
	return TRUE;
}

function is_password($password, &$err = '') {
	$len = strlen($password);
	
	if($len == 0) {
		$err = lang('password_is_empty');
		return FALSE;
	} elseif($len != 32) {
		$err = lang('password_length_incorrect');
		return FALSE;
	} elseif($password == 'd41d8cd98f00b204e9800998ecf8427e') {
		$err = lang('password_is_empty');
		return FALSE;
	}
	
	return TRUE;
}




?><?php


// ------------> 关联的 CURD，无关联其他数据。



function mythread_create($uid, $tid) {
	
	if($uid == 0) return TRUE; // 匿名发帖
	$thread = mythread_read($uid, $tid);
	if(empty($thread)) {
		$r = db_create('mythread', array('uid'=>$uid, 'tid'=>$tid));
		return $r;
	} else {
		return TRUE;
	}
	
}

function mythread_read($uid, $tid) {
	
	$mythread = db_find_one('mythread', array('uid'=>$uid, 'tid'=>$tid));
	
	return $mythread;
}

function mythread_delete($uid, $tid) {
	
	$r = db_delete('mythread', array('uid'=>$uid, 'tid'=>$tid));
	
	return $r;
}

function mythread_delete_by_uid($uid) {
	
	$r = db_delete('mythread', array('uid'=>$uid));
	
	return $r;
}

function mythread_delete_by_fid($fid) {
	
	$r = db_delete('mythread', array('fid'=>$fid));
	
	return $r;
}

function mythread_delete_by_tid($tid) {
	
	$r = db_delete('mythread', array('tid'=>$tid));
	
	return $r;
}

function mythread_find($cond = array(), $orderby = array(), $page = 1, $pagesize = 20) {
	
	$mythreadlist = db_find('mythread', $cond, $orderby, $page, $pagesize);
	
	return $mythreadlist;
}

function mythread_find_by_uid($uid, $page = 1, $pagesize = 20) {
	
	$mythreadlist = mythread_find(array('uid'=>$uid), array('tid'=>-1), $page, $pagesize);
	if(empty($mythreadlist)) return array();
	$threadlist = array();
	foreach ($mythreadlist as &$mythread) {
		$threadlist[$mythread['tid']] = thread_read($mythread['tid']);
	}
	
	return $threadlist;
}




?><?php




function runtime_init() {
	
	global $conf;
	$runtime = cache_get('runtime'); // 实时运行的数据，初始化！
	if($runtime === NULL || !isset($runtime['users'])) {
		$runtime = array();
		$runtime['users'] = user_count();
		$runtime['posts'] = post_count();
		$runtime['threads'] = thread_count();
		$runtime['posts'] -= $runtime['threads']; // 减去首帖
		$runtime['todayusers'] = 0;
		$runtime['todayposts'] = 0;
		$runtime['todaythreads'] = 0;
		$runtime['onlines'] = max(1, online_count());
		$runtime['cron_1_last_date'] = 0;
		$runtime['cron_2_last_date'] = 0;
		
		cache_set('runtime', $runtime);
		
	}
	
	return $runtime;
}

function runtime_get($k) {
	
	global $runtime;
	
	return array_value($runtime, $k, NULL);
}

function runtime_set($k, $v) {
	
	global $conf, $runtime;
	$op = substr($k, -1);
	if($op == '+' || $op == '-') {
		$k = substr($k, 0, -1);
		!isset($runtime[$k]) AND $runtime[$k] = 0;
		$v = $op == '+' ? ($runtime[$k] + $v) : ($runtime[$k] - $v);
	}
	
	$runtime[$k] = $v;
	return TRUE;
	
}

function runtime_delete($k) {
	
	global $conf, $runtime;
	unset($runtime[$k]);
	runtime_save();
	return TRUE;
	
}

function runtime_save() {
	
	global $runtime;
	
	function_exists('chdir') AND chdir(APP_PATH);
	
	$r = cache_set('runtime', $runtime);
	
	
}

function runtime_truncate() {
	
	global $conf;
	cache_delete('runtime');
	
}

register_shutdown_function('runtime_save');




?><?php




// -------------> 用来统计表每天的最大ID，有利于加速！
function table_day_read($table, $year, $month, $day) {
	
	$arr = db_find_one('table_day', array('year'=>$year, 'month'=>$month, 'day'=>$day, 'table'=>$table));
	
	return $arr;
}

/*
	// 支持两种日期格式：年-月-日 or UNIXTIMESTAMP
	$maxtid = table_day_maxid('thread', '2014-9-1');
	$maxtid = table_day_maxid('thread', 1234567890);

*/
function table_day_maxid($table, $date) {
	

	// 不能小于 2014-9-24，不能大于等于当前时间
	$mintime = 1411516800; // strtotime('2014-9-24');
	!is_numeric($date) AND $date = strtotime($date);
	if($date < $mintime) return 0;

	list($year, $month, $day) = explode('-', date('Y-n-j', $date));
	$arr = table_day_read($table, $year, $month, $day);
	
	return $arr ? intval($arr['maxid']) : 0;
}

/*
	每天0点0分执行一次！最好 linux crontab 计划任务执行，web 触发的不准确
	统计常用表的最大ID，用来削减日期类的索引，和加速查询。
*/
function table_day_cron($crontime = 0) {
	
	global $time;
	$crontime = $crontime ? $crontime : $time;
	list($y, $m, $d) = explode('-', date('Y-n-j', $crontime)); // 往前推8个小时，确保在前一天。
	
	$table_map = array(
		'thread'=>'tid',
		'post'=>'pid',
		'user'=>'uid',
	);
	foreach ($table_map as $table=>$col) {
		$maxid = db_maxid($table, $col, array('create_date'=>array('<'=>$crontime)));
		$count = db_count($table, array('create_date'=>array('<'=>$crontime)));
		$arr = array(
			'year'=>$y,
			'month'=>$m, 
			'day'=>$d, 
			'create_date'=>$crontime, 
			'table'=>$table, 
			'maxid'=>$maxid, 
			'count'=>$count
		);
		db_replace('table_day', $arr);
	}
	
}

// 重新生成数据
function table_day_rebuild() {
	
	global $time;
	$user = user__read(1);
	$crontime = $user['create_date'];
	while($crontime < $time) {
		table_day_cron($crontime);
		$crontime = $crontime + 86400;
	}
	
}




?><?php




// 计划任务
function cron_run($force = 0) {
	
	global $conf, $time, $forumlist, $runtime;
	$cron_1_last_date = runtime_get('cron_1_last_date');
	$cron_2_last_date = runtime_get('cron_2_last_date');
	
	$t = $time - $cron_1_last_date;
	
	// 每隔 5 分钟执行一次的计划任务
	if($t > 300 || $force) {
		$lock = cache_get('cron_lock_1');
		if($lock === NULL) {
			cache_set('cron_lock_1', 1, 10); // 设置 10 秒超时
			
			sess_gc($conf['online_hold_time']);
			
			$runtime['onlines'] = max(1, online_count());
			
			runtime_set('cron_1_last_date', $time);
			
			
			
			cache_delete('cron_lock_1');
		}
	}
	
	// 每日 0 点执行一次的计划任务
	$t = $time - $cron_2_last_date;
	if($t > 86400 || $force) {
		
		$lock = cache_get('cron_lock_2'); // 高并发下, mysql 机制实现的锁锁不住，但是没关系
		if($lock === NULL) {
			cache_set('cron_lock_2', 1, 10); // 设置 10 秒超时
			
			// 每日统计清 0
			runtime_set('todayposts', 0);
			runtime_set('todaythreads', 0);
			runtime_set('todayusers', 0);
			
			foreach($forumlist as $fid=>$forum) {
				forum__update($fid, array('todayposts'=>0, 'todaythreads'=>0));
			}
			forum_list_cache_delete();
			
			// 清理临时附件
			attach_gc();
			
			// 清理过期的队列数据
			queue_gc();
			
			list($y, $n, $d) = explode(' ', date('Y n j', $time)); 	// 0 点
			$today = mktime(0, 0, 0, $n, $d, $y);			// -8 hours
			runtime_set('cron_2_last_date', $today, TRUE);		// 加到1天后
			
			// 往前推8个小时，尽量保证在前一天
			// 如果是升级过来和采集的数据，这里会很卡。
			// table_day_cron($time - 8 * 3600);
			
			
			
			cache_delete('cron_lock_2');
		}
		
	}
	
}






?><?php


/*
* Copyright (C) 2015 xiuno.com
*/

function form_radio_yes_no($name, $checked = 0) {
	$checked = intval($checked);
	return form_radio($name, array(1=>lang('yes'), 0=>lang('no')), $checked);
}

function form_radio($name, $arr, $checked = 0) {
	empty($arr) && $arr = array(lang('no'), lang('yes'));
	$s = '';

	foreach((array)$arr as $k=>$v) {
		$add = $k == $checked ? ' checked="checked"' : '';
		$s .= "<label class=\"form-input form-radio\"><input type=\"radio\" name=\"$name\" value=\"$k\"$add /> $v</label> &nbsp; \r\n";
	}
	return $s;
}

function form_checkbox($name, $checked = 0, $txt = '') {
	$add = $checked ? ' checked="checked"' : '';
	$s = "<label class=\"form-input form-checkbox mr-4\"><input type=\"checkbox\" name=\"$name\" value=\"1\" $add /> $txt</label>";
	return $s;
}

/*
	form_multi_checkbox('cateid[]', array('value1'=>'text1', 'value2'=>'text2', 'value3'=>'text3'), array('value1', 'value2'));
*/
function form_multi_checkbox($name, $arr, $checked = array()) {
	$s = '';
	foreach($arr as $value=>$text) {
		$ischecked = in_array($value, $checked);
		$s .= form_checkbox($name, $ischecked, $text);
	}
	return $s;
}

function form_select($name, $arr, $checked = 0, $id = TRUE) {
	if(empty($arr)) return '';
	$idadd = $id === TRUE ? "id=\"$name\"" : ($id ? "id=\"$id\"" : '');
	$s = "<select name=\"$name\" class=\"form-select\" $idadd> \r\n";
	$s .= form_options($arr, $checked);
	$s .= "</select> \r\n";
	return $s;
}

function form_options($arr, $checked = 0) {
	$s = '';
	foreach((array)$arr as $k=>$v) {
		$add = $k == $checked ? ' selected="selected"' : '';
		$s .= "<option value=\"$k\"$add>$v</option> \r\n";
	}
	return $s;
}

function form_text($name, $value, $width = FALSE, $holdplacer = '') {
	$style = '';
	if($width !== FALSE) {
		is_numeric($width) AND $width .= 'px';
		$style = " style=\"width: $width\"";
	}
	$s = "<input type=\"text\" name=\"$name\" id=\"$name\" placeholder=\"$holdplacer\" value=\"$value\" class=\"form-control\"$style />";
	return $s;
}

function form_hidden($name, $value) {
	$s = "<input type=\"hidden\" name=\"$name\" id=\"$name\" value=\"$value\" />";
	return $s;
}

function form_textarea($name, $value, $width = FALSE,  $height = FALSE) {
	$style = '';
	if($width !== FALSE) {
		is_numeric($width) AND $width .= 'px';
		is_numeric($height) AND $height .= 'px';
		$style = " style=\"width: $width; height: $height; \"";
	}
	$s = "<textarea name=\"$name\" id=\"$name\" class=\"form-control\" $style>$value</textarea>";
	return $s;
}

function form_password($name, $value, $width = FALSE) {
	$style = '';
	if($width !== FALSE) {
		is_numeric($width) AND $width .= 'px';
		$style = " style=\"width: $width\"";
	}
	$s = "<input type=\"password\" name=\"$name\" id=\"$name\" class=\"form-control\" value=\"$value\" $style />";
	return $s;
}

function form_time($name, $value, $width = FALSE) {
	$style = '';
	if($width !== FALSE) {
		is_numeric($width) AND $width .= 'px';
		$style = " style=\"width: $width\"";
	}
	$s = "<input type=\"text\" name=\"$name\" id=\"$name\" class=\"form-control\" value=\"$value\" $style />";
	return $s;
}



/**用法

echo form_radio_yes_no('radio1', 0);
echo form_checkbox('aaa', array('无', '有'), 0);

echo form_radio_yes_no('aaa', 0);
echo form_radio('aaa', array('无', '有'), 0);
echo form_radio('aaa', array('a'=>'aaa', 'b'=>'bbb', 'c'=>'ccc', ), 'b');

echo form_select('aaa', array('a'=>'aaa', 'b'=>'bbb', 'c'=>'ccc', ), 'a');

*/


?><?php





/*
	url("thread-create-1.htm");
	根据 $conf['url_rewrite_on'] 设置，返回以下四种格式：
	?thread-create-1.htm
	thread-create-1.htm
	?/thread/create/1
	/thread/create/1
*/
function url($url, $extra = array()) {
	$conf = _SERVER('conf');
	!isset($conf['url_rewrite_on']) AND $conf['url_rewrite_on'] = 0;
	
	
	
	$r = $path = $query = '';
	if(strpos($url, '/') !== FALSE) {
		$path = substr($url, 0, strrpos($url, '/') + 1);
		$query = substr($url, strrpos($url, '/') + 1);
	} else {
		$path = '';
		$query = $url;
	}
	
	if($conf['url_rewrite_on'] == 0) {
		$r = $path . '?' . $query . '.htm';
	} elseif($conf['url_rewrite_on'] == 1) {
		$r = $path . $query . '.htm';
	} elseif($conf['url_rewrite_on'] == 2) {
		$r = $path . '?' . str_replace('-', '/', $query);
	} elseif($conf['url_rewrite_on'] == 3) {
		$r = $path . str_replace('-', '/', $query);
	}
	// 附加参数
	if($extra) {
		$args = http_build_query($extra);
		$sep = strpos($r, '?') === FALSE ? '?' : '&';
		$r .= $sep.$args;
	}
	
	
	
	return $r;
}


// 检测站点的运行级别
function check_runlevel() {
	global $conf, $method, $gid;
	
	
	if($gid == 1) return;
	$param0 = param(0);
	$param1 = param(1);
	if($param0 == 'user' && in_array($param1, array('login', 'create', 'logout', 'sendinitpw', 'resetpw', 'resetpw_sendcode', 'resetpw_complete', 'synlogin'))) return;
	switch ($conf['runlevel']) {
		case 0: message(-1, $conf['runlevel_reason']); break;
		case 1: message(-1, lang('runlevel_reson_1')); break;
		case 2: ($gid == 0 || $method != 'GET') AND message(-1, lang('runlevel_reson_2')); break;
		case 3: $gid == 0 AND message(-1, lang('runlevel_reson_3')); break;
		case 4: $method != 'GET' AND message(-1, lang('runlevel_reson_4')); break;
		//case 5: break;
	}
	
}

/*
	message(0, '登录成功');
	message(1, '密码错误');
	message(-1, '数据库连接失败');
	
	code:
		< 0 全局错误，比如：系统错误：数据库丢失连接/文件不可读写
		= 0 正确
		> 0 一般业务逻辑错误，可以定位到具体控件，比如：用户名为空/密码为空
*/
function message($code, $message, $extra = array()) {
	global $ajax, $header, $conf;
	
	$arr = $extra;
	$arr['code'] = $code.'';
	$arr['message'] = $message;
	$header['title'] = $conf['sitename'];
	
	
	
	// 防止 message 本身出现错误死循环
	static $called = FALSE;
	$called ? exit(xn_json_encode($arr)) : $called = TRUE;
	if($ajax) {
		echo xn_json_encode($arr);
	} else {
		if(IN_CMD) {
			if(is_array($message) || is_object($message)) {
				print_r($message);
			} else {
				echo $message;
			}
			exit;
		} else {
			if(defined('MESSAGE_HTM_PATH')) {
				include _include(MESSAGE_HTM_PATH);
			} else {
				include _include(APP_PATH."view/htm/message.htm");
			}
		}
	}
	
	exit;
}

// 上锁
function xn_lock_start($lockname = '', $life = 10) {
	global $conf, $time;
	$lockfile = $conf['tmp_path'].'lock_'.$lockname.'.lock';
	if(is_file($lockfile)) {
		// 大于 $life 秒，删除锁
		if($time - filemtime($lockfile) > $life) {
			xn_unlink($lockfile);
		} else {
			// 锁存在，上锁失败。
			return FALSE;
		}
	}
	
	$r = file_put_contents($lockfile, $time, LOCK_EX);
	return $r;
}

// 删除锁
function xn_lock_end($lockname = '') {
	global $conf, $time;
	$lockfile = $conf['tmp_path'].'lock_'.$lockname.'.lock';
	xn_unlink($lockfile);
}


// class xn_html_safe 由 axiuno@gmail.com 编写

include_once XIUNOPHP_PATH.'xn_html_safe.func.php';

function xn_html_safe($doc, $arg = array()) {
	
	
	
	empty($arg['table_max_width']) AND $arg['table_max_width'] = 746; // 这个宽度为 bbs 回帖宽度
	
	$pattern = array (
		//'img_url'=>'#^(https?://[^\'"\\\\<>:\s]+(:\d+)?)?([^\'"\\\\<>:\s]+?)*$#is',
		'img_url'=>'#^(((https?://[^\'"\\\\<>:\s]+(:\d+)?)?([^\'"\\\\<>:\s]+?)*)|(data:image/png;base64,[\w\/+]+))$#is',
		'url'=>'#^(https?://[^\'"\\\\<>:\s]+(:\d+)?)?([^\'"\\\\<>:\s]+?)*$#is', // '#https?://[\w\-/%?.=]+#is'
		'mailto'=>'#^mailto:([\w%\-\.]+)@([\w%\-\.]+)(\.[\w%\-\.]+?)+$#is',
		'ftp_url'=>'#^ftp:([\w%\-\.]+)@([\w%\-\.]+)(\.[\w%\-\.]+?)+$#is',
		'ed2k_url'=>'#^(?:ed2k|thunder|qvod|magnet)://[^\s\'\"\\\\<>]+$#is',
		'color'=>'#^(\#\w{3,6})|(rgb\(\d+,\s*\d+,\s*\d+\)|(\w{3,10}))$#is',
		'safe'=>'#^[\w\-:;\.\s\x7f-\xff]+$#is',
		'css'=>'#^[\(,\)\#;\w\-\.\s\x7f-\xff]+$#is',
		'word'=>'#^[\w\-\x7f-\xff]+$#is',
	);

	$white_tag = array('a', 'b', 'i', 'u', 'font', 'strong', 'em', 'span',
		'table', 'tr', 'td', 'th', 'tbody', 'thead', 'tfoot','caption',
		'ol', 'ul', 'li', 'dl', 'dt', 'dd', 'menu', 'multicol',
		'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'p', 'div', 'pre',
		'br', 'img', 'area',  'embed', 'code', 'blockquote', 'iframe', 'section', 'fieldset', 'legend'
	);
	$white_value = array(
		'href'=>array('pcre', '', array($pattern['url'], $pattern['ed2k_url'])),
		'src'=>array('pcre', '', array($pattern['img_url'])),
		'width'=>array('range', '', array(0, 4096)),
		'height'=>array('range', 'auto', array(0, 80000)),
		'size'=>array('range', 4, array(-10, 10)),
		'border'=>array('range', 0, array(0, 10)),
		'family'=>array('pcre', '', array($pattern['word'])),
		'class'=>array('pcre', '', array($pattern['safe'])),
		'face'=>array('pcre', '', array($pattern['word'])),
		'color'=>array('pcre', '', array($pattern['color'])),
		'alt'=>array('pcre', '', array($pattern['safe'])),
		'label'=>array('pcre', '', array($pattern['safe'])),
		'title'=>array('pcre', '', array($pattern['safe'])),
		'target'=>array('list', '_self', array('_blank', '_self')),
		'type'=>array('pcre', '', array('#^[\w/\-]+$#')),
		'allowfullscreen'=>array('list', 'true', array('true', '1', 'on')),
		'wmode'=>array('list', 'transparent', array('transparent', '')),
		'allowscriptaccess'=>array('list', 'never', array('never')),
		'value'=>array('list', '', array('#^[\w+/\-]$#')),
		'cellspacing'=>array('range', 0, array(0, 10)),
		'cellpadding'=>array('range', 0, array(0, 10)),
		'frameborder'=>array('range', 0, array(0, 10)),
		'allowfullscreen'=>array('range', 0, array(0, 10)),
		'align'=>array('list', 'left', array('left', 'center', 'right')),
		'valign'=>array('list', 'middle', array('middle', 'top', 'bottom')),
        'name'=>array('pcre', '', array($pattern['word'])),
	);
	$white_css = array(
		'font'=>array('pcre', 'none', array($pattern['safe'])),
		'font-style'=>array('pcre', 'none', array($pattern['safe'])),
		'font-weight'=>array('pcre', 'none', array($pattern['safe'])),
		'font-family'=>array('pcre', 'none', array($pattern['word'])),
		'font-size'=>array('range', 12, array(6, 48)),
		'width'=>array('range', '100%', array(1, 1800)),
		'height'=>array('range', '', array(1, 80000)),
		'min-width'=>array('range', 1, array(1, 80000)),
		'min-height'=>array('range', 400, array(1, 80000)),
		'max-width'=>array('range', 1800, array(1, 80000)),
		'max-height'=>array('range', 80000, array(1, 80000)),
		'line-height'=>array('range', '14px', array(1, 50)),
		'color'=>array('pcre', '#000000', array($pattern['color'])),
		'background'=>array('pcre', 'none', array($pattern['color'], '#url\((https?://[^\'"\\\\<>]+?:?\d?)?([^\'"\\\\<>:]+?)*\)[\w\s\-]*$#')),
		'background-color'=>array('pcre', 'none', array($pattern['color'])),
		'background-image'=>array('pcre', 'none', array($pattern['img_url'])),
		'background-position'=>array('pcre', 'none', array($pattern['safe'])),
		'border'=>array('pcre', 'none', array($pattern['css'])),
		'border-left'=>array('pcre', 'none', array($pattern['css'])),
		'border-right'=>array('pcre', 'none', array($pattern['css'])),
		'border-top'=>array('pcre', 'none', array($pattern['css'])),
		'border-left-color'=>array('pcre', 'none', array($pattern['css'])),
		'border-right-color'=>array('pcre', 'none', array($pattern['css'])),
		'border-top-color'=>array('pcre', 'none', array($pattern['css'])),
		'border-bottom-color'=>array('pcre', 'none', array($pattern['css'])),
		'border-left-width'=>array('pcre', 'none', array($pattern['css'])),
		'border-right-width'=>array('pcre', 'none', array($pattern['css'])),
		'border-top-width'=>array('pcre', 'none', array($pattern['css'])),
		'border-bottom-width'=>array('pcre', 'none', array($pattern['css'])),
		'border-bottom-style'=>array('pcre', 'none', array($pattern['css'])),
		'margin-left'=>array('range', 0, array(0, 100)),
		'margin-right'=>array('range', 0, array(0, 100)),
		'margin-top'=>array('range', 0, array(0, 100)),
		'margin-bottom'=>array('range', 0, array(0, 100)),
		'margin'=>array('pcre', '', array($pattern['safe'])),
		'padding'=>array('pcre', '', array($pattern['safe'])),
		'padding-left'=>array('range', 0, array(0, 100)),
		'padding-right'=>array('range', 0, array(0, 100)),
		'padding-top'=>array('range', 0, array(0, 100)),
		'padding-bottom'=>array('range', 0, array(0, 100)),
		'zoom'=>array('range', 1, array(1, 10)),
		'list-style'=>array('list', 'none', array('disc', 'circle', 'square', 'decimal', 'lower-roman', 'upper-roman', 'none')),
		'text-align'=>array('list', 'left', array('left', 'right', 'center', 'justify')),
		'text-indent'=>array('range', 0, array(0, 100)),
		
		// 代码高亮需要支持，但是不安全！
		/*
		'position'=>array('list', 'static', array('absolute', 'fixed', 'relative', 'static')),
		'left'=>array('range', 0, array(0, 1000)),
		'top'=>array('range', 0, array(0, 1000)),
		'white-space'=>array('list', 'nowrap', array('nowrap', 'pre')),
		'word-wrap'=>array('list', 'normal', array('break-word', 'normal')),
		'word-break'=>array('list', 'break-all', array('break-all', 'normal')),
		'display'=>array('list', 'block', array('block', 'table', 'none', 'inline-block', 'table-cell')),
		'overflow'=>array('list', 'auto', array('scroll', 'hidden', 'auto')),
		'overflow-x'=>array('list', 'auto', array('scroll', 'hidden', 'auto')),
		'overflow-y'=>array('list', 'auto', array('scroll', 'hidden', 'auto')),
		*/
		
	);
	
	
	$safehtml = new HTML_White($white_tag, $white_value, $white_css, $arg);
	
	
	$result = $safehtml->parse($doc);
	
	
	
	return $result;
}




?><?php
 

/*
	php 默认的 session 采用文件存储，并且使用 flock() 文件锁避免并发访问不出问题（实际上还是无法解决业务层的并发读后再写入）
	自定义的 session 采用数据表来存储，同样无法解决业务层并发请求问题。
	xiuno.js $.each_sync() 串行化并发请求，可以避免客户端并发访问导致的 session 写入问题。
*/

$sid = '';
$g_session = array();	
$g_session_invalid = FALSE; // 0: 有效， 1：无效

// 可以指定独立的 session 服务器，在系统压力巨大的时候可以考虑优化
//$g_sess_db = $db;

// 如果是管理员, sid, 与 ip 绑定，一旦 IP 发生变化，则需要重新登录。管理员采用 token (绑定IP) 双重验证，避免 sid 被中间窃取。

function sess_open($save_path, $session_name) { 
	//echo "sess_open($save_path,$session_name) \r\n";
	return true;
}

// 关闭句柄，清理资源，这里 $sid 已经为空，
function sess_close() {
	return true;
}

// 如果 cookie 中没有 bbs_sid, php 会自动生成 sid，作为参数
function sess_read($sid) { 
	global $g_session, $longip, $time;
	//echo "sess_read() sid: $sid <br>\r\n";
	if(empty($sid)) {
		// 查找刚才是不是已经插入一条了？  如果相隔时间特别短，并且 data 为空，则删除。
		// 测试是否支持 cookie，如果不支持 cookie，则不生成 sid
		$sid = session_id();
		sess_new($sid);
		return '';
	}
	$arr = db_find_one('session', array('sid'=>$sid));
	if(empty($arr)) {
		sess_new($sid);
		return '';
	}
	if($arr['bigdata'] == 1) {
		$arr2 = db_find_one('session_data', array('sid'=>$sid));
		$arr['data'] = $arr2['data'];
	}
	$g_session = $arr;
	// 在 php 5.6.29 版本，需要返回 session_decode()
	//return $arr ? session_decode($arr['data']) : '';
	return $arr ? $arr['data'] : '';
}

function sess_new($sid) {
	global $time, $longip, $conf, $g_session, $g_session_invalid;
	
	$agent = _SERVER('HTTP_USER_AGENT');
	
	// 干掉同 ip 的 sid，仅仅在遭受攻击的时候
	//db_delete('session', array('ip'=>$longip));
	
	$cookie_test = _COOKIE('cookie_test');
	if($cookie_test) {
		$cookie_test_decode = xn_decrypt($cookie_test, $conf['auth_key']);
		$g_session_invalid = ($cookie_test_decode != md5($agent.$longip));
		setcookie('cookie_test', '', $time - 86400, '');
	} else {
		$cookie_test = xn_encrypt(md5($agent.$longip), $conf['auth_key']);
		setcookie('cookie_test', $cookie_test, $time + 86400, '');
		$g_session_invalid = FALSE;
		return;
	}
	
	// 可能会暴涨
	$url = _SERVER('REQUEST_URI_NO_PATH');
	
	$arr = array(
		'sid'=>$sid,
		'uid'=>0,
		'fid'=>0,
		'url'=>$url,
		'last_date'=>$time,
		'data'=> '',
		'ip'=> $longip,
		'useragent'=> $agent,
		'bigdata'=> 0,
	);
	$g_session = $arr;
	db_insert('session', $arr);
}

// 重新启动 session，降低并发写入数据的问题，这回抛弃前面的 _SESSION 数据
function sess_restart() {
	global $sid;
	$data = sess_read($sid);
	session_decode($data); // 直接存入了 $_SESSION
}

// 将当前的 _SESSION 变量保存
function sess_save() {
	global $sid;
	sess_write($sid, TRUE);
}

// 模拟加锁，如果发现写入的时候数据已经发生改变，则读取后，合并数据，重新写入（合并总比删除安全一点）。
function sess_write($sid, $data) {
	global $g_session, $time, $longip, $g_session_invalid, $conf;
	
	//echo "sess_write($sid, $data)";
	//if($g_session_invalid) return TRUE;
	
	$uid = _SESSION('uid');
	$fid = _SESSION('fid');
	unset($_SESSION['uid']);
	unset($_SESSION['fid']);
	
	if($data) {
		//$arr = session_decode($data);
		//unset($_SESSION['uid']);
		//unset($_SESSION['fid']);
		$data = session_encode();
	}
	
	function_exists('chdir') AND chdir(APP_PATH);
	
	$url = _SERVER('REQUEST_URI_NO_PATH');
	$agent = _SERVER('HTTP_USER_AGENT');
	$arr = array(
		'uid'=>$uid,
		'fid'=>$fid,
		'url'=>$url,
		'last_date'=>$time,
		'data'=> $data,
		'ip'=> $longip,
		'useragent'=> $agent,
		'bigdata'=> 0,
	);
	
	// 开启 session 延迟更新，减轻压力，会导致不重要的数据(useragent,url)显示有些延迟，单位为秒。
	$session_delay_update_on = !empty($conf['session_delay_update']) && $time - $g_session['last_date'] < $conf['session_delay_update'];
	if($session_delay_update_on) {
		unset($arr['fid']);
		unset($arr['url']);
		unset($arr['last_date']);
	}
	
	// 判断数据是否超长
	$len = strlen($data);
	if($len > 255 && $g_session['bigdata'] == 0) {
		db_insert('session_data', array('sid'=>$sid));
	}
	if($len <= 255) {
		$update = array_diff_value($arr, $g_session);
		db_update('session', array('sid'=>$sid), $update);
		if(!empty($g_session) && $g_session['bigdata'] == 1) {
			db_delete('session_data', array('sid'=>$sid));
		}
	} else {
		$arr['data'] = '';
		$arr['bigdata'] = 1;
		$update = array_diff_value($arr, $g_session);
		$update AND db_update('session', array('sid'=>$sid), $update);
		$arr2 = array('data'=>$data, 'last_date'=>$time);
		if($session_delay_update_on) unset($arr2['last_date']);
		$update2 = array_diff_value($arr2, $g_session);
		$update2 AND db_update('session_data', array('sid'=>$sid), $update2);
	}
	return TRUE;
}

function sess_destroy($sid) { 
	//echo "sess_destroy($sid) \r\n";
	db_delete('session', array('sid'=>$sid));
	db_delete('session_data', array('sid'=>$sid));
	return TRUE; 
}

function sess_gc($maxlifetime) {
	global $time;
	// echo "sess_gc($maxlifetime) \r\n";
	$expiry = $time - $maxlifetime;
	db_delete('session', array('last_date'=>array('<'=>$expiry)));
	db_delete('session_data', array('last_date'=>array('<'=>$expiry)));
	return TRUE; 
}

function sess_start() {
	global $conf, $sid, $g_session;
	ini_set('session.name', 'bbs_sid');
	
	ini_set('session.use_cookies', 'On');
	ini_set('session.use_only_cookies', 'On');
	ini_set('session.cookie_domain', '');
	ini_set('session.cookie_path', '');	// 为空则表示当前目录和子目录
	ini_set('session.cookie_secure', 'Off'); // 打开后，只有通过 https 才有效。
	ini_set('session.cookie_lifetime', 86400);
	ini_set('session.cookie_httponly', 'On'); // 打开后 js 获取不到 HTTP 设置的 cookie, 有效防止 XSS，这个对于安全很重要，除非有 BUG，否则不要关闭。
	
	ini_set('session.gc_maxlifetime', $conf['online_hold_time']);	// 活动时间 $conf['online_hold_time']
	ini_set('session.gc_probability', 1); 	// 垃圾回收概率 = gc_probability/gc_divisor
	ini_set('session.gc_divisor', 500); 	// 垃圾回收时间 5 秒，在线人数 * 10 
	
	session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc'); 
	
	// register_shutdown_function 会丢失当前目录，需要 chdir(APP_PATH)
	
	// 这个比须有，否则 ZEND 会提前释放 $db 资源
	register_shutdown_function('session_write_close');
	
	session_start();
	
	$sid = session_id();
	
	//$_SESSION['uid'] = $g_session['uid'];
	//$_SESSION['fid'] = $g_session['fid'];
	
	//echo "sess_start() sid: $sid <br>\r\n";
	//print_r(db_find('session'));
	return $sid;
}

function online_count() {
	return db_count('session');
}

function online_find_cache() {
	return db_find('session');
}

function online_list_cache() {
	$onlinelist = cache_get('online_list');
	if($onlinelist === NULL) {
		$onlinelist = db_find('session', array('uid'=>array('>'=>0)), array('last_date'=>-1), 1, 500);
		foreach($onlinelist as &$online) {
			$user = user_read_cache($online['uid']);
			$online['username'] = $user['username'];
			$online['gid'] = $user['gid'];
			$online['ip_fmt'] = long2ip($online['ip']);
			$online['last_date_fmt'] = date('Y-n-j H:i', $online['last_date']);
		}
		cache_set('online_list', $onlinelist, 300);
	}
	return $onlinelist;
}


?>