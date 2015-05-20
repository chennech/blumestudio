<div id="header">
	 <div class="row">
	   <h2 class="ttl ttl3">Project: <?php echo $project->name; ?> <span style="font-size:0.5em; color: #ccc;">(<?php echo $completion_percent; ?>%)</span></h2>
	   <?php echo $template['partials']['search']; ?>
	 </div>
</div>

<div class="row">

<?php /* --- [Being Main Content] --- */ ?>
<div class="eight columns content-wrapper">
		<div class="project-group-list">
		<?php
			// Generic Project Task List
			$this->load->view('_projects_list')


		?>
		</div><!-- /.project-group-list -->

                <?php if (count($milestones)):  ?>
	          <h4 class="sidebar-title"><?php echo __('global:milestones'); ?></h4>
                  <div class="sortable-milestones" data-project-id="<?php echo $project->id; ?>" style="margin-bottom: 20px;">
                    <?php foreach ($milestones as $milestone): ?>
                <div class="sortable-milestone" data-milestone-id="<?php echo $milestone->id; ?>">
                            <a href='<?php echo site_url("admin/projects/milestones/view/".$milestone->id); ?>'><div class="milestone-legend" style="background-color: <?php echo $milestone->color; ?>"></div> <?php echo $milestone->name; ?></a>
                        </div>
                    <?php endforeach; ?>
            </div>
                  <?php endif; ?>


                  <?php if (count($linked_invoices)): ?>
                      <div class='project-info'><h3 class="project-title">Project Invoices</h3></div>
                      <?php $this->load->view('reports/table', array('rows' => $linked_invoices)); ?>
                  <?php endif; ?>

</div><!-- /eight columns content-wrapper -->

<div class="four columns side-bar-wrapper">


	<?php // $this->load->view('../../partials/_taskstatus') ?>

	<div class="panel">

<!-- client -->


		<div class="client-details project-details-sidebar">

			<div class="row">
				<div class="three columns mobile-two">
					  	 <div class="client-image">
            				<img src="<?php echo get_gravatar($project->email, '600'); ?>" alt="<?php echo $project->first_name . ' ' . $project->last_name;?> image"/>
         				</div><!-- client-image -->
			  	</div><!-- /two -->
			  	<div class="nine columns mobile-two">
					<h4 style="margin:0; font-size:1.25em"><a href="<?php echo site_url('admin/clients/view/'.$project->client_id); ?>"><?php echo $project->company; ?></a></h4>
			    	<p class="no-bottom"><?php echo $project->first_name; ?> <?php echo $project->last_name; ?></p>
			    	<p class="f-thin-grey no-bottom project-due-date"><?php echo __('invoices:due') ?> <?php echo format_date($project->due_date); ?></p>
			  	</div><!-- /ten -->
			</div><!-- /row -->

		</div>
<!-- /client -->


        <?php echo $extra_project_sidebar_info; ?>


	  <h4 class="sidebar-title"><?php echo __('projects:manage_project') ?></h4>
		<ul class="side-bar-btns">
			<?php if (is_admin()): ?>
			<li class="add-milestone"><a href="<?php echo site_url('admin/projects/milestones/create/'.$project->id); ?>" class="fire-ajax"><span><?php echo __('milestones:add') ?></span></a></li>
			<?php endif ?>

			<?php if (can('create', get_client('projects', $project->id), 'project_tasks')): ?>
			<li class="add-task"><a href="<?php echo site_url('admin/projects/tasks/create/'.$project->id); ?>" class="fire-ajax"><span><?php echo __('tasks:create') ?></span></a></li>
			<?php endif ?>

			<li class="add-time"><a href="<?php echo site_url('admin/projects/times/create/'.$project->id); ?>" class="fire-ajax"><span><?php echo __('projects:add_time') ?></span></a></li>

			<?php if (count($tasks_select)): ?>
  			<?php if (can('generate_from_project', $project->client_id, 'projects', $project->id)): ?>
    			<li class="generate-invoice"><a href="<?php echo site_url('admin/invoices/create/'.$project->id); ?>" ><span><?php echo __('projects:generate_invoice') ?></span></a></li>
    		<?php endif ?>

  			<?php if (can('generate_from_project', $project->client_id, 'projects', $project->id)): ?>
    			<li class="generate-invoice"><a href="<?php echo site_url('admin/invoices/create/'.$project->id.'/'.$project->client_id.'/'.'estimate'); ?>"><span><?php echo __('estimates:generate_estimate') ?></span></a></li>
    		<?php endif ?>

			<?php endif ?>

			<li class="add-expense"><a href="<?php echo site_url('admin/projects/add_expense/'.$project->id); ?>" class="fire-ajax"><span><?php echo __('items:add_expense_to_project') ?></span></a></li>

                        <li class="view-timesheet"><a href="<?php echo site_url('admin/projects/times/view_entries/project/' . $project->id); ?>" ><span><?php echo __('timesheet:view_pdf') ?></span></a></li>
                        <li class="view-timesheet hide"><a href="<?php echo site_url('timesheet/' . $project->unique_id); ?>"><span><?php echo __('timesheet:view_pdf') ?></span></a></li>
                        <?php if (can('update', $project->client_id, 'projects', $project->id)): ?>
                            <?php if (!$project->is_archived): ?>
                                <li class="save"><a href="<?php echo site_url('admin/projects/archive/' . $project->id); ?>"><?php echo __('projects:archive_proj') ?></a></li>
                            <?php else: ?>
                                <li class="save"><a href="<?php echo site_url('admin/projects/unarchive/' . $project->id); ?>"><?php echo __('projects:unarchive_proj') ?></a></li>
                            <?php endif ?>
                        <li class="save make-template-from"><a href="<?php echo site_url('admin/projects/templatize/'.$project->id); ?>" class="fire-ajax"><span><?php echo __('projects:templatize'); ?></span></a></li>
                            <li>
                                <i class="quicklink-icon fi-pencil"></i>
                                <a href="<?php echo site_url('admin/projects/edit/'.$project->id); ?>" class="not-has-before fire-ajax"><span><?php echo __('projects:edit') ?></span></a>
                            </li>
                        <?php endif ?>

                        <?php if (can('delete', $project->client_id, 'projects', $project->id)): ?>
                            <li>
                                <i class="quicklink-icon fi-trash"></i>
                                <a href="<?php echo site_url('admin/projects/delete/'.$project->id); ?>" class="not-has-before fire-ajax"><span><?php echo __('projects:delete') ?></span></a>
                            </li>
                        <?php endif ?>

		</ul><!-- /sidebar-list -->


		<!-- here -->


