<?php
require_once('geetestlib.php');
if(!class_exists("Geetest")){
    
	class Geetest{
		private $options;
		private $plugin_directory;

		function start_plugin(){
                            $this->plugin_directory = basename(dirname(__FILE__));
                            $this->register_default_options() ;
                            // register the hooks
                            $this->register_actions();
                            $this->register_filters();
		}

		function register_actions() {
            //管理员设置 数据提交的回调函数
            add_action('admin_init', array($this, 'register_settings_group'));            
             //插件启动  保存，获取参数 options
            register_activation_hook($this->plugin_directory . '/wp-geetest.php', array($this, 'register_default_options')); // this way it only happens once, when the plugin is activated
             //插件停用
            register_activation_hook($this->plugin_directory . '/wp-geetest.php', array($this, 'uninstall')); // this way it only happens once, when the plugin is activated            
            // styling
            // add_action('wp_head', array($this, 'register_stylesheets')); // make unnecessary: instead, inform of classes for styling
            // add_action('admin_head', array($this, 'register_stylesheets')); // make unnecessary: shouldn't require styling in the options page
            
            if ($this->options['show_in_login']){
                add_action('login_head', array($this, 'login_geetest_style')); //修改验证码样式
                add_action('login_form', array($this, 'show_geetest_in_login'));
            }

            // only register the hooks if the user wants geetest on the registration page
            if ($this->options['show_in_registration']) {
                add_action('register_head', array($this, 'register_geetest_style')); //修改验证码样式
                //在新用户注册表结尾部分前执行此动作函数。 geetest form display
                add_action('register_form', array($this, 'show_geetest_in_registration'));
            }

            // only register the hooks if the user wants geetest on the comments page
            if ($this->options['show_in_comments']) {
                //在标准WordPress主题中执行此动作函数以插入评论表单。函数接收的参数：日志ID。
                add_action('comment_form', array($this, 'show_geetest_in_comments'));               
            }

            //==========================管理员======================================

            //给插件添加设置链接 administration (menus, pages, notifications, etc.)
            add_filter("plugin_action_links", array($this, 'show_settings_link'), 10, 2);
             
            //添加管理员插件设置页面
            add_action('admin_menu', array($this, 'add_settings_page'));
            
            //添加管理员geetest验证不正常警告 admin notices
            add_action('admin_notices', array($this, 'missing_keys_notice'));
        }
        
        function register_filters() {
            // only register the hooks if the user wants geetest on the registration page
            if ($this->options['show_in_login']) {
                // geetest validation  应用于注册新用户所生成的注册错误列表
               
                add_filter('wp_authenticate_user', array($this, 'validate_geetest_login'),100,1);
            }

            // only register the hooks if the user wants geetest on the registration page
            if ($this->options['show_in_registration']) {
                // geetest validation  应用于注册新用户所生成的注册错误列表
                add_filter('registration_errors', array($this, 'validate_geetest_register'),100,1);
            }

            if($this->options['show_in_registration']){
                //将评论保存到数据库中，尚未进行其它操作时，应用于评论信息。函数可接收的参数：评论信息数组，包括"comment_post_ID", "comment_author", 
                add_filter('preprocess_comment', array($this, 'validate_geetest_comment'), 100,1);
            }
        }
        //==========================插件若是第一次运行  保存参数=================================
        // set the default options
        function register_default_options() {           
            $option_defaults = array();
            //从option表中获取参数   
            $old_options = get_option("geetest_options");
           
            if ($old_options) {
               $option_defaults['public_key'] = $old_options['public_key']; // the public key for GeeTest
               $option_defaults['private_key'] = $old_options['private_key']; // the private key for GeeTest

               // placement
               $option_defaults['show_in_comments'] = $old_options['show_in_comments']; // whether or not to show GeeTest on the comment post
               $option_defaults['show_in_login'] = $old_options['show_in_login']; // whether or not to show GeeTest on the registration page
               $option_defaults['show_in_registration'] = $old_options['show_in_registration']; // whether or not to show GeeTest on the registration page            
            }else {
               // keys
               $option_defaults['public_key'] = ''; // the public key for GeeTest
               $option_defaults['private_key'] = ''; // the private key for GeeTest

               // placement
               $option_defaults['show_in_comments'] = "1"; // whether or not to show GeeTest on the comment post
               $option_defaults['show_in_login'] = "1";
               $option_defaults['show_in_registration'] = "1"; // whether or not to show GeeTest on the registration page

              // add the option based on what environment we're in
               add_option("geetest_options", $option_defaults);
            }
            $this->options = $option_defaults;         
        }
        //停止插件   回调函数
        function uninstall(){
            //移除管理员设置页面
            unregister_setting("geetest_options_group", 'geetest_options');

        }
        //==========================样式=================================
        // // todo: make unnecessary
        function register_stylesheets() {
            $path = $this->plugin_directory . '/geetest.css';
            echo '<link rel="stylesheet" type="text/css" href="' . $path . '" />';
        }  
        // stylesheet information
        // todo: this 'hack' isn't nice, try to figure out a workaround
        function login_geetest_style() {
           $style = <<<STYLE
              <style>
                 .gt_holder{
                    margin-bottom: 20px;
                   }
                  
                  t_info .gt_info_tip {
                    height: 12px;
                    width: 216px;
                    background-position: 0 -443px;
                  }
                  .gt_info {
                    margin-top: 9px;
                  }
              </style>
STYLE;
           echo $style;
        }
        function register_geetest_style() {
           $style = <<<STYLE
              <style>
                 .gt_holder{
                    margin-bottom: 20px;
                   }
              </style>
STYLE;
           echo $style;

        }

        //===========================显示login验证回调函数====================================
        // display geetest
        function show_geetest_in_login($errors) {
            $geetestlib = new geetestlib();
            if ($geetestlib->register($this->options['public_key'])) {
                echo $geetestlib->geetest_get_html($this->options['public_key']);
            }
        }
        // //处理验证
        function validate_geetest_login($user) {
            // empty so throw the empty response error
            $geetestlib = new geetestlib();

            
            $response = $geetestlib->geetest_check_answer($this->options['private_key'], $_POST['geetest_challenge'], $_POST['geetest_validate'], $_POST['geetest_seccode']);
            if (!$response) {
               return  new WP_Error('broke', __("验证未通过"));         
            } 
            return $user;               
            
        }
           
        //===========================显示registration验证回调函数====================================
        // display geetest
        function show_geetest_in_registration($errors) {
            $geetestlib = new geetestlib();
            if ($geetestlib->register($this->options['public_key'])) {
                echo $geetestlib->geetest_get_html($this->options['public_key']);
            }
            
        }
        //处理验证
        function validate_geetest_register($errors) {
            // empty so throw the empty response error

            $geetestlib = new geetestlib();
            $response = $geetestlib->geetest_check_answer($this->options['private_key'], $_POST['geetest_challenge'], $_POST['geetest_validate'], $_POST['geetest_seccode']);
            if (!$response) {
                $errors->add('captcha_wrong', "<strong>ERROR</strong>: 验证未通过");                
            }                

            return $errors;
        }
        
        //===========================显示comments验证回调函数====================================
        //用于前端点击提交，判断验证不通过提示
        function show_geetest_in_comments() {
            //modify the comment form for the GeeTest widget
            $geetest_js_opts = <<<OPTS
            <script type='text/javascript'>
                var geetest_result = 0;
                function gt_custom_ajax(res) {                        
                    geetest_result = res;
                }

                function gt_check_submit() {
                    if (!geetest_result) {
                        alert('请先拖动滑块通过验证');
                        return false;
                    }
                }
                //遍历寻找评论提交表单
                var forms = document.getElementsByTagName('form');
                for(i=0;i<forms.length;i++) {
                    var form = forms[i];

                    var a = form.attributes['action'];
                    var action = a.value;
                    if(action.indexOf('wp-comments-post.php') != -1){
                        form.onsubmit = gt_check_submit;
                        break;
                    }

                }

                
            </script>
OPTS;
            
            $position_geetest = <<<POST
                <script type='text/javascript'>
                    //将验证码显示在submit，提交按钮前面
                    var comment_submit = document.getElementById('submit');
                    var gt_holder = document.getElementById('geetest_unique_id');
                    comment_submit.parentNode.insertBefore(gt_holder,comment_submit);
                    gt_holder.style.marginTop = "20px";
                    gt_holder.style.marginBottom = "40px";
                </script>

POST;
            $geetestlib = new geetestlib();
            if ($geetestlib->register($this->options['public_key'])) {
                echo $geetest_js_opts.$geetestlib->geetest_get_html($this->options['public_key']).$position_geetest;
            }
        }
        
      
        //--------------判断验证validate_geetest_comment--------------------------
        
        //判断验证   保存评论
        function validate_geetest_comment($comment_data) {
            // do not check trackbacks/pingbacks
            if ($comment_data['comment_type'] == '') {
                $challenge = $_POST['geetest_challenge'];
                $validate = $_POST['geetest_validate'];
                $seccode = $_POST['geetest_seccode'];    
                $geetestlib = new geetestlib();

                $geetest_response = $geetestlib->geetest_check_answer($this->options['private_key'], $challenge, $validate, $seccode);
                
                if ($geetest_response) {                        
                    return $comment_data;
                } else {    
                    // http://codex.wordpress.org/Plugin_API/Filter_Reference#Database_Writes_2
                    add_filter('pre_comment_approved', 'is_comment_approved','99',2);
                }
            }
             return $comment_data;
        }
        //pre_comment_approved  处理验证不通过，不保存comment
        function is_comment_approved($approved , $commentdata){
            $approved = 0;
            return $approved;
        }


        //=========================给插件添加设置链接 ========================================
        // add a settings link to the plugin in the plugin list
        function show_settings_link($links, $file) {
            if ($file == plugin_basename($this->plugin_directory . '/wp-geetest.php')) {
               $settings_title = __('Settings for this Plugin', 'geetest');
               $settings = __('Settings', 'geetest');
               $settings_link = '<a href="options-general.php?page=geetest/geetest.class.php" title="' . $settings_title . '">' . $settings . '</a>';
               array_unshift($links, $settings_link);
            }
            
            return $links;
        }
        //============================显示管理员  geetest设置页面====================================
        // add the settings page
        function add_settings_page() {            
            add_options_page('GeeTest验证设置', 'GeeTest验证设置', 'manage_options', __FILE__, array($this, 'show_settings_page'));
        }
        // store the xhtml in a separate file and use include on it
        function show_settings_page() {
            include("settings.php");
        }
        //============================用于处理管理员设置页面数据提交的处理=============================================
        // register the settings,
        function register_settings_group() {
            register_setting("geetest_options_group", 'geetest_options', array($this, 'validate_options'));
        }
        function validate_options($input) {
            // todo: make sure that 'incorrect_response_error' is not empty, prevent from being empty in the validation phase
            
            // trim the spaces out of the key, as they are usually present when copied and pasted
            // todo: keys seem to usually be 40 characters in length, verify and if confirmed, add to validation process
            $validated['public_key'] = trim($input['public_key']);
            $validated['private_key'] = trim($input['private_key']);
            
            $validated['show_in_comments'] = ($input['show_in_comments'] == "1" ? "1" : "0");
            $validated['show_in_login'] = ($input['show_in_login'] == "1" ? "1" : "0");
            $validated['show_in_registration'] = ($input['show_in_registration'] == "1" ? "1" : "0");
           
            
            return $validated;
        }

         //========================管理员的 错误提示===========================================

        function geetest_enabled() {
            return ($this->options['show_in_comments'] || $this->options['show_in_registration']);
        }
        
        function keys_missing() {
            return (empty($this->options['public_key']) || empty($this->options['private_key']));
        }
        
        function create_error_notice($message, $anchor = '') {
            $options_url = admin_url('options-general.php?page=geetest/geetest.class.php') . $anchor;
            $error_message = sprintf(__($message . ' <a href="%s" title="WP-GeeTest Options">点击修复</a>', 'geetest'), $options_url);
            
            echo '<div class="error"><p><strong>' . $error_message . '</strong></p></div>';
        }
        
        function missing_keys_notice() {
            if ($this->geetest_enabled() && $this->keys_missing()) {
                $this->create_error_notice('您的极验ID或私钥为空.');
            }
        }
        //==================================================================================
    }
}
?>