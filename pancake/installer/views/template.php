<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>Pancake Payments Install Wizard</title>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
	<link rel="stylesheet" href="<?php echo Asset::get_src('style.css', 'css'); ?>" type="text/css" media="screen" title="Pancake Installer" charset="utf-8">
	<!-- grabbing fonts from Google cause they make me smile, :D <= like that. -->
	<link href='http://fonts.googleapis.com/css?family=Paytone+One&v1' rel='stylesheet' type='text/css'>
	<!-- Javascript -->
	<script src="http://code.jquery.com/jquery-latest.js" type="text/javascript" charset="utf-8"></script>
	
	
	<script>
	$(function() {

		
		$('input, select, textarea').bind({
			focusin: function() {
				var wrapper = $(this).closest('tr');
				$(wrapper).addClass('focus');
			},
			focusout: function() {
				var wrapper = $(this).closest('tr');
				$(wrapper).removeClass('focus');
			}
		});

	});
	</script>
</head>
<body>
	<div id="wrapper">

		<div id="content" class="no_object_notification">
		<?php if ($message = $this->session->flashdata('success')): ?>
			<div class="notification success"><?php echo $message; ?></div>
		<?php endif; ?>
		<?php if (isset($messages['success'])): ?>
			<div class="notification success"><?php echo $messages['success']; ?></div>
		<?php endif; ?>

		<?php if ( $message = $this->session->flashdata('error')): ?>
			<div class="notification error"><b>Error:</b> <?php echo $message; ?></div>
		<?php endif; ?>
		<?php if (isset($messages['error'])): ?>
			<div class="notification error"><b>Error:</b> <?php echo $messages['error']; ?></div>
		<?php endif; ?>
		<?php if ($errors = validation_errors('<p>', '</p>')): ?>
			<div class="notification error"><?php echo $errors; ?></div>
		<?php endif; ?>
<?php echo $content; ?>
		</div><!-- /content -->

	   <div id="footer">
	   </div><!-- /footer -->

	</div><!-- /wrapper -->
</body>
</html>