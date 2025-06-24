<?php
/*
Plugin Name: I18N Manager Plugin
Plugin URI: https://github.com/8Mi-Tech/yourls-i18n-manager
Description: I18N Manager is a MultiLanguage System
Version: 1.0
Author: 8Mi-Tech
Author URI: https://8mi.ink
*/

if ( !defined( 'YOURLS_ABSPATH' ) ) die(); // 这是必须的

yourls_add_action( 'plugins_loaded', 'i18n_manager_set_language' );
function i18n_manager_set_language(){
    global $yourls_locale;
    $browserLang = current(explode(',', isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : ''));
    $json_languages = json_decode( file_get_contents( dirname( __FILE__ ).'/languages.json' ), true );
    foreach ($json_languages as $json_language) {
        if (in_array($browserLang, $json_language['aliases'])) {
            $matchedCode = $json_language['code'];
            break;
        }
    }
    $langFilePath = YOURLS_ABSPATH . '/user/languages/' . $matchedCode . '.mo';
    if (file_exists($langFilePath) && is_readable($langFilePath) && (!is_link($langFilePath) || (is_readable(readlink($langFilePath)) && filesize($langFilePath) > 60))) {                
        // TODO: Here, logic for checking the ".mo" language file needs to be inserted.
        $yourls_locale = is_link( $langFilePath ) ? pathinfo( readlink( $langFilePath ) )[ 'filename' ] : $matchedCode;
    }
    if (!isset($yourls_locale) or $yourls_locale === '' ) {
        $yourls_locale = 'en_US';
    }
    define( 'YOURLS_LANG', $yourls_locale);
    return $yourls_locale;
}

// 添加设置页面
yourls_add_action( 'plugins_loaded', 'i18n_manager_plugin_loaded' );
function i18n_manager_plugin_loaded() {
    // define( 'YOURLS_LANG', i18n_manager_set_language());
    yourls_load_custom_textdomain( 'i18n_manager', dirname( __FILE__ ) . '/languages' ); // 加载自身语言包
    if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' && $_SERVER["QUERY_STRING"] === 'page=i18n-manager' ) { // 检查请求方法是否为 POST
        i18n_manager_process_request(); // 如果是 POST 请求，执行插件逻辑
    } else {
        yourls_register_plugin_page( 'i18n-manager', yourls__( 'I18N Manager' ,'i18n_manager'), 'i18n_manager_html' ); // 如果不是 POST 请求，注册设置页面
    }
}

