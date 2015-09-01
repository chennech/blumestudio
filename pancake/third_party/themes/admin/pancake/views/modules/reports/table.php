
<?php foreach ($rows as $row): ?>

    <?php
    $client_details = trim($row->client_name);
    $buffer = trim(isset($row->proposal_number) ? $row->client_company : $row->company);
    $client_details = empty($buffer) ? $client_details : $client_details . ' - ' . $buffer;
    $phone = isset($row->proposal_number) ? $row->client->phone : $row->phone;
    $email = isset($row->proposal_number) ? $row->client->email : $row->email;

    $client_url = site_url('admin/clients/view/' . (isset($row->proposal_number) ? $row->client->id : $row->client_id));
    ?>

    <?php $permission_module = isset($row->proposal_number) ? 'proposals' : 'invoices'; ?>

    <?php if (isset($row->proposal_number)) : ?>
        <div class="invoice-container"><div class="invoice-item <?php echo isset($row->proposal_number) ? 'proposal' : 'invoice' ?>" id="invoice_<?php echo $row->unique_id; ?>">
                <div class="nine columns invoice-body">
                    <div class="row">
                        <div class="three columns mobile-four">
                            <span class="invoice-banner">#<?php echo $row->proposal_number ?></span>
                        </div><!-- /three -->

                        <div class="nine columns">
                            <h4><span class="invoice-client"><?php echo $row->title; ?></span>

                                <span class="invoice-company">(<a class="color-inherit" href="<?php echo $client_url; ?>"><?php echo $client_details; ?></a>)</span>
                                <div class="invoice-details">
        <?php echo ucfirst(($row->last_viewed > 0) ? (__('proposals:lastviewed', array(format_date($row->last_viewed), format_time($row->last_viewed)))) : __('proposals:neverviewed')) ?><br />
                                    <?php echo empty($email) ? '' : 'Email: ' . $email; ?> <?php echo empty($phone) ? '' : 'Phone: ' . $phone; ?><br>
                                 </div>

                        </div><!-- /nine -->

                    </div><!-- /row -->

                    <div class="row fixed-bottom">
                        <div class="three columns">
                            <ul class="invoice-buttons">
                                <li><?php echo anchor((isset($row->proposal_number) ? 'proposal/' : '') . $row->unique_id, __('global:view'), array('class' => 'preview', 'title' => __('global:view'))); ?></li>

        <?php if (can('send', $row->client_id, $permission_module, $row->id)): ?>
                                <li><?php echo anchor('admin/' . (isset($row->proposal_number) ? 'proposals/send' : human_invoice_type($row->type) . '/created') . '/' . $row->unique_id, __('global:send_to_client'), array('class' => 'email', 'title' => __('global:send_to_client'))); ?>
                                <?php endif ?>

                                <li><a class="settings" href="#">Settings</a>
                                    <ul class="settings-dropdown">
        <?php if (isset($row->type) or isset($row->proposal_number) and can('update', $row->client_id, $permission_module, $row->id)) : ?>
                                            <li><a href="<?php echo site_url((isset($row->proposal_number) ? 'proposal/' : 'pdf/') . $row->unique_id . (isset($row->proposal_number) ? '/pdf' : '')); ?>"><?php echo __('global:viewpdf'); ?></a></li>
                                        <?php endif; ?>

                                        <?php if ((isset($row->type) and ($row->type == 'DETAILED' or $row->type == 'SIMPLE')) and can('update', $row->client_id, $permission_module, $row->id)): ?>
                                            <?php if (!$row->paid) : ?>
                                                <li><a href="#" class="add_payment" data-invoice-unique-id="<?php echo $row->unique_id; ?>"><span><?php echo __('partial:add_payment'); ?></span></a></li>
                                            <?php endif; ?>

                                            <?php if ($row->part_count == 1): ?>
                                                <li><a href="#" class="partial-payment-details invoice_<?php echo $row->unique_id; ?> key_1" data-details="1" data-invoice-unique-id="<?php echo $row->unique_id; ?>"><span><?php echo __('partial:' . (($row->paid) ? 'paymentdetails' : 'markaspaid')); ?></span></a></li>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if ((isset($row->type) and ($row->type == 'DETAILED')) and ($row->last_sent == 0)) : ?>
                                            <li><a href="#" class="mark-as-sent" data-invoice-unique-id="<?php echo $row->unique_id; ?>"><span><?php echo __('invoices:markassent'); ?></span></a></li>
                                        <?php endif; ?>

                                        <?php if (can('create', $row->client_id, $permission_module)): ?>
                                            <li><a href="<?php echo site_url('admin/' . (isset($row->proposal_number) ? 'proposals' : human_invoice_type($row->type)) . '/duplicate/' . $row->unique_id); ?>"><?php echo __('global:duplicate') ?></a></li>
                                        <?php endif ?>

                                        <?php if (can('update', $row->client_id, $permission_module, $row->id)): ?>
                                            <li><?php echo anchor('admin/' . (isset($row->proposal_number) ? 'proposals' : human_invoice_type($row->type)) . '/edit/' . $row->unique_id, __('global:edit'), array('class' => '', 'title' => __('global:edit'))); ?></li>
                                        <?php endif; ?>

                                        <?php if (isset($row->type) and $row->type == 'ESTIMATE') : ?>
                                            <li><a href="<?php echo site_url('admin/estimates/convert/' . $row->unique_id); ?>"><?php echo $row->project_id > 0 ? __('global:appendtoproject', array($this->project_m->get_dropdown_per_client($row->client_id, $row->project_id))) : __('global:converttoproject'); ?></a></li>
                                            <li><a href="<?php echo site_url('admin/estimates/convert_to_invoice/' . $row->unique_id); ?>"><?php echo __('global:converttoinvoice'); ?></a></li>
        <?php endif; ?>

                                        <?php if (isset($row->type) and $row->type == 'DETAILED') : ?>
                                            <li><a href="<?php echo site_url('admin/invoices/convert_to_invoice/' . $row->unique_id); ?>"><?php echo __('global:converttoestimate'); ?></a></li>
                                        <?php endif; ?>

                                        <?php if (can('delete', $row->client_id, $permission_module, $row->id)): ?>
                                            <li><?php echo anchor('admin/' . (isset($row->proposal_number) ? 'proposals' : human_invoice_type($row->type)) . '/delete/' . $row->unique_id, __('global:delete'), array('title' => __('global:delete'))); ?></li>
                                        <?php endif ?>
                                    </ul>
                                </li><!-- settings column-->
                            </ul>
                        </div><!-- /three -->
                        <div class="three columns end invoice-paid"></div>

                        <div class="three columns end invoice-total"></div>

                        <div class="two columns end invoice-due"></div>

                    </div><!-- /row-->

                </div>
                <div class="three columns invoice-outstanding">
                    <div>
        <?php $amount = ', estimated at <span class="' . ($row->status == 'ACCEPTED' ? 'paid' : 'unpaid') . '-amount">' . Currency::format($row->amount) . '</span>'; ?>


        <?php $amount = '<span class=" total-amount ' . ($row->status == 'ACCEPTED' ? 'paid' : 'unpaid') . '-amount">' . Currency::format($row->amount) . '</span>'; ?>
                        <?php echo $amount; ?>

                        <small><?php echo __('proposals:' . (!empty($row->status) ? strtolower($row->status) : 'noanswer'), array(format_date($row->last_status_change))); ?></small>
                    </div>
                </div><!-- /total-->
            </div>
        </div>
        <br class="clear" /> <br />
    <?php endif ?>

    <?php // End Proposal section  ?>

    <?php /*  ### Begin Invoices section ###    */ ?>
    <?php if (!isset($row->proposal_number)): ?>

        <div class="invoice-container"><div class="invoice-item add-bottom" id="invoice_<?php echo $row->unique_id; ?>">
                <div class="nine columns invoice-body <?php if (($row->paid == 1 && $row->type == 'DETAILED')): ?>paid<?php endif ?><?php if ($row->paid == 0 && $row->overdue != 1 && $row->type == 'DETAILED'): ?>unpaid<?php endif ?><?php if ($row->overdue == 1 && $row->type == 'DETAILED'): ?>overdue<?php endif ?>">
                    <div class="row">
                        <div class="three columns mobile-four">
        <?php if (($row->paid == 1 && $row->type == 'DETAILED')): ?>
                                <span class="invoice-banner paid"><?php echo __('global:paid'); ?></span>
                            <?php endif ?>

                            <?php if ($row->paid == 0 && $row->overdue != 1 && $row->type == 'DETAILED'): ?>
                                <span class="invoice-banner unpaid"><?php echo __('global:unpaid'); ?></span>
                            <?php endif ?>

                            <?php if ($row->overdue == 1 && $row->type == 'DETAILED'): ?>
                                <span class="invoice-banner overdue"><?php echo __('global:overdue'); ?></span>
                            <?php endif ?>

                            <?php if ($row->type == 'ESTIMATE' or $row->type == 'CREDIT_NOTE'): ?>
                                <span class="invoice-banner"><?php echo anchor('admin/'.human_invoice_type($row->type).'/edit/' . $row->unique_id, '#' . $row->invoice_number); ?></span>
                            <?php endif ?>
                        </div><!-- /three -->

                        <div class="nine columns mobile-four">
                            <h4 class="half-bottom">

