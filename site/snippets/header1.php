<!DOCTYPE html>
<html lang="en">
<head>

  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0">

  <title><?php echo $site->title()->html() ?> | <?php echo $page->title()->html() ?></title>
  <meta name="description" content="<?php echo $site->description()->html() ?>">
  <meta name="keywords" content="<?php echo $site->keywords()->html() ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
   
 <!-- //Typekit-->
	<script src="//use.typekit.net/ucf8hbf.js"></script>
	<script>try{Typekit.load();}catch(e){}</script>
 
  <!-- JS -->
  
  <?php echo js('http://code.jquery.com/jquery-1.8.3.min.js') ?>
  <?php echo js('assets/ResponsiveSlides/responsiveslides.min.js') ?>
  
  
   	
<script>
  $(function() {
   
         // Slideshow 1
         $("#slider1").responsiveSlides({
            pause: false,
            pager: false,
            speed: 600,
            namespace: "centered-btns"
         });
   
         // Slideshow 2
         $("#slider2").responsiveSlides({
           pause: false,
           pager: false,
           speed: 100,
           namespace: "centered-btns"
         });
     });
     
</script>
</head>

<body>

<div class="container">
  <header class="header cf" role="banner">
  
  
    <div class="row clearfix">
      <div class="column third headertext">
        ◦  design, art, exploration  ∆ est.  2011
      </div>
      <div class="column third ">
       <a class="logo" href="<?php echo url() ?>">
          <img src="<?php echo url('assets/images/blume.svg') ?>" alt="<?php echo $site->title()->html() ?>" />
         </a>
      </div>
      <div class="column third headertext">
        <?php snippet('menu') ?>    
       </div>
    </div>
  
     <!-- <?php snippet('menu') ?>-->
  </header>

