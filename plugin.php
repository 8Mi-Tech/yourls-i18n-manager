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

// 加载自身语言包
yourls_add_action( 'plugins_loaded', 'i18n_manager_load_textdomain' );
function i18n_manager_load_textdomain() {
    yourls_load_custom_textdomain( 'i18n_manager', dirname( __FILE__ ) . '/languages' );
}

// 添加设置页面
yourls_add_action( 'plugins_loaded', 'i18n_manager_addpage' );
function i18n_manager_addpage() {
    // 检查请求方法是否为 POST
    if ( $_SERVER[ 'REQUEST_METHOD' ] === 'POST' && $_SERVER["QUERY_STRING"] === 'page=i18n-manager' ) {
        // 如果是 POST 请求，执行插件逻辑
        i18n_manager_process_request();
    } else {
        // 如果不是 POST 请求，注册设置页面
        yourls_register_plugin_page( 'i18n-manager', yourls__( 'I18N Manager' ,'i18n_manager'), 'i18n_manager_html' );
    }
}

function i18n_manager_process_request(){
    if ( isset( $_POST[ 'nonce' ] ) && yourls_verify_nonce( 'i18n-manager' ) ) {
        ob_start();  // 开始输出缓冲
        ob_end_clean();  // 清空输出缓冲
        header('Content-Type: application/json');

        function console_log($message, $level=null) {
            echo <<<HTML
             <script>console.log("{$message}");</script>
HTML;
        }

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

        function _curl_getfile($type,$url,$finename=null){
            $ch = curl_init();// 设置 cURL 选项,先初始化curl
            curl_setopt($ch, CURLOPT_URL, $url); // 设置要下载的文件的 URL
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 ); // 将响应保存到变量而不是直接输出
            curl_setopt($ch, CURLOPT_MAXREDIRS, 10);  // 设置最大重定向次数
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);  // 启用重定向跟随
            curl_setopt($ch, CURLOPT_AUTOREFERER, true);  // 设置为 true 以自动设置 Referer 头
            $fileContent = curl_exec( $ch ); // 执行 cURL 请求并获取响应
            // 检查是否有错误发生
            if ( curl_errno( $ch ) ) {
                    $response = array('success' => true, 'message' => yourls__( 'Curl Error' ,'i18n_manager').': '.curl_error( $ch ));
            } else {
                // 写到文件
                if (empty($fileContent)) {
                    $response = array('success' => true, 'message' => '文件内容为空');
                } else {
                    if ( $type === 'update-json') {
                        $path = __DIR__.'/languages.json';
                        $response = array('success' => true, 'message' => yourls__( 'Update JSON Complete' ,'i18n_manager'), "refresh" => true);
                    }
                    if ( $type === 'download') {
                        $path_ =  __DIR__.'/../../languages/'.$finename;
                        $path = $path_.'.mo';
                        if (file_exists($path_ . '.disabled')) {
                            unlink($path_ . '.disabled');
                        }
                        $response = array('success' => true, 'message' => yourls__( 'Download/Update Complete' ,'i18n_manager'), "refresh" => true);
                    }
                    file_put_contents( $path, $fileContent );
                }
            }
            #console_log(var_dump(curl_getinfo($ch)));
            curl_close( $ch ); // 关闭 cURL 资源
            return $response;
        }

        function findDownloadUrl($json, $name) {
            foreach ($json as $item) {
                if ($item['code'] == $name) {
                    return $item['url'];
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
            if ( $_POST[ 'download' ] === 'update-all')  {
                $languagesFolderPath = __DIR__.'/../../languages/'; // 语言文件夹
                $files = scandir($languagesFolderPath); // 类似Linux的ls 但是他是数组类型
                $json_languages = json_decode( file_get_contents( dirname( __FILE__ ).'/languages.json' ), true );
                $count_success=0;
                $count_failure=0;
                foreach ($files as $file) {
                    if ($file != '.' && $file != '..') { // 排除当前目录（.）和上级目录（..）
                        $fileInfo = pathinfo($file); // 使用pathinfo函数获取文件信息
                        if ($fileInfo['extension'] == 'mo') { // 检查文件后缀是否为 ".mo"
                            // echo $fileInfo['filename']; // 输出去除后缀的文件名
                            if (_curl_getfile('download',findDownloadUrl($json_languages,$fileInfo['filename']),$fileInfo['filename'])['success']){
                                $count_success++;
                            } else {
                                $count_failure++;
                            }
                        }
                    }
                }
                echo json_encode(array('success' => true, 'message' => yourls__( 'Update All Language Complete' ,'i18n_manager').', '.yourls__( 'Success' ,'i18n_manager').': '.$count_success.' '.yourls__( 'Error' ,'i18n_manager').': '.$count_failure, "refresh" => true));
            } else {
                echo json_encode(_curl_getfile('download', $_POST[ 'url'], $_POST['download']));  // 输出 JSON 数据
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
            if ($url !== '') {
                $Field .= '<input type="hidden" name="url" value="' . $url . '" />';
            } else {
                return '';
            }
        }
        $submitButton = '<input type="submit" value="' . $displayName . '" class="button" />';
        return '<form method="post" class="inline-form">' . $Field . $submitButton . '</form>';
    }
    $lang_lc=yourls__( 'Language Code', 'i18n_manager' );
    $lang_ln=yourls__( 'Language Name', 'i18n_manager' );
    $lang_author=yourls__( 'Author', 'i18n_manager' );
    $lang_ac=yourls__( 'Action', 'i18n_manager' );
    $lang_update_json=yourls__( 'Update JSON', 'i18n_manager');
    $lang_update_all_lang=yourls__( 'Update All Language', 'i18n_manager');
    // 使用HEREDOC语法输出HTML表格
    echo <<<HTML
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
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
                        alert(response.message);
                        if (response.refresh) {
                            location.reload();
                        }
                    } else {
                        // 处理失败的操作
                        alert('处理失败');
                    }
                },
                error: function() {
                    // 请求失败的操作
                    alert('请求失败');
                }
            });
        });
    });
    </script>   
    <style>
    .inline-form {
        display: inline;
    }
    </style>
    <h3>{$title}</h3>
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
    
    // 输出表格的闭合标签
    echo '</table>';


    if ( isset( $_POST[ 'nonce' ] ) && yourls_verify_nonce( 'i18n-manager' ) ) {
        if ( isset( $_POST[ 'download' ] ) ) {
            // JSON
            $data = json_decode( file_get_contents( dirname( __FILE__ ).'/languages.json' ), true );
        }
    }

}
?>