 <div class="push"></div>
  </div> <!--end container--> 
  
  <footer class="footer cf row clearfix" role="contentinfo">
   
   <div class="column third center">
      <?php echo $site->copyright()->kirbytext() ?>
    </div>

 <div class="column third center">
 <p>W ◂ live in San Francisco ≈  heart in Tel Aviv ▸ E </p>
 </div>
 
  <div class="column third center">
    Connect with us! <a href="<?php echo $site->twitter() ?>">Twitter</a>
 |  
 <a href="<?php echo $site->instagram() ?>">Instagram</a>
</div>

  </footer>
 
 <?php echo css('assets/css/main.css') ?>	




<?php if(!$site->googleanalytics()->empty()): ?>
  <!-- Google Analytics-->
  <script>
    (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
      (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
      m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
    })(window,document,'script','//www.google-analytics.com/analytics.js','ga');
    ga('create', '<?php echo $site->googleanalytics() ?>', 'auto');
    ga('send', 'pageview');
  </script>
<?php endif ?>
</body>
</html>