<?php

switch ($row->type) {
    case 'ESTIMATE':
        $title_wording = "global:estimate";
        break;
    case "CREDIT_NOTE":
        $title_wording = "global:credit_note";
        break;
    default:
        $title_wording = Settings::get('default_invoice_title');
        break;
}

$title_wording = __($title_wording);

?>

                                <span class="invoice-client">
        <?php echo $title_wording; ?>
                                    <?php echo anchor('admin/invoices/edit/' . $row->unique_id, '#' . $row->invoice_number); ?></span>

                                <span class="invoice-company">(<a class="color-inherit" href="<?php echo $client_url; ?>"><?php echo $client_details; ?></a>)</span>
                                <div class="invoice-details">
        <?php echo ucfirst(($row->last_viewed > 0) ? (__('proposals:lastviewed', array(format_date($row->last_viewed), format_time($row->last_viewed)))) : __('proposals:neverviewed')) ?><br />
                                    <?php echo empty($email) ? '' : 'Email: ' . $email; ?> <?php echo empty($phone) ? '' : 'Phone: ' . $phone; ?>

                                    <?php if ($row->is_recurring and $row->auto_send and $row->last_sent == 0): ?>
                                        <br />
                                        <?php echo __('invoices:willbesentautomatically', array(format_date($row->date_to_automatically_notify))); ?>
                                    <?php endif; ?>

                                    <?php if ($row->is_recurring) : ?>
                                            <br />
                                        <?php if ($row->id == $row->recur_id) : ?>

                                            <?php $last_recurrence = $this->invoice_m->get_last_reoccurrence($row->id); ?>
                                            <?php if (isset($last_recurrence['id'])): ?>
                                                <?php echo __('invoices:lastreoccurrence', array(anchor('admin/invoices/edit/' . $last_recurrence['unique_id'], '#' . $last_recurrence['invoice_number']))) ?><br />
                                            <?php endif; ?>
                                            <?php echo __('invoices:willreoccurin', array(format_date($this->invoice_m->getNextInvoiceReoccurrenceDate($row->id)))) ?>
                                        <?php else: ?>
                                            <?php echo __('invoices:thisisareoccurrence', array(anchor('admin/invoices/edit/' . $this->invoice_m->getUniqueIdById($row->recur_id), '#' . $this->invoice_m->getInvoiceNumberById($row->recur_id)))); ?>
                                        <?php endif; ?>

                                    <?php endif; ?>
                                </div>

                            </h4>
                            <p><small><?php echo $row->description; ?></small></p>
                        </div><!-- /ten -->
                    </div><!-- /row -->


                    <div class="row fixed-bottom">
                        <div class="three columns mobile-two">
                            <ul class="invoice-buttons">
                                <li><?php echo anchor((isset($row->proposal_number) ? 'proposal/' : '') . $row->unique_id, __('global:view'), array('class' => 'preview', 'title' => __('global:view'))); ?></li>
                                <li><a class="email" href="<?php echo site_url('admin/invoices/created/' . $row->unique_id); ?>" title="<?php echo __(($row->type == "ESTIMATE" ? "estimates" : "invoices").':send_to_client'); ?>"></a></li>
                                <li><a class="settings" href="#"></a>
                                    <ul class="settings-dropdown more-actions">
                                        <li><a href="<?php echo site_url((isset($row->proposal_number) ? 'proposal/' : 'pdf/') . $row->unique_id . (isset($row->proposal_number) ? '/pdf' : '')); ?>"><?php echo __('global:viewpdf'); ?></a></li>
                                        <li><?php echo anchor('admin/invoices/edit/' . $row->unique_id, '' . __('global:edit')); ?></li>

        <?php if ((isset($row->type) and ($row->type == 'DETAILED' or $row->type == 'SIMPLE')) and can('update', $row->client_id, $permission_module, $row->id)): ?>
                                            <?php if (!$row->paid) : ?>
                                                <li><a href="#" class="add_payment" data-invoice-unique-id="<?php echo $row->unique_id; ?>"><span><?php echo __('partial:add_payment'); ?></span></a></li>
                                            <?php endif; ?>

                                            <?php if ($row->part_count == 1): ?>
                                                <li><a href="#" class="partial-payment-details invoice_<?php echo $row->unique_id; ?> key_1" data-details="1" data-invoice-unique-id="<?php echo $row->unique_id; ?>"><span><?php echo __('partial:' . (($row->paid) ? 'paymentdetails' : 'markaspaid')); ?></span></a></li>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <?php if ((isset($row->type) and ($row->type == 'DETAILED' or $row->type == 'SIMPLE')) and ($row->last_sent == 0)) : ?>
                                            <li><a href="#" class="mark-as-sent" data-invoice-unique-id="<?php echo $row->unique_id; ?>"><span><?php echo __('invoices:markassent'); ?></span></a></li>
                                        <?php endif; ?>

                                        <?php if (can('create', $row->client_id, $permission_module)): ?>
                                            <li><a href="<?php echo site_url('admin/' . (isset($row->proposal_number) ? 'proposals' : human_invoice_type($row->type)) . '/duplicate/' . $row->unique_id); ?>"><?php echo __('global:duplicate') ?></a></li>
                                        <?php endif ?>

                                        <?php if (isset($row->type) and $row->type == 'ESTIMATE') : ?>
                                            <li><a href="<?php echo site_url('admin/estimates/convert/' . $row->unique_id); ?>"><?php echo $row->project_id > 0 ? __('global:appendtoproject', array($this->project_m->get_dropdown_per_client($row->client_id, $row->project_id))) : __('global:converttoproject'); ?></a></li>
                                            <li><a href="<?php echo site_url('admin/estimates/convert_to_invoice/' . $row->unique_id); ?>"><?php echo __('global:converttoinvoice'); ?></a></li>
        <?php endif; ?>

                                        <?php if (isset($row->type) and $row->type == 'DETAILED') : ?>
                                            <li><a href="<?php echo site_url('admin/invoices/convert_to_invoice/' . $row->unique_id); ?>"><?php echo __('global:converttoestimate'); ?></a></li>
                                        <?php endif; ?>

                                        <?php if (can('delete', $row->client_id, $permission_module, $row->id)): ?>
                                            <li><?php echo anchor('admin/' . (isset($row->proposal_number) ? 'proposals' : human_invoice_type($row->type)) . '/delete/' . $row->unique_id, __('global:delete'), array('title' => __('global:delete'))); ?></li>
                                        <?php endif ?>
                                    </ul>
                                </li><!-- settings column-->
                            </ul>
                        </div><!-- /two -->

                                <div class="three columns mobile-two invoice-total">
                                    <?php echo __("invoices:total"); ?>: <?php echo Currency::format($row->billable_amount, $row->currency_symbol); ?>
                                </div>

                                <div class="six columns mobile-two" style="text-align: right;">
                                    <?php if (isset($row->type) and $row->type == 'DETAILED') : ?>
                                        <?php if ($row->paid_amount > 0) : ?>
                                            <span class="invoice-paid">
                                                <?php echo __('global:paid') ?>: <?php echo Currency::format($row->paid_amount, $row->currency_symbol); ?>
                                            </span>
                                        <?php endif ?>
                                        <?php if ($row->unpaid_part_count > 0) : ?>
                                            <span class="invoice-unpaid">
                                                <?php echo __('global:unpaid') ?>: <?php echo Currency::format($row->unpaid_amount, $row->currency_symbol); ?>
                                            </span>
                                        <?php endif ?>
                                    <?php endif; ?>
                                </div>

                    </div><!-- /row-->
                </div><!-- /invoice-item -->
                <div class="three columns mobile-four invoice-outstanding">
                    <div>

                        <?php if ($row->type == 'DETAILED'): ?>
                            <p class="no-bottom"><small><?php echo __('invoices:due') ?>: <?php echo ($row->due_date > 0) ? format_date($row->due_date) : 'n/a'; ?></small></p>
                        <?php endif ?>

                        <span class="total-amount half-bottom">
                            <?php echo Currency::format($row->billable_amount, $row->currency_symbol); ?>
                        </span>

                        <p class="no-bottom"><small>
                        <?php if ($row->type == 'ESTIMATE' or $row->type == 'CREDIT_NOTE') : ?>
                            <?php echo __('global:'.strtolower(singular(human_invoice_type($row->type)))); ?>
                        <?php else: ?>
                            <?php if (isset($row->paid) and $row->paid) : ?>
                                <?php echo __('invoices:paidon', array(format_date($row->payment_date))); ?>.
                            <?php else: ?>
                                <?php echo (isset($row->last_sent) and $row->last_sent > 0) ? __('invoices:senton', array(format_date($row->last_sent))) . '' : __('global:notyetsent') . ''; ?>.
                            <?php endif; ?>
                        <?php endif; ?>


                            </small></p>
                    </div>
                </div><!-- /total-->
            </div><!-- /row -->
        </div>
        <br class="clear" /> <br />

    <?php endif; ?>
<?php endforeach; ?>



<script>
    $(".close-reveal-modal").click(function() {
        $(".reveal-modal").trigger("reveal:close");
    });
</script>