<?php if (is_admin()): ?>
	<div class="row content-wrapper">

		<div class="twelve columns">

		<h3 style="border-bottom:1px solid #5C5651"><i class="fa fa-picture-o"></i> Snapshot</h3>

		<!-- /#CA6040 -->


		<!-- due & comments -->
		<div class="row">
		  <div class="six columns mobile-two">
		    <h5 style="color: #A6E19D; font-weight: bold"><?php echo __('invoices:due') ?></h5>
		    <p class="f-thin-black no-bottom"><?php echo format_date($project->due_date); ?></p>
		  </div><!-- /six -->
		  <div class="six columns mobile-two">
  		  <h5 style="color: #A6E19D; font-weight: bold"><?php echo __('kitchen:comments') ?></h5>
		    <p class="f-thin-black no-bottom"><?php echo anchor(Settings::get('kitchen_route').'/'.get_client_unique_id_by_id($project->client_id).'/comments/project/'.$project->id, get_count('project_comments', $project->id)) ?></p>
		  </div><!-- /six -->
		</div><!-- /row -->

		<!-- hours & default rate -->
		<div class="row">
		  <div class="six columns mobile-two">
		    <h5 style="color: #A6E19D; font-weight: bold"><?php echo __('tasks:hours') ?></h5>
		    <p class="f-thin-black"><?php echo $totals['hours']; ?></p>
			</div><!-- /six -->

		  <div class="six columns mobile-two">
  		  <h5 style="color: #A6E19D; font-weight: bold"><?php echo __('projects:projected') ?></h5>
                  <p class="f-thin-black"><?php echo format_hours($project->projected_hours); ?></p>
		  </div><!-- /six -->
		</div>

                <!-- billed and unbilled hours -->
		<div class="row">
		  <div class="six columns mobile-two">
		    <h5 style="color: #A6E19D; font-weight: bold"><?php echo __('tasks:billed_hours') ?></h5>
		    <p class="f-thin-black"><?php echo format_hours($totals['billed_hours']); ?></p>
			</div><!-- /six -->

		  <div class="six columns mobile-two">
  		  <h5 style="color: #CA6040; font-weight: bold"><?php echo __('tasks:unbilled_hours') ?></h5>
                  <p class="f-thin-black"><?php echo format_hours($totals['unbilled_hours']); ?></p>
		  </div><!-- /six -->
		</div>

		<?php if ($project->projected_hours > 0): ?>
			<input class="knob dial" value="<?php echo $project->budget_percentage ?>" data-readonly="true" data-fgColor="<?php echo $project->budget_status_color ?>" data-bgColor="<?php echo $project->budget_status_bgcolor ?>" data-thickness=".4" data-min="<?php echo $project->budget_percentage_min ?>" data-max="<?php echo $project->budget_percentage_max ?>"  />
		<?php endif; ?>

  		<!-- cost & expenses -->
		<div class="row">
		    <div class="six columns mobile-two">
		   		<h5 style="color: #CA6040; font-weight: bold"><?php echo __('items:cost') ?></h5>
		    	<p class="f-thin-black no-bottom"><?php echo Currency::format($totals['cost'], $project->currency_id); ?></p>
		  	</div><!-- /six -->

		  	<div class="six columns mobile-two">
		    	<h5 style="color: #A6E19D; font-weight: bold"><?php echo __('items:expenses'); ?></h5>
  		  		<p class="f-thin-black no-bottom"><?php echo Currency::format($totals['expenses']); ?></p>
		  	</div><!-- /six -->
		</div>


	  <h4 class="sidebar-title"><?php echo __("projects:progress"); ?></h4>
	  <div class="progress-bar blue">
	    <span style="width: <?php echo $completion_percent; ?>%"><?php echo $completion_percent; ?>%</span>
	  </div>


		</div><!-- /twelve columns -->
	</div><!-- /row -->

<?php endif ?>
    	<!-- /here -->
	</div><!-- /panel -->


</div><!-- /three columns side-bar-wrapper -->


<script type="text/javascript">
    $(".dial").knob();
</script>