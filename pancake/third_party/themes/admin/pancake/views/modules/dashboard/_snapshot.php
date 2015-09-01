

<div class="row">
	<div class="six columns">
            <h4 style="color: #A6E19D; margin:0"><i class="fa fa-check-circle" ></i> <a href="<?php echo site_url('reports/payments/view/from:'.Settings::fiscal_year_start().'-to:0-client:0'); ?>" class="collect"  style="color: #A6E19D"><?php echo Currency::format($paid); ?></a></h4>
	</div><!-- /six columns -->

	<div class="six columns">
		<h4 style="color: #CA6040; margin:0"><i class="fa fa-exclamation-circle" ></i> <a href="<?php echo site_url('admin/invoices/unpaid'); ?>" class="uncollected"  style="color: #CA6040"><?php echo Currency::format($unpaid['total']); ?></a></h4>

	</div><!-- /six columns -->

</div><!-- /row -->

<hr />

<div class="row">
	<div class="six columns">
		<h5 style="color: #A6E19D; margin:0"><a href="#"><?php echo __('projects:hours_worked_short') ?></a><br /> <?php echo $hours_worked; ?></h5>
	</div><!-- /six columns -->

	<div class="six columns">
		<h5 style="color: #A6E19D; margin:0"><a href="#"><?php echo __('tasks:timers_running') ?></a><br /> <?php echo count($timers); ?></h5>
		
	</div><!-- /six columns -->

</div><!-- /row -->

<hr />

<div class="row">
	<div class="six columns">
		<h5 style="color: #A6E19D; margin:0"><a href="<?php echo site_url('admin/projects/') ?>" title="<?php echo __('projects:totalprojects') ?>"><?php echo __('projects:totalprojects') ?></a> <br /> <?php if ($project_count >= 1) { echo $project_count; } else { echo "0"; } ?></h5>
	</div><!-- /six columns -->

	<div class="six columns">
		<h5 style="color: #A6E19D; margin:0"> <a href="<?php echo site_url('admin/clients/') ?>"><?php echo __('clients:total_clients') ?></a><br /> <?php echo $client_count; ?></h5>
		
	</div><!-- /six columns -->

</div><!-- /row -->