function i18n_manager_process_request(){
    if ( isset( $_POST[ 'nonce' ] ) && yourls_verify_nonce( 'i18n-manager' ) ) {
        ob_start();  // 开始输出缓冲
        ob_end_clean();  // 清空输出缓冲
        header('Content-Type: application/json');

        function lang_rename_file($lang_code,$old_prefix, $new_prefix) {
            $languagesFolderPath = __DIR__.'/../../languages/';
            $sourceFile = $languagesFolderPath . $lang_code . '.' . $old_prefix;
            $targetFile = $languagesFolderPath . $lang_code . '.' . $new_prefix;
            $success = rename($sourceFile, $targetFile);
            if ($success) {
                // 文件重命名成功，返回成功的响应
                $response = array('success' => true, 'message' => yourls__( 'Action Success' ,'i18n_manager'), 'refresh' => true);
            } else {
                // 文件重命名失败
                $response = array('success' => true, 'message' => yourls__( 'Action Failure, Please refresh the page and try again' ,'i18n_manager'));
            }
            echo json_encode($response);  // 输出 JSON 数据
           
        }

        function _curl_getfile($type,$url,$finename=null,$aliases=null){
            $languagesFolderPath = __DIR__.'/../../languages/';
            $ch = curl_init("$url");// 设置 cURL 选项,先初始化curl
            // curl_setopt($ch, CURLOPT_URL, $url); // 设置要下载的文件的 URL
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 ); // 将响应保存到变量而不是直接输出
            curl_setopt($ch, CURLOPT_MAXREDIRS, 10);  // 设置最大重定向次数
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);  // 启用重定向跟随
            curl_setopt($ch, CURLOPT_AUTOREFERER, true);  // 设置为 true 以自动设置 Referer 头
            curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER["user-agent"]);
            #curl_setopt($ch, CURLOPT_REFERER,_REFERER_);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $fileContent = curl_exec( $ch ); // 执行 cURL 请求并获取响应
            // 检查是否有错误发生
            if ( curl_errno( $ch ) ) {
                    $response = array('success' => true, "result" => false, 'message' => yourls__( 'Curl Error' ,'i18n_manager').': '.curl_error( $ch ));
            } else {
                // 写到文件
                if (empty($fileContent)) {
                    $response = array('success' => true, "result" => false, 'message' => '文件内容为空');
                } else {
                    if ( $type === 'update-json') {
                        $path = __DIR__.'/languages.json';
                        $response = array('success' => true, "result" => true, 'message' => yourls__( 'Update JSON Complete' ,'i18n_manager'), "refresh" => true);
                    }
                    if ( $type === 'download') {
                        $path_ = $languagesFolderPath.$finename;
                        $path = $path_.'.mo';
                        if (file_exists($path_ . '.disabled')) {
                            $path = $path_ . '.disabled';
                        }
                        if ($aliases!==null) {
                            $originalDir = getcwd();
                            if (chdir($languagesFolderPath)) {
                                foreach ($aliases as $alias){
                                    symlink($finename.'.mo', $alias.'.mo');
                                }
                                chdir($originalDir);
                            }    
                        }
                        $response = array('success' => true,  "result" => true,  'message' => yourls__( 'Download/Update Complete' ,'i18n_manager'), "refresh" => true);
                    }
                    file_put_contents( $path, $fileContent );
                }
            }
            curl_close( $ch ); // 关闭 cURL 资源
            #console_log(var_dump(curl_getinfo($ch)));

            
            #if ($response === null or $response === '') { 
                #$response = array('success' => true,  "result" => false,  'message' => yourls__( 'Unknown Error' ,'i18n_manager'));
            #}
            
            return $response;
        }

        function _findJSON($jsonData, $name, $string) {
            foreach ($jsonData as $item) {
                if ($item['code'] == $name) {
                    return $item[$string];
                }
            }
            return null; // 如果找不到对应的下载地址
        }

        if (isset($_POST['enabled'])) {
            lang_rename_file($_POST['enabled'],'disabled', 'mo');
        }
    
        if (isset($_POST['disabled'])) {
            lang_rename_file($_POST['disabled'],'mo', 'disabled');
        }
        
        if ( isset( $_POST[ 'download' ] ) ) {
            $json_languages = json_decode( file_get_contents( dirname( __FILE__ ).'/languages.json' ), true );
            if ( $_POST[ 'download' ] === 'update-all')  {
                $languagesFolderPath = __DIR__.'/../../languages/'; // 语言文件夹
                $files = scandir($languagesFolderPath); // 类似Linux的ls 但是他是数组类型
                $count_success=0;
                $count_failure=0;
                foreach ($files as $file) {
                    if ($file != '.' && $file != '..') { // 排除当前目录（.）和上级目录（..）
                        $fileInfo = pathinfo($file); // 使用pathinfo函数获取文件信息
                        if ($fileInfo['extension'] == 'mo' and is_link($file) == false ) { // 检查文件后缀是否为 ".mo"
                            // echo $fileInfo['filename']; // 输出去除后缀的文件名
                            if (_curl_getfile('download',_findJSON($json_languages,$fileInfo['filename'],'url'),$fileInfo['filename'],_findJSON($json_languages,$fileInfo['filename'],'aliases'))['result']){
                                $count_success++;
                            } else {
                                $count_failure++;
                            }
                        }
                    }
                }
                echo json_encode(array('success' => true, 'message' => yourls__( 'Update All Language Complete' ,'i18n_manager').', '.yourls__( 'Success' ,'i18n_manager').': '.$count_success.' '.yourls__( 'Error' ,'i18n_manager').': '.$count_failure, "refresh" => true));
            } else {
                echo json_encode(_curl_getfile('download', _findJSON($json_languages,$_POST['download'],'url'), $_POST['download'],_findJSON($json_languages,$_POST['download'],'aliases')));  // 输出 JSON 数据
            }            
        }

        if ( isset( $_POST[ 'update-json' ] )){
            echo json_encode(_curl_getfile('update-json', 'https://github.com/8Mi-Tech/yourls-i18n-manager/raw/main/languages.json'));
        }
        exit;  // 终止脚本执行，确保只返回 JSON 数据
    }
    
}

function i18n_manager_html() {  
    // 设置页面HTML代码
    $title = yourls__( 'I18N Manager', 'i18n_manager' ).' '.yourls__( 'Settings', 'i18n_manager' );
    $title_language = yourls__( 'Language', 'i18n_manager' );
    $current_language = yourls_get_locale();
    $nonce = yourls_create_nonce( 'i18n-manager' );

    function generateForm($name, $lang, $displayName, $nonce, $url = null) {
        $Field = '<input type="hidden" name="nonce" value="' . $nonce . '" />';
        $Field .= '<input type="hidden" name="' . $name . '" value="' . $lang . '" />';
        if ($name === 'download') {
            if ($url === '') {
                return '';
            }
        }
        $submitButton = '<input type="submit" value="' . $displayName . '" class="button" />';
        return '<form method="post" class="inline-form">' . $Field . $submitButton . '</form>';
    }
    $lang_lc=yourls__( 'Language Code', 'i18n_manager' );
    $lang_ln=yourls__( 'Language Name', 'i18n_manager' );
    $lang_author=yourls__( 'Authors', 'i18n_manager' );
    $lang_ac=yourls__( 'Action', 'i18n_manager' );
    $lang_update_json=yourls__( 'Update JSON', 'i18n_manager');
    $lang_update_all_lang=yourls__( 'Update All Language', 'i18n_manager');
    // 使用HEREDOC语法输出HTML表格
    echo <<<HTML
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/layui/layui@main/dist/layui.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/layui/layui@main/src/css/modules/layer.css">
    <script>
    $(document).ready(function() {
        $('.inline-form').submit(function(e) {
            e.preventDefault();
            var formData = $(this).serialize();
            $.ajax({
                type: 'POST',
                url: 'plugins.php?page=i18n-manager',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // 成功处理的操作，可以显示提示信息等
                        layer.msg(response.message, {icon: 1}, function(){
                            if (response.refresh) {
                                location.reload();
                            }
                        });
                    } else {
                        // 处理失败的操作
                         layer.msg(response.message, {icon: 2});
                    }
                },
                error: function() {
                    // 请求失败的操作
                    layer.msg('请求失败', {icon: 5});
                }
            });
        });
    });
    </script>   
    <style>
    .inline-form { display: inline; }
    
