<!--<h2>Latest projects</h2>-->


<ul class="rslides" id="slider1">
  <?php foreach(page('projects')->children()->visible()->limit(10) as $project): ?>
  <li>
    <!--<h3><a href="<?php echo $project->url() ?>"><?php echo $project->title()->html() ?></a></h3>
    <p><?php echo $project->text()->excerpt(80) ?> <a href="<?php echo $project->url() ?>">read&nbsp;more&nbsp;→</a></p>-->
    
     <?php
        $selectedImage = $project->coverimage();
        	?>
        
        
    <?php if($image = $project->images()->find((string)$selectedImage)): ?>
    <!--
    For project URL- Save as an option
    <a href="<?php echo $project->url() ?>"> 
       -->
    <a href="<?php echo page('projects') ?>">
      <img src="<?php echo $image->url() ?>" alt="<?php echo $project->title()->html() ?>" >
    </a>
    
    <?php endif ?>
  </li>
  <?php endforeach ?>
</ul>

