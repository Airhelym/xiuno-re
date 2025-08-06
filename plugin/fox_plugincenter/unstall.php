<?php
/*
 * 奇狐插件中心 卸载文件
 * QQ:77798085
 */

!defined('DEBUG') AND exit('Forbidden');

$modelfilepath = APP_PATH . 'model/plugin.func.php';
$modelbackname = file_backname($modelfilepath);

// 还原备份
if(is_file($modelbackname)){
    $r = file_backup_restore($modelfilepath);
    $r == FALSE AND message(-1, '还原model文件失败');
}

//删除缓存的json文件
$json_path = APP_PATH.'log/plugin-all-4.json';
xn_unlink($json_path);

//清空缓存
cache_truncate();
$runtime = NULL;
rmdir_recusive($conf['tmp_path'], 1);
?>