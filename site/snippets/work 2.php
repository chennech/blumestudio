<!--<h2>Latest projects</h2>-->





<ul class="teaser cf row">
  <?php foreach(page('projects')->children()->visible()->limit(20) as $project): ?>
  <li class="column third">
    
    
    <?php
    	$selectedImage = $project->thumbimage();
    	?>
    
  <!--  <p><?php echo $project->text()->excerpt(80) ?> <a href="<?php echo $project->url() ?>">read&nbsp;more&nbsp;→</a></p>-->
    <?php if($image = $project->images()->find((string)$selectedImage)): ?>
    
    <a href="<?php echo $project->url() ?>">
      <img src="<?php echo $image->url() ?>" alt="<?php echo $project->title()->html() ?>" >
    </a>
    <?php endif ?>
    
    <h4 class="font2"><a href="<?php echo $project->url() ?>"><?php echo $project->title()->html() ?><!--</a>--></h4>
    
  </li>
  <?php endforeach ?>
</ul>
