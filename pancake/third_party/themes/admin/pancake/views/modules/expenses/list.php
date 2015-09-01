<!-- Add and List Expenses -->

<div id="header">
    <div class="row">
        <h2 class="ttl ttl3"><?php echo __('expenses:expenses') ?></h2>
        <?php echo $template['partials']['search']; ?>
    </div>
</div>

<!-- Add Expense form -->
<div id="add-time-form" class="row form-holder content-wrapper">
    <h4 class="twelve columns add-bottom"><?php echo __('expenses:add') ?></h4>

    <br class="clear" />

    <?php if (is_admin() and ( $this->db->where("deleted", 0)->count_all("project_expenses_categories") == 0 || $this->db->where("deleted", 0)->count_all_results("project_expenses_suppliers") == 0)): ?>
        <div class="twelve columns">
            <div class="help add-bottom">
                <p style="margin: 10px 0;"><?php echo __("expenses:before_you_can_add_expenses", array('<a href="'.site_url('admin/expenses/suppliers').'">'.__('expenses:suppliers').'</a>', '<a href="'.site_url('admin/expenses/categories').'">'.__('expenses:categories').'</a>')); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <?php echo form_open_multipart('admin/expenses/create/', array('name' => 'add_expense', 'id' => 'add_expense')); ?>

    <div class="twelve columns">
        <div class="row">
            <div class="two columns mobile-four">
                <label for="name"><?php echo __("expenses:name"); ?></label>
                <input type="text" name="name" id="name" class="txt" placeholder="" />
            </div>

            <div class="two columns mobile-four">
                <label for="rate"><?php echo __("invoices:amount"); ?></label>
                <input type="text" name="rate" id="rate" class="txt" placeholder="" />
            </div>

            <div class="two columns mobile-four">
                <label for="category_id"><?php echo __("expenses:category"); ?></label>
                <span class="sel-item">
                    <select name="category_id">
                        <option value=""><?php echo __("global:select"); ?></option>
                        <?php foreach ($categories as $category): ?>

                            <?php if (!empty($category->categories)): ?>
                                <optgroup label="<?php echo $category->name ?>">
                                    <?php foreach ($category->categories as $subcat): ?>
                                        <option value="<?php echo $subcat->id ?>"><?php echo $subcat->name ?></option>
                                    <?php endforeach ?>
                                </optgroup>
                            <?php else: ?>
                                <option value="<?php echo $category->id ?>"><?php echo $category->name ?></option>
                            <?php endif; ?>

                        <?php endforeach ?>
                    </select>
                </span>
            </div>

            <div class="two columns mobile-four">
                <label for="supplier_id"><?php echo __("expenses:supplier"); ?></label>
                <span class="sel-item">
                    <select name="supplier_id">
                        <option value=""><?php echo __("global:select"); ?></option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo $supplier->id ?>"><?php echo $supplier->name ?></option>
                        <?php endforeach ?>
                    </select>
                </span>
            </div>

            <div class="four columns mobile-four">
                <label for="project_id"><?php echo __("global:project"); ?></label>
                <span class="sel-item">
                    <select name="project_id">
                        <option value=""><?php echo __('expenses:no_project_business_expense'); ?></option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project->id ?>"><?php echo $project->name ?></option>
                        <?php endforeach ?>
                    </select>
                </span>
            </div>
        </div>
    </div>

    <div class="twelve columns">
        <div class="row">
            <div class="four columns mobile-four">
                <label for="receipt"><?php echo __("expenses:attach_receipt", array(get_max_upload_size())); ?></label>
                <input type="file" name="receipt" id="receipt" />
            </div>
            <div class="two columns mobile-four">
                <label for="due_date"><?php echo __('expenses:expense_date'); ?></label>
                <?php echo form_input('date', ($date = set_value('due_date', isset($time) ? $time->date : time())) ? format_date($date) : '', 'id="date" class="datePicker"'); ?>
            </div>
            <div class="six columns mobile-four">
                <label for="description"><?php echo __("global:description"); ?></label>
                <?php echo form_textarea('description', set_value('description'), 'rows="4" placeholder="" class="txt add-time-note"'); ?>
            </div>
        </div>
    </div>

    <div class="twelve columns mobile-four" style="margin-top: 10px;">
        <?php assignments('project_expenses'); ?>
    </div>

    <div class="two columns mobile-four" style="margin-top: 20px;margin-bottom: 10px;">
        <a href="#" class="blue-btn js-fake-submit-button"><span><?php echo __('expenses:add'); ?></span></a>
        <input type="submit" class="hidden-submit" />
    </div>

    <?php echo form_close(); ?>
