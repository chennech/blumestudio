<div id="header">
	 <div class="row">
	   <h2 class="ttl ttl3"><?php echo __('global:timesheets') ?><br /></h2>
	   <?php echo $template['partials']['search']; ?>
	 </div>
</div>
<div class="row content-wrapper" >
	<div class="twelve columns">
<div class="row">
	<div class="twelve columns">
		
		<div class="row">
			<div class="nine columns">
				<h3 style="border-bottom:1px solid #5C5651"><a href="#" class="toggle-filter-entries"><i class="fa fa-calendar"></i></a> <?php echo $dateRange ?> - <?php echo __('tasks:entries'); ?></h3>
                <p>                
        <i class="fa fa-clock-o"></i> <?php echo __("tasks:total_logged_time"); ?>
	<i class="fa fa-star"></i> <?php echo __("timesheets:rounded_time", array(format_hours(Settings::get("task_time_interval"))))?>
        </p>
                                
                                <div class="row">
			<div class="twelve columns panel filter-entries-container" style="display: none;">
                                <form action="<?php echo site_url("admin/timesheets/rehash"); ?>" method="POST" style="margin-top: -2em;" name="filter-entries" id="filter-entries">
					<div class="row">
						<div class="two columns">
							<label>Start date</label>
                                                        <input type="text" name="startDate" class="rounded-input datePicker" value="<?php echo $start; ?>" placeholder="<?php echo format_date(time()) ?>" />
						</div><!-- /three columns -->
						<div class="two columns">
							<label>End date</label>
							<input type="text" name="endDate" class="rounded-input datePicker" value="<?php echo $end; ?>" placeholder="<?php echo format_date(time()) ?>" />
						</div><!-- /three columns -->

						<?php if (is_admin()): ?>
						<div class="two columns">
							<label>Select User</label>

							<?php echo form_dropdown('user_id', $users) ?>

							
						</div><!-- /three columns -->

						<div class="six columns" style="padding-top:2em">
							<a class="blue-btn js-fake-submit-button" href="#">
<span>Filter</span>
</a>
						</div><!-- /three columns -->
						<?php else: ?>
						<div class="eight columns" style="padding-top:2em">
							<a class="blue-btn js-fake-submit-button" href="#">
<span>Filter</span>
</a>
						</div><!-- /three columns -->
                                                
						<?php endif ?>

					</div><!-- /row -->
					
				</form>
			</div><!-- /twelve columns -->
		</div><!-- /row -->
                                
                                
			</div>
			<div class="three columns">
				<h3 style="border-bottom:1px solid #5C5651"><i class="fa fa-dot-circle-o"></i> <?php echo __("global:projects"); ?></h3>
			</div>
		</div>


            <?php if (count($userEntries) == 0): ?>
                <div class="row">
                    <div class="twelve columns">
                        <h5><?php echo __('timesheets:there_are_no_time_entries'); ?></h5>
                    </div>
                </div>
            <?php endif; ?>


		<?php foreach ($userEntries as $userEntry): ?>
			
		

		<?php // Start of user entry  ?>
		<div class="row" style="margin-bottom: 2em">
			<div class="two columns totals" >
				<p style="font-size: 1.2em"><a href="#"><?php echo $userEntry['user'] ?></a>
				<br /><span style="font-size: 0.85em"><i class="fa fa-clock-o" title="<?php echo __("tasks:total_logged_time"); ?>"></i> <?php echo round($userEntry['totalHours'], 2) ?> hours</span></span>
				<br /><span style="font-size: 0.85em"><i class="fa fa-star" title="<?php echo __("timesheets:rounded_time", array(format_hours(Settings::get("task_time_interval"))))?>"></i> <?php echo round($userEntry['billableHours'], 2) ?> hours</span></span></p>
			</div><!-- /two columns -->
	
			<div class="seven columns ledger">

				<ul class="time-sheet-ledger">

					<?php foreach ($userEntry['entries'] as $entry): ?>
						
					
					<li><span class="time-logged"><i class="fa fa-clock-o" title="<?php echo __("tasks:total_logged_time"); ?>"></i> <?php echo round($entry->minutes/60, 2); ?> hrs</span> on <a href="<?php echo site_url("admin/projects/view/".$entry->project_id)?>"><?php echo $entry->task_name ?></a> <?php echo ($entry->note)? '- '.$entry->note : '' ?></li>
					<?php endforeach ?>

				</ul>
			</div><!-- /seven columns -->

			<div class="three columns">
				<?php foreach ($userEntry['projectHours'] as $project_id => $project): ?>
				<div class="row">	
					<div class="twelve columns">
                                            <p style=""><a href="<?php echo site_url("admin/projects/view/".$project_id)?>"><?php echo $project['name'] ?></a><br />

						<?php if($project['company'] != ''): ?><i><?php echo $project['company'] ?></i><br /><?php endif ?>
                                                <i class="fa fa-clock-o" title="<?php echo __("tasks:total_logged_time"); ?>"></i> <?php echo round($project['hours'], 2) ?>hrs - 
                                                <i class="fa fa-star" title="<?php echo __("timesheets:rounded_time", array(format_hours(Settings::get("task_time_interval"))))?>"></i> <?php echo round($project['billableTime'], 2) ?> hrs
						</p>
					</div><!-- /ten columns -->
				</div><!-- /row -->
				<?php endforeach ?>
			</div><!-- /three columns -->
		</div><!-- /row -->

		<?php // End of user entry  ?>

		<?php endforeach ?>

		</div><!-- /twelve columns -->
	</div><!-- /row -->

</div><!-- /twelve columns -->




	
	

</div><!-- /row -->