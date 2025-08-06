<?php
/*
 * 奇狐插件 升级文件
 * QQ:77798085
 */

!defined('DEBUG') AND exit('Forbidden');

// 1.0版本升级 | 删除旧版废弃的文件
$path = APP_PATH.'plugin/fox_plugincenter/hook/';
rmdir_recusive($path, 0);

// 1.02版本升级 | 替换plugin.func.php
$modelfilepath = APP_PATH . 'model/plugin.func.php';
$fox_modelfilepath = APP_PATH . 'plugin/fox_plugincenter/model/plugin.func.php';
$r = xn_copy($fox_modelfilepath, $modelfilepath);
$r == FALSE AND message(-1, '替换model文件失败');

//清空缓存
cache_truncate();
$runtime = NULL;
rmdir_recusive($conf['tmp_path'], 1);
?>