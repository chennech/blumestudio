<!--<h2>Latest projects</h2>-->

<ul class="teaser cf row">

<div class="column one-fifth ">
	<h3><?php echo $page->title()->html() ?></h3>
	<h5>Selected Graphic and interactive projects</h5>
	</div>

<div class="column four-fifth ">	
  <?php foreach(page('projects')->children()->visible()->limit(20) as $project): ?>
  <li class="column third">
    
    
    <?php
    	$selectedImage = $project->thumbimage();
    	?>
    
 
    <?php if($image = $project->images()->find((string)$selectedImage)): ?>
    
    <!-- <div class="workthumb" >
        <h4 class="font2"><a href="<?php echo $project->url() ?>"><?php echo $project->title()->html() ?></a></h4>
    </div>-->
    
    <a href="<?php echo $project->url() ?>">
      <img class="fade"src="<?php echo $image->url() ?>" alt="<?php echo $project->title()->html() ?>" >
    </a>
    <?php endif ?>
   
  

    
  </li>
  <?php endforeach ?>
  </div>
</ul>
 <!--  <p><?php echo $project->text()->excerpt(80) ?> <a href="<?php echo $project->url() ?>">read&nbsp;more&nbsp;→</a></p>-->