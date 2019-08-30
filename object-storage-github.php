<?php

/**
 * Plugin Name: 云存储（Github）
 * Plugin URI: https://github.com/ixiumu/object-storage-github
 * Description: 上传本地附件到云存储空间
 * Author: 朽木
 * Author URI: http://www.xiumu.org/
 * Text Domain: 云存储 Github
 * Version: 0.0.2
 * License: GPLv2
*/

// 七牛云存储 SDK
require_once dirname(__FILE__) . '/class.githubsdk.php';
require_once dirname(__FILE__) . '/vendor/autoload.php';

function add_pages() {
    add_submenu_page('options-general.php', "云存储", "云存储", 'manage_options', basename(__FILE__), 'option_page');
}

function option_page() {

    $messages = array();

    if(isset($_POST['resync']) && $_POST['resync']) {

        $files = storage_resync();

        if (count($files) == 0) {
            $messages[] = "没有需要同步的文件。";
        }else{
            $messages[] = "同步结果：";
        }
        foreach($files as $file => $stat) {
            if($stat === true) {
                $messages[] = "$file 上传成功。";
            } else if($stat === false) {
                $messages[] = "$file 上传失败。";
            } else {
                $messages[] = "$file 跳过。";
            }
        }
    }

    require dirname(__FILE__) . "/tpl/setting.php";
}


function storage_options()
{
    // 基础设置
    register_setting('storage-options', 'storage-owner', 'strval');
    register_setting('storage-options', 'storage-repo', 'strval');
    register_setting('storage-options', 'storage-branch', array('type' => 'strval', 'default' => 'master'));
    register_setting('storage-options', 'storage-token', 'strval');
    
    // 拓展名
    register_setting('storage-options', 'storage-extensions', array('type' => 'strval', 'default' => '*'));

    // CDN域名
    register_setting('storage-options', 'storage-baseurl', 'strval');

    // 同步
    register_setting('storage-options', 'storage-delobject', array('type' => 'boolval', 'default' => 1));

    // register_setting('storage-resync', 'storage-resync', 'intval');
}

// 测试连接
function storage_connect_test()
{
    $owner = '';
    if(isset($_POST['storage-owner'])) {
        $owner = sanitize_text_field($_POST['storage-owner']);
    }

    $repo = '';
    if(isset($_POST['storage-repo'])) {
        $repo = sanitize_text_field($_POST['storage-repo']);
    }

    $branch = '';
    if(isset($_POST['storage-branch'])) {
        $branch = sanitize_text_field($_POST['storage-branch']);
    }

    $token = '';
    if(isset($_POST['storage-token'])) {
        $token = sanitize_text_field($_POST['storage-token']);
    }

    $sdk = new GithubSDK(array(
        'owner' => $owner,
        'repo' => $repo,
        'branch' => $branch,
        'token' => $token
    ));

    $res = $sdk->test();

    if (!$res) {
        $message = "配置信息错误或 README.md 不存在。";
        $is_error = true;
    }else{
        $message = "测试连接成功。"; // Connection was Successfully.
        $is_error = false;
    }

    die( json_encode(array(
                         'message' => $message,
                         'is_error' => $is_error
                 )));
}

// 同步
function storage_resync() {
    $args = array(
        'post_type' => 'attachment',
        'numberposts' => null,
        'post_status' => null,
        'post_parent' => null,
        'orderby' => null,
        'order' => null,
        'exclude' => null,
    );

    $attachments = get_posts($args);
    if( ! $attachments) {
        return array();
    }


    $retval = array();
    foreach($attachments as $attach) {
        $filepath = get_attached_file($attach->ID);
        $object_name = __generate_object_name_from_path($filepath);

        $obj = __head_object($object_name);

        $do_upload = false;
        if( ! $obj OR ! file_exists($filepath)) {
            $do_upload = true;

        } else {
            // 对比本地和远程文件时间
            $mod1 = new DateTime('@'.$obj['putTime']);
            $mod2 = new DateTime('@'.filemtime($filepath));

            $d = $mod2->diff($mod1);
            
            if($d->invert === 1) {
                $do_upload = true;
            }
        }

        // 上传文件
        if( $do_upload ) {

            // 上传文件
            $retval[$object_name] = __upload_object($filepath);

            if ( $retval[$object_name] ) {
                // 获取缩略图信息
                $metadatas = wp_get_attachment_metadata($attach->ID);
                // 上传缩略图
                storage_thumb_upload($metadatas);
            }

        } else {
            $retval[$object_name] = null;
        }
    }
    return $retval;
}

