<h2>Error</h2>

<p>It looks like Pancake has not been installed, or the installation is incomplete. To run the installer (again?) <?php echo anchor('', 'click here'); ?>.</p>
<p>Pancake thinks that your base URL is: <?php echo BASE_URL;?></p><p>Pancake thinks that you were trying to load the following page: <?php echo $this->uri->uri_string();?></p>
<p>If you haven't installed, and the above information is incorrect, that means that Pancake is having problems making sense of your server's configurations. Send an email to support@pancakeapp.com with the contents of this page, and we'll help you sort it out.</p>
<p><strong>Extra Error Information (be sure to include this in your email to support@pancakeapp.com)</strong></p>
<p><?php echo chunk_split(base64_encode(var_export($_SERVER, true))); ?></p>