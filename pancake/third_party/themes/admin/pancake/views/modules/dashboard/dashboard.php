
<div id="header">
	 <div class="row">
	   <h2 class="ttl ttl3"><?php echo __('global:dashboard'); ?></h2>
	   <?php echo $template['partials']['search']; ?>
	 </div>
</div>
<div class="row content-wrapper" >
	
	<div class="six columns">
		<h3 style="border-bottom:1px solid #5C5651"><i class="fa fa-check-square-o "></i> <?php echo __("dashboard:today"); ?></h3>
		<?php if (count($my_upcoming_tasks) > 0): ?>
                    <?php $this->load->view("_todays_tasks"); ?>
                <?php else: ?>
                    <?php echo __('global:there_are_no_tasks_assigned_to_you'); ?>
                <?php endif; ?>
		
	</div><!-- /six columns -->


	<div class="three columns">

		<h3  style="border-bottom:1px solid #5C5651"><i class="fa fa-dot-circle-o"></i> <?php echo __("dashboard:your_projects"); ?></h3>

		<?php $this->load->view('_projects') ?>
		
		
	</div><!-- /three columns -->

	<div class="three columns">

		<h3  style="border-bottom:1px solid #5C5651"><i class="fa fa-signal"></i> <?php echo __("dashboard:team_activity"); ?></h3>
		<?php $this->load->view('_team_activity'); ?>
		
	</div><!-- /three columns -->

</div><!-- /row -->




<?php if (is_admin()): ?>

<div class="row" style="margin-top:1em">

		
		<div class="four columns content-wrapper" style="width:33%; margin-right:1%">
			<h3 style="border-bottom:1px solid #5C5651"><i class="fa fa-warning"></i> <?php echo __("dashboard:upcoming_invoices");?></h3>
			<?php echo $this->load->view('_invoices', array('rows' => $upcoming_invoices)); ?>
		</div><!-- /eight columns -->

		<div class="four columns content-wrapper" style="width:33%; margin-right:1%">
			<h3  style="border-bottom:1px solid #5C5651"><i class="fa fa-asterisk"></i> <?php echo __('dashboard:client_activity'); ?></h3>
			<?php $this->load->view('_activity'); ?>
		
			
		</div><!-- /eight columns -->

		<div class="four columns content-wrapper" style="width:32%; margin-right:0px">
			<h3 style="border-bottom:1px solid #5C5651"><i class="fa fa-picture-o"></i> <?php echo __('dashboard:snapshot'); ?></h3>
			<?php $this->load->view('_snapshot'); ?>

			
			
		</div><!-- /eight columns -->

	
</div><!-- /row -->


<?php endif ?>













