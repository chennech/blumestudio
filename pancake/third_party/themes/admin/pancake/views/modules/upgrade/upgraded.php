<div class="update-notification" id="update">
    <h2>Pancake was upgraded from <?php echo $from; ?> to <?php echo $to; ?>!</h2>

    <?php if (!empty($changelog)) :?>
    <div class="changelog-container">
	<h3><?php echo __('update:whatschanged', array($to))?></h3>
	<div class="changelog">
	    <?php echo $changelog; ?>
	</div>
    </div>
    <?php else: ?>
	<div class="error-download">
	    <h3>We hope you enjoy.</h3>
            <br />&nbsp;
	</div>
    <?php endif;?>
</div>