PlumePHP - 羽量级的单文件php开发框架
========


### 简介

PlumePHP是一个单文件PHP框架，适用于简单系统的快速开发，提供了简单的路由方式，抛弃了坑爹的PHP模板，采用原生PHP语法来渲染页面,同时提供了widget功能，简单且实用。


### 目录结构

    ├── Application                         #业务代码文件夹，可在配置中指定路径
    │   ├── Controller                      #控制器文件夹
    │   │   └── IndexController.class.php
    │   ├── Lib                             #外部库
    │   ├── Log                             #日志文件夹，需要写权限
    │   ├── View                            #模板文件夹
    │   │   ├── Index                       #对应Index控制器
    │   │   │   └── Index.php
    │   │   └── Public
    │   │       ├── footer.php
    │   │       └── header.php
    │   ├── Widget                          #widget文件夹
    │   │   ├── MenuWidget.class.php
    │   │   └── Tpl                         #widget模板文件夹
    │   │       └── MenuWidget.php
    │   ├── common.php                      #一些共用函数
    |   └── PlumePHP.class.php              #PlumePHP核心文件
    ├── public
    │   ├── js
    │   ├── images
    │   └── css
    └── index.php                           #入口文件
    
### Hello World

只需增加3个文件，即可输出hello world。

入口文件：index.php

    <?php
    define('ENVIRONMENT', 'development');
    include __DIR__.'/Application/PlumePHP.class.php';
    // 这里可以动态增加全局配置信息
    $config = array('APP_PATH' => 'Application');
    PlumePHP::getInstance($config)->run();
    
默认控制器：Application/Controller/IndexController.class.php

    <?php
    class IndexController extends Controller {       //控制器必须继承Controller类或其子类
        public function IndexAction(){               //默认Action
            $this->assign('content', 'Hello World'); //给模板变量赋值
            $this->display();                        //渲染
        }
    }
    
模板文件：Application/View/Index/Index.php

    <?php echo $content;
    
在浏览器访问index.php，应该会输出

    Hello World