</div><!-- /row -->



<!-- Filters -->
<div id="sort-entries-fields" class="row content-wrapper">

    <h3 class="twelve columns"><?php echo __('expenses:expenses') ?></h3>

    <div class="twelve columns">
        <p><?php echo anchor('/admin/expenses/sort_form/', __("expenses:sort_or_filter"), array('class' => 'blue-btn fire-ajax')) ?></p>

    </div><!-- /12 -->

    <div class="height_transition twelve columns">
        <div class="view_entries_table">

            <table id="view-entries" class="listtable pc-table table-activity" style="width: 100%;">
                <thead>
                <th class="cell1"></th>
                <th class="cell3"><?php echo __('global:name') ?></th>
                <th class="cell2"><?php echo __('expenses:amount') ?></th>
                <th class="cell2"><?php echo __('expenses:category') ?></th>
                <th class="cell2"><?php echo __('expenses:supplier') ?></th>
                <th class="cell3"><?php echo __('expenses:expense_date') ?></th>
                <th class="cell3"><?php echo __('expenses:receipt') ?></th>
                <th class="cell5"><?php echo __('global:notes') ?></th>
                </thead>

                <tbody>
                    <?php foreach ($expenses as $entry): ?>
                        <tr data-id="<?php echo $entry->id ?>">

                            <td data-title="<?php echo __("global:actions"); ?>" class="td-no-wrap">
                                <?php echo anchor('admin/expenses/edit/' . $entry->id, 'Edit', array('class' => 'fire-ajax edit-entry timesheet-icon edit')) ?>
                                <?php echo anchor('admin/expenses/delete/' . $entry->id, 'Delete', array('class' => 'delete-entry timesheet-icon delete confirm-delete')) ?>
                            </td>

                            <td data-title="<?php echo __('global:name') ?>" class="td-no-wrap">
                                <span class="time-sheet-name"><?php echo $entry->name; ?></span>
                            </td>

                            <td data-title="<?php echo __('expenses:amount') ?>" class="td-no-wrap">
                                <?php if ($entry->rate) echo Currency::format($entry->rate); ?>
                            </td>

                            <td data-title="<?php echo __('expenses:category') ?>" class="td-no-wrap">
                                <?php if ($entry->category_id) echo $entry->category_name; ?>
                            </td>

                            <td data-title="<?php echo __('expenses:supplier') ?>" class="td-no-wrap">
                                <?php if ($entry->supplier_id) echo $entry->supplier_name; ?>
                            </td>

                            <td data-title="<?php echo __('expenses:expense_date') ?>" class="td-no-wrap">
                                <?php if ($entry->due_date) echo format_date(strtotime($entry->due_date)); ?>
                            </td>

                            <td data-title="<?php echo __('expenses:receipt') ?>" class="td-no-wrap">
                                <?php if ($entry->receipt): ?>
                                    <a target="_blank" href="<?php echo site_url("uploads/{$entry->receipt}"); ?>"><?php echo array_end(explode("/", $entry->receipt)); ?></a>
                                <?php else: ?>
                                    <?php echo __('expenses:no_receipt'); ?>
                                <?php endif; ?>
                            </td>

                            <td data-title="<?php echo __('global:notes') ?>" class="td-full-width">
                                <small><?php if ($entry->description) echo nl2br($entry->description); ?></small>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td class='expenses-total-column' colspan='2' style='text-align: right;'>
                            <?php echo __('invoices:total'); ?>
                        </td>
                        <td data-title="<?php echo __('invoices:total'); ?>">
                            <?php echo Currency::format($total); ?>
                        </td>
                        <td colspan="5"></td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>
</div><!-- /row -->



