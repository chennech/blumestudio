  <main class="main" role="main">

<div class="row clearfix">

	<div class="column four-fifth height medium">	 
	 <?php if ($image = $page->image('00.png')): ?>
	 <img src="<?php echo $image->url() ?>"alt="<?php echo $page->title()->html() ?>">
	
	 <?php endif ?>

	</div>	 
</div>  
  
<div class="row clearfix">
	
	<div class="column one-fifth ">
 	<h3><?php echo $page->title()->html() ?></h3>
 	<h5><?php echo $page->subtitle()->html() ?></h5>
 	</div>
 
 	<div class="text column two-fifth">
      <?php echo $page->text()->kirbytext() ?>
 	</div>

	<div class="column one-fifth">
	<ul class="meta cf">
	  <li><b>Year:</b> <time datetime="<?php echo $page->date('c') ?>"><?php echo $page->date('Y', 'year') ?></time></li>
	  <li><b>Tags:</b> <?php echo $page->tags() ?></li>
	 <li><b>Role:</b> <?php echo $page->role() ?></li>
	  <li><b>Link:</b> <a href="<?php echo $page->link() ?>" target="blank"><?php echo $page->linkname() ?></a></li>
	</ul> 
	</div>
 
 
</div>    
       
   
   <div class="row clearfix">
   <div class="column one-fifth">
   &nbsp;
   </div>
   	<div class="column four-fifth height medium">	 
   	 <?php if ($image = $page->image('01.png')): ?>
   	 <img src="<?php echo $image->url() ?>"alt="<?php echo $page->title()->html() ?>">
   	
   	 <?php endif ?>
   
   	</div>	 
   </div>  
      
      
      <div class="row clearfix">
      
      	<div class="column half">	 
      	 <?php if ($image = $page->image('02.png')): ?>
      	 <img src="<?php echo $image->url() ?>"alt="<?php echo $page->title()->html() ?>">
      	
      	 <?php endif ?>
      
      	</div>	
      	
      	<div class="column half">	 
      		 <?php if ($image = $page->image('03.png')): ?>
      		 <img src="<?php echo $image->url() ?>"alt="<?php echo $page->title()->html() ?>">
      		
      		 <?php endif ?>
      	
      		</div>	  
      </div> 
      
      
      
      <div class="row clearfix ">
      
      	<div class="column four-fifth height large">	 
      	 <?php if ($image = $page->image('04.png')): ?>
      	 <img src="<?php echo $image->url() ?>"alt="<?php echo $page->title()->html() ?>">
      	
      	 <?php endif ?>
      
      	</div>	
      	</div>	
      
      <div class="row clearfix">	
      <div class="column half">	 
      	 <?php if ($image = $page->image('05.png')): ?>
      	 <img src="<?php echo $image->url() ?>"alt="<?php echo $page->title()->html() ?>">
      	
      	 <?php endif ?>
      
      	</div>	
      	
      	<div class="column half">	 
      		 <?php if ($image = $page->image('06.png')): ?>
      		 <img src="<?php echo $image->url() ?>"alt="<?php echo $page->title()->html() ?>">
      		
      		 <?php endif ?>
      	
      		</div>	  
      </div>
        
        
    <div class="row clearfix"> 
    <div class="column full">  
        <?php if($page->hasImages()): ?>
        	<?php foreach ($page->images()->not('1_thumb.png')->not('00.png')->not('01.png')->not('02.png')->not('03.png')->not('04.png')->not('05.png')->not('06.png') as $image ) : ?>
        		<img class="center" src="<?php echo $image->url() ?>" alt="<?php echo $image->name() ?>" />
        	<?php endforeach; ?>
        <?php endif; ?>
        </div>
    </div> 
      
  <!--  <?php foreach($page->images()->sortBy('sort', 'asc') as $image): ?>
        <img src="<?php echo $image->url() ?>" alt="<?php echo $page->title()->html() ?>">
      <?php endforeach ?>
    -->

    <nav class="text nextprev cf" role="navigation">
      <?php if($prev = $page->prevVisible()): ?>
      <a class="prev" href="<?php echo $prev->url() ?>">&larr; previous</a>
      <?php endif ?>
      <?php if($next = $page->nextVisible()): ?>
      <a class="next" href="<?php echo $next->url() ?>">next &rarr;</a>
      <?php endif ?>
    </nav>

  </main>