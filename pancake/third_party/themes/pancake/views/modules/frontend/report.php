<table class="report-contents timesheet-table">
    <thead>
        <tr>
            <?php $i = 0; ?>
            <?php foreach ($fields as $field => $title) : ?>
                <?php if ($field == 'taxes') : ?>
                    <th colspan="<?php echo $i; ?>"></th>
                    <?php foreach (array_keys($totals['taxes']) as $tax_id): ?>
                        <th class="taxes" colspan="3"><?php echo str_ireplace('{tax}', $taxes[$tax_id], $title); ?></th>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php $i++; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
        </tr>
        <tr>
            <?php foreach ($fields as $field => $title) : ?>
                <?php if ($field == 'taxes' or $field == 'collected_taxes') : ?>
                    <?php foreach (array_keys($totals[$field]) as $tax_id): ?>
                        <?php if ($field != "collected_taxes"): ?>
                            <th><?php echo __('reports:uncollected'); ?></th>
                            <th><?php echo __('reports:collected'); ?></th>
                            <th><?php echo __('invoices:total'); ?></th>
                        <?php else: ?>
                            <th><?php echo $taxes[$tax_id]; ?></th>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <th class="<?php echo $field; ?>"><?php echo $title; ?></th>
                <?php endif; ?>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($records as $record) : ?>
            <tr>
                <?php foreach (array_keys($fields) as $field) : ?>
                    <?php if ($field == 'taxes' or $field == 'collected_taxes') : ?>
                        <?php foreach (array_keys($totals[$field]) as $tax_id): ?>
                            <?php if ($field != "collected_taxes"): ?>
                                <td><?php echo Currency::format((isset($record['total_taxes'][$tax_id]) and isset($record[$field][$tax_id])) ? ($record['total_taxes'][$tax_id] - $record[$field][$tax_id]) : 0, Currency::code($record['currency_id'])); ?></td>
                                <td><?php echo Currency::format(isset($record[$field][$tax_id]) ? $record[$field][$tax_id] : 0, Currency::code($record['currency_id'])); ?></td>
                                <td><?php echo Currency::format(isset($record['total_taxes'][$tax_id]) ? $record['total_taxes'][$tax_id] : 0, Currency::code($record['currency_id'])); ?></td>
                            <?php else: ?>
                                <td><?php echo Currency::format(isset($record["taxes"][$tax_id]) ? $record["taxes"][$tax_id] : 0, Currency::code($record['currency_id'])); ?></td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <td><?php
                            switch ($field) {
                                case 'due_date':
                                case 'payment_date':
                                    echo format_date($record[$field]);
                                    break;
                                case 'client':
                                    echo htmlentities($record[$field]);
                                    break;
                                case 'payment_method':
                                    echo isset($gateways[$record[$field]]) ? $gateways[$record[$field]]['title']."<br />".$record["txn_id"] : __('global:na');
                                    break;
                                case 'invoice_number':
                                    echo '<a href="' . site_url($record['unique_id']) . '">#' . ($record[$field]) . '</a><br /><a class="hide-pdf" href="' . site_url('admin/invoices/edit/' . $record['unique_id']) . '">[' . __("global:edit") . ']</a> <a class="hide-pdf" href="' . site_url('pdf/' . $record['unique_id']) . '">[PDF]</a>';
                                    break;
                                case 'is_billed':
                                    echo $record[$field] ? __("global:yes") . "<br><a target='_blank' href='" . site_url($record['unique_id']) . "'>#" . $record['invoice_number'] . "</a>" : __("global:no");
                                    break;
                                case 'project':
                                case 'name':
                                case 'supplier':
                                case 'category':
                                    echo htmlentities($record[$field]);
                                    break;
                                default:
                                    echo Currency::format($record[$field], Currency::code($record['currency_id']));
                                    break;
                            }
                            ?></td>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <?php foreach (array_keys($fields) as $field) : ?>
                <?php if (isset($totals[$field])) : ?>
                    <?php if ($field == 'taxes' or $field == 'collected_taxes') : ?>
                        <?php foreach (array_keys($totals[$field]) as $tax_id): ?>
                            <?php if ($field != "collected_taxes"): ?>
                                <th class="<?php echo $field; ?>"><?php echo Currency::format($totals[$field][$tax_id]['uncollected']); ?></th>
                                <th class="<?php echo $field; ?>"><?php echo Currency::format($totals[$field][$tax_id]['collected']); ?></th>
                                <th class="<?php echo $field; ?>"><?php echo Currency::format($totals[$field][$tax_id]['total']); ?></th>
                            <?php else: ?>
                                <th class="<?php echo $field; ?>"><?php echo Currency::format($totals[$field][$tax_id]['collected']); ?></th>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <th class="<?php echo $field; ?>"><?php echo Currency::format($totals[$field]); ?></th>
                    <?php endif; ?>
                <?php else: ?>
                    <th></th>
                <?php endif; ?>
            <?php endforeach; ?>
        </tr>
    </tfoot>
</table>