<script>

    var $_GET = <?php echo json_encode($_GET); ?>;

    function start_edit_time(id) {
        $('.view_entries_table').fadeOut(function () {
            $('.edit-entry-' + id).show();
        });
    }

    function submit_edit_time(id) {

        var visible = $('.edit-entry-' + id);

        if (visible.find('.undefined').length > 0) {
            visible.find('.undefined').siblings('input').focus();
            return false;
        } else {
            var startTime = visible.find('.start_time_input').val();
            if (!isNaN(startTime))
                startTime += ':00';
            startTime = Date.parse(startTime).toString('HH:mm');

            var endTime = visible.find('.end_time_input').val();
            if (!isNaN(endTime))
                endTime += ':00';
            endTime = (Date.parse(endTime).toString('HH:mm'));
        }

        var date = $('[name=date-' + id + ']').val();
        var note = visible.find('[name=note]').val();
        var task_id = visible.find('[name=task_id]').val();
        $.post('<?php echo site_url('admin/projects/times/ajax_set_entry') ?>', {
            'id': id,
            'start_time': startTime,
            'end_time': endTime,
            'date': date,
            'note': note,
            'task_id': task_id
        });

        close_reveal();
    }

    jQuery(function ($) {

        $('.start_time_input, .end_time_input').each(function () {

            var val = $(this).val();
            if (!isNaN(val))
                // add minutes to numeric value otherwise it will be interpreted as a date
                val = val + ':00';
            var dt = Date.parse(val);
            if (dt !== null) {
                $(this).siblings('.time').removeClass('undefined');
            } else {
                $(this).siblings('.time').addClass('undefined');
            }
            dt = (dt !== null) ? dt.toString('hh:mm tt') : 'not a valid time';
            $(this).siblings('.time').html(dt);
        });

        $('.start_time_input, .end_time_input').keyup(function (e) {
            var val = $(this).val();
            if (!isNaN(val))
                // add minutes to numeric value otherwise it will be interpreted as a date
                val = val + ':00';
            var dt = Date.parse(val);
            if (dt !== null) {
                $(this).siblings('.time').removeClass('undefined');
            } else {
                $(this).siblings('.time').addClass('undefined');
            }
            dt = (dt !== null) ? dt.toString('hh:mm tt') : 'not a valid time';
            $(this).siblings('.time').html(dt);
        });

        $('.start_time span, .end_time span, .date span').live('click', function () {
            $(this).hide().siblings('input').show();
        });

        $('.start_time input, .end_time input, .date input').live('blur change', function (e) {

            var input = this;
            var row = $(this).closest('tr');

            $.post('<?php echo site_url('admin/projects/times/ajax_set_entry') ?>', {
                'id': row.data('id'),
                'start_time': $('.start_time input', row).val(),
                'end_time': $('.end_time input', row).val(),
                'date': $('.date input', row).datepicker("getDate").getTime()
            }, function (data) {

                $(input).hide().siblings('span').text(input.value).show();

                $('.duration', row).text(data.new_duration);

            }, 'json');

        });

        $('.delete-entry').click(function () {
            if (!confirm('Are you sure?'))
                return false;

            var row = $(this).closest('tr');
            var id = row.data('id');

            $.post(baseURL + 'admin/expenses/ajax_delete_entry', {
                'id': row.data('id'),
            }, function () {
                row.slideUp('slow');
            });

            return false;
        });
    })

    // fix for modal close
    $(".close-reveal-modal").click(function () {
        $(".reveal-modal").trigger("reveal:close");
    });

    // show/hide date on click of "other"
    /*$('#date-other').click(function() {
     if ($('#date').hasClass('hide')) {
     $('#date').removeClass('hide');
     return false;
     } else {
     $('#date').addClass('hide');
     return false;
     }
     }
     );*/

    var currentDateBtn = $('.date-btn.current').attr('id');

    $('.date-btn').click(function (e) {
        e.preventDefault();

        var id = $(this).attr('id');
        if (currentDateBtn != id) {
            $('#' + currentDateBtn).removeClass('current');
            $(this).addClass('current');
            currentDateBtn = id;
        }

        setDateValue();
    });

    function setDateValue() {
        var date = $('#date');
        var day = $('#date-day');

        switch (currentDateBtn) {
            case 'date-today':
                date.addClass('hide');
                //day.val('today');
                break;
            case 'date-yesterday':
                date.addClass('hide');
                //day.val('yesterday');
                break;
            case 'date-other':
                date.removeClass('hide');
                //day.val('other');
                break;
        }
    }

    setDateValue();

</script>