// 上传文件
function storage_upload_file($file_id) {

    $filepath = get_attached_file($file_id);

    if( ! __file_has_upload_extensions($filepath)) {
        return null;
    }

    return __upload_object($filepath);
}

// 上传缩略图
function storage_thumb_upload($metadatas) {

    if( ! isset($metadatas['sizes'])) {
        return $metadatas;
    }

    $dir = wp_upload_dir();
    foreach($metadatas['sizes'] as $thumb) {
        $filepath = $dir['path'] . DIRECTORY_SEPARATOR . $thumb['file'];

        if( ! __file_has_upload_extensions($path)) {
            return false;
        }

        if( ! __upload_object($filepath)) {
            throw new Exception("upload thumb error");
        }
    }

    return $metadatas;
}

// 删除 object
function storage_delete_object($filepath) {
    if( ! __file_has_upload_extensions($filepath)) {
        return true;
    }
    return __delete_object($filepath);
}

// -------------------- WordPress hooks --------------------

add_action('admin_menu', 'add_pages');
add_action('admin_init', 'storage_options' );
add_action('wp_ajax_storage_connect_test', 'storage_connect_test');

add_action('add_attachment', 'storage_upload_file');
add_action('edit_attachment', 'storage_upload_file');
add_action('delete_attachment', 'storage_delete_object');
add_filter('wp_update_attachment_metadata', 'storage_thumb_upload');

add_filter( 'upload_dir', function( $args ) {
    $baseurl = get_option('storage-baseurl');
    if ($baseurl) {
        $args['baseurl'] = $baseurl;
    }
    return $args;
});


if(get_option('storage-delobject') == 1) {
    add_filter('wp_delete_file', 'storage_delete_object');
}

// -------------------- 私有函数 --------------------

// 转换文件路径
function __generate_object_name_from_path($path) {
    return str_replace( array(ABSPATH, '\\'), array('', '/'), $path);
}

// 确认文件拓展名
function __file_has_upload_extensions($file) {
    $extensions = get_option('storage-extensions');

    if($extensions == '' OR $extensions == '*') {
        return true;
    }

    $f = new SplFileInfo($file);
    if( ! $f->isFile()) {
        return false;
    }

    $fileext = $f->getExtension();
    $fileext = strtolower($fileext);

    foreach(explode(',', $extensions) as $ext) {
        if($fileext == strtolower($ext)) {
            return true;
        }
    }
    return false;
}

// 上传文件
function __upload_object($filepath) {

    // 上传文件
    if(is_readable($filepath)) {        
        $object_name = __generate_object_name_from_path($filepath);

        // 初始化 SDK 并进行文件的上传。
        $sdk = __get_github_sdk();
        $res = $sdk->upload($filepath, $object_name);

        if (!$res) {
            return false;
        }

    }

    return true;

}

// 获取object信息
function __head_object($object_name) {
    // sdk
    $sdk = __get_github_sdk();

    try {
        // 读取的文件 sha 信息
        $res = $sdk->getSha($object_name);
        return $res;

    } catch(Exception $ex) {
        return false;
    }
}

// 删除object
function __delete_object($filepath) {
    if (is_numeric($filepath)) {
        $filepath = get_attached_file($filepath);
    }
    $object_name = __generate_object_name_from_path($filepath);
    $sdk = __get_github_sdk();
    return $sdk->delFile($object_name);
}

// sdk
function __get_github_sdk($accessKey = null, $secretKey = null, $bucket = null) {
    static $sdk = null;
    if( ! $sdk) {
        $sdk = new GithubSDK(array(
            'owner' => get_option('storage-owner'),
            'repo' => get_option('storage-repo'),
            'branch' => get_option('storage-branch'),
            'token' => get_option('storage-token')
        ));
    }
    return $sdk;
}
