<div class="client-item row">
    <div class="client-info row">
        <div class="ten columns mobile-three">
            <img src="<?php echo get_gravatar($row->email, '50') ?>" class="client-user-pic" />
            <div style="margin-left: 70px;">
                <span class="f-thin-black"><a href="<?php echo site_url('admin/clients/view/' . $row->id); ?>"><?php echo client_name($row); ?></a></span>

            <br />

            <span class="contact address"></span> <span class="contact-text"><a href="<?php echo site_url(Settings::get('kitchen_route') . '/' . $row->unique_id); ?>"><?php echo __("global:client_area"); ?></a></span>

            <?php
            if ($row->phone || $row->mobile) {
                if ($row->phone) {
                    echo '<span class="contact phone">'.__('global:phone').'</span> <span class="contact-text"><a href="#" data-client="' . $row->id . '">' . $row->phone . '</a></span>';
                }
                if ($row->mobile) {
                    echo '<span class="contact mobile">'.__('global:mobile').'</span> <span class="contact-text"><a href="#" data-client="' . $row->id . '">' . $row->mobile . '</a></span>';
                }
                if ($row->phone and $row->mobile) {
                    echo "<br />";
                }
            }
            ?>
            <span class="contact email"><?php echo __('global:email'); ?></span> <span class="contact-text"><?php echo mailto($row->email) ?></span>
            <br />
            <?php if (isset($custom[$row->id])): ?>
                <?php foreach ($custom[$row->id] as $field => $details): ?>
                <?php if (trim($details['value']) != ""): ?>
                    <span class="contact-text"><strong><?php echo $details['label']; ?></strong> <?php echo $details['value']; ?></span><br />
                <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
                    </div>
        </div><!-- /ten -->
        <div class="two columns projects mobile-one">
            <?php echo __("global:projects"); ?> <br />
            <span class="project-count"><?php echo $row->project_count; ?></span>
        </div><!-- /two -->
    </div><!-- /client-info-->

    <div class="client-extra row">
        <div class="three columns mobile-one"><strong><?php echo __('global:unpaid'); ?>:</strong> <?php echo Currency::format($row->unpaid_total); ?></div>
        <div class="three columns mobile-one"><strong><?php echo __('global:paid'); ?></strong> <?php echo Currency::format($row->paid_total); ?></div>
        <div class="three columns mobile-one">
            <div class="healthCheck">
                <span class="healthBar"><span class="paid" style="width:<?php echo $row->health['overall']; ?>%"></span></span>
            </div><!-- /healthCheck -->
        </div><!-- /three -->
        <div class="three columns align-right mobile-one">
            <?php if (can('delete', $row->id, 'clients', $row->id)): ?>
    <?php echo anchor('admin/clients/delete/' . $row->id, lang('global:delete'), array('class' => 'icon delete', 'title' => __('global:delete'))); ?>
<?php endif ?>

<?php if (can('update', $row->id, 'clients', $row->id)): ?>
    <?php echo anchor('admin/clients/edit/' . $row->id, __('global:edit'), array('class' => 'icon edit', 'title' => __('global:edit'))); ?>
<?php endif ?>
        </div><!-- /three-->
    </div><!-- /client-exra-->
</div><!-- /client-item -->