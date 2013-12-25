<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" style="height:100%">
<head>
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8" />
    <title><?php echo isset($title)? $title : '后台管理系统'?></title>
    <link media="all" type="text/css" href="<?php echo $this->res("css/admincp.css") ?>" rel="stylesheet">
    <link media="all" type="text/css" href="<?php echo $this->res("css/front-style.css") ?>" rel="stylesheet">
    <link media="all" type="text/css" href="<?php echo $this->res("css/skin.css") ?>" rel="stylesheet">
    <script src="<?php echo $this->res("js/common.js?v=1") ?>" type="text/javascript"></script>
    <script type="text/javascript" src="<?php echo $this->res("js/lib/jquery.js") ?>"></script>

    <script type="text/javascript" src="<?php echo $this->res("lib/artDialog/jquery.artDialog.js?skin=green") ?>"></script>
    <script type="text/javascript" src="<?php echo $this->res("lib/artDialog/plugins/iframeTools.js") ?>"></script>

    <script type="text/javascript" src="<?php echo $this->res("js/cp.js") ?>"></script>
    <script type="text/javascript">var SITE_URL='<?php echo SITE_URL ?>',STATIC_URL='<?php echo STATIC_URL ?>';</script>
    <!--
    <script type="text/javascript" src="https://getfirebug.com/firebug-lite.js"></script>
    -->
</head>
<body style="height: 100%">
<table cellpadding="0" cellspacing="0" width="100%" height="100%">
    <tr>
        <td colspan="2" height="90">
            <div class="mainhd">
                <div class="logo">
                    <!--<img src="<?php echo $this->res("images/logo.png"); ?>" alt="" style="position: absolute;width:140px;left:1px;" />-->
                </div>
                <div class="uinfo">
                    <p>您好, <em><?php echo $_SESSION["admin"] ?></em> [ <a href="<?php echo $this->link("admin:logout") ?>" target="_top">退出</a> ]</p>
                    <p class="btnlink"><a href="" target="_blank">网站首页</a></p>
                </div>
                <div class="navbg"></div>
                <div class="nav">
                    <ul id="topmenu">
                        <?php foreach($this->get_nav_menu() as $nav_menu) : ?>
                            <li <?php if(strtolower($this->controller) == $nav_menu["link"]) : ?>class="navon"<?php endif ?>>
                                <em>
                                    <a href="<?php echo $this->link($nav_menu["link"]) ?>">
                                        <?php echo $nav_menu["name"] ?>
                                    </a>
                                </em>
                            </li>
                        <?php endforeach ?>
                    </ul>
                    <div class="currentloca">
                        <p id="cp_news">欢迎使用本系统</p>
                    </div>
                    <div class="navbd"></div>
                </div>
            </div>
        </td>
    </tr>
    <tr>
        <td valign="top" width="160" class="menutd" style="height: 100%">
            <div id="leftmenu" class="menu">
                <ul id="menu_global" style="display: block">
                    <?php foreach($this->get_menu() as $m) : ?>
                        <li>
                            <a href="<?php echo $this->link(strtolower($this->controller).':'.$m["link"]) ?>" hidefocus="true">
                                <?php echo $m["name"] ?>
                            </a>
                        </li>
                    <?php endforeach ?>
                </ul>
            </div>
        </td>
        <td valign="top" width="100%" class="mask" id="mainframes">
            <div style="padding:0px 10px;">
                <?php echo $content ?>
            </div>
        </td>
    </tr>
</table>
<!--
<script type="text/javascript" src="<?php echo $this->res("js/cpnews.js") ?>"></script>
-->
</body>
</html>