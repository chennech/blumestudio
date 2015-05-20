<h2><?php echo __('transactions:paymentfailed');?></h2>
<p><?php echo __('transactions:extrapaymentfailed', array(Business::getAdminName(), Business::getNotifyEmail()));?></p>
<a href="<?php echo site_url($unique_id);?>">Back to Invoice</a>