@font-face {
  font-family: 'layui-icon';
  src: url('https://cdn.jsdelivr.net/gh/layui/layui@main/dist/font/iconfont.eot');
  src: url('https://cdn.jsdelivr.net/gh/layui/layui@main/dist/font/iconfont.eot') format('embedded-opentype'),
       url('https://cdn.jsdelivr.net/gh/layui/layui@main/dist/font/iconfont.woff2') format('woff2'),
       url('https://cdn.jsdelivr.net/gh/layui/layui@main/dist/font/iconfont.woff') format('woff'),
       url('https://cdn.jsdelivr.net/gh/layui/layui@main/dist/font/iconfont.ttf') format('truetype'),
       url('https://cdn.jsdelivr.net/gh/layui/layui@main/dist/font/iconfont.svg') format('svg');
}

.layui-icon{
  font-family:"layui-icon" !important;
  font-size: 16px;
  font-style: normal;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
    .layui-icon-success:before{content:"✅"}
    .layui-icon-error:before{content:"❌"}
    .layui-icon-face-cry:before{content:"😭"}
    </style>
    <h2>{$title}</h2>
    <table class = 'form-table'>
    <tr>
    <th>{$lang_lc}</th>
    <th>{$lang_ln}</th>
    <th>{$lang_author}</th>
    <td>
    {$lang_ac}
    <form method="post" class="inline-form"><input type = 'hidden' name = 'nonce' value = "$nonce" /><input type = 'hidden' name = 'update-json' value = 'true'><input type = 'submit' value = '{$lang_update_json}' class = 'button' /></form>
    <form method="post" class="inline-form"><input type = 'hidden' name = 'nonce' value = "$nonce" /><input type = 'hidden' name = 'download' value = 'update-all'><input type = 'submit' value = '{$lang_update_all_lang}' class = 'button' /></form>
    </td>
    </tr>
HTML;
    $languages = json_decode( file_get_contents( dirname( __FILE__ ).'/languages.json' ), true );
    // 循环遍历语言数组并输出表格行
    foreach ($languages as $language) {
        $languageCode = $language['code'];
        $languageName = $language['nickname'];
        $languageAuthor = $language['author'];
        $languageRepoURL = $language['repo-url'];
        // 获取插件文件所在的目录
        $pluginDirectory = __DIR__;
        // 构建文件路径
        $moFilePath = $pluginDirectory . '/../../languages/' . "$languageCode.mo";
        $disableFilePath = $pluginDirectory . '/../../languages/' . "$languageCode.disabled";
        // 输出页面
        echo <<<HTML
        <tr>
        <td>{$languageCode}</td>
        <td>{$languageName}</td>
        <td><a target="_blank" href="{$languageRepoURL}">{$languageAuthor}</a></td>
        <td>
HTML;
        // 判断文件是否存在并输出对应的表单
        if (!file_exists($moFilePath) && !file_exists($disableFilePath)) {
            echo generateForm('download', $language['code'], yourls__( 'Download', 'i18n_manager' ), $nonce, $language['url']);
        } else {
            echo generateForm('download', $language['code'], yourls__( 'Update', 'i18n_manager' ), $nonce, $language['url']);
        }

        if (file_exists($moFilePath)) {
            echo generateForm('disabled', $language['code'], yourls__( 'Disabled', 'i18n_manager' ), $nonce);
        } elseif (file_exists($disableFilePath)) {
            echo generateForm('enabled', $language['code'], yourls__( 'Enabled', 'i18n_manager' ), $nonce);
        }        
        
        echo '</td></tr>';
    }
    $websiteLang=yourls_get_locale();
    $browserLang = current(explode(',', isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : ''));
    $lang_tip_browserlang=yourls__( 'Your Browser language', 'i18n_manager' );
    
    echo <<< HTML
    </table>
    <p>{$lang_tip_browserlang}: {$browserLang} </p>
    <p>website: {$websiteLang}</p>
HTML;
}
?>
