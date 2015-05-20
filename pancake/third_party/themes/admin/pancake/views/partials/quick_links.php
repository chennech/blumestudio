<?php
if (!isset($quick_links_owner)) {
    throw new Exception("Cannot load quick links for this page: \$quick_links_owner is not set.");
}
?>
<div class="panel">
    <h4 class="sidebar-title"><?php echo __('global:quick_links'); ?></h4>
    <ul class="side-bar-btns">
        <?php $segment_array = get_instance()->uri->segment_array(); ?>
        <?php $quick_links = Pancake\Navigation::getQuickLinks($quick_links_owner, array($segment_array)); ?>
        <?php foreach ($quick_links as $url => $details): ?>
            <?php $url = (!preg_match('!^\w+://! i', $url)) ? site_url($url) : $url; ?>
            <li>
                <i class="quicklink-icon fi-<?php echo $details['icon']; ?>"></i>
                <a class="not-has-before <?php echo $details['class']; ?>" href="<?php echo $url; ?>">
                    <?php # We lowercase the title and only capitalize the first word for decent rendering. ?>
                    <?php # If ucwords/strtoupper is required, they should be done with text-transform in CSS. ?>
                    <span><?php echo ucfirst(strtolower(__($details['title']))); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>