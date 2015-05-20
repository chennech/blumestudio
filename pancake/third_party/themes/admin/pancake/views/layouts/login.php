<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width" />
	<title><?php echo $template['title']; ?></title>
	<?php echo Asset::css('login.css', array('media' => 'all')); ?>
	<!--[if lt IE 7]><?php echo asset::css('lt7.css'); ?> <![endif]-->
	<?php if (Settings::get('backend_css')): ?>
		<link rel="stylesheet" href="<?php echo site_url("admin/dashboard/backend_css/" . crc32(Settings::get('backend_css')) . '.css'); ?>"/>
	<?php endif; ?>
</head>
<body class="module-<?php echo $this->router->fetch_module(); ?> controller-<?php echo $this->router->fetch_class(); ?> action-<?php echo $this->router->fetch_method(); ?> login-layout not-main-layout">

<div id="header-area">
		<?php echo Business::getLogo();?>
</div>

<div id="wrapper">
	<div id="main" class="form-holder">
		<?php echo $template['partials']['notifications']; ?>
		<?php echo $template['body']; ?>
	</div><!-- /main end -->
	
</div><!-- /wrapper end -->

<?php if (IS_DEMO) :?>
    <?php echo file_get_contents(FCPATH.'DEMO');?>
<?php endif;?>
<script src="<?php echo Asset::get_src("login.js", "js"); ?>"></script>
<?php if (Settings::get('backend_js')): ?>
	<script src="<?php echo site_url("admin/dashboard/backend_js/" . crc32(Settings::get('backend_js')) . '.js'); ?>"></script>
<?php endif; ?>
</body>
</html>