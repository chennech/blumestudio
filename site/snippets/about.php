  <main class="main" role="main">

 	
 	
<div class="row clearfix">
<div class="column four-fifth height medium">

<?php
 	$coverImage = $page->coverimage();
 	?>
 <?php if($image = $page->images()->find((string)$coverImage)): ?>
        <img src="<?php echo $image->url() ?>" alt="<?php echo $page->title()->html() ?>" >
   <?php endif ?>
</div>  
</div>

  
<div class="row clearfix">
	
	<div class="column one-fifth ">
 	<h3><?php echo $page->title()->html() ?></h3>
 	</div>
 
 	<div class="text column two-fifth">
      <?php echo $page->text1()->kirbytext() ?>
 	</div>
 	
 	<div class="text column two-fifth">
 	  <?php echo $page->text2()->kirbytext() ?>
 	</div>

	 
</div>    
     
     
     
       
   
   <div class="row clearfix">
   <div class="text column one-fifth">
   &nbsp;
   </div>
   
   <div class="column one-fifth">
   	<ul class="meta cf">
   	  <li><b>Writings/Talks/Mentions:</b> <?php echo $page->mentions() ?></li>
   	</ul> 
   	</div>
   
   
   <div class="column one-fifth">
   	<ul class="meta cf">
   	  <li><b>Collaborators:</b> <?php echo $page->collaborators() ?></li>   	
   	  </ul> 
   	</div>
   	
   	<div class="column one-fifth">
   		<ul class="meta cf">
   	 <li><b>Disciplines:</b> <?php echo $page->disciplines() ?></li>
   		</ul> 
   		</div>
   	
   
   	   </div>  
      
      
    

  </main>