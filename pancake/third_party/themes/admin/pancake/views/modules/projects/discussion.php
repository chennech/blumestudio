<?php

// Page for Project Discussion

?>

<div id="header">
     <div class="row">
       <h2 class="ttl ttl3"><?php echo lang('tasks:discussion'); ?> <?php echo $task->name ?></h2>
	   <?php echo $template['partials']['search']; ?>
     </div>
</div>

<div class="row" style="height: 100%;">
  <div class="nine columns content-wrapper" id="comment-holder">

    <?php if (count($comments) > 0): ?>
	   <div id="comments">
      <?php foreach ($comments as $comment): ?>
        	<div class="comment <?php echo $comment->user_id == $this->current_user->id ? 'you' : ''; ?>">
	        	<div class="message">
             <?php if (isset($comment->user)): ?>
		    		    <span class="comment-img"><img src="<?php echo get_gravatar($comment->user->email, 40) ?>" /></span>
             <?php elseif (isset($comment->client)): ?>
                <span class="comment-img"><img src="<?php echo get_gravatar($comment->client['email'], 40) ?>" /></span>
             <?php endif; ?>
            <div class="comment-body">
	            <div class="comment-dot"></div>
							<div class="comment-area <?php echo $comment->is_private ? "private": "";?>">

				        <?php echo auto_typography(auto_link($comment->comment)); ?>

								<!-- Can we have a if has files here? This would clean up the UI of the chat system a little -->
								<div class="files">
									<ul class="list-of-files">
									<?php foreach ($comment->files as $file): ?>

										<?php $ext = explode('.', $file->orig_filename); end($ext); $ext = current($ext); ?>
								          <?php $bg = asset::get_src('file-types/'.$ext.'.png', 'img'); ?>
								          <?php $style = empty($bg) ? '' : 'style="background: url('.$bg.') 1px 0px no-repeat;"'; ?>

										<?php $ext = explode('.', $file->orig_filename); end($ext); $ext = current($ext); ?>

										<?php if($ext == 'png' OR $ext == 'jpg' OR $ext == 'gif'): ?>
											<div class="image-preview">
												<p><img src="<?php echo site_url('/uploads/'.$file->real_filename);?>" style="max-width:50%" /></p>
											</div>
										<?php endif ?>


										<li><a class="file-to-download" <?php echo $style;?> href="<?php echo site_url(Settings::get('kitchen_route').'/'.$client['unique_id'].'/download/'.$comment->id.'/'.$file->id);?>"><?php echo $file->orig_filename;?></a></li>

							  		<?php endforeach; ?>
									</ul>
								</div><!-- /files -->
							</div><!-- /comment-area -->

							<br class="clear" />

							<div class="comment-details">
								<?php echo __("global:by_x_with_time", array('<a name="'.$comment->id.'" title="'.format_date($comment->created).'">'.$comment->user_name.'</a>', better_timespan($comment->created))); ?> <?php echo $comment->is_private ? __("global:private_comment") : "";?>
							</div>
					</div><!-- /comment-body -->
				</div><!--/message -->
	    </div><!-- /comment -->
    <?php endforeach; ?>
  </div><!-- /comments -->
    <?php endif; ?>

    <div class="new-comment form-holder">

    		<?php echo form_open_multipart('/admin/projects/tasks/discuss', 'id="comment-form"');?>
    	  <h4><?php echo __('kitchen:submitcomment'); ?>:</h4>
    	  <textarea rows="3" name="comment"></textarea>
    	  <div class="row">
      			<div class="eight columns file-holder">
      				<label for="file" style="float: left; margin-right: 10px;"><?php echo __('global:attach_file', array(get_max_upload_size())) ?>:</label>
      				<?php echo form_upload('files[]'); ?>
      			</div>

            <div class="two columns align-right">
              <label><?php echo __("global:is_private"); ?> <input type="checkbox" name="is_private" checked="checked"/></label>
              <p><?php echo __("global:clients_cant_see_private"); ?></p>
            </div>

      			<div class="two columns align-right">
    				<button type="submit" class="blue-btn"><?php echo __('kitchen:submitcomment'); ?></button>
    				<input type="hidden" name="task_id" value="<?php echo $task->id; ?>">
      			</div>

    	  </div><!-- /row-->
    	</form>

      </div> <!-- /new-comment -->

  </div><!-- /nine columns content-wrapper-->

<div class="three columns side-bar-wrapper">
	<div class="panel">
		<h4 class="sidebar-title"><?php echo __('kitchen:people_in_discussion') ?></h4>
		<ul class="active-users">
            <?php foreach ($chatters as $chatter): ?>
                <li><img src="<?php echo get_gravatar($chatter['email'], 35); ?>" /> <span><?php echo $chatter['first_name'] . " " . $chatter['last_name']; ?></span> </li>
            <?php endforeach; ?>
		</ul>

		<br class="clear"/>
                
	</div><!-- /panel -->
</div><!-- /three columns side-bar-wrapper -->

</div><!-- row -->
<?php echo asset::js('jquery.history.js'); ?>
<script type="text/javascript">
    var client_id = <?php echo $client_id;?>;

    $.history.init(function(hash){
        if(hash == "create") {
        $(document).ready(function() {
            $('#create_project').click();
        });
        } else {
        }
    },
    { unescape: ",/" });

    // fix for modal close
    $(".close-reveal-modal").click(function () {
    		 $(".reveal-modal").trigger("reveal:close");
    });

</script>