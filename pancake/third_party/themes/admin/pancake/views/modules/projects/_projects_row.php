<?php if (count($rows)): ?>
<div class="row">
	<div id="projectholder">
		<?php if(isset($status)): ?>
		<h4 class="twelve columns">
				<?php if ($status =='active'): ?>
					<?php echo __('projects:active') ?>
				<?php else:  ?>
					<?php echo __('projects:archived') ?>
				<?php endif ?></h4>
		<?php endif ?>


		<?php $count = 0; ?>
	    <?php foreach ($rows as $row): ?>


	<?php if( $count % 3 == 0): ?>
		<?php if ($count > 0): ?>
			</div>
		<?php endif ?>
			<!-- Restart the row -->
			<div id="project_container">
		<?php endif ?>
	    <div class="four columns mobile-four projectItem">
	      <div class="project-box"  id="project-<?php echo $row->id; ?>">
	        <h5 class="name"><?php echo anchor('admin/projects/view/'. $row->id, $row->name); ?></h5>
	        <p class="half-bottom">
	        	<span class="g16 user"></span>
	        	<?php echo (isset($row->company) && $row->company != '') ? $row->company : $row->first_name.' '.$row->last_name; ?>
	        </p>
	        <p><span class="g16 cal"></span> <?php echo $row->due_date ? format_date($row->due_date) : 'n/a'; ?></p>

	        <!-- Assigned area for members/clients linked to the projects here -->
	        <span class="members-assigned">

				<?php foreach($row->users as $user): ?>
					<img src="<?php echo get_gravatar($user['email'], '40') ?>" class="members-pic" />
				<?php endforeach ?>

	        </span>
	      </div><!-- project box -->
	      <div class="project-footer">
                  <?php if (!is_object($row)) debug($row);?>
	       <strong><?php echo __("projects:tracked")?>:</strong> <?php echo $this->project_m->getTotalHoursForProject($row->id, true);?>
	       <?php if (can('update', get_client('projects', $row->id), 'projects', $row->id)): ?>
               <ul class="invoice-buttons">
	         <li class="settings">
	           <ul>
				<?php if (can('update', get_client('projects', $row->id), 'projects', $row->id) or can('generate_from_project', get_client('projects', $row->id), 'projects', $row->id)): ?>
				 	<li><a href="<?php echo site_url('admin/projects/edit/'.$row->id); ?>" class="fire-ajax no-bottom"><?php echo __('global:edit') ?></a></li>

					<?php if (! $row->is_archived ): ?>
						 <li><a href="<?php echo site_url('admin/projects/archive/' . $row->id); ?>"><?php echo __('projects:archive_proj') ?></a></li>
					<?php else: ?>
						<li><a href="<?php echo site_url('admin/projects/unarchive/' . $row->id); ?>"><?php echo __('projects:unarchive_proj') ?></a></li>
					<?php endif ?>
				 <?php endif; ?>
		 	    <?php if (can('generate_from_project', get_client('projects', $row->id), 'projects', $row->id)): ?>
			   	    <li><a title="Project Settings" href="<?php echo site_url('admin/invoices/create/' . $row->id); ?>"><?php echo lang('invoices:newinvoice') ?></a></li>
			   	<?php endif ?>
	           </ul>
	         </li>
	       </ul>
               <?php endif; ?>

	       <!-- Project Percentage of Tasks -->
	        <?php if ($row->total_tasks > 0): ?>
	           <?php
	             $percentNumber = number_format(($row->complete_tasks / $row->total_tasks) * 100, 1);
	             if ($percentNumber == 100) {
	               echo '<div class="percent project_complete">' . $percentNumber . '%</div>';
	             } else {
	               //echo '<div class="percent" style="background: #4caed7;">' . $percentNumber . '%</div>';
				   echo '<div class="percent project_inprogress" >' . $percentNumber . '%</div>';
	             }
	           ?>
	         <?php else: ?>
	           <div class="percent project_na">N/A</div>
	        <?php endif; ?>
	      </div><!-- "" -->
	    </div><!-- four -->

		<?php $count++ ?>
	    <?php endforeach; ?>
		</div>



	</div><!-- /projectholder -->
</div><!-- /row -->
<?php